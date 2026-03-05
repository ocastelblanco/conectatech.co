#!/usr/bin/env php
<?php
/**
 * matricular.php — Crea/actualiza usuarios Moodle y los matricula en sus cursos
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

// ─────────────────────────────────────────────────────────────────────────────
// Argumentos CLI
// ─────────────────────────────────────────────────────────────────────────────

$opts      = getopt('', ['file:', 'user:', 'dry-run']);
$csvFile   = $opts['file'] ?? CONFIG_DIR . '/matriculas.csv';
$onlyUser  = $opts['user'] ?? null;
$dryRun    = isset($opts['dry-run']);

if (!file_exists($csvFile)) {
    fwrite(STDERR, "ERROR: No se encontró el CSV: {$csvFile}\n");
    exit(1);
}

// ─────────────────────────────────────────────────────────────────────────────
// Librerías Moodle
// ─────────────────────────────────────────────────────────────────────────────

global $DB, $CFG;
require_once($CFG->dirroot . '/user/lib.php');
require_once($CFG->libdir  . '/enrollib.php');

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
// Reporte
// ─────────────────────────────────────────────────────────────────────────────

$report = [
    'timestamp'       => date('c'),
    'dry_run'         => $dryRun,
    'elapsed_seconds' => 0,
    'users'           => [],
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

println("\nUsuarios en CSV: " . count($rows));

// ─────────────────────────────────────────────────────────────────────────────
// Procesar cada fila
// ─────────────────────────────────────────────────────────────────────────────

$okCount   = 0;
$skipCount = 0;
$errCount  = 0;

foreach ($rows as $row) {
    $username    = strtolower(trim($row['username']));
    $password    = trim($row['password']);
    $firstname   = trim($row['firstname']);
    $lastname    = trim($row['lastname']);
    $email       = strtolower(trim($row['email']));
    $institution = trim($row['institution']);
    $rol         = strtolower(trim($row['rol']));
    $grado       = isset($row['grado']) ? (int)trim($row['grado']) : 0;

    // Filtrar por --user si se especificó
    if ($onlyUser && $username !== strtolower($onlyUser)) {
        continue;
    }

    println("\n→ {$username}  ({$firstname} {$lastname})");

    $userResult = [
        'username'    => $username,
        'institution' => $institution,
        'rol'         => $rol,
        'grado'       => $grado ?: null,
        'action'      => '',
        'user_id'     => null,
        'courses'     => [],
        'error'       => null,
    ];

    try {
        // ── 1. Normalizar rol ─────────────────────────────────────────────────
        $moodleRol = normalizeRol($rol);
        println("   rol Moodle: {$moodleRol}");

        // ── 2. Obtener o crear usuario ────────────────────────────────────────
        $existing = $DB->get_record('user', ['username' => $username, 'deleted' => 0]);

        if ($dryRun) {
            $action = $existing ? 'update' : 'create';
            println("   [dry-run] {$action}ría usuario");

            // Buscar cursos en dry-run para mostrar qué se haría
            $catId   = resolveCategoryId("COLEGIOS/{$institution}");
            $courses = findCourses($catId, $rol === 'student' ? $grado : null);
            println("   [dry-run] matricularía en " . count($courses) . " curso(s):");
            foreach ($courses as $c) {
                println("     · {$c->shortname}");
                $userResult['courses'][] = $c->shortname;
            }

            $userResult['action'] = "dry-run:{$action}";
            $report['users'][] = $userResult;
            continue;
        }

        if ($existing) {
            // Actualizar perfil (sin cambiar password)
            $updateData = (object)[
                'id'          => $existing->id,
                'firstname'   => $firstname,
                'lastname'    => $lastname,
                'email'       => $email,
                'institution' => $institution,
            ];
            user_update_user($updateData, false, false);
            $userId = (int)$existing->id;
            println("   actualizado id={$userId}");
            $userResult['action'] = 'updated';
        } else {
            // Crear usuario nuevo
            $newData = (object)[
                'username'    => $username,
                'password'    => $password,
                'firstname'   => $firstname,
                'lastname'    => $lastname,
                'email'       => $email,
                'institution' => $institution,
                'confirmed'   => 1,
                'auth'        => 'manual',
                'mnethostid'  => $CFG->mnet_localhost_id,
                'lang'        => 'es',
            ];
            $userId = user_create_user($newData, true, false);
            println("   creado id={$userId}");
            $userResult['action'] = 'created';
        }

        $userResult['user_id'] = $userId;

        // ── 3. Encontrar cursos destino ───────────────────────────────────────
        $catId   = resolveCategoryId("COLEGIOS/{$institution}");
        $courses = findCourses($catId, $rol === 'student' ? $grado : null);

        println("   cursos encontrados: " . count($courses));

        if (empty($courses)) {
            println("   AVISO: No se encontraron cursos para institution='{$institution}'" .
                    ($grado ? " grado={$grado}" : ''));
        }

        // ── 4. Matricular en cada curso ───────────────────────────────────────
        $roleId = $DB->get_field('role', 'id', ['shortname' => $moodleRol], MUST_EXIST);
        $enrol  = enrol_get_plugin('manual');

        foreach ($courses as $course) {
            $instance = ensureManualEnrolInstance($course);
            $enrol->enrol_user($instance, $userId, $roleId);
            println("   ✓ matriculado en {$course->shortname}");
            $userResult['courses'][] = $course->shortname;
        }

        $okCount++;

    } catch (Throwable $e) {
        $msg = $e->getMessage();
        println("   ERROR: {$msg}");
        fwrite(STDERR, $e->getTraceAsString() . "\n");
        $userResult['action'] = 'error';
        $userResult['error']  = $msg;
        $report['errors'][]   = "[{$username}] {$msg}";
        $errCount++;
    }

    $report['users'][] = $userResult;
}

// ─────────────────────────────────────────────────────────────────────────────
// Resumen
// ─────────────────────────────────────────────────────────────────────────────

$elapsed = round(microtime(true) - $startTime, 2);
$report['elapsed_seconds'] = $elapsed;

$reportPath = BACKEND_DIR . '/report-ultimo-matriculas.json';
file_put_contents($reportPath, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

println("\n" . str_repeat("─", 60));
println("✓ COMPLETADO en {$elapsed}s");
println("  procesados={$okCount}  omitidos={$skipCount}  errores={$errCount}");
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
 * Carga y valida las filas del CSV.
 * Cabecera requerida: username,password,firstname,lastname,email,institution,rol,grado
 */
