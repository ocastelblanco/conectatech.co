<?php
/**
 * MoodleBootstrap.php
 * Inicializa el entorno Moodle para scripts CLI.
 * Ruta absoluta al config.php — independiente del directorio de ejecución.
 */

define('CLI_SCRIPT', true);

$moodleConfig = '/var/www/html/moodle/config.php';

if (!file_exists($moodleConfig)) {
    fwrite(STDERR, "ERROR: No se encontró config.php en {$moodleConfig}\n");
    exit(1);
}

require($moodleConfig);
