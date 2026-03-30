<?php
/**
 * index.php — Router principal de la API REST de ConectaTech Admin.
 *
 * Todas las peticiones a /api/* pasan por aquí vía la RewriteRule del .htaccess.
 * El router parsea el método HTTP y la ruta, carga el handler correspondiente
 * y delega la ejecución. Los errores no capturados devuelven HTTP 500 con JSON.
 *
 * Rutas disponibles:
 *   GET  /api/ping                  → health check (sin auth)
 *   GET  /api/cursos                → listar cursos [?category=COLEGIOS/San Marino]
 *   POST /api/cursos/crear          → crear cursos desde array JSON
 *   POST /api/cursos/poblar         → poblar cursos desde mapping JSON
 *   POST /api/matriculas            → crear/actualizar usuarios y matricular
 *   POST /api/markdown              → procesar Markdown en un curso repositorio
 *   GET  /api/reportes/{nombre}                → último reporte JSON de una operación
 *   GET  /api/activos/cursos-repositorio       → cursos repositorio con secciones
 *   POST /api/activos/crear-visor              → crea visor PDF (mod_label) en Moodle
 *   GET  /api/organizaciones                   → listar organizaciones
 *   POST /api/organizaciones                   → crear organización
 *   PUT  /api/organizaciones/{id}              → renombrar / reasignar categoría
 *   DEL  /api/organizaciones/{id}              → eliminar organización (cascade)
 *   GET  /api/organizaciones/{id}/gestor-pines → listar pines de gestor
 *   POST /api/organizaciones/{id}/gestor-pines → crear pin de gestor
 *   DEL  /api/gestor-pines/{hash}              → anular pin de gestor pendiente
 *   GET  /api/paquetes                         → listar paquetes [?org_id=X]
 *   POST /api/paquetes                         → crear paquete + generar pines
 *   POST /api/paquetes/{id}/asignar            → reasignar paquete a organización
 *   GET  /api/pines/reporte                    → reporte de uso [?org_id=X&package_id=Y]
 *   POST /api/activar/resolver                 → identifica hash y retorna info del pin (público)
 *   POST /api/activar/gestor                   → crea cuenta de gestor y activa pin-gestor (público)
 *   POST /api/activar/login                    → verifica credenciales Moodle (público)
 *   POST /api/activar/pin                      → activa pin de acceso a curso (público)
 */

// ─────────────────────────────────────────────────────────────────────────────
// Rutas absolutas
// ─────────────────────────────────────────────────────────────────────────────

define('API_DIR',     __DIR__);
define('BACKEND_DIR', dirname(__DIR__) . '/backend');
define('CONFIG_DIR',  BACKEND_DIR . '/config');
define('LIB_DIR',     BACKEND_DIR . '/lib');

// Capturar cualquier output HTML que Moodle pueda generar durante la inicialización
// o procesamiento (p.ej. el importador GIFT imprime HTML). Se descarta antes de
// enviar el JSON para que Angular reciba solo JSON válido.
ob_start();

// ─────────────────────────────────────────────────────────────────────────────
// Headers globales
// ─────────────────────────────────────────────────────────────────────────────

header('Content-Type: application/json; charset=utf-8');

// CORS — permite únicamente el origen del panel de administración
$allowedOrigins = ['https://admin.conectatech.co'];
$origin         = $_SERVER['HTTP_ORIGIN'] ?? '';

if (in_array($origin, $allowedOrigins, true)) {
    header("Access-Control-Allow-Origin: {$origin}");
    header('Access-Control-Allow-Credentials: true');   // necesario para enviar cookies
    header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type');
}

// Responder a pre-flight CORS sin pasar por auth ni Moodle
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// ─────────────────────────────────────────────────────────────────────────────
// Health check (sin autenticación — útil para monitoreo y tests de conectividad)
// ─────────────────────────────────────────────────────────────────────────────

// La API se sirve desde conectatech.co/admin-api/* (Alias Apache)
// para que el host coincida con $CFG->wwwroot y Moodle no redirija.
$uri     = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$rawPath = trim(preg_replace('#^/?admin-api/?#', '', $uri), '/');

