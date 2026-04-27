# TODO.md — Motor JIT · ConectaTech.co
> Siempre exactamente 2 tareas atómicas · Última actualización: 2026-04-25 (rev. 6)

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

## Tarea 1 — [FEATURE] Notificaciones por correo

**Origen:** PRD §6 (Alta) · Prerequisito completado: sistema de correos AWS con SES + SMTP Moodle

**Problema:** Actualmente no hay comunicación automática por correo en los dos eventos clave del negocio: cuando el administrador crea un paquete de pines para una organización (el gestor no se entera) y cuando un usuario activa su pin (no recibe confirmación).

**Qué hacer:**

### Notificación 1 — Gestor: nuevo paquete disponible
En `PinesService::crearPaquete()`, después de insertar el paquete en BD:
- Buscar todos los gestores activos de esa organización (`ct_gestor.moodle_userid`)
- Enviar email con `email_to_user()` de Moodle indicando cantidad de pines, rol y que pueden verlos en `/gestor/pines`

### Notificación 2 — Usuario: pin activado exitosamente
En `ActivacionService::activarPin()`, después de matricular al usuario:
- Obtener el usuario Moodle (`$DB->get_record('user', ['id' => $userId])`)
- Enviar email de confirmación con nombre del curso y fecha de vigencia (`expires_at`)

### Implementación
Crear `admin-module/backend/lib/EmailService.php` con métodos estáticos que usan `email_to_user()` de Moodle. Envolver cada llamada en `try-catch` para que el fallo de email nunca rompa el flujo principal.

**Archivos a modificar / crear:**
1. `admin-module/backend/lib/EmailService.php` — **Nuevo**
2. `admin-module/backend/lib/PinesService.php` — llamar `EmailService::notificarPaqueteCreado()`
3. `admin-module/backend/lib/ActivacionService.php` — llamar `EmailService::notificarPinActivado()`

**Definición de done:**
- [ ] Al crear un paquete, el/los gestor(es) de la org reciben un email en su correo Moodle
- [ ] Al activar un pin, el usuario recibe email de confirmación con nombre del curso y vigencia
- [ ] Si el envío falla (p.ej. sandbox SES), la operación principal (crear paquete / activar pin) sigue funcionando sin error

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
| 2026-04-25 | [INFRA] Sistema de correos AWS | SES dominio+DKIM+MX, Lambda forwarder nodejs24.x con FORWARD_MAP, rule set SES, trigger S3→Lambda, Moodle SMTP configurado, 3 alarmas CloudWatch |

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

### 2026-04-25 — Revisión 5 (nuevas prioridades del cliente — sistema de correos)

**Comparación PRD vs MEMORY:**
- ✅ Revisión sistema de pines: completado (PR #5)
- ✅ Previsualizador Markdown: completado
- 🆕 **Sistema de correos AWS**: nuevo, Alta prioridad — plan detallado en `docs/email/plan-trabajo-conectatech.md`
- 🆕 **Actualizar Moodle a 5.2.x**: nuevo, Alta prioridad
- 🆕 **Notificaciones por correo**: Alta prioridad, depende del sistema de correos
- ⏸ Sección 0 de cursos finales: se desplaza
- ⏸ Reportes de progreso: se desplaza

**Resultado:** Tarea 1 = Sistema de correos AWS Fases 1-2-3 (infra completa, independiente). Tarea 2 = Actualización Moodle 5.2.x (independiente, Alta prioridad). Notificaciones por correo entra al TODO en la próxima revisión, una vez el sistema de correos esté en producción.

### 2026-04-25 — Revisión 6 (sistema de correos completado)

**Cambios en esta sesión:**
- ✅ Sistema de correos AWS completado: SES + DKIM + MX + Lambda forwarder + Moodle SMTP
- Inbound routing probado y funcionando (`conectatech-email-forwarder` procesando y reenviando)
- 3 alarmas CloudWatch activas (Lambda errors, bounce rate, complaint rate)
- Pendiente: salida del sandbox SES (Fase 4, aprobación humana 24–48h) y confirmación `ajumoto@gmail.com`

**Comparación PRD vs MEMORY:**
- ✅ Sistema de correos AWS: completado
- 🎯 **Notificaciones por correo**: Alta prioridad, prerequisito satisfecho
- ⏳ Actualizar Moodle 5.2.x: Alta prioridad, independiente
- ⏸ Sección 0 de cursos finales: pausada
- ⏸ Reportes de progreso: pausada

**Resultado:** Tarea 1 = Notificaciones por correo (2 eventos: paquete creado → gestor; pin activado → usuario). Tarea 2 = Actualización Moodle 5.2.x (independiente, sin bloqueos).
