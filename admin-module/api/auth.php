<?php
/**
 * auth.php — Middleware de autenticación para la API REST.
 *
 * Tres niveles de acceso según la ruta ($rawPath, definida en index.php):
 *
 *   activar/*  → Público. Sin autenticación. Cualquier usuario puede llamar
 *                estas rutas para activar su pin o crear su cuenta.
 *
 *   gestor/*   → Gestor. Requiere sesión Moodle activa y que el usuario esté
 *                registrado en ct_gestor. Inyecta $ctGestor con los datos de
 *                la organización del gestor (disponible via global $ctGestor).
 *
 *   resto      → Administrador. Requiere sesión Moodle activa + is_siteadmin().
 *
 * No requiere tokens adicionales: la autenticación es 100% delegada a Moodle.
 * El navegador envía automáticamente la cookie MoodleSession en cada petición
 * (Angular debe usar withCredentials: true en HttpClient).
 */

// ─── Rutas públicas ───────────────────────────────────────────────────────────

if (str_starts_with($rawPath, 'activar')) {
    // Sin autenticación: continuar.
    return;
}

// ─── Verificación de sesión (común para gestor y admin) ───────────────────────

if (!isloggedin() || isguestuser()) {
    http_response_code(401);
    echo json_encode([
        'ok'       => false,
        'error'    => 'No autenticado.',
        'redirect' => 'https://conectatech.co/login',
    ]);
    exit;
}

// ─── Rutas de gestor ──────────────────────────────────────────────────────────

if (str_starts_with($rawPath, 'gestor')) {
    require_once LIB_DIR . '/GestorAuth.php';

    $ctGestor = (new GestorAuth())->lookupGestor((int)$USER->id);

    if ($ctGestor === null) {
        http_response_code(403);
        echo json_encode([
            'ok'    => false,
            'error' => 'Acceso denegado. No tiene rol de gestor.',
        ]);
        exit;
    }

    // $ctGestor queda disponible en el scope global; los handlers lo leen con:
    //   global $ctGestor;
    return;
}

// ─── Rutas de administrador ───────────────────────────────────────────────────

if (!is_siteadmin()) {
    http_response_code(403);
    echo json_encode([
        'ok'    => false,
        'error' => 'Acceso denegado. Se requiere rol de administrador de Moodle.',
    ]);
    exit;
}
