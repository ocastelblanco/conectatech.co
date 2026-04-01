<?php
/**
 * ActivacionService.php
 * Lógica de negocio para la activación pública de pines:
 *   - Resolver un pin por hash (gestor o regular).
 *   - Crear cuenta de gestor y asignarle rol en la organización.
 *   - Matricular un usuario en el curso asociado a un pin.
 */

class ActivacionService
{
    // =========================================================================
    // Resolución de pin
    // =========================================================================

    /**
     * Busca un pin por hash en ct_gestor_pin y ct_pin, y retorna sus datos
     * junto con el tipo ('gestor' o 'regular').
     *
     * @throws InvalidArgumentException Si el pin no existe, ya fue usado o
     *         no está en estado activable.
     */
    public function resolvePin(string $hash): array
    {
        global $DB;

        // ── 1. Buscar en ct_gestor_pin ────────────────────────────────────────
        $gestorPin = $DB->get_record('ct_gestor_pin', ['hash' => $hash]);

        if ($gestorPin) {
            if ($gestorPin->status === 'used') {
                throw new InvalidArgumentException('Este pin de gestor ya fue utilizado.');
            }

            // status === 'pending' → disponible para activar
            $org = $DB->get_record('ct_organization', ['id' => $gestorPin->organization_id], '*', MUST_EXIST);

            return [
                'type' => 'gestor',
                'pin'  => [
                    'id'              => (int)$gestorPin->id,
                    'hash'            => $gestorPin->hash,
                    'status'          => $gestorPin->status,
                    'organization_id' => (int)$gestorPin->organization_id,
                    'org_name'        => $org->name,
                    'created_at'      => (int)$gestorPin->created_at,
                ],
            ];
        }

        // ── 2. Buscar en ct_pin ───────────────────────────────────────────────
        $sql = "SELECT p.id, p.hash, p.role, p.status, p.group_id,
                       p.moodle_course_id, p.activated_by, p.activated_at,
                       pkg.id         AS package_id,
                       pkg.expires_at AS expires_at,
                       c.fullname     AS course_name,
                       g.name         AS group_name,
                       o.name         AS org_name
                FROM {ct_pin} p
                JOIN {ct_pin_package} pkg ON pkg.id = p.package_id
                JOIN {ct_organization} o  ON o.id  = pkg.organization_id
                LEFT JOIN {course}    c   ON c.id  = p.moodle_course_id
                LEFT JOIN {ct_group}  g   ON g.id  = p.group_id
                WHERE p.hash = :hash";

        $pin = $DB->get_record_sql($sql, ['hash' => $hash]);

        if (!$pin) {
            throw new InvalidArgumentException('Pin no encontrado.');
        }

        if ($pin->status === 'available') {
            throw new InvalidArgumentException('Este pin no ha sido asignado todavía.');
        }

        if ($pin->status === 'active' && (int)$pin->expires_at > time()) {
            throw new InvalidArgumentException('Este pin ya está activo.');
        }

        // status === 'assigned', o 'active' con vigencia vencida (reactivación)
        return [
            'type' => 'regular',
            'pin'  => [
                'id'          => (int)$pin->id,
                'hash'        => $pin->hash,
                'role'        => $pin->role,
                'status'      => $pin->status,
                'expires_at'  => (int)$pin->expires_at,
                'group_name'  => $pin->group_name,
                'course_id'   => $pin->moodle_course_id ? (int)$pin->moodle_course_id : null,
                'course_name' => $pin->course_name,
                'org_name'    => $pin->org_name,
            ],
        ];
    }

    // =========================================================================
    // Activación de pin de gestor
    // =========================================================================

    /**
     * Crea la cuenta Moodle del gestor, le asigna rol de teacher en la
     * categoría de la organización e inserta el registro en ct_gestor.
     *
     * @param string $hash   Hash del ct_gestor_pin.
     * @param array  $datos  Claves: firstname, lastname, email, username, password.
     * @throws InvalidArgumentException Si el pin no está pendiente o los datos son inválidos.
     */
    public function activarGestor(string $hash, array $datos): array
    {
        global $DB, $CFG;

        $pin = $DB->get_record('ct_gestor_pin', ['hash' => $hash], '*', MUST_EXIST);

        if ($pin->status !== 'pending') {
            throw new InvalidArgumentException('Este pin de gestor no está en estado pendiente.');
        }

        // Verificar unicidad de username y email
        if ($DB->record_exists('user', ['username' => $datos['username']])) {
            throw new InvalidArgumentException("El nombre de usuario '{$datos['username']}' ya está en uso.");
        }
        if ($DB->record_exists('user', ['email' => $datos['email']])) {
            throw new InvalidArgumentException("El correo electrónico '{$datos['email']}' ya está registrado.");
        }

        // Crear el usuario en Moodle
        require_once $CFG->dirroot . '/user/lib.php';

        $userId = user_create_user((object)[
            'username'   => $datos['username'],
            'password'   => $datos['password'],
            'firstname'  => $datos['firstname'],
            'lastname'   => $datos['lastname'],
            'email'      => $datos['email'],
            'auth'       => 'manual',
            'confirmed'  => 1,
            'mnethostid' => $CFG->mnet_localhost_id,
        ], true, false);

        // Asignar rol teacher a nivel de categoría (herencia de contexto)
        $teacherRoleId = $DB->get_field('role', 'id', ['shortname' => 'teacher'], MUST_EXIST);
        $org    = $DB->get_record('ct_organization', ['id' => $pin->organization_id], '*', MUST_EXIST);
        $catCtx = context_coursecat::instance($org->moodle_category_id);
        role_assign($teacherRoleId, $userId, $catCtx->id);

        // Matricular explícitamente como teacher en TODOS los cursos del árbol
        // de la categoría (incluyendo subcategorías recursivamente).
        $enrol      = enrol_get_plugin('manual');
        $courseIds  = $this->getAllCourseIdsInCategory((int)$org->moodle_category_id);
        foreach ($courseIds as $courseId) {
            $instance = $DB->get_record('enrol', [
                'courseid' => $courseId,
                'enrol'    => 'manual',
                'status'   => ENROL_INSTANCE_ENABLED,
            ]);
            if ($instance) {
                $enrol->enrol_user($instance, $userId, $teacherRoleId);
            }
        }

        // Insertar en ct_gestor
        $gestorId = $DB->insert_record('ct_gestor', (object)[
            'organization_id' => (int)$pin->organization_id,
            'moodle_userid'   => $userId,
            'created_at'      => time(),
        ]);

        // Marcar el pin como usado y vincularlo al gestor creado
        $DB->update_record('ct_gestor_pin', (object)[
            'id'        => $pin->id,
            'status'    => 'used',
            'used_at'   => time(),
            'gestor_id' => $gestorId,
        ]);

        return [
            'ok'       => true,
            'user_id'  => $userId,
            'username' => $datos['username'],
        ];
    }

