<?php
/**
 * MatriculasService.php — Lógica de negocio para usuarios y matrículas en Moodle.
 *
 * Gestiona el ciclo completo de un usuario: verificar si existe, crear o actualizar
 * su perfil, resolver los cursos que le corresponden según institución/rol/grado,
 * y matricularlo con el rol Moodle correcto vía enrol_manual.
 *
 * La operación es idempotente: ejecutarla dos veces produce el mismo estado final
 * sin duplicar usuarios ni matrículas.
 *
 * Uso típico:
 *   $service = new MatriculasService();
 *   $result  = $service->matricularUsuario($row, $dryRun);
 */

class MatriculasService
{
    // ─────────────────────────────────────────────────────────────────────────
    // Operación pública
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Crea o actualiza un usuario en Moodle y lo matricula en sus cursos.
     *
     * Lógica de usuario:
     * - Si NO existe → se crea con todos los datos del CSV, incluida la contraseña.
     * - Si ya existe → se actualiza firstname/lastname/email/institution.
     *   La contraseña nunca se modifica en actualizaciones.
     *
     * Lógica de cursos:
     * - Se resuelven los IDs de todas las categorías bajo COLEGIOS/{institution}.
     * - Para 'student': solo cursos cuyo shortname termina en "-{grado}".
     * - Para 'teacher': todos los cursos de la institución sin filtro de grado.
     *
     * @param array $data {
     *   username:    string  Nombre de usuario (se convierte a minúsculas).
     *   password:    string  Contraseña inicial (ignorada si el usuario ya existe).
     *   firstname:   string
     *   lastname:    string
     *   email:       string
     *   institution: string  Nombre exacto de la subcategoría bajo COLEGIOS/.
     *   rol:         string  'student' o 'teacher'.
     *   grado:       int     1–11 para student; 0 o vacío para teacher.
     * }
     * @param bool $dryRun  Si true, no ejecuta cambios en Moodle.
     * @return array {
     *   username: string, institution: string, rol: string, grado: int|null,
     *   action: 'created'|'updated'|'dry-run:create'|'dry-run:update'|'error',
     *   user_id: int|null, courses: string[], error: string|null
     * }
     */
    public function matricularUsuario(array $data, bool $dryRun = false): array
    {
        global $DB, $CFG;
        require_once($CFG->dirroot . '/user/lib.php');
        require_once($CFG->libdir  . '/enrollib.php');

        $username    = strtolower(trim($data['username']));
        $password    = trim($data['password']    ?? '');
        $firstname   = trim($data['firstname']);
        $lastname    = trim($data['lastname']);
        $email       = strtolower(trim($data['email']));
        $institution = trim($data['institution']);
        $rol         = strtolower(trim($data['rol']));
        $grado       = isset($data['grado']) ? (int)$data['grado'] : 0;

        $moodleRol = $this->normalizeRol($rol);

        $result = [
            'username'    => $username,
            'institution' => $institution,
            'rol'         => $rol,
            'grado'       => $grado ?: null,
            'action'      => '',
            'user_id'     => null,
            'courses'     => [],
            'error'       => null,
        ];

        // ── Resolver cursos destino (siempre, incluso en dry-run) ─────────────
        $catId   = $this->resolveCategoryId("COLEGIOS/{$institution}");
        $courses = $this->findCourses($catId, $rol === 'student' ? $grado : null);

        // ── Dry-run: informar sin ejecutar ────────────────────────────────────
        if ($dryRun) {
            $existing        = $DB->get_record('user', ['username' => $username, 'deleted' => 0]);
            $result['action']  = $existing ? 'dry-run:update' : 'dry-run:create';
            $result['courses'] = array_map(fn($c) => $c->shortname, $courses);
            return $result;
        }

        // ── Crear o actualizar usuario ────────────────────────────────────────
        $existing = $DB->get_record('user', ['username' => $username, 'deleted' => 0]);

        if ($existing) {
            user_update_user((object)[
                'id'          => $existing->id,
                'firstname'   => $firstname,
                'lastname'    => $lastname,
                'email'       => $email,
                'institution' => $institution,
            ], false, false);

            $userId          = (int)$existing->id;
            $result['action'] = 'updated';
        } else {
            $userId = user_create_user((object)[
                'username'    => $username,
                'password'    => $password,
                'firstname'   => $firstname,
                'lastname'    => $lastname,
                'email'       => $email,
                'institution' => $institution,
                'confirmed'   => 1,
                'auth'        => 'manual',
                'mnethostid'  => $CFG->mnet_localhost_id,
                'lang'        => 'es',
            ], true, false);

            $result['action'] = 'created';
        }

        $result['user_id'] = $userId;

        // ── Matricular en cada curso ──────────────────────────────────────────
        $roleId = $DB->get_field('role', 'id', ['shortname' => $moodleRol], MUST_EXIST);
        $enrol  = enrol_get_plugin('manual');

        foreach ($courses as $course) {
            $instance = $this->ensureManualEnrolInstance($course);
            $enrol->enrol_user($instance, $userId, $roleId);
            $result['courses'][] = $course->shortname;
        }

        return $result;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Métodos privados
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Traduce el rol del CSV al shortname de rol en Moodle.
     *
     * @throws InvalidArgumentException Si el rol no es 'student' ni 'teacher'.
     */
    private function normalizeRol(string $rol): string
    {
        return match ($rol) {
            'student' => 'student',
            'teacher' => 'editingteacher',
            default   => throw new InvalidArgumentException(
                "Rol desconocido: '{$rol}'. Use 'student' o 'teacher'."
            ),
        };
    }

    /**
     * Navega course_categories por name+parent y retorna el ID de la categoría hoja.
     * No crea categorías; lanza RuntimeException si algún segmento no existe.
     *
     * @throws RuntimeException Si algún segmento del path no existe.
     */
    private function resolveCategoryId(string $path): int
    {
        global $DB;

        $parts    = array_map('trim', explode('/', $path));
        $parentId = 0;

        foreach ($parts as $part) {
            $cat = $DB->get_record('course_categories', ['name' => $part, 'parent' => $parentId]);

            if (!$cat) {
                throw new RuntimeException(
                    "Categoría '{$part}' no encontrada bajo parent_id={$parentId} (path: {$path})"
                );
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
     * Retorna los cursos dentro de la jerarquía de categorías bajo $catId.
     * Para student filtra por shortname que termine en "-{$grado}".
     * Para teacher (grado null) devuelve todos sin filtro.
     *
     * @param int      $catId  ID de la categoría raíz (e.g., COLEGIOS/San Marino).
     * @param int|null $grado  Número de grado para filtrar; null para teacher.
     * @return object[]        Registros de la tabla 'course'.
     */
    private function findCourses(int $catId, ?int $grado): array
    {
        global $DB;

        $catIds  = $this->getDescendantCategoryIds($catId);
        $courses = $DB->get_records_list('course', 'category', $catIds, 'shortname');

        // Excluir el sitio (id=1)
        $courses = array_filter($courses, fn($c) => (int)$c->id !== 1);

        if ($grado !== null && $grado > 0) {
            $suffix  = "-{$grado}";
            $courses = array_filter($courses, fn($c) => str_ends_with($c->shortname, $suffix));
        }

        return array_values($courses);
    }

    /**
     * Obtiene la instancia de enrol_manual para el curso.
     * Si no existe, la crea con los valores por defecto de Moodle.
     */
    private function ensureManualEnrolInstance(object $course): object
    {
        global $DB;

        $instance = $DB->get_record('enrol', ['courseid' => $course->id, 'enrol' => 'manual']);

        if ($instance) {
            return $instance;
        }

        $enrol      = enrol_get_plugin('manual');
        $instanceId = $enrol->add_default_instance($course);

        return $DB->get_record('enrol', ['id' => $instanceId], '*', MUST_EXIST);
    }
}
