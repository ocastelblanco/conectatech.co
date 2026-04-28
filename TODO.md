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

## Tarea 1 — [FEATURE] Sección 0 de cursos finales

**Origen:** PRD §6 (Alta) · ADR-005 · Deuda técnica desde despliegue inicial de árboles curriculares

**Problema:** Al desplegar un árbol curricular, los cursos finales se crean sin contenido en su sección 0 (portada/bienvenida). Los estudiantes que acceden al curso ven una sección vacía. El editor de árboles en el panel admin no tiene UI para definir ese contenido por curso.

**Qué hacer:**

### Paso 1 — Editor de árboles: campo de sección 0 por curso final
En el editor de árboles curriculares (`admin-module/frontend`), agregar un campo de texto enriquecido (o Markdown) por cada nodo de tipo "curso final" que permita definir el contenido de bienvenida. Este contenido se guarda como parte del JSON del árbol.

### Paso 2 — Pipeline: poblar sección 0 al desplegar
En `PobladorService` (o el servicio equivalente de despliegue), al crear cada curso final, usar la API de Moodle para insertar el contenido de la sección 0 si el árbol lo define.

**Archivos a modificar / crear:**
1. `admin-module/frontend/src/app/features/arboles/` — UI del editor (campo por curso final)
2. `admin-module/backend/lib/PobladorService.php` (o equivalente) — poblar sección 0 al desplegar
3. Posiblemente el schema JSON del árbol si no tiene campo para sección 0

**Definición de done:**
- [ ] El editor de árboles muestra un campo de contenido por cada curso final
- [ ] Al desplegar el árbol, los cursos finales tienen su sección 0 poblada con ese contenido
- [ ] Los árboles sin contenido de sección 0 se despliegan igual que antes (retrocompatibilidad)
- [ ] El campo es opcional; no bloquea el despliegue si está vacío

---

## Tarea 2 — [FEATURE] Reportes de progreso

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
