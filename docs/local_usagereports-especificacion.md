# Especificación técnica: `local_usagereports`

**Plugin:** Fuente de datos personalizada para el Report Builder nativo de Moodle, que expone métricas de uso de la plataforma (visualizaciones, respuestas a actividad, creación de recursos) agrupables por Institución, Rol y Curso.

**Destino en el repo:** `conectatech.co/admin-module/` (o repo propio `local_usagereports/`, a decidir según convención actual del proyecto).

**Documento de referencia relacionado:** `CLAUDE-admin-module.md` (entry point de Claude Code para este proyecto). Este documento complementa, no reemplaza, `arquitectura-tecnica-definitiva.md`.

---

## 1. Contexto y objetivo

ConectaTech necesita un reporte recurrente (envío mensual por correo) dentro de Moodle, usando el sistema **nativo** de Informes personalizados (Report Builder, *Sitio administración > Informes > Informes personalizados*), que muestre:

- **Institución** (campo `Institution` del perfil de usuario, ya poblado para todos los usuarios)
- **Rol** (`student` o `editingteacher`, resuelto en el contexto del curso)
- **Curso**
- Tres contadores de actividad, definidos así (decisión explícita del cliente, reemplazando cualquier noción de "tiempo de sesión"):
  - **Visualizaciones**: un profesor o estudiante ingresa a un curso.
  - **Actividades**: un estudiante responde/envía una actividad de un curso.
  - **Creación**: un profesor crea un recurso en un curso.

### Restricción de diseño explícita

**No se debe usar ningún plugin de terceros** (ni comercial ni comunitario, tipo `block_dedication`, `report_customsql`, IntelliBoard, Edwiser, etc.). La solución debe ser código propio de ConectaTech, instalado y mantenido igual que el resto del `admin-module`.

### Hallazgo que motiva esta arquitectura

Las fuentes de datos nativas del Report Builder de Moodle 5.1 (*Custom reports*) son: Badges, Blogs, Cohorts, Comments, Competencies, Course categories, Course participants, Courses, Files, Groups, Notes, Roles, Tags, Task logs, User badges, Users. **Ninguna expone el registro de eventos** (`mdl_logstore_standard_log`), que es donde existen los datos de vistas, envíos de actividad y creación de recursos.

Sin embargo, Moodle expone oficialmente la **Report Builder API** para que cualquier plugin registre una fuente de datos nueva (`datasource`) con sus propias entidades, columnas y filtros. Esa fuente aparece y se comporta exactamente igual que las nativas: mismo generador visual, mismos filtros, misma exportación (CSV/Excel/PDF/etc.) y **la misma programación de envíos recurrentes por correo** (Schedules tab: hourly, daily, weekly, monthly, annually).

Este es el mismo patrón que usan plugins core-adjacent como `mod_attendance` para su propio reporte: las entidades van en `classes/local/entities/` y el datasource en `classes/reportbuilder/datasource/` del plugin. No es un plugin de reporting de terceros — es una extensión estándar y documentada del núcleo, que nosotros mismos escribimos y controlamos.

---

## 2. Paso previo obligatorio: auditoría de eventos reales

Antes de fijar la lista de eventos que cuentan como "visualización", "actividad" o "creación", **se debe auditar la tabla `mdl_logstore_standard_log` de la instancia real** para confirmar qué `eventname` se generan efectivamente con el contenido actual (recursos "Área de texto y medios" = `mod_label`/`mod_subsection`, cuestionarios GIFT = `mod_quiz`).

Consulta sugerida para la auditoría (ejecutar vía CLI/mysql, sin dejar instalado ningún plugin de reporting):

```sql
SELECT eventname, crud, edulevel, COUNT(*) AS total
FROM mdl_logstore_standard_log
WHERE timecreated > UNIX_TIMESTAMP(NOW() - INTERVAL 30 DAY)
GROUP BY eventname, crud, edulevel
ORDER BY total DESC;
```

Con el resultado, ajustar el archivo de configuración `usage-events.json` (sección 4) antes de dar por cerrada la implementación. La lista propuesta abajo es un punto de partida razonable, no definitivo.

**Candidatos iniciales conocidos** (a confirmar con la auditoría):

