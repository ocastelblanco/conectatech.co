<?php
/**
 * PobladorService.php — Lógica de negocio para poblar cursos finales.
 *
 * Wrapper delgado sobre MoodleSectionCloner que expone una interfaz orientada
 * a "poblar un curso" en lugar de "clonar una sección". Centraliza el manejo
 * de errores por sección y la liberación de memoria entre clonaciones.
 *
 * Requiere que MoodleSectionCloner.php esté cargado antes de instanciar.
 *
 * Uso típico:
 *   $service = new PobladorService();
 *   $result  = $service->poblarCurso($shortname, $sections, $dryRun);
 */

class PobladorService
{
    private MoodleSectionCloner $cloner;
    private int $adminId;

    public function __construct()
    {
        $this->cloner  = new MoodleSectionCloner();
        $this->adminId = MoodleSectionCloner::getAdminUserId();
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Operación pública
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Puebla un curso final clonando secciones desde cursos repositorio.
     *
     * Itera sobre cada sección del mapping, clona con backup/restore nativo
     * de Moodle y agrega la sección al final del curso destino. Los errores
     * por sección no detienen el proceso; se registran y continúa con la siguiente.
     *
     * @param string $targetSn  Shortname del curso final destino.
     * @param array  $sections  Array de configuraciones de sección: [
     *   ['repo' => 'repo-cc-cn-6-7', 'section_num' => 1],
     *   ...
     * ]
     * @param bool $dryRun  Si true, valida los IDs pero no ejecuta clonaciones.
     * @return array {
     *   shortname: string,
     *   target_id: int|null,
     *   sections: [{repo, section_num, action, error}],
     *   errors: string[],
     *   cloned: int
     * }
     * @throws RuntimeException Si el curso destino no existe.
     */
    public function poblarCurso(string $targetSn, array $sections, bool $dryRun = false): array
    {
        $targetId = MoodleSectionCloner::resolveCourseId($targetSn);

        $result = [
            'shortname' => $targetSn,
            'target_id' => $targetId,
            'sections'  => [],
            'errors'    => [],
            'cloned'    => 0,
        ];

        foreach ($sections as $secConfig) {
            $repoSn     = $secConfig['repo'];
            $sectionNum = (int)$secConfig['section_num'];

            $secResult = [
                'repo'        => $repoSn,
                'section_num' => $sectionNum,
                'action'      => '',
                'error'       => null,
            ];

            try {
                $repoId = MoodleSectionCloner::resolveCourseId($repoSn);

                if ($dryRun) {
                    $secResult['action'] = 'dry-run';
                } else {
                    $this->cloner->cloneSection($repoId, $sectionNum, $targetId, $this->adminId);
                    $secResult['action'] = 'cloned';
                    $result['cloned']++;
                }
            } catch (Throwable $e) {
                $secResult['action'] = 'error';
                $secResult['error']  = $e->getMessage();
                $result['errors'][]  = "[{$repoSn} §{$sectionNum}] " . $e->getMessage();
            }

            $result['sections'][] = $secResult;

            // Liberar memoria entre clonaciones (backup/restore es costoso)
            gc_collect_cycles();
        }

        // Eliminar secciones placeholder vacías que Moodle crea automáticamente
        // cuando la sección origen tiene section_num > 1 (ej. secciones 8-10 en
        // un repositorio compartido). TARGET_EXISTING_ADDING preserva el número
        // de sección origen, lo que crea huecos vacíos en el curso destino.
        if (!$dryRun && $result['cloned'] > 0) {
            $this->eliminarPlaceholdersVacios($targetId);
        }

        return $result;
    }

    /**
     * Elimina secciones vacías (sin nombre, sin summary, sin módulos) que Moodle
     * genera como placeholder cuando el restore preserva un section_num > posición
     * actual del cursor en el curso destino.
     *
     * Moodle auto-renumera las secciones al borrar, por lo que no se requiere
     * renumeración explícita posterior.
     */
    private function eliminarPlaceholdersVacios(int $targetId): void
    {
        global $DB, $CFG;

        require_once($CFG->dirroot . '/course/lib.php');

        $course = $DB->get_record('course', ['id' => $targetId]);
        if (!$course) {
            return;
        }

        // Buscar secciones > 0 sin nombre, sin summary y sin módulos asignados
        $placeholders = $DB->get_records_sql(
            "SELECT id, section, name
               FROM {course_sections}
              WHERE course   = ?
                AND section  > 0
                AND (name     IS NULL OR name     = '')
                AND (summary  IS NULL OR summary  = '')
                AND (sequence IS NULL OR sequence = '')",
            [$targetId]
        );

        if (empty($placeholders)) {
            return;
        }

        // Borrar de mayor a menor para aprovechar el auto-renumerado de Moodle
        usort($placeholders, fn($a, $b) => (int)$b->section - (int)$a->section);

        foreach ($placeholders as $s) {
            course_delete_section($course, $s, true);
        }

        rebuild_course_cache($targetId, true);
    }
}
