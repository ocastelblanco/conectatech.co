# Faceta `php-app` (incluye Moodle)

> CC BY-SA 4.0. Fuentes: PHP Configuration, SQL Injection Prevention, Query Parameterization, File Upload, XXE Prevention.

| Chequeo | Qué revisar |
|---|---|
| PHP-04 autenticación y autorización | Cada script en `api/`, `handlers/` o en la raíz web: primero el control de sesión del proyecto o, en Moodle, `require_login()` y `require_capability()`/`has_capability()` con el contexto correcto. |
| PHP-05 cookies y CSRF | `session.cookie_secure`, `cookie_httponly`, `cookie_samesite`. Formularios con token CSRF; en Moodle, `require_sesskey()` o `confirm_sesskey()`. |
| BASE-09 SQL | Moodle: `$DB->get_records_sql($sql, $params)` con `?` o `:nombre`, nunca concatenar. PDO: `prepare` + `execute([…])`. |
| BASE-10 comandos | `exec`, `shell_exec`: argumentos con `escapeshellarg` uno por uno (no basta `escapeshellcmd` sobre la cadena completa), o `proc_open` con arreglo. |

## Moodle

- Parámetros: `required_param` u `optional_param` con `PARAM_INT`, `PARAM_ALPHANUMEXT` o similares. `PARAM_RAW` exige justificación.
- Salida: `s()`, `format_string()`, `format_text()`. `echo $variable` de entrada del usuario es `fail`.
- Scripts CLI con `define('CLI_SCRIPT', true)` no deben ser alcanzables desde la web (fuera de DocumentRoot, que en Moodle 5.x es `public/`).
