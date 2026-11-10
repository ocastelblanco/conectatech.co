# Faceta `ci-cd` (GitHub Actions)

> CC BY-SA 4.0. Fuentes: CI CD Security, GitHub Actions Security, Software Supply Chain Security.

Los chequeos CICD-01..05 son automáticos. Al confirmar o corregir:

- **CICD-01:** mueve `${{ github.event.* }}` a `env:` y úsalo como `"$VAR"` dentro de `run:`.
- **CICD-02:** `pull_request_target` solo para etiquetar o comentar; nunca hagas checkout del código del PR en ese contexto.
- **CICD-03:** `permissions: contents: read` arriba, y permisos adicionales por job.
- **CICD-04:** fija las acciones de terceros por SHA, con la versión en un comentario (`@<sha> # v4.1.0`). Dependabot puede actualizarlas.
- **CICD-05:** AWS con `role-to-assume` (OIDC) y Google con Workload Identity Federation, en vez de llaves en secrets.
