<?php
/**
 * ActivacionService.php
 * Lógica central para la activación pública de pines.
 *
 * Tres operaciones principales:
 *
 *   resolvePin()     — Identifica el pin (gestor o regular) y retorna su info
 *                      sin ejecutar ningún cambio.
 *
 *   activarGestor()  — Crea una cuenta Moodle de gestor usando un pin-gestor,
 *                      asigna el rol 'teacher' a nivel de categoría y registra
 *                      el gestor en ct_gestor.
 *
 *   activarPin()     — Activa un pin de acceso a curso. Crea el grupo en Moodle
 *                      si no existe, matricula al usuario con timeend = expires_at
 *                      y lo añade al grupo. Soporta creación de cuenta nueva o
 *                      autenticación de cuenta existente en el mismo paso.
 */

class ActivacionService
{
    // =========================================================================
    // Resolver pin
    // =========================================================================

    /**
     * Identifica el tipo de pin y retorna su información pública.
     * No modifica ningún dato.
     *
     * Retorna:
     *   Para pin-gestor:
     *     ['type' => 'gestor', 'organization_name' => '...']
     *
     *   Para pin de acceso:
     *     ['type' => 'pin', 'role' => '...', 'expires_at' => N,
     *      'course_name' => '...', 'group_name' => '...', 'reuse' => bool]
     *
     * @throws InvalidArgumentException  Si el hash no existe o tiene formato inválido.
     * @throws RuntimeException          Si el pin no está disponible para activación.
     */
    public function resolvePin(string $hash): array
    {
        global $DB;

        $hash = strtolower(trim($hash));

        if (strlen($hash) !== 32 || !ctype_xdigit($hash)) {
            throw new InvalidArgumentException('El hash del pin no tiene el formato correcto (32 caracteres hexadecimales).');
        }

        // ── Pin de gestor ────────────────────────────────────────────────────
        $gestorPin = $DB->get_record('ct_gestor_pin', ['hash' => $hash]);
        if ($gestorPin) {
            if ($gestorPin->status === 'used') {
                throw new RuntimeException('Este pin de gestor ya fue utilizado.');
            }
            $org = $DB->get_record('ct_organization', ['id' => $gestorPin->organization_id], '*', MUST_EXIST);
            return [
                'type'              => 'gestor',
                'organization_name' => $org->name,
            ];
        }

        // ── Pin de acceso ────────────────────────────────────────────────────
        $pin = $DB->get_record_sql(
            "SELECT p.id, p.role, p.status, p.group_id, p.moodle_course_id,
                    pkg.expires_at,
                    c.fullname AS course_name,
                    g.name     AS group_name
             FROM {ct_pin} p
             JOIN {ct_pin_package} pkg ON pkg.id = p.package_id
             LEFT JOIN {course} c   ON c.id  = p.moodle_course_id
             LEFT JOIN {ct_group} g ON g.id  = p.group_id
             WHERE p.hash = ?",
            [$hash]
        );

        if (!$pin) {
            throw new InvalidArgumentException('Pin no encontrado. Verifica que lo hayas escrito correctamente.');
        }

        if ($pin->status === 'available') {
            throw new RuntimeException('Este pin aún no ha sido asignado a un curso. Contacta a tu gestor.');
        }

        if ($pin->status === 'active' && (int)$pin->expires_at > time()) {
            throw new RuntimeException('Este pin ya está activo y su vigencia no ha vencido aún.');
        }

        return [
            'type'        => 'pin',
            'role'        => $pin->role,
            'expires_at'  => (int)$pin->expires_at,
            'course_name' => $pin->course_name,
            'group_name'  => $pin->group_name,
            'reuse'       => $pin->status === 'active',   // true = reactivación tras vencimiento
        ];
    }

    // =========================================================================
    // Activar pin de gestor
    // =========================================================================

    /**
     * Crea la cuenta Moodle del gestor a partir de un pin-gestor pendiente.
     *
     * Pasos:
     *   1. Valida el hash y los datos del formulario.
     *   2. Crea el usuario en Moodle con auth 'manual'.
     *   3. Asigna rol 'teacher' a nivel de categoría (visibilidad sin edición).
     *   4. Registra al usuario en ct_gestor.
     *   5. Marca el pin-gestor como 'used'.
     *
     * @param array $datos [firstname, lastname, email, username, password]
     */
    public function activarGestor(string $hash, array $datos): array
    {
        global $DB, $CFG;

        $gestorPin = $DB->get_record('ct_gestor_pin', ['hash' => strtolower(trim($hash))], '*', MUST_EXIST);
        if ($gestorPin->status !== 'pending') {
            throw new RuntimeException('Este pin de gestor ya fue utilizado.');
        }

        $userId = $this->crearUsuarioMoodle($datos);

        // Rol 'teacher' a nivel de categoría: el gestor ve los cursos sin poder editarlos
        $org    = $DB->get_record('ct_organization', ['id' => $gestorPin->organization_id], '*', MUST_EXIST);
        $ctx    = context_coursecat::instance((int)$org->moodle_category_id);
        $roleId = $DB->get_field('role', 'id', ['shortname' => 'teacher'], MUST_EXIST);
        role_assign($roleId, $userId, $ctx->id);

        $DB->insert_record('ct_gestor', (object)[
            'organization_id' => $gestorPin->organization_id,
            'moodle_userid'   => $userId,
            'created_at'      => time(),
        ]);

        $gestorPin->status  = 'used';
        $gestorPin->used_at = time();
        $DB->update_record('ct_gestor_pin', $gestorPin);

        return [
            'user_id'   => (int)$userId,
            'username'  => clean_param(trim($datos['username']), PARAM_USERNAME),
            'firstname' => trim($datos['firstname']),
            'lastname'  => trim($datos['lastname']),
        ];
    }

