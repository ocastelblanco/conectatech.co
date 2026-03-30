#!/usr/bin/env php
<?php
/**
 * install-pines.php — Crea las tablas ct_* para el sistema de gestión de pines.
 *
 * Uso:
 *   sudo -u apache php /var/www/html/admin/backend/install-pines.php
 *
 * Idempotente: verifica si cada tabla ya existe antes de crearla.
 * Orden de creación respeta las dependencias entre tablas.
 *
 * Tablas:
 *   ct_organization  — organizaciones (colegios / empresas)
 *   ct_group         — grupos de la app (→ grupos Moodle al matricular)
 *   ct_gestor        — relación usuario Moodle ↔ organización con rol gestor
 *   ct_gestor_pin    — pines de un solo uso para crear cuentas de gestor
 *   ct_pin_package   — paquetes de pines creados por el administrador
 *   ct_pin           — pines individuales de acceso a cursos
 */

define('BACKEND_DIR', __DIR__);
define('LIB_DIR',     BACKEND_DIR . '/lib');

require_once(LIB_DIR . '/MoodleBootstrap.php');

$dbman = $DB->get_manager();

// ─────────────────────────────────────────────────────────────────────────────
// Helper
// ─────────────────────────────────────────────────────────────────────────────

function create_if_not_exists(database_manager $dbman, xmldb_table $table): void
{
    $name = $table->getName();
    if ($dbman->table_exists($table)) {
        echo "  SKIP  {$name}  (ya existe)\n";
        return;
    }
    $dbman->create_table($table);
    echo "  OK    {$name}\n";
}

// ─────────────────────────────────────────────────────────────────────────────

echo "=== Sistema de gestión de pines — instalación de tablas ===\n\n";

// ─────────────────────────────────────────────────────────────────────────────
// 1. ct_organization
//    Una institución educativa o empresa que compra pines a ConectaTech.
//    Se asocia a una subcategoría Moodle dentro de COLEGIOS.
// ─────────────────────────────────────────────────────────────────────────────
$t = new xmldb_table('ct_organization');
$t->add_field('id',                 XMLDB_TYPE_INTEGER, '10',  null, XMLDB_NOTNULL, XMLDB_SEQUENCE);
$t->add_field('name',               XMLDB_TYPE_CHAR,    '255', null, XMLDB_NOTNULL);
$t->add_field('moodle_category_id', XMLDB_TYPE_INTEGER, '10',  null, XMLDB_NOTNULL);
$t->add_field('created_at',         XMLDB_TYPE_INTEGER, '10',  null, XMLDB_NOTNULL);
$t->add_key(  'primary',            XMLDB_KEY_PRIMARY,  ['id']);
$t->add_index('idx_category',       XMLDB_INDEX_UNIQUE, ['moodle_category_id']);
create_if_not_exists($dbman, $t);

// ─────────────────────────────────────────────────────────────────────────────
// 2. ct_group
//    Grupos creados por el gestor dentro de la app.
//    moodle_group_id se rellena al matricular el primer usuario del grupo.
// ─────────────────────────────────────────────────────────────────────────────
$t = new xmldb_table('ct_group');
$t->add_field('id',              XMLDB_TYPE_INTEGER, '10',  null, XMLDB_NOTNULL, XMLDB_SEQUENCE);
$t->add_field('organization_id', XMLDB_TYPE_INTEGER, '10',  null, XMLDB_NOTNULL);
$t->add_field('name',            XMLDB_TYPE_CHAR,    '255', null, XMLDB_NOTNULL);
$t->add_field('moodle_group_id', XMLDB_TYPE_INTEGER, '10',  null, null);  // null hasta la primera matriculación
$t->add_key(  'primary',         XMLDB_KEY_PRIMARY,  ['id']);
$t->add_index('idx_org',         XMLDB_INDEX_NOTUNIQUE, ['organization_id']);
create_if_not_exists($dbman, $t);

