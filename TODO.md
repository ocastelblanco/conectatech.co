# TODO.md — Motor JIT · ConectaTech.co
> Siempre exactamente 2 tareas atómicas · Última actualización: 2026-04-25 (rev. 5)

---

## Cómo funciona este archivo

**Motor JIT (Just-In-Time):** En lugar de mantener un backlog largo, este archivo contiene únicamente las **2 próximas tareas atómicas** más prioritarias, calculadas comparando el PRD.md (objetivo final) con el MEMORY.md (estado real).

**Cuándo actualizar:**
1. Al completar cualquiera de las dos tareas, marcarla en el historial y calcular la siguiente
2. Al inicio de cada sesión, verificar que las tareas siguen siendo las más prioritarias

**Criterio de prioridad:**
1. Seguridad activa en producción con gaps OWASP no resueltos
2. Features de Alta prioridad del PRD §6
3. Features de Media prioridad del PRD §6

**Criterio de atomicidad:** Una tarea es atómica si puede completarse en una sola sesión de trabajo, modifica máximo 3 archivos, tiene una definición de done verificable y no depende de que la otra tarea del TODO esté completa primero.

---

## Tarea 1 — [INFRA] Sistema de correos AWS — Fases 1, 2 y 3

**Origen:** PRD §6 (Alta — cliente activo) · Plan detallado: `docs/email/plan-trabajo-conectatech.md`

**Problema:** El dominio `conectatech.co` no tiene infraestructura de correo. Moodle usa `PHP mail()` por defecto (sin SMTP configurado). No hay reenvío de correos entrantes a Gmail. El sistema de correos es prerequisito para las notificaciones automáticas.

**Qué hacer:**

### Fase 1 — Verificación DNS + SES (≈30 min + espera propagación)
- Verificar dominio `conectatech.co` en SES → obtener VerificationToken
- Crear TXT `_amazonses.conectatech.co` en Route 53 zona `Z0767805255ZNR9CRNWLH`
- Habilitar DKIM → 3 CNAMEs `<token>._domainkey.conectatech.co`
- Crear registro MX: `conectatech.co MX 10 inbound-smtp.us-east-1.amazonaws.com`
- Verificar emails `no-reply@conectatech.co` y `forwarder@conectatech.co` en SES

### Fase 2 — Infraestructura Inbound (≈45 min, en paralelo con espera DNS)
- S3: crear bucket `conectatech-ses-incoming-emails` con bucket policy para SES
- IAM: crear rol `conectatech-email-forwarder-role` (Lambda trust + S3 GetObject + SES SendRawEmail)
- Lambda: escribir `index.mjs` (Node.js 24.x) con FORWARD_MAP de `docs/email/redireccion-emails.md` → desplegar como `conectatech-email-forwarder`
- SES: crear rule set `conectatech-rules`, activarlo, crear regla `forward-to-gmail`
- S3 trigger: `s3:ObjectCreated:*` en prefix `incoming/` → invoca Lambda

### Fase 3 — Moodle SMTP (≈20 min, requiere dominio verificado + credenciales humanas)
- ⚠️ **Bloqueo humano**: SMTP credentials deben generarse en SES Console → proporcionarlas al agente en tiempo de ejecución (no se guardan en el repo)
- Configurar Moodle via CLI: `smtphosts`, `smtpuser`, `smtppass`, `noreplyaddress = no-reply@conectatech.co`, `smtpsecure = tls`

**El agente se detiene aquí.** Fase 4 (salida del sandbox) requiere aprobación humana de AWS (24–48h). Fase 5 (pruebas y monitoreo) se ejecuta después.

**Archivos a crear:**
- `lambda/email-forwarder/index.mjs`
- `lambda/email-forwarder/package.json`

