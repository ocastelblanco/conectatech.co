#!/usr/bin/env php
<?php
/**
 * crear-cursos.php — Crea cursos finales desde CSV, copiando formato e imagen
 * del curso plantilla.
 *
 * Uso:
 *   sudo -u apache php /var/www/html/admin/backend/crear-cursos.php \
 *       [--file config/cursos-finales.csv] \
 *       [--course san-marino-cn-6] \
 *       [--dry-run]
 */

// ─────────────────────────────────────────────────────────────────────────────
// Rutas
// ─────────────────────────────────────────────────────────────────────────────

define('BACKEND_DIR', __DIR__);
define('CONFIG_DIR',  BACKEND_DIR . '/config');
define('LIB_DIR',     BACKEND_DIR . '/lib');
define('LOGS_DIR',    BACKEND_DIR . '/logs');

require_once(LIB_DIR . '/MoodleBootstrap.php');
require_once(LIB_DIR . '/CsvLoader.php');
require_once(LIB_DIR . '/CursosService.php');

// ─────────────────────────────────────────────────────────────────────────────
// Argumentos CLI
// ─────────────────────────────────────────────────────────────────────────────

$opts       = getopt('', ['file:', 'course:', 'dry-run']);
$csvFile    = $opts['file']   ?? CONFIG_DIR . '/cursos-finales.csv';
$onlyCourse = $opts['course'] ?? null;
$dryRun     = isset($opts['dry-run']);

if (!file_exists($csvFile)) {
    fwrite(STDERR, "ERROR: No se encontró el CSV: {$csvFile}\n");
    exit(1);
}

// ─────────────────────────────────────────────────────────────────────────────
// Banner
// ─────────────────────────────────────────────────────────────────────────────

$startTime = microtime(true);

println("╔══════════════════════════════════════════════════════════╗");
println("║  ConectaTech — Creador de Cursos Finales                 ║");
println("╚══════════════════════════════════════════════════════════╝");
println("CSV      : {$csvFile}");
println("Filtro   : " . ($onlyCourse ?? '(todos)'));
println("Dry-run  : " . ($dryRun ? 'SÍ' : 'NO'));
println(str_repeat("─", 60));

// ─────────────────────────────────────────────────────────────────────────────
// Cargar CSV
// ─────────────────────────────────────────────────────────────────────────────

$rows = array_filter(CsvLoader::loadRows($csvFile), fn($r) => !empty($r['shortname']));

if (empty($rows)) {
    fwrite(STDERR, "ERROR: CSV vacío o sin filas válidas.\n");
    exit(1);
}

println("\nCursos en CSV: " . count($rows));

// ─────────────────────────────────────────────────────────────────────────────
// Procesar cada fila
// ─────────────────────────────────────────────────────────────────────────────

$service = new CursosService(CONFIG_DIR);

$report = [
    'timestamp'       => date('c'),
    'dry_run'         => $dryRun,
    'elapsed_seconds' => 0,
    'courses'         => [],
    'errors'          => [],
];

$okCount   = 0;
$skipCount = 0;
$errCount  = 0;

foreach ($rows as $row) {
    $shortname = $row['shortname'];

    if ($onlyCourse && $shortname !== $onlyCourse) {
        continue;
    }

    println("\n→ {$shortname}  ({$row['fullname']})");

    try {
        $result = $service->crearCurso($row, $dryRun);

        switch ($result['action']) {
            case 'skipped':
                println("   skip — el curso ya existe (id={$result['course_id']})");
                $skipCount++;
                break;
            case 'dry-run':
                println("   [dry-run] crearía el curso en categoría '{$row['category']}'");
                break;
            case 'created':
                println("   creado id={$result['course_id']}");
                $okCount++;
                break;
        }

    } catch (Throwable $e) {
        $msg = $e->getMessage();
        println("   ERROR: {$msg}");
        fwrite(STDERR, $e->getTraceAsString() . "\n");
        $result = [
            'shortname' => $shortname,
            'fullname'  => $row['fullname'],
            'category'  => $row['category'],
            'template'  => $row['templatecourse'],
            'action'    => 'error',
            'course_id' => null,
            'error'     => $msg,
        ];
        $report['errors'][] = "[{$shortname}] {$msg}";
        $errCount++;
    }

    $report['courses'][] = $result;
}

// ─────────────────────────────────────────────────────────────────────────────
// Resumen y reporte
// ─────────────────────────────────────────────────────────────────────────────

$elapsed = round(microtime(true) - $startTime, 2);
$report['elapsed_seconds'] = $elapsed;

$reportPath = BACKEND_DIR . '/report-ultimo-creacion.json';
file_put_contents($reportPath, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

println("\n" . str_repeat("─", 60));
println("✓ COMPLETADO en {$elapsed}s");
println("  creados={$okCount}  omitidos={$skipCount}  errores={$errCount}");
println("  Reporte: {$reportPath}");
println(str_repeat("─", 60));

exit($errCount > 0 ? 2 : 0);

// ─────────────────────────────────────────────────────────────────────────────
// Funciones auxiliares CLI
// ─────────────────────────────────────────────────────────────────────────────

function println(string $msg): void
{
    echo $msg . "\n";
}
