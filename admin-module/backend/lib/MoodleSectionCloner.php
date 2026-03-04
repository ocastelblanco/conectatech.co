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

        // ── 3. Restore al curso destino ───────────────────────────────────────
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
