#!/usr/bin/env php
<?php
/**
 * poblar-cursos.php — Puebla cursos finales con secciones de repositorios
 * usando backup/restore nativo de Moodle.
 *
 * Uso:
 *   sudo -u apache php /var/www/html/admin/backend/poblar-cursos.php \
 *       [--mapping config/poblamiento.json] \
 *       [--course san-marino-cn-6] \
 *       [--dry-run]
 *
 * Requiere ejecutarse como usuario 'apache'.
 */

// ─────────────────────────────────────────────────────────────────────────────
// Rutas
// ─────────────────────────────────────────────────────────────────────────────

define('BACKEND_DIR', __DIR__);
define('CONFIG_DIR',  BACKEND_DIR . '/config');
define('LIB_DIR',     BACKEND_DIR . '/lib');
define('LOGS_DIR',    BACKEND_DIR . '/logs');

require_once(LIB_DIR . '/MoodleBootstrap.php');
require_once(LIB_DIR . '/MoodleSectionCloner.php');
require_once(LIB_DIR . '/PobladorService.php');

// ─────────────────────────────────────────────────────────────────────────────
// Argumentos CLI
// ─────────────────────────────────────────────────────────────────────────────

$opts        = getopt('', ['mapping:', 'course:', 'dry-run']);
$mappingFile = $opts['mapping'] ?? CONFIG_DIR . '/poblamiento.json';
$onlyCourse  = $opts['course']  ?? null;
$dryRun      = isset($opts['dry-run']);

if (!file_exists($mappingFile)) {
    fwrite(STDERR, "ERROR: No se encontró el mapping: {$mappingFile}\n");
    exit(1);
}

// ─────────────────────────────────────────────────────────────────────────────
// Banner
// ─────────────────────────────────────────────────────────────────────────────

$startTime = microtime(true);

println("╔══════════════════════════════════════════════════════════╗");
println("║  ConectaTech — Poblador de Cursos Finales                ║");
println("╚══════════════════════════════════════════════════════════╝");
println("Mapping  : {$mappingFile}");
println("Filtro   : " . ($onlyCourse ?? '(todos)'));
println("Dry-run  : " . ($dryRun ? 'SÍ' : 'NO'));
println(str_repeat("─", 60));

// ─────────────────────────────────────────────────────────────────────────────
// Cargar mapping JSON
// ─────────────────────────────────────────────────────────────────────────────

$mapping = json_decode(file_get_contents($mappingFile), true);

if (json_last_error() !== JSON_ERROR_NONE || empty($mapping['courses'])) {
    fwrite(STDERR, "ERROR: JSON inválido o sin clave 'courses': {$mappingFile}\n");
    exit(1);
}

$courses = $mapping['courses'];

if ($onlyCourse) {
    $courses = array_values(array_filter($courses, fn($c) => $c['shortname'] === $onlyCourse));
    if (empty($courses)) {
        fwrite(STDERR, "ERROR: El shortname '{$onlyCourse}' no está en el mapping.\n");
        exit(1);
    }
}

println("\nCursos a poblar: " . count($courses));

// ─────────────────────────────────────────────────────────────────────────────
// Procesar cada curso
// ─────────────────────────────────────────────────────────────────────────────

$service = new PobladorService();

$report = [
    'timestamp'       => date('c'),
    'dry_run'         => $dryRun,
    'elapsed_seconds' => 0,
    'courses'         => [],
    'errors'          => [],
];

$totalOk  = 0;
$totalErr = 0;

foreach ($courses as $courseConfig) {
    $targetSn = $courseConfig['shortname'];
    $sections = $courseConfig['sections'] ?? [];

    println("\n→ {$targetSn}  (" . count($sections) . " secciones)");

    try {
        $result = $service->poblarCurso($targetSn, $sections, $dryRun);

        println("   curso destino id={$result['target_id']}");

        foreach ($result['sections'] as $sec) {
            $label = "[{$sec['repo']} §{$sec['section_num']}]";
            if ($sec['action'] === 'dry-run') {
                println("   {$label} [dry-run] clonaría");
            } elseif ($sec['action'] === 'cloned') {
                println("   {$label} ✓ clonado");
                $totalOk++;
            } elseif ($sec['action'] === 'error') {
                println("   {$label} ERROR: {$sec['error']}");
                $totalErr++;
            }
        }

        if (!empty($result['errors'])) {
            foreach ($result['errors'] as $err) {
                $report['errors'][] = "[{$targetSn}] {$err}";
            }
        }

    } catch (Throwable $e) {
        $msg = $e->getMessage();
        println("   ERROR: {$msg}");
        fwrite(STDERR, $e->getTraceAsString() . "\n");
        $result = [
            'shortname' => $targetSn,
            'target_id' => null,
            'sections'  => [],
            'errors'    => [$msg],
            'cloned'    => 0,
        ];
        $report['errors'][] = "[{$targetSn}] {$msg}";
        $totalErr++;
    }

    $report['courses'][] = $result;
}

// ─────────────────────────────────────────────────────────────────────────────
// Resumen y reporte
// ─────────────────────────────────────────────────────────────────────────────

$elapsed = round(microtime(true) - $startTime, 2);
$report['elapsed_seconds'] = $elapsed;

$reportPath = BACKEND_DIR . '/report-ultimo-poblamiento.json';
file_put_contents($reportPath, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

println("\n" . str_repeat("─", 60));
println("✓ COMPLETADO en {$elapsed}s");
println("  secciones clonadas={$totalOk}  errores={$totalErr}");
println("  Reporte: {$reportPath}");
println(str_repeat("─", 60));

exit($totalErr > 0 ? 2 : 0);

// ─────────────────────────────────────────────────────────────────────────────
// Funciones auxiliares CLI
// ─────────────────────────────────────────────────────────────────────────────

function println(string $msg): void
{
    echo $msg . "\n";
}
