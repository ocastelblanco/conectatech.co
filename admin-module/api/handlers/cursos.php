<?php
/**
 * handlers/cursos.php — Handlers para operaciones sobre cursos finales.
 *
 * GET  /api/cursos               → listar cursos existentes en Moodle
 * POST /api/cursos/crear         → crear cursos desde array JSON
 * POST /api/cursos/poblar        → poblar cursos con secciones de repositorios
 */

// ─────────────────────────────────────────────────────────────────────────────
// GET /api/cursos
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Lista los cursos de Moodle, opcionalmente filtrados por categoría.
 *
 * Query params:
 *   ?category=COLEGIOS/San Marino   Filtra por jerarquía de categoría.
 *                                   Si se omite, devuelve todos los cursos.
 *
 * Response 200:
 *   { ok: true, cursos: [{id, shortname, fullname, category, visible}], total: int }
 */
function handleGetCursos(): void
{
    $category = $_GET['category'] ?? '';
    $service  = new CursosService(CONFIG_DIR);
    $cursos   = $service->listarCursos($category);

    echo json_encode([
        'ok'     => true,
        'cursos' => $cursos,
        'total'  => count($cursos),
    ]);
}

// ─────────────────────────────────────────────────────────────────────────────
// POST /api/cursos/crear
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Crea cursos finales en Moodle desde un array JSON.
 *
 * El frontend envía el array resultante de convertir el Excel con ExcelJS.
 * Si un curso ya existe se omite sin error. La operación es idempotente.
 *
 * Request body:
 *   {
 *     "dry_run": false,
 *     "cursos": [
 *       { "shortname": "san-marino-cn-6", "fullname": "Ciencias Naturales - 6",
 *         "category": "COLEGIOS/San Marino/Ciencias Naturales",
 *         "templatecourse": "PL-CC-CN" }
 *     ]
 *   }
 *
 * Response 200:
 *   { ok: bool, dry_run: bool,
 *     summary: { created: int, skipped: int, errors: int },
 *     results: [{ shortname, fullname, action, course_id, error }] }
 */
function handleCrearCursos(): void
{
    $body = readJsonBody();

    if (empty($body['cursos']) || !is_array($body['cursos'])) {
        badRequest("Se requiere 'cursos' como array no vacío.");
    }

    $dryRun  = (bool)($body['dry_run'] ?? false);
    $service = new CursosService(CONFIG_DIR);

    $results = [];
    $summary = ['created' => 0, 'skipped' => 0, 'errors' => 0];

    foreach ($body['cursos'] as $curso) {
        try {
            $result = $service->crearCurso($curso, $dryRun);
        } catch (Throwable $e) {
            $result = [
                'shortname' => $curso['shortname'] ?? '',
                'action'    => 'error',
                'course_id' => null,
                'error'     => $e->getMessage(),
            ];
        }

        match ($result['action']) {
            'created'  => $summary['created']++,
            'skipped'  => $summary['skipped']++,
            'error'    => $summary['errors']++,
            default    => null,
        };

        $results[] = $result;
    }

    echo json_encode([
        'ok'      => $summary['errors'] === 0,
        'dry_run' => $dryRun,
        'summary' => $summary,
        'results' => $results,
    ]);
}

// ─────────────────────────────────────────────────────────────────────────────
// POST /api/cursos/poblar
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Puebla cursos finales clonando secciones desde cursos repositorio.
 *
 * Request body:
 *   {
 *     "dry_run": false,
 *     "courses": [
 *       {
 *         "shortname": "san-marino-cn-6",
 *         "sections": [
 *           { "repo": "repo-cc-cn-6-7", "section_num": 1 },
 *           { "repo": "repo-cc-cn-6-7", "section_num": 2 }
 *         ]
 *       }
 *     ]
 *   }
 *
 * Response 200:
 *   { ok: bool, dry_run: bool,
 *     summary: { cloned: int, errors: int },
 *     results: [{ shortname, target_id, sections, errors, cloned }] }
 */
function handlePoblarCursos(): void
{
    $body = readJsonBody();

    if (empty($body['courses']) || !is_array($body['courses'])) {
        badRequest("Se requiere 'courses' como array no vacío.");
    }

    $dryRun  = (bool)($body['dry_run'] ?? false);
    $service = new PobladorService();

    $results = [];
    $summary = ['cloned' => 0, 'errors' => 0];

    foreach ($body['courses'] as $courseConfig) {
        $targetSn = $courseConfig['shortname'] ?? '';
        $sections = $courseConfig['sections']  ?? [];

        try {
            $result             = $service->poblarCurso($targetSn, $sections, $dryRun);
            $summary['cloned'] += $result['cloned'];
            $summary['errors'] += count($result['errors']);
        } catch (Throwable $e) {
            $result = [
                'shortname' => $targetSn,
                'target_id' => null,
                'sections'  => [],
                'errors'    => [$e->getMessage()],
                'cloned'    => 0,
            ];
            $summary['errors']++;
        }

        $results[] = $result;
    }

    echo json_encode([
        'ok'      => $summary['errors'] === 0,
        'dry_run' => $dryRun,
        'summary' => $summary,
        'results' => $results,
    ]);
}
