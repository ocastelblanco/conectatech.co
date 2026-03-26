<?php
/**
 * bootstrap.php — Bootstrap de Moodle para contexto web (API REST).
 *
 * A diferencia de lib/MoodleBootstrap.php (usado en scripts CLI), este bootstrap
 * NO define CLI_SCRIPT. Esto permite a Moodle inicializar su sistema de sesiones
 * y leer la cookie MoodleSession del navegador, que es la base de la autenticación
 * delegada al login nativo de Moodle.
 *
 * La API se sirve desde https://conectatech.co/admin-api/ (via Apache Alias)
 * para que el host coincida con $CFG->wwwroot. De esta forma Moodle inicializa
 * la sesión normalmente y la cookie MoodleSession ya está disponible (mismo dominio).
 *
 * El frontend Angular en admin.conectatech.co llama con withCredentials: true,
 * y CORS en index.php permite el origen admin.conectatech.co con credenciales.
 */

$moodleConfig = '/var/www/html/moodle/config.php';

if (!file_exists($moodleConfig)) {
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => false, 'error' => 'Moodle config not found on server.']);
    exit;
}

require($moodleConfig);

// Moodle inicializa la navegación en contexto web y el autoloader carga
// theme_boost_union\boostnavbar antes de que lib.php haya sido incluido,
// lo que causa "Undefined constant THEME_BOOST_UNION_SETTING_SELECT_YES".
// Cargamos lib.php explícitamente para que las constantes estén disponibles.
if (isset($CFG) && file_exists($CFG->dirroot . '/theme/boost_union/lib.php')) {
    require_once($CFG->dirroot . '/theme/boost_union/lib.php');
}