**Definición de done:**
- [ ] `aws ses get-identity-verification-attributes` → `VerificationStatus: Success` para `conectatech.co`
- [ ] `dig MX conectatech.co` resuelve a `inbound-smtp.us-east-1.amazonaws.com`
- [ ] Lambda `conectatech-email-forwarder` en estado `Active`
- [ ] Rule set `conectatech-rules` activo en SES
- [ ] Trigger S3 → Lambda configurado y verificado
- [ ] Moodle configurado con SMTP SES (validar con correo de prueba desde Admin → Servidor)

---

## Tarea 2 — [INFRA] Actualizar Moodle a la versión 5.2.x

**Origen:** PRD §6 (Alta) · Referencia: [Nuevas features 5.2](https://docs.moodle.org/502/en/New_features)

**Problema:** La instancia actual corre Moodle 5.1.3 (Build: 20260216). Hay que valorar la actualización a 5.2.x preservando la integridad de los datos y las funcionalidades personalizadas (plugin `ct_*`, tablas `mdl_ct_*`, rol `ct_gestor`, API REST propia).

**Qué hacer:**

### Paso 1 — Evaluación (antes de tocar el servidor)
- Revisar las [release notes de Moodle 5.2](https://docs.moodle.org/502/en/New_features) y el [upgrade guide](https://docs.moodle.org/dev/Upgrading)
- Verificar compatibilidad de PHP requerida para 5.2.x (actualmente PHP 8.4 en EC2)
- Identificar si alguna API o tabla Moodle que usamos fue modificada o deprecada en 5.2
- Revisar si los permisos `ct_gestor` (especialmente `moodle/user:editprofile`) se comportan igual

### Paso 2 — Backup previo
- Backup completo de la BD Moodle antes de cualquier cambio
- Confirmar que el backup de archivos está al día

### Paso 3 — Actualización en servidor
- Descargar Moodle 5.2.x en el servidor
- Reemplazar los archivos core (preservando `config.php` y plugins en `local/`)
- Ejecutar `admin/cli/upgrade.php --non-interactive`
- Ejecutar `admin/cli/purge_caches.php`
- Verificar que las tablas `mdl_ct_*` y el rol `ct_gestor` siguen intactos

**Archivos a modificar:** ninguno en el repo (la actualización es solo en el servidor).

**Definición de done:**
- [ ] Backup de BD confirmado antes de iniciar
- [ ] `admin/cli/upgrade.php` finaliza sin errores
- [ ] `https://conectatech.co` carga correctamente en 5.2.x
- [ ] El panel admin (`admin.conectatech.co`) funciona sin regresiones
- [ ] Rol `ct_gestor` con sus 22 capabilities sigue intacto
- [ ] Las tablas `mdl_ct_*` no tienen cambios de esquema inesperados
- [ ] La API REST propia (`/admin-api/*`) responde correctamente

---

## Historial de tareas completadas

| Fecha | Tarea | Descripción breve |
|---|---|---|
| 2026-04-14 | [FIX] Pipeline Markdown truncado | Fix `force_rollback` Moodle, `numsections` MAX(section), `question.name` 255 chars, `ensayo` variante adjunto |
| 2026-04-14 | [DOCS] Sistema de documentación | PRD.md, tech-specs.md, CLAUDE.md actualizado (OWASP + git flow), MEMORY.md, TODO.md |
| 2026-04-15 | [FEATURE] Previsualizador de contenido Markdown | Endpoint `POST /admin-api/markdown/preview`, árbol `p-tree` con drag & drop, dropzone, layout en dos filas (520px / 640px), reconstrucción de contenido en tiempo real |
| 2026-04-17 | [FIX] Árbol de preview y PobladorService | `items_ordered` en MarkdownParser para orden correcto de nodos; `hiddenTitle` derivado de `semantic-blocks.json`; `TreeDragDropService` en providers (fix drag-and-drop); dropzone a la izquierda, card destino condicional; `eliminarPlaceholdersVacios()` en PobladorService |
| 2026-04-25 | [FEATURE] Revisión sistema de pines + portal gestor | Vigencia por duración (3/6/12 meses desde activación), rol `ct_gestor` con 22 capabilities, portal gestor: colegios/grupos, pines, usuarios con edición de perfil y restablecimiento de contraseña, filtros por colegio/grupo/curso |

---

## Log del motor JIT

### 2026-04-14 — Cálculo inicial

**Comparación PRD vs MEMORY:**
- Sin gaps de seguridad activos en producción (las reglas OWASP están ahora en CLAUDE.md)
- Pendientes de Alta prioridad (PRD §6): Sección 0 de cursos finales, Reportes de progreso
- Pendientes de Media prioridad: tipos de pregunta GIFT, notificaciones, pines reutilizables

**Resultado:** Tarea 1 = Sección 0 (Alta, deuda técnica documentada en ADR-005). Tarea 2 = Reportes de progreso (Alta, siguiente feature de mayor valor para el negocio).

### 2026-04-14 — Revisión 1 (ajuste de prioridad por el usuario)

**Cambio:** El usuario añadió "Previsualizador de contenido Markdown" como nueva funcionalidad de Alta prioridad, ubicada al inicio del roadmap del PRD §6.

**Resultado:** Tarea 1 = Previsualizador Markdown (nueva, más prioritaria). Tarea 2 = Sección 0 (desplazada). Reportes de progreso queda fuera del TODO activo.

### 2026-04-15 — Revisión 2 (Previsualizador completado)

**Comparación PRD vs MEMORY:**
- Previsualizador de contenido Markdown: ✅ implementado y build verificado
- Pendientes de Alta prioridad (PRD §6): Sección 0 de cursos finales, Reportes de progreso

**Resultado:** Tarea 1 = Sección 0 de cursos finales (Alta, ADR-005). Tarea 2 = Reportes de progreso (Alta, siguiente feature de mayor valor).

### 2026-04-17 — Revisión 3 (Fixes del previsualizador completados)

**Comparación PRD vs MEMORY:**
- Previsualizador y sus fixes: ✅ en producción
- Sin nuevos gaps de seguridad
- Pendientes de Alta prioridad (PRD §6): Sección 0 de cursos finales, Reportes de progreso

**Resultado:** Sin cambio de prioridad — Tarea 1 = Sección 0 de cursos finales. Tarea 2 = Reportes de progreso.

### 2026-04-21 — Revisión 4 (Revisión sistema de pines — cliente activo)

**Cambios en esta sesión:**
- Solicitud de cliente: vigencia de pines por duración desde activación (3/6/12 meses)
- Nuevo rol Moodle `ct_gestor` (solo lectura + soporte a usuarios) en lugar de `teacher`
- Portal gestor extendido: colegios/grupos, pines, usuarios con edición de perfil y reset de contraseña

**Resultado:** PR #5 mergeada. Todo completo.

### 2026-04-25 — Revisión 5 (nuevas prioridades del cliente)

**Comparación PRD vs MEMORY:**
- ✅ Revisión sistema de pines: completado (PR #5)
- ✅ Previsualizador Markdown: completado
- 🆕 **Sistema de correos AWS**: nuevo, Alta prioridad — plan detallado en `docs/email/plan-trabajo-conectatech.md`
- 🆕 **Actualizar Moodle a 5.2.x**: nuevo, Alta prioridad
- 🆕 **Notificaciones por correo**: Alta prioridad, depende del sistema de correos
- ⏸ Sección 0 de cursos finales: se desplaza
- ⏸ Reportes de progreso: se desplaza

**Resultado:** Tarea 1 = Sistema de correos AWS Fases 1-2-3 (infra completa, independiente). Tarea 2 = Actualización Moodle 5.2.x (independiente, Alta prioridad). Notificaciones por correo entra al TODO en la próxima revisión, una vez el sistema de correos esté en producción.
