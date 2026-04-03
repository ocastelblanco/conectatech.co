<?php
/**
 * GestorService.php
 * Lógica de negocio para la vista del gestor:
 *   - Consultar su organización y los cursos disponibles.
 *   - CRUD de grupos de la organización.
 *   - Listar, filtrar y asignar pines.
 *   - Generar CSV de pines.
 *
 * Todos los métodos reciben $ctGestor (array inyectado por auth.php) para
 * garantizar que las operaciones estén siempre acotadas a la organización
 * del gestor autenticado.
 */

class GestorService
{
    // =========================================================================
    // Organización y cursos
    // =========================================================================

    /**
     * Retorna los datos de la organización del gestor junto con la lista de
     * cursos disponibles en su categoría Moodle.
     */
    public function getOrganizacion(array $ctGestor): array
    {
        global $DB;

        $org     = $DB->get_record('ct_organization', ['id' => $ctGestor['organization_id']], '*', MUST_EXIST);
        $courses = $this->getAllCoursesInCategory((int)$ctGestor['moodle_category_id']);

        // Ordenar por nombre completo
        usort($courses, fn($a, $b) => strcmp($a['fullname'], $b['fullname']));

        return [
            'id'                 => (int)$org->id,
            'name'               => $org->name,
            'moodle_category_id' => (int)$org->moodle_category_id,
            'courses'            => $courses,
        ];
    }

    /**
     * Devuelve todos los cursos dentro de $categoryId y sus subcategorías
     * (a cualquier profundidad), excluyendo el sitio raíz.
     */
    private function getAllCoursesInCategory(int $categoryId): array
    {
        global $DB;

        $result  = [];
        $courses = $DB->get_records('course', ['category' => $categoryId], '', 'id, fullname, shortname');

        foreach ($courses as $c) {
            if ((int)$c->id === SITEID) continue;
            $result[] = [
                'id'        => (int)$c->id,
                'fullname'  => $c->fullname,
                'shortname' => $c->shortname,
            ];
        }

        $subcats = $DB->get_records('course_categories', ['parent' => $categoryId], '', 'id');
        foreach ($subcats as $sub) {
            $result = array_merge($result, $this->getAllCoursesInCategory((int)$sub->id));
        }

        return $result;
    }

    // =========================================================================
    // Grupos
    // =========================================================================

    /**
     * Lista los grupos de la organización.
     */
    public function listarGrupos(array $ctGestor): array
    {
        global $DB;

        $grupos = $DB->get_records(
            'ct_group',
            ['organization_id' => $ctGestor['organization_id']],
            'name ASC'
        );

        return array_values(array_map(fn($g) => [
            'id'              => (int)$g->id,
            'name'            => $g->name,
            'moodle_group_id' => $g->moodle_group_id ? (int)$g->moodle_group_id : null,
        ], $grupos));
    }

    /**
     * Crea un nuevo grupo en la organización.
     * El nombre debe ser único dentro de la organización.
     * El grupo aún no existe en Moodle; se creará al matricular el primer usuario.
     */
    public function crearGrupo(array $ctGestor, string $name): array
    {
        global $DB;

        $name = trim($name);
        if ($name === '') {
            throw new InvalidArgumentException('El nombre del grupo no puede estar vacío.');
        }

        if ($DB->record_exists('ct_group', ['organization_id' => $ctGestor['organization_id'], 'name' => $name])) {
            throw new InvalidArgumentException("Ya existe un grupo con el nombre '{$name}' en esta organización.");
        }

        $id = $DB->insert_record('ct_group', (object)[
            'organization_id' => $ctGestor['organization_id'],
            'name'            => $name,
            'moodle_group_id' => null,
        ]);

        return ['id' => (int)$id, 'name' => $name, 'moodle_group_id' => null];
    }

    // =========================================================================
    // Pines
    // =========================================================================

    /**
     * Lista los pines de la organización con información de grupo y curso.
     * Parámetros de filtro opcionales: status, group_id, moodle_course_id.
     */
    public function listarPines(
        array   $ctGestor,
        ?string $status   = null,
        ?int    $groupId  = null,
        ?int    $courseId = null
    ): array {
        global $DB;

        $where  = 'pkg.organization_id = :orgid';
        $params = ['orgid' => $ctGestor['organization_id']];

        if ($status !== null) {
            $where          .= ' AND p.status = :status';
            $params['status'] = $status;
        }
        if ($groupId !== null) {
            $where            .= ' AND p.group_id = :groupid';
            $params['groupid'] = $groupId;
        }
        if ($courseId !== null) {
            $where              .= ' AND p.moodle_course_id = :courseid';
            $params['courseid'] = $courseId;
        }

        $sql = "SELECT p.id, p.hash, p.role, p.status, p.activated_at,
                       p.group_id, p.moodle_course_id,
                       pkg.id AS pkg_id, pkg.teacher_role, pkg.expires_at,
                       c.fullname  AS course_name,
                       g.name      AS group_name,
                       u.firstname AS activated_firstname,
                       u.lastname  AS activated_lastname
                FROM {ct_pin} p
                JOIN {ct_pin_package} pkg ON pkg.id = p.package_id
                LEFT JOIN {course}   c ON c.id  = p.moodle_course_id
                LEFT JOIN {ct_group} g ON g.id  = p.group_id
                LEFT JOIN {user}     u ON u.id  = p.activated_by
                WHERE {$where}
                ORDER BY p.id ASC";

        $rows   = $DB->get_records_sql($sql, $params);
        $result = [];

        foreach ($rows as $row) {
            $result[] = [
                'id'           => (int)$row->id,
                'hash'         => $row->hash,
                'role'         => $row->role,
                'status'       => $row->status,
                'expires_at'   => (int)$row->expires_at,
                'activated_at' => $row->activated_at ? (int)$row->activated_at : null,
                'group_id'     => $row->group_id     ? (int)$row->group_id     : null,
                'group_name'   => $row->group_name,
                'course_id'    => $row->moodle_course_id ? (int)$row->moodle_course_id : null,
                'course_name'      => $row->course_name,
                'package_id'       => (int)$row->pkg_id,
                'teacher_role'     => $row->teacher_role,
                'activated_nombre' => ($row->activated_firstname !== null)
                    ? trim($row->activated_firstname . ' ' . $row->activated_lastname)
                    : null,
            ];
        }

        return $result;
    }