    // =========================================================================
    // Activar pin de acceso
    // =========================================================================

    /**
     * Activa un pin de acceso a curso para un usuario.
     *
     * El usuario puede ser nuevo (crear cuenta) o existente (autenticar).
     * Si el campo 'firstname' está presente se asume cuenta nueva; en caso
     * contrario se autentica la cuenta con username + password.
     *
     * Pasos de la activación:
     *   1. Verifica el hash y el estado del pin.
     *   2. Obtiene o crea el usuario Moodle.
     *   3. Crea el grupo Moodle en el curso si ct_group.moodle_group_id es null.
     *   4. Matricula el usuario con timeend = expires_at.
     *   5. Añade el usuario al grupo.
     *   6. Marca el pin como 'active'.
     *
     * @param array $datos [username, password, firstname?, lastname?, email?]
     */
    public function activarPin(string $hash, array $datos): array
    {
        global $DB, $CFG;

        $hash = strtolower(trim($hash));

        $pin = $DB->get_record_sql(
            "SELECT p.id, p.role, p.status, p.group_id, p.moodle_course_id,
                    pkg.expires_at
             FROM {ct_pin} p
             JOIN {ct_pin_package} pkg ON pkg.id = p.package_id
             WHERE p.hash = ?",
            [$hash],
            MUST_EXIST
        );

        if ($pin->status === 'available') {
            throw new RuntimeException('Este pin aún no ha sido asignado a un curso.');
        }
        if ($pin->status === 'active' && (int)$pin->expires_at > time()) {
            throw new RuntimeException('Este pin ya está activo y su vigencia no ha vencido.');
        }
        if (!$pin->moodle_course_id || !$pin->group_id) {
            throw new RuntimeException('Este pin no tiene curso o grupo asignado. Contacta a tu gestor.');
        }

        // Obtener o crear usuario
        $nuevoUsuario = !empty(trim($datos['firstname'] ?? ''));
        if ($nuevoUsuario) {
            $userId = $this->crearUsuarioMoodle($datos);
        } else {
            $userId = $this->autenticarUsuario(
                trim($datos['username'] ?? ''),
                $datos['password'] ?? ''
            );
        }

        // Crear grupo Moodle si aún no existe
        $grupo = $DB->get_record('ct_group', ['id' => $pin->group_id], '*', MUST_EXIST);
        if (!$grupo->moodle_group_id) {
            $moodleGroupId = $this->crearGrupoMoodle($grupo->name, (int)$pin->moodle_course_id);
            $DB->update_record('ct_group', (object)['id' => $grupo->id, 'moodle_group_id' => $moodleGroupId]);
            $grupo->moodle_group_id = $moodleGroupId;
        }

        // Matricular con fecha de fin = expires_at del paquete
        $this->matricularUsuario($userId, (int)$pin->moodle_course_id, $pin->role, (int)$pin->expires_at);

        // Añadir al grupo
        require_once($CFG->dirroot . '/group/lib.php');
        groups_add_member((int)$grupo->moodle_group_id, $userId);

        // Marcar pin como activo
        $DB->update_record('ct_pin', (object)[
            'id'           => $pin->id,
            'status'       => 'active',
            'activated_by' => $userId,
            'activated_at' => time(),
        ]);

        $course = $DB->get_record('course', ['id' => $pin->moodle_course_id], 'id, fullname', MUST_EXIST);

        return [
            'course_name' => $course->fullname,
            'role'        => $pin->role,
            'expires_at'  => (int)$pin->expires_at,
        ];
    }

    // =========================================================================
    // Verificar credenciales (sin activar nada)
    // =========================================================================

