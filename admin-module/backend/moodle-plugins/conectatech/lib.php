<?php
/**
 * local_conectatech — lib.php
 *
 * Añade el enlace "ConectaTech Admin" a la navegación de Moodle.
 * Solo visible para administradores del sitio y usuarios con cuenta de gestor
 * registrada en ct_gestor.
 */

/**
 * Extiende la navegación global de Moodle con el enlace al panel de administración.
 * En el tema Boost (Moodle 4.x+) este nodo aparece en el menú principal.
 */
function local_conectatech_extend_navigation(global_navigation $nav): void {
    global $USER;

    if (!isloggedin() || isguestuser()) {
        return;
    }

    if (!is_siteadmin($USER->id) && !local_conectatech_is_gestor((int)$USER->id)) {
        return;
    }

    $nav->add(
        get_string('adminlink', 'local_conectatech'),
        new moodle_url('https://admin.conectatech.co'),
        navigation_node::TYPE_CUSTOM,
        null,
        'local-conectatech-admin',
        new pix_icon('i/settings', '')
    );
}

/**
 * Inyecta un pequeño script que abre el enlace del panel en una pestaña nueva.
 */
function local_conectatech_before_footer(): string {
    global $USER;

    if (!isloggedin() || isguestuser()) {
        return '';
    }

    if (!is_siteadmin($USER->id) && !local_conectatech_is_gestor((int)$USER->id)) {
        return '';
    }

    return '<script>
(function() {
    function patchLinks() {
        document.querySelectorAll("a").forEach(function(a) {
            if (a.href && a.href.indexOf("admin.conectatech.co") !== -1) {
                a.target = "_blank";
                a.rel    = "noopener noreferrer";
            }
        });
    }
    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", patchLinks);
    } else {
        patchLinks();
    }
})();
</script>';
}

/**
 * Devuelve true si el usuario tiene una cuenta de gestor activa en ct_gestor.
 */
function local_conectatech_is_gestor(int $userId): bool {
    global $DB;
    return $DB->record_exists('ct_gestor', ['moodle_userid' => $userId]);
}
