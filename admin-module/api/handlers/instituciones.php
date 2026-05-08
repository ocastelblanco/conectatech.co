<?php
/**
 * handlers/instituciones.php
 * Handlers de la API REST para instituciones educativas directas (Track A).
 *
 * Rutas (todas requieren rol administrador):
 *   GET    /api/instituciones                → listar todas
 *   POST   /api/instituciones                → crear
 *   PUT    /api/instituciones/{id}           → actualizar
 *   DELETE /api/instituciones/{id}           → eliminar
 *   GET    /api/instituciones/categorias     → categorías Moodle disponibles
 *   GET    /api/instituciones/{id}/progreso  → progreso de cursos por institución
 */

function handleListarInstituciones(): void
{
    $svc  = new InstitucionService();
    $data = $svc->listar();
    while (ob_get_level() > 0) { ob_end_clean(); }
    echo json_encode(['ok' => true, 'data' => $data]);
}

function handleCrearInstitucion(): void
{
    $body        = readJsonBody();
    $name        = trim($body['name'] ?? '');
    $catId       = isset($body['moodle_category_id']) ? (int)$body['moodle_category_id'] : 0;
    $anioEscolar = isset($body['anio_escolar']) ? trim($body['anio_escolar']) : null;

    if ($name === '') badRequest('El campo name es obligatorio.');
    if ($catId === 0) badRequest('El campo moodle_category_id es obligatorio.');

    $svc    = new InstitucionService();
    $result = $svc->crear($name, $catId, $anioEscolar ?: null);
    while (ob_get_level() > 0) { ob_end_clean(); }
    http_response_code(201);
    echo json_encode(['ok' => true, 'data' => $result]);
}

function handleActualizarInstitucion(int $id): void
{
    $body        = readJsonBody();
    $name        = isset($body['name'])               ? trim($body['name'])               : null;
    $catId       = isset($body['moodle_category_id']) ? (int)$body['moodle_category_id'] : null;
    $anioEscolar = isset($body['anio_escolar'])        ? ($body['anio_escolar'] ?: null)  : null;

    if ($name !== null && $name === '') badRequest('El campo name no puede estar vacío.');

    $svc = new InstitucionService();
    $svc->actualizar($id, $name, $catId, $anioEscolar);
    while (ob_get_level() > 0) { ob_end_clean(); }
    echo json_encode(['ok' => true]);
}

function handleEliminarInstitucion(int $id): void
{
    $svc = new InstitucionService();
    $svc->eliminar($id);
    while (ob_get_level() > 0) { ob_end_clean(); }
    echo json_encode(['ok' => true]);
}

function handleGetProgresoInstitucion(int $id): void
{
    $svc  = new InstitucionService();
    $data = $svc->getProgreso($id);
    while (ob_get_level() > 0) { ob_end_clean(); }
    echo json_encode(['ok' => true, 'data' => $data]);
}

function handleGetCategoriasInstituciones(): void
{
    $svc  = new InstitucionService();
    $data = $svc->getCategorias();
    while (ob_get_level() > 0) { ob_end_clean(); }
    echo json_encode(['ok' => true, 'categorias' => $data]);
}
