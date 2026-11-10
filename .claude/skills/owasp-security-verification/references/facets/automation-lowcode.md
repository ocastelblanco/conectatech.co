# Faceta `automation-lowcode` (n8n)

> CC BY-SA 4.0. Fuentes: Webhook Security, SSRF Prevention, Secrets Management.

| Chequeo | Qué revisar en los workflows exportados (`*.json`) |
|---|---|
| N8N-01 | Automático: nodos `webhook` y `formTrigger` con `authentication` distinto de `none`. |
| N8N-03 triggers externos | Telegram: un nodo `if` temprano que compara `chat.id` o `from.id` con una lista permitida. Webhooks de terceros: validación HMAC en un nodo Code antes de cualquier acción. |
| N8N-04 nodos Code y credenciales | Sin `eval` ni `new Function` sobre datos entrantes; las API keys como credenciales de n8n o `$env`, no como texto en `parameters`. |
| BASE-32 SSRF | Nodos `httpRequest` con `url` armada con datos del mensaje: el host debe venir de una lista fija. |

Instancia: `N8N_SECURE_COOKIE` sin desactivar, `N8N_ENCRYPTION_KEY` fuera del repositorio y acceso a la UI solo con usuario y MFA.
