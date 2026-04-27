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
    private const MONTHS_ES = [
        1  => 'enero',
        2  => 'febrero',
        3  => 'marzo',
        4  => 'abril',
        5  => 'mayo',
        6  => 'junio',
        7  => 'julio',
        8  => 'agosto',
        9  => 'septiembre',
        10 => 'octubre',
        11 => 'noviembre',
        12 => 'diciembre',
    ];

    private static function noreply(): object
    {
        return \core_user::get_noreply_user();
    }

    private static function descripcionTipoPaquete(string $teacherRole): string
    {
        if ($teacherRole === 'editingteacher') {
            return 'profesores editores';
        }
        return 'profesores';
    }

    private static function formatearFechaEs(int $timestamp): string
    {
        if (class_exists('\IntlDateFormatter')) {
            $formatter = new \IntlDateFormatter(
                'es_ES',
                \IntlDateFormatter::LONG,
                \IntlDateFormatter::NONE,
                date_default_timezone_get(),
                \IntlDateFormatter::GREGORIAN,
                "d 'de' MMMM 'de' y"
            );
            $fecha = $formatter->format($timestamp);
            if ($fecha !== false) {
                return mb_strtolower($fecha, 'UTF-8');
            }
        }

        $dia = date('d', $timestamp);
        $mes = self::MONTHS_ES[(int)date('n', $timestamp)] ?? date('F', $timestamp);
        $anio = date('Y', $timestamp);
        return "{$dia} de {$mes} de {$anio}";
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

            $para    = self::descripcionTipoPaquete($teacherRole);
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

            $fechaVigencia = self::formatearFechaEs($expiresAt);
            $noreply       = self::noreply();

            $asunto = "Tu acceso a \"{$courseName}\" ha sido activado";
            $texto  = "Hola {$usuario->firstname},\n\n"
                . "Tu acceso al curso \"{$courseName}\" ha sido activado exitosamente.\n\n"
                . "Vigencia: hasta el {$fechaVigencia}.\n\n"
                . "Ingresa aquí: https://conectatech.co/login\n\n"
                . "Saludos,\n"
                . "El equipo de ConectaTech";
            email_to_user($usuario, $noreply, $asunto, $texto);
        } catch (\Throwable $e) {
            error_log('EmailService::notificarPinActivado — ' . $e->getMessage());
        }
    }
}
