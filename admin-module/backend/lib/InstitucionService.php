<?php
/**
 * InstitucionService — CRUD y métricas para instituciones educativas directas (Track A).
 *
 * Track A: colegios que compran directamente a ConectaTech. Las matrículas
 * las gestiona ConectaTech vía CSV/Excel. Cada institución tiene una categoría
 * Moodle propia donde viven todos sus cursos.
 */
class InstitucionService
{
    // ── Listado ───────────────────────────────────────────────────────────────

    public function listar(): array
    {
        global $DB;

        $sql = "SELECT i.id, i.name, i.moodle_category_id, i.created_at,
                       cat.name AS categoria_nombre
                FROM {ct_institucion} i
                LEFT JOIN {course_categories} cat ON cat.id = i.moodle_category_id
                ORDER BY i.name ASC";

        $rows   = $DB->get_records_sql($sql);
        $result = [];

        foreach ($rows as $row) {
            $counts   = $this->contarPorCategoria((int)$row->moodle_category_id);
            $result[] = [
                'id'                 => (int)$row->id,
                'name'               => $row->name,
                'moodle_category_id' => (int)$row->moodle_category_id,
                'created_at'         => (int)$row->created_at,
                'categoria_nombre'   => $row->categoria_nombre ?? '',
                'estudiantes'        => $counts['estudiantes'],
                'docentes'           => $counts['docentes'],
                'cursos'             => $counts['cursos'],
            ];
        }

        return $result;
    }

    // ── CRUD ─────────────────────────────────────────────────────────────────

    public function crear(string $name, int $moodleCategoryId): array
    {
        global $DB;

        if ($DB->record_exists('ct_institucion', ['moodle_category_id' => $moodleCategoryId])) {
            throw new Exception('La categoría ya está asociada a otra institución.');
        }

        $record = (object)[
            'name'               => $name,
            'moodle_category_id' => $moodleCategoryId,
            'created_at'         => time(),
        ];

        $id = $DB->insert_record('ct_institucion', $record);

        return [
            'id'                 => (int)$id,
            'name'               => $name,
            'moodle_category_id' => $moodleCategoryId,
        ];
    }

    public function actualizar(int $id, ?string $name, ?int $moodleCategoryId): void
    {
        global $DB;

        $record = $DB->get_record('ct_institucion', ['id' => $id]);
        if (!$record) {
            throw new Exception('Institución no encontrada.');
        }

        if ($name !== null)             $record->name               = $name;
        if ($moodleCategoryId !== null) $record->moodle_category_id = $moodleCategoryId;

        $DB->update_record('ct_institucion', $record);
    }

    public function eliminar(int $id): void
    {
        global $DB;

        if (!$DB->record_exists('ct_institucion', ['id' => $id])) {
            throw new Exception('Institución no encontrada.');
        }

        $DB->delete_records('ct_institucion', ['id' => $id]);
    }

    // ── Progreso por institución ──────────────────────────────────────────────

    public function getProgreso(int $id): array
    {
        global $DB;

        $inst = $DB->get_record('ct_institucion', ['id' => $id]);
        if (!$inst) {
            throw new Exception('Institución no encontrada.');
        }

        $catId         = (int)$inst->moodle_category_id;
        $studentRoleId = $this->getStudentRoleId();

        $courses = $DB->get_records_sql(
            "SELECT c.id, c.fullname, c.shortname
             FROM {course} c
             WHERE c.category = ? AND c.id != 1
             ORDER BY c.fullname ASC",
            [$catId]
        );

        $cursos = [];
        foreach ($courses as $course) {
            $courseId = (int)$course->id;

            $matriculados = (int)$DB->count_records_sql(
                "SELECT COUNT(DISTINCT ra.userid)
                 FROM {role_assignments} ra
                 JOIN {context} ctx ON ctx.id = ra.contextid
                 WHERE ra.roleid = ? AND ctx.contextlevel = 50 AND ctx.instanceid = ?",
                [$studentRoleId, $courseId]
            );

            if ($matriculados === 0) continue;

            $completados = (int)$DB->count_records_sql(
                "SELECT COUNT(*) FROM {course_completions}
                 WHERE course = ? AND timecompleted IS NOT NULL AND timecompleted > 0",
                [$courseId]
            );

            $cursos[] = [
                'course_id'    => $courseId,
                'nombre'       => $course->fullname,
                'shortname'    => $course->shortname,
                'matriculados' => $matriculados,
                'completados'  => $completados,
                'en_progreso'  => max(0, $matriculados - $completados),
                'pct'          => (int)round($completados / $matriculados * 100),
            ];
        }

        return [
            'institucion' => [
                'id'           => (int)$inst->id,
                'name'         => $inst->name,
                'anio_escolar' => $inst->anio_escolar,
            ],
            'cursos' => $cursos,
        ];
    }

