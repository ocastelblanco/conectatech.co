# Faceta `baseline` — siempre aplica

> CC BY-SA 4.0. Adaptado de OWASP Cheat Sheet Series. Úsala para resolver los chequeos `BASE-*` de tipo review.

## Cómo resolver un chequeo de revisión

1. Abre solo los **candidatos** que listó `verify.mjs`. Si están vacíos, busca con Grep las palabras de la tabla de abajo.
2. Decide `pass`, `fail` o `not-applicable` y regístralo con `findings.mjs record` y al menos una evidencia `ruta:línea`.
3. Un `pass` también necesita evidencia: la línea donde está el control (middleware, validación, regla). Si no lo encuentras, el veredicto es `fail` o se deja pendiente. Nunca `pass` por ausencia de problemas.

## Dónde mirar

| Chequeo | Busca | Señal de `pass` | Señal de `fail` |
|---|---|---|---|
| BASE-19 autorización en servidor | `middleware`, `requireRole`, `verifyIdToken`, `require_capability`, `@login_required`, reglas de Firestore | Un control común que aplica a cada ruta protegida y resuelve el rol desde el almacén del servidor | Rutas protegidas sin el control, o con el rol leído de `req.body` o de un claim sin verificar |
| BASE-20 IDOR | handlers con `:id`, `params.id`, `GetItem`, `doc(id)` | La consulta incluye el dueño (`owner = user.id`) o hay una verificación explícita antes de responder | Se busca por ID y se devuelve sin comparar con el usuario |
| BASE-21 validación | `zod`, `joi`, `class-validator`, `pydantic`, `filter_var`, `Validator` | Esquema por endpoint con tipos y longitudes | `JSON.parse(body)` usado directo |
| BASE-22 errores | `catch`, handlers globales, `set_exception_handler`, `errorhandler` | Respuesta genérica con ID de correlación; el detalle va solo a logs | `res.send(err)`, `err.stack` o mensajes SQL en la respuesta; `catch { return true }` |
| BASE-23 logs de auth | login, logout, 401, 403 | Log estructurado con usuario, IP, resultado | Sin logs, o logs con contraseña o token |
| BASE-24/25 datos personales | PRD, tech-specs, servicios HTTP | Inventario de datos personales con retención; datos sensibles en el body | Cédulas o correos en rutas o query strings |
| BASE-26 carga de archivos | `multer`, `busboy`, `request.files`, `$_FILES`, URLs prefirmadas de S3 | Límite de tamaño, lista de tipos, nombre generado | Sin límite, o con el nombre original como ruta |
| BASE-27 recursos limitados | aforos, inventario, boletas, pines | `ConditionExpression`, `TransactWriteItems`, `runTransaction`, `FOR UPDATE` | Leer, comparar y escribir sin condición |
| BASE-28 anti-automatización | API Gateway throttling, WAF, `express-rate-limit`, `limit_req` | Límite en login, compra, correo, OCR y LLM | Endpoints costosos sin límite |
| BASE-29 pagos | webhooks de la pasarela | Firma o HMAC verificada y monto comparado con la orden | Orden marcada como pagada por la redirección del navegador |
| BASE-31 exposición de campos | serialización de respuestas | DTO o `select` explícito | `res.json(item)` con el registro completo |
| BASE-32 SSRF | `fetch(url)`, `axios(url)`, `requests.get(url)`, SSR de Angular, nodos HTTP de n8n | Host validado contra lista de permitidos | URL construida con datos del usuario |

## Falsos positivos frecuentes de las reglas `auto`

- **BASE-01** con `user:pass@` o valores de ejemplo: ya se filtran. Si queda uno en documentación, verifica si la contraseña es real antes de marcarlo.
- **BASE-11** md5 usado como clave de caché o ETag: no es criptográfico. Regístralo como `pass` con la evidencia y la nota.
- **BASE-15** `Access-Control-Allow-Origin: *` en endpoints públicos sin credenciales: es aceptable. Regístralo como excepción en el perfil (`accepted`) con su justificación.

Para silenciar una línea concreta con justificación, deja en el código un comentario `owasp-ignore <ID>: motivo` en la línea anterior.
