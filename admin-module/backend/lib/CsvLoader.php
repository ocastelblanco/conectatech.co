<?php
/**
 * CsvLoader.php — Utilidad compartida para leer archivos CSV.
 *
 * Centraliza la lógica de carga para que los scripts CLI y los handlers de API
 * usen exactamente el mismo comportamiento de parseo y normalización.
 */

class CsvLoader
{
    /**
     * Lee un CSV y devuelve sus filas como arrays asociativos.
     *
     * La primera fila se trata como cabecera; los nombres de columna se normalizan
     * a minúsculas con trim. Las filas con menos columnas que la cabecera se descartan.
     *
     * @param string $path  Ruta absoluta al archivo CSV.
     * @return array[]      Array de arrays asociativos ['columna' => 'valor', ...].
     * @throws RuntimeException Si el archivo no existe o no se puede abrir.
     */
    public static function loadRows(string $path): array
    {
        if (!file_exists($path)) {
            throw new RuntimeException("Archivo CSV no encontrado: {$path}");
        }

        $fh = fopen($path, 'r');

        if ($fh === false) {
            throw new RuntimeException("No se pudo abrir el archivo CSV: {$path}");
        }

        $header = fgetcsv($fh);

        if ($header === false) {
            fclose($fh);
            return [];
        }

        // Normalizar cabecera: trim + lowercase
        $header = array_map(fn($h) => strtolower(trim($h)), $header);

        $rows = [];

        while ($raw = fgetcsv($fh)) {
            // Descartar filas incompletas
            if (count($raw) < count($header)) {
                continue;
            }

            $row = array_combine($header, $raw);

            // Limpiar espacios en todos los valores
            $rows[] = array_map('trim', $row);
        }

        fclose($fh);
        return $rows;
    }
}
