<?php
/**
 * OrganizacionService.php
 * CRUD de organizaciones, gestores y pines de gestor.
 */

class OrganizacionService
{
    // =========================================================================
    // Organizaciones
    // =========================================================================

    /**
     * Lista todas las organizaciones con nombre de categoría Moodle,
     * número de gestores y conteo de pines por estado.
     */
    public function listar(): array
    {
        global $DB;

        $orgs   = $DB->get_records('ct_organization', null, 'name ASC');
        $result = [];

        foreach ($orgs as $org) {
            $category = $DB->get_record('course_categories', ['id' => $org->moodle_category_id]);

            $result[] = [
                'id'                 => (int)$org->id,
                'name'               => $org->name,
                'moodle_category_id' => (int)$org->moodle_category_id,
                'category_name'      => $category ? $category->name : null,
                'gestor_count'       => $DB->count_records('ct_gestor', ['organization_id' => $org->id]),
                'pins'               => $this->getPinCounts((int)$org->id),
                'created_at'         => (int)$org->created_at,
            ];
        }

        return $result;
    }

    /**
     * Crea una nueva organización.
     */
    public function crear(string $name, int $moodleCategoryId): array
    {
        global $DB;

        $name = trim($name);
        if ($name === '') {
            throw new InvalidArgumentException('El nombre no puede estar vacío.');
        }

        if (!$DB->record_exists('course_categories', ['id' => $moodleCategoryId])) {
            throw new InvalidArgumentException("La categoría Moodle {$moodleCategoryId} no existe.");
        }

        if ($DB->record_exists('ct_organization', ['moodle_category_id' => $moodleCategoryId])) {
            throw new InvalidArgumentException('Esa categoría ya está asignada a otra organización.');
        }

        $id = $DB->insert_record('ct_organization', (object)[
            'name'               => $name,
            'moodle_category_id' => $moodleCategoryId,
            'created_at'         => time(),
        ]);

        return ['id' => (int)$id, 'name' => $name, 'moodle_category_id' => $moodleCategoryId];
    }

    /**
     * Renombra la organización y/o reasigna su categoría Moodle.
     */
    public function actualizar(int $id, ?string $name, ?int $moodleCategoryId): void
    {
        global $DB;

        $org = $DB->get_record('ct_organization', ['id' => $id], '*', MUST_EXIST);

        if ($name !== null) {
            $name = trim($name);
            if ($name === '') {
                throw new InvalidArgumentException('El nombre no puede estar vacío.');
            }
            $org->name = $name;
        }

        if ($moodleCategoryId !== null) {
            if (!$DB->record_exists('course_categories', ['id' => $moodleCategoryId])) {
                throw new InvalidArgumentException("La categoría Moodle {$moodleCategoryId} no existe.");
            }
            $conflict = $DB->get_record('ct_organization', ['moodle_category_id' => $moodleCategoryId]);
            if ($conflict && (int)$conflict->id !== $id) {
                throw new InvalidArgumentException('Esa categoría ya está asignada a otra organización.');
            }
            $org->moodle_category_id = $moodleCategoryId;
        }

        $DB->update_record('ct_organization', $org);
    }

    /**
     * Elimina la organización y TODOS sus datos en cascada:
     * — elimina los usuarios Moodle de cada gestor y sus matrículas
     * — libera los pines de gestor usados
     * — borra todos los registros ct_*
     */
    public function eliminar(int $id): void
    {
        global $DB;

        $org = $DB->get_record('ct_organization', ['id' => $id], '*', MUST_EXIST);

        // Eliminar usuarios Moodle de los gestores activos
        $gestores = $DB->get_records('ct_gestor', ['organization_id' => $id]);
        foreach ($gestores as $gestor) {
            $this->eliminarMoodleGestor((int)$gestor->moodle_userid, (int)$org->moodle_category_id);
        }

        // Borrar pines de todos los paquetes de la organización
        $packages = $DB->get_records('ct_pin_package', ['organization_id' => $id], '', 'id');
        foreach ($packages as $pkg) {
            $DB->delete_records('ct_pin', ['package_id' => $pkg->id]);
        }

        $DB->delete_records('ct_pin_package', ['organization_id' => $id]);
        $DB->delete_records('ct_group',       ['organization_id' => $id]);
        $DB->delete_records('ct_gestor',      ['organization_id' => $id]);
        $DB->delete_records('ct_gestor_pin',  ['organization_id' => $id]);
        $DB->delete_records('ct_organization', ['id' => $id]);
    }

