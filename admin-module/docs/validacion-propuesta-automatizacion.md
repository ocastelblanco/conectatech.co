# Validación Técnica de la Propuesta de Automatización

**Proyecto:** ConectaTech — Moodle 5.1.3 en AWS  
**Documento base:** `propuesta-creacion_externa_contenido.md`  
**Fecha:** Febrero 2026

---

## Resumen Ejecutivo

La propuesta es **técnicamente viable en su totalidad**, con matices importantes en tres puntos que requieren ajustes de diseño. El flujo más eficiente para este volumen de contenido (~600 secciones, ~17 cursos) es un **script PHP CLI ejecutado directamente en el servidor**, que accede a las APIs internas de Moodle —más capaces y más rápidas que la API REST externa para creación de contenido.

---

## Análisis por Paso del Flujo 1

### Paso 1 — Exportar Google Doc a Markdown ✅ Viable

**Estado:** Completamente funcional desde julio 2024.

Google Docs soporta nativamente la exportación a Markdown desde la interfaz (`Archivo > Descargar > Markdown`) y también **via Google Drive API** con el parámetro `exportFormat=markdown`:

```
GET https://docs.google.com/feeds/download/documents/export/Export
    ?exportFormat=markdown&id={DOCUMENT_ID}
    Authorization: Bearer {ACCESS_TOKEN}
```

Esto permite crear un script que, dado el URL del Google Doc, descargue automáticamente el archivo `.md` sin necesidad de un addon de terceros.

**⚠️ Consideración importante — Caracteres especiales en español:**  
La exportación de Google Docs a Markdown puede generar artefactos en tildes, ñ y signos de puntuación especiales (`¿`, `¡`, comillas tipográficas). El script de parseo debe incluir una etapa de **normalización UTF-8** antes de procesar el contenido.

**Recomendación:** Usar la Google Drive API directamente (con OAuth2 o Service Account) desde el servidor EC2, sin requerir intervención manual. El token de servicio se guarda como variable de entorno en la instancia.

---

### Paso 2 — Crear el curso repositorio en Moodle ✅ Viable

**Estado:** Nativo en Moodle, bien documentado.

Via script PHP CLI en el servidor, usando la API interna `create_course()`:

```php
define('CLI_SCRIPT', true);
require(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/course/lib.php');

$coursedata = (object)[
    'fullname'  => 'Ciencias Naturales — Grados 4° y 5°',
    'shortname' => 'repo-cn-4-5',
    'categoryid' => $repositoriosCategoryId,
    'visible'   => 0, // oculto para usuarios
    'format'    => 'topics',
    'numsections' => 0,
];
$course = create_course($coursedata);
```

**Sin bloqueos.** La API crea correctamente el contexto, la categoría y los registros asociados. Importante: los scripts CLI deben ejecutarse como `sudo -u apache php ...` para no corromper los permisos del moodledata.

---

### Paso 3 — Crear secciones (títulos H1) ✅ Viable

**Estado:** Nativo via `course_create_section()` y `course_update_section()`.

```php
$section = course_create_section($course->id, $position);
$section->name = 'Nombre de la Sección 1';
$section->summaryformat = FORMAT_HTML;
course_update_section($course->id, $section, ['name' => $section->name]);
```

**⚠️ Límite de secciones (`maxsections`):**  
Moodle tiene un límite global de secciones por curso (por defecto: **52**). Con 30–40 secciones por curso repositorio este límite **podría alcanzarse**. Solución: en la administración de Moodle, aumentar `maxsections` a 60 o más en `Administración del sitio > Características del curso > Número máximo de secciones`.

---

### Paso 4 — Crear el "Área de texto y medios" raíz de cada sección ✅ Viable

**Estado:** Viable via API interna (`add_moduleinfo()`). `mod_label` es el módulo interno de Moodle para el recurso "Área de texto y medios".

```php
require_once($CFG->dirroot . '/course/modlib.php');

$moduleinfo = (object)[
    'modulename'   => 'label',
    'course'       => $course->id,
    'section'      => $section->section, // número de sección
    'visible'      => 1,
    'intro'        => $htmlContent, // HTML generado desde Markdown
    'introformat'  => FORMAT_HTML,
];
$moduleinfo = add_moduleinfo($moduleinfo, $course);
```

La conversión Markdown → HTML debe seguir las **reglas de mapeo custom** definidas en la propuesta (por ejemplo, `### Texto bíblico guía` → `<div class="resaltado cita-biblica">`). Esto se implementa como un parser PHP propio, ligero y controlado.

