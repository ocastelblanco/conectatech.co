# Faceta `angular-spa`

> CC BY-SA 4.0. Fuentes: Web Frontend Security, XSS Prevention, DOM based XSS Prevention, Content Security Policy.

Angular escapa la interpolación `{{ }}` y sanitiza `[innerHTML]`, así que el riesgo real está donde el código **desactiva** esas defensas o **escribe en el DOM** sin pasar por ellas.

| Chequeo | Qué revisar |
|---|---|
| NG-01 `bypassSecurityTrust*` | Un pipe `safe` genérico que aplica bypass a cualquier entrada es `fail`. Solo vale con contenido de origen fijo (URL de un iframe propio, SVG empaquetado), justificado con `owasp-ignore`. |
| NG-03 `[innerHTML]` | Rastrea de dónde sale el valor. Si viene de la API y lo escribió un usuario (descripciones, comentarios), exige sanitización en el servidor o DOMPurify. |
| NG-05 CSP | Si la regla no la encuentra, búscala en el hosting (Firebase `headers`, CloudFront `ResponseHeadersPolicy`, `server.ts`). Si no está en ningún lado, el estado es `not-verified` hasta correr LIVE-02. |
| NG-08 logout | `signOut()` más limpieza de signals, stores y `localStorage` con datos del usuario. |
| NG-09 versión y terceros | Compara la versión mayor de `@angular/core` con la tabla de soporte de angular.dev. Revisa `<script src="https://…">` en `index.html`: necesita `integrity` o se empaqueta. |
| NG-10 redirecciones | `returnUrl` o `redirect` en el login: solo rutas que empiezan con `/` y no con `//`. |

## SSR (`@angular/ssr`)

- `server.ts` es un servidor Express. NODE-01 (helmet) y BASE-32 (SSRF) aplican si el SSR hace `fetch` a URLs construidas con la petición.
- No pases secretos al estado transferido (`TransferState`): queda en el HTML.
