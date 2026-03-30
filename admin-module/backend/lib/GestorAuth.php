<?php
/**
 * GestorAuth.php
 * Resuelve el rol de gestor de un usuario Moodle en el sistema de pines.
 *
 * Un usuario Moodle es gestor si existe en ct_gestor con su moodle_userid.
 * Un usuario puede ser gestor de una sola organización.
 */

class GestorAuth
{
    /**
     * Busca el registro de gestor para el usuario Moodle indicado.
     * Retorna un array con los datos de la organización, o null si el usuario
     * no es gestor de ninguna organización.
     *
     * @return array{
     *   gestor_id:          int,
     *   organization_id:    int,
     *   organization_name:  string,
     *   moodle_category_id: int,
     *   gestor_since:       int,
     * }|null
     */
    public function lookupGestor(int $moodleUserId): ?array
    {
        global $DB;

        $row = $DB->get_record_sql(
            "SELECT g.id              AS gestor_id,
                    g.organization_id,
                    g.created_at      AS gestor_since,
                    org.name          AS organization_name,
                    org.moodle_category_id
             FROM {ct_gestor} g
             JOIN {ct_organization} org ON org.id = g.organization_id
             WHERE g.moodle_userid = ?",
            [$moodleUserId]
        );

        if (!$row) {
            return null;
        }

        return [
            'gestor_id'          => (int)$row->gestor_id,
            'organization_id'    => (int)$row->organization_id,
            'organization_name'  => $row->organization_name,
            'moodle_category_id' => (int)$row->moodle_category_id,
            'gestor_since'       => (int)$row->gestor_since,
        ];
    }
}
