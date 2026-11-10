<?php
/**
 * VisorService.php
 * Arma, leyendo Moodle en vivo, el contenido expositivo de un tema (sección H1)
 * para el visor fullscreen (fullscreen-viewer/). Solo lectura.
 *
 * Contrato (ver tech-specs.md §6.4):
 *   titulo   string        Nombre de la sección.
 *   cabecera string|null   HTML del referente bíblico del tema (label con .cita-biblica
 *                          anterior al primer subtema).
 *   subtemas array         [{titulo, bloques: [html, ...]}] en el orden de la sección.
 *
 * Mapeo respecto al pipeline Markdown (MoodleContentBuilder::processSubsection):
 *   - mod_subsection        → un subtema; sus mod_label son los bloques.
 *   - mod_label suelto      → un subtema propio (H2 sin H3, "h2-texto-directo").
 *   - Se omiten: el label del botón (.ct-exponer), presaberes, quizzes, subsecciones
 *     sin bloques (evaluaciones) y labels cuyo único contenido es un <iframe>.
 *
 * Dependencias de Moodle sensibles a un upgrade mayor (revisar en cada uno):
 *   get_fast_modinfo(), cm_info::get_formatted_content(), get_section_name() y la
 *   relación course_sections.component = 'mod_subsection' / itemid = subsection.id.
 */

class VisorService
{
    /** Marcas de HTML que identifican contenido que NO debe mostrar el visor. */
    private const MARCAS_EXCLUIDAS = ['ct-exponer', 'data-quiz-block', 'moodle-quiz-container'];

    /**
     * @return array{titulo:string, cabecera:?string, subtemas:array}|null
     *         null si la sección no existe en el curso indicado.
     */
    public function obtenerTema(int $courseId, int $sectionId): ?array
    {
        global $DB, $CFG, $PAGE;
        require_once($CFG->dirroot . '/course/lib.php');

        $course  = $DB->get_record('course', ['id' => $courseId], '*', MUST_EXIST);
        $section = $DB->get_record('course_sections', ['id' => $sectionId, 'course' => $courseId]);

        // Una sección delegada (subtema) no es un tema: el visor solo abre secciones H1.
        if (!$section || (string)$section->component !== '') {
            return null;
        }

        // format_module_intro()/format_text() necesitan un contexto de página.
        $PAGE->set_context(context_course::instance($courseId));

        $modinfo     = get_fast_modinfo($course);
        $sectionInfo = $modinfo->get_section_info((int)$section->section, MUST_EXIST);
        if (!$sectionInfo->uservisible) {
            return null;
        }

        $cabecera = null;
        $subtemas = [];

        foreach ($modinfo->sections[(int)$section->section] ?? [] as $cmId) {
            $cm = $modinfo->cms[$cmId] ?? null;
            if (!$cm || !$cm->uservisible || $cm->deletioninprogress) {
                continue;
            }

            if ($cm->modname === 'subsection') {
                $bloques = $this->bloquesDeSubseccion($modinfo, (int)$cm->instance);
                if ($bloques) {
                    $subtemas[] = ['titulo' => $this->limpiarTitulo($cm->name), 'bloques' => $bloques];
                }
                continue;
            }

            if ($cm->modname !== 'label') {
                continue; // quizzes y cualquier otra actividad
            }

            $html = $this->htmlDeLabel($cm);
            if ($html === null) {
                continue;
            }

            // El referente bíblico del tema va antes del primer subtema.
            if ($cabecera === null && !$subtemas && str_contains($html, 'cita-biblica')) {
                $cabecera = $html;
                continue;
            }

            $subtemas[] = [
                'titulo'  => $this->tituloDesdeHtml($html, (string)$cm->name),
                'bloques' => [$html],
            ];
        }

        return [
            'titulo'   => $this->limpiarTitulo(get_section_name($course, $sectionInfo)),
            'cabecera' => $cabecera,
            'subtemas' => $subtemas,
        ];
    }

    /** Labels (en orden) de la sección delegada de un mod_subsection. */
    private function bloquesDeSubseccion(course_modinfo $modinfo, int $subsectionInstanceId): array
    {
        global $DB;

        // itemid = id de la tabla subsection (NO el cmid). Ver admin-module/CLAUDE.md.
        $delegada = $DB->get_record('course_sections', [
            'course'    => $modinfo->courseid,
            'component' => 'mod_subsection',
            'itemid'    => $subsectionInstanceId,
        ]);
        if (!$delegada) {
            return [];
        }

        $bloques = [];
        foreach ($modinfo->sections[(int)$delegada->section] ?? [] as $cmId) {
            $cm = $modinfo->cms[$cmId] ?? null;
            if (!$cm || !$cm->uservisible || $cm->deletioninprogress || $cm->modname !== 'label') {
                continue;
            }
            $html = $this->htmlDeLabel($cm);
            if ($html !== null) {
                $bloques[] = $html;
            }
        }
        return $bloques;
    }

    /** HTML formateado del label, o null si el visor no debe mostrarlo. */
    private function htmlDeLabel(cm_info $cm): ?string
    {
        $html = trim((string)$cm->get_formatted_content());
        if ($html === '') {
            return null;
        }
        foreach (self::MARCAS_EXCLUIDAS as $marca) {
            if (str_contains($html, $marca)) {
                return null;
            }
        }
        // Un label cuyo único contenido es un <iframe> (p. ej. visor PDF) no se puede
        // mostrar en el visor: el sanitizador de Angular descarta los iframes.
        $sinIframe = preg_replace('#<iframe\b.*?</iframe>#is', '', $html);
        if (trim(strip_tags((string)$sinIframe)) === '' && !preg_match('#<img\b#i', (string)$sinIframe)) {
            return null;
        }
        return $html;
    }

    /** Primer encabezado del HTML; si no hay, el nombre del módulo; si no, "Contenido". */
    private function tituloDesdeHtml(string $html, string $nombreModulo): string
    {
        if (preg_match('#<h[1-6]\b[^>]*>(.*?)</h[1-6]>#is', $html, $m)) {
            $t = $this->limpiarTitulo($m[1]);
            if ($t !== '') {
                return $t;
            }
        }
        $nombre = $this->limpiarTitulo($nombreModulo);
        return ($nombre !== '' && $nombre !== 'label') ? $nombre : 'Contenido';
    }

    private function limpiarTitulo(string $texto): string
    {
        return trim(html_entity_decode(strip_tags($texto), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    }
}
