<?php
/**
 * handlers/markdown.php — Handler para procesamiento de Markdown en Moodle.
 *
 * POST /api/markdown
 */

// ─────────────────────────────────────────────────────────────────────────────
// POST /api/markdown
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Procesa contenido Markdown y crea/actualiza secciones en un curso repositorio.
 *
 * El frontend lee el archivo .md del disco (o permite pegarlo) y envía
 * su contenido como string en el campo "content". No hay upload de archivos.
 *
 * Request body:
 *   {
 *     "shortname": "repo-cc-cn-6-7",
 *     "content":   "# Sección 1\n\nContenido del Markdown..."
 *   }
 *
 * Response 200:
 *   { ok: bool,
 *     summary: { total_sections, sections_created, sections_updated, total_errors },
 *     errors: string[] }
 *
 * Response 404: si el shortname no está en courses.csv.
 */
function handleMarkdown(): void
{
    $body = readJsonBody();

    if (empty($body['shortname'])) {
        badRequest("Se requiere 'shortname' del curso repositorio.");
    }

    if (empty($body['content'])) {
        badRequest("Se requiere 'content' con el Markdown como string.");
    }

    $shortname  = trim($body['shortname']);
    $coursesMap = loadCoursesMapForApi();

    if (!isset($coursesMap[$shortname])) {
        http_response_code(404);
        echo json_encode([
            'ok'        => false,
            'error'     => "Shortname '{$shortname}' no encontrado en courses.csv.",
            'available' => array_keys($coursesMap),
        ]);
        return;
    }

    $service = new MarkdownService(CONFIG_DIR);
    $result  = $service->procesarContenido($shortname, $coursesMap[$shortname], $body['content']);

    echo json_encode([
        'ok'      => empty($result['errors']),
        'summary' => $result['summary'],
        'errors'  => $result['errors'],
    ]);
}

/**
 * Carga el mapa shortname → datos del curso desde courses.csv.
 * Función local del handler para no acoplar al MarkdownService con el CSV.
 *
 * @return array<string, array{shortname: string, fullname: string, category_path: string}>
 */
function loadCoursesMapForApi(): array
{
    $csvPath = CONFIG_DIR . '/courses.csv';

    if (!file_exists($csvPath)) {
        throw new RuntimeException("No se encontró courses.csv en: {$csvPath}");
    }

    $map = [];
    $fh  = fopen($csvPath, 'r');
    fgetcsv($fh); // saltar cabecera

    while ($row = fgetcsv($fh)) {
        if (count($row) < 3) {
            continue;
        }
        $sn       = trim($row[0]);
        $map[$sn] = [
            'shortname'     => $sn,
            'fullname'      => trim($row[1]),
            'category_path' => trim($row[2]),
        ];
    }

    fclose($fh);
    return $map;
}
