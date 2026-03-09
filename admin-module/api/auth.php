<?php
/**
 * auth.php — Middleware de autenticación para la API REST.
 *
 * Verifica que la petición provenga de un usuario con sesión activa en Moodle
 * y que dicho usuario sea administrador del sitio (is_siteadmin()).
 *
 * No requiere tokens adicionales: la autenticación es 100% delegada a Moodle.
 * El navegador envía automáticamente la cookie MoodleSession en cada petición
 * (siempre que Angular use withCredentials: true en HttpClient).
 *
 * Flujo:
 *   1. Usuario se loguea en https://conectatech.co (Moodle nativo).
 *   2. Moodle establece la cookie MoodleSession en el dominio .conectatech.co.
 *   3. Usuario abre https://admin.conectatech.co → la cookie viaja en cada request.
 *   4. Este middleware lee la sesión, verifica rol y permite o deniega el acceso.
 *
 * Si falla: HTTP 401 con JSON de error. El frontend debe redirigir a /login de Moodle.
 */

if (!isloggedin() || isguestuser()) {
    http_response_code(401);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'ok'       => false,
        'error'    => 'No autenticado.',
        'redirect' => 'https://conectatech.co/login',
    ]);
    exit;
}

if (!is_siteadmin()) {
    http_response_code(403);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'ok'    => false,
        'error' => 'Acceso denegado. Se requiere rol de administrador de Moodle.',
    ]);
    exit;
}