if ($_SERVER['REQUEST_METHOD'] === 'GET' && $rawPath === 'ping') {
    echo json_encode(['ok' => true, 'message' => 'ConectaTech API activa']);
    exit;
}

// ─────────────────────────────────────────────────────────────────────────────
// Bootstrap de Moodle (inicializa sesión desde la cookie MoodleSession)
// ─────────────────────────────────────────────────────────────────────────────

require_once API_DIR . '/bootstrap.php';

// ─────────────────────────────────────────────────────────────────────────────
// Autenticación: sesión Moodle activa + rol administrador
// ─────────────────────────────────────────────────────────────────────────────

require_once API_DIR . '/auth.php';

// ─────────────────────────────────────────────────────────────────────────────
// Servicios
// ─────────────────────────────────────────────────────────────────────────────

require_once LIB_DIR . '/CursosService.php';
require_once LIB_DIR . '/ArbolCurricularService.php';
require_once LIB_DIR . '/MatriculasService.php';
require_once LIB_DIR . '/MoodleSectionCloner.php';
require_once LIB_DIR . '/PobladorService.php';
require_once LIB_DIR . '/MarkdownParser.php';
require_once LIB_DIR . '/HtmlConverter.php';
require_once LIB_DIR . '/PresaberesHtmlBuilder.php';
require_once LIB_DIR . '/GiftConverter.php';
require_once LIB_DIR . '/MoodleContentBuilder.php';
require_once LIB_DIR . '/MarkdownService.php';
require_once LIB_DIR . '/OrganizacionService.php';
require_once LIB_DIR . '/PinesService.php';

// ─────────────────────────────────────────────────────────────────────────────
// Routing
// ─────────────────────────────────────────────────────────────────────────────

$method   = $_SERVER['REQUEST_METHOD'];
$segments = $rawPath !== '' ? explode('/', $rawPath) : [];
$seg0     = $segments[0] ?? '';
$seg1     = $segments[1] ?? '';
$seg2     = $segments[2] ?? '';