    // =========================================================================
    // Activación de pin regular
    // =========================================================================

    /**
     * Matricula al usuario en el curso del pin, lo añade al grupo y actualiza
     * el estado del pin a 'active'.
     *
     * @param string $hash          Hash del ct_pin.
     * @param int    $moodleUserId  ID del usuario Moodle ya autenticado.
     * @throws InvalidArgumentException Si el pin no es activable o faltan datos de asignación.
     */
    public function activarPin(string $hash, int $moodleUserId): array
    {
        global $DB, $CFG;

        $pin = $DB->get_record('ct_pin', ['hash' => $hash], '*', MUST_EXIST);

        // Verificar que el pin es activable
        $activable = ($pin->status === 'assigned')
            || ($pin->status === 'active' && (int)$pin->expires_at <= time());

        if (!$activable) {
            throw new InvalidArgumentException('Este pin no está en un estado activable.');
        }

        if (empty($pin->moodle_course_id) || empty($pin->group_id)) {
            throw new InvalidArgumentException('El pin no tiene curso ni grupo asignados.');
        }

        // Crear el grupo en Moodle si aún no existe
        $ctGroup = $DB->get_record('ct_group', ['id' => $pin->group_id], '*', MUST_EXIST);

        if (empty($ctGroup->moodle_group_id)) {
            require_once $CFG->dirroot . '/group/lib.php';

            $moodleGroupId = groups_create_group((object)[
                'courseid' => $pin->moodle_course_id,
                'name'     => $ctGroup->name,
            ]);

            $DB->update_record('ct_group', (object)[
                'id'              => $ctGroup->id,
                'moodle_group_id' => $moodleGroupId,
            ]);

            $ctGroup->moodle_group_id = $moodleGroupId;
        }

        $moodleGroupId = (int)$ctGroup->moodle_group_id;

        // Resolver el rol de Moodle según el shortname del pin
        $roleId = $DB->get_field('role', 'id', ['shortname' => $pin->role], MUST_EXIST);

        // Matricular al usuario con enrol_manual
        $pkg      = $DB->get_record('ct_pin_package', ['id' => $pin->package_id], '*', MUST_EXIST);
        $enrol    = enrol_get_plugin('manual');
        $instance = $DB->get_record('enrol', [
            'courseid' => $pin->moodle_course_id,
            'enrol'    => 'manual',
            'status'   => ENROL_INSTANCE_ENABLED,
        ], '*', MUST_EXIST);

        $enrol->enrol_user($instance, $moodleUserId, $roleId, 0, $pkg->expires_at);

        // Añadir al grupo
        if (!function_exists('groups_add_member')) {
            require_once $CFG->dirroot . '/group/lib.php';
        }
        groups_add_member($moodleGroupId, $moodleUserId);

        // Actualizar el estado del pin
        $DB->update_record('ct_pin', (object)[
            'id'           => $pin->id,
            'status'       => 'active',
            'activated_by' => $moodleUserId,
            'activated_at' => time(),
        ]);

        return [
            'ok'         => true,
            'course_id'  => (int)$pin->moodle_course_id,
            'course_url' => $CFG->wwwroot . '/course/view.php?id=' . $pin->moodle_course_id,
            'group_name' => $ctGroup->name,
            'expires_at' => (int)$pkg->expires_at,
        ];
    }

    // =========================================================================
    // Helpers privados
    // =========================================================================

    /**
     * Devuelve recursivamente los IDs de todos los cursos que pertenecen a
     * $categoryId o a cualquiera de sus subcategorías (a cualquier profundidad).
     * Excluye el sitio raíz (id = SITEID).
     */
    private function getAllCourseIdsInCategory(int $categoryId): array
    {
        global $DB;

        $ids = [];

        // Cursos directamente en esta categoría
        $courses = $DB->get_records('course', ['category' => $categoryId], '', 'id');
        foreach ($courses as $course) {
            if ((int)$course->id !== SITEID) {
                $ids[] = (int)$course->id;
            }
        }

        // Subcategorías → recursión
        $subcats = $DB->get_records('course_categories', ['parent' => $categoryId], '', 'id');
        foreach ($subcats as $subcat) {
            $ids = array_merge($ids, $this->getAllCourseIdsInCategory((int)$subcat->id));
        }

        return $ids;
    }
}
