<?php
/**
 * OrganizacionService.php
 * CRUD de organizaciones y gestión de pines de gestor.
 *
 * Una organización representa una institución educativa o empresa que
 * compra pines a ConectaTech. Se asocia a una subcategoría de Moodle
 * dentro de la categoría raíz COLEGIOS.
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
     * La categoría Moodle debe existir y no estar asignada a otra org.
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
     * Elimina la organización y todos sus datos asociados en cascada:
     * gestores, pines de gestor, paquetes y pines individuales.
     *
     * No borra nada en Moodle (grupos, matrículas): esas operaciones
     * son responsabilidad de ActivacionService al crear / vencer pines.
     */
    public function eliminar(int $id): void
    {
        global $DB;

        $DB->get_record('ct_organization', ['id' => $id], '*', MUST_EXIST);

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
    // Pines de gestor
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
     * Lista los pines de gestor de una organización.
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
                'created_at' => (int)$pin->created_at,
                'used_at'    => $pin->used_at ? (int)$pin->used_at : null,
            ];
        }

        return $result;
    }

    // =========================================================================
    // Internos
    // =========================================================================

    /**
     * Cuenta los pines de una organización agrupados por estado.
     * Suma los pines de todos los paquetes de la organización.
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
     * Genera un hash de 32 caracteres hexadecimales que no exista
     * en la columna 'hash' de la tabla indicada.
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
