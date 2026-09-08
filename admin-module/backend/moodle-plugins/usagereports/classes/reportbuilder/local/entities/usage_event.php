<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

declare(strict_types=1);

namespace local_usagereports\reportbuilder\local\entities;

use lang_string;
use local_usagereports\local\usage_events_config;
use core_reportbuilder\local\entities\base;
use core_reportbuilder\local\filters\{date, select};
use core_reportbuilder\local\helpers\{database, format};
use core_reportbuilder\local\report\{column, filter};

/**
 * Entidad "Uso de la plataforma": eventos de mdl_logstore_standard_log clasificados
 * en visualizacion/actividad/creacion (ver usage_events_config), con el rol
 * (student/editingteacher) del usuario en el contexto del curso del evento.
 *
 * Tablas propias de la entidad (independientes de cualquier otra entidad que use
 * el datasource): logstore_standard_log, context, role_assignments, role.
 * Institución (mdl_user) y Curso (mdl_course) se resuelven en el datasource
 * reutilizando las entidades core `user` y `course`.
 *
 * @package    local_usagereports
 * @copyright  2026 ConectaTech.co
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class usage_event extends base {

    /** @var string[] Shortnames de rol de interés — ver especificación, sección 3 */
    private const ROLE_SHORTNAMES = ['student', 'editingteacher'];

    /**
     * Database tables that this entity uses
     *
     * @return string[]
     */
    protected function get_default_tables(): array {
        return [
            'logstore_standard_log',
            'context',
            'role_assignments',
            'role',
        ];
    }

    /**
     * The default title for this entity
     *
     * @return lang_string
     */
    protected function get_default_entity_title(): lang_string {
        return new lang_string('entityusageevent', 'local_usagereports');
    }

    /**
     * Da de alta los joins internos de la entidad: contexto de curso del evento,
     * asignación de rol del usuario del evento en ese contexto, y el rol en sí
     * (restringido a student/editingteacher).
     *
     * @return self
     */
    public function initialise(): self {
        $logalias = $this->get_table_alias('logstore_standard_log');
        $contextalias = $this->get_table_alias('context');
        $roleassignmentalias = $this->get_table_alias('role_assignments');
        $rolealias = $this->get_table_alias('role');

        $this->add_join("LEFT JOIN {context} {$contextalias}
                            ON {$contextalias}.contextlevel = " . CONTEXT_COURSE . "
                           AND {$contextalias}.instanceid = {$logalias}.courseid");

        $this->add_join("LEFT JOIN {role_assignments} {$roleassignmentalias}
                            ON {$roleassignmentalias}.contextid = {$contextalias}.id
                           AND {$roleassignmentalias}.userid = {$logalias}.userid");

        // join_trait::add_join() solo acepta SQL crudo (sin parámetros con nombre), por eso
        // los 2 shortnames se embeben como literales SQL directamente. No hay riesgo de
        // inyección: son constantes fijas de esta clase (self::ROLE_SHORTNAMES), no vienen
        // de input de usuario ni de config editable.
        $roleshortnamesin = implode(', ', array_map(
            static fn(string $shortname) => "'" . $shortname . "'",
            self::ROLE_SHORTNAMES
        ));
        $this->add_join("LEFT JOIN {role} {$rolealias}
                            ON {$rolealias}.id = {$roleassignmentalias}.roleid
                           AND {$rolealias}.shortname IN ({$roleshortnamesin})");

        return parent::initialise();
    }

    /**
     * Construye el SQL (CASE) que clasifica cada evento en visualizacion/actividad/creacion
     * a partir de config/usage-events.json, con parámetros con nombre (sin concatenar valores
     * directamente en el SQL).
     *
     * @param string $logalias
     * @return array{0: string, 1: array} [SQL, params]
     */
    private function get_eventtype_case_sql(string $logalias): array {
        global $DB;

        $categories = usage_events_config::get_categories();
        $case = 'CASE';
        $params = [];

        foreach ($categories as $category => $eventnames) {
            if (empty($eventnames)) {
                continue;
            }
            $paramprefix = database::generate_param_name();
            [$insql, $inparams] = $DB->get_in_or_equal($eventnames, SQL_PARAMS_NAMED, $paramprefix . '_');
            // $category viene de usage_events_config::CATEGORIES (constante fija de esta clase,
            // no de usage-events.json), por eso se embebe directo sin necesidad de parámetro.
            $case .= " WHEN {$logalias}.eventname {$insql} THEN '{$category}'";
            $params = array_merge($params, $inparams);
        }

        $case .= ' ELSE NULL END';

        return [$case, $params];
    }

    /**
     * Returns list of all available columns
     *
     * @return column[]
     */
    protected function get_available_columns(): array {
        $logalias = $this->get_table_alias('logstore_standard_log');
        $rolealias = $this->get_table_alias('role');

        [$eventtypesql, $eventtypeparams] = $this->get_eventtype_case_sql($logalias);

        $eventtypelabels = $this->get_eventtype_labels();

        // Tipo de evento (calculada desde usage-events.json).
        $columns[] = (new column(
            'eventtype',
            new lang_string('columneventtype', 'local_usagereports'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_type(column::TYPE_TEXT)
            ->add_field($eventtypesql, 'eventtype', $eventtypeparams)
            ->set_is_sortable(true)
            ->add_callback(static fn(?string $eventtype) => $eventtypelabels[$eventtype] ?? '');

        // Rol (student/editingteacher, con nombre amigable).
        $columns[] = (new column(
            'role',
            new lang_string('columnrole', 'local_usagereports'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_type(column::TYPE_TEXT)
            ->add_field("{$rolealias}.shortname", 'roleshortname')
            ->set_is_sortable(true)
            ->add_callback([$this, 'format_role']);

        // Fecha.
        $columns[] = (new column(
            'timecreated',
            new lang_string('columndate', 'local_usagereports'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_type(column::TYPE_TIMESTAMP)
            ->add_field("{$logalias}.timecreated")
            ->set_is_sortable(true)
            ->add_callback([format::class, 'userdate']);

        return $columns;
    }

    /**
     * Return list of all available filters
     *
     * @return filter[]
     */
    protected function get_available_filters(): array {
        $logalias = $this->get_table_alias('logstore_standard_log');
        $rolealias = $this->get_table_alias('role');

        [$eventtypesql, $eventtypeparams] = $this->get_eventtype_case_sql($logalias);
        $eventtypelabels = $this->get_eventtype_labels();

        // Filtro: tipo de evento.
        $filters[] = (new filter(
            select::class,
            'eventtype',
            new lang_string('columneventtype', 'local_usagereports'),
            $this->get_entity_name(),
            $eventtypesql,
            $eventtypeparams
        ))
            ->add_joins($this->get_joins())
            ->set_options($eventtypelabels);

        // Filtro: rol.
        $filters[] = (new filter(
            select::class,
            'role',
            new lang_string('columnrole', 'local_usagereports'),
            $this->get_entity_name(),
            "{$rolealias}.shortname"
        ))
            ->add_joins($this->get_joins())
            ->set_options($this->get_role_labels());

        // Filtro: rango de fecha.
        $filters[] = (new filter(
            date::class,
            'timecreated',
            new lang_string('columndate', 'local_usagereports'),
            $this->get_entity_name(),
            "{$logalias}.timecreated"
        ))
            ->add_joins($this->get_joins());

        return $filters;
    }

    /**
     * Callback de formato para la columna Rol.
     *
     * @param string|null $roleshortname
     * @return string
     */
    public function format_role(?string $roleshortname): string {
        return $this->get_role_labels()[$roleshortname] ?? '';
    }

    /**
     * Etiquetas amigables para las 3 categorías de tipo de evento.
     *
     * @return array<string, string>
     */
    private function get_eventtype_labels(): array {
        return [
            'visualizacion' => get_string('eventtype:visualizacion', 'local_usagereports'),
            'actividad' => get_string('eventtype:actividad', 'local_usagereports'),
            'creacion' => get_string('eventtype:creacion', 'local_usagereports'),
        ];
    }

    /**
     * Etiquetas amigables para los 2 roles de interés.
     *
     * @return array<string, string>
     */
    private function get_role_labels(): array {
        return [
            'student' => get_string('role:student', 'local_usagereports'),
            'editingteacher' => get_string('role:editingteacher', 'local_usagereports'),
        ];
    }
}
