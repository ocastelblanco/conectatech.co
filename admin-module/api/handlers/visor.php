<?php
/**
 * handlers/visor.php — Contenido de un tema para el visor fullscreen.
 *
 * GET /api/visor/tema?course=ID&section=ID
 *
 * Autorización: la resuelve auth.php (rama visor/*) ANTES de llegar aquí:
 * sesión Moodle no invitada (401) + moodle/course:viewhiddenactivities en el
 * contexto del curso (403). Este handler solo valida la sección (400/404).
 */

/**
 * Response 200: { ok: true, titulo: string, cabecera: string|null,
 *                 subtemas: [{ titulo: string, bloques: string[] }] }
 * Response 400: parámetros inválidos.
 * Response 404: la sección no existe en ese curso, no es un tema o no es visible.
 */
function handleGetVisorTema(): void
{
    $sectionId = filter_var($_GET['section'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if ($sectionId === false) {
        badRequest('El parámetro section debe ser un entero positivo.');
    }
    // course ya fue validado por auth.php (rama visor/*).
    $courseId = (int)$_GET['course'];

    $tema = (new VisorService())->obtenerTema($courseId, $sectionId);

    if ($tema === null) {
        http_response_code(404);
        echo json_encode(['ok' => false, 'error' => 'Tema no encontrado en el curso indicado.']);
        return;
    }

    // El contenido depende del usuario y de su sesión: nunca cachear.
    header('Cache-Control: private, no-store');
    echo json_encode(['ok' => true] + $tema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}
