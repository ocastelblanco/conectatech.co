<?php
/**
 * MoodleContentBuilder.php
 * Crea y actualiza cursos, secciones, subsecciones y recursos en Moodle.
 * Requiere el entorno Moodle inicializado (MoodleBootstrap.php).
 *
 * Flujo:
 *   1. ensureCourse()      → crea el curso si no existe
 *   2. processSection()    → por cada H1: detecta si existe (actualizar) o crea
 *   3. processSubsection() → según el tipo, crea label / subsección / quiz
 */

class MoodleContentBuilder
{
    private string               $shortname;
    private array                $courseData;
    private HtmlConverter        $htmlConverter;
    private PresaberesHtmlBuilder $presBuilder;
    private GiftConverter        $giftConverter;
    private ?object              $course = null;
    private array                $categoriesConfig = [];

    public function __construct(
        string                $shortname,
        array                 $courseData,
        HtmlConverter         $htmlConverter,
        PresaberesHtmlBuilder $presBuilder,
        GiftConverter         $giftConverter
    ) {
        $this->shortname     = $shortname;
        $this->courseData    = $courseData;
        $this->htmlConverter = $htmlConverter;
        $this->presBuilder   = $presBuilder;
        $this->giftConverter = $giftConverter;
    }

    // =========================================================================
    // Curso
    // =========================================================================

    /**
     * Obtiene el curso por shortname o lo crea si no existe.
     */
    public function ensureCourse(): void
    {
        global $DB, $CFG;
        require_once($CFG->dirroot . '/course/lib.php');

        $existing = $DB->get_record('course', ['shortname' => $this->shortname]);

        if ($existing) {
            $this->course = $existing;
            return;
        }

        $categoryId = $this->ensureCategoryPath($this->courseData['category_path']);

        $data = (object)[
            'fullname'    => $this->courseData['fullname'],
            'shortname'   => $this->shortname,
            'category'    => $categoryId,   // Moodle usa 'category', no 'categoryid'
            'visible'     => 0,
            'format'      => 'topics',
            'numsections' => 0,
        ];

        $this->course = create_course($data);
    }

    // =========================================================================
    // Sección (H1)
    // =========================================================================

    /**
     * Procesa una sección H1: detecta si ya existe en Moodle y actúa en consecuencia.
     *
     * @param array $section      Árbol de la sección del parser
     * @param int   $sectionIndex Índice 1-based (para IDs únicos)
     * @return array              Resultado para el reporte
     */
    public function processSection(array $section, int $sectionIndex): array
    {
        global $DB, $CFG;
        require_once($CFG->dirroot . '/course/lib.php');

        $result = [
            'title'       => $section['title'],
            'index'       => $sectionIndex,
            'action'      => '',
            'subsections' => [],
            'errors'      => [],
        ];

        try {
            $existingSection = $this->findExistingSection($section['title']);

            if ($existingSection) {
                // Modo actualización: vaciar contenido y recrear
                $this->clearSectionContent((int)$existingSection->section);
                $sectionNum = (int)$existingSection->section;
                $this->updateSectionMeta($sectionNum, $section['title']);
                $result['action'] = 'updated';
            } else {
                // Modo creación: agregar al final del curso
                $newSection = course_create_section($this->course->id);
                $sectionNum = (int)$newSection->section;
                $this->updateSectionMeta($sectionNum, $section['title']);
                $result['action'] = 'created';
            }

            // Procesar subsecciones (cada llamada Moodle maneja su propia transacción)
            foreach ($section['subsections'] as $subIndex => $subsection) {
                $subResult               = $this->processSubsection($subsection, $sectionNum, $sectionIndex, $subIndex + 1);
                $result['subsections'][] = $subResult;
            }

        } catch (Throwable $e) {
            $result['errors'][] = $e->getMessage();
            fwrite(STDERR, "ERROR en sección '{$section['title']}': " . $e->getMessage() . "\n" . $e->getTraceAsString() . "\n");
        }

        return $result;
    }

    // =========================================================================
    // Subsección (H2)
    // =========================================================================

