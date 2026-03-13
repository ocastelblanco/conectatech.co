<?php
/**
 * ArbolCurricularService.php — CRUD de árboles curriculares y lógica de ejecución.
 *
 * Los árboles se almacenan como archivos JSON en backend/data/arboles/{uuid}.json.
 * Los métodos que consultan Moodle requieren que el bootstrap esté cargado ($DB, $CFG).
 *
 * Uso típico:
 *   $service = new ArbolCurricularService();
 *   $arboles = $service->listar();
 *   $arbol   = $service->crear(['nombre' => '...', ...]);
 */

class ArbolCurricularService
{
    private string $dataDir;

    public function __construct()
    {
        // Calcular ruta absoluta desde la ubicación de este archivo
        $this->dataDir = dirname(__DIR__) . '/data/arboles';

        if (!is_dir($this->dataDir)) {
            mkdir($this->dataDir, 0755, true);
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // CRUD de árboles (archivos JSON)
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Lista todos los árboles con metadatos básicos.
     *
     * @return array[]  [{id, nombre, shortname, periodo, institucion, updated_at}]
     */
    public function listar(): array
    {
        $files  = glob($this->dataDir . '/*.json') ?: [];
        $result = [];

        foreach ($files as $file) {
            $arbol = $this->decodeJson($file);
            if ($arbol === null) {
                continue;
            }
            $result[] = [
                'id'          => $arbol['id']          ?? '',
                'nombre'      => $arbol['nombre']       ?? '',
                'shortname'   => $arbol['shortname']    ?? '',
                'periodo'     => $arbol['periodo']      ?? '',
                'institucion' => $arbol['institucion']  ?? '',
                'updated_at'  => $arbol['updated_at']   ?? '',
            ];
        }

        // Ordenar por updated_at descendente
        usort($result, fn($a, $b) => strcmp($b['updated_at'], $a['updated_at']));

        return $result;
    }

    /**
     * Crea un árbol nuevo y lo persiste.
     *
     * @param array $data  {nombre, shortname, periodo, institucion, categoria_raiz}
     * @return array  Árbol completo recién creado.
     * @throws InvalidArgumentException Si faltan campos requeridos.
     */
    public function crear(array $data): array
    {
        $required = ['nombre', 'shortname', 'periodo', 'institucion', 'categoria_raiz'];
        foreach ($required as $field) {
            if (empty($data[$field])) {
                throw new InvalidArgumentException("Campo requerido ausente: {$field}");
            }
        }

        $now   = date('c');
        $arbol = [
            'id'            => uniqid('arbol-', true),
            'nombre'        => $data['nombre'],
            'shortname'     => $data['shortname'],
            'shortname_inst'=> $data['shortname_inst'] ?? '',
            'periodo'       => $data['periodo'],
            'institucion'   => $data['institucion'],
            'categoria_raiz'=> $data['categoria_raiz'],
            'created_at'    => $now,
            'updated_at'    => $now,
            'grados'        => $data['grados'] ?? [],
        ];

        $this->saveArbol($arbol);
        return $arbol;
    }

    /**
     * Carga y retorna el árbol completo.
     *
     * @throws RuntimeException Si no existe.
     */
    public function obtener(string $id): array
    {
        return $this->loadArbol($id);
    }

    /**
     * Guarda el árbol completo (actualiza updated_at).
     *
     * @throws RuntimeException Si el ID no existe.
     */
    public function guardar(string $id, array $arbol): array
    {
        // Verificar que existe
        $this->loadArbol($id);

        $arbol['id']         = $id;
        $arbol['updated_at'] = date('c');

        $this->saveArbol($arbol);
        return $arbol;
    }

    /**
     * Elimina el archivo JSON del árbol.
     *
     * @throws RuntimeException Si no existe.
     */
    public function eliminar(string $id): void
    {
        $path = $this->filePath($id);

        if (!file_exists($path)) {
            throw new RuntimeException("Árbol no encontrado: {$id}");
        }

        if (!unlink($path)) {
            throw new RuntimeException("No se pudo eliminar el árbol: {$id}");
        }
    }

    /**
     * Duplica un árbol existente con nuevos metadatos.
     *
     * @param array $meta  {nombre, shortname, periodo, institucion}
     * @throws RuntimeException Si el árbol fuente no existe.
     */
    public function duplicar(string $id, array $meta): array
    {
        $fuente = $this->loadArbol($id);

        $now    = date('c');
        $nuevo  = $fuente;

        $nuevo['id']          = uniqid('arbol-', true);
        $nuevo['nombre']      = $meta['nombre']      ?? $fuente['nombre'];
        $nuevo['shortname']   = $meta['shortname']   ?? $fuente['shortname'];
        $nuevo['periodo']     = $meta['periodo']      ?? $fuente['periodo'];
        $nuevo['institucion'] = $meta['institucion']  ?? $fuente['institucion'];
        $nuevo['created_at']  = $now;
        $nuevo['updated_at']  = $now;
        // grados/cursos/temas se mantienen igual

        $this->saveArbol($nuevo);
        return $nuevo;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Consultas a Moodle
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Retorna los valores únicos de proyecto_css y area_css de todos los árboles.
     *
     * @return array{proyectos: string[], areas: string[]}
     */
    public function getOpcionesCss(): array
    {
        $files     = glob($this->dataDir . '/*.json') ?: [];
        $proyectos = [];
        $areas     = [];

        foreach ($files as $file) {
            $arbol = $this->decodeJson($file);
            if ($arbol === null) {
                continue;
            }

            $p = trim($arbol['proyecto_css'] ?? '');
            if ($p !== '' && !in_array($p, $proyectos, true)) {
                $proyectos[] = $p;
            }

            foreach ($arbol['grados'] ?? [] as $grado) {
                foreach ($grado['cursos'] ?? [] as $curso) {
                    $a = trim($curso['area_css'] ?? '');
                    if ($a !== '' && !in_array($a, $areas, true)) {
                        $areas[] = $a;
                    }
                }
            }
        }

        sort($proyectos);
        sort($areas);

        return ['proyectos' => $proyectos, 'areas' => $areas];
    }

    /**
     * Retorna las categorías de primer nivel (parent=0) en Moodle.
     *
     * @return array[]  [{id, name}]
     */
    public function getCategoriasRaiz(): array
    {
        global $DB;

        $cats   = $DB->get_records('course_categories', ['parent' => 0], 'sortorder', 'id,name');
        $result = [];

        foreach ($cats as $cat) {
            $result[] = [
                'id'   => (int)$cat->id,
                'name' => $cat->name,
            ];
        }

        return $result;
    }

    /**
     * Retorna el árbol de plantillas (categoría PLANTILLAS y sus subcategorías con cursos).
     *
     * @return array[]  [{nombre, cursos: [{shortname, fullname}]}]
     */
    public function getPlantillas(): array
    {
        global $DB;

        $catPlantillas = $DB->get_record('course_categories', ['name' => 'PLANTILLAS', 'parent' => 0]);

        if (!$catPlantillas) {
            return [];
        }

        $subCats = $DB->get_records('course_categories', ['parent' => $catPlantillas->id], 'sortorder', 'id,name');
        $result  = [];

        foreach ($subCats as $sub) {
            $cursos = $DB->get_records('course', ['category' => $sub->id], 'shortname', 'id,shortname,fullname');
            $items  = [];

            foreach ($cursos as $c) {
                $items[] = [
                    'shortname' => $c->shortname,
                    'fullname'  => $c->fullname,
                ];
            }

            $result[] = [
                'nombre' => $sub->name,
                'cursos' => $items,
            ];
        }

        return $result;
    }

    /**
     * Retorna el árbol de repositorios con sus secciones (dos niveles: proyecto → área → cursos).
     *
     * @return array[]  [{nombre: proyecto, areas: [{nombre, cursos: [{shortname, fullname, secciones: [{num, titulo}]}]}]}]
     */
    public function getRepositorios(): array
    {
        global $DB;

        $catRepo = $DB->get_record('course_categories', ['name' => 'REPOSITORIOS', 'parent' => 0]);

        if (!$catRepo) {
            return [];
        }

        // Primer nivel: proyectos
        $proyectos = $DB->get_records('course_categories', ['parent' => $catRepo->id], 'sortorder', 'id,name');
        $result    = [];

        foreach ($proyectos as $proyecto) {
            // Segundo nivel: áreas
            $areas    = $DB->get_records('course_categories', ['parent' => $proyecto->id], 'sortorder', 'id,name');
            $areasArr = [];

            foreach ($areas as $area) {
                $cursos    = $DB->get_records('course', ['category' => $area->id], 'shortname', 'id,shortname,fullname');
                $cursosArr = [];

                foreach ($cursos as $c) {
                    $sql = "SELECT cs.section, cs.name
                              FROM {course_sections} cs
                             WHERE cs.course = :courseid
                               AND cs.section > 0
                               AND cs.name != ''
                               AND (cs.component IS NULL OR cs.component = '')
                             ORDER BY cs.section";

                    $secciones    = $DB->get_records_sql($sql, ['courseid' => (int)$c->id]);
                    $seccionesArr = [];

                    foreach ($secciones as $sec) {
                        $seccionesArr[] = [
                            'num'    => (int)$sec->section,
                            'titulo' => $sec->name,
                        ];
                    }

                    $cursosArr[] = [
                        'shortname' => $c->shortname,
                        'fullname'  => $c->fullname,
                        'secciones' => $seccionesArr,
                    ];
                }

                $areasArr[] = [
                    'nombre' => $area->name,
                    'cursos' => $cursosArr,
                ];
            }

            $result[] = [
                'nombre' => $proyecto->name,
                'areas'  => $areasArr,
            ];
        }

        return $result;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Validación y ejecución
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Pre-valida los conflictos de shortname antes de ejecutar.
     *
     * @return array  {ok: true, conflictos: [{shortname, fullname, estado, estudiantes, temas_nuevos}]}
     */
    public function validarEjecucion(string $id): array
    {
        global $DB;

        $arbol      = $this->loadArbol($id);
        $conflictos = [];

        foreach ($arbol['grados'] as $grado) {
            foreach ($grado['cursos'] as $curso) {
                $shortnameMoodle = $this->buildShortname($arbol, $curso, $grado);
                $fullnameMoodle  = $this->buildFullname($curso, $grado);

                $existing = $DB->get_record('course', ['shortname' => $shortnameMoodle]);

                if (!$existing) {
                    $conflictos[] = [
                        'shortname'    => $shortnameMoodle,
                        'fullname'     => $fullnameMoodle,
                        'area_nombre'  => $curso['nombre'],
                        'grado_nombre' => $grado['nombre'],
                        'estado'       => 'nuevo',
                        'estudiantes'  => 0,
                        'temas_nuevos' => count($curso['temas'] ?? []),
                        'temas_borrar' => 0,
                    ];
                    continue;
                }

                $numEstudiantes = $this->countStudents((int)$existing->id);
                $existentes     = $this->getExistingSectionNames((int)$existing->id);
                $temasArbol     = array_column($curso['temas'] ?? [], 'titulo');
                $temasNuevos    = 0;
                $temasBorrar    = 0;

                if ($numEstudiantes > 0) {
                    // Nuevos: en árbol pero no en Moodle
                    foreach ($temasArbol as $titulo) {
                        if (!in_array($titulo, $existentes, true)) {
                            $temasNuevos++;
                        }
                    }
                    // A borrar: en Moodle pero no en árbol
                    foreach ($existentes as $titulo) {
                        if (!in_array($titulo, $temasArbol, true)) {
                            $temasBorrar++;
                        }
                    }
                } else {
                    // El curso se recreará: todos los temas actuales se perderán
                    $temasBorrar = count($existentes);
                    $temasNuevos = count($temasArbol);
                }

                $conflictos[] = [
                    'shortname'    => $shortnameMoodle,
                    'fullname'     => $fullnameMoodle,
                    'area_nombre'  => $curso['nombre'],
                    'grado_nombre' => $grado['nombre'],
                    'estado'       => $numEstudiantes > 0 ? 'existe_con_estudiantes' : 'existe_sin_estudiantes',
                    'estudiantes'  => $numEstudiantes,
                    'temas_nuevos' => $temasNuevos,
                    'temas_borrar' => $temasBorrar,
                ];
            }
        }

        return ['ok' => true, 'conflictos' => $conflictos];
    }

    /**
     * Ejecuta la creación y poblado de cursos en Moodle a partir del árbol.
     *
     * @param bool $dryRun  Si true, no ejecuta cambios reales.
     * @return array  {ok, dry_run, summary: {created, updated, errors}, results: [...]}
     */
    public function ejecutar(string $id, bool $dryRun = false): array
    {
        global $DB, $CFG;
        require_once($CFG->dirroot . '/course/lib.php');

        $arbol   = $this->loadArbol($id);
        $results = [];
        $summary = ['created' => 0, 'updated' => 0, 'errors' => 0];

        $cursosService  = new CursosService(dirname(__DIR__) . '/config');
        $pobladorService = new PobladorService();

        foreach ($arbol['grados'] as $grado) {
            foreach ($grado['cursos'] as $curso) {
                $shortnameMoodle = $this->buildShortname($arbol, $curso, $grado);
                $fullnameMoodle  = $this->buildFullname($curso, $grado);
                $categoryPath    = $this->buildCategoryPath($arbol, $curso);

                $entry = [
                    'shortname' => $shortnameMoodle,
                    'fullname'  => $fullnameMoodle,
                    'action'    => '',
                    'error'     => null,
                ];

                try {
                    $existing = $DB->get_record('course', ['shortname' => $shortnameMoodle]);

                    if ($existing) {
                        $numEstudiantes = $this->countStudents((int)$existing->id);

                        if ($numEstudiantes > 0) {
                            // Solo añadir temas nuevos y actualizar fullname
                            if (!$dryRun) {
                                $updateData           = new stdClass();
                                $updateData->id       = $existing->id;
                                $updateData->fullname = $fullnameMoodle;
                                update_course($updateData);
                            }

                            $existentes   = $this->getExistingSectionNames((int)$existing->id);
                            $temasAAnadir = array_filter(
                                $curso['temas'] ?? [],
                                fn($t) => !in_array($t['titulo'], $existentes, true)
                            );

                            if (!empty($temasAAnadir)) {
                                $sections = array_map(fn($t) => [
                                    'repo'        => $t['repo_shortname'],
                                    'section_num' => $t['section_num'],
                                ], array_values($temasAAnadir));

                                $pobladorService->poblarCurso($shortnameMoodle, $sections, $dryRun);
                            }

                            $entry['action'] = 'updated';
                            $summary['updated']++;
                        } else {
                            // Eliminar y recrear
                            if (!$dryRun) {
                                delete_course($existing->id, false);
                            }

                            $this->crearYPoblar(
                                $cursosService, $pobladorService,
                                $shortnameMoodle, $fullnameMoodle, $categoryPath, $curso, $dryRun
                            );

                            $entry['action'] = 'recreated';
                            $summary['created']++;
                        }
                    } else {
                        // Curso nuevo
                        $this->crearYPoblar(
                            $cursosService, $pobladorService,
                            $shortnameMoodle, $fullnameMoodle, $categoryPath, $curso, $dryRun
                        );

                        $entry['action'] = 'created';
                        $summary['created']++;
                    }
                } catch (Throwable $e) {
                    $entry['action'] = 'error';
                    $entry['error']  = $e->getMessage();
                    $summary['errors']++;
                }

                $results[] = $entry;
            }
        }

        if (!$dryRun) {
            $this->regenerarHtmlAdicional();
        }

        return [
            'ok'      => true,
            'dry_run' => $dryRun,
            'summary' => $summary,
            'results' => $results,
        ];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Métodos privados — utilidades de archivo
    // ─────────────────────────────────────────────────────────────────────────

    private function filePath(string $id): string
    {
        return $this->dataDir . '/' . $id . '.json';
    }

    private function loadArbol(string $id): array
    {
        $path = $this->filePath($id);

        if (!file_exists($path)) {
            throw new RuntimeException("Árbol no encontrado: {$id}");
        }

        $arbol = $this->decodeJson($path);

        if ($arbol === null) {
            throw new RuntimeException("El archivo del árbol es inválido: {$id}");
        }

        return $arbol;
    }

    private function decodeJson(string $path): ?array
    {
        $contents = file_get_contents($path);
        if ($contents === false) {
            return null;
        }

        $decoded = json_decode($contents, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return null;
        }

        return $decoded;
    }

    private function saveArbol(array $arbol): void
    {
        $path     = $this->filePath($arbol['id']);
        $contents = json_encode($arbol, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        if ($contents === false) {
            throw new RuntimeException("No se pudo serializar el árbol: " . json_last_error_msg());
        }

        if (file_put_contents($path, $contents, LOCK_EX) === false) {
            throw new RuntimeException("No se pudo escribir el archivo del árbol: {$path}");
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Métodos privados — construcción de campos Moodle
    // ─────────────────────────────────────────────────────────────────────────

    private function buildShortname(array $arbol, array $curso, array $grado): string
    {
        $catPrefix = $this->resolveCategoryPrefix((int)$arbol['categoria_raiz']);
        $parts     = [$catPrefix, $arbol['shortname_inst'], $arbol['periodo'], $curso['shortname'], $grado['shortname']];
        return implode('-', array_filter($parts, fn($p) => $p !== '' && $p !== null));
    }

    private function buildFullname(array $curso, array $grado): string
    {
        return "{$curso['nombre']} - {$grado['shortname']}";
    }

    private function buildCategoryPath(array $arbol, array $curso): string
    {
        $catName = $this->resolveCategoryName((int)$arbol['categoria_raiz']);
        return "{$catName}/{$arbol['institucion']}/{$curso['nombre']}";
    }

    /**
     * Regenera el mapa CT_COURSES en el HTML Adicional de Moodle (additionalhtmlfooter).
     *
     * Lee todos los árboles, obtiene el ID de Moodle de cada curso final y construye
     * CT_COURSES = { "courseId": ["proyecto_css", "area_css"] }.
     * Reemplaza la sección delimitada por /* CT:MAP:START * / y /* CT:MAP:END * / en
     * additionalhtmlfooter. Si los delimitadores no existen, no hace nada (seguro).
     */
    private function regenerarHtmlAdicional(): void
    {
        global $DB;

        $files = glob($this->dataDir . '/*.json') ?: [];
        $map   = [];

        foreach ($files as $file) {
            $arbol = $this->decodeJson($file);
            if ($arbol === null) {
                continue;
            }

            $proyectoCss = trim($arbol['proyecto_css'] ?? '');

            foreach ($arbol['grados'] ?? [] as $grado) {
                foreach ($grado['cursos'] ?? [] as $curso) {
                    $areaCss  = trim($curso['area_css'] ?? '');
                    $shortname = $this->buildShortname($arbol, $curso, $grado);

                    $course = $DB->get_record('course', ['shortname' => $shortname], 'id');
                    if (!$course) {
                        continue;
                    }

                    $clases = array_values(array_filter([$proyectoCss, $areaCss]));
                    if (!empty($clases)) {
                        $map[(string)$course->id] = $clases;
                    }
                }
            }
        }

        $json    = json_encode($map, JSON_UNESCAPED_UNICODE);
        $newMap  = "/* CT:MAP:START */\n  const CT_COURSES = {$json};\n  /* CT:MAP:END */";

        $current = get_config('core', 'additionalhtmlfooter') ?? '';

        $updated = preg_replace(
            '/\/\* CT:MAP:START \*\/.*?\/\* CT:MAP:END \*\//s',
            $newMap,
            $current
        );

        // Solo guardar si hubo un reemplazo real (los delimitadores existían)
        if ($updated !== null && $updated !== $current) {
            set_config('additionalhtmlfooter', $updated);
        }
    }

    /**
     * Retorna el nombre completo de una categoría dado su ID.
     */
    private function resolveCategoryName(int $catId): string
    {
        global $DB;
        return (string)($DB->get_field('course_categories', 'name', ['id' => $catId]) ?? $catId);
    }

    /**
     * Retorna las 3 primeras letras en mayúsculas del nombre de la categoría raíz.
     */
    private function resolveCategoryPrefix(int $catId): string
    {
        return strtoupper(substr($this->resolveCategoryName($catId), 0, 3));
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Métodos privados — consultas Moodle
    // ─────────────────────────────────────────────────────────────────────────

    private function countStudents(int $courseId): int
    {
        global $DB;

        $sql = "SELECT COUNT(ra.id)
                  FROM {role_assignments} ra
                  JOIN {role} r ON r.id = ra.roleid
                  JOIN {context} ctx ON ctx.id = ra.contextid
                 WHERE ctx.contextlevel = :ctxlevel
                   AND ctx.instanceid   = :courseid
                   AND r.shortname      = 'student'";

        return (int)$DB->count_records_sql($sql, [
            'ctxlevel' => CONTEXT_COURSE,
            'courseid' => $courseId,
        ]);
    }

    private function getExistingSectionNames(int $courseId): array
    {
        global $DB;

        $sql = "SELECT name
                  FROM {course_sections}
                 WHERE course  = :courseid
                   AND section > 0
                   AND name   != ''";

        $rows  = $DB->get_records_sql($sql, ['courseid' => $courseId]);
        $names = [];

        foreach ($rows as $row) {
            $names[] = $row->name;
        }

        return $names;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Métodos privados — helpers de ejecución
    // ─────────────────────────────────────────────────────────────────────────

    private function crearYPoblar(
        CursosService  $cursosService,
        PobladorService $pobladorService,
        string          $shortname,
        string          $fullname,
        string          $categoryPath,
        array           $curso,
        bool            $dryRun
    ): void {
        $cursosService->crearCurso([
            'shortname'      => $shortname,
            'fullname'       => $fullname,
            'category'       => $categoryPath,
            'templatecourse' => $curso['templatecourse'] ?? '',
            'startdate'      => $curso['startdate']       ?? null,
            'enddate'        => $curso['enddate']          ?? null,
        ], $dryRun);

        $sections = array_map(fn($t) => [
            'repo'        => $t['repo_shortname'],
            'section_num' => $t['section_num'],
        ], $curso['temas'] ?? []);

        if (!empty($sections)) {
            $pobladorService->poblarCurso($shortname, $sections, $dryRun);
        }
    }
}