---

### Paso 5 — Crear subsecciones (títulos H2) ✅ Viable con arquitectura correcta

**Estado:** Viable, pero requiere comprender cómo funciona `mod_subsection` en Moodle 4.5+/5.x.

**Arquitectura de subsecciones en Moodle 5.x:**  
Las subsecciones ya **no son secciones tradicionales** anidadas. Desde Moodle 4.5, se implementan como un **módulo de actividad especial** (`mod_subsection`) que, internamente, crea una "sección delegada" vinculada. Se crean como cualquier otro módulo:

```php
$subsectionModuleinfo = (object)[
    'modulename' => 'subsection',
    'course'     => $course->id,
    'section'    => $parentSectionNumber, // sección padre (H1)
    'name'       => 'Nombre de la subsección',
    'visible'    => 1,
    'intro'      => '',
    'introformat'=> FORMAT_HTML,
];
$subsection = add_moduleinfo($subsectionModuleinfo, $course);
// $subsection->sectionnum tiene el número de la sección delegada creada
```

Los recursos y actividades dentro de la subsección se agregan a `$subsection->sectionnum`, no a la sección padre.

**⚠️ Consideración sobre maxsections:**  
Cada subsección también cuenta como sección para efectos del límite `maxsections`. Con 4–5 subsecciones por sección × 30–40 secciones = hasta **200 "secciones" por curso**. Aumentar `maxsections` es **obligatorio** (recomendado: 250 por seguridad).

---

### Paso 6 — Crear "Área de texto y medios" por subsección ✅ Viable

Idéntico al Paso 4, pero apuntando al número de sección delegada creada por el `mod_subsection`.

```php
$labelInfo = (object)[
    'modulename' => 'label',
    'course'     => $course->id,
    'section'    => $subsection->sectionnum, // sección delegada de la subsección
    'intro'      => $htmlContent,
    'introformat'=> FORMAT_HTML,
    'visible'    => 1,
];
add_moduleinfo($labelInfo, $course);
```

---

### Paso 7 — Crear Cuestionarios en la subsección final ⚠️ Viable con ajuste de diseño

**Estado:** Técnicamente viable, pero la API de creación de preguntas es compleja. Se propone una alternativa más práctica.

**Flujo directo (más complejo):** crear el quiz, crear cada pregunta en el banco de preguntas, y añadirla al quiz. Esto requiere configurar correctamente el `questioncategory`, el tipo de pregunta (`multichoice`, `truefalse`, `shortanswer`), y los items de respuesta. Viable para un desarrollador con tiempo, pero frágil si el formato de contenido varía.

**Alternativa recomendada — Formato GIFT:**  
Moodle soporta el formato **GIFT** para importación masiva de preguntas. Es un formato de texto plano diseñado específicamente para cuestionarios. El script convierte el markdown de preguntas a GIFT, y luego usa la API de importación de banco de preguntas:

```
// Formato GIFT de ejemplo:
::Pregunta 1:: ¿Cuál es la función de la mitocondria? {
    =Producir energía (ATP)
    ~Sintetizar proteínas
    ~Almacenar el ADN
    ~Regular el ciclo celular
}
```

```php
// Importar archivo GIFT programáticamente
require_once($CFG->dirroot . '/question/format/gift/format.php');
$importer = new qformat_gift();
$importer->setCategory($questionCategory);
$importer->setFilename($giftFilePath);
$importer->importprocess();
```

Luego se crea el quiz y se vincula al banco de preguntas de forma estándar.

**Definición del formato markdown para preguntas:** se debe establecer antes de la implementación del Paso 2. Se propone un bloque especial al final de cada sección (después de una línea `---` o un marcador como `## [Evaluación]`).

---

### Paso 8 — Reporte de resultados ✅ Viable

El script PHP CLI genera un archivo JSON/CSV de resultados al final de cada ejecución, detallando secciones, subsecciones y recursos creados, con errores y advertencias. Este archivo puede ser descargado desde el servidor o enviado por email usando las funciones de notificación de Moodle.

---

## Validación del Flujo 2 (Usuarios y Matrícula)

El Manual Técnico Moodle Fase 1 ya cubre este flujo con detalle. La propuesta es sólida. Solo se agrega:

- La alternativa de usar CSV + REST API (`core_user_create_users`, `enrol_manual_enrol_users`) permite que el Flujo 2 se ejecute **desde la máquina local** sin requerir CLI en el servidor, lo que simplifica el pipeline.
- Para la desmatriculación masiva al final del año escolar, `enrol_manual_unenrol_users` está disponible en la API REST.
- Se recomienda usar **unenrol** (desmatricular) en vez de eliminar usuarios, para conservar el histórico de accesos.