    private function processSubsection(
        array $subsection,
        int   $parentSectionNum,
        int   $sectionIdx,
        int   $subIdx
    ): array {
        $result = [
            'title'  => $subsection['title'],
            'type'   => $subsection['type'],
            'action' => '',
            'error'  => null,
        ];

        try {
            switch ($subsection['type']) {

                case 'referente-biblico-seccion':
                    // Label directamente en la sección padre (sin subsección contenedora)
                    foreach ($subsection['blocks'] as $block) {
                        $html = $this->htmlConverter->convertBlock($block);
                        if (!empty(trim($html))) {
                            $labelName = HtmlConverter::parseBlockTitle($block['h3_title'])['label'];
                            $this->createLabelModule($parentSectionNum, $html, $labelName);
                        }
                    }
                    $result['action'] = 'label_en_seccion_padre';
                    break;

                case 'h2-texto-directo':
                    // Regla 1: H2 sin H3 → label directamente en la sección padre
                    foreach ($subsection['blocks'] as $block) {
                        $html = $this->htmlConverter->convertBlock($block);
                        if (!empty(trim($html))) {
                            $labelName = HtmlConverter::parseBlockTitle($block['h3_title'])['label'];
                            $this->createLabelModule($parentSectionNum, $html, $labelName);
                        }
                    }
                    $result['action'] = 'label_en_seccion_padre';
                    break;

                case 'subseccion-regular':
                    $delegatedNum = $this->createSubsectionModule($parentSectionNum, $subsection['title']);
                    // Cuestionarios H3-level [evaluacion] dentro de la subsección
                    foreach ($subsection['h3_evaluaciones'] ?? [] as $h3ev) {
                        $this->createQuizModule($delegatedNum, $h3ev['title'], $h3ev['questions'], $sectionIdx);
                    }
                    // Un recurso "Área de medios y texto" independiente por cada bloque H3
                    // Regla 2: si h3_title es "(Título)", usar el texto interior como nombre del label
                    foreach ($subsection['blocks'] as $block) {
                        $html = $this->htmlConverter->convertBlock($block);
                        if (!empty(trim($html))) {
                            $labelName = HtmlConverter::parseBlockTitle($block['h3_title'])['label'];
                            $this->createLabelModule($delegatedNum, $html, $labelName);
                        }
                    }
                    $result['action'] = 'subseccion_con_label';
                    break;

                case 'subseccion-presaberes':
                    $delegatedNum = $this->createSubsectionModule($parentSectionNum, $subsection['title']);
                    $html         = $this->presBuilder->buildHtml(
                        $subsection['pregunta_blocks'],
                        $sectionIdx,
                        $subIdx
                    );
                    if (!empty(trim($html))) {
                        $this->createLabelModule($delegatedNum, $html, $subsection['title']);
                    }
                    $result['action'] = 'subseccion_presaberes';
                    break;

                case 'subseccion-evaluacion':
                    $delegatedNum = $this->createSubsectionModule($parentSectionNum, $subsection['title']);
                    $this->createQuizModule($delegatedNum, $subsection['title'], $subsection['questions'], $sectionIdx);
                    $result['action'] = 'subseccion_con_quiz';
                    break;
            }
        } catch (Throwable $e) {
            $result['error'] = $e->getMessage();
            fwrite(STDERR, "ERROR subsección '{$subsection['title']}' [{$subsection['type']}]: "
                . $e->getMessage() . "\n" . $e->getTraceAsString() . "\n");
        }

        return $result;
    }

    // =========================================================================
    // Módulos Moodle
    // =========================================================================

    /**
     * Crea un mod_label (Área de texto y medios) en la sección indicada.
     */
    private function createLabelModule(int $sectionNum, string $html, ?string $title = null): void
    {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/course/modlib.php');

        $mod = $DB->get_record('modules', ['name' => 'label'], '*', MUST_EXIST);

        $info                 = new stdClass();
        $info->modulename     = 'label';
        $info->module         = $mod->id;
        $info->course         = $this->course->id;
        $info->section        = $sectionNum;
        $info->visible        = 1;
        $info->intro          = $html;
        $info->introformat    = FORMAT_HTML;
        $info->name           = $title ?: 'label';

        add_moduleinfo($info, $this->course);
    }

    /**
     * Crea un mod_subsection y retorna el número de la sección delegada.
     */
    private function createSubsectionModule(int $parentSectionNum, string $title): int
    {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/course/modlib.php');

        $mod = $DB->get_record('modules', ['name' => 'subsection'], '*', MUST_EXIST);

        $info                 = new stdClass();
        $info->modulename     = 'subsection';
        $info->module         = $mod->id;
        $info->course         = $this->course->id;
        $info->section        = $parentSectionNum;
        $info->name           = $title;
        $info->visible        = 1;
        $info->intro          = '';
        $info->introformat    = FORMAT_HTML;

        $result     = add_moduleinfo($info, $this->course);
        $instanceId = (int)$result->instance;

        // La sección delegada se registra en course_sections con component = mod_subsection
        // e itemid = ID de instancia en la tabla 'subsection' (NO el cmid)
        $delegated = $DB->get_record('course_sections', [
            'course'    => $this->course->id,
            'component' => 'mod_subsection',
            'itemid'    => $instanceId,
        ], '*', MUST_EXIST);

        return (int)$delegated->section;
    }