    // =========================================================================
    // Gestores
    // =========================================================================

    /**
     * Lista los gestores activos de una organización con sus datos de usuario Moodle.
     */
    public function listarGestores(int $orgId): array
    {
        global $DB;

        $DB->get_record('ct_organization', ['id' => $orgId], '*', MUST_EXIST);

        $gestores = $DB->get_records('ct_gestor', ['organization_id' => $orgId], 'created_at ASC');
        $result   = [];

        foreach ($gestores as $g) {
            $u = $DB->get_record('user', ['id' => $g->moodle_userid], 'id,firstname,lastname,email,username,deleted');

            $result[] = [
                'id'            => (int)$g->id,
                'moodle_userid' => (int)$g->moodle_userid,
                'firstname'     => $u ? $u->firstname : '',
                'lastname'      => $u ? $u->lastname  : '',
                'email'         => ($u && !$u->deleted) ? $u->email    : '[eliminado]',
                'username'      => ($u && !$u->deleted) ? $u->username : '[eliminado]',
                'deleted'       => $u ? (bool)$u->deleted : true,
                'created_at'    => (int)$g->created_at,
            ];
        }

        return $result;
    }

    /**
     * Elimina un gestor:
     *  — des-matricula al usuario Moodle de todos los cursos de la categoría
     *  — elimina el usuario Moodle (soft-delete)
     *  — libera el pin de gestor que activó este gestor (vuelve a 'pending')
     *  — borra el registro ct_gestor
     */
    public function eliminarGestor(int $gestorId): void
    {
        global $DB;

        $gestor = $DB->get_record('ct_gestor', ['id' => $gestorId], '*', MUST_EXIST);
        $org    = $DB->get_record('ct_organization', ['id' => $gestor->organization_id], '*', MUST_EXIST);

        // Eliminar usuario Moodle + matrículas
        $this->eliminarMoodleGestor((int)$gestor->moodle_userid, (int)$org->moodle_category_id);

        // Liberar el pin de gestor vinculado
        $pin = $DB->get_record('ct_gestor_pin', ['gestor_id' => $gestorId]);
        if ($pin) {
            $DB->update_record('ct_gestor_pin', (object)[
                'id'        => $pin->id,
                'status'    => 'pending',
                'used_at'   => null,
                'gestor_id' => null,
            ]);
        }

        $DB->delete_records('ct_gestor', ['id' => $gestorId]);
    }

    // =========================================================================
    // Pines de gestor (invitaciones)
    // =========================================================================

    /**
     * Genera un pin de gestor (un solo uso) para la organización indicada.
     */
    public function crearGestorPin(int $orgId, int $adminUserId): array
    {
        global $DB;

        $DB->get_record('ct_organization', ['id' => $orgId], '*', MUST_EXIST);

        $hash = $this->generateUniqueHash('ct_gestor_pin');

        $id = $DB->insert_record('ct_gestor_pin', (object)[
            'organization_id' => $orgId,
            'hash'            => $hash,
            'status'          => 'pending',
            'created_by'      => $adminUserId,
            'created_at'      => time(),
            'used_at'         => null,
            'gestor_id'       => null,
        ]);

        return ['id' => (int)$id, 'hash' => $hash, 'status' => 'pending'];
    }

    /**
     * Anula un pin de gestor pendiente (solo si aún no fue usado).
     */
    public function anularGestorPin(string $hash): void
    {
        global $DB;

        $pin = $DB->get_record('ct_gestor_pin', ['hash' => $hash], '*', MUST_EXIST);

        if ($pin->status !== 'pending') {
            throw new InvalidArgumentException('Solo se pueden anular pines con estado pending.');
        }

        $DB->delete_records('ct_gestor_pin', ['hash' => $hash]);
    }

