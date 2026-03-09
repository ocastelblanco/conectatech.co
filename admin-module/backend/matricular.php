#!/usr/bin/env php
<?php
/**
 * matricular.php — Crea/actualiza usuarios Moodle y los matricula en sus cursos.
 *
 * Uso:
 *   sudo -u apache php /var/www/html/admin/backend/matricular.php \
 *       [--file config/matriculas.csv] \
 *       [--user <username>] \
 *       [--dry-run]
 *
 * Columnas del CSV:
 *   username, password, firstname, lastname, email, institution, rol, grado
 *
 *   - institution : nombre exacto de la subcategoría bajo COLEGIOS/ (e.g., "San Marino")
 *   - rol         : "student" o "teacher"
 *   - grado       : número 1–11 para student; vacío para teacher
 *   - password    : ignorado si el usuario ya existe
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
require_once(LIB_DIR . '/MatriculasService.php');

// ─────────────────────────────────────────────────────────────────────────────
// Argumentos CLI
// ─────────────────────────────────────────────────────────────────────────────

$opts     = getopt('', ['file:', 'user:', 'dry-run']);
$csvFile  = $opts['file'] ?? CONFIG_DIR . '/matriculas.csv';
$onlyUser = $opts['user'] ?? null;
$dryRun   = isset($opts['dry-run']);

if (!file_exists($csvFile)) {
    fwrite(STDERR, "ERROR: No se encontró el CSV: {$csvFile}\n");
    exit(1);
}

// ─────────────────────────────────────────────────────────────────────────────
// Banner
// ─────────────────────────────────────────────────────────────────────────────

$startTime = microtime(true);

println("╔══════════════════════════════════════════════════════════╗");
println("║  ConectaTech — Matriculación Masiva                      ║");
println("╚══════════════════════════════════════════════════════════╝");
println("CSV      : {$csvFile}");
println("Filtro   : " . ($onlyUser ?? '(todos)'));
println("Dry-run  : " . ($dryRun ? 'SÍ' : 'NO'));
println(str_repeat("─", 60));

// ─────────────────────────────────────────────────────────────────────────────
// Cargar CSV
// ─────────────────────────────────────────────────────────────────────────────

$rows = array_filter(CsvLoader::loadRows($csvFile), fn($r) => !empty($r['username']));

if (empty($rows)) {
    fwrite(STDERR, "ERROR: CSV vacío o sin filas válidas.\n");
    exit(1);
}

println("\nUsuarios en CSV: " . count($rows));

// ─────────────────────────────────────────────────────────────────────────────
// Procesar cada fila
// ─────────────────────────────────────────────────────────────────────────────

$service = new MatriculasService();

$report = [
    'timestamp'       => date('c'),
    'dry_run'         => $dryRun,
    'elapsed_seconds' => 0,
    'users'           => [],
    'errors'          => [],
];

$okCount  = 0;
$errCount = 0;

foreach ($rows as $row) {
    $username = strtolower(trim($row['username']));

    if ($onlyUser && $username !== strtolower($onlyUser)) {
        continue;
    }

    println("\n→ {$username}  ({$row['firstname']} {$row['lastname']})");

    try {
        $result = $service->matricularUsuario($row, $dryRun);

        switch ($result['action']) {
            case 'dry-run:create':
                println("   [dry-run] crearía usuario");
                break;
            case 'dry-run:update':
                println("   [dry-run] actualizaría usuario");
                break;
            case 'created':
                println("   creado id={$result['user_id']}");
                break;
            case 'updated':
                println("   actualizado id={$result['user_id']}");
                break;
        }

        if (!empty($result['courses'])) {
            println("   cursos encontrados: " . count($result['courses']));
            foreach ($result['courses'] as $sn) {
                println("   ✓ matriculado en {$sn}");
            }
        } else {
            println("   AVISO: No se encontraron cursos para esta institución/grado");
        }

        $okCount++;

    } catch (Throwable $e) {
        $msg = $e->getMessage();
        println("   ERROR: {$msg}");
        fwrite(STDERR, $e->getTraceAsString() . "\n");
        $result = [
            'username' => $username,
            'action'   => 'error',
            'error'    => $msg,
            'courses'  => [],
        ];
        $report['errors'][] = "[{$username}] {$msg}";
        $errCount++;
    }

    $report['users'][] = $result;
}

// ─────────────────────────────────────────────────────────────────────────────
// Resumen y reporte
// ─────────────────────────────────────────────────────────────────────────────

$elapsed = round(microtime(true) - $startTime, 2);
$report['elapsed_seconds'] = $elapsed;

$reportPath = BACKEND_DIR . '/report-ultimo-matriculas.json';
file_put_contents($reportPath, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

println("\n" . str_repeat("─", 60));
println("✓ COMPLETADO en {$elapsed}s");
println("  procesados={$okCount}  errores={$errCount}");
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