    /**
     * Crea un mod_quiz con las preguntas de una subsección [evaluacion].
     */
    private function createQuizModule(int $sectionNum, string $title, array $questions, int $sectionIdx): void
    {
        global $CFG;
        require_once($CFG->dirroot . '/course/modlib.php');
        require_once($CFG->dirroot . '/mod/quiz/lib.php');
        require_once($CFG->dirroot . '/mod/quiz/locallib.php');
        require_once($CFG->dirroot . '/question/engine/lib.php');
        require_once($CFG->dirroot . '/question/format.php');
        require_once($CFG->dirroot . '/question/format/gift/format.php');

        // Crear el módulo quiz
        global $DB;
        $quizMod = $DB->get_record('modules', ['name' => 'quiz'], '*', MUST_EXIST);

        $info                         = new stdClass();
        $info->modulename             = 'quiz';
        $info->module                 = $quizMod->id;
        $info->course                 = $this->course->id;
        $info->section                = $sectionNum;
        $info->name                   = $title;
        $info->visible                = 1;
        $info->intro                  = '';
        $info->introformat            = FORMAT_HTML;
        $info->grade                  = 10;
        $info->gradepass              = 0;
        $info->preferredbehaviour     = 'deferredfeedback';
        $info->attempts_number        = 0;   // ilimitado
        $info->timeopen               = 0;
        $info->timeclose              = 0;
        $info->timelimit              = 0;
        $info->shuffleanswers         = 1;
        $info->decimalpoints          = 2;
        $info->questiondecimalpoints  = -1;
        $info->overduehandling        = 'autosubmit';
        $info->quizpassword           = '';
        $info->subnet                 = '';
        $info->browsersecurity        = '-';
        $info->navmethod              = 'free';
        $info->cmidnumber             = '';

        $result   = add_moduleinfo($info, $this->course);
        $quizId   = (int)$result->instance;
        $quizCmId = (int)$result->coursemodule;

        // En Moodle 5.x question_get_top_category requiere CONTEXT_MODULE (no CONTEXT_COURSE)
        $context  = context_module::instance($quizCmId);
        $catName  = 'Sección ' . $sectionIdx . ': ' . $title;
        $category = $this->ensureQuestionCategory($catName, $context);

        $questionIds = [];

        // Preguntas opción múltiple → importar vía GIFT
        $giftQs = array_values($this->giftConverter->extractGiftQuestions($questions));
        if (!empty($giftQs)) {
            $giftText = $this->giftConverter->convertQuestions(
                $giftQs,
                $catName,
                $this->shortname,
                $sectionIdx
            );
            $ids         = $this->importGiftQuestions($giftText, $category, $context);
            $questionIds = array_merge($questionIds, $ids);
        }

        // Preguntas ensayo → crear vía PHP API
        $essayQs = array_values($this->giftConverter->extractEssayQuestions($questions));
        foreach ($essayQs as $eq) {
            $qid = $this->createEssayQuestion($eq, (int)$category->id, $context);
            if ($qid) {
                $questionIds[] = $qid;
            }
        }

        // Agregar preguntas al quiz
        // Usar registro completo de DB: quiz_add_quiz_question requiere course y questionsperpage
        $quizObj = $DB->get_record('quiz', ['id' => $quizId], '*', MUST_EXIST);
        foreach ($questionIds as $qid) {
            quiz_add_quiz_question($qid, $quizObj, 0, 1);
        }

        // quiz_update_sumgrades() deprecada desde Moodle 4.2 → usar nueva API
        require_once($CFG->dirroot . '/mod/quiz/classes/quiz_settings.php');
        $quizSettings = \mod_quiz\quiz_settings::create($quizId);
        $quizSettings->get_grade_calculator()->recompute_quiz_sumgrades();
    }

    // =========================================================================
    // Categorías de preguntas
    // =========================================================================

    private function ensureQuestionCategory(string $name, object $context): object
    {
        global $DB;

        $existing = $DB->get_record('question_categories', [
            'name'      => $name,
            'contextid' => $context->id,
        ]);

        if ($existing) {
            return $existing;
        }

        $topCat = question_get_top_category($context->id, true);

        $cat              = new stdClass();
        $cat->name        = $name;
        $cat->contextid   = $context->id;
        $cat->parent      = $topCat->id;
        $cat->info        = '';
        $cat->infoformat  = FORMAT_HTML;
        $cat->sortorder   = 0;
        $cat->stamp       = make_unique_id_code();
        $cat->id          = $DB->insert_record('question_categories', $cat);

        return $cat;
    }

