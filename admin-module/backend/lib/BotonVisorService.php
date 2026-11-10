<?php
/**
 * BotonVisorService.php
 * Garantiza el botón «▶ Exponer contenido» del visor fullscreen (ADR-012) en los
 * temas de un curso: label oculto (visible = 0) como primer recurso de la sección.
 *
 * Lo usan:
 *   - ArbolCurricularService::ejecutar() — al terminar cada curso del árbol, para que
 *     también los cursos con matrículas (que no se repueblan) reciban el botón.
 *   - agregar-boton-visor.php — retrocompatibilidad por CLI.
 *
 * Idempotente: no duplica el botón; si existe pero no es el primer recurso, lo mueve al
 * inicio; omite los temas sin contenido expositivo (misma regla que VisorService).
 * No toca contenido, notas ni matrículas.
 *
 * Requiere Moodle inicializado y MoodleContentBuilder.php + VisorService.php cargados.
 */

class BotonVisorService
{
    public const ACCION_AGREGADO      = 'agregado';
    public const ACCION_MOVIDO        = 'movido';
    public const ACCION_YA            = 'ya_correcto';
    public const ACCION_SIN_CONTENIDO = 'sin_contenido';
    public const ACCION_ERROR         = 'error';

    private VisorService $visor;

    public function __construct(?VisorService $visor = null)
    {
        $this->visor = $visor ?? new VisorService();
    }

    /**
     * Asegura el botón en todos los temas (secciones H1) del curso.
     *
     * @param stdClass $course  Registro del curso.
     * @param int|null $userId  Usuario para quien se evalúa la visibilidad (null = el actual).
     * @param bool     $dryRun  Si true, solo informa lo que haría.
     * @return array{temas: array<int, array{section:int, titulo:string, accion:string, mensaje:?string}>,
     *               totales: array<string,int>}
     */
    public function asegurarEnCurso(stdClass $course, ?int $userId = null, bool $dryRun = false): array
    {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/course/lib.php');
        require_once($CFG->dirroot . '/course/modlib.php');

        $totales = [
            self::ACCION_AGREGADO      => 0,
            self::ACCION_MOVIDO        => 0,
            self::ACCION_YA            => 0,
            self::ACCION_SIN_CONTENIDO => 0,
            self::ACCION_ERROR         => 0,
        ];
        $temas = [];

        $sections = $DB->get_records_select(
            'course_sections',
            "course = ? AND section > 0 AND (component IS NULL OR component = '')",
            [$course->id],
            'section'
        );

        foreach ($sections as $section) {
            $fila = ['section' => (int)$section->section, 'titulo' => '', 'accion' => '', 'mensaje' => null];

            try {
                $botonId = $this->buscarBoton((int)$section->id);

                if ($botonId !== null) {
                    $fila['titulo'] = (string)$section->name;
                    if ($this->primerRecursoId($section) === $botonId) {
                        $fila['accion'] = self::ACCION_YA;
                    } else {
                        if (!$dryRun) {
                            $this->moverAlInicio($course, $section, $botonId);
                        }
                        $fila['accion'] = self::ACCION_MOVIDO;
                    }
                } else {
                    $tema = $this->visor->obtenerTema((int)$course->id, (int)$section->id, $userId);
                    if ($tema === null || empty($tema['subtemas'])) {
                        $fila['titulo'] = (string)$section->name;
                        $fila['accion'] = self::ACCION_SIN_CONTENIDO;
                    } else {
                        $fila['titulo'] = $tema['titulo'];
                        $fila['mensaje'] = count($tema['subtemas']) . ' subtemas';
                        if (!$dryRun) {
                            $this->insertar($course, $section);
                        }
                        $fila['accion'] = self::ACCION_AGREGADO;
                    }
                }
            } catch (Throwable $e) {
                $DB->force_transaction_rollback();
                $fila['accion']  = self::ACCION_ERROR;
                $fila['mensaje'] = $e->getMessage();
                error_log("BotonVisorService: curso {$course->shortname}, sección {$section->section}: " . $e->getMessage());
            }

            $totales[$fila['accion']]++;
            $temas[] = $fila;
        }

        return ['temas' => $temas, 'totales' => $totales];
    }

    /** cmid del label con el botón en la sección (id de course_sections), o null. */
    private function buscarBoton(int $sectionId): ?int
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
    private function primerRecursoId(stdClass $section): ?int
    {
        global $DB;

        $sequence = (string)$DB->get_field('course_sections', 'sequence', ['id' => $section->id]);
        foreach (array_filter(explode(',', $sequence)) as $id) {
            return (int)$id;
        }
        return null;
    }

    /** Crea el label oculto con el botón y lo deja como primer recurso de la sección. */
    private function insertar(stdClass $course, stdClass $section): void
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
        $this->moverAlInicio($course, $section, (int)$creado->coursemodule);
    }

    /**
     * Deja el módulo como primer recurso de la sección y garantiza que siga oculto.
     * moveto_module() exige un registro con `modname`, por eso se usa get_coursemodule_from_id()
     * y no una fila cruda de course_modules.
     */
    private function moverAlInicio(stdClass $course, stdClass $section, int $cmId): void
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
}
