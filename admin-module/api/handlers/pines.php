<?php
/**
 * handlers/pines.php
 * Handlers de la API REST para paquetes de pines y reporte de uso.
 *
 * Rutas (todas requieren rol administrador):
 *   POST /api/paquetes                   → crear paquete + generar N pines
 *   GET  /api/paquetes                   → listar paquetes [?org_id=X]
 *   POST /api/paquetes/{id}/asignar      → reasignar paquete a otra organización
 *   GET  /api/pines/reporte              → reporte de uso [?org_id=X&package_id=Y]
 */

function handleCrearPaquete(): void
{
    global $USER;

    $body        = readJsonBody();
    $orgId        = isset($body['organization_id']) ? (int)$body['organization_id'] : 0;
    $teacherRole  = trim($body['teacher_role'] ?? '');
    $durationDays = isset($body['duration_days']) ? (int)$body['duration_days'] : 0;
    $cantidad     = isset($body['cantidad'])      ? (int)$body['cantidad']      : 0;

    if ($orgId === 0)        badRequest('El campo organization_id es obligatorio.');
    if ($teacherRole === '')  badRequest('El campo teacher_role es obligatorio.');
    if ($durationDays === 0)  badRequest('El campo duration_days es obligatorio (93, 182 o 365).');
    if ($cantidad === 0)      badRequest('El campo cantidad es obligatorio.');

    $svc    = new PinesService();
    $result = $svc->crearPaquete($orgId, $teacherRole, $durationDays, $cantidad, (int)$USER->id);
    while (ob_get_level() > 0) { ob_end_clean(); }
    http_response_code(201);
    echo json_encode(['ok' => true, 'data' => $result]);
}

function handleListarPaquetes(): void
{
    $orgId = isset($_GET['org_id']) ? (int)$_GET['org_id'] : null;

    $svc  = new PinesService();
    $data = $svc->listarPaquetes($orgId ?: null);
    while (ob_get_level() > 0) { ob_end_clean(); }
    echo json_encode(['ok' => true, 'data' => $data]);
}

function handleAsignarPaquete(int $packageId): void
{
    $body  = readJsonBody();
    $orgId = isset($body['organization_id']) ? (int)$body['organization_id'] : 0;

    if ($orgId === 0) badRequest('El campo organization_id es obligatorio.');

    $svc = new PinesService();
    $svc->asignarPaquete($packageId, $orgId);
    while (ob_get_level() > 0) { ob_end_clean(); }
    echo json_encode(['ok' => true]);
}

function handleReportePines(): void
{
    $orgId     = isset($_GET['org_id'])     ? (int)$_GET['org_id']     : null;
    $packageId = isset($_GET['package_id']) ? (int)$_GET['package_id'] : null;

    $svc  = new PinesService();
    $data = $svc->reporte($orgId ?: null, $packageId ?: null);
    while (ob_get_level() > 0) { ob_end_clean(); }
    echo json_encode(['ok' => true, 'data' => $data]);
}
