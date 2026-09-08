# local_usagereports

Fuente de datos personalizada para el Report Builder nativo de Moodle (*Sitio administración > Informes > Informes personalizados*) que expone métricas de uso de la plataforma — visualizaciones, actividad de estudiantes y creación de recursos — agrupables por Institución, Rol y Curso.

Ver especificación completa en `docs/local_usagereports-especificacion.md` del repo principal y ADR-011 en `MEMORY.md`.

**Restricción de diseño:** ningún plugin de terceros (comercial ni comunitario). Esta es una extensión estándar de la Report Builder API de Moodle, igual que la usa `mod_attendance` para su propio reporte.

## Estado de implementación

- [x] Fase 0 — Auditoría real de eventos contra `mdl_logstore_standard_log`
- [x] Fase 1 — Esqueleto del plugin (`version.php`)
- [x] Fase 2 — `config/usage-events.json` poblado con eventos confirmados
- [x] Fase 3 — Entidad (`classes/reportbuilder/local/entities/usage_event.php`) y datasource (`classes/reportbuilder/datasource/usage_report.php`)
- [ ] Fase 4 — Validar en Moodle 5.2 si el generador visual agrupa/cuenta por Institución+Rol+Curso+Tipo de evento (riesgo conocido `MDL-76392`), o si hay que exponer fila-por-evento y resolver el conteo en tabla dinámica sobre el CSV/Excel exportado
- [ ] Fase 5 — Deploy en producción bajo ventana de mantenimiento (no hay staging, ver ADR-006) + configuración del informe en la UI (columnas, filtros, Audiencia, Schedule mensual)
- [ ] Fase 6 — `db/access.php` si se requieren capacidades específicas para ver el reporte (a evaluar tras la Fase 5, según quién deba consumir el informe)

## Fase 3 — Entidad y datasource (2026-09-07)

### Corrección de la ruta real de las clases

La especificación original proponía `classes/local/entities/usage_event.php`. La ruta **real** que usa Moodle 5.2 para las entidades del Report Builder es `classes/reportbuilder/local/entities/`, confirmada leyendo el código fuente de plugins core equivalentes en el servidor de producción (`admin/roles/classes/reportbuilder/local/entities/role.php`, `admin/classes/reportbuilder/local/entities/task_log.php`). Se usó esa ruta real, no la del documento.

### Reutilización de entidades core en vez de reimplementar joins

En vez de que `usage_event` resuelva Institución (join a `mdl_user`) y Curso (join a `mdl_course`) con SQL propio, el **datasource** reutiliza las entidades ya existentes de Moodle core `\core_reportbuilder\local\entities\user` (columna `user:institution`) y `\core_reportbuilder\local\entities\course` (columna `course:fullname`), uniéndolas por `userid`/`courseid` del log — exactamente el mismo patrón que usa `\core_role\reportbuilder\datasource\roles` para unir la entidad `user` genérica a `role_assignments`. Esto evita duplicar lógica de formato/links ya resuelta en el núcleo y reduce el código propio a mantener.

La entidad `usage_event` sólo resuelve lo que es específico de este reporte: `mdl_logstore_standard_log` (Tipo de evento, Fecha) y el join a `mdl_context` → `mdl_role_assignments` → `mdl_role` restringido a `student`/`editingteacher` (columna y filtro Rol).

### Filtro de curso: `course:courseselector`, no `course:fullname`

La especificación pedía un filtro de Curso "autocomplete". La entidad core `course` expone justo eso como filtro `courseselector` (selector de curso con autocompletado), separado de la columna `course:fullname`. Se usa `course:courseselector` como filtro por defecto.

### Filtro de Institución: texto libre, no select

La especificación asumía un filtro de Institución tipo `select`. La entidad core `user` no tiene un callback de opciones para `institution` (a diferencia de, por ejemplo, `country` o `lang`, que sí lo tienen) — es un campo de texto libre en el perfil de usuario, sin lista fija de valores en el esquema de Moodle. Se usa el filtro `text` que la entidad ya provee (`user:institution`), que en la práctica es más útil que un select para un campo sin enumeración fija.

### Clasificación de eventos: SQL `CASE` dinámico con parámetros con nombre

