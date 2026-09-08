# TODO.md — Motor JIT · ConectaTech.co
> Siempre exactamente 2 tareas atómicas · Última actualización: 2026-09-07 (rev. 15)

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

## Tarea 1 — [FEATURE] Renovación y reutilización de pines

**Origen:** PRD §6 (Media)

**Problema:** Cuando un estudiante completa su curso, el pin queda "usado" y no se puede reutilizar. El administrador no tiene forma de recuperar pines de cursos completados y reasignarlos a nuevos estudiantes, lo que genera costo innecesario de nuevos pines.

**Qué hacer:**

### Paso 1 — Endpoint de renovación
En `handlers/pines.php`, agregar un endpoint `POST /pines/{id}/renovar` que, dado un pin activado cuyo estudiante haya completado el curso, lo marque como disponible nuevamente (desmatricula al estudiante anterior y resetea el estado del pin).

### Paso 2 — UI en el panel de pines
En el componente de pines del gestor, añadir un botón "Renovar" visible solo en pines con estado `usado` + curso completado, con confirmación antes de ejecutar.

**Archivos a modificar / crear:**
1. `admin-module/api/handlers/pines.php` — endpoint de renovación
2. `admin-module/frontend/src/app/features/pines/` — botón de renovación en tabla

**Definición de done:**
- [ ] El endpoint desmatricula al estudiante anterior y cambia el estado del pin a disponible
- [ ] El botón solo aparece para pines que califican (usados + curso completado)
- [ ] Confirmación antes de ejecutar la renovación
- [ ] El pin renovado puede ser activado por un nuevo estudiante sin errores

---

## Tarea 2 — [FEATURE] Tipos de pregunta adicionales

**Origen:** PRD §6 (Media)

**Problema:** El pipeline Markdown soporta actualmente solo opción múltiple y ensayo. Los autores de contenido necesitan más variedad para construir evaluaciones completas.

**Qué hacer:**

### Paso 1 — Parser: reconocer nuevos tipos en el Markdown
En `MarkdownParser.php` y `GiftConverter.php`, añadir soporte para las sintaxis de verdadero/falso, emparejamiento, respuesta corta y numérica según el formato GIFT de Moodle.

### Paso 2 — Preview: iconos diferenciados en el árbol
En `ContenidoComponent`, añadir los nuevos `nodeType` al método `getNodeIcon()` para que el árbol de estructura muestre íconos distintos por tipo de pregunta.

**Archivos a modificar / crear:**
1. `admin-module/backend/lib/MarkdownParser.php` — reconocer nuevas sintaxis
2. `admin-module/backend/lib/GiftConverter.php` — generar GIFT para los nuevos tipos
3. `admin-module/frontend/src/app/features/contenido/contenido.component.ts` — iconos del preview

**Definición de done:**
- [ ] Verdadero/falso se procesa correctamente en GIFT
- [ ] Respuesta corta se procesa correctamente en GIFT
- [ ] Los nuevos tipos aparecen en el árbol de estructura con ícono diferenciado
- [ ] Los tipos existentes (opción múltiple, ensayo) no se ven afectados

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
| 2026-04-27 | [FEATURE] Notificaciones por correo | `EmailService.php` con `notificarPaqueteCreado()` y `notificarPinActivado()`; integrado en `PinesService` y `ActivacionService`; fecha en español vía `IntlDateFormatter`; CRLF correcto en Lambda forwarder |
| 2026-04-27 | [INFRA] Actualización Moodle 5.1.3 → 5.2 | Plugin `local_conectatech` desinstalado; upgrade limpio vía GitHub archive + composer install; `qtype_random` huérfano eliminado; todas las tablas `mdl_ct_*` y rol `ct_gestor` (22 capabilities) intactos |
| 2026-04-30 | [FEATURE] Sección 0 de cursos finales | UI por nodo de curso final en editor de árboles; `PobladorService` pobla sección 0 al desplegar; retrocompatible con árboles sin contenido definido |
| 2026-05-14 | [FEATURE] Dashboard + panel de instituciones | Dashboard con tabs por track comercial (instituciones/organizaciones/cursos); CRUD de instituciones directas (Track A); conteos reales via `path` de categorías Moodle; script de limpieza `limpiar-cms-huerfanos.php` con cron diario |
| 2026-09-07 | [FEATURE] `local_usagereports` — Fase 0-2 | Auditoría real contra `mdl_logstore_standard_log` (30 días); hallazgo: `course_module_viewed` no se dispara (0 eventos), se usa `section_viewed` en su lugar; esqueleto del plugin (`version.php`) y `config/usage-events.json` poblado con eventos confirmados; `README.md` del plugin documenta la auditoría |
| 2026-09-07 | [FEATURE] `local_usagereports` — Fase 3 | Entidad `usage_event` y datasource `usage_report` para el Report Builder; ruta real de clases corregida (`classes/reportbuilder/local/entities/`, no `classes/local/entities/` como asumía la especificación); reutiliza entidades core `user`/`course` (mismo patrón que `core_role\reportbuilder\datasource\roles`); clasificación de eventos vía `CASE` SQL dinámico con parámetros con nombre; `lang/en` y `lang/es` completos |
| 2026-09-07 | [FEATURE] `local_usagereports` — Fase 4 | Plugin instalado en producción bajo ventana de mantenimiento; validación real en la UI del Report Builder: agrupar/contar por Institución+Rol+Curso+Tipo de evento funciona sin errores (`MDL-76392` no bloquea este datasource); hallazgo: no agrupar por Fecha (granularidad de minuto) — usarla como filtro de rango en su lugar; usuario admin temporal y reporte de prueba eliminados al terminar |
| 2026-09-07 | [FEATURE] `local_usagereports` — Fase 5 (cierre) | Informe real "Uso de la plataforma" creado por el cliente siguiendo las instrucciones (columnas agregadas por Institución+Rol+Curso+Tipo de evento, Fecha como filtro, Audiencia y Schedule mensual solo para el admin de ConectaTech, formato CSV). Feature completa de punta a punta |

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

