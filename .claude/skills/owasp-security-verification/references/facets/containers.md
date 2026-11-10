# Faceta `containers`

> CC BY-SA 4.0. Fuentes: Docker Security, NodeJS Docker, Network Segmentation, TLS, HSTS.

- **CTR-01:** en el compose de producción, Redis, Postgres, Ollama y similares no llevan `ports:`, o los publican solo en `127.0.0.1:`. La red interna de Docker basta para la comunicación entre servicios.
- **CTR-02:** `:latest` impide reproducir un despliegue y saber qué versión corre. Fija la versión, y el digest `@sha256` en imágenes críticas.
- **CTR-03:** `USER` no root en la etapa final. Las imágenes oficiales de `node` traen el usuario `node`.
- **CTR-08:** Redis con `requirepass` o ACL; Postgres con contraseña fuerte vía variable de entorno y `pg_hba` sin `trust`; paneles (OpenKM, n8n) sin credenciales por defecto.
- El proxy (Caddy o nginx) termina TLS: CTR-06 (sin TLS 1.0/1.1) y CTR-07 (HSTS, sin autoindex).
