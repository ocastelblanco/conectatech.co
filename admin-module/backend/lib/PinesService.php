<?php
/**
 * PinesService.php
 * Gestión de paquetes de pines y reporte de uso.
 *
 * Un paquete agrupa N pines con la misma fecha de expiración y el mismo
 * tipo de rol para profesor (editingteacher | teacher). Al crearse, se
 * generan automáticamente los pines con status 'available'.
 *
 * Los pines se asignan (grupo + curso + rol) por el gestor a través de
 * GestorService; aquí solo se expone la vista del administrador.
 */

class PinesService
{
    // =========================================================================
    // Paquetes
    // =========================================================================

    /**
     * Crea un paquete y genera $cantidad pines con status 'available'.
     * El rol de cada pin se fija en 'student' por defecto; el gestor puede
     * cambiarlo al asignarlos (excepto el teacher_role del paquete, que es
     * el rol máximo permitido para pines de tipo profesor).
     * $durationDays define la vigencia de la matrícula desde el momento de
     * activación: 93 = 3 meses, 182 = 6 meses, 365 = 12 meses.
     */
    public function crearPaquete(
        int    $orgId,
        string $teacherRole,
        int    $durationDays,
        int    $cantidad,
        int    $adminUserId
    ): array {
        global $DB;

        $DB->get_record('ct_organization', ['id' => $orgId], '*', MUST_EXIST);

        if (!in_array($teacherRole, ['editingteacher', 'teacher'], true)) {
            throw new InvalidArgumentException("teacher_role debe ser 'editingteacher' o 'teacher'.");
        }

        if ($cantidad < 1 || $cantidad > 1000) {
            throw new InvalidArgumentException('La cantidad debe estar entre 1 y 1000.');
        }

        if (!in_array($durationDays, [93, 182, 365], true)) {
            throw new InvalidArgumentException("duration_days debe ser 93, 182 o 365.");
        }

        $pkgId = $DB->insert_record('ct_pin_package', (object)[
            'organization_id' => $orgId,
            'teacher_role'    => $teacherRole,
            'duration_days'   => $durationDays,
            'created_by'      => $adminUserId,
            'created_at'      => time(),
        ]);

        $hashes = $this->generateUniqueHashes($cantidad);
        foreach ($hashes as $hash) {
            $DB->insert_record('ct_pin', (object)[
                'package_id'       => $pkgId,
                'hash'             => $hash,
                'role'             => 'student',   // el gestor cambia a teacher_role al asignar
                'group_id'         => null,
                'moodle_course_id' => null,
                'status'           => 'available',
                'activated_by'     => null,
                'activated_at'     => null,
            ]);
        }

        return [
            'id'              => (int)$pkgId,
            'organization_id' => $orgId,
            'teacher_role'    => $teacherRole,
            'duration_days'   => $durationDays,
            'cantidad'        => $cantidad,
        ];
    }

    /**
     * Lista paquetes. Si se pasa $orgId, filtra por organización.
     */
    public function listarPaquetes(?int $orgId = null): array
    {
        global $DB;

        $conditions = $orgId !== null ? ['organization_id' => $orgId] : [];
        $packages   = $DB->get_records('ct_pin_package', $conditions, 'created_at DESC');
        $result     = [];

        foreach ($packages as $pkg) {
            $org = $DB->get_record('ct_organization', ['id' => $pkg->organization_id]);

            $result[] = [
                'id'                => (int)$pkg->id,
                'organization_id'   => (int)$pkg->organization_id,
                'organization_name' => $org ? $org->name : null,
                'teacher_role'      => $pkg->teacher_role,
                'duration_days'     => (int)$pkg->duration_days,
                'created_at'        => (int)$pkg->created_at,
                'pins'              => $this->getPinCountsByPackage((int)$pkg->id),
            ];
        }

        return $result;
    }

