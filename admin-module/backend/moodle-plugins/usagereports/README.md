# local_usagereports

Fuente de datos personalizada para el Report Builder nativo de Moodle (*Sitio administración > Informes > Informes personalizados*) que expone métricas de uso de la plataforma — visualizaciones, actividad de estudiantes y creación de recursos — agrupables por Institución, Rol y Curso.

Ver especificación completa en `docs/local_usagereports-especificacion.md` del repo principal y ADR-011 en `MEMORY.md`.

**Restricción de diseño:** ningún plugin de terceros (comercial ni comunitario). Esta es una extensión estándar de la Report Builder API de Moodle, igual que la usa `mod_attendance` para su propio reporte.

## Estado de implementación

- [x] Fase 0 — Auditoría real de eventos contra `mdl_logstore_standard_log`
- [x] Fase 1 — Esqueleto del plugin (`version.php`)
- [x] Fase 2 — `config/usage-events.json` poblado con eventos confirmados
- [ ] Fase 3 — Entidad (`classes/local/entities/usage_event.php`) y datasource (`classes/reportbuilder/datasource/usage_report.php`)
- [ ] Fase 4 — Validar en Moodle 5.2 si el generador visual agrupa/cuenta por Institución+Rol+Curso+Tipo de evento (riesgo conocido `MDL-76392`), o si hay que exponer fila-por-evento y resolver el conteo en tabla dinámica sobre el CSV/Excel exportado
- [ ] Fase 5 — Deploy en producción bajo ventana de mantenimiento (no hay staging, ver ADR-006) + configuración del informe en la UI (columnas, filtros, Audiencia, Schedule mensual)
- [ ] Fase 6 — `lang/es/local_usagereports.php` y `lang/en/local_usagereports.php`, `db/access.php` si se requieren capacidades específicas para ver el reporte

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
