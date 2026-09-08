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

/**
 * Version details for local_usagereports.
 *
 * Fuente de datos personalizada para el Report Builder nativo de Moodle
 * que expone visualizaciones, actividades y creación de recursos,
 * agrupables por Institución, Rol y Curso.
 *
 * @package    local_usagereports
 * @copyright  2026 ConectaTech.co
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$plugin->component = 'local_usagereports';
$plugin->version   = 2026090700;
$plugin->requires  = 2026042000; // Moodle 5.2.1+ (Build: 20260630), confirmado en el servidor de producción.
$plugin->maturity  = MATURITY_ALPHA;
$plugin->release   = '0.1.0';
