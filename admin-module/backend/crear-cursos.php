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

// ─────────────────────────────────────────────────────────────────────────────
// Argumentos CLI
// ─────────────────────────────────────────────────────────────────────────────

$opts      = getopt('', ['file:', 'course:', 'dry-run']);
$csvFile   = $opts['file']   ?? CONFIG_DIR . '/cursos-finales.csv';
$onlyCourse = $opts['course'] ?? null;
$dryRun    = isset($opts['dry-run']);

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
// Reporte
// ─────────────────────────────────────────────────────────────────────────────

$report = [
    'timestamp'       => date('c'),
    'dry_run'         => $dryRun,
    'elapsed_seconds' => 0,
    'courses'         => [],
    'errors'          => [],
];

// ─────────────────────────────────────────────────────────────────────────────
// Cargar CSV
// ─────────────────────────────────────────────────────────────────────────────

$rows = loadCsvRows($csvFile);

if (empty($rows)) {
    fwrite(STDERR, "ERROR: CSV vacío o sin filas válidas.\n");
    exit(1);
}

println("\nCursos en CSV: " . count($rows));

// ─────────────────────────────────────────────────────────────────────────────
// Procesar cada fila
// ─────────────────────────────────────────────────────────────────────────────

global $DB, $CFG;
require_once($CFG->dirroot . '/course/lib.php');

$okCount   = 0;
$skipCount = 0;
$errCount  = 0;

foreach ($rows as $row) {
    $shortname      = $row['shortname'];
    $fullname       = $row['fullname'];
    $categoryPath   = $row['category'];
    $templateSn     = $row['templatecourse'];

    // Filtrar por --course si se especificó
    if ($onlyCourse && $shortname !== $onlyCourse) {
        continue;
    }

    println("\n→ {$shortname}  ({$fullname})");

    $courseResult = [
        'shortname' => $shortname,
        'fullname'  => $fullname,
        'category'  => $categoryPath,
        'template'  => $templateSn,
        'action'    => '',
        'error'     => null,
    ];

    try {
        // ── Verificar si ya existe ────────────────────────────────────────────
        $existing = $DB->get_record('course', ['shortname' => $shortname]);

        if ($existing) {
            println("   skip — el curso ya existe (id={$existing->id})");
            $courseResult['action'] = 'skip';
            $skipCount++;
            $report['courses'][] = $courseResult;
            continue;
        }

        if ($dryRun) {
            println("   [dry-run] crearía el curso en categoría '{$categoryPath}'");
            $courseResult['action'] = 'dry-run';
            $report['courses'][] = $courseResult;
            continue;
        }

        // ── Garantizar jerarquía de categorías ───────────────────────────────
        $categoryId = ensureCategoryPath($categoryPath);
        println("   categoría id={$categoryId}");

        // ── Crear el curso ────────────────────────────────────────────────────
        $data = (object)[
            'fullname'    => $fullname,
            'shortname'   => $shortname,
            'category'    => $categoryId,
            'visible'     => 0,
            'format'      => 'topics',
            'numsections' => 0,
        ];

        $newCourse = create_course($data);
        println("   creado id={$newCourse->id}");

        // ── Copiar formato y opciones del curso plantilla ─────────────────────
        $template = $DB->get_record('course', ['shortname' => $templateSn]);

        if ($template) {
            copyFormatOptions($template, $newCourse);
            println("   formato copiado de '{$templateSn}'");

            copyOverviewImage($template, $newCourse);
            println("   imagen copiada de '{$templateSn}'");
        } else {
            println("   AVISO: curso plantilla '{$templateSn}' no encontrado — se omite copia");
        }

        $courseResult['action'] = 'created';
        $okCount++;

    } catch (Throwable $e) {
        $msg = $e->getMessage();
        println("   ERROR: {$msg}");
        fwrite(STDERR, $e->getTraceAsString() . "\n");
        $courseResult['action'] = 'error';
        $courseResult['error']  = $msg;
        $report['errors'][]     = "[{$shortname}] {$msg}";
        $errCount++;
    }

    $report['courses'][] = $courseResult;
}

// ─────────────────────────────────────────────────────────────────────────────
// Resumen
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
// Funciones auxiliares
// ─────────────────────────────────────────────────────────────────────────────