// ─────────────────────────────────────────────────────────────────────────────
// 3. ct_gestor
//    Vincula un usuario Moodle con una organización, otorgándole el rol gestor
//    dentro de la app. Un usuario puede ser gestor de una sola organización.
// ─────────────────────────────────────────────────────────────────────────────
$t = new xmldb_table('ct_gestor');
$t->add_field('id',              XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE);
$t->add_field('organization_id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
$t->add_field('moodle_userid',   XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
$t->add_field('created_at',      XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
$t->add_key(  'primary',         XMLDB_KEY_PRIMARY,  ['id']);
$t->add_index('idx_user',        XMLDB_INDEX_UNIQUE, ['moodle_userid']);  // un gestor → una organización
$t->add_index('idx_org',         XMLDB_INDEX_NOTUNIQUE, ['organization_id']);
create_if_not_exists($dbman, $t);

// ─────────────────────────────────────────────────────────────────────────────
// 4. ct_gestor_pin
//    Pin de un solo uso generado por el administrador para que el futuro
//    gestor cree su cuenta Moodle y quede vinculado a la organización.
//    status: 'pending' → disponible; 'used' → ya utilizado.
// ─────────────────────────────────────────────────────────────────────────────
$t = new xmldb_table('ct_gestor_pin');
$t->add_field('id',              XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE);
$t->add_field('organization_id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
$t->add_field('hash',            XMLDB_TYPE_CHAR,    '32', null, XMLDB_NOTNULL);
$t->add_field('status',          XMLDB_TYPE_CHAR,    '10', null, XMLDB_NOTNULL, null, 'pending');
$t->add_field('created_by',      XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);  // moodle user id del admin
$t->add_field('created_at',      XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
$t->add_field('used_at',         XMLDB_TYPE_INTEGER, '10', null, null);           // null hasta ser utilizado
$t->add_key(  'primary',         XMLDB_KEY_PRIMARY,  ['id']);
$t->add_index('idx_hash',        XMLDB_INDEX_UNIQUE,    ['hash']);
$t->add_index('idx_org',         XMLDB_INDEX_NOTUNIQUE, ['organization_id', 'status']);
create_if_not_exists($dbman, $t);

// ─────────────────────────────────────────────────────────────────────────────
// 5. ct_pin_package
//    Lote de pines creado por el administrador.
//    teacher_role define el rol Moodle que se asignará a los pines de tipo
//    profesor en este paquete: 'editingteacher' o 'teacher'.
//    expires_at es la fecha de expiración de todos los pines del paquete
//    (también se usa como timeend en las matrículas Moodle).
// ─────────────────────────────────────────────────────────────────────────────
$t = new xmldb_table('ct_pin_package');
$t->add_field('id',              XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE);
$t->add_field('organization_id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
$t->add_field('teacher_role',    XMLDB_TYPE_CHAR,    '20', null, XMLDB_NOTNULL);  // editingteacher | teacher
$t->add_field('expires_at',      XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);  // unix timestamp
$t->add_field('created_by',      XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);  // moodle user id del admin
$t->add_field('created_at',      XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
$t->add_key(  'primary',         XMLDB_KEY_PRIMARY,  ['id']);
$t->add_index('idx_org',         XMLDB_INDEX_NOTUNIQUE, ['organization_id']);
create_if_not_exists($dbman, $t);

// ─────────────────────────────────────────────────────────────────────────────
// 6. ct_pin
//    Pin individual de acceso a un curso. Representa un cupo en el curso.
//
//    role: el rol que se asigna en Moodle al activar el pin.
//      Para pines de tipo profesor, hereda teacher_role del paquete.
//      Para pines de estudiante, siempre es 'student'.
//
//    status:
//      'available' → creado, sin asignar aún
//      'assigned'  → el gestor le asignó grupo + curso + rol; pendiente de activación
//      'active'    → activado por un usuario; la matrícula está vigente
//
//    El pin puede reutilizarse cuando expires_at < now (matrícula caducada).
//    El gestor lo reactiva cambiando status a 'assigned' con nuevos datos.
// ─────────────────────────────────────────────────────────────────────────────
$t = new xmldb_table('ct_pin');
$t->add_field('id',               XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE);
$t->add_field('package_id',       XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
$t->add_field('hash',             XMLDB_TYPE_CHAR,    '32', null, XMLDB_NOTNULL);
$t->add_field('role',             XMLDB_TYPE_CHAR,    '20', null, XMLDB_NOTNULL);  // editingteacher | teacher | student
$t->add_field('group_id',         XMLDB_TYPE_INTEGER, '10', null, null);            // ct_group.id; null hasta asignar
$t->add_field('moodle_course_id', XMLDB_TYPE_INTEGER, '10', null, null);            // null hasta asignar
$t->add_field('status',           XMLDB_TYPE_CHAR,    '10', null, XMLDB_NOTNULL, null, 'available');
$t->add_field('activated_by',     XMLDB_TYPE_INTEGER, '10', null, null);            // moodle user id; null hasta activar
$t->add_field('activated_at',     XMLDB_TYPE_INTEGER, '10', null, null);            // null hasta activar
$t->add_key(  'primary',          XMLDB_KEY_PRIMARY,  ['id']);
$t->add_index('idx_hash',         XMLDB_INDEX_UNIQUE,    ['hash']);
$t->add_index('idx_package',      XMLDB_INDEX_NOTUNIQUE, ['package_id']);
$t->add_index('idx_status',       XMLDB_INDEX_NOTUNIQUE, ['status']);
create_if_not_exists($dbman, $t);

// ─────────────────────────────────────────────────────────────────────────────

echo "\n=== Instalación completada ===\n";
