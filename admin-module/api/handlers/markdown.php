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
 *     "shortname": "REP-CC-MA-6-7",
 *     "content":   "# Sección 1\n\nContenido del Markdown..."
 *   }
 *
 * Response 200:
 *   { ok: bool,
 *     summary: { total_sections, sections_created, sections_updated, total_errors },
 *     errors: string[] }
 *
 * Response 404: si el shortname no existe en Moodle.
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
    $courseData = lookupCourseFromMoodle($shortname);

    if ($courseData === null) {
        http_response_code(404);
        while (ob_get_level() > 0) { ob_end_clean(); }
        echo json_encode([
            'ok'    => false,
            'error' => "Curso con shortname '{$shortname}' no encontrado en Moodle.",
        ]);
        return;
    }

    $service = new MarkdownService(CONFIG_DIR);
    $result  = $service->procesarContenido($shortname, $courseData, $body['content']);

    while (ob_get_level() > 0) { ob_end_clean(); }
    echo json_encode([
        'ok'      => empty($result['errors']),
        'summary' => $result['summary'],
        'errors'  => $result['errors'],
    ]);
}

/**
 * Busca un curso en Moodle por shortname y construye el array courseData
 * necesario para MarkdownService / MoodleContentBuilder.
 *
 * category_path se reconstruye recorriendo la jerarquía de course_categories.
 * Solo se usa si el curso no existe aún (ensureCourse lo crearía); si el curso
 * ya existe ensureCourse hace early-return y no lo necesita.
 *
 * @return array{shortname: string, fullname: string, category_path: string}|null
 */
function lookupCourseFromMoodle(string $shortname): ?array
{
    global $DB;

    $course = $DB->get_record('course', ['shortname' => $shortname]);

    if (!$course) {
        return null;
    }

    // Reconstruir la ruta de categoría (p.ej. "Repositorios / Ciencias Naturales")
    $categoryPath = buildCategoryPath((int) $course->category);

    return [
        'shortname'     => $course->shortname,
        'fullname'      => $course->fullname,
        'category_path' => $categoryPath,
    ];
}

/**
 * Recorre la jerarquía de course_categories hacia arriba y devuelve la ruta
 * completa separada por " / " (sin incluir la categoría raíz "Miscellaneous").
 */
function buildCategoryPath(int $categoryId): string
{
    global $DB;

    $parts = [];
    $currentId = $categoryId;

    while ($currentId > 0) {
        $cat = $DB->get_record('course_categories', ['id' => $currentId], 'id, name, parent');
        if (!$cat) {
            break;
        }
        array_unshift($parts, $cat->name);
        $currentId = (int) $cat->parent;
    }

    return implode(' / ', $parts);
}
