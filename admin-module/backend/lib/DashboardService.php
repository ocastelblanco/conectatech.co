<?php
/**
 * DashboardService — KPIs globales del panel de administración.
 *
 * Agrega métricas de ambos tracks comerciales:
 *   Track A (directos): instituciones + categorías Moodle
 *   Track B (indirectos): organizaciones + pines
 */
class DashboardService
{
    public function getResumen(): array
    {
        global $DB;

        $hace30dias = time() - 30 * 24 * 3600;

        $instituciones  = (int)$DB->count_records('ct_institucion');
        $organizaciones = (int)$DB->count_records('ct_organization');
        $pinesActivos   = (int)$DB->count_records('ct_pin', ['status' => 'active']);

        $cursos = (int)$DB->count_records_sql(
            "SELECT COUNT(DISTINCT c.id)
             FROM {course} c
             JOIN {enrol} e ON e.courseid = c.id
             JOIN {user_enrolments} ue ON ue.enrolid = e.id
             WHERE c.id != 1",
            []
        );

        $usuariosActivos = (int)$DB->count_records_sql(
            "SELECT COUNT(*) FROM {user}
             WHERE deleted = 0 AND suspended = 0 AND lastlogin > ? AND id != 1",
            [$hace30dias]
        );

        $teacherRoles   = $DB->get_records_sql(
            "SELECT id FROM {role} WHERE shortname IN ('editingteacher', 'teacher')"
        );
        $teacherRoleIds = implode(',', array_map(fn($r) => (int)$r->id, $teacherRoles));

        $docentesActivos = 0;
        if ($teacherRoleIds) {
            $docentesActivos = (int)$DB->count_records_sql(
                "SELECT COUNT(DISTINCT u.id)
                 FROM {user} u
                 JOIN {role_assignments} ra ON ra.userid = u.id
                 WHERE ra.roleid IN ($teacherRoleIds)
                 AND u.deleted = 0 AND u.suspended = 0 AND u.lastlogin > ?",
                [$hace30dias]
            );
        }

        return [
            'instituciones'    => $instituciones,
            'organizaciones'   => $organizaciones,
            'pines_activos'    => $pinesActivos,
            'cursos'           => $cursos,
            'usuarios_activos' => $usuariosActivos,
            'docentes_activos' => $docentesActivos,
            'activaciones'     => $this->getActivacionesMensuales(),
        ];
    }

    public function getOrganizaciones(): array
    {
        global $DB;

        $sql = "SELECT o.id, o.name, o.created_at,
                       cat.name AS categoria_nombre,
                       COUNT(CASE WHEN p.status = 'available' THEN 1 END) AS pines_disponibles,
                       COUNT(CASE WHEN p.status = 'assigned'  THEN 1 END) AS pines_asignados,
                       COUNT(CASE WHEN p.status = 'active'    THEN 1 END) AS pines_activos,
                       COUNT(p.id) AS pines_total
                FROM {ct_organization} o
                LEFT JOIN {course_categories} cat ON cat.id = o.moodle_category_id
                LEFT JOIN {ct_pin_package} pkg     ON pkg.organization_id = o.id
                LEFT JOIN {ct_pin} p               ON p.package_id = pkg.id
                GROUP BY o.id, o.name, o.created_at, cat.name
                ORDER BY o.name ASC";

        $rows   = $DB->get_records_sql($sql);
        $result = [];

        foreach ($rows as $row) {
            $total = (int)$row->pines_total;
            $activos = (int)$row->pines_activos;
            $result[] = [
                'id'                 => (int)$row->id,
                'name'               => $row->name,
                'categoria_nombre'   => $row->categoria_nombre ?? '',
                'pines_disponibles'  => (int)$row->pines_disponibles,
                'pines_asignados'    => (int)$row->pines_asignados,
                'pines_activos'      => $activos,
                'pines_total'        => $total,
                'tasa_activacion'    => $total > 0 ? (int)round($activos / $total * 100) : 0,
            ];
        }

        return $result;
    }

    public function getCursos(): array
    {
        global $DB;

        $studentRole   = $DB->get_record('role', ['shortname' => 'student'], 'id');
        $studentRoleId = $studentRole ? (int)$studentRole->id : 5;

        // Solo cursos con al menos 1 estudiante matriculado
        $courses = $DB->get_records_sql(
            "SELECT c.id, c.fullname, cat.name AS categoria
             FROM {course} c
             JOIN {course_categories} cat ON cat.id = c.category
             JOIN {context} ctx ON ctx.instanceid = c.id AND ctx.contextlevel = 50
             JOIN {role_assignments} ra ON ra.contextid = ctx.id AND ra.roleid = ?
             WHERE c.id != 1
             GROUP BY c.id, c.fullname, cat.name
             ORDER BY c.fullname ASC
             LIMIT 200",
            [$studentRoleId]
        );

        $result = [];
        foreach ($courses as $course) {
            $courseId = (int)$course->id;

            $matriculados = (int)$DB->count_records_sql(
                "SELECT COUNT(DISTINCT ra.userid)
                 FROM {role_assignments} ra
                 JOIN {context} ctx ON ctx.id = ra.contextid
                 WHERE ra.roleid = ? AND ctx.contextlevel = 50 AND ctx.instanceid = ?",
                [$studentRoleId, $courseId]
            );

            $completados = (int)$DB->count_records_sql(
                "SELECT COUNT(*) FROM {course_completions}
                 WHERE course = ? AND timecompleted IS NOT NULL AND timecompleted > 0",
                [$courseId]
            );

            $result[] = [
                'id'           => $courseId,
                'nombre'       => $course->fullname,
                'categoria'    => $course->categoria,
                'matriculados' => $matriculados,
                'completados'  => $completados,
                'pct'          => $matriculados > 0
                    ? (int)round($completados / $matriculados * 100)
                    : 0,
            ];
        }

        usort($result, fn($a, $b) => $a['pct'] <=> $b['pct']);

        return $result;
    }

    // ── Privado ───────────────────────────────────────────────────────────────

    private function getActivacionesMensuales(): array
    {
        global $DB;

        $hace12meses = mktime(0, 0, 0, (int)date('n') - 11, 1);

        $rows = $DB->get_records_sql(
            "SELECT DATE_FORMAT(FROM_UNIXTIME(activated_at), '%Y-%m') AS mes,
                    COUNT(*) AS total
             FROM {ct_pin}
             WHERE status = 'active' AND activated_at IS NOT NULL AND activated_at > ?
             GROUP BY mes
             ORDER BY mes ASC",
            [$hace12meses]
        );

        return array_values(array_map(fn($r) => [
            'mes'   => $r->mes,
            'total' => (int)$r->total,
        ], $rows));
    }
}
