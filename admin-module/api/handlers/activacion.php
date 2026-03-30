<?php
/**
 * handlers/activacion.php
 * Handlers de la API pública de activación de pines.
 *
 * Todas las rutas son públicas (sin autenticación Moodle).
 *
 * Rutas:
 *   POST /api/activar/resolver  → identifica el hash y retorna info del pin
 *   POST /api/activar/gestor    → crea cuenta de gestor y activa el pin-gestor
 *   POST /api/activar/login     → verifica credenciales Moodle (paso previo a activar)
 *   POST /api/activar/pin       → activa un pin de acceso a curso
 */

function handleResolverPin(): void
{
    $body = readJsonBody();
    $hash = trim($body['hash'] ?? '');

    if ($hash === '') {
        badRequest('El campo hash es obligatorio.');
    }

    $data = (new ActivacionService())->resolvePin($hash);
    while (ob_get_level() > 0) { ob_end_clean(); }
    echo json_encode(['ok' => true, 'data' => $data]);
}

function handleActivarGestor(): void
{
    $body = readJsonBody();
    $hash = trim($body['hash'] ?? '');

    if ($hash === '') {
        badRequest('El campo hash es obligatorio.');
    }

    $datos = [
        'firstname' => $body['firstname'] ?? '',
        'lastname'  => $body['lastname']  ?? '',
        'email'     => $body['email']     ?? '',
        'username'  => $body['username']  ?? '',
        'password'  => $body['password']  ?? '',
    ];

    $data = (new ActivacionService())->activarGestor($hash, $datos);
    while (ob_get_level() > 0) { ob_end_clean(); }
    http_response_code(201);
    echo json_encode(['ok' => true, 'data' => $data]);
}

function handleLoginActivacion(): void
{
    $body     = readJsonBody();
    $username = trim($body['username'] ?? '');
    $password = $body['password'] ?? '';

    if ($username === '' || $password === '') {
        badRequest('Los campos username y password son obligatorios.');
    }

    $data = (new ActivacionService())->verificarCredenciales($username, $password);
    while (ob_get_level() > 0) { ob_end_clean(); }
    echo json_encode(['ok' => true, 'data' => $data]);
}

function handleActivarPin(): void
{
    $body = readJsonBody();
    $hash = trim($body['hash'] ?? '');

    if ($hash === '') {
        badRequest('El campo hash es obligatorio.');
    }

    $datos = [
        'username'  => $body['username']  ?? '',
        'password'  => $body['password']  ?? '',
        'firstname' => $body['firstname'] ?? '',
        'lastname'  => $body['lastname']  ?? '',
        'email'     => $body['email']     ?? '',
    ];

    $data = (new ActivacionService())->activarPin($hash, $datos);
    while (ob_get_level() > 0) { ob_end_clean(); }
    echo json_encode(['ok' => true, 'data' => $data]);
}
