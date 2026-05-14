#!/usr/bin/env php
<?php
/**
 * migrar-instituciones.php — Crea la tabla ct_institucion (Track A, clientes directos).
 *
 * Uso:
 *   sudo -u apache php /var/www/html/admin/backend/migrar-instituciones.php
 *
 * Idempotente: verifica si la tabla ya existe antes de crearla.
 */

define('BACKEND_DIR', __DIR__);
define('LIB_DIR',     BACKEND_DIR . '/lib');

require_once(LIB_DIR . '/MoodleBootstrap.php');

$dbman = $DB->get_manager();

echo "=== Migración: ct_institucion ===\n\n";

// ─────────────────────────────────────────────────────────────────────────────
// ct_institucion
//   Colegio o institución educativa que compra directamente a ConectaTech.
//   ConectaTech gestiona las matrículas desde Excel/CSV.
//   Se asocia a una categoría Moodle que agrupa todos sus cursos.
// ─────────────────────────────────────────────────────────────────────────────

$t = new xmldb_table('ct_institucion');
if (!$dbman->table_exists($t)) {
    $t->add_field('id',                 XMLDB_TYPE_INTEGER, '10',  null, XMLDB_NOTNULL, XMLDB_SEQUENCE);
    $t->add_field('name',               XMLDB_TYPE_CHAR,    '255', null, XMLDB_NOTNULL);
    $t->add_field('moodle_category_id', XMLDB_TYPE_INTEGER, '10',  null, XMLDB_NOTNULL);
    $t->add_field('anio_escolar',       XMLDB_TYPE_CHAR,    '9',   null, null);           // ej: "2026", nullable
    $t->add_field('created_at',         XMLDB_TYPE_INTEGER, '10',  null, XMLDB_NOTNULL);
    $t->add_key(  'primary',            XMLDB_KEY_PRIMARY,  ['id']);
    $t->add_index('idx_category',       XMLDB_INDEX_UNIQUE, ['moodle_category_id']);
    $dbman->create_table($t);
    echo "  OK    ct_institucion creada\n";
} else {
    echo "  SKIP  ct_institucion (ya existe)\n";
}

echo "\n=== Migración completada ===\n";
