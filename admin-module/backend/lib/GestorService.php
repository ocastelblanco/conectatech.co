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
    // Colegios
    // =========================================================================

    /**
     * Lista los colegios de la organización con sus grupos anidados.
     */
    public function listarColegios(array $ctGestor): array
    {
        global $DB;

        $colegios = $DB->get_records(
            'ct_colegio',
            ['organization_id' => $ctGestor['organization_id']],
            'name ASC'
        );

        $result = [];
        foreach ($colegios as $c) {
            $grupos = $this->listarGruposDeColegio($ctGestor, (int)$c->id);
            $result[] = [
                'id'         => (int)$c->id,
                'name'       => $c->name,
                'created_at' => (int)$c->created_at,
                'grupos'     => $grupos,
            ];
        }

        return $result;
    }

    /**
     * Crea un nuevo colegio en la organización.
     * El nombre debe ser único dentro de la organización.
     */
    public function crearColegio(array $ctGestor, string $name): array
    {
        global $DB;

        $name = trim($name);
        if ($name === '') {
            throw new InvalidArgumentException('El nombre del colegio no puede estar vacío.');
        }

        if ($DB->record_exists('ct_colegio', ['organization_id' => $ctGestor['organization_id'], 'name' => $name])) {
            throw new InvalidArgumentException("Ya existe un colegio con el nombre '{$name}' en esta organización.");
        }

        $id = $DB->insert_record('ct_colegio', (object)[
            'organization_id' => $ctGestor['organization_id'],
            'name'            => $name,
            'created_at'      => time(),
        ]);

        return ['id' => (int)$id, 'name' => $name, 'grupos' => []];
    }

    // =========================================================================
    // Grupos
    // =========================================================================

    /**
     * Lista todos los grupos de la organización (con info de colegio).
     */
    public function listarGrupos(array $ctGestor): array
    {
        global $DB;

        $sql = "SELECT g.id, g.name, g.colegio_id, g.moodle_group_id,
                       c.name AS colegio_name
                FROM {ct_group} g
                LEFT JOIN {ct_colegio} c ON c.id = g.colegio_id
                WHERE g.organization_id = :orgid
                ORDER BY c.name ASC, g.name ASC";

        $rows = $DB->get_records_sql($sql, ['orgid' => $ctGestor['organization_id']]);

        return array_values(array_map(fn($g) => [
            'id'              => (int)$g->id,
            'name'            => $g->name,
            'colegio_id'      => $g->colegio_id ? (int)$g->colegio_id : null,
            'colegio_name'    => $g->colegio_name,
            'moodle_group_id' => $g->moodle_group_id ? (int)$g->moodle_group_id : null,
        ], $rows));
    }

    /**
     * Lista los grupos de un colegio específico.
     */
    private function listarGruposDeColegio(array $ctGestor, int $colegioId): array
    {
        global $DB;

        $grupos = $DB->get_records(
            'ct_group',
            ['organization_id' => $ctGestor['organization_id'], 'colegio_id' => $colegioId],
            'name ASC'
        );

        if (empty($grupos)) {
            return [];
        }

        // Conteos de pines por grupo (assigned + active), agrupados por rol
        $groupIds = array_keys($grupos);
        [$inSql, $params] = $DB->get_in_or_equal($groupIds);
        $rows = $DB->get_records_sql(
            "SELECT CONCAT(group_id, '_', role, '_', status) AS rkey,
                    group_id, role, status, COUNT(*) AS cnt
               FROM {ct_pin}
              WHERE group_id $inSql
                AND status IN ('assigned', 'active')
           GROUP BY group_id, role, status",
            $params
        );

        // Mapa: [group_id][tipo: teacher|student][status] = count
        $counts = [];
        foreach ($rows as $row) {
            $gid  = (int)$row->group_id;
            $tipo = in_array($row->role, ['teacher', 'editingteacher'], true) ? 'teacher' : 'student';
            $counts[$gid][$tipo][$row->status] = (int)$row->cnt;
        }

        return array_values(array_map(fn($g) => [
            'id'                => (int)$g->id,
            'name'              => $g->name,
            'colegio_id'        => (int)$colegioId,
            'moodle_group_id'   => $g->moodle_group_id ? (int)$g->moodle_group_id : null,
            'teachers_active'   => $counts[$g->id]['teacher']['active']   ?? 0,
            'teachers_assigned' => $counts[$g->id]['teacher']['assigned'] ?? 0,
            'students_active'   => $counts[$g->id]['student']['active']   ?? 0,
            'students_assigned' => $counts[$g->id]['student']['assigned'] ?? 0,
        ], $grupos));
    }

    /**
     * Crea un nuevo grupo dentro de un colegio.
     * El nombre debe ser único dentro del colegio.
     * El grupo aún no existe en Moodle; se creará al matricular el primer usuario.
     */
    public function crearGrupo(array $ctGestor, int $colegioId, string $name): array
    {
        global $DB;

        $name = trim($name);
        if ($name === '') {
            throw new InvalidArgumentException('El nombre del grupo no puede estar vacío.');
        }

        // Verificar que el colegio pertenece a la organización
        if (!$DB->record_exists('ct_colegio', ['id' => $colegioId, 'organization_id' => $ctGestor['organization_id']])) {
            throw new InvalidArgumentException('El colegio no pertenece a esta organización.');
        }

        if ($DB->record_exists('ct_group', ['colegio_id' => $colegioId, 'name' => $name])) {
            throw new InvalidArgumentException("Ya existe un grupo con el nombre '{$name}' en este colegio.");
        }

        $id = $DB->insert_record('ct_group', (object)[
            'organization_id' => $ctGestor['organization_id'],
            'colegio_id'      => $colegioId,
            'name'            => $name,
            'moodle_group_id' => null,
        ]);

        return ['id' => (int)$id, 'name' => $name, 'colegio_id' => $colegioId, 'moodle_group_id' => null];
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

        $sql = "SELECT p.id, p.hash, p.role, p.status, p.activated_at, p.expires_at,
                       p.group_id, p.moodle_course_id,
                       pkg.id AS pkg_id, pkg.teacher_role, pkg.duration_days,
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
                'id'            => (int)$row->id,
                'hash'          => $row->hash,
                'role'          => $row->role,
                'status'        => $row->status,
                'duration_days' => (int)$row->duration_days,
                'expires_at'    => $row->expires_at ? (int)$row->expires_at : null,
                'activated_at'  => $row->activated_at ? (int)$row->activated_at : null,
                'group_id'      => $row->group_id ? (int)$row->group_id : null,
                'group_name'    => $row->group_name,
                'course_id'     => $row->moodle_course_id ? (int)$row->moodle_course_id : null,
                'course_name'       => $row->course_name,
                'package_id'        => (int)$row->pkg_id,
                'teacher_role'      => $row->teacher_role,
                'activated_nombre'  => ($row->activated_firstname !== null)
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

        fputcsv($buffer, ['Hash', 'Rol', 'Estado', 'Grupo', 'Curso', 'Duración', 'Vence el', 'Activado el']);

        $durationLabel = [93 => '3 meses', 182 => '6 meses', 365 => '12 meses'];

        foreach ($pins as $pin) {
            fputcsv($buffer, [
                $pin['hash'],
                $pin['role'],
                $pin['status'],
                $pin['group_name']  ?? '',
                $pin['course_name'] ?? '',
                $durationLabel[$pin['duration_days']] ?? $pin['duration_days'] . ' días',
                $pin['expires_at']   ? date('Y-m-d', $pin['expires_at'])          : '',
                $pin['activated_at'] ? date('Y-m-d H:i', $pin['activated_at'])    : '',
            ]);
        }

        rewind($buffer);
        $csv = stream_get_contents($buffer);
        fclose($buffer);

        return $csv;
    }

    /**
     * Lista los usuarios matriculados en cursos de la organización del gestor.
     * Incluye cursos, grupos Moodle y colegios de ConectaTech de cada usuario.
     * Alcance: categoría Moodle de la org y sus subcategorías. Máximo 200 usuarios.
     */
    public function listarUsuarios(array $ctGestor, ?string $search): array
    {
        global $DB, $USER;

        $catId   = (int)$ctGestor['moodle_category_id'];
        $orgId   = (int)$ctGestor['organization_id'];
        $catPath = '%/' . $catId . '/%';

        // Query 1 — IDs distintos; excluye al propio gestor autenticado
        $params = ['catid' => $catId, 'catpath' => $catPath, 'curuser' => (int)$USER->id];
        $where  = '(cc.id = :catid OR cc.path LIKE :catpath)';

        if ($search !== null && strlen($search) >= 3) {
            $like = '%' . $DB->sql_like_escape($search) . '%';
            $where .= ' AND (' .
                $DB->sql_like('u.firstname', ':s1', false) . ' OR ' .
                $DB->sql_like('u.lastname',  ':s2', false) . ' OR ' .
                $DB->sql_like('u.email',     ':s3', false) . ' OR ' .
                $DB->sql_like('u.username',  ':s4', false) .
            ')';
            $params['s1'] = $like;
            $params['s2'] = $like;
            $params['s3'] = $like;
            $params['s4'] = $like;
        }

        $userRows = $DB->get_records_sql(
            "SELECT DISTINCT u.id, u.firstname, u.lastname, u.email,
                             u.username, u.lastlogin, u.suspended
               FROM {user} u
               JOIN {user_enrolments}  ue ON ue.userid = u.id AND ue.status = 0
               JOIN {enrol}             e  ON e.id = ue.enrolid AND e.status = 0
               JOIN {course}            c  ON c.id = e.courseid
               JOIN {course_categories} cc ON cc.id = c.category
              WHERE u.deleted = 0 AND u.id != 1 AND u.id != :curuser AND {$where}
           ORDER BY u.lastname, u.firstname",
            $params, 0, 200
        );

        if (empty($userRows)) {
            return [];
        }

        // Mapa base de usuarios
        $users = [];
        foreach ($userRows as $u) {
            $users[(int)$u->id] = [
                'id'        => (int)$u->id,
                'firstname' => $u->firstname,
                'lastname'  => $u->lastname,
                'email'     => $u->email,
                'username'  => $u->username,
                'lastlogin' => (int)$u->lastlogin,
                'suspended' => (bool)$u->suspended,
                'role'      => '',
                'cursos'    => [],
                'grupos'    => [],
                'colegios'  => [],
            ];
        }

        // Query 2 — cursos, grupos y colegios para esos usuarios
        [$inSql, $inParams] = $DB->get_in_or_equal(array_keys($users), SQL_PARAMS_NAMED, 'uid');
        $enrichParams = array_merge(
            $inParams,
            ['catid' => $catId, 'catpath' => $catPath, 'orgid' => $orgId]
        );

        $enrichRows = $DB->get_records_sql(
            "SELECT u.id          AS user_id,
                    c.id          AS course_id,  c.fullname AS course_name,
                    g.id          AS group_id,   g.name     AS group_name,
                    ctc.id        AS colegio_id, ctc.name   AS colegio_name,
                    r.shortname   AS role_shortname
               FROM {user} u
               JOIN {user_enrolments}  ue  ON ue.userid = u.id AND ue.status = 0
               JOIN {enrol}             e  ON e.id = ue.enrolid AND e.status = 0
               JOIN {course}            c  ON c.id = e.courseid
               JOIN {course_categories} cc ON cc.id = c.category
               LEFT JOIN {groups_members} gm  ON gm.userid = u.id
               LEFT JOIN {groups}         g   ON g.id = gm.groupid AND g.courseid = c.id
               LEFT JOIN {ct_group}       ctg ON ctg.moodle_group_id = g.id
                                              AND ctg.organization_id = :orgid
               LEFT JOIN {ct_colegio}     ctc ON ctc.id = ctg.colegio_id
               LEFT JOIN {context}          ctx ON ctx.instanceid = c.id AND ctx.contextlevel = 50
               LEFT JOIN {role_assignments} ra  ON ra.userid = u.id AND ra.contextid = ctx.id
               LEFT JOIN {role}             r   ON r.id = ra.roleid
                                              AND r.shortname IN ('student', 'teacher', 'editingteacher')
              WHERE u.id {$inSql}
                AND (cc.id = :catid OR cc.path LIKE :catpath)
           ORDER BY u.id, c.fullname, g.name",
            $enrichParams
        );

        // Agregar cursos, grupos y colegios (deduplicados por id)
        $seen = [];
        foreach ($enrichRows as $row) {
            $uid = (int)$row->user_id;
            if (!isset($users[$uid])) continue;

            if ($row->course_id) {
                $key = "c{$row->course_id}";
                if (empty($seen[$uid][$key])) {
                    $users[$uid]['cursos'][] = ['id' => (int)$row->course_id, 'name' => $row->course_name];
                    $seen[$uid][$key] = true;
                }
            }
            if ($row->group_id) {
                $key = "g{$row->group_id}";
                if (empty($seen[$uid][$key])) {
                    // El nombre Moodle es "{colegio} - {grupo}"; extraemos solo la parte del grupo
                    $groupName = $row->group_name;
                    if (($pos = strpos($groupName, ' - ')) !== false) {
                        $groupName = substr($groupName, $pos + 3);
                    }
                    $users[$uid]['grupos'][] = ['id' => (int)$row->group_id, 'name' => $groupName];
                    $seen[$uid][$key] = true;
                }
            }
            if ($row->colegio_id) {
                $key = "col{$row->colegio_id}";
                if (empty($seen[$uid][$key])) {
                    $users[$uid]['colegios'][] = ['id' => (int)$row->colegio_id, 'name' => $row->colegio_name];
                    $seen[$uid][$key] = true;
                }
            }
            // Rol: teacher/editingteacher tiene precedencia sobre student
            if (!empty($row->role_shortname)) {
                $current = $users[$uid]['role'];
                $incoming = $row->role_shortname === 'editingteacher' ? 'teacher' : $row->role_shortname;
                if ($current === '' || ($current === 'student' && $incoming === 'teacher')) {
                    $users[$uid]['role'] = $incoming;
                }
            }
        }

        return array_values($users);
    }

    /**
     * Edita nombre, apellido y email de un usuario de la organización.
     *
     * @throws Exception Si el usuario no pertenece a la org, es admin, o los datos son inválidos.
     */
    public function editarPerfil(array $ctGestor, int $userId,
                                  string $firstname, string $lastname, string $email): void
    {
        global $CFG, $DB;

        if ($firstname === '') throw new Exception('El nombre no puede estar vacío.');
        if ($lastname  === '') throw new Exception('El apellido no puede estar vacío.');
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) throw new Exception('El email no es válido.');
        if ($userId <= 1) throw new Exception('Usuario no válido.');
        if (is_siteadmin($userId)) throw new Exception('No se puede modificar este usuario.');

        $catId   = (int)$ctGestor['moodle_category_id'];
        $catPath = '%/' . $catId . '/%';
        $pertenece = $DB->record_exists_sql(
            "SELECT 1
               FROM {user_enrolments} ue
               JOIN {enrol}            e  ON e.id = ue.enrolid AND e.status = 0
               JOIN {course}           c  ON c.id = e.courseid
               JOIN {course_categories} cc ON cc.id = c.category
              WHERE ue.userid = :uid AND ue.status = 0
                AND (cc.id = :catid OR cc.path LIKE :catpath)",
            ['uid' => $userId, 'catid' => $catId, 'catpath' => $catPath]
        );
        if (!$pertenece) throw new Exception('El usuario no pertenece a tu organización.');

        require_once($CFG->dirroot . '/user/lib.php');
        user_update_user(
            ['id' => $userId, 'firstname' => $firstname, 'lastname' => $lastname, 'email' => $email],
            false, false
        );
    }

    /**
     * Restablece la contraseña de un usuario, verificando que pertenece a la org del gestor.
     *
     * @throws Exception Si el usuario no pertenece a la org, es admin, o la contraseña es inválida.
     */
    public function resetearPassword(array $ctGestor, int $userId, string $newPassword): void
    {
        global $CFG, $DB;

        if (strlen($newPassword) < 8) {
            throw new Exception('La contraseña debe tener al menos 8 caracteres.');
        }
        if ($userId <= 1) {
            throw new Exception('Usuario no válido.');
        }
        if (is_siteadmin($userId)) {
            throw new Exception('No se puede modificar este usuario.');
        }

        $catId   = (int)$ctGestor['moodle_category_id'];
        $catPath = '%/' . $catId . '/%';
        $pertenece = $DB->record_exists_sql(
            "SELECT 1
               FROM {user_enrolments} ue
               JOIN {enrol}            e  ON e.id = ue.enrolid AND e.status = 0
               JOIN {course}           c  ON c.id = e.courseid
               JOIN {course_categories} cc ON cc.id = c.category
              WHERE ue.userid = :uid
                AND ue.status = 0
                AND (cc.id = :catid OR cc.path LIKE :catpath)",
            ['uid' => $userId, 'catid' => $catId, 'catpath' => $catPath]
        );

        if (!$pertenece) {
            throw new Exception('El usuario no pertenece a tu organización.');
        }

        require_once($CFG->dirroot . '/user/lib.php');
        user_update_user(['id' => $userId, 'password' => $newPassword], true, false);
    }
}