function loadCsvRows(string $path): array
{
    $rows   = [];
    $fh     = fopen($path, 'r');
    $header = fgetcsv($fh);

    // Normalizar cabecera (trim + lowercase)
    $header = array_map(fn($h) => strtolower(trim($h)), $header);

    while ($raw = fgetcsv($fh)) {
        if (count($raw) < count($header)) continue;
        $row = array_combine($header, $raw);
        $row = array_map('trim', $row);
        if (empty($row['username'])) continue;
        $rows[] = $row;
    }

    fclose($fh);
    return $rows;
}

/**
 * Traduce el rol del CSV al shortname de rol en Moodle.
 */
function normalizeRol(string $rol): string
{
    return match ($rol) {
        'teacher' => 'editingteacher',
        'student' => 'student',
        default   => throw new InvalidArgumentException("Rol desconocido: '{$rol}'. Use 'student' o 'teacher'."),
    };
}

/**
 * Navega course_categories por name+parent y retorna el ID de la categoría hoja.
 * NO crea categorías; lanza excepción si algún segmento no existe.
 *
 * @param string $path  Ruta separada por "/" (e.g., "COLEGIOS/San Marino")
 * @return int          ID de la categoría encontrada
 */
function resolveCategoryId(string $path): int
{
    global $DB;

    $parts    = array_map('trim', explode('/', $path));
    $parentId = 0;

    foreach ($parts as $part) {
        $cat = $DB->get_record('course_categories', ['name' => $part, 'parent' => $parentId]);

        if (!$cat) {
            throw new RuntimeException(
                "Categoría '{$part}' no encontrada bajo parent_id={$parentId} (path: {$path})"
            );
        }

        $parentId = (int)$cat->id;
    }

    return $parentId;
}

/**
 * Retorna todos los IDs de categorías descendientes (BFS), incluyendo el nodo raíz.
 *
 * @param int $parentId  ID de la categoría raíz
 * @return int[]
 */
function getDescendantCategoryIds(int $parentId): array
{
    global $DB;

    $ids   = [$parentId];
    $queue = [$parentId];

    while (!empty($queue)) {
        $current  = array_shift($queue);
        $children = $DB->get_records('course_categories', ['parent' => $current], '', 'id');

        foreach ($children as $child) {
            $childId = (int)$child->id;
            $ids[]   = $childId;
            $queue[] = $childId;
        }
    }

    return $ids;
}

/**
 * Retorna los cursos dentro de la jerarquía de categorías bajo $catId.
 * Para student filtra por shortname que termine en "-{$grado}".
 * Para teacher devuelve todos.
 *
 * @param int      $catId  ID de la categoría raíz (e.g., COLEGIOS/San Marino)
 * @param int|null $grado  Número de grado para filtrar; null para teacher
 * @return object[]        Registros de la tabla 'course'
 */
function findCourses(int $catId, ?int $grado): array
{
    global $DB;

    $catIds  = getDescendantCategoryIds($catId);
    $courses = $DB->get_records_list('course', 'category', $catIds, 'shortname');

    // Excluir el sitio (id=1)
    $courses = array_filter($courses, fn($c) => (int)$c->id !== 1);

    if ($grado !== null && $grado > 0) {
        $suffix  = "-{$grado}";
        $courses = array_filter($courses, fn($c) => str_ends_with($c->shortname, $suffix));
    }

    return array_values($courses);
}

/**
 * Obtiene la instancia de enrol_manual para el curso.
 * Si no existe, la crea con los valores por defecto.
 *
 * @param object $course  Registro de la tabla 'course'
 * @return object         Registro de la tabla 'enrol'
 */
function ensureManualEnrolInstance(object $course): object
{
    global $DB;

    $instance = $DB->get_record('enrol', ['courseid' => $course->id, 'enrol' => 'manual']);

    if ($instance) {
        return $instance;
    }

    $enrol      = enrol_get_plugin('manual');
    $instanceId = $enrol->add_default_instance($course);

    return $DB->get_record('enrol', ['id' => $instanceId], '*', MUST_EXIST);
}