try {
    switch (true) {

        // GET /api/cursos/arbol
        case $method === 'GET' && $seg0 === 'cursos' && $seg1 === 'arbol':
            require API_DIR . '/handlers/cursos.php';
            handleGetArbolCursos();
            break;

        // GET /api/cursos
        case $method === 'GET' && $seg0 === 'cursos' && $seg1 === '':
            require API_DIR . '/handlers/cursos.php';
            handleGetCursos();
            break;

        // POST /api/cursos/crear
        case $method === 'POST' && $seg0 === 'cursos' && $seg1 === 'crear':
            require API_DIR . '/handlers/cursos.php';
            handleCrearCursos();
            break;

        // POST /api/cursos/poblar
        case $method === 'POST' && $seg0 === 'cursos' && $seg1 === 'poblar':
            require API_DIR . '/handlers/cursos.php';
            handlePoblarCursos();
            break;

        // POST /api/matriculas
        case $method === 'POST' && $seg0 === 'matriculas':
            require API_DIR . '/handlers/matriculas.php';
            handleMatriculas();
            break;

        // POST /api/markdown
        case $method === 'POST' && $seg0 === 'markdown':
            require API_DIR . '/handlers/markdown.php';
            handleMarkdown();
            break;

        // GET /api/reportes/{nombre}
        case $method === 'GET' && $seg0 === 'reportes' && $seg1 !== '':
            require API_DIR . '/handlers/reportes.php';
            handleReportes($seg1);
            break;

        // GET /api/arboles/plantillas
        case $method === 'GET' && $seg0 === 'arboles' && $seg1 === 'plantillas':
            require API_DIR . '/handlers/arboles.php';
            handleGetPlantillas();
            break;

        // GET /api/arboles/repositorios
        case $method === 'GET' && $seg0 === 'arboles' && $seg1 === 'repositorios':
            require API_DIR . '/handlers/arboles.php';
            handleGetRepositorios();
            break;

        // GET /api/arboles/categorias-raiz
        case $method === 'GET' && $seg0 === 'arboles' && $seg1 === 'categorias-raiz':
            require API_DIR . '/handlers/arboles.php';
            handleGetCategoriasRaiz();
            break;

        // GET /api/arboles/opciones-css
        case $method === 'GET' && $seg0 === 'arboles' && $seg1 === 'opciones-css':
            require API_DIR . '/handlers/arboles.php';
            handleGetOpcionesCss();
            break;

        // GET /api/arboles/{id}/validar
        case $method === 'GET' && $seg0 === 'arboles' && $seg2 === 'validar':
            require API_DIR . '/handlers/arboles.php';
            handleValidarArbol($seg1);
            break;

        // POST /api/arboles/{id}/ejecutar
        case $method === 'POST' && $seg0 === 'arboles' && $seg2 === 'ejecutar':
            require API_DIR . '/handlers/arboles.php';
            handleEjecutarArbol($seg1);
            break;

        // POST /api/arboles/{id}/duplicar
        case $method === 'POST' && $seg0 === 'arboles' && $seg2 === 'duplicar':
            require API_DIR . '/handlers/arboles.php';
            handleDuplicarArbol($seg1);
            break;

        // GET /api/arboles/{id}
        case $method === 'GET' && $seg0 === 'arboles' && $seg1 !== '':
            require API_DIR . '/handlers/arboles.php';
            handleObtenerArbol($seg1);
            break;

        // POST /api/arboles
        case $method === 'POST' && $seg0 === 'arboles' && $seg1 === '':
            require API_DIR . '/handlers/arboles.php';
            handleCrearArbol();
            break;

        // PUT /api/arboles/{id}
        case $method === 'PUT' && $seg0 === 'arboles' && $seg1 !== '':
            require API_DIR . '/handlers/arboles.php';
            handleGuardarArbol($seg1);
            break;

        // DELETE /api/arboles/{id}
        case $method === 'DELETE' && $seg0 === 'arboles' && $seg1 !== '':
            require API_DIR . '/handlers/arboles.php';
            handleEliminarArbol($seg1);
            break;

        // GET /api/arboles
        case $method === 'GET' && $seg0 === 'arboles' && $seg1 === '':
            require API_DIR . '/handlers/arboles.php';
            handleListarArboles();
            break;

        // GET /api/activos/cursos-repositorio
        case $method === 'GET' && $seg0 === 'activos' && $seg1 === 'cursos-repositorio':
            require API_DIR . '/handlers/activos.php';
            handleGetCursosRepositorio();
            break;

        // POST /api/activos/crear-visor
        case $method === 'POST' && $seg0 === 'activos' && $seg1 === 'crear-visor':
            require API_DIR . '/handlers/activos.php';
            handleCrearVisor();
            break;

        // ── Organizaciones ──────────────────────────────────────────────────

        // GET /api/organizaciones
        case $method === 'GET' && $seg0 === 'organizaciones' && $seg1 === '':
            require API_DIR . '/handlers/organizaciones.php';
            handleListarOrganizaciones();
            break;

        // POST /api/organizaciones
        case $method === 'POST' && $seg0 === 'organizaciones' && $seg1 === '':
            require API_DIR . '/handlers/organizaciones.php';
            handleCrearOrganizacion();
            break;

        // PUT /api/organizaciones/{id}
        case $method === 'PUT' && $seg0 === 'organizaciones' && $seg1 !== '' && $seg2 === '':
            require API_DIR . '/handlers/organizaciones.php';
            handleActualizarOrganizacion((int)$seg1);
            break;

        // DELETE /api/organizaciones/{id}
        case $method === 'DELETE' && $seg0 === 'organizaciones' && $seg1 !== '' && $seg2 === '':
            require API_DIR . '/handlers/organizaciones.php';
            handleEliminarOrganizacion((int)$seg1);
            break;

        // GET /api/organizaciones/{id}/gestor-pines
        case $method === 'GET' && $seg0 === 'organizaciones' && $seg1 !== '' && $seg2 === 'gestor-pines':
            require API_DIR . '/handlers/organizaciones.php';
            handleListarGestorPines((int)$seg1);
            break;

        // POST /api/organizaciones/{id}/gestor-pines
        case $method === 'POST' && $seg0 === 'organizaciones' && $seg1 !== '' && $seg2 === 'gestor-pines':
            require API_DIR . '/handlers/organizaciones.php';
            handleCrearGestorPin((int)$seg1);
            break;

        // DELETE /api/gestor-pines/{hash}
        case $method === 'DELETE' && $seg0 === 'gestor-pines' && $seg1 !== '':
            require API_DIR . '/handlers/organizaciones.php';
            handleAnularGestorPin($seg1);
            break;

        // ── Paquetes de pines ────────────────────────────────────────────────

        // GET /api/paquetes
        case $method === 'GET' && $seg0 === 'paquetes' && $seg1 === '':
            require API_DIR . '/handlers/pines.php';
            handleListarPaquetes();
            break;

        // POST /api/paquetes
        case $method === 'POST' && $seg0 === 'paquetes' && $seg1 === '':
            require API_DIR . '/handlers/pines.php';
            handleCrearPaquete();
            break;

        // POST /api/paquetes/{id}/asignar
        case $method === 'POST' && $seg0 === 'paquetes' && $seg1 !== '' && $seg2 === 'asignar':
            require API_DIR . '/handlers/pines.php';
            handleAsignarPaquete((int)$seg1);
            break;

        // GET /api/pines/reporte
        case $method === 'GET' && $seg0 === 'pines' && $seg1 === 'reporte':
            require API_DIR . '/handlers/pines.php';
            handleReportePines();
            break;

        // ── Gestor ───────────────────────────────────────────────────────────

        // GET /api/gestor/organizacion
        case $method === 'GET' && $seg0 === 'gestor' && $seg1 === 'organizacion':
            require API_DIR . '/handlers/gestor.php';
            handleGetOrganizacion();
            break;

        // GET /api/gestor/grupos
        case $method === 'GET' && $seg0 === 'gestor' && $seg1 === 'grupos':
            require API_DIR . '/handlers/gestor.php';
            handleListarGrupos();
            break;

        // POST /api/gestor/grupos
        case $method === 'POST' && $seg0 === 'gestor' && $seg1 === 'grupos':
            require API_DIR . '/handlers/gestor.php';
            handleCrearGrupo();
            break;

        // GET /api/gestor/pines/descargar  (antes que el listado para evitar ambigüedad)
        case $method === 'GET' && $seg0 === 'gestor' && $seg1 === 'pines' && $seg2 === 'descargar':
            require API_DIR . '/handlers/gestor.php';
            handleDescargarPines();
            break;

        // GET /api/gestor/pines
        case $method === 'GET' && $seg0 === 'gestor' && $seg1 === 'pines' && $seg2 === '':
            require API_DIR . '/handlers/gestor.php';
            handleListarPinesGestor();
            break;

        // PUT /api/gestor/pines/asignar
        case $method === 'PUT' && $seg0 === 'gestor' && $seg1 === 'pines' && $seg2 === 'asignar':
            require API_DIR . '/handlers/gestor.php';
            handleAsignarPines();
            break;

        default:
            http_response_code(404);
            echo json_encode([
                'ok'    => false,
                'error' => "Ruta no encontrada: {$method} /api/{$rawPath}",
            ]);
    }
} catch (Throwable $e) {
    while (ob_get_level() > 0) { ob_end_clean(); }
    http_response_code(500);
    echo json_encode([
        'ok'    => false,
        'error' => $e->getMessage(),
    ]);
}

// ─────────────────────────────────────────────────────────────────────────────
// Funciones auxiliares (disponibles para todos los handlers via include)
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Lee y decodifica el body JSON de la petición.
 * Termina con 400 si el body está vacío o no es JSON válido.
 */
function readJsonBody(): array
{
    $raw = file_get_contents('php://input');

    if (empty($raw)) {
        badRequest('El body de la petición está vacío.');
    }

    $data = json_decode($raw, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        badRequest('El body no es JSON válido: ' . json_last_error_msg());
    }

    return $data;
}

/**
 * Responde con HTTP 400 y termina la ejecución.
 */
function badRequest(string $message): never
{
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => $message]);
    exit;
}
