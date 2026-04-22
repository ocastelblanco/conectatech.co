#!/usr/bin/env php
<?php
/**
 * crear-rol-gestor.php — Crea el rol Moodle `ct_gestor` para gestores de organizaciones.
 *
 * El rol otorga acceso de solo lectura a cursos, participantes y calificaciones
 * dentro de la categoría de la organización asignada al gestor.
 *
 * Uso:
 *   sudo -u apache php /var/www/html/admin/backend/crear-rol-gestor.php
 *
 * Idempotente: si el rol ya existe, actualiza sus capacidades.
 */

define('BACKEND_DIR', __DIR__);
define('LIB_DIR',     BACKEND_DIR . '/lib');

require_once(LIB_DIR . '/MoodleBootstrap.php');

echo "=== Creación/actualización del rol ct_gestor ===\n\n";

// ─── Crear o localizar el rol ─────────────────────────────────────────────────

$shortname = 'ct_gestor';
$existing  = $DB->get_record('role', ['shortname' => $shortname]);

if ($existing) {
    $roleId = (int)$existing->id;
    echo "  INFO  Rol '{$shortname}' ya existe (id={$roleId}). Actualizando capacidades.\n";
} else {
    $roleId = create_role(
        'Gestor ConectaTech',
        $shortname,
        'Observador de cursos para gestores de organizaciones. ' .
        'Solo lectura: puede ver contenidos, participantes y calificaciones, pero no editar.',
        ''
    );
    echo "  OK    Rol '{$shortname}' creado (id={$roleId})\n";
}

// ─── Contextos donde aplica ───────────────────────────────────────────────────

set_role_contextlevels($roleId, [CONTEXT_COURSECAT, CONTEXT_COURSE]);
echo "  OK    Contextos: COURSECAT + COURSE\n";

// ─── Capacidades ALLOW ────────────────────────────────────────────────────────

$allows = [
    // Acceso general a cursos
    'moodle/course:view',
    'moodle/course:viewhiddencourses',
    'moodle/course:viewparticipants',

    // Ver recursos y actividades
    'mod/resource:view',
    'mod/page:view',
    'mod/url:view',
    'mod/label:view',
    'mod/folder:view',
    'mod/quiz:view',
    'mod/quiz:viewreports',
    'mod/quiz:grade',
    'mod/forum:viewdiscussion',
    'mod/assign:view',
    'mod/assign:viewgrades',
    'mod/subsection:view',

    // Calificaciones (solo lectura)
    'moodle/grade:viewall',
    'gradereport/grader:view',
    'gradereport/overview:view',
    'gradereport/user:view',

    // Usuarios
    'moodle/user:viewdetails',
    'moodle/user:viewhiddendetails',

    // Grupos (ver todos los grupos de su org)
    'moodle/site:accessallgroups',
];

$systemCtx = context_system::instance();

foreach ($allows as $cap) {
    assign_capability($cap, CAP_ALLOW, $roleId, $systemCtx->id, true);
}

echo "  OK    " . count($allows) . " capacidades ALLOW asignadas\n";

// ─── Recalcular caché de roles ────────────────────────────────────────────────

accesslib_clear_all_caches(true);
echo "  OK    Caché de permisos recalculada\n";

echo "\n=== Rol ct_gestor listo ===\n";
echo "Ejecutar este script cada vez que se agreguen nuevas capacidades al rol.\n";