### 2026-04-27 — Revisión 7 (notificaciones por correo completadas)

**Cambios en esta sesión:**
- ✅ Notificaciones por correo completadas: `EmailService.php` creado, integrado en `PinesService` y `ActivacionService`
- ✅ Fixes adicionales: fecha de vigencia en español correcto, descripción de rol correcta, CRLF en Lambda forwarder
- PR #6 mergeada. `main` al día, ramas limpiadas.

**Comparación PRD vs MEMORY:**
- ✅ Sistema de correos AWS: completado
- ✅ Notificaciones por correo: completado (PR #6)
- 🎯 **Actualizar Moodle a 5.2.x**: Alta prioridad, sin bloqueos
- 🎯 **Sección 0 de cursos finales**: Alta prioridad, deuda técnica desde v1
- ⏸ Reportes de progreso: pausada

**Resultado:** Tarea 1 = Actualizar Moodle a 5.2.x. Tarea 2 = Sección 0 de cursos finales (retoma prioridad después de la actualización).

### 2026-04-27 — Revisión 8 (Moodle 5.2 upgrade completado)

**Cambios en esta sesión:**
- ✅ Plugin `local_conectatech` desinstalado del servidor (nunca estuvo en el repo)
- ✅ Moodle actualizado de 5.1.3 → 5.2 (Build: 20260420) — upgrade limpio con directorio fresco
- ✅ Todas las tablas `mdl_ct_*` intactas; rol `ct_gestor` con 22 capabilities; API REST operativa
- 🐛 Gotcha documentado: `cp -a` overlay deja archivos huérfanos de versiones anteriores (`qtype_random`); el método correcto para upgrades mayores es directorio limpio + mover `config.php`

**Comparación PRD vs MEMORY:**
- ✅ Actualización Moodle 5.2: completada
- 🎯 **Sección 0 de cursos finales**: Alta prioridad, deuda técnica desde v1
- 🎯 **Reportes de progreso**: Alta prioridad, siguiente feature de valor

**Resultado:** Tarea 1 = Sección 0 de cursos finales. Tarea 2 = Reportes de progreso.

### 2026-05-14 — Revisión 9 (Dashboard + panel de instituciones completado)

**Cambios en esta sesión:**
- ✅ Dashboard con tabs por track comercial: instituciones (Track A), organizaciones (Track B), cursos cross-cutting
- ✅ Panel de instituciones: CRUD completo, categorías filtradas a hijas de `COLEGIOS`, conteos reales via `course_categories.path`
- ✅ Script `limpiar-cms-huerfanos.php` con cron diario (3AM) — previene corrupción de course_modules
- ⏸ Reportes de progreso (estadísticas detalladas por estudiante/curso): explícitamente diferido por el usuario — "por ahora no vamos a usar esas estadísticas"

**Comparación PRD vs MEMORY:**
- ✅ Dashboard básico con métricas por institución/organización: completado
- ⏸ Reportes de progreso detallados (completitud, calificaciones, actividad): diferido sin fecha
- 🎯 **Tipos de pregunta adicionales**: Media prioridad, siguiente feature de valor para contenido
- 🎯 **Renovación y reutilización de pines**: Media prioridad, reduce costo operativo

**Resultado:** Tarea 1 = Tipos de pregunta adicionales. Tarea 2 = Renovación y reutilización de pines.

### 2026-04-28 — Revisión 9 (Boost Union + estilos login)

**Cambios en esta sesión:**
- ✅ Boost Union actualizado v5.1-r8 → v5.1-r10; SCSS recompilando correctamente (1.27 MB)
- ✅ Fix SCSS Moodle 5.2: `$white`, `$black`, `$logincontainer-shadow` añadidos al `scsspre` con `!default`
- ✅ Fix selector login: `.login-form, .card` → `#theme_boost_union-loginform` (el contenedor visual real)
- ✅ `moodle_old` eliminado del servidor (746 MB liberados)
- ✅ PRs #7 y #8 fusionadas y ramas limpiadas

**Comparación PRD vs MEMORY:**
- Sin cambio de prioridades — todo lo de esta sesión fue infra/fix

**Resultado:** Sin cambio — Tarea 1 = Sección 0 de cursos finales. Tarea 2 = Reportes de progreso.

### 2026-04-30 — Revisión 10 (Sección 0 de cursos finales completada)

**Cambios en esta sesión:**
- ✅ Sección 0 de cursos finales: UI por nodo en editor de árboles + `PobladorService` pobla sección 0 al desplegar

**Comparación PRD vs MEMORY:**
- ✅ Sección 0 de cursos finales: completada
- 🎯 **Reportes de progreso**: Alta prioridad, siguiente feature de valor para el negocio
- 🎯 **Tipos de pregunta adicionales**: Media prioridad, enriquece el pipeline de contenido

**Resultado:** Tarea 1 = Reportes de progreso. Tarea 2 = Tipos de pregunta adicionales.

### 2026-09-07 — Revisión 11 (nueva prioridad del cliente — reportes de uso de la plataforma)

**Cambios en esta sesión:**
- Skill `slim-readme`: symlink externo reemplazado por fuente real dentro del repo (PR #25, fusionada)
- Cliente entregó `docs/local_usagereports-especificacion.md` y aprobó el plan de acción propuesto (ver ADR-011 en MEMORY.md): plugin `local_usagereports` vía Report Builder nativo de Moodle, sin plugins de terceros, para el informe recurrente mensual de uso (visualizaciones/actividad/creación por Institución/Rol/Curso)

**Comparación PRD vs MEMORY:**
- 🆕 **Reportes de uso de la plataforma**: nuevo, Alta prioridad — aprobado explícitamente por el cliente en esta sesión, con plan detallado ya validado
- ⏸ Reportes de progreso (completitud/calificaciones): sigue diferido sin fecha por decisión del cliente
- ⏸ Tipos de pregunta adicionales y Renovación de pines: Media prioridad, se desplazan

**Resultado:** Tarea 1 = Reportes de uso de la plataforma — Fase 0-2 (auditoría de eventos reales + esqueleto del plugin + `usage-events.json`). Tarea 2 = Tipos de pregunta adicionales (desplazada, retoma como siguiente en cuanto se libere un slot).

### 2026-09-07 — Revisión 12 (Fase 0-2 de `local_usagereports` completada, misma sesión)

**Cambios en esta sesión:**
- ✅ Auditoría SQL ejecutada contra la BD real de producción (RDS, solo lectura) — resultados y hallazgos documentados en `admin-module/backend/moodle-plugins/usagereports/README.md`
- ✅ Esqueleto del plugin creado (`version.php`, `config/usage-events.json` con los eventos confirmados, `README.md`)
- 🐛 Gotcha operativo: SSH dio timeout por IP local cambiada — Security Group `sg-039bcb1cb3a57db7f` actualizado con la nueva IP (ver sección "Security Group SSH" en MEMORY.md del sistema de memoria)

**Comparación PRD vs MEMORY:**
- ✅ Fase 0-2 de `local_usagereports`: completada
- 🎯 **Fase 3 (entidad + datasource Report Builder)**: siguiente paso natural, sin bloqueos
- ⏳ Tipos de pregunta adicionales: Media prioridad, sigue desplazada

**Resultado:** Tarea 1 = Reportes de uso de la plataforma — Fase 3 (entidad + datasource). Tarea 2 = Tipos de pregunta adicionales.

### 2026-09-07 — Revisión 13 (Fase 3 de `local_usagereports` completada, misma sesión)

**Cambios en esta sesión:**
- ✅ Entidad `usage_event` y datasource `usage_report` implementados y verificados con `php -l`
- 🐛 Corrección de la especificación: la ruta real de las entidades del Report Builder en Moodle 5.2 es `classes/reportbuilder/local/entities/`, no `classes/local/entities/` — confirmado leyendo el código fuente de plugins core (`admin/roles`, `admin`) directamente en el servidor antes de escribir código, no por suposición
- Decisión de diseño: el datasource reutiliza las entidades core `user` y `course` (columnas `user:institution`, `course:fullname`, filtro `course:courseselector`) en vez de reimplementar esos joins — mismo patrón que `core_role\reportbuilder\datasource\roles`
- `lang/en/local_usagereports.php` y `lang/es/local_usagereports.php` completos, verificados contra todos los `get_string()`/`lang_string()` usados

**Comparación PRD vs MEMORY:**
- ✅ Fase 3 de `local_usagereports`: completada
- 🎯 **Fase 4-5 (validación de agregación + deploy en producción)**: siguiente paso, requiere instalar el plugin por primera vez
- ⏳ Tipos de pregunta adicionales: Media prioridad, sigue desplazada

**Resultado:** Tarea 1 = Reportes de uso de la plataforma — Fase 4-5 (instalar, validar agregación `MDL-76392`, configurar el informe con Schedule mensual). Tarea 2 = Tipos de pregunta adicionales.

### 2026-09-07 — Revisión 14 (Fase 4 completada, misma sesión — plugin instalado y validado en producción)

**Cambios en esta sesión:**
- ✅ Plugin instalado en `local/usagereports/` en producción bajo ventana de mantenimiento; `upgrade.php --non-interactive` sin errores
- ✅ Validación en la UI real del Report Builder (con usuario admin temporal provisto por el cliente, eliminado al terminar): agrupar/contar por Institución+Rol+Curso+Tipo de evento **funciona correctamente** — `MDL-76392` no es un bloqueante para este datasource
- 🐛 Hallazgo de diseño: la columna Fecha tiene granularidad de minuto — agruparla junto con las demás produce casi un grupo por evento; se debe usar como filtro de rango, no como columna agrupada, en el informe real
- Verificación adicional previa a la UI: se ejercitó el datasource vía CLI (`manager::get_report_from_persistent()`) para confirmar ausencia de errores fatales antes de arriesgar la instancia de producción

**Comparación PRD vs MEMORY:**
- ✅ Fase 0-4 de `local_usagereports`: completada — validación técnica end-to-end exitosa
- 🎯 **Fase 5 (informe real con Audiencia y Schedule)**: bloqueada en una decisión del cliente (destinatarios del correo mensual, formato CSV/Excel) — no es una tarea técnica
- ⏳ Tipos de pregunta adicionales: Media prioridad, sigue desplazada

**Resultado:** Tarea 1 = Reportes de uso de la plataforma — Fase 5 (crear el informe real, bloqueada en decisión de destinatarios). Tarea 2 = Tipos de pregunta adicionales.

### 2026-09-07 — Revisión 15 (`local_usagereports` completado de punta a punta)

**Cambios en esta sesión:**
- ✅ Cliente confirmó destinatario (solo admin de ConectaTech) y formato (CSV)
- ✅ Cliente creó el informe real "Uso de la plataforma" siguiendo las instrucciones: columnas Institución/Rol/Curso/Tipo de evento con agregación "Cuenta", Fecha removida como columna (usada solo como filtro de rango), Audiencia restringida al admin, Schedule mensual activo
- Pregunta de seguimiento del cliente resuelta: exportación bajo demanda vía el botón "Exportar" en la vista del informe (independiente del schedule mensual)

**Comparación PRD vs MEMORY:**
- ✅ **Reportes de uso de la plataforma (`local_usagereports`)**: completado de punta a punta (Fases 0-5)
- 🎯 **Renovación y reutilización de pines**: Media prioridad, siguiente feature de valor (reduce costo operativo)
- ⏳ Tipos de pregunta adicionales: Media prioridad, sigue en el TODO

**Resultado:** Tarea 1 = Renovación y reutilización de pines. Tarea 2 = Tipos de pregunta adicionales.
