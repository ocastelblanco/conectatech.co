<?php
/**
 * handlers/arboles.php — Handlers para el CRUD y ejecución de árboles curriculares.
 *
 * GET    /api/arboles                    → listar árboles
 * POST   /api/arboles                    → crear árbol
 * GET    /api/arboles/{id}               → obtener árbol completo
 * PUT    /api/arboles/{id}               → guardar árbol completo
 * DELETE /api/arboles/{id}               → eliminar árbol
 * POST   /api/arboles/{id}/duplicar      → duplicar con nuevos metadatos
 * GET    /api/arboles/{id}/validar       → pre-validar conflictos
 * POST   /api/arboles/{id}/ejecutar      → crear y poblar cursos en Moodle
 * GET    /api/arboles/plantillas         → árbol de PLANTILLAS
 * GET    /api/arboles/repositorios       → secciones de repositorios
 * GET    /api/arboles/categorias-raiz    → categorías de primer nivel
 */

// ─────────────────────────────────────────────────────────────────────────────
// GET /api/arboles
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Lista todos los árboles con metadatos básicos.
 *
 * Response 200:
 *   { ok: true, arboles: [{id, nombre, shortname, periodo, institucion, updated_at}], total: int }
 */
function handleListarArboles(): void
{
    $service = new ArbolCurricularService();
    $arboles = $service->listar();

    echo json_encode([
        'ok'     => true,
        'arboles'=> $arboles,
        'total'  => count($arboles),
    ]);
}

// ─────────────────────────────────────────────────────────────────────────────
// POST /api/arboles
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Crea un árbol nuevo.
 *
 * Body JSON: { nombre, shortname, periodo, institucion, categoria_raiz }
 *
 * Response 201:
 *   { ok: true, arbol: {...} }
 */
