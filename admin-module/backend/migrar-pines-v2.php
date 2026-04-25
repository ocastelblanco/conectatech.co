#!/usr/bin/env php
<?php
/**
 * migrar-pines-v2.php — Migración de esquema del sistema de pines (v1 → v2).
 *
 * Cambios:
 *   ct_pin_package: reemplaza `expires_at` (timestamp absoluto) por
 *                   `duration_days` (93 | 182 | 365).
 *   ct_pin:         agrega `expires_at` (timestamp calculado al activar).
 *
 * Uso:
 *   sudo -u apache php /var/www/html/admin/backend/migrar-pines-v2.php
 *
 * Idempotente: verifica si cada cambio ya fue aplicado antes de ejecutarlo.
 */

define('BACKEND_DIR', __DIR__);
define('LIB_DIR',     BACKEND_DIR . '/lib');

require_once(LIB_DIR . '/MoodleBootstrap.php');

$dbman = $DB->get_manager();

echo "=== Migración pines v2 ===\n\n";

// ─── 1. ct_pin_package: añadir duration_days ─────────────────────────────────

$table = new xmldb_table('ct_pin_package');
$field = new xmldb_field('duration_days', XMLDB_TYPE_INTEGER, '10', null, null, null, null, 'teacher_role');

if (!$dbman->field_exists($table, $field)) {
    $dbman->add_field($table, $field);
    echo "  OK    ct_pin_package.duration_days añadido\n";
} else {
    echo "  SKIP  ct_pin_package.duration_days (ya existe)\n";
}

// ─── 2. ct_pin_package: eliminar expires_at ──────────────────────────────────

$field = new xmldb_field('expires_at');

if ($dbman->field_exists($table, $field)) {
    $dbman->drop_field($table, $field);
    echo "  OK    ct_pin_package.expires_at eliminado\n";
} else {
    echo "  SKIP  ct_pin_package.expires_at (ya no existe)\n";
}

// ─── 3. ct_pin: añadir expires_at ────────────────────────────────────────────

$table = new xmldb_table('ct_pin');
$field = new xmldb_field('expires_at', XMLDB_TYPE_INTEGER, '10', null, null, null, null, 'activated_at');

if (!$dbman->field_exists($table, $field)) {
    $dbman->add_field($table, $field);
    echo "  OK    ct_pin.expires_at añadido\n";
} else {
    echo "  SKIP  ct_pin.expires_at (ya existe)\n";
}

// ─── 4. Crear tabla ct_colegio ────────────────────────────────────────────────

$table = new xmldb_table('ct_colegio');
if (!$dbman->table_exists($table)) {
    $table->add_field('id',              XMLDB_TYPE_INTEGER, '10',  null, XMLDB_NOTNULL, XMLDB_SEQUENCE);
    $table->add_field('organization_id', XMLDB_TYPE_INTEGER, '10',  null, XMLDB_NOTNULL);
    $table->add_field('name',            XMLDB_TYPE_CHAR,    '255', null, XMLDB_NOTNULL);
    $table->add_field('created_at',      XMLDB_TYPE_INTEGER, '10',  null, XMLDB_NOTNULL);
    $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
    $table->add_index('idx_org', XMLDB_INDEX_NOTUNIQUE, ['organization_id']);
    $dbman->create_table($table);
    echo "  OK    ct_colegio creada\n";
} else {
    echo "  SKIP  ct_colegio (ya existe)\n";
}

// ─── 5. ct_group: añadir colegio_id ──────────────────────────────────────────

$table = new xmldb_table('ct_group');
$field = new xmldb_field('colegio_id', XMLDB_TYPE_INTEGER, '10', null, null, null, null, 'organization_id');
if (!$dbman->field_exists($table, $field)) {
    $dbman->add_field($table, $field);
    echo "  OK    ct_group.colegio_id añadido\n";
} else {
    echo "  SKIP  ct_group.colegio_id (ya existe)\n";
}

// ─── 6. Limpiar datos de prueba ───────────────────────────────────────────────

echo "\n--- Limpiando datos de prueba ---\n";

$DB->delete_records('ct_pin');
echo "  OK    ct_pin vaciado\n";

$DB->delete_records('ct_pin_package');
echo "  OK    ct_pin_package vaciado\n";

$DB->delete_records('ct_gestor');
echo "  OK    ct_gestor vaciado\n";

$DB->delete_records('ct_gestor_pin');
echo "  OK    ct_gestor_pin vaciado\n";

$DB->delete_records('ct_group');
echo "  OK    ct_group vaciado\n";

$DB->delete_records('ct_organization');
echo "  OK    ct_organization vaciado\n";

echo "\n=== Migración completada ===\n";
