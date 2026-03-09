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

        return $result;
    }
}