    /**
     * Lista los pines de gestor (invitaciones) de una organización.
     */
    public function listarGestorPines(int $orgId): array
    {
        global $DB;

        $pins   = $DB->get_records('ct_gestor_pin', ['organization_id' => $orgId], 'created_at DESC');
        $result = [];

        foreach ($pins as $pin) {
            $result[] = [
                'id'         => (int)$pin->id,
                'hash'       => $pin->hash,
                'status'     => $pin->status,
                'gestor_id'  => $pin->gestor_id ? (int)$pin->gestor_id : null,
                'created_at' => (int)$pin->created_at,
                'used_at'    => $pin->used_at ? (int)$pin->used_at : null,
            ];
        }

        return $result;
    }

    // =========================================================================
    // Helpers privados
    // =========================================================================

    /**
     * Soft-deletes el usuario Moodle, revoca su rol de teacher en la categoría
     * y lo des-matricula de todos los cursos del árbol de la categoría.
     * Si el usuario ya fue eliminado antes, no hace nada.
     */
    private function eliminarMoodleGestor(int $moodleUserId, int $moodleCategoryId): void
    {
        global $DB, $CFG;

        $user = $DB->get_record('user', ['id' => $moodleUserId]);
        if (!$user || $user->deleted) {
            return;
        }

        // Des-matricular de todos los cursos del árbol de la categoría
        $enrol     = enrol_get_plugin('manual');
        $courseIds = $this->getAllCourseIdsInCategory($moodleCategoryId);
        foreach ($courseIds as $courseId) {
            $instance = $DB->get_record('enrol', [
                'courseid' => $courseId,
                'enrol'    => 'manual',
                'status'   => ENROL_INSTANCE_ENABLED,
            ]);
            if ($instance) {
                $enrol->unenrol_user($instance, $moodleUserId);
            }
        }

        // Revocar rol ct_gestor en el contexto de la categoría
        $catCtx = context_coursecat::instance($moodleCategoryId);
        $gestorRoleId = $DB->get_field('role', 'id', ['shortname' => 'ct_gestor']);
        if ($gestorRoleId) {
            role_unassign($gestorRoleId, $moodleUserId, $catCtx->id);
        }

        // Soft-delete del usuario Moodle
        require_once $CFG->dirroot . '/user/lib.php';
        delete_user($user);
    }

    /**
     * Devuelve recursivamente los IDs de todos los cursos dentro de
     * $categoryId y sus subcategorías (a cualquier profundidad).
     */
    private function getAllCourseIdsInCategory(int $categoryId): array
    {
        global $DB;

        $ids = [];

        $courses = $DB->get_records('course', ['category' => $categoryId], '', 'id');
        foreach ($courses as $course) {
            if ((int)$course->id !== SITEID) {
                $ids[] = (int)$course->id;
            }
        }

        $subcats = $DB->get_records('course_categories', ['parent' => $categoryId], '', 'id');
        foreach ($subcats as $subcat) {
            $ids = array_merge($ids, $this->getAllCourseIdsInCategory((int)$subcat->id));
        }

        return $ids;
    }

    /**
     * Cuenta los pines de una organización agrupados por estado.
     */
    private function getPinCounts(int $orgId): array
    {
        global $DB;

        $rows = $DB->get_records_sql(
            "SELECT p.status, COUNT(*) AS total
             FROM {ct_pin} p
             JOIN {ct_pin_package} pkg ON pkg.id = p.package_id
             WHERE pkg.organization_id = ?
             GROUP BY p.status",
            [$orgId]
        );

        $counts = ['available' => 0, 'assigned' => 0, 'active' => 0];
        foreach ($rows as $row) {
            if (array_key_exists($row->status, $counts)) {
                $counts[$row->status] = (int)$row->total;
            }
        }

        return $counts;
    }

    /**
     * Genera un hash de 32 caracteres hexadecimales único en la tabla indicada.
     */
    private function generateUniqueHash(string $table): string
    {
        global $DB;

        do {
            $hash = bin2hex(random_bytes(16));
        } while ($DB->record_exists($table, ['hash' => $hash]));

        return $hash;
    }
}