    /**
     * Reasigna un paquete a otra organización.
     * Solo se permite si todos los pines del paquete están en estado 'available'
     * (ninguno ha sido asignado ni activado).
     */
    public function asignarPaquete(int $packageId, int $orgId): void
    {
        global $DB;

        $pkg = $DB->get_record('ct_pin_package', ['id' => $packageId], '*', MUST_EXIST);
        $DB->get_record('ct_organization', ['id' => $orgId], '*', MUST_EXIST);

        $nonAvailable = $DB->count_records_select(
            'ct_pin',
            "package_id = ? AND status != 'available'",
            [$packageId]
        );

        if ($nonAvailable > 0) {
            throw new InvalidArgumentException(
                'Solo se pueden reasignar paquetes cuyos pines estén todos en estado available.'
            );
        }

        $pkg->organization_id = $orgId;
        $DB->update_record('ct_pin_package', $pkg);
    }

    // =========================================================================
    // Reporte
    // =========================================================================

    /**
     * Reporte de uso de pines con detalle de asignación y activación.
     * Se puede filtrar por organización o por paquete; sin filtros, devuelve todo.
     */
    public function reporte(?int $orgId = null, ?int $packageId = null): array
    {
        global $DB;

        $where  = '1=1';
        $params = [];

        if ($packageId !== null) {
            $where   .= ' AND p.package_id = :pkgid';
            $params['pkgid'] = $packageId;
        } elseif ($orgId !== null) {
            $where   .= ' AND pkg.organization_id = :orgid';
            $params['orgid'] = $orgId;
        }

        $sql = "SELECT p.id, p.hash, p.role, p.status, p.activated_at, p.expires_at,
                       pkg.id AS pkg_id, pkg.teacher_role, pkg.duration_days,
                       org.name AS org_name,
                       c.fullname AS course_name,
                       g.name AS group_name,
                       u.username AS activated_username
                FROM {ct_pin} p
                JOIN {ct_pin_package} pkg ON pkg.id = p.package_id
                JOIN {ct_organization} org ON org.id = pkg.organization_id
                LEFT JOIN {course} c   ON c.id  = p.moodle_course_id
                LEFT JOIN {ct_group} g ON g.id  = p.group_id
                LEFT JOIN {user} u     ON u.id  = p.activated_by
                WHERE {$where}
                ORDER BY org.name ASC, pkg.id ASC, p.id ASC";

        $rows   = $DB->get_records_sql($sql, $params);
        $result = [];

        foreach ($rows as $row) {
            $result[] = [
                'id'                 => (int)$row->id,
                'hash'               => $row->hash,
                'role'               => $row->role,
                'status'             => $row->status,
                'expires_at'         => $row->expires_at ? (int)$row->expires_at : null,
                'activated_at'       => $row->activated_at ? (int)$row->activated_at : null,
                'activated_username' => $row->activated_username,
                'package_id'         => (int)$row->pkg_id,
                'teacher_role'       => $row->teacher_role,
                'duration_days'      => (int)$row->duration_days,
                'organization_name'  => $row->org_name,
                'course_name'        => $row->course_name,
                'group_name'         => $row->group_name,
            ];
        }

        return $result;
    }

    // =========================================================================
    // Internos
    // =========================================================================

    /**
     * Conteo de pines de un paquete agrupados por estado.
     */
    private function getPinCountsByPackage(int $packageId): array
    {
        global $DB;

        $rows   = $DB->get_records_sql(
            "SELECT status, COUNT(*) AS total FROM {ct_pin}
             WHERE package_id = ? GROUP BY status",
            [$packageId]
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
     * Genera $count hashes únicos de 32 caracteres hexadecimales
     * verificando colisiones contra ct_pin y dentro del lote generado.
     */
    private function generateUniqueHashes(int $count): array
    {
        global $DB;

        $hashes   = [];
        $attempts = 0;
        $maxTries = $count * 5;

        while (count($hashes) < $count) {
            if (++$attempts > $maxTries) {
                throw new RuntimeException('No se pudieron generar suficientes hashes únicos.');
            }

            $hash = bin2hex(random_bytes(16));

            if (
                !in_array($hash, $hashes, true)
                && !$DB->record_exists('ct_pin', ['hash' => $hash])
            ) {
                $hashes[] = $hash;
            }
        }

        return $hashes;
    }
}