function println(string $msg): void
{
    echo $msg . "\n";
}

/**
 * Carga y valida las filas del CSV (cabecera: shortname,fullname,category,templatecourse).
 */
function loadCsvRows(string $path): array
{
    $rows = [];
    $fh   = fopen($path, 'r');
    $header = fgetcsv($fh);

    // Normalizar cabecera (trim + lowercase)
    $header = array_map(fn($h) => strtolower(trim($h)), $header);

    while ($raw = fgetcsv($fh)) {
        if (count($raw) < count($header)) continue;
        $row = array_combine($header, $raw);
        // Limpiar valores
        $row = array_map('trim', $row);
        if (empty($row['shortname'])) continue;
        $rows[] = $row;
    }

    fclose($fh);
    return $rows;
}

/**
 * Crea la jerarquía de categorías de curso según el path (separado por /)
 * y retorna el ID de la categoría hoja.
 *
 * Duplicado localmente desde MoodleContentBuilder para evitar acoplamiento
 * de dependencias en esta fase.
 */
function ensureCategoryPath(string $path): int
{
    global $DB, $CFG;
    require_once($CFG->dirroot . '/course/lib.php');

    $parts    = array_map('trim', explode('/', $path));
    $parentId = 0;

    // Cargar categories.json para idnumbers (opcional, no bloquea si no existe)
    $catConfig = loadCategoriesConfig();
    $node      = $catConfig;

    foreach ($parts as $part) {
        $existing = $DB->get_record('course_categories', ['name' => $part, 'parent' => $parentId]);

        if ($existing) {
            $parentId = (int)$existing->id;
        } else {
            $idnumber = $node[$part]['idnumber'] ?? '';

            $catData           = new stdClass();
            $catData->name     = $part;
            $catData->idnumber = $idnumber;
            $catData->parent   = $parentId;
            $catData->visible  = 1;
            $catData->sortorder = 0;

            $newCat   = core_course_category::create($catData);
            $parentId = (int)$newCat->id;
        }

        $node = $node[$part]['children'] ?? [];
    }

    return $parentId;
}

/**
 * Carga categories.json si existe (para idnumbers). Retorna array vacío si no.
 */
function loadCategoriesConfig(): array
{
    $path = CONFIG_DIR . '/categories.json';

    if (!file_exists($path)) {
        return [];
    }

    $decoded = json_decode(file_get_contents($path), true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        return [];
    }

    unset($decoded['comment']);
    return $decoded;
}

/**
 * Copia el formato y opciones visuales del curso plantilla al curso nuevo.
 */
function copyFormatOptions(object $template, object $newCourse): void
{
    global $CFG;
    require_once($CFG->dirroot . '/course/lib.php');

    $templateFormat = course_get_format($template->id);
    $formatOptions  = $templateFormat->get_format_options();

    $updateData = (object)[
        'id'     => $newCourse->id,
        'format' => $template->format,
    ];

    // Copiar opciones del formato (hiddensections, coursedisplay, etc.)
    foreach ($formatOptions as $key => $value) {
        $updateData->$key = $value;
    }

    update_course($updateData);
}

/**
 * Copia la imagen de portada (overviewfiles) del curso plantilla al nuevo curso.
 */
function copyOverviewImage(object $template, object $newCourse): void
{
    global $CFG;
    require_once($CFG->libdir . '/filelib.php');

    $fs          = get_file_storage();
    $templateCtx = context_course::instance($template->id);
    $newCtx      = context_course::instance($newCourse->id);

    $files = $fs->get_area_files(
        $templateCtx->id,
        'course',
        'overviewfiles',
        false,
        'filename',
        false
    );

    foreach ($files as $file) {
        if ($file->get_filename() === '.') continue;

        $newFileRecord = [
            'contextid' => $newCtx->id,
            'component' => 'course',
            'filearea'  => 'overviewfiles',
            'itemid'    => 0,
            'filepath'  => $file->get_filepath(),
            'filename'  => $file->get_filename(),
        ];

        // Eliminar si ya existe (no debería, pero por seguridad)
        $fs->delete_area_files($newCtx->id, 'course', 'overviewfiles');
        $fs->create_file_from_storedfile($newFileRecord, $file);
        break; // Solo se copia la primera imagen
    }
}
