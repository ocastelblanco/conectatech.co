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

namespace local_usagereports\reportbuilder\datasource;

use core_reportbuilder\datasource;
use core_reportbuilder\local\entities\{course, user};
use local_usagereports\reportbuilder\local\entities\usage_event;

/**
 * Datasource "Uso de la plataforma".
 *
 * Reutiliza las entidades core `user` (columna Institución: user:institution) y
 * `course` (columna Curso: course:fullname) en vez de reimplementar esos joins,
 * igual que hace \core_role\reportbuilder\datasource\roles con `user`/`context`.
 *
 * @package    local_usagereports
 * @copyright  2026 ConectaTech.co
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class usage_report extends datasource {

    /**
     * Return user friendly name of the report source
     *
     * @return string
     */
    public static function get_name(): string {
        return get_string('datasourcename', 'local_usagereports');
    }

    /**
     * Initialise report
     */
    protected function initialise(): void {
        $usageevententity = new usage_event();
        $logalias = $usageevententity->get_table_alias('logstore_standard_log');

        $this->set_main_table('logstore_standard_log', $logalias);
        $this->add_entity($usageevententity);

        // Institución: reutiliza la entidad core `user`, unida por userid del evento.
        $userentity = new user();
        $useralias = $userentity->get_table_alias('user');
        $this->add_entity($userentity
            ->add_join("LEFT JOIN {user} {$useralias} ON {$useralias}.id = {$logalias}.userid"));

        // Curso: reutiliza la entidad core `course`, unida por courseid del evento.
        $courseentity = new course();
        $coursealias = $courseentity->get_table_alias('course');
        $this->add_entity($courseentity
            ->add_join("LEFT JOIN {course} {$coursealias} ON {$coursealias}.id = {$logalias}.courseid"));

        $this->add_all_from_entities();
    }

    /**
     * Return the columns that will be added to the report upon creation
     *
     * @return string[]
     */
    public function get_default_columns(): array {
        return [
            'user:institution',
            'usage_event:role',
            'course:fullname',
            'usage_event:eventtype',
            'usage_event:timecreated',
        ];
    }

    /**
     * Return the column sorting that will be added to the report upon creation
     *
     * @return int[]
     */
    public function get_default_column_sorting(): array {
        return [
            'usage_event:timecreated' => SORT_DESC,
        ];
    }

    /**
     * Return the filters that will be added to the report upon creation
     *
     * @return string[]
     */
    public function get_default_filters(): array {
        return [
            'user:institution',
            'usage_event:role',
            'course:courseselector',
            'usage_event:eventtype',
            'usage_event:timecreated',
        ];
    }

    /**
     * Return the conditions that will be added to the report upon creation
     *
     * @return string[]
     */
    public function get_default_conditions(): array {
        return [];
    }
}
