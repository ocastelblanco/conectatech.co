<?php
/**
 * handlers/dashboard.php
 * Handlers del resumen global del dashboard de administración.
 *
 * Rutas (todas requieren rol administrador):
 *   GET /api/dashboard/resumen  → KPIs globales (ambos tracks)
 *   GET /api/dashboard/cursos   → cursos con métricas de progreso
 */

function handleGetDashboardResumen(): void
{
    $svc  = new DashboardService();
    $data = $svc->getResumen();
    while (ob_get_level() > 0) { ob_end_clean(); }
    echo json_encode(['ok' => true, 'data' => $data]);
}

function handleGetDashboardCursos(): void
{
    $svc  = new DashboardService();
    $data = $svc->getCursos();
    while (ob_get_level() > 0) { ob_end_clean(); }
    echo json_encode(['ok' => true, 'data' => $data]);
}

function handleGetDashboardOrganizaciones(): void
{
    $svc  = new DashboardService();
    $data = $svc->getOrganizaciones();
    while (ob_get_level() > 0) { ob_end_clean(); }
    echo json_encode(['ok' => true, 'data' => $data]);
}
