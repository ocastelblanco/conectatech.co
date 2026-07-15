<?php
/**
 * MoodleSectionCloner.php
 * Clona una sección de un curso repositorio a un curso final
 * usando la API nativa de Backup/Restore de Moodle.
 *
 * Requiere el entorno Moodle inicializado (MoodleBootstrap.php).
 */

class MoodleSectionCloner
{
    /**
     * Clona una sección (1-based) desde un curso origen al curso destino.
     *
     * @param int $sourceCourseId  ID numérico del curso repositorio en Moodle
     * @param int $sectionNum      Número de sección 1-based (H1 en el markdown)
     * @param int $targetCourseId  ID numérico del curso final en Moodle
     * @param int $adminUserId     ID del usuario administrador para el backup/restore
     * @throws RuntimeException    Si la sección no existe o el backup/restore falla
     */
    public function cloneSection(
        int $sourceCourseId,
        int $sectionNum,
        int $targetCourseId,
        int $adminUserId
    ): void {
        global $CFG, $DB;

        require_once($CFG->dirroot . '/backup/util/includes/backup_includes.php');
        require_once($CFG->dirroot . '/backup/util/includes/restore_includes.php');

        // ── 1. Resolver section_id ────────────────────────────────────────────
        $sectionRecord = $DB->get_record('course_sections', [
            'course'  => $sourceCourseId,
            'section' => $sectionNum,
        ]);

        if (!$sectionRecord) {
            throw new RuntimeException(
                "Sección {$sectionNum} no encontrada en curso ID {$sourceCourseId}"
            );
        }

        $sectionId = (int)$sectionRecord->id;

        // Guardar el máximo ID de mdl_files para identificar el archivo de backup
        $beforeMaxId = (int)$DB->get_field_sql('SELECT COALESCE(MAX(id),0) FROM {files}');

        // ── 2. Backup de la sección ───────────────────────────────────────────
        $bc = new backup_controller(
            backup::TYPE_1SECTION,
            $sectionId,
            backup::FORMAT_MOODLE,
            backup::INTERACTIVE_NO,
            backup::MODE_SAMESITE,
            $adminUserId
        );

        // Sin datos de usuarios ni logs
        $bc->get_plan()->get_setting('users')->set_value(false);

        // Desactivar logs para evitar spam en output CLI
        try {
            $bc->get_plan()->get_setting('logs')->set_value(false);
        } catch (base_setting_exception $e) {
            // Algunos planes no tienen el setting 'logs' — ignorar
        }

        $bc->execute_plan();
        $backupid = $bc->get_backupid();
        $bc->destroy();

        // ── 3. Localizar backup.mbz en filedir ───────────────────────────────
        // El backup guarda el .mbz en mdl_files (component='backup', filename='backup.mbz').
        // Identificamos el registro recién creado buscando por ID > beforeMaxId.
        $backupFileRec = $DB->get_record_sql(
            "SELECT contenthash
             FROM {files}
             WHERE id > ?
               AND component = 'backup'
               AND filename  = 'backup.mbz'
             ORDER BY id DESC
             LIMIT 1",
            [$beforeMaxId]
        );

        if (!$backupFileRec) {
            throw new RuntimeException(
                "No se encontró backup.mbz en mdl_files para backupid={$backupid}"
            );
        }

        $hash     = $backupFileRec->contenthash;
        $filePath = $CFG->dataroot . '/filedir/'
                  . substr($hash, 0, 2) . '/'
                  . substr($hash, 2, 2) . '/'
                  . $hash;

        if (!file_exists($filePath)) {
            throw new RuntimeException("backup.mbz no encontrado en filedir: {$filePath}");
        }

        // ── 4. Extraer .mbz al directorio temporal del restore ────────────────
        // restore_controller espera un subdirectorio en $CFG->backuptempdir con
        // el contenido descomprimido del .mbz (incluyendo moodle_backup.xml).
        $tempdir = make_backup_temp_directory($backupid);
        $packer  = get_file_packer('application/vnd.moodle.backup');

        if (!$packer->extract_to_pathname($filePath, $tempdir)) {
            throw new RuntimeException("Error al extraer backup.mbz a {$tempdir}");
        }

        // ── 4b. Renumerar la sección del backup al final del curso destino ────
        // restore_section_structure_step::process_section() busca en el destino
        // una sección no-delegada con el MISMO número que trae el backup: si
        // existe, FUSIONA los módulos dentro de ella en vez de crear una nueva;
        // si no existe, la crea con ese número dejando placeholders vacíos en
        // los huecos. Reescribir <number> a MAX(section)+1 del destino garantiza
        // que el restore siempre cree una sección nueva al final, sin fusiones
        // ni placeholders.
        $this->renumerarSeccionEnBackup($tempdir, $sectionId, $targetCourseId);

        // ── 5. Restore al curso destino ───────────────────────────────────────
        $rc = new restore_controller(
            $backupid,
            $targetCourseId,
            backup::INTERACTIVE_NO,
            backup::MODE_SAMESITE,
            $adminUserId,
            backup::TARGET_EXISTING_ADDING
        );

        $rc->execute_precheck();
        $rc->execute_plan();
        $rc->destroy();
    }