| Categoría | `eventname` candidatos |
|---|---|
| `visualizacion` | `\core\event\course_viewed`, `\core\event\course_module_viewed` |
| `actividad` | `\mod_quiz\event\attempt_submitted` (y cualquier otro evento de tipo "submitted"/"answered" que aparezca en la auditoría para módulos usados) |
| `creacion` | `\core\event\course_module_created` |

---

## 3. Modelo de datos

**Tabla base:** `mdl_logstore_standard_log` (alias `log`). Columnas relevantes ya disponibles sin joins adicionales: `userid`, `courseid`, `eventname`, `crud`, `edulevel`, `timecreated`.

**Joins requeridos:**

1. `mdl_user u ON u.id = log.userid` → para `u.institution` y datos de nombre/correo si se requieren como columna opcional.
2. `mdl_context ctx ON ctx.contextlevel = 50 AND ctx.instanceid = log.courseid` (50 = `CONTEXT_COURSE`) → puente hacia el rol.
3. `mdl_role_assignments ra ON ra.contextid = ctx.id AND ra.userid = log.userid` → asignación de rol del usuario en ese curso específico.
4. `mdl_role r ON r.id = ra.roleid AND r.shortname IN ('student', 'editingteacher')` → filtra a los dos roles de interés y descarta otros (manager, etc.).
5. `mdl_course c ON c.id = log.courseid` → nombre completo/corto del curso.

**Nota de integridad:** un usuario puede, en teoría, tener más de una asignación de rol en el mismo contexto de curso. Para los fines de este reporte (roles `student` / `editingteacher`, mutuamente excluyentes en la práctica de ConectaTech), no se requiere deduplicación adicional, pero el desarrollador debe verificarlo si aparecen filas duplicadas al probar.

**Clasificación del evento:** columna calculada (`CASE WHEN log.eventname IN (...) THEN 'visualizacion' WHEN log.eventname IN (...) THEN 'actividad' WHEN log.eventname IN (...) THEN 'creacion' END`), generada dinámicamente a partir del archivo de configuración externo (ver sección 4), no hardcodeada en el SQL fuente si es evitable — o, si el `reportbuilder` no permite fácilmente `CASE` dinámico desde config, al menos mantener las tres listas de eventos como constantes claramente documentadas y centralizadas en una sola clase/archivo, fáciles de editar.

---

## 4. Configuración externa de eventos (`usage-events.json`)

Siguiendo el mismo principio ya usado en el proyecto para `semantic-blocks.json` (config externa en vez de lógica hardcodeada), este plugin debe leer una lista editable de eventos desde un archivo de configuración, para que agregar un nuevo tipo de actividad (por ejemplo, si más adelante se usa `mod_assign` o `mod_h5pactivity`) no requiera tocar código PHP.

Ubicación sugerida: `local/usagereports/config/usage-events.json`

```json
{
  "visualizacion": [
    "\\core\\event\\course_viewed",
    "\\core\\event\\course_module_viewed"
  ],
  "actividad": [
    "\\mod_quiz\\event\\attempt_submitted"
  ],
  "creacion": [
    "\\core\\event\\course_module_created"
  ]
}
```

El plugin debe cargar este archivo en tiempo de ejecución (con manejo de errores si el archivo falta o tiene JSON inválido) y construir el `CASE`/filtro SQL a partir de él.

---

## 5. Estructura del plugin

```
local/usagereports/
├── classes/
│   ├── local/
│   │   └── entities/
│   │       └── usage_event.php        # Entidad Report Builder
│   └── reportbuilder/
│       └── datasource/
│           └── usage_report.php       # Datasource Report Builder
├── config/
│   └── usage-events.json              # Config editable de clasificación de eventos
├── lang/
│   ├── en/
│   │   └── local_usagereports.php
│   └── es/
│       └── local_usagereports.php
├── db/
│   └── access.php                     # Capacidades si se requieren
├── version.php
└── README.md
```

### 5.1 Entidad (`classes/local/entities/usage_event.php`)