    // =========================================================================
    // Pregunta de ensayo (PHP API)
    // =========================================================================

    private function createEssayQuestion(array $question, int $categoryId, object $context): ?int
    {
        $enunciado = trim(!empty($question['enunciado']) ? $question['enunciado'] : $question['title']);

        // save_question($question, $form) lee las opciones DIRECTAMENTE de $form,
        // no de $form->options. Se pasa el mismo objeto como ambos argumentos.
        // questiontext y generalfeedback deben ser arrays ['text'=>..., 'format'=>...]
        $qdata                          = new stdClass();
        $qdata->qtype                   = 'essay';
        $qdata->name                    = $question['title'];
        $qdata->questiontext            = ['text' => '<p>' . htmlspecialchars($enunciado, ENT_QUOTES, 'UTF-8') . '</p>', 'format' => FORMAT_HTML];
        $qdata->generalfeedback         = ['text' => '', 'format' => FORMAT_HTML];
        $qdata->defaultmark             = 1;
        $qdata->hidden                  = 0;
        $qdata->category                = $categoryId;
        $qdata->contextid               = $context->id;
        $qdata->context                 = $context;   // save_question_options lo requiere

        // Opciones de ensayo directamente en $qdata (no en $qdata->options)
        $qdata->responseformat          = 'editor';
        $qdata->responserequired        = 1;
        $qdata->responsefieldlines      = 15;
        $qdata->attachments             = 0;
        $qdata->attachmentsrequired     = 0;
        $qdata->maxbytes                = 0;
        // graderinfo y responsetemplate en formato de importación (con clave 'files')
        $qdata->graderinfo              = ['text' => '', 'format' => FORMAT_HTML, 'files' => []];
        $qdata->graderinfoformat        = FORMAT_HTML;
        $qdata->responsetemplate        = ['text' => '', 'format' => FORMAT_HTML, 'files' => []];
        $qdata->responsetemplateformat  = FORMAT_HTML;

        try {
            $qtype = question_bank::get_qtype('essay');
            // $form debe ser un clone: save_question() sobreescribe $question->questiontext
            // con un string, rompiendo el acceso a ['format'] si $question === $form
            $saved = $qtype->save_question($qdata, clone $qdata);
            return (int)$saved->id;
        } catch (Throwable $e) {
            fwrite(STDERR, "ERROR pregunta ensayo '{$question['title']}': " . $e->getMessage() . "\n");
            return null;
        }
    }

    // =========================================================================
    // Importar GIFT
    // =========================================================================

    private function importGiftQuestions(string $giftText, object $category, object $context): array
    {
        $tmpFile = tempnam(sys_get_temp_dir(), 'gift_') . '.txt';
        file_put_contents($tmpFile, $giftText);

        try {
            $importer = new qformat_gift();
            $importer->setContexts([$context]);
            $importer->setCourse($this->course);
            $importer->setCategory($category);
            $importer->setFilename($tmpFile);
            $importer->setStoponerror(false);
            $importer->setMatchgrades('nearest');

            if (!$importer->importprocess()) {
                error_log("WARN: importprocess() GIFT retornó false.");
                return [];
            }

            return $importer->questionids ?? [];

        } catch (Throwable $e) {
            error_log("ERROR importando GIFT: " . $e->getMessage());
            return [];
        } finally {
            @unlink($tmpFile);
        }
    }

    // =========================================================================
    // Reset total del curso
    // =========================================================================

    /**
     * Elimina todas las secciones del curso (salvo la sección 0) y su contenido.
     * Garantiza que el markdown es la única fuente de verdad: lo que no está en
     * el markdown no existe en el curso.
     *
     * Las secciones delegadas (mod_subsection) se omiten porque Moodle las elimina
     * automáticamente al borrar el módulo subsection de la sección padre.
     */
    public function resetCourse(): void
    {
        global $DB, $CFG;
        require_once($CFG->dirroot . '/course/lib.php');

        $sections = $DB->get_records('course_sections', ['course' => $this->course->id], 'section ASC');

        foreach ($sections as $s) {
            if ((int)$s->section === 0) continue;          // Nunca tocar sección 0
            if (!empty($s->component))  continue;          // Omitir secciones delegadas

            $this->clearSectionContent((int)$s->section);
            // NO llamar course_delete_section(): invoca move_section_to() que
            // renumera secciones y genera duplicate key cuando hay gaps.
        }

        // Borrar registros de secciones directamente (sin renumeración de Moodle)
        $DB->delete_records_select(
            'course_sections',
            'course = ? AND section > 0',
            [$this->course->id]
        );

        rebuild_course_cache($this->course->id, true);
    }

