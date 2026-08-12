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
 * Obtiene las secciones nombradas de un curso (excluye sección 0 y delegadas),
 * con las subsecciones (mod_subsection) que cuelgan de cada una.
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
        $num = (int) $r->section;
        $result[] = [
            'num'          => $num,
            'titulo'       => $r->name,
            'subsecciones' => getSubseccionesDeSeccion($DB, $courseId, $num),
        ];
    }

    return $result;
}

/**
 * Obtiene las subsecciones (mod_subsection) que cuelgan directamente de una
 * sección padre, con el número de su sección delegada (destino real para
 * insertar/reemplazar contenido dentro de la subsección).
 */
function getSubseccionesDeSeccion($DB, int $courseId, int $parentSectionNum): array
{
    $parentSection = $DB->get_record('course_sections', [
        'course'  => $courseId,
        'section' => $parentSectionNum,
    ]);

    if (!$parentSection) {
        return [];
    }

    $sql = "SELECT cm.instance AS instanceid, sub.name AS titulo
              FROM {course_modules} cm
              JOIN {modules}    m   ON m.id = cm.module
              JOIN {subsection} sub ON sub.id = cm.instance
             WHERE cm.course  = :courseid
               AND cm.section = :parentsectionid
               AND m.name     = 'subsection'
             ORDER BY cm.id";

    $rows = $DB->get_records_sql($sql, [
        'courseid'        => $courseId,
        'parentsectionid' => $parentSection->id,
    ]);

    $result = [];

    foreach ($rows as $r) {
        $delegated = $DB->get_record('course_sections', [
            'course'    => $courseId,
            'component' => 'mod_subsection',
            'itemid'    => (int) $r->instanceid,
        ]);

        if (!$delegated) {
            continue; // huérfano defensivo, no debería ocurrir
        }

        $result[] = [
            'num'    => (int) $delegated->section,
            'titulo' => $r->titulo,
        ];
    }

    return $result;
}

// ─────────────────────────────────────────────────────────────────────────────
// POST /api/activos/crear-visor
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Crea un recurso mod_label (Área de texto y medios) con un iframe
 * incrustando el visor PDF del CDN, en una sección de un curso repositorio.
 *
 * Si se indica subseccionNum, el visor se inserta dentro de esa subsección
 * REEMPLAZANDO todo su contenido actual (se borran sus módulos existentes).
 * Si no se indica, se agrega al final de seccionNum (comportamiento original).
 *
 * Body JSON: { pdfId, pdfTitle, courseId, seccionNum, subseccionNum?, pageStart?, pageEnd? }
 *
 * Response 200: { ok: true, cmId: int|null }
 */
function handleCrearVisor(): void
{
    global $DB, $CFG;

    $body          = readJsonBody();
    $pdfId         = trim($body['pdfId']    ?? '');
    $pdfTitle      = trim($body['pdfTitle'] ?? '');
    $courseId      = (int) ($body['courseId']   ?? 0);
    $seccionNum    = (int) ($body['seccionNum'] ?? 0);
    $subseccionNum = (isset($body['subseccionNum']) && $body['subseccionNum'] !== '')
        ? (int) $body['subseccionNum']
        : null;
    $pageStart     = (isset($body['pageStart']) && $body['pageStart'] !== '') ? (int) $body['pageStart'] : null;
    $pageEnd       = (isset($body['pageEnd'])   && $body['pageEnd']   !== '') ? (int) $body['pageEnd']   : null;

    if (!$pdfId || !$pdfTitle || !$courseId || $seccionNum < 1) {
        badRequest('Faltan campos obligatorios: pdfId, pdfTitle, courseId, seccionNum (>= 1)');
    }
    if ($subseccionNum !== null && $subseccionNum < 1) {
        badRequest('subseccionNum debe ser un entero mayor a 0');
    }

    require_once($CFG->dirroot . '/course/lib.php');

    $targetSectionNum = $seccionNum;

    if ($subseccionNum !== null) {
        // Verificar que subseccionNum pertenece a este curso y es realmente una
        // sección delegada (mod_subsection) — evita que el cliente apunte a
        // cualquier número de sección arbitrario (p. ej. una sección H1 normal).
        $delegated = $DB->get_record('course_sections', [
            'course'    => $courseId,
            'section'   => $subseccionNum,
            'component' => 'mod_subsection',
        ]);
        if (!$delegated) {
            badRequest('subseccionNum no corresponde a una subsección válida de este curso');
        }

        // Vaciar el contenido actual de la subsección antes de insertar el visor
        // (mismo patrón que MoodleContentBuilder::clearSectionContent()).
        $course  = $DB->get_record('course', ['id' => $courseId], '*', MUST_EXIST);
        $modinfo = get_fast_modinfo($course);
        $section = $modinfo->get_section_info($subseccionNum, IGNORE_MISSING);
        if ($section && !empty($section->sequence)) {
            foreach (array_filter(explode(',', $section->sequence)) as $cmid) {
                course_delete_module((int) $cmid);
            }
        }

        $targetSectionNum = $subseccionNum;
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
    $module = (object) [
        'course'       => $courseId,
        'section'      => $targetSectionNum,
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
