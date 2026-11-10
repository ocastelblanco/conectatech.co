#!/usr/bin/env php
<?php
/**
 * agregar-boton-visor.php — Inserta el botón «▶ Exponer contenido» en los temas
 * que ya existen (retrocompatibilidad del visor fullscreen, ADR-012).
 *
 * El pipeline Markdown ya crea el botón en los temas nuevos. Los cursos finales con
 * estudiantes no se repueblan, así que este script lo añade a los temas existentes
 * sin tocar el resto del contenido ni las calificaciones.
 *
 * Uso (como usuario 'apache'):
 *   sudo -u apache php /var/www/html/admin/backend/agregar-boton-visor.php --course <shortname> [--dry-run]
 *   sudo -u apache php /var/www/html/admin/backend/agregar-boton-visor.php --all [--dry-run]
 *
 * Sin --course ni --all no hace nada. Siempre probar primero con --dry-run.
 *
 * Idempotente: omite los temas que ya tienen el botón y los que no tienen contenido
 * expositivo (misma regla que VisorService: lo que el visor mostraría). El label se
 * crea oculto (visible = 0) como primer recurso del tema.
 */

define('BACKEND_DIR', __DIR__);
define('LIB_DIR',     BACKEND_DIR . '/lib');

require_once(LIB_DIR . '/MoodleBootstrap.php');
require_once(LIB_DIR . '/MoodleContentBuilder.php');   // solo por las constantes del botón
require_once(LIB_DIR . '/VisorService.php');
require_once($CFG->dirroot . '/course/lib.php');
require_once($CFG->dirroot . '/course/modlib.php');

function println(string $msg): void
{
    echo $msg . "\n";
}

// ─── Argumentos ───────────────────────────────────────────────────────────────

$opts   = getopt('', ['course:', 'all', 'dry-run']);
$only   = $opts['course'] ?? null;
$all    = isset($opts['all']);
$dryRun = isset($opts['dry-run']);

if ($only === null && !$all) {
    fwrite(STDERR, "Uso: agregar-boton-visor.php (--course <shortname> | --all) [--dry-run]\n");
    exit(1);
}
if ($only !== null && $all) {
    fwrite(STDERR, "ERROR: --course y --all son excluyentes.\n");
    exit(1);
}

// El visor evalúa la visibilidad para un usuario: en CLI no hay sesión, se usa el admin.
$admin = get_admin();
\core\session\manager::set_user($admin);

$courses = $only !== null
    ? $DB->get_records('course', ['shortname' => $only])
    : $DB->get_records_select('course', 'id <> ?', [SITEID], 'id');

if (empty($courses)) {
    fwrite(STDERR, "ERROR: No se encontró ningún curso" . ($only ? " con shortname '{$only}'" : '') . ".\n");
    exit(1);
}

println("Botón del visor — " . ($dryRun ? 'DRY-RUN (no modifica nada)' : 'EJECUCIÓN REAL'));
println(str_repeat('─', 60));

$visor  = new VisorService();
$totals = ['agregados' => 0, 'ya_tenian' => 0, 'sin_contenido' => 0, 'errores' => 0];

foreach ($courses as $course) {
    $sections = $DB->get_records_select(
        'course_sections',
        "course = ? AND section > 0 AND (component IS NULL OR component = '')",
        [$course->id],
        'section'
    );

    foreach ($sections as $section) {
        $etiqueta = "[{$course->shortname}] sección {$section->section}";

        try {
            if (tieneBoton((int)$course->id, (int)$section->id)) {
                $totals['ya_tenian']++;
                println("  YA     {$etiqueta}");
                continue;
            }

            $tema = $visor->obtenerTema((int)$course->id, (int)$section->id, (int)$admin->id);
            if ($tema === null || empty($tema['subtemas'])) {
                $totals['sin_contenido']++;
                println("  SIN    {$etiqueta} (sin contenido expositivo)");
                continue;
            }

            if ($dryRun) {
                $totals['agregados']++;
                println("  AÑADIR {$etiqueta} — «{$tema['titulo']}» (" . count($tema['subtemas']) . " subtemas)");
                continue;
            }

            insertarBoton($course, $section);
            $totals['agregados']++;
            println("  OK     {$etiqueta} — «{$tema['titulo']}»");

        } catch (Throwable $e) {
            $totals['errores']++;
            $DB->force_transaction_rollback();
            error_log("agregar-boton-visor: {$etiqueta}: " . $e->getMessage());
            println("  ERROR  {$etiqueta}: " . $e->getMessage());
        }
    }
}

println(str_repeat('─', 60));
println(sprintf(
    "%s: %d  |  ya tenían el botón: %d  |  sin contenido: %d  |  errores: %d",
    $dryRun ? 'A añadir' : 'Añadidos',
    $totals['agregados'], $totals['ya_tenian'], $totals['sin_contenido'], $totals['errores']
));
exit($totals['errores'] > 0 ? 2 : 0);

// ─── Funciones ────────────────────────────────────────────────────────────────

/** ¿La sección ya tiene un label con el botón? */
function tieneBoton(int $courseId, int $sectionId): bool
{
    global $DB;

    return $DB->record_exists_sql(
        "SELECT 1
           FROM {course_modules} cm
           JOIN {modules} m ON m.id = cm.module AND m.name = 'label'
           JOIN {label} l   ON l.id = cm.instance
          WHERE cm.course = ? AND cm.section = ? AND cm.deletioninprogress = 0
            AND " . $DB->sql_like('l.intro', '?'),
        [$courseId, $sectionId, '%ct-exponer-btn%']
    );
}

/** Crea el label oculto con el botón y lo deja como primer recurso de la sección. */
function insertarBoton(stdClass $course, stdClass $section): void
{
    global $DB;

    // Primer recurso actual de la sección (antes de crear el botón).
    $primerCmId = null;
    foreach (array_filter(explode(',', (string)$section->sequence)) as $id) {
        $primerCmId = (int)$id;
        break;
    }

    $mod = $DB->get_record('modules', ['name' => 'label'], '*', MUST_EXIST);

    $info              = new stdClass();
    $info->modulename  = 'label';
    $info->module      = $mod->id;
    $info->course      = $course->id;
    $info->section     = (int)$section->section;
    $info->visible     = 0;
    $info->intro       = MoodleContentBuilder::BOTON_VISOR_HTML;
    $info->introformat = FORMAT_HTML;
    $info->name        = MoodleContentBuilder::BOTON_VISOR_NOMBRE;

    $creado = add_moduleinfo($info, $course);
    $nuevoCmId = (int)$creado->coursemodule;

    if ($primerCmId !== null) {
        $nuevoCm    = $DB->get_record('course_modules', ['id' => $nuevoCmId], '*', MUST_EXIST);
        $primerCm   = $DB->get_record('course_modules', ['id' => $primerCmId], '*', MUST_EXIST);
        $seccionAct = $DB->get_record('course_sections', ['id' => $section->id], '*', MUST_EXIST);
        moveto_module($nuevoCm, $seccionAct, $primerCm);
    }

    // moveto_module() puede reajustar la visibilidad según la sección: el botón debe quedar oculto.
    if ((int)$DB->get_field('course_modules', 'visible', ['id' => $nuevoCmId]) !== 0) {
        set_coursemodule_visible($nuevoCmId, 0);
    }
    rebuild_course_cache((int)$course->id, true);
}