    /**
     * Verifica las credenciales de un usuario Moodle existente.
     * Usada por el frontend para el paso de "¿ya tienes cuenta?" antes de activar.
     */
    public function verificarCredenciales(string $username, string $password): array
    {
        global $DB, $CFG;

        $username = trim($username);
        if (!$username || !$password) {
            throw new InvalidArgumentException('Usuario y contraseña son obligatorios.');
        }

        $user = $DB->get_record('user', [
            'username'   => $username,
            'mnethostid' => $CFG->mnet_localhost_id,
            'deleted'    => 0,
            'suspended'  => 0,
        ]);

        if (!$user || !validate_internal_user_password($user, $password)) {
            throw new RuntimeException('Usuario o contraseña incorrectos.');
        }

        return [
            'id'        => (int)$user->id,
            'username'  => $user->username,
            'firstname' => $user->firstname,
            'lastname'  => $user->lastname,
            'email'     => $user->email,
        ];
    }

    // =========================================================================
    // Helpers privados
    // =========================================================================

    /**
     * Crea un usuario Moodle con autenticación manual.
     * Valida que username y email no existan antes de insertar.
     *
     * @param array $datos [firstname, lastname, email, username, password]
     * @return int  ID del usuario creado
     */
    private function crearUsuarioMoodle(array $datos): int
    {
        global $DB, $CFG;
        require_once($CFG->dirroot . '/user/lib.php');

        $username  = clean_param(trim($datos['username']  ?? ''), PARAM_USERNAME);
        $email     = clean_param(trim($datos['email']     ?? ''), PARAM_EMAIL);
        $firstname = trim($datos['firstname'] ?? '');
        $lastname  = trim($datos['lastname']  ?? '');
        $password  = $datos['password'] ?? '';

        foreach (['firstname' => $firstname, 'lastname' => $lastname, 'email' => $email,
                  'username'  => $username,  'password' => $password] as $field => $value) {
            if ($value === '') {
                throw new InvalidArgumentException("El campo {$field} es obligatorio.");
            }
        }

        if (!validate_email($email)) {
            throw new InvalidArgumentException("El correo '{$email}' no es válido.");
        }

        if ($DB->record_exists('user', ['username' => $username, 'mnethostid' => $CFG->mnet_localhost_id])) {
            throw new InvalidArgumentException("El nombre de usuario '{$username}' ya está en uso.");
        }

        if ($DB->record_exists('user', ['email' => $email, 'mnethostid' => $CFG->mnet_localhost_id, 'deleted' => 0])) {
            throw new InvalidArgumentException("El correo '{$email}' ya está registrado.");
        }

        return (int)user_create_user((object)[
            'auth'       => 'manual',
            'username'   => $username,
            'password'   => $password,
            'firstname'  => $firstname,
            'lastname'   => $lastname,
            'email'      => $email,
            'confirmed'  => 1,
            'mnethostid' => $CFG->mnet_localhost_id,
            'lang'       => 'es',
            'country'    => 'CO',
        ]);
    }

    /**
     * Autentica un usuario Moodle existente y retorna su ID.
     */
    private function autenticarUsuario(string $username, string $password): int
    {
        global $DB, $CFG;

        if (!$username || !$password) {
            throw new InvalidArgumentException('Usuario y contraseña son obligatorios.');
        }

        $user = $DB->get_record('user', [
            'username'   => $username,
            'mnethostid' => $CFG->mnet_localhost_id,
            'deleted'    => 0,
            'suspended'  => 0,
        ]);

        if (!$user || !validate_internal_user_password($user, $password)) {
            throw new RuntimeException('Usuario o contraseña incorrectos.');
        }

        return (int)$user->id;
    }

    /**
     * Crea un grupo Moodle en el curso indicado y retorna su ID.
     */
    private function crearGrupoMoodle(string $nombre, int $courseId): int
    {
        global $CFG;
        require_once($CFG->dirroot . '/group/lib.php');

        return (int)groups_create_group((object)[
            'courseid' => $courseId,
            'name'     => $nombre,
        ]);
    }

    /**
     * Matricula al usuario en el curso usando la instancia de enrolamiento manual.
     * Si no existe instancia manual, la crea. Si el usuario ya está matriculado,
     * actualiza el timeend.
     */
    private function matricularUsuario(int $userId, int $courseId, string $roleShortname, int $timeEnd): void
    {
        global $DB;

        $course  = $DB->get_record('course', ['id' => $courseId], '*', MUST_EXIST);
        $roleId  = $DB->get_field('role', 'id', ['shortname' => $roleShortname], MUST_EXIST);
        $plugin  = enrol_get_plugin('manual');

        // Buscar instancia de enrolamiento manual en el curso
        $instance = null;
        foreach (enrol_get_instances($courseId, true) as $i) {
            if ($i->enrol === 'manual') {
                $instance = $i;
                break;
            }
        }

        if (!$instance) {
            $instanceId = $plugin->add_default_instance($course);
            $instance   = $DB->get_record('enrol', ['id' => $instanceId], '*', MUST_EXIST);
        }

        // enrol_user actualiza la matrícula si el usuario ya estaba inscrito
        $plugin->enrol_user($instance, $userId, $roleId, 0, $timeEnd);
    }
}
