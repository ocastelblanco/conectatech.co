<?php
/**
 * handlers/activos.php — Gestión de activos CDN y creación de visores en Moodle.
 *
 * GET  /api/activos/cursos-repositorio  → cursos repositorio con sus secciones
 * POST /api/activos/crear-visor         → crea mod_label con iframe del visor PDF
 */

// ─────────────────────────────────────────────────────────────────────────────
// GET /api/activos/cursos-repositorio
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Retorna todos los cursos de la categoría REPOSITORIOS con sus secciones nombradas.
 *
 * Response 200: { ok: true, cursos: [{id, shortname, fullname, secciones: [{num, titulo}]}] }
 */
function handleGetCursosRepositorio(): void
{
    global $DB;

    $catRepo = $DB->get_record('course_categories', ['name' => 'REPOSITORIOS', 'parent' => 0]);

    if (!$catRepo) {
        echo json_encode(['ok' => true, 'cursos' => []]);
        return;
    }

    $cursos = getCursosDeCategoria($DB, (int) $catRepo->id);

    echo json_encode(['ok' => true, 'cursos' => $cursos]);
}

/**
 * Obtiene recursivamente todos los cursos de una categoría y sus subcategorías.
 */
function getCursosDeCategoria($DB, int $catId): array
{
    $result = [];

    $courses = $DB->get_records('course', ['category' => $catId], 'shortname', 'id,shortname,fullname');

    foreach ($courses as $c) {
        $secciones = getSeccionesRepositorio($DB, (int) $c->id);

        if (empty($secciones)) {
            continue;
        }

        $result[] = [
            'id'        => (int) $c->id,
            'shortname' => $c->shortname,
            'fullname'  => $c->fullname,
            'secciones' => $secciones,
        ];
    }

    $subCats = $DB->get_records('course_categories', ['parent' => $catId], 'sortorder', 'id,name');

    foreach ($subCats as $sub) {
        $result = array_merge($result, getCursosDeCategoria($DB, (int) $sub->id));
    }

    return $result;
}

/**
 * Obtiene las secciones nombradas de un curso (excluye sección 0 y delegadas).
 */
function getSeccionesRepositorio($DB, int $courseId): array
{
    $sql = "SELECT cs.section, cs.name
              FROM {course_sections} cs
             WHERE cs.course     = :courseid
               AND cs.section    > 0
               AND cs.name      != ''
               AND (cs.component IS NULL OR cs.component = '')
             ORDER BY cs.section";

    $rows   = $DB->get_records_sql($sql, ['courseid' => $courseId]);
    $result = [];

    foreach ($rows as $r) {
        $result[] = [
            'num'    => (int) $r->section,
            'titulo' => $r->name,
        ];
    }

    return $result;
}

// ─────────────────────────────────────────────────────────────────────────────
// POST /api/activos/crear-visor
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Crea un recurso mod_label (Área de texto y medios) en una sección de un
 * curso repositorio, con un iframe incrustando el visor PDF del CDN.
 *
 * Body JSON: { pdfId, pdfTitle, courseId, seccionNum, pageStart?, pageEnd? }
 *
 * Response 200: { ok: true, cmId: int|null }
 */
function handleCrearVisor(): void
{
    global $DB, $CFG;

    $body       = readJsonBody();
    $pdfId      = trim($body['pdfId']    ?? '');
    $pdfTitle   = trim($body['pdfTitle'] ?? '');
    $courseId   = (int) ($body['courseId']   ?? 0);
    $seccionNum = (int) ($body['seccionNum'] ?? 0);
    $pageStart  = (isset($body['pageStart']) && $body['pageStart'] !== '') ? (int) $body['pageStart'] : null;
    $pageEnd    = (isset($body['pageEnd'])   && $body['pageEnd']   !== '') ? (int) $body['pageEnd']   : null;

    if (!$pdfId || !$pdfTitle || !$courseId || $seccionNum < 1) {
        badRequest('Faltan campos obligatorios: pdfId, pdfTitle, courseId, seccionNum (>= 1)');
    }

    // Construir URL del visor
    $params = ['id' => $pdfId];
    if ($pageStart !== null) $params['start'] = $pageStart;
    if ($pageEnd   !== null) $params['end']   = $pageEnd;
    $viewerUrl = 'https://assets.conectatech.co/herramientas/visor-pdf/?' . http_build_query($params);

    // HTML del iframe
    $iframeHtml = '<p>'
        . '<iframe src="' . htmlspecialchars($viewerUrl, ENT_QUOTES, 'UTF-8') . '"'
        . ' style="width:100%;height:700px;border:none;"'
        . ' allow="fullscreen" loading="lazy"></iframe>'
        . '</p>';

    // Crear mod_label
    require_once($CFG->dirroot . '/course/lib.php');

    $module = (object) [
        'course'       => $courseId,
        'section'      => $seccionNum,
        'modulename'   => 'label',
        'name'         => $pdfTitle,
        'introeditor'  => [
            'text'   => $iframeHtml,
            'format' => FORMAT_HTML,
            'itemid' => 0,
        ],
        'visible'      => 1,
    ];

    $result = create_module($module);

    echo json_encode([
        'ok'   => true,
        'cmId' => $result->coursemodule ?? null,
    ]);
}
