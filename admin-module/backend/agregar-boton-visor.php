#!/usr/bin/env php
<?php
/**
 * agregar-boton-visor.php — Inserta el botón «▶ Exponer contenido» en los temas
 * que ya existen (retrocompatibilidad del visor fullscreen, ADR-012).
 *
 * El pipeline Markdown crea el botón en los temas nuevos y la ingesta de árboles
 * (ArbolCurricularService::ejecutar) lo asegura en cada curso que procesa. Este script
 * sirve para cursos que no pasan por esos flujos (p. ej. repositorios ya publicados).
 * La lógica vive en lib/BotonVisorService.php.
 *
 * Uso (como usuario 'apache'):
 *   sudo -u apache php /var/www/html/admin/backend/agregar-boton-visor.php --course <shortname> [--dry-run]
 *   sudo -u apache php /var/www/html/admin/backend/agregar-boton-visor.php --all [--dry-run]
 *
 * Sin --course ni --all no hace nada. Siempre probar primero con --dry-run.
 *
 * Idempotente: omite los temas que ya tienen el botón bien colocado y los que no tienen
 * contenido expositivo. Si un botón existe pero no es el primer recurso, lo reposiciona.
 * No toca contenido, notas ni matrículas.
 */

define('BACKEND_DIR', __DIR__);
define('LIB_DIR',     BACKEND_DIR . '/lib');

require_once(LIB_DIR . '/MoodleBootstrap.php');
require_once(LIB_DIR . '/MoodleContentBuilder.php');   // constantes del botón
require_once(LIB_DIR . '/VisorService.php');
require_once(LIB_DIR . '/BotonVisorService.php');

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

$servicio = new BotonVisorService();
$totales  = [
    BotonVisorService::ACCION_AGREGADO => 0, BotonVisorService::ACCION_MOVIDO => 0,
    BotonVisorService::ACCION_YA => 0, BotonVisorService::ACCION_SIN_CONTENIDO => 0,
    BotonVisorService::ACCION_ERROR => 0,
];
$etiquetas = [
    BotonVisorService::ACCION_AGREGADO      => $dryRun ? 'AÑADIR' : 'OK    ',
    BotonVisorService::ACCION_MOVIDO        => $dryRun ? 'MOVER ' : 'MOVIDO',
    BotonVisorService::ACCION_YA            => 'YA    ',
    BotonVisorService::ACCION_SIN_CONTENIDO => 'SIN   ',
    BotonVisorService::ACCION_ERROR         => 'ERROR ',
];

foreach ($courses as $course) {
    $r = $servicio->asegurarEnCurso($course, (int)$admin->id, $dryRun);
    foreach ($r['temas'] as $t) {
        println(sprintf('  %s [%s] sección %d — «%s»%s', $etiquetas[$t['accion']], $course->shortname,
            $t['section'], $t['titulo'], $t['mensaje'] ? " ({$t['mensaje']})" : ''));
    }
    foreach ($r['totales'] as $accion => $n) {
        $totales[$accion] += $n;
    }
}

println(str_repeat('─', 60));
println(sprintf(
    "%s: %d  |  %s: %d  |  ya correctos: %d  |  sin contenido: %d  |  errores: %d",
    $dryRun ? 'A añadir' : 'Añadidos', $totales[BotonVisorService::ACCION_AGREGADO],
    $dryRun ? 'a reposicionar' : 'reposicionados', $totales[BotonVisorService::ACCION_MOVIDO],
    $totales[BotonVisorService::ACCION_YA], $totales[BotonVisorService::ACCION_SIN_CONTENIDO],
    $totales[BotonVisorService::ACCION_ERROR]
));
exit($totales[BotonVisorService::ACCION_ERROR] > 0 ? 2 : 0);
