#!/usr/bin/env php
<?php
/**
 * procesar-markdown.php — Script principal de procesamiento Markdown → Moodle.
 *
 * Uso:
 *   sudo -u apache php /var/www/html/admin/backend/procesar-markdown.php \
 *       --file /tmp/ciencias-naturales-6-7.md \
 *       --course repo-cn-6-7
 *
 * Requiere ejecutarse como usuario 'apache' para que los archivos creados
 * en moodledata tengan los permisos correctos.
 */

// ─────────────────────────────────────────────────────────────────────────────
// Rutas
// ─────────────────────────────────────────────────────────────────────────────

define('BACKEND_DIR', __DIR__);
define('CONFIG_DIR',  BACKEND_DIR . '/config');
define('LIB_DIR',     BACKEND_DIR . '/lib');
define('LOGS_DIR',    BACKEND_DIR . '/logs');

require_once(LIB_DIR . '/MoodleBootstrap.php');
require_once(LIB_DIR . '/MarkdownParser.php');
require_once(LIB_DIR . '/HtmlConverter.php');
require_once(LIB_DIR . '/PresaberesHtmlBuilder.php');
require_once(LIB_DIR . '/GiftConverter.php');
require_once(LIB_DIR . '/MoodleContentBuilder.php');
require_once(LIB_DIR . '/CsvLoader.php');
require_once(LIB_DIR . '/MarkdownService.php');

// ─────────────────────────────────────────────────────────────────────────────
// Argumentos CLI
// ─────────────────────────────────────────────────────────────────────────────

$opts      = getopt('', ['file:', 'course:']);
$mdFile    = $opts['file']   ?? null;
$shortname = $opts['course'] ?? null;

if (!$mdFile || !$shortname) {
    fwrite(STDERR, implode("\n", [
        "ConectaTech — Procesador Markdown → Moodle",
        "",
        "Uso:",
        "  sudo -u apache php procesar-markdown.php --file <ruta.md> --course <shortname>",
        "",
        "Ejemplo:",
        "  sudo -u apache php /var/www/html/admin/backend/procesar-markdown.php \\",
        "      --file /tmp/ciencias-naturales-6-7.md --course repo-cn-6-7",
        "",
    ]) . "\n");
    exit(1);
}

if (!file_exists($mdFile)) {
    fwrite(STDERR, "ERROR: Archivo no encontrado: {$mdFile}\n");
    exit(1);
}

// ─────────────────────────────────────────────────────────────────────────────
// Mapa de cursos
// ─────────────────────────────────────────────────────────────────────────────

$courseMap = loadCoursesMap(CONFIG_DIR . '/courses.csv');

if (!isset($courseMap[$shortname])) {
    fwrite(STDERR, "ERROR: Shortname '{$shortname}' no encontrado en courses.csv.\n");
    fwrite(STDERR, "Shornames disponibles:\n");
    foreach (array_keys($courseMap) as $sn) {
        fwrite(STDERR, "  - {$sn}\n");
    }
    exit(1);
}

$courseData = $courseMap[$shortname];

// ─────────────────────────────────────────────────────────────────────────────
// Logging
// ─────────────────────────────────────────────────────────────────────────────

ini_set('error_log', LOGS_DIR . '/automation.log');

// ─────────────────────────────────────────────────────────────────────────────
// Banner
// ─────────────────────────────────────────────────────────────────────────────

$startTime = microtime(true);

println("╔══════════════════════════════════════════════════════════╗");
println("║  ConectaTech — Procesador Markdown → Moodle              ║");
println("╚══════════════════════════════════════════════════════════╝");
println("Archivo  : {$mdFile}");
println("Curso    : {$shortname}");
println("Nombre   : {$courseData['fullname']}");
println("Categoría: {$courseData['category_path']}");
println(str_repeat("─", 60));

$report = [
    'shortname'       => $shortname,
    'fullname'        => $courseData['fullname'],
    'file'            => $mdFile,
    'timestamp'       => date('c'),
    'elapsed_seconds' => 0,
    'sections'        => [],
    'summary'         => [],
    'errors'          => [],
];

// ─────────────────────────────────────────────────────────────────────────────
// Procesar
// ─────────────────────────────────────────────────────────────────────────────

try {
    println("\nProcesando secciones...\n");

    $service = new MarkdownService(CONFIG_DIR);
    $result  = $service->procesarArchivo($shortname, $courseData, $mdFile);

    $report['sections'] = $result['sections'];
    $report['errors']   = $result['errors'];

    // Imprimir progreso por sección
    $total = $result['summary']['total_sections'];
    foreach ($result['sections'] as $i => $sec) {
        $num  = $i + 1;
        $pad  = str_pad($num, strlen($total), '0', STR_PAD_LEFT);
        $icon = $sec['action'] === 'created' ? '✚' : ($sec['action'] === 'updated' ? '↺' : '?');
        println("  [{$pad}/{$total}] {$sec['title']}");
        println("         {$icon} {$sec['action']} — " . count($sec['subsections']) . " subsecciones");

        if (!empty($sec['errors'])) {
            foreach ($sec['errors'] as $err) {
                println("         ✗ ERROR: {$err}");
            }
        }
    }

    $elapsed = round(microtime(true) - $startTime, 2);
    $report['elapsed_seconds'] = $elapsed;
    $report['summary']         = $result['summary'];

    $reportPath = BACKEND_DIR . '/report-ultimo.json';
    file_put_contents($reportPath, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

    $s = $result['summary'];
    println("\n" . str_repeat("─", 60));
    println("✓ COMPLETADO en {$elapsed}s");
    println("  Secciones: creadas={$s['sections_created']}  actualizadas={$s['sections_updated']}  errores={$s['total_errors']}");
    println("  Reporte  : {$reportPath}");
    println(str_repeat("─", 60));

    exit($s['total_errors'] > 0 ? 2 : 0);

} catch (Throwable $e) {
    $elapsed = round(microtime(true) - $startTime, 2);
    $msg     = "ERROR FATAL: " . $e->getMessage();

    fwrite(STDERR, "\n" . $msg . "\n");
    fwrite(STDERR, $e->getTraceAsString() . "\n");

    $report['errors'][]        = $msg;
    $report['elapsed_seconds'] = $elapsed;

    $reportPath = BACKEND_DIR . '/report-ultimo.json';
    file_put_contents($reportPath, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

    exit(1);
}

// ─────────────────────────────────────────────────────────────────────────────
// Funciones auxiliares CLI
// ─────────────────────────────────────────────────────────────────────────────

function println(string $msg): void
{
    echo $msg . "\n";
}

/**
 * Carga el mapa shortname → datos del curso desde courses.csv.
 *
 * @return array<string, array{shortname: string, fullname: string, category_path: string}>
 */
function loadCoursesMap(string $csvPath): array
{
    if (!file_exists($csvPath)) {
        throw new RuntimeException("No se encontró courses.csv en: {$csvPath}");
    }

    $map = [];
    $fh  = fopen($csvPath, 'r');
    fgetcsv($fh); // saltar cabecera

    while ($row = fgetcsv($fh)) {
        if (count($row) < 3) {
            continue;
        }
        $sn       = trim($row[0]);
        $map[$sn] = [
            'shortname'     => $sn,
            'fullname'      => trim($row[1]),
            'category_path' => trim($row[2]),
        ];
    }

    fclose($fh);
    return $map;
}
