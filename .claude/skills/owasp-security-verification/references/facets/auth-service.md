# Faceta `auth-service`

> CC BY-SA 4.0. Fuentes: Authentication, Session Management, OAuth2, JSON Web Token, LDAP Injection Prevention, Password Storage.

| Chequeo | Qué revisar |
|---|---|
| AUTH-04 ciclo de sesión | `req.session.regenerate()` tras el login; en el logout, `req.session.destroy()`, borrado en Redis y revocación del refresh token. |
| AUTH-05 JWT emitidos | `exp` corto (≤ 15 min para access tokens), `iss`, `aud` y `typ`. Los consumidores verifican con la JWKS pública y una lista de algoritmos (`RS256`). La llave privada nunca se versiona (BASE-02). |
| AUTH-06 OIDC | `openid-client`: `generators.codeVerifier()`, `state` y `nonce` por petición, comparados en el callback; `client.callback(…, { state, nonce, code_verifier })`. |
| AUTH-07 contraseñas | Solo si hay cuentas locales: argon2id o bcrypt (cost ≥ 12). Sin usuarios semilla con contraseñas conocidas. |
| AUTH-08 expiración | `cookie.maxAge`, `rolling: true` para inactividad y un TTL absoluto en Redis. |
| AUTH-02 LDAP | `ldapts`: `EqualityFilter` o `escapeFilter`; nunca interpolar el usuario en el filtro. Bind con cuenta de servicio de mínimo privilegio. |
