---
name: owasp-security-verification
description: >
  Verifica una aplicación o componente recién creado contra los requisitos OWASP que le
  aplican según su stack (Angular, Node/Lambda, Firebase, Supabase, PHP/Moodle, Python,
  LLM, autenticación propia, Docker, Terraform/Serverless, n8n, GitHub Actions), con
  evidencia archivo:línea, IDs de ASVS 5.0 y categorías del Top 10:2025. Úsala cuando
  pidan "verifica la seguridad", "auditoría OWASP", "¿esto es seguro?", "revisa
  vulnerabilidades", "security check", "OWASP review", "security audit", o al terminar
  un componente antes de abrir el PR. Modos: profile, verify, live, report.
allowed-tools: Read, Glob, Grep, AskUserQuestion, Bash(node:*), Bash(npm audit:*), Bash(composer audit:*), Bash(git ls-files:*)
---

# OWASP Security Verification

Verifica lo construido contra los requisitos OWASP que **le aplican**, con hallazgos trazables y sin datos inventados.

**Regla que define la skill:** un chequeo solo se marca `pass` o `fail` con evidencia que exista (archivo y línea, URL del allowlist o llamada de API de solo lectura). Sin evidencia, el estado queda `pendiente` o `no verificado`, que **no** equivale a aprobado. `findings.mjs` rechaza cualquier veredicto que no cumpla esto.

Estándares: **ASVS 5.0.0** (requisito verificable), **Top 10:2025** (riesgo) y **Cheat Sheet Series** (corrección). Detalle en [`references/sources.md`](references/sources.md).

Rutas: `$SKILL` es el directorio de esta skill y `<p>` la raíz del proyecto. Todo se escribe en `<p>/.owasp/`; la skill nunca modifica el código auditado.

---

## Modos

| Modo | Cuándo | Comando base |
|---|---|---|
| `profile` | la primera vez en un proyecto | `node $SKILL/scripts/profile.mjs propose\|confirm --root <p>` |
| `verify` | al cerrar un componente o antes del PR (por defecto) | `node $SKILL/scripts/verify.mjs --root <p> [--scope <ruta>]` |
| `live` | solo si el usuario lo pide de forma explícita | `node $SKILL/scripts/live.mjs --root <p>` |
| `report` | después de resolver la cola | `node $SKILL/scripts/report.mjs --root <p>` |

Si el usuario no indica modo: sin `<p>/.owasp/profile.json`, empieza por `profile` y sigue con `verify`; con perfil, haz `verify`.

---

## Modo `profile`

1. `profile.mjs propose --root <p>` muestra las facetas detectadas (con el archivo que activó cada una) y el nivel sugerido con sus señales (PII, pagos, autenticación propia).
2. Pregunta con **AskUserQuestion** solo lo que el repositorio no dice:
   - Nivel ASVS: L1 por defecto; L2 si hay datos personales, pagos o autenticación propia.
   - Facetas mal detectadas que haya que desactivar.
   - URLs públicas para el modo live (opcional) y perfil y región de AWS para la lectura de configuración (opcional).
3. `profile.mjs confirm --root <p> --level N [--disable faceta] [--url https://…]`.
4. Sugiere versionar `.owasp/profile.json` y agregar `.owasp/results.json`, `.owasp/history/`, `.owasp/live.json` y `.owasp/report.*` al `.gitignore`: los reportes describen debilidades y no deben ir a un repositorio público.

## Modo `verify`

1. Ejecuta `verify.mjs`. Usa `--scope <ruta>` para validar solo el componente recién creado. La salida trae los fallos automáticos y la **cola de revisión** con candidatos.
2. Para cada ítem de la cola:
   - Lee la guía de **su faceta** en [`references/facets/`](references/facets/). Carga solo las facetas que aparezcan en la cola.
   - Abre solo los candidatos listados, o búscalos con Grep según la guía.
   - Registra el veredicto:
     ```bash
     node $SKILL/scripts/findings.mjs record --root <p> --check BASE-19 --status fail \
       --evidence rund-api/app/routes_v2.php:85 --note "La ruta /documentos no pasa por AuthMiddleware"
     ```
   - Un `pass` también exige evidencia: la línea donde está el control. Si no la encuentras, no marques `pass`.
   - Si `findings.mjs` rechaza el veredicto, corrige la evidencia. No la reformules para que pase.
3. Los ítems con hallazgos de **baja confianza** (por ejemplo `[innerHTML]` o `Resource: '*'`) se confirman igual: `fail` si el riesgo es real; `pass`, con la evidencia del control que lo mitiga, si no lo es.
4. Un falso positivo que deba quedar silenciado se registra con justificación y vencimiento:
   `profile.mjs accept --root <p> --check CTR-02 --path docker-compose.dev.yml --reason "…" --expires AAAA-MM-DD`. También sirve un comentario `owasp-ignore <ID>: motivo` en la línea anterior del código.
5. Termina con el modo `report`.

**Presupuesto de contexto:** no leas `references/checks.json` ni `asvs-5.0.0.json`; los scripts ya filtran. Cada guía de faceta tiene menos de 40 líneas.

## Modo `live` (opt-in)

Solo con autorización explícita del usuario en esta sesión y sobre URLs de su propiedad.

1. `live.mjs --root <p>` usa `profile.live.urls`, o pásalas con `--url`. Solo hace GET, HEAD y OPTIONS a hosts del allowlist, a 1 petición por segundo. No escanea puertos ni hace fuzzing.
2. Los chequeos `AWS-*` se resuelven con el MCP de AWS en **solo lectura**: operaciones `Get*`, `List*` o `Describe*` indicadas en cada chequeo. Registra cada veredicto con `--evidence aws:<servicio>:<Operación>[:recurso]`. Nunca ejecutes operaciones que modifiquen recursos.
3. Corre `report.mjs`: incorpora `live.json` automáticamente.

## Modo `report`

`report.mjs --root <p>` genera `report.md` (en español) y `report.sarif` (para code scanning) y compara con la corrida anterior. Presenta al usuario:

- Fallas por severidad, con su ID de ASVS y el enlace de corrección.
- Cobertura: qué porcentaje de los chequeos aplicables tiene veredicto con evidencia.
- Lo que quedó pendiente o no verificado y por qué. Nunca lo presentes como aprobado.

---

## Integración con las otras skills

- **project-docs-bootstrap:** la sección `## Seguridad (OWASP)` de `CLAUDE.md` declara reglas propias del proyecto. Inclúyelas en la revisión de BASE-19, BASE-20 y FB-03. Si el proyecto tiene `TODO.md` con motor JIT, propone como candidata la falla de mayor severidad, sin superar el límite de 2 tareas.
- **ai-effort-tracking:** registra cada corrida como una unidad de trabajo de tipo `security-verification`.

## Reglas duras

- No inventes evidencia ni cites líneas que no leíste.
- No marques `pass` por ausencia de hallazgos en un chequeo `review`.
- No ejecutes el modo live ni consultas a AWS sin autorización explícita en esta sesión.
- No copies secretos en el chat ni en notas: los reportes ya los muestran redactados.
- No modifiques el código auditado como parte de la verificación. Si el usuario pide corregir, eso es otra tarea.
