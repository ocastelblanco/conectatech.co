<?php
/**
 * Repara las "Opciones de revisión" de cuestionarios existentes.
 *
 * Los quizzes creados por procesar-markdown.php (antes de este fix) quedaron con
 * reviewattempt=DURING únicamente y el resto de campos review* en 0, porque
 * add_moduleinfo() no aplica los defaults de config_plugins (ver MoodleContentBuilder.php,
 * método createQuizModule()). Esto impide que el estudiante vea el intento, el puntaje y
 * la retroalimentación específica al revisar el cuestionario después de responderlo
 * ("Inmediatamente después de cada intento" y "Más tarde, mientras está aún abierto").
 *
 * Esta reparación es puramente ADITIVA: para cada quiz, cada campo review* se actualiza
 * a (valor_actual | defaults_del_sitio). Nunca se apaga un bit que ya estuviera activo,
 * así que un quiz con opciones de revisión personalizadas manualmente no pierde nada.
 *
 * Uso:
 *   sudo -u apache php reparar-review-options-quiz.php --dry-run --category 14
 *   sudo -u apache php reparar-review-options-quiz.php --category 14
 *   sudo -u apache php reparar-review-options-quiz.php --course 39,40,41
 *   sudo -u apache php reparar-review-options-quiz.php --all
 *
 * --category incluye subcategorías. --all requiere el flag explícito (sin filtro).
 * Log: /var/log/moodle-quiz-review-options.log
 */

define('CLI_SCRIPT', true);
require('/var/www/html/moodle/public/config.php');

// --- Opciones CLI ---
$options = getopt('', ['dry-run', 'category:', 'course:', 'all']);
$dry_run = isset($options['dry-run']);
$category_id = isset($options['category']) ? (int)$options['category'] : null;
$course_ids = isset($options['course'])
    ? array_map('intval', explode(',', $options['course']))
    : null;
$all = isset($options['all']);

if (!$category_id && !$course_ids && !$all) {
    fwrite(STDERR, "Debe indicar --category <id>, --course <id[,id,...]> o --all\n");
    exit(1);
}

// --- Logger ---
$log_file = '/var/log/moodle-quiz-review-options.log';
function log_msg(string $msg): void {
    global $log_file;
    $line = '[' . date('Y-m-d H:i:s') . '] ' . $msg . "\n";
    file_put_contents($log_file, $line, FILE_APPEND);
    echo $line;
}

// --- Campos y fases de revisión (mismos que MoodleContentBuilder::createQuizModule) ---
$review_fields = [
    'reviewattempt', 'reviewcorrectness', 'reviewmaxmarks', 'reviewmarks',
    'reviewspecificfeedback', 'reviewgeneralfeedback', 'reviewrightanswer', 'reviewoverallfeedback',
];

$site_defaults = [];
foreach ($review_fields as $field) {
    $site_defaults[$field] = (int)get_config('quiz', $field);
}

// --- Resolver cursos objetivo ---
if ($course_ids) {
    $courses = $DB->get_records_list('course', 'id', $course_ids, '', 'id,fullname,shortname');
} elseif ($category_id) {
    $cat = $DB->get_record('course_categories', ['id' => $category_id], '*', MUST_EXIST);
    $cat_ids = $DB->get_fieldset_select(
        'course_categories',
        'id',
        'id = :id OR path = :path OR path LIKE :pathlike',
        ['id' => $cat->id, 'path' => $cat->path, 'pathlike' => $cat->path . '/%']
    );
    [$in_sql, $in_params] = $DB->get_in_or_equal($cat_ids);
    $courses = $DB->get_records_select('course', "category $in_sql", $in_params, '', 'id,fullname,shortname');
} else {
    $courses = $DB->get_records_select('course', 'id > 1', [], '', 'id,fullname,shortname');
}

log_msg(sprintf(
    '=== Inicio reparación review options%s — %d curso(s) objetivo ===',
    $dry_run ? ' [DRY-RUN]' : '',
    count($courses)
));

$quizzes_afectados = 0;
$quizzes_revisados = 0;

foreach ($courses as $course) {
    $quizzes = $DB->get_records('quiz', ['course' => $course->id]);
    foreach ($quizzes as $quiz) {
        $quizzes_revisados++;
        $cambios = [];
        foreach ($review_fields as $field) {
            $actual = (int)$quiz->$field;
            $nuevo = $actual | $site_defaults[$field];
            if ($nuevo !== $actual) {
                $cambios[$field] = [$actual, $nuevo];
            }
        }

        if (!empty($cambios)) {
            $quizzes_afectados++;
            $detalle = implode(', ', array_map(
                fn($f) => "$f: {$cambios[$f][0]} -> {$cambios[$f][1]}",
                array_keys($cambios)
            ));
            log_msg(sprintf(
                'Curso %d (%s) / Quiz %d (%s): %s%s',
                $course->id,
                $course->shortname,
                $quiz->id,
                $quiz->name,
                $detalle,
                $dry_run ? ' [no aplicado]' : ' [corregido]'
            ));

            if (!$dry_run) {
                foreach ($cambios as $field => [$old, $new]) {
                    $DB->set_field('quiz', $field, $new, ['id' => $quiz->id]);
                }
            }
        }
    }
}

log_msg(sprintf(
    '=== Fin: %d/%d cuestionarios revisados con cambios%s ===',
    $quizzes_afectados,
    $quizzes_revisados,
    $dry_run ? ' [DRY-RUN, nada aplicado]' : ''
));
