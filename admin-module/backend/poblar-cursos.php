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
// Preparar
// ─────────────────────────────────────────────────────────────────────────────

$cloner    = new MoodleSectionCloner();
$adminId   = MoodleSectionCloner::getAdminUserId();

$report = [
    'timestamp'       => date('c'),
    'dry_run'         => $dryRun,
    'elapsed_seconds' => 0,
    'courses'         => [],
    'errors'          => [],
];

$totalOk  = 0;
$totalErr = 0;

// ─────────────────────────────────────────────────────────────────────────────
// Procesar cada curso
// ─────────────────────────────────────────────────────────────────────────────

foreach ($courses as $courseConfig) {
    $targetSn   = $courseConfig['shortname'];
    $sections   = $courseConfig['sections'] ?? [];

    println("\n→ {$targetSn}  (" . count($sections) . " secciones)");

    $courseResult = [
        'shortname' => $targetSn,
        'sections'  => [],
        'errors'    => [],
    ];

    // Verificar que el curso final existe
    try {
        $targetId = MoodleSectionCloner::resolveCourseId($targetSn);
        println("   curso destino id={$targetId}");
    } catch (Throwable $e) {
        $msg = "Curso final no encontrado: {$e->getMessage()}";
        println("   ERROR: {$msg}");
        $courseResult['errors'][] = $msg;
        $report['errors'][]       = "[{$targetSn}] {$msg}";
        $report['courses'][]      = $courseResult;
        $totalErr++;
        continue;
    }

    // Clonar cada sección
    foreach ($sections as $secConfig) {
        $repoSn     = $secConfig['repo'];
        $sectionNum = (int)$secConfig['section_num'];

        $secResult = [
            'repo'        => $repoSn,
            'section_num' => $sectionNum,
            'action'      => '',
            'error'       => null,
        ];

        println("   [{$repoSn} §{$sectionNum}]");

        try {
            $repoId = MoodleSectionCloner::resolveCourseId($repoSn);

            if ($dryRun) {
                println("      [dry-run] clonaría sección {$sectionNum} de '{$repoSn}' (id={$repoId}) → '{$targetSn}' (id={$targetId})");
                $secResult['action'] = 'dry-run';
            } else {
                $cloner->cloneSection($repoId, $sectionNum, $targetId, $adminId);
                println("      ✓ clonado");
                $secResult['action'] = 'cloned';
                $totalOk++;
            }

        } catch (Throwable $e) {
            $msg = $e->getMessage();
            println("      ERROR: {$msg}");
            fwrite(STDERR, $e->getTraceAsString() . "\n");
            $secResult['action'] = 'error';
            $secResult['error']  = $msg;
            $courseResult['errors'][] = "[{$repoSn} §{$sectionNum}] {$msg}";
            $report['errors'][]       = "[{$targetSn}/{$repoSn} §{$sectionNum}] {$msg}";
            $totalErr++;
        }

        $courseResult['sections'][] = $secResult;

        // Liberar memoria entre clonaciones
        gc_collect_cycles();
    }

    $report['courses'][] = $courseResult;
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
// Funciones auxiliares
// ─────────────────────────────────────────────────────────────────────────────

function println(string $msg): void
{
    echo $msg . "\n";
}