`usage_events_config::get_categories()` carga y cachea `config/usage-events.json` (vía `core_component::get_component_directory()`, no una ruta relativa hardcodeada). La entidad construye un `CASE WHEN eventname IN (:p1, :p2, ...) THEN 'visualizacion' ...` usando `$DB->get_in_or_equal(..., SQL_PARAMS_NAMED, ...)` para cada categoría — los `eventname` del JSON nunca se concatenan directo en el SQL. La restricción de rol (`student`/`editingteacher`) sí se embebe como literal SQL directo en el `JOIN`, porque son 2 constantes fijas de la clase (no vienen de `usage-events.json` ni de input externo) y la API de joins de Report Builder (`join_trait::add_join()`) no admite parámetros con nombre.

### Alcance no cubierto en esta fase

Falta validar en el generador visual de Moodle si el agrupado/conteo por Institución+Rol+Curso+Tipo de evento funciona directamente o si hay que exponer fila-por-evento (Fase 4), y el deploy real en producción (Fase 5).

## Auditoría de eventos (2026-09-07)

Consulta ejecutada contra la BD de producción (RDS MariaDB, solo lectura, sin instalar nada), ventana de 30 días:

```sql
SELECT eventname, crud, edulevel, COUNT(*) AS total
FROM mdl_logstore_standard_log
WHERE timecreated > UNIX_TIMESTAMP(NOW() - INTERVAL 30 DAY)
GROUP BY eventname, crud, edulevel
ORDER BY total DESC;
```

### Hallazgos relevantes

| Evento | Total (30 días) | Nota |
|---|---|---|
| `\core\event\course_viewed` | 1946 | Visualización de la página principal de un curso |
| `\core\event\section_viewed` | 48 | Visualización de una sección/subsección de contenido |
| `\mod_quiz\event\course_module_viewed` | 22 | Visualización específica de la página de un cuestionario |
| `\core\event\course_module_created` | 400 | Creación de un módulo/recurso (label, quiz, subsection, etc.) |
| `\core\event\course_section_created` | 385 | Creación de una sección de curso (el pipeline Markdown crea una por cada H1) |
| `\mod_quiz\event\attempt_submitted` | 3 | Envío de un intento de cuestionario |

**Hallazgo importante — `\core\event\course_module_viewed` no aparece en absoluto (0 eventos en 30 días).** La lista candidata original del documento de especificación asumía este evento como la visualización genérica de contenido, pero la estructura de curso de ConectaTech (secciones/subsecciones vía `mod_subsection`, no actividades individuales) dispara `\core\event\section_viewed` en su lugar. Se usa `section_viewed` como el evento real de "visualización de contenido", y se mantiene `\mod_quiz\event\course_module_viewed` aparte para la vista específica de un cuestionario.

**Decisión — se incluye `\core\event\course_section_created` en `creacion`.** El documento original solo consideraba `course_module_created`, pero la creación de secciones (385 eventos en 30 días, generadas por el pipeline Markdown al crear cada H1) es una señal de "creación de recurso" igual de válida y mucho más frecuente. Si se prefiere contar solo módulos (labels, quizzes) y no la estructura de secciones, quitar esta línea de `usage-events.json`.

**Ruido descartado — eventos de `tool_recyclebin`.** `course_bin_item_created` (353) y `course_bin_item_deleted` (408) son generados por el cron diario `limpiar-cms-huerfanos.php` (ver MEMORY.md), no por actividad real de usuarios. No se incluyen en ninguna categoría — son un namespace de evento distinto (`\tool_recyclebin\event\*`) al de `\core\event\course_module_created/deleted`, así que no hay riesgo de que se cuenten por error.

**Sin eventos de otros tipos de actividad todavía.** No aparecen eventos de `mod_assign`, `mod_h5pactivity`, etc. — consistente con que el contenido actual son solo cuestionarios GIFT y áreas de texto/medios. Si en el futuro se usan esos módulos, agregar sus eventos de "submitted"/"answered" a la categoría `actividad` en `usage-events.json`, sin tocar código PHP.

## Cómo editar `usage-events.json`

Agregar el `eventname` completo (con namespace) a la categoría correspondiente (`visualizacion`, `actividad`, `creacion`). El plugin recarga el archivo en tiempo de ejecución — no requiere reinstalar ni tocar la entidad/datasource.
