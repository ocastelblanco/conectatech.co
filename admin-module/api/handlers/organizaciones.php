<?php
/**
 * handlers/organizaciones.php
 * Handlers de la API REST para organizaciones y pines de gestor.
 *
 * Rutas (todas requieren rol administrador):
 *   GET    /api/organizaciones                     → listar todas
 *   POST   /api/organizaciones                     → crear
 *   PUT    /api/organizaciones/{id}                → renombrar / reasignar categoría
 *   DELETE /api/organizaciones/{id}                → eliminar (cascade)
 *   GET    /api/organizaciones/{id}/gestor-pines   → listar pines de gestor
 *   POST   /api/organizaciones/{id}/gestor-pines   → crear pin de gestor
 *   DELETE /api/gestor-pines/{hash}                → anular pin de gestor pendiente
 */

function handleListarOrganizaciones(): void
{
    $svc  = new OrganizacionService();
    $data = $svc->listar();
    while (ob_get_level() > 0) { ob_end_clean(); }
    echo json_encode(['ok' => true, 'data' => $data]);
}

function handleCrearOrganizacion(): void
{
    $body  = readJsonBody();
    $name  = trim($body['name'] ?? '');
    $catId = isset($body['moodle_category_id']) ? (int)$body['moodle_category_id'] : 0;

    if ($name === '')  badRequest('El campo name es obligatorio.');
    if ($catId === 0)  badRequest('El campo moodle_category_id es obligatorio.');

    $svc    = new OrganizacionService();
    $result = $svc->crear($name, $catId);
    while (ob_get_level() > 0) { ob_end_clean(); }
    http_response_code(201);
    echo json_encode(['ok' => true, 'data' => $result]);
}

function handleActualizarOrganizacion(int $id): void
{
    $body        = readJsonBody();
    $name        = isset($body['name'])               ? trim($body['name'])               : null;
    $catId       = isset($body['moodle_category_id']) ? (int)$body['moodle_category_id'] : null;

    if ($name !== null && $name === '') badRequest('El campo name no puede estar vacío.');

    $svc = new OrganizacionService();
    $svc->actualizar($id, $name ?: null, $catId);
    while (ob_get_level() > 0) { ob_end_clean(); }
    echo json_encode(['ok' => true]);
}

function handleEliminarOrganizacion(int $id): void
{
    $svc = new OrganizacionService();
    $svc->eliminar($id);
    while (ob_get_level() > 0) { ob_end_clean(); }
    echo json_encode(['ok' => true]);
}

// ─── Pines de gestor ─────────────────────────────────────────────────────────

function handleListarGestorPines(int $orgId): void
{
    $svc  = new OrganizacionService();
    $data = $svc->listarGestorPines($orgId);
    while (ob_get_level() > 0) { ob_end_clean(); }
    echo json_encode(['ok' => true, 'data' => $data]);
}

function handleCrearGestorPin(int $orgId): void
{
    global $USER;

    $svc    = new OrganizacionService();
    $result = $svc->crearGestorPin($orgId, (int)$USER->id);
    while (ob_get_level() > 0) { ob_end_clean(); }
    http_response_code(201);
    echo json_encode(['ok' => true, 'data' => $result]);
}

function handleAnularGestorPin(string $hash): void
{
    $svc = new OrganizacionService();
    $svc->anularGestorPin($hash);
    while (ob_get_level() > 0) { ob_end_clean(); }
    echo json_encode(['ok' => true]);
}

// ─── Categorías disponibles para organizaciones ───────────────────────────────

/**
 * GET /api/organizaciones/categorias
 * Devuelve las subcategorías de COLEGIOS (parent = 13) ordenadas por nombre.
 */
function handleGetCategoriasColegios(): void
{
    global $DB;

    $cats = $DB->get_records('course_categories', ['parent' => 13], 'name ASC', 'id, name');
    $data = array_values(array_map(fn($c) => [
        'id'   => (int)$c->id,
        'name' => $c->name,
    ], $cats));

    while (ob_get_level() > 0) { ob_end_clean(); }
    echo json_encode(['ok' => true, 'categorias' => $data]);
}
