<?php
/**
 * handlers/reportes.php — Sirve los últimos reportes JSON de operaciones.
 *
 * GET /api/reportes/{nombre}
 */

// ─────────────────────────────────────────────────────────────────────────────
// GET /api/reportes/{nombre}
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Devuelve el contenido del último reporte JSON generado por una operación.
 *
 * Útil para que el frontend muestre el resultado detallado de la última
 * ejecución de cada operación sin necesidad de recibirlo en la respuesta
 * de la operación misma (que puede ser muy grande).
 *
 * Nombres válidos:
 *   matriculas   → report-ultimo-matriculas.json
 *   creacion     → report-ultimo-creacion.json
 *   poblamiento  → report-ultimo-poblamiento.json
 *   markdown     → report-ultimo.json
 *
 * Response 200:
 *   { ok: true, report: { ...contenido del JSON... } }
 *
 * Response 404:
 *   - Nombre no reconocido: lista los nombres válidos.
 *   - Archivo no existe: indica que la operación no se ha ejecutado aún.
 *
 * @param string $nombre  Nombre del reporte (segmento de URL).
 */
function handleReportes(string $nombre): void
{
    $fileMap = [
        'matriculas'  => BACKEND_DIR . '/report-ultimo-matriculas.json',
        'creacion'    => BACKEND_DIR . '/report-ultimo-creacion.json',
        'poblamiento' => BACKEND_DIR . '/report-ultimo-poblamiento.json',
        'markdown'    => BACKEND_DIR . '/report-ultimo.json',
    ];

    if (!isset($fileMap[$nombre])) {
        http_response_code(404);
        echo json_encode([
            'ok'        => false,
            'error'     => "Reporte '{$nombre}' no reconocido.",
            'available' => array_keys($fileMap),
        ]);
        return;
    }

    $path = $fileMap[$nombre];

    if (!file_exists($path)) {
        http_response_code(404);
        echo json_encode([
            'ok'    => false,
            'error' => "El reporte '{$nombre}' no existe aún. Ejecuta la operación primero.",
        ]);
        return;
    }

    $report = json_decode(file_get_contents($path), true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        http_response_code(500);
        echo json_encode([
            'ok'    => false,
            'error' => "El reporte '{$nombre}' está corrupto: " . json_last_error_msg(),
        ]);
        return;
    }

    echo json_encode(['ok' => true, 'report' => $report]);
}
