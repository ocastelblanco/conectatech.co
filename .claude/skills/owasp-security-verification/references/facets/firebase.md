# Faceta `firebase`

> CC BY-SA 4.0. Fuentes: Authorization, Multi Tenant Security, Authentication.

Las reglas de seguridad son el backend: los guardias de Angular no protegen nada.

| Chequeo | Qué revisar |
|---|---|
| FB-01/02 | Ya automáticos. Una lectura pública (`if true`) de un catálogo es aceptable: regístrala como excepción en el perfil. |
| FB-03 escalada de rol | En `match /users/{uid}`: `allow update` debe impedir cambiar `role`. Patrón: `!request.resource.data.diff(resource.data).affectedKeys().hasAny(['role'])`. |
| FB-04 proyecto Auth compartido | Cuando varias apps comparten proyecto, cada una debe exigir que el correo o UID esté en **su** colección o tabla de usuarios. Estar autenticado no basta. |

## Cloud Functions y Admin SDK

- El Admin SDK ignora las reglas: cada función callable o HTTP verifica `context.auth` o `verifyIdToken` y resuelve el rol del lado del servidor.