---

## Arquitectura Técnica Recomendada

```
┌─────────────────────────────┐
│    Máquina local (Admin)    │
│                             │
│  1. URL de Google Doc       │
│  2. Script de orquestación  │
│     (Node.js o Python)      │
│                             │
│  > Descarga el .md via      │
│    Drive API                │
│  > Transfiere a EC2 via SCP │
│  > Ejecuta script PHP via   │
│    SSH                      │
└──────────────┬──────────────┘
               │ SSH + SCP
               ▼
┌─────────────────────────────┐
│    EC2 — Servidor Moodle    │
│                             │
│  /scripts/automation/       │
│  ├── parse-md.php           │  ← Parser Markdown → HTML
│  ├── create-course.php      │  ← Crear curso repositorio
│  ├── create-content.php     │  ← Secciones, subsecciones,
│  │                          │    labels, cuestionarios
│  └── report.php             │  ← Genera reporte JSON
│                             │
│  Ejecuta como:              │
│  sudo -u apache php         │
│  /scripts/automation/...    │
└─────────────────────────────┘
```

---

## Prerequisitos de Configuración en Moodle

Antes de ejecutar cualquier script, se deben ajustar estas configuraciones:

| Parámetro | Valor recomendado | Ruta en Moodle Admin |
|---|---|---|
| `maxsections` | 250 | Administración del sitio > Características del curso |
| Servicios web activados | Sí | Administración > Servidor > Servicios web |
| API REST habilitada | Sí | Administración > Servidor > Servicios web > Protocolos |
| Token de administrador | Creado | Administración > Servidor > Servicios web > Gestionar tokens |
| Categoría "Repositorios" | Creada | Administración > Cursos > Gestionar cursos y categorías |

---

## Preguntas que Necesitan Respuesta Antes de Implementar

Para que Claude Code genere los scripts de forma precisa, es necesario definir:

1. **Formato de preguntas en el Markdown:** ¿Cuál será la sintaxis para las preguntas de los cuestionarios? (opciones múltiples, verdadero/falso, respuesta corta, etc.)

2. **Mapeo completo de bloques semánticos:** La propuesta menciona `cita-biblica` y `cierre-espiritual`. ¿Existen otros tipos de bloques (`que-tiene-que-ver-esto-contigo`, etc.) y cuál es el listado completo con sus títulos H3 correspondientes?

3. **Comportamiento al re-ejecutar:** La nota dice que nuevas secciones se adjuntan al final del curso. ¿Cómo se identifica que un curso ya existe? ¿Por `shortname`? ¿Qué pasa si una sección (H1) con el mismo nombre ya existe: se ignora, se sobreescribe, o se duplica?

4. **Nombre de los cursos repositorio:** ¿Se toma del nombre del archivo Google Doc, o habrá un mapping explícito de URL → nombre/shortname del curso?

5. **Cuenta de servicio de Google:** ¿Se usará una cuenta de servicio de Google (Service Account con JSON key) para autenticación automática, o OAuth2 interactivo en cada ejecución?

6. **Cuestionarios — ¿tienen retroalimentación por respuesta?** ¿Las preguntas tienen explicaciones o retroalimentación que también se importarán?

---

## Conclusión

| Paso | Viabilidad | Enfoque |
|---|---|---|
| Descargar Google Doc como Markdown | ✅ | Drive API + script local |
| Crear curso repositorio | ✅ | PHP CLI — `create_course()` |
| Crear secciones (H1) | ✅ | PHP CLI — `course_create_section()` |
| Crear label raíz de sección | ✅ | PHP CLI — `add_moduleinfo()` |
| Crear subsecciones (H2) | ✅ | PHP CLI — `add_moduleinfo()` con `mod_subsection` |
| Crear label de subsección | ✅ | PHP CLI — `add_moduleinfo()` en sección delegada |
| Crear cuestionarios (H2 final) | ✅ | PHP CLI — conversión a GIFT + API de importación |
| Reporte de resultados | ✅ | JSON generado por el script |
| Flujo 2 — Usuarios y matrículas | ✅ | REST API o PHP CLI con CSV |

La propuesta es sólida. Los scripts serán PHP CLI en el servidor, orquestados desde la máquina local via SSH. No se requieren plugins adicionales de Moodle, solo ajustar `maxsections` y los permisos del servidor.
