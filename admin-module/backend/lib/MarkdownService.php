<?php
/**
 * MarkdownService.php — Lógica de negocio para procesar Markdown en Moodle.
 *
 * Wrapper sobre MarkdownParser, HtmlConverter, PresaberesHtmlBuilder,
 * GiftConverter y MoodleContentBuilder. Expone una interfaz que acepta
 * tanto una ruta de archivo (uso CLI) como contenido en string (uso API).
 *
 * Requiere que las clases del pipeline estén cargadas antes de instanciar.
 *
 * Uso típico desde API (contenido como string):
 *   $service = new MarkdownService(CONFIG_DIR);
 *   $result  = $service->procesarContenido($shortname, $courseData, $markdownString);
 *
 * Uso típico desde CLI (archivo en disco):
 *   $result = $service->procesarArchivo($shortname, $courseData, '/tmp/archivo.md');
 */

class MarkdownService
{
    private string $configDir;

    /**
     * @param string $configDir  Ruta absoluta al directorio config/ del backend.
     *                           Se usa para localizar semantic-blocks.json.
     */
    public function __construct(string $configDir)
    {
        $this->configDir = $configDir;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Operaciones públicas
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Procesa contenido Markdown recibido como string.
     *
     * Escribe el contenido en un archivo temporal, lo procesa con el pipeline
     * completo y elimina el temporal al finalizar (incluso si hay errores).
     * Esta variante es la que usa el handler de API.
     *
     * @param string $shortname   Shortname del curso repositorio en Moodle.
     * @param array  $courseData  ['shortname', 'fullname', 'category_path'].
     * @param string $content     Contenido Markdown como string.
     * @return array              Ver retorno de procesarArchivo().
     */
    public function procesarContenido(string $shortname, array $courseData, string $content): array
    {
        $tmpFile = sys_get_temp_dir() . '/ct_md_' . uniqid() . '.md';
        file_put_contents($tmpFile, $content);

        try {
            return $this->procesarArchivo($shortname, $courseData, $tmpFile);
        } finally {
            @unlink($tmpFile);
        }
    }

    /**
     * Procesa un archivo Markdown en disco y crea/actualiza las secciones del curso.
     *
     * Pipeline completo:
     *   1. MarkdownParser      → árbol de secciones/subsecciones
     *   2. HtmlConverter       → bloques semánticos a HTML
     *   3. PresaberesHtmlBuilder → HTML interactivo para presaberes
     *   4. GiftConverter       → preguntas en formato GIFT
     *   5. MoodleContentBuilder → secciones, labels y quizzes en Moodle
     *
     * @param string $shortname   Shortname del curso repositorio en Moodle.
     * @param array  $courseData  ['shortname', 'fullname', 'category_path'].
     * @param string $filePath    Ruta absoluta al archivo .md.
     * @return array {
     *   sections: array,
     *   summary: {
     *     total_sections: int, sections_created: int,
     *     sections_updated: int, total_errors: int
     *   },
     *   errors: string[]
     * }
     */
    public function procesarArchivo(string $shortname, array $courseData, string $filePath): array
    {
        $parser   = new MarkdownParser();
        $sections = $parser->parse($filePath);

        $htmlConverter   = new HtmlConverter($this->configDir . '/semantic-blocks.json');
        $feedbackMode    = $htmlConverter->getPresaberesFeedbackMode();
        $presHtmlBuilder = new PresaberesHtmlBuilder($feedbackMode, $shortname);
        $giftConverter   = new GiftConverter();

        $builder = new MoodleContentBuilder(
            $shortname,
            $courseData,
            $htmlConverter,
            $presHtmlBuilder,
            $giftConverter
        );

        $builder->ensureCourse();
        $builder->resetCourse();   // Reemplaza el curso completo (markdown = fuente de verdad)

        $result = [
            'sections' => [],
            'summary'  => [],
            'errors'   => [],
        ];

        $createdCount = 0;
        $updatedCount = 0;

        foreach ($sections as $index => $section) {
            $sectionResult    = $builder->processSection($section, $index + 1);
            $result['sections'][] = $sectionResult;

            if (!empty($sectionResult['errors'])) {
                foreach ($sectionResult['errors'] as $err) {
                    $result['errors'][] = "[Sección " . ($index + 1) . "] {$err}";
                }
            }

            if ($sectionResult['action'] === 'created') {
                $createdCount++;
            } elseif ($sectionResult['action'] === 'updated') {
                $updatedCount++;
            }

            gc_collect_cycles();
        }

        $result['summary'] = [
            'total_sections'   => count($sections),
            'sections_created' => $createdCount,
            'sections_updated' => $updatedCount,
            'total_errors'     => count($result['errors']),
        ];

        return $result;
    }
}