    /**
     * Reescribe el <number> de la sección principal (no delegada) en el backup
     * extraído, asignándole el siguiente número de sección libre del curso
     * destino. Las secciones delegadas (mod_subsection) incluidas en el backup
     * no se tocan: el restore siempre las crea nuevas al final del curso.
     *
     * @param string $tempdir         Directorio del backup extraído
     * @param int    $sectionId       ID (mdl_course_sections.id) de la sección origen
     * @param int    $targetCourseId  ID del curso destino
     * @throws RuntimeException Si el section.xml no existe o no se puede reescribir
     */
    private function renumerarSeccionEnBackup(
        string $tempdir,
        int $sectionId,
        int $targetCourseId
    ): void {
        global $DB;

        $sectionXml = "{$tempdir}/sections/section_{$sectionId}/section.xml";

        if (!file_exists($sectionXml)) {
            throw new RuntimeException(
                "section.xml no encontrado en el backup extraído: {$sectionXml}"
            );
        }

        $maxSection = (int)$DB->get_field_sql(
            'SELECT COALESCE(MAX(section), 0) FROM {course_sections} WHERE course = ?',
            [$targetCourseId]
        );
        $newNumber = $maxSection + 1;

        $contents = file_get_contents($sectionXml);
        if ($contents === false) {
            throw new RuntimeException("No se pudo leer {$sectionXml}");
        }

        $updated = preg_replace(
            '/<number>\d+<\/number>/',
            "<number>{$newNumber}</number>",
            $contents,
            1,
            $count
        );

        if ($updated === null || $count !== 1) {
            throw new RuntimeException(
                "No se encontró el elemento <number> en {$sectionXml}"
            );
        }

        if (file_put_contents($sectionXml, $updated) === false) {
            throw new RuntimeException("No se pudo escribir {$sectionXml}");
        }
    }

    /**
     * Resuelve el ID numérico de un curso a partir de su shortname.
     *
     * @param  string $shortname
     * @return int
     * @throws RuntimeException Si el curso no existe
     */
    public static function resolveCourseId(string $shortname): int
    {
        global $DB;

        $course = $DB->get_record('course', ['shortname' => $shortname]);

        if (!$course) {
            throw new RuntimeException("Curso no encontrado: '{$shortname}'");
        }

        return (int)$course->id;
    }

    /**
     * Devuelve el ID del usuario admin (el primero con rol siteadmin).
     */
    public static function getAdminUserId(): int
    {
        global $DB;

        $admins = get_admins();
        if (empty($admins)) {
            throw new RuntimeException("No se encontró ningún usuario administrador en Moodle.");
        }

        return (int)reset($admins)->id;
    }
}
