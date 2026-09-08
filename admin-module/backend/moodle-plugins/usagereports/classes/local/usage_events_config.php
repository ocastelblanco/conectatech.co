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

namespace local_usagereports\local;

use coding_exception;
use core_component;

/**
 * Carga y cachea la clasificación de eventos definida en config/usage-events.json.
 *
 * Editar ese archivo (agregar/quitar eventname por categoría) no requiere tocar
 * ninguna clase PHP — ver README.md del plugin.
 *
 * @package    local_usagereports
 * @copyright  2026 ConectaTech.co
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class usage_events_config {

    /** @var string[] Categorías válidas, en el orden en que deben evaluarse en el CASE SQL */
    public const CATEGORIES = ['visualizacion', 'actividad', 'creacion'];

    /** @var array|null Cache en memoria del contenido ya parseado */
    private static ?array $cache = null;

    /**
     * Devuelve el mapa categoría => lista de eventname, tal como está en usage-events.json.
     *
     * @return array<string, string[]>
     * @throws coding_exception Si el archivo falta o el JSON es inválido.
     */
    public static function get_categories(): array {
        if (self::$cache !== null) {
            return self::$cache;
        }

        $path = core_component::get_component_directory('local_usagereports') . '/config/usage-events.json';

        if (!is_readable($path)) {
            throw new coding_exception("local_usagereports: no se encontró el archivo de configuración en {$path}");
        }

        $decoded = json_decode(file_get_contents($path), true);

        if (json_last_error() !== JSON_ERROR_NONE || !is_array($decoded)) {
            throw new coding_exception('local_usagereports: usage-events.json no es un JSON válido: ' . json_last_error_msg());
        }

        $categories = [];
        foreach (self::CATEGORIES as $category) {
            $categories[$category] = array_values(array_filter(
                (array) ($decoded[$category] ?? []),
                static fn($eventname) => is_string($eventname) && $eventname !== ''
            ));
        }

        return self::$cache = $categories;
    }
}
