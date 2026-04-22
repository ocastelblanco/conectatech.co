<?php
/**
 * handlers/gestor.php
 * Handlers de la API REST para la vista del gestor.
 *
 * Todas las rutas requieren rol gestor (verificado en auth.php).
 * $ctGestor contiene los datos de la organización del gestor autenticado.
 *
 * Rutas:
 *   GET  /api/gestor/organizacion       → datos de la org + cursos disponibles
 *   GET  /api/gestor/colegios           → listar colegios con sus grupos
 *   POST /api/gestor/colegios           → crear colegio
 *   GET  /api/gestor/grupos             → listar todos los grupos (con info de colegio)
 *   POST /api/gestor/grupos             → crear grupo (requiere colegio_id)
 *   GET  /api/gestor/pines              → listar pines [?status=X&group_id=Y&course_id=Z]
 *   PUT  /api/gestor/pines/asignar      → asignar lote de pines
 *   GET  /api/gestor/pines/descargar    → descarga CSV [mismos filtros que listar]
 */

function handleGetOrganizacion(): void
{
    global $ctGestor;
    $data = (new GestorService())->getOrganizacion($ctGestor);
    while (ob_get_level() > 0) { ob_end_clean(); }
    echo json_encode(['ok' => true, 'data' => $data]);
}

function handleListarColegios(): void
{
    global $ctGestor;
    $data = (new GestorService())->listarColegios($ctGestor);
    while (ob_get_level() > 0) { ob_end_clean(); }
    echo json_encode(['ok' => true, 'data' => $data]);
}

function handleCrearColegio(): void
{
    global $ctGestor;
    $body = readJsonBody();
    $name = trim($body['name'] ?? '');
    if ($name === '') badRequest('El campo name es obligatorio.');
    $result = (new GestorService())->crearColegio($ctGestor, $name);
    while (ob_get_level() > 0) { ob_end_clean(); }
    http_response_code(201);
    echo json_encode(['ok' => true, 'data' => $result]);
}

function handleListarGrupos(): void
{
    global $ctGestor;
    $data = (new GestorService())->listarGrupos($ctGestor);
    while (ob_get_level() > 0) { ob_end_clean(); }
    echo json_encode(['ok' => true, 'data' => $data]);
}

function handleCrearGrupo(): void
{
    global $ctGestor;
    $body      = readJsonBody();
    $colegioId = isset($body['colegio_id']) ? (int)$body['colegio_id'] : 0;
    $name      = trim($body['name'] ?? '');
    if ($colegioId === 0) badRequest('El campo colegio_id es obligatorio.');
    if ($name === '')     badRequest('El campo name es obligatorio.');
    $result = (new GestorService())->crearGrupo($ctGestor, $colegioId, $name);
    while (ob_get_level() > 0) { ob_end_clean(); }
    http_response_code(201);
    echo json_encode(['ok' => true, 'data' => $result]);
}

function handleListarPinesGestor(): void
{
    global $ctGestor;
    $status   = $_GET['status']    ?? null;
    $groupId  = isset($_GET['group_id'])  ? (int)$_GET['group_id']  : null;
    $courseId = isset($_GET['course_id']) ? (int)$_GET['course_id'] : null;
    $data = (new GestorService())->listarPines(
        $ctGestor,
        $status   ?: null,
        $groupId  ?: null,
        $courseId ?: null
    );
    while (ob_get_level() > 0) { ob_end_clean(); }
    echo json_encode(['ok' => true, 'data' => $data]);
}

function handleAsignarPines(): void
{
    global $ctGestor;
    $body     = readJsonBody();
    $pinIds   = $body['pin_ids']    ?? [];
    $groupId  = isset($body['group_id'])  ? (int)$body['group_id']  : 0;
    $courseId = isset($body['course_id']) ? (int)$body['course_id'] : 0;
    $role     = trim($body['role'] ?? '');

    if (empty($pinIds))   badRequest('El campo pin_ids es obligatorio.');
    if ($groupId  === 0)  badRequest('El campo group_id es obligatorio.');
    if ($courseId === 0)  badRequest('El campo course_id es obligatorio.');
    if ($role     === '') badRequest('El campo role es obligatorio.');

    (new GestorService())->asignarPines(
        $ctGestor,
        array_map('intval', $pinIds),
        $groupId,
        $courseId,
        $role
    );
    while (ob_get_level() > 0) { ob_end_clean(); }
    echo json_encode(['ok' => true]);
}

function handleDescargarPines(): void
{
    global $ctGestor;
    $status   = $_GET['status']    ?? null;
    $groupId  = isset($_GET['group_id'])  ? (int)$_GET['group_id']  : null;
    $courseId = isset($_GET['course_id']) ? (int)$_GET['course_id'] : null;

    $csv = (new GestorService())->generarCsv(
        $ctGestor,
        $status   ?: null,
        $groupId  ?: null,
        $courseId ?: null
    );

    $filename = 'pines-' . date('Y-m-d') . '.csv';
    while (ob_get_level() > 0) { ob_end_clean(); }
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Length: ' . strlen($csv));
    echo $csv;
}
