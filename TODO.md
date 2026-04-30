# TODO.md — Motor JIT · ConectaTech.co
> Siempre exactamente 2 tareas atómicas · Última actualización: 2026-04-27 (rev. 8)

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

## Tarea 1 — [FEATURE] Reportes de progreso

**Origen:** PRD §6 (Alta)

**Problema:** No existe forma de ver el progreso de los estudiantes dentro del panel admin. Los gestores y el equipo ConectaTech no pueden saber qué estudiantes han completado cursos o actividades, ni generar reportes para los colegios.

**Qué hacer:**

### Paso 1 — Endpoint de progreso por paquete/pin
En `handlers/reportes.php`, agregar un endpoint que consulte `mdl_course_completions` y devuelva el progreso por paquete: estudiantes matriculados, completados, en progreso, por organización/colegio.

### Paso 2 — Vista de reporte en el panel admin
En `admin-module/frontend`, agregar una vista bajo `/pines/reporte` (o ruta nueva) que muestre el progreso en una tabla con filtros por organización, paquete y estado de completitud.

**Archivos a modificar / crear:**
1. `admin-module/api/handlers/reportes.php` — nuevo endpoint de progreso
2. `admin-module/frontend/src/app/features/pines/pines-reporte/` — componente de reporte

**Definición de done:**
- [ ] El endpoint devuelve progreso por paquete consultando `mdl_course_completions`
- [ ] La vista muestra tabla con estudiantes y su estado por curso
- [ ] Filtros por organización y paquete funcionan
- [ ] Los datos son consistentes con los que se ven en Moodle directamente

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