    // =========================================================================
    // Buscar / limpiar secciones existentes
    // =========================================================================

    private function findExistingSection(string $title): ?object
    {
        global $DB;

        $normTarget = MarkdownParser::normalizeTitle($title);
        $sections   = $DB->get_records('course_sections', ['course' => $this->course->id]);

        foreach ($sections as $s) {
            if (!empty($s->name) && MarkdownParser::normalizeTitle($s->name) === $normTarget) {
                return $s;
            }
        }

        return null;
    }

    /**
     * Elimina todos los course_modules de una sección y de sus subsecciones delegadas.
     */
    private function clearSectionContent(int $sectionNum): void
    {
        global $CFG;
        require_once($CFG->dirroot . '/course/lib.php');

        $cmids = [];
        $modinfo = get_fast_modinfo($this->course);
        $this->collectCmIds($sectionNum, $modinfo, $cmids);

        foreach (array_reverse($cmids) as $cmid) {
            try {
                course_delete_module($cmid);
            } catch (Throwable $e) {
                error_log("WARN: No se pudo eliminar cmid {$cmid}: " . $e->getMessage());
            }
        }
    }

    private function collectCmIds(int $sectionNum, object $modinfo, array &$cmids): void
    {
        global $DB;

        $section = $modinfo->get_section_info($sectionNum, IGNORE_MISSING);
        if (!$section || empty($section->sequence)) {
            return;
        }

        foreach (array_filter(explode(',', $section->sequence)) as $cmid) {
            $cmid = (int)$cmid;
            if (!$cmid) continue;

            $cmids[] = $cmid;

            // Si es una subsección, recorrer recursivamente la sección delegada
            try {
                $cm = $modinfo->get_cm($cmid);
                if ($cm && $cm->modname === 'subsection') {
                    $delegated = $DB->get_record('course_sections', [
                        'course'    => $this->course->id,
                        'component' => 'mod_subsection',
                        'itemid'    => $cm->instance,  // itemid = instance id, no el cmid
                    ]);
                    if ($delegated) {
                        $freshModinfo = get_fast_modinfo($this->course);
                        $this->collectCmIds((int)$delegated->section, $freshModinfo, $cmids);
                    }
                }
            } catch (Throwable $e) {
                // CM puede haber sido ya eliminado en la recursión
            }
        }
    }

    private function updateSectionMeta(int $sectionNum, string $title): void
    {
        global $DB, $CFG;
        require_once($CFG->dirroot . '/course/lib.php');

        $section          = $DB->get_record('course_sections', [
            'course'  => $this->course->id,
            'section' => $sectionNum,
        ], '*', MUST_EXIST);

        course_update_section($this->course->id, $section, ['name' => $title, 'visible' => 1]);
    }

    // =========================================================================
    // Categorías de cursos (jerarquía de carpetas)
    // =========================================================================

    /**
     * Crea la jerarquía de categorías según el path (separado por /)
     * y asigna el idnumber de categories.json a cada nivel.
     */
    private function ensureCategoryPath(string $path): int
    {
        global $DB, $CFG;
        require_once($CFG->dirroot . '/course/lib.php');

        $parts    = explode('/', $path);
        $parentId = 0;
        $node     = $this->loadCategoriesConfig(); // árbol raíz

        foreach ($parts as $part) {
            $part     = trim($part);
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
                $newCat            = core_course_category::create($catData);
                $parentId          = (int)$newCat->id;
            }

            // Descender al nivel siguiente del árbol de configuración
            $node = $node[$part]['children'] ?? [];
        }

        return $parentId;
    }

    /**
     * Carga categories.json (lazy, una sola vez por instancia).
     * Retorna el árbol raíz del JSON.
     */
    private function loadCategoriesConfig(): array
    {
        if (!empty($this->categoriesConfig)) {
            return $this->categoriesConfig;
        }

        $path = BACKEND_DIR . '/config/categories.json';

        if (!file_exists($path)) {
            return [];
        }

        $decoded = json_decode(file_get_contents($path), true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log("WARN: Error al parsear categories.json: " . json_last_error_msg());
            return [];
        }

        // Eliminar la clave 'comment' si existe
        unset($decoded['comment']);

        $this->categoriesConfig = $decoded;
        return $this->categoriesConfig;
    }
}
