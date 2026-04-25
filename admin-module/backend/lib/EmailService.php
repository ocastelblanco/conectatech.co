<?php
/**
 * EmailService — notificaciones transaccionales via Moodle (SMTP → AWS SES).
 *
 * Usa email_to_user() de Moodle para respetar la config SMTP configurada.
 * Todos los métodos son fail-safe: si el envío falla, solo loguea el error;
 * la operación principal nunca falla por un email.
 */
class EmailService
{
    private static function noreply(): object
    {
        return \core_user::get_noreply_user();
    }

    /**
     * Notifica a todos los gestores activos de una org que hay pines nuevos disponibles.
     */
    public static function notificarPaqueteCreado(int $orgId, int $cantidad, string $teacherRole): void
    {
        global $DB;
        try {
            $gestores = $DB->get_records_sql(
                "SELECT u.* FROM {user} u
                  JOIN {ct_gestor} g ON g.moodle_userid = u.id
                 WHERE g.organization_id = :orgid AND u.deleted = 0",
                ['orgid' => $orgId]
            );
            if (empty($gestores)) return;

            $para    = $teacherRole === 'student' ? 'estudiantes' : 'profesores';
            $noreply = self::noreply();

            foreach ($gestores as $gestor) {
                $asunto = "Nuevo paquete de pines disponible en ConectaTech";
                $texto  = "Hola {$gestor->firstname},\n\n"
                    . "Se ha creado un nuevo paquete con {$cantidad} pines para {$para} en tu organización.\n\n"
                    . "Ya puedes asignarlos desde tu portal:\n"
                    . "https://admin.conectatech.co/gestor/pines\n\n"
                    . "Saludos,\n"
                    . "El equipo de ConectaTech";
                email_to_user($gestor, $noreply, $asunto, $texto);
            }
        } catch (\Throwable $e) {
            error_log('EmailService::notificarPaqueteCreado — ' . $e->getMessage());
        }
    }

    /**
     * Notifica al usuario que su pin fue activado y le indica la vigencia.
     */
    public static function notificarPinActivado(int $userId, string $courseName, int $expiresAt): void
    {
        global $DB;
        try {
            $usuario = $DB->get_record('user', ['id' => $userId, 'deleted' => 0]);
            if (!$usuario) return;

            $fechaVigencia = date('d \d\e F \d\e Y', $expiresAt);
            $noreply       = self::noreply();

            $asunto = "Tu acceso a \"{$courseName}\" ha sido activado";
            $texto  = "Hola {$usuario->firstname},\n\n"
                . "Tu acceso al curso \"{$courseName}\" ha sido activado exitosamente.\n\n"
                . "Vigencia: hasta el {$fechaVigencia}.\n\n"
                . "Ingresa aquí: https://conectatech.co\n\n"
                . "Saludos,\n"
                . "El equipo de ConectaTech";
            email_to_user($usuario, $noreply, $asunto, $texto);
        } catch (\Throwable $e) {
            error_log('EmailService::notificarPinActivado — ' . $e->getMessage());
        }
    }
}