    // ── Categorías disponibles ────────────────────────────────────────────────

    public function getCategorias(): array
    {
        global $DB;

        $colegiosId = $DB->get_field('course_categories', 'id', ['name' => 'COLEGIOS']);
        if (!$colegiosId) {
            return [];
        }

        $usedIds = $DB->get_fieldset_sql("SELECT moodle_category_id FROM {ct_institucion}", []);

        $where  = 'WHERE parent = :parent';
        $params = ['parent' => (int)$colegiosId];

        if (!empty($usedIds)) {
            [$insql, $inparams] = $DB->get_in_or_equal($usedIds, SQL_PARAMS_NAMED, 'cat', false);
            $where  .= " AND id $insql";
            $params  = array_merge($params, $inparams);
        }

        $cats = $DB->get_records_sql(
            "SELECT id, name FROM {course_categories} $where ORDER BY name ASC",
            $params
        );

        return array_values(array_map(fn($c) => [
            'id'   => (int)$c->id,
            'name' => $c->name,
        ], $cats));
    }

    // ── Helpers privados ──────────────────────────────────────────────────────

    private function contarPorCategoria(int $categoryId): array
    {
        global $DB;

        // Obtener todos los IDs de categoría descendientes (la propia + subcategorías)
        // usando el campo `path` de course_categories (ej: /1/13/28)
        $parent = $DB->get_record('course_categories', ['id' => $categoryId], 'id,path');
        if (!$parent) {
            return ['cursos' => 0, 'estudiantes' => 0, 'docentes' => 0];
        }
        $descCatIds = $DB->get_fieldset_sql(
            "SELECT id FROM {course_categories} WHERE path LIKE ?",
            [$parent->path . '/%']
        );
        $descCatIds[] = $categoryId;
        $descCatIds   = array_map('intval', $descCatIds);

        [$catSql, $catParams] = $DB->get_in_or_equal($descCatIds, SQL_PARAMS_NAMED, 'cat');

        $studentRoleId = $this->getStudentRoleId();

        $teacherRoles   = $DB->get_records_sql(
            "SELECT id FROM {role} WHERE shortname IN ('editingteacher', 'teacher')"
        );
        $teacherRoleIds = implode(',', array_map(fn($r) => (int)$r->id, $teacherRoles));

        $cursos = (int)$DB->count_records_sql(
            "SELECT COUNT(*) FROM {course} WHERE category $catSql AND id != 1",
            $catParams
        );

        $estudiantes = (int)$DB->count_records_sql(
            "SELECT COUNT(DISTINCT ra.userid)
             FROM {role_assignments} ra
             JOIN {context} ctx ON ctx.id = ra.contextid AND ctx.contextlevel = 50
             JOIN {course} c ON c.id = ctx.instanceid
             WHERE ra.roleid = :student AND c.category $catSql",
            array_merge(['student' => $studentRoleId], $catParams)
        );

        $docentes = 0;
        if ($teacherRoleIds) {
            $docentes = (int)$DB->count_records_sql(
                "SELECT COUNT(DISTINCT ra.userid)
                 FROM {role_assignments} ra
                 JOIN {context} ctx ON ctx.id = ra.contextid AND ctx.contextlevel = 50
                 JOIN {course} c ON c.id = ctx.instanceid
                 WHERE ra.roleid IN ($teacherRoleIds) AND c.category $catSql",
                $catParams
            );
        }

        return [
            'cursos'      => $cursos,
            'estudiantes' => $estudiantes,
            'docentes'    => $docentes,
        ];
    }

    private function getStudentRoleId(): int
    {
        global $DB;
        $role = $DB->get_record('role', ['shortname' => 'student'], 'id');
        return $role ? (int)$role->id : 5;
    }
}
