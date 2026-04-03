<?php
/**
 * handlers/activacion.php
 * Handlers de la API REST para la activación pública de pines.
 *
 * Estas rutas son públicas (sin autenticación). El frontend de activación
 * puede estar en cualquier origen permitido por CORS.
 *
 * Rutas:
 *   POST /api/activar/resolver → resuelve un pin por hash
 *   POST /api/activar/gestor   → crea cuenta de gestor y activa el pin
 *   POST /api/activar/login    → verifica credenciales Moodle sin iniciar sesión
 *   POST /api/activar/pin      → matricula al usuario en el curso del pin
 */

/**
 * POST /api/activar/resolver
 * Body: { hash: string }
 */
function handleResolverPin(): void
{
    $body = readJsonBody();
    $hash = trim($body['hash'] ?? '');

    if ($hash === '') {
        badRequest('El campo hash es obligatorio.');
    }

    try {
        $resultado = (new ActivacionService())->resolvePin($hash);
        while (ob_get_level() > 0) { ob_end_clean(); }
        echo json_encode(array_merge(['ok' => true], $resultado));
    } catch (InvalidArgumentException $e) {
        while (ob_get_level() > 0) { ob_end_clean(); }
        http_response_code(422);
        echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
    }
}

/**
 * POST /api/activar/gestor
 * Body: { hash, firstname, lastname, email, username, password }
 */
function handleActivarGestor(): void
{
    $body = readJsonBody();

    $campos = ['hash', 'firstname', 'lastname', 'email', 'username', 'password'];
    foreach ($campos as $campo) {
        if (empty(trim($body[$campo] ?? ''))) {
            badRequest("El campo {$campo} es obligatorio.");
        }
    }

    $datos = [
        'firstname' => trim($body['firstname']),
        'lastname'  => trim($body['lastname']),
        'email'     => trim($body['email']),
        'username'  => trim($body['username']),
        'password'  => $body['password'],
    ];

    try {
        $resultado = (new ActivacionService())->activarGestor(trim($body['hash']), $datos);
        while (ob_get_level() > 0) { ob_end_clean(); }
        echo json_encode($resultado);
    } catch (InvalidArgumentException $e) {
        while (ob_get_level() > 0) { ob_end_clean(); }
        http_response_code(422);
        echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
    }
}

/**
 * POST /api/activar/registro
 * Body: { firstname, lastname, email, username, password }
 *
 * Crea la cuenta Moodle del usuario regular sin asignarle ningún rol
 * de categoría. El frontend llamará a /activar/pin justo después.
 */
function handleRegistrarUsuario(): void
{
    $body = readJsonBody();

    $campos = ['firstname', 'lastname', 'email', 'username', 'password'];
    foreach ($campos as $campo) {
        if (empty(trim($body[$campo] ?? ''))) {
            badRequest("El campo {$campo} es obligatorio.");
        }
    }

    $datos = [
        'firstname' => trim($body['firstname']),
        'lastname'  => trim($body['lastname']),
        'email'     => trim($body['email']),
        'username'  => strtolower(trim($body['username'])),
        'password'  => $body['password'],
    ];

    try {
        $resultado = (new ActivacionService())->registrarUsuario($datos);
        while (ob_get_level() > 0) { ob_end_clean(); }
        echo json_encode($resultado);
    } catch (InvalidArgumentException $e) {
        while (ob_get_level() > 0) { ob_end_clean(); }
        http_response_code(422);
        echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
    }
}

/**
 * POST /api/activar/login
 * Body: { username: string, password: string }
 *
 * Verifica las credenciales Moodle sin iniciar sesión ni cambiar estado.
 * Retorna el user_id para que el frontend lo use en /activar/pin.
 */
function handleActivarLogin(): void
{
    $body     = readJsonBody();
    $username = trim($body['username'] ?? '');
    $password = $body['password'] ?? '';

    if ($username === '') badRequest('El campo username es obligatorio.');
    if ($password === '') badRequest('El campo password es obligatorio.');

    $user = authenticate_user_login($username, $password);

    if (!$user) {
        while (ob_get_level() > 0) { ob_end_clean(); }
        http_response_code(401);
        echo json_encode(['ok' => false, 'error' => 'Usuario o contraseña incorrectos.']);
        return;
    }

    while (ob_get_level() > 0) { ob_end_clean(); }
    echo json_encode([
        'ok'       => true,
        'user_id'  => (int)$user->id,
        'username' => $user->username,
        'fullname' => fullname($user),
    ]);
}

/**
 * POST /api/activar/pin
 * Body: { hash: string, user_id: int }
 */
function handleActivarPin(): void
{
    $body   = readJsonBody();
    $hash   = trim($body['hash'] ?? '');
    $userId = isset($body['user_id']) ? (int)$body['user_id'] : 0;

    if ($hash   === '') badRequest('El campo hash es obligatorio.');
    if ($userId === 0)  badRequest('El campo user_id es obligatorio.');

    try {
        $resultado = (new ActivacionService())->activarPin($hash, $userId);
        while (ob_get_level() > 0) { ob_end_clean(); }
        echo json_encode($resultado);
    } catch (InvalidArgumentException $e) {
        while (ob_get_level() > 0) { ob_end_clean(); }
        http_response_code(422);
        echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
    }
}
