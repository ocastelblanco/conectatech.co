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
 * Idempotente: omite los temas que ya tienen el botón bien colocado y los que no tienen
 * contenido expositivo (misma regla que VisorService: lo que el visor mostraría). El label
 * se crea oculto (visible = 0) como primer recurso del tema. Si un botón existe pero no es
 * el primer recurso (p. ej. tras una ejecución interrumpida), se reposiciona al inicio.
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
$totals = ['agregados' => 0, 'reposicionados' => 0, 'ya_tenian' => 0, 'sin_contenido' => 0, 'errores' => 0];

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
            $botonId = buscarBoton((int)$section->id);
            if ($botonId !== null) {
                if (primerRecursoId($section) === $botonId) {
                    $totals['ya_tenian']++;
                    println("  YA     {$etiqueta}");
                } elseif ($dryRun) {
                    $totals['reposicionados']++;
                    println("  MOVER  {$etiqueta} — el botón existe pero no es el primer recurso");
                } else {
                    moverAlInicio($course, $section, $botonId);
                    $totals['reposicionados']++;
                    println("  MOVIDO {$etiqueta} — botón colocado como primer recurso");
                }
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
    "%s: %d  |  %s: %d  |  ya correctos: %d  |  sin contenido: %d  |  errores: %d",
    $dryRun ? 'A añadir' : 'Añadidos', $totals['agregados'],
    $dryRun ? 'a reposicionar' : 'reposicionados', $totals['reposicionados'],
    $totals['ya_tenian'], $totals['sin_contenido'], $totals['errores']
));
exit($totals['errores'] > 0 ? 2 : 0);

// ─── Funciones ────────────────────────────────────────────────────────────────

/** cmid del label con el botón en la sección (id de course_sections), o null. */
function buscarBoton(int $sectionId): ?int
{
    global $DB;

    $id = $DB->get_field_sql(
        "SELECT MIN(cm.id)
           FROM {course_modules} cm
           JOIN {modules} m ON m.id = cm.module AND m.name = 'label'
           JOIN {label} l   ON l.id = cm.instance
          WHERE cm.section = ? AND cm.deletioninprogress = 0
            AND " . $DB->sql_like('l.intro', '?'),
        [$sectionId, '%ct-exponer-btn%']
    );
    return $id ? (int)$id : null;
}

/** cmid del primer recurso de la sección según su secuencia, o null si está vacía. */
function primerRecursoId(stdClass $section): ?int
{
    global $DB;

    $sequence = (string)$DB->get_field('course_sections', 'sequence', ['id' => $section->id]);
    foreach (array_filter(explode(',', $sequence)) as $id) {
        return (int)$id;
    }
    return null;
}

/** Crea el label oculto con el botón y lo deja como primer recurso de la sección. */
function insertarBoton(stdClass $course, stdClass $section): void
{
    global $DB;

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
    moverAlInicio($course, $section, (int)$creado->coursemodule);
}

/**
 * Deja el módulo como primer recurso de la sección y garantiza que siga oculto.
 * moveto_module() exige un registro con `modname`, por eso se usa get_coursemodule_from_id()
 * y no una fila cruda de course_modules.
 */
function moverAlInicio(stdClass $course, stdClass $section, int $cmId): void
{
    global $DB;

    $primerId = null;
    $sequence = (string)$DB->get_field('course_sections', 'sequence', ['id' => $section->id]);
    foreach (array_filter(explode(',', $sequence)) as $id) {
        if ((int)$id !== $cmId) {
            $primerId = (int)$id;
            break;
        }
    }

    if ($primerId !== null) {
        $cm         = get_coursemodule_from_id('label', $cmId, $course->id, false, MUST_EXIST);
        $primerCm   = $DB->get_record('course_modules', ['id' => $primerId], '*', MUST_EXIST);
        $seccionAct = $DB->get_record('course_sections', ['id' => $section->id], '*', MUST_EXIST);
        moveto_module($cm, $seccionAct, $primerCm);
    }

    // moveto_module() puede reajustar la visibilidad según la sección: el botón debe quedar oculto.
    if ((int)$DB->get_field('course_modules', 'visible', ['id' => $cmId]) !== 0) {
        set_coursemodule_visible($cmId, 0);
    }
    rebuild_course_cache((int)$course->id, true);
}