- Extiende `\core_reportbuilder\local\entities\base`.
- Implementa `get_default_table_aliases()`, `get_default_entity_title()`, `initialise()`.
- Define las tablas: `mdl_logstore_standard_log`, `mdl_user`, `mdl_context`, `mdl_role_assignments`, `mdl_role`, `mdl_course` (con sus joins, ver sección 3).
- Columnas a exponer:
  - `Institución` (`u.institution`)
  - `Rol` (`r.shortname`, con lang string amigable: Estudiante / Profesor)
  - `Curso` (`c.fullname`)
  - `Tipo de evento` (columna calculada: visualizacion / actividad / creacion)
  - `Fecha` (`log.timecreated`, tipo datetime)
  - Columna de conteo agregable (para que el usuario del reporte pueda agrupar y sumar en la UI, o bien exponer tres columnas de conteo ya separadas por tipo si el agrupado nativo resulta limitado — a validar durante implementación, dado que el Report Builder de Moodle 4.x/5.x tiene limitaciones conocidas para agregaciones tipo SUM/COUNT agrupadas, ver `MDL-76392`).
- Filtros a exponer: Institución (select), Rol (select), Curso (autocomplete), Rango de fecha (date range), Tipo de evento (select).

### 5.2 Datasource (`classes/reportbuilder/datasource/usage_report.php`)

- Extiende `\core_reportbuilder\datasource`.
- `initialise()`: define tabla principal (`mdl_logstore_standard_log`), agrega la entidad `usage_event`, agrega las columnas y filtros por defecto que debe ver el administrador al crear el informe.

### 5.3 Verificación de agregación

**Importante para el desarrollador:** el Report Builder de Moodle ha tenido, históricamente, limitaciones para reportes con agregación tipo SUM/COUNT agrupados (ver discusión de la comunidad sobre `MDL-76392`). Antes de dar por cerrada la UX del reporte, se debe probar en la instancia real de Moodle 5.1.3 si:

(a) el generador visual permite agrupar y contar filas por Institución + Rol + Curso + Tipo de evento directamente, o
(b) es necesario exponer la entidad a nivel de fila (una fila por evento) y dejar que el conteo se resuelva al exportar a CSV/Excel (tabla dinámica), lo cual sigue siendo válido dado que el objetivo final es el envío mensual del archivo exportado.

Si (b) es el caso, documentarlo en el `README.md` del plugin para que quien reciba el correo sepa que debe usar una tabla dinámica sobre el CSV/Excel adjunto.

---

## 6. Configuración del informe en la UI (post-instalación)

1. Instalar el plugin (Git/SCP + `admin/cli/upgrade.php --non-interactive`, igual que el resto del `admin-module`).
2. *Sitio administración > Informes > Informes personalizados > Nuevo informe.*
3. Elegir la fuente `Uso de la plataforma` (nombre a definir vía lang string).
4. Agregar columnas: Institución, Rol, Curso, Tipo de evento, (conteo si aplica).
5. Configurar condiciones/filtros según sección 5.1.
6. Guardar, definir Audiencia (quién puede verlo).
7. Pestaña **Schedules** → *New schedule* → recurrencia mensual, formato CSV o Excel, lista de destinatarios.

---

## 7. Criterios de aceptación

- [ ] La fuente `Uso de la plataforma` aparece en *Informes personalizados* sin necesidad de ningún plugin de terceros instalado.
- [ ] Los tres tipos de evento (visualización, actividad, creación) se derivan de `usage-events.json`, no de una lista hardcodeada en PHP.
- [ ] El reporte permite filtrar/agrupar por Institución, Rol (`student`/`editingteacher`) y Curso.
- [ ] Es posible programar un envío mensual por correo desde la pestaña Schedules, sin código adicional.
- [ ] La lista de eventos en `usage-events.json` fue validada contra una auditoría real de `mdl_logstore_standard_log` (sección 2), no solo contra la lista candidata de este documento.

---

## 8. Próximos pasos sugeridos para Claude Code

1. Ejecutar la consulta de auditoría (sección 2) contra la base de datos real y ajustar `usage-events.json`.
2. Generar el esqueleto del plugin (`version.php`, `lang/`, estructura de carpetas de la sección 5).
3. Implementar la entidad y el datasource siguiendo la Report Builder API (`reportbuilder/classes/local/entities/base.php` y `\core_reportbuilder\datasource` como clases base a extender).
4. Probar en `admin.conectatech.co`/instancia de staging antes de producción, validando el punto 5.3 sobre agregación.
5. Documentar en el `README.md` del plugin cómo editar `usage-events.json` para futuros tipos de actividad.
