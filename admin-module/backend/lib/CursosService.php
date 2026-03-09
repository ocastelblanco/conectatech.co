<?php
/**
 * CursosService.php — Lógica de negocio para crear y listar cursos finales.
 *
 * Encapsula todas las operaciones Moodle relacionadas con cursos: verificación
 * de existencia, creación, jerarquía de categorías, y copia de formato e imagen
 * desde un curso plantilla. Reutilizable desde scripts CLI y handlers de API.
 *
 * Uso típico:
 *   $service = new CursosService(CONFIG_DIR);
 *   $result  = $service->crearCurso($row, $dryRun);
 */

class CursosService
{
    private string $configDir;

    /**
     * @param string $configDir  Ruta absoluta al directorio config/ del backend.
     *                           Se usa para leer categories.json.
     */
    public function __construct(string $configDir)
    {
        $this->configDir = $configDir;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Operaciones públicas
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Crea un curso final en Moodle.
     *
     * Si el curso ya existe (por shortname), lo omite sin modificarlo.
     * En modo dry-run retorna el resultado esperado sin ejecutar cambios.
     * Copia el formato visual (opciones de formato) y la imagen de portada
     * del curso plantilla al curso recién creado.
     *
     * @param array $data  {
     *   shortname:     string  Nombre corto único del curso.
     *   fullname:      string  Nombre completo visible.
     *   category:      string  Ruta de categorías separada por "/" (e.g., "COLEGIOS/San Marino/CN").
     *   templatecourse: string Shortname del curso del que se copian formato e imagen.
     * }
     * @param bool $dryRun  Si true, no ejecuta cambios en Moodle.
     * @return array {
     *   shortname: string, fullname: string, category: string, template: string,
     *   action: 'created'|'skipped'|'dry-run'|'error',
     *   course_id: int|null, error: string|null
     * }
     */
    public function crearCurso(array $data, bool $dryRun = false): array
    {
        global $DB, $CFG;
        require_once($CFG->dirroot . '/course/lib.php');

        $shortname    = $data['shortname'];
        $fullname     = $data['fullname'];
        $categoryPath = $data['category'];
        $templateSn   = $data['templatecourse'];

        $result = [
            'shortname' => $shortname,
            'fullname'  => $fullname,
            'category'  => $categoryPath,
            'template'  => $templateSn,
            'action'    => '',
            'course_id' => null,
            'error'     => null,
        ];

        // ── Verificar si ya existe ────────────────────────────────────────────
        $existing = $DB->get_record('course', ['shortname' => $shortname]);

        if ($existing) {
            $result['action']    = 'skipped';
            $result['course_id'] = (int)$existing->id;
            return $result;
        }

        if ($dryRun) {
            $result['action'] = 'dry-run';
            return $result;
        }

        // ── Garantizar jerarquía de categorías ───────────────────────────────
        $categoryId = $this->ensureCategoryPath($categoryPath);

        // ── Crear el curso ────────────────────────────────────────────────────
        $courseData = (object)[
            'fullname'    => $fullname,
            'shortname'   => $shortname,
            'category'    => $categoryId,
            'visible'     => 0,
            'format'      => 'topics',
            'numsections' => 0,
        ];

        $newCourse = create_course($courseData);

        // ── Copiar formato e imagen del curso plantilla ───────────────────────
        $template = $DB->get_record('course', ['shortname' => $templateSn]);

        if ($template) {
            $this->copyFormatOptions($template, $newCourse);
            $this->copyOverviewImage($template, $newCourse);
        }

        $result['action']    = 'created';
        $result['course_id'] = (int)$newCourse->id;
        return $result;
    }

    /**
     * Lista los cursos dentro de una jerarquía de categorías.
     *
     * Si no se especifica categoría, devuelve todos los cursos (excepto el sitio).
     * Útil para el endpoint GET /api/cursos del panel de administración.
     *
     * @param string $categoryPath  Ruta de categoría (e.g., "COLEGIOS/San Marino").
     *                              Cadena vacía para listar todos.
     * @return array[]  Objetos {id, shortname, fullname, category, visible}.
     */
    public function listarCursos(string $categoryPath = ''): array
    {
        global $DB;

        if ($categoryPath === '') {
            $courses = $DB->get_records('course', null, 'shortname', 'id,shortname,fullname,category,visible');
            return array_values(array_filter(
                (array)$courses,
                fn($c) => (int)$c->id !== 1
            ));
        }

        try {
            $catId  = $this->resolveCategoryId($categoryPath);
            $catIds = $this->getDescendantCategoryIds($catId);
        } catch (RuntimeException) {
            return [];
        }

        $courses = $DB->get_records_list('course', 'category', $catIds, 'shortname', 'id,shortname,fullname,category,visible');
        return array_values(array_filter(
            (array)$courses,
            fn($c) => (int)$c->id !== 1
        ));
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Métodos privados — categorías
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Crea la jerarquía de categorías si no existe y retorna el ID de la hoja.
     * Lee idnumbers opcionales de categories.json.
     */
    private function ensureCategoryPath(string $path): int
    {
        global $DB, $CFG;
        require_once($CFG->dirroot . '/course/lib.php');

        $parts     = array_map('trim', explode('/', $path));
        $parentId  = 0;
        $catConfig = $this->loadCategoriesConfig();
        $node      = $catConfig;

        foreach ($parts as $part) {
            $existing = $DB->get_record('course_categories', ['name' => $part, 'parent' => $parentId]);

            if ($existing) {
                $parentId = (int)$existing->id;
            } else {
                $catData           = new stdClass();
                $catData->name     = $part;
                $catData->idnumber = $node[$part]['idnumber'] ?? '';
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
     * Navega course_categories por name+parent sin crear nada.
     * Lanza RuntimeException si algún segmento no existe.
     */
    private function resolveCategoryId(string $path): int
    {
        global $DB;

        $parts    = array_map('trim', explode('/', $path));
        $parentId = 0;

        foreach ($parts as $part) {
            $cat = $DB->get_record('course_categories', ['name' => $part, 'parent' => $parentId]);

            if (!$cat) {
                throw new RuntimeException("Categoría '{$part}' no encontrada (path: {$path})");
            }

            $parentId = (int)$cat->id;
        }

        return $parentId;
    }

    /**
     * Retorna todos los IDs de categorías descendientes, incluido el nodo raíz (BFS).
     *
     * @return int[]
     */
    private function getDescendantCategoryIds(int $parentId): array
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
     * Carga categories.json para idnumbers de categorías.
     * Retorna array vacío si el archivo no existe o es inválido.
     */
    private function loadCategoriesConfig(): array
    {
        $path = $this->configDir . '/categories.json';

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

    // ─────────────────────────────────────────────────────────────────────────
    // Métodos privados — copia de plantilla
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Copia el formato y sus opciones visuales del curso plantilla al nuevo curso.
     */
    private function copyFormatOptions(object $template, object $newCourse): void
    {
        global $CFG;
        require_once($CFG->dirroot . '/course/lib.php');

        $templateFormat = course_get_format($template->id);
        $formatOptions  = $templateFormat->get_format_options();

        $updateData         = (object)['id' => $newCourse->id, 'format' => $template->format];
        foreach ($formatOptions as $key => $value) {
            $updateData->$key = $value;
        }

        update_course($updateData);
    }

    /**
     * Copia la imagen de portada (overviewfiles) del curso plantilla al nuevo curso.
     * Solo copia la primera imagen encontrada.
     */
    private function copyOverviewImage(object $template, object $newCourse): void
    {
        global $CFG;
        require_once($CFG->libdir . '/filelib.php');

        $fs          = get_file_storage();
        $templateCtx = context_course::instance($template->id);
        $newCtx      = context_course::instance($newCourse->id);

        $files = $fs->get_area_files(
            $templateCtx->id, 'course', 'overviewfiles', false, 'filename', false
        );

        foreach ($files as $file) {
            if ($file->get_filename() === '.') {
                continue;
            }

            $fs->delete_area_files($newCtx->id, 'course', 'overviewfiles');
            $fs->create_file_from_storedfile([
                'contextid' => $newCtx->id,
                'component' => 'course',
                'filearea'  => 'overviewfiles',
                'itemid'    => 0,
                'filepath'  => $file->get_filepath(),
                'filename'  => $file->get_filename(),
            ], $file);
            break;
        }
    }
}