    /**
     * Asigna en lote un conjunto de pines a un grupo, curso y rol.
     *
     * Validaciones:
     *   - Los pines pertenecen a la organización del gestor.
     *   - Solo se pueden asignar pines en estado 'available' o 'assigned'.
     *   - Si el rol es 'editingteacher' o 'teacher', debe coincidir exactamente
     *     con el teacher_role del paquete de cada pin.
     *   - El grupo pertenece a la organización.
     *   - El curso pertenece a la categoría de la organización.
     */
    public function asignarPines(
        array  $ctGestor,
        array  $pinIds,
        int    $groupId,
        int    $courseId,
        string $role
    ): void {
        global $DB;

        if (empty($pinIds)) {
            throw new InvalidArgumentException('Debe indicar al menos un pin.');
        }

        if (!in_array($role, ['editingteacher', 'teacher', 'student'], true)) {
            throw new InvalidArgumentException("Rol inválido: '{$role}'.");
        }

        // Verificar que el grupo pertenece a la organización
        if (!$DB->record_exists('ct_group', ['id' => $groupId, 'organization_id' => $ctGestor['organization_id']])) {
            throw new InvalidArgumentException('El grupo no pertenece a esta organización.');
        }

        // Verificar que el curso pertenece a la categoría de la organización
        // (incluyendo subcategorías a cualquier profundidad)
        $allCourseIds = array_column($this->getAllCoursesInCategory((int)$ctGestor['moodle_category_id']), 'id');
        if (!in_array($courseId, $allCourseIds, true)) {
            throw new InvalidArgumentException('El curso no pertenece a la categoría de esta organización.');
        }

        // Obtener los pines verificando que pertenecen a la organización
        [$inSql, $inParams] = $DB->get_in_or_equal($pinIds, SQL_PARAMS_QM);
        $params = array_merge([$ctGestor['organization_id']], $inParams);

        $pins = $DB->get_records_sql(
            "SELECT p.id, p.status, pkg.teacher_role
             FROM {ct_pin} p
             JOIN {ct_pin_package} pkg ON pkg.id = p.package_id
             WHERE pkg.organization_id = ? AND p.id {$inSql}",
            $params
        );

        if (count($pins) !== count($pinIds)) {
            throw new InvalidArgumentException('Uno o más pines no pertenecen a esta organización.');
        }

        foreach ($pins as $pin) {
            if (!in_array($pin->status, ['available', 'assigned'], true)) {
                throw new InvalidArgumentException(
                    "El pin {$pin->id} está activo y no puede reasignarse hasta que venza."
                );
            }

            // El rol de profesor debe coincidir con el teacher_role del paquete
            if ($role !== 'student' && $pin->teacher_role !== $role) {
                throw new InvalidArgumentException(
                    "El pin {$pin->id} pertenece a un paquete con teacher_role '{$pin->teacher_role}';"
                    . " no se puede asignar como '{$role}'."
                );
            }
        }

        // Actualizar todos los pines en lote
        foreach ($pins as $pin) {
            $DB->update_record('ct_pin', (object)[
                'id'               => $pin->id,
                'group_id'         => $groupId,
                'moodle_course_id' => $courseId,
                'role'             => $role,
                'status'           => 'assigned',
            ]);
        }
    }

    // =========================================================================
    // CSV
    // =========================================================================

    /**
     * Genera y retorna el contenido de un archivo CSV con los pines filtrados.
     * El handler es responsable de enviar los headers HTTP correctos.
     */
    public function generarCsv(
        array   $ctGestor,
        ?string $status   = null,
        ?int    $groupId  = null,
        ?int    $courseId = null
    ): string {
        $pins = $this->listarPines($ctGestor, $status, $groupId, $courseId);

        $buffer = fopen('php://temp', 'r+');

        // BOM UTF-8 para compatibilidad con Excel
        fwrite($buffer, "\xEF\xBB\xBF");

        fputcsv($buffer, ['Hash', 'Rol', 'Estado', 'Grupo', 'Curso', 'Vigencia hasta', 'Activado el']);

        foreach ($pins as $pin) {
            fputcsv($buffer, [
                $pin['hash'],
                $pin['role'],
                $pin['status'],
                $pin['group_name']  ?? '',
                $pin['course_name'] ?? '',
                date('Y-m-d', $pin['expires_at']),
                $pin['activated_at'] ? date('Y-m-d H:i', $pin['activated_at']) : '',
            ]);
        }

        rewind($buffer);
        $csv = stream_get_contents($buffer);
        fclose($buffer);

        return $csv;
    }
}
