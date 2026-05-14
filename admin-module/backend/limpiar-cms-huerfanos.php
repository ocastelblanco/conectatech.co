<?php
/**
 * Limpieza diaria de course_modules huérfanos en Moodle.
 *
 * Repara dos tipos de corrupción que impiden borrar cursos:
 *   Tipo 1 — cmids en course_sections.sequence que no existen en course_modules.
 *   Tipo 2 — registros en course_modules que no aparecen en ninguna sequence
 *             (modinfo los ignora, causando "ID de módulo no válido" al borrar).
 *
 * Uso: sudo -u apache php limpiar-cms-huerfanos.php [--dry-run] [--course <id>]
 * Log: /var/log/moodle-cms-cleanup.log
 */

define('CLI_SCRIPT', true);
require('/var/www/html/moodle/public/config.php');
require_once($CFG->dirroot . '/course/lib.php');

// --- Opciones CLI ---
$options = getopt('', ['dry-run', 'course:']);
$dry_run = isset($options['dry-run']);
$only_course = isset($options['course']) ? (int)$options['course'] : null;

// --- Logger ---
$log_file = '/var/log/moodle-cms-cleanup.log';
function log_msg(string $msg): void {
    global $log_file;
    $line = '[' . date('Y-m-d H:i:s') . '] ' . $msg . "\n";
    file_put_contents($log_file, $line, FILE_APPEND);
    echo $line;
}

log_msg('=== Inicio limpieza cms huérfanos' . ($dry_run ? ' [DRY-RUN]' : '') . ' ===');

// --- Obtener cursos a revisar ---
if ($only_course) {
    $courses = $DB->get_records('course', ['id' => $only_course], '', 'id,fullname,shortname');
} else {
    // Todos excepto el curso raíz (id=1)
    $courses = $DB->get_records_select('course', 'id > 1', [], '', 'id,fullname,shortname');
}

$total_tipo1 = 0;
$total_tipo2 = 0;
$courses_afectados = 0;

foreach ($courses as $course) {
    $cambios_tipo1 = 0;
    $cambios_tipo2 = 0;

    // ----------------------------------------------------------------
    // TIPO 1: cmids en sequence que no existen en course_modules
    // ----------------------------------------------------------------
    $sections = $DB->get_records('course_sections', ['course' => $course->id], 'section ASC');
    foreach ($sections as $s) {
        if (empty($s->sequence)) {
            continue;
        }
        $cmids = array_filter(array_map('trim', explode(',', $s->sequence)));
        $validos = [];
        $huerfanos = [];
        foreach ($cmids as $cmid) {
            if ($DB->record_exists('course_modules', ['id' => (int)$cmid])) {
                $validos[] = $cmid;
            } else {
                $huerfanos[] = $cmid;
            }
        }
        if (!empty($huerfanos)) {
            $cambios_tipo1 += count($huerfanos);
            if (!$dry_run) {
                $DB->set_field('course_sections', 'sequence', implode(',', $validos), ['id' => $s->id]);
            }
        }
    }

    // ----------------------------------------------------------------
    // TIPO 2: course_modules que no están en ninguna sequence
    // ----------------------------------------------------------------
    $all_cms = array_map('intval',
        $DB->get_fieldset_select('course_modules', 'id', 'course = ?', [$course->id])
    );
    if (empty($all_cms)) {
        continue;
    }

    // Recargar secciones (pueden haber cambiado en Tipo 1)
    $sections = $DB->get_records('course_sections', ['course' => $course->id]);
    $in_sequence = [];
    foreach ($sections as $s) {
        if (!empty($s->sequence)) {
            foreach (explode(',', $s->sequence) as $id) {
                $in_sequence[] = (int)trim($id);
            }
        }
    }

    $missing = array_values(array_diff($all_cms, $in_sequence));
    if (!empty($missing)) {
        $cambios_tipo2 = count($missing);
        if (!$dry_run) {
            $s0 = $DB->get_record('course_sections', ['course' => $course->id, 'section' => 0]);
            if ($s0) {
                $seq_actual = $s0->sequence ? array_map('intval', explode(',', $s0->sequence)) : [];
                $nueva_seq = array_unique(array_merge($seq_actual, $missing));
                $DB->set_field('course_sections', 'sequence', implode(',', $nueva_seq), ['id' => $s0->id]);
            }
        }
    }

    if ($cambios_tipo1 > 0 || $cambios_tipo2 > 0) {
        $courses_afectados++;
        log_msg(sprintf(
            'Curso %d (%s): tipo1=%d cmids huérfanos en sequence, tipo2=%d cms sin sequence%s',
            $course->id,
            $course->shortname,
            $cambios_tipo1,
            $cambios_tipo2,
            $dry_run ? ' [no aplicado]' : ' [corregido]'
        ));

        // Reconstruir caché de modinfo para el curso afectado
        if (!$dry_run) {
            \course_modinfo::clear_instance_cache($course->id);
            rebuild_course_cache($course->id, true);
        }
    }

    $total_tipo1 += $cambios_tipo1;
    $total_tipo2 += $cambios_tipo2;
}

if (!$dry_run && ($total_tipo1 > 0 || $total_tipo2 > 0)) {
    purge_all_caches();
}

log_msg(sprintf(
    '=== Fin: %d cursos afectados, %d cmids tipo1 eliminados de sequences, %d cms tipo2 añadidos a section-0 ===',
    $courses_afectados,
    $total_tipo1,
    $total_tipo2
));
