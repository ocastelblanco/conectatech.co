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
 *   GET  /api/reportes/{nombre}     → último reporte JSON de una operación
 */

// ─────────────────────────────────────────────────────────────────────────────
// Rutas absolutas
// ─────────────────────────────────────────────────────────────────────────────

define('API_DIR',     __DIR__);
define('BACKEND_DIR', dirname(__DIR__) . '/backend');
define('CONFIG_DIR',  BACKEND_DIR . '/config');
define('LIB_DIR',     BACKEND_DIR . '/lib');

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
    header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
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
require_once LIB_DIR . '/MatriculasService.php';
require_once LIB_DIR . '/MoodleSectionCloner.php';
require_once LIB_DIR . '/PobladorService.php';
require_once LIB_DIR . '/MarkdownParser.php';
require_once LIB_DIR . '/HtmlConverter.php';
require_once LIB_DIR . '/PresaberesHtmlBuilder.php';
require_once LIB_DIR . '/GiftConverter.php';
require_once LIB_DIR . '/MoodleContentBuilder.php';
require_once LIB_DIR . '/MarkdownService.php';

// ─────────────────────────────────────────────────────────────────────────────
// Routing
// ─────────────────────────────────────────────────────────────────────────────

$method   = $_SERVER['REQUEST_METHOD'];
$segments = $rawPath !== '' ? explode('/', $rawPath) : [];
$seg0     = $segments[0] ?? '';
$seg1     = $segments[1] ?? '';

try {
    switch (true) {

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

        default:
            http_response_code(404);
            echo json_encode([
                'ok'    => false,
                'error' => "Ruta no encontrada: {$method} /api/{$rawPath}",
            ]);
    }
} catch (Throwable $e) {
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