function handleCrearArbol(): void
{
    $data = readJsonBody();

    try {
        $service = new ArbolCurricularService();
        $arbol   = $service->crear($data);

        http_response_code(201);
        echo json_encode(['ok' => true, 'arbol' => $arbol]);
    } catch (InvalidArgumentException $e) {
        badRequest($e->getMessage());
    } catch (Throwable $e) {
        http_response_code(500);
        echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// GET /api/arboles/{id}
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Obtiene el árbol completo por ID.
 *
 * Response 200: { ok: true, arbol: {...} }
 * Response 404: { ok: false, error: "..." }
 */
function handleObtenerArbol(string $id): void
{
    try {
        $service = new ArbolCurricularService();
        $arbol   = $service->obtener($id);

        echo json_encode(['ok' => true, 'arbol' => $arbol]);
    } catch (RuntimeException $e) {
        http_response_code(404);
        echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
    } catch (Throwable $e) {
        http_response_code(500);
        echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// PUT /api/arboles/{id}
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Guarda el árbol completo (reemplaza el contenido).
 *
 * Body JSON: árbol completo
 *
 * Response 200: { ok: true, arbol: {...} }
 */
function handleGuardarArbol(string $id): void
{
    $data = readJsonBody();

    try {
        $service = new ArbolCurricularService();
        $arbol   = $service->guardar($id, $data);

        echo json_encode(['ok' => true, 'arbol' => $arbol]);
    } catch (RuntimeException $e) {
        http_response_code(404);
        echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
    } catch (Throwable $e) {
        http_response_code(500);
        echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// DELETE /api/arboles/{id}
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Elimina un árbol por ID.
 *
 * Response 200: { ok: true }
 * Response 404: { ok: false, error: "..." }
 */
function handleEliminarArbol(string $id): void
{
    try {
        $service = new ArbolCurricularService();
        $service->eliminar($id);

        echo json_encode(['ok' => true]);
    } catch (RuntimeException $e) {
        http_response_code(404);
        echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
    } catch (Throwable $e) {
        http_response_code(500);
        echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// POST /api/arboles/{id}/duplicar
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Duplica un árbol con nuevos metadatos.
 *
 * Body JSON: { nombre, shortname, periodo, institucion }
 *
 * Response 201: { ok: true, arbol: {...} }
 */
function handleDuplicarArbol(string $id): void
{
    $meta = readJsonBody();

    try {
        $service = new ArbolCurricularService();
        $nuevo   = $service->duplicar($id, $meta);

        http_response_code(201);
        echo json_encode(['ok' => true, 'arbol' => $nuevo]);
    } catch (RuntimeException $e) {
        http_response_code(404);
        echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
    } catch (Throwable $e) {
        http_response_code(500);
        echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// GET /api/arboles/{id}/validar
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Pre-valida conflictos de shortname antes de ejecutar.
 *
 * Response 200: { ok: true, conflictos: [...] }
 */
function handleValidarArbol(string $id): void
{
    try {
        $service    = new ArbolCurricularService();
        $validacion = $service->validarEjecucion($id);

        echo json_encode($validacion);
    } catch (RuntimeException $e) {
        http_response_code(404);
        echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
    } catch (Throwable $e) {
        http_response_code(500);
        echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// POST /api/arboles/{id}/ejecutar
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Ejecuta la creación y poblado de cursos en Moodle.
 *
 * Body JSON (opcional): { dry_run: true|false }
 *
 * Response 200: { ok, dry_run, summary: {created, updated, errors}, results: [...] }
 */
function handleEjecutarArbol(string $id): void
{
    $body   = file_get_contents('php://input');
    $params = (!empty($body)) ? json_decode($body, true) : [];
    $dryRun = (bool)($params['dry_run'] ?? false);

    try {
        $service  = new ArbolCurricularService();
        $reporte  = $service->ejecutar($id, $dryRun);

        echo json_encode($reporte);
    } catch (RuntimeException $e) {
        http_response_code(404);
        echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
    } catch (Throwable $e) {
        http_response_code(500);
        echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// GET /api/arboles/plantillas
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Retorna el árbol de plantillas disponibles en Moodle.
 *
 * Response 200: { ok: true, plantillas: [{nombre, cursos: [{shortname, fullname}]}] }
 */
function handleGetPlantillas(): void
{
    try {
        $service    = new ArbolCurricularService();
        $plantillas = $service->getPlantillas();

        echo json_encode(['ok' => true, 'plantillas' => $plantillas]);
    } catch (Throwable $e) {
        http_response_code(500);
        echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// GET /api/arboles/repositorios
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Retorna el árbol de repositorios con sus secciones (para drag-and-drop).
 *
 * Response 200: { ok: true, repositorios: [{nombre, areas: [{nombre, cursos: [...]}]}] }
 */
function handleGetRepositorios(): void
{
    try {
        $service      = new ArbolCurricularService();
        $repositorios = $service->getRepositorios();

        echo json_encode(['ok' => true, 'repositorios' => $repositorios]);
    } catch (Throwable $e) {
        http_response_code(500);
        echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// GET /api/arboles/opciones-css
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Retorna los valores únicos de proyecto_css y area_css de todos los árboles.
 *
 * Response 200: { ok: true, proyectos: string[], areas: string[] }
 */
function handleGetOpcionesCss(): void
{
    try {
        $service  = new ArbolCurricularService();
        $opciones = $service->getOpcionesCss();

        echo json_encode(['ok' => true, ...$opciones]);
    } catch (Throwable $e) {
        http_response_code(500);
        echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// GET /api/arboles/categorias-raiz
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Retorna las categorías de primer nivel en Moodle.
 *
 * Response 200: { ok: true, categorias: [{id, name}] }
 */
function handleGetCategoriasRaiz(): void
{
    try {
        $service   = new ArbolCurricularService();
        $categorias = $service->getCategoriasRaiz();

        echo json_encode(['ok' => true, 'categorias' => $categorias]);
    } catch (Throwable $e) {
        http_response_code(500);
        echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
    }
}
