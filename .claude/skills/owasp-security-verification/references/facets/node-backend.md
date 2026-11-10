# Faceta `node-backend`

> CC BY-SA 4.0. Fuentes: Nodejs Security, NPM Security, Mass Assignment, IDOR Prevention, Prototype Pollution Prevention.

| Chequeo | Qué revisar |
|---|---|
| NODE-03 verificación de token | Identifica el wrapper o middleware común (por ejemplo `conAutenticacion(handler)` o `app.use(verificarToken)`). Lista las rutas o funciones y confirma que todas las no públicas pasan por él. Firebase: `verifyIdToken(token, true)` si importa la revocación. |
| BASE-33 asignación masiva | `update(id, req.body)`, `{ ...body }` hacia la base, `Object.assign(entity, body)`. Exige campos elegidos uno a uno. |
| NODE-02 JWT | `jwt.verify` siempre con `{ algorithms: [...] }`. `jwt.decode` solo para leer `kid` antes de verificar. |

## Lambda

- Cada función es un endpoint: la verificación de identidad va en cada handler o en un wrapper común.
- El `event.requestContext.authorizer` solo es confiable si API Gateway tiene authorizer configurado (SLS-05).
