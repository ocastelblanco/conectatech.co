# Plan de implementación — Árboles curriculares

**Fecha:** 2026-03-10
**Versión:** 1.1 (decisiones incorporadas)

---

## 1. Resumen del alcance

Implementar en el admin-module el ciclo completo de vida de un árbol curricular:

| Flujo | Descripción |
|-------|-------------|
| **Crear** | Definir metadatos, grados, áreas y asignar temas desde repositorios |
| **Editar** | Modificar metadatos, reorganizar/añadir/eliminar temas |
| **Duplicar** | Copiar un árbol cambiando período escolar y/o institución |
| **Ejecutar** | Crear y poblar los cursos en Moodle a partir del árbol |

Los flujos CSV actuales (Crear Cursos y Poblar Cursos) **conviven** con el nuevo flujo.

---

## 2. Decisiones de diseño

### 2.1 Almacenamiento
**JSON en servidor**, un archivo por árbol en `backend/data/arboles/{uuid}.json`.
Diseñar el esquema pensando en migración futura a **AWS DynamoDB / DocumentDB**
(IDs tipo UUID, sin relaciones implícitas por carpeta, timestamps ISO 8601).

### 2.2 Shortname de cursos en Moodle
Formato: `{árbol.shortname}-{periodo}-{curso.shortname}-{grado.shortname}`

El campo `periodo` es exactamente el string que define el usuario al crear el árbol.
Puede ser `2026`, `2026-1`, `2026-I`, `2026-2027`, etc.

Ejemplos:
- Periodo `2026-2027` → `CCSM-2026-2027-CN-6`
- Periodo `2026-I` → `CCSM-2026-I-CN-6`
- Periodo `2026` → `CCSM-2026-CN-6`

### 2.3 Granularidad del drag-and-drop
Se arrastran **secciones completas** de Moodle. Una sección = un Tema (puede contener subsecciones, recursos y actividades).

### 2.4 Proyectos
Los árboles **no tienen proyecto**. La agrupación por proyecto es interna de los repositorios y plantillas. El árbol solo conoce: categoría raíz, institución, grados y áreas.

### 2.5 Ejecución sobre cursos con estudiantes matriculados
Si el curso destino **ya existe y tiene estudiantes matriculados**, la ejecución solo puede:
- **Añadir** temas que estén en el árbol pero no en el curso actual.
- **Cambiar el nombre largo** del curso.

No se puede eliminar ni recrear un curso con estudiantes. Este comportamiento es el **procedimiento general de actualización** de cursos en ejecución.

Si el curso existe pero **sin estudiantes**: se elimina (previa confirmación) y se recrea.

### 2.6 Fechas de inicio y fin
Cada **curso** (área curricular en un grado) lleva sus propias fechas de inicio y finalización. Se almacenan en el JSON del árbol y se aplican al crear el curso en Moodle (`startdate` / `enddate` en la tabla `course`).

### 2.7 Categoría raíz
El usuario puede seleccionar cualquier categoría raíz de primer nivel en Moodle. **COLEGIOS** es la opción por defecto.

### 2.8 Orden de implementación
**Backend primero**, luego frontend fase a fase.

---

## 3. Impacto en la arquitectura existente

### 3.1 Backend

| Archivo | Cambio |
|---------|--------|
| `backend/lib/ArbolCurricularService.php` | **Nuevo** — CRUD JSON, lógica de ejecución |
| `backend/lib/CursosService.php` | **Reutilizado** — `crearCurso()` y `ensureCategoryPath()` |
| `backend/lib/PobladorService.php` | **Reutilizado** — `poblarCurso()` |
| `api/handlers/arboles.php` | **Nuevo** — handlers REST |
| `api/index.php` | **Modificado** — añadir rutas `/api/arboles/*` |
| `backend/data/arboles/` | **Nuevo directorio** — un JSON por árbol (en `.gitignore`) |

### 3.2 Frontend

| Módulo | Cambio |
|--------|--------|
| `features/arboles/` | **Nuevo feature module** |
| `app.routes.ts` | **Modificado** — ruta `/arboles` |
| `layout/sidebar/` | **Modificado** — ítem "Árboles Curriculares" |
| `core/services/api.service.ts` | **Modificado** — métodos de la nueva API |

---

## 4. Esquema JSON del árbol

```json
{
  "id": "550e8400-e29b-41d4-a716-446655440000",
  "nombre": "Cosmovisión Cristiana San Marino",
  "shortname": "CCSM",
  "periodo": "2026-2027",
  "institucion": "San Marino",
  "categoria_raiz": "COLEGIOS",
  "created_at": "2026-03-10T14:00:00Z",
  "updated_at": "2026-03-10T14:00:00Z",
  "grados": [
    {
      "id": "g-01",
      "nombre": "6º de básica secundaria",
      "shortname": "6",
      "cursos": [
        {
          "id": "c-01",
          "nombre": "Ciencias Naturales",
          "shortname": "CN",
          "templatecourse": "PL-CC-CN",
          "startdate": "2026-01-26",
          "enddate":   "2026-11-27",
          "temas": [
            {
              "repo_shortname": "repo-cc-cn-6-7",
              "section_num": 1,
              "titulo": "Tema 1 — Célula y tejidos"
            },
            {
              "repo_shortname": "repo-cc-cn-6-7",
              "section_num": 2,
              "titulo": "Tema 2 — Ecosistemas"
            }
          ]
        }
      ]
    }
  ]
}
```

**Campos del árbol:** `id`, `nombre`, `shortname`, `periodo`, `institucion`, `categoria_raiz`, `created_at`, `updated_at`, `grados[]`

**Campos del grado:** `id`, `nombre`, `shortname`, `cursos[]`

**Campos del curso:** `id`, `nombre`, `shortname`, `templatecourse`, `startdate`, `enddate`, `temas[]`

**Campos del tema:** `repo_shortname`, `section_num`, `titulo`

---

## 5. API REST — rutas

| Método | Ruta | Descripción |
|--------|------|-------------|
| `GET` | `/api/arboles` | Listar árboles (id, nombre, periodo, updated_at) |
| `POST` | `/api/arboles` | Crear árbol vacío |
| `GET` | `/api/arboles/{id}` | Obtener árbol completo |
| `PUT` | `/api/arboles/{id}` | Guardar árbol completo |
| `DELETE` | `/api/arboles/{id}` | Eliminar árbol |
| `POST` | `/api/arboles/{id}/duplicar` | Duplicar con nuevos metadatos |
| `GET` | `/api/arboles/{id}/validar` | Pre-validar conflictos de shortname |
| `POST` | `/api/arboles/{id}/ejecutar` | Crear y poblar cursos en Moodle |
| `GET` | `/api/arboles/plantillas` | Árbol de PLANTILLAS (para selector templatecourse) |
| `GET` | `/api/arboles/repositorios` | Secciones de repositorios (para drag-and-drop) |
| `GET` | `/api/arboles/categorias-raiz` | Categorías de primer nivel en Moodle |

---

## 6. Endpoint `GET /api/arboles/repositorios`

```json
{
  "ok": true,
  "repositorios": [
    {
      "nombre": "Cosmovisión Cristiana",
      "areas": [
        {
          "nombre": "Ciencias Naturales",
          "cursos": [
            {
              "shortname": "repo-cc-cn-6-7",
              "fullname": "Ciencias Naturales — Grados 6° y 7°",
              "secciones": [
                { "num": 1, "titulo": "Tema 1 — Célula y tejidos" },
                { "num": 2, "titulo": "Tema 2 — Ecosistemas" }
              ]
            }
          ]
        }
      ]
    }
  ]
}
```

Consulta Moodle: `mdl_course_sections` donde `name != ''` y `section > 0`, join con `mdl_course` para filtrar por categoría REPOSITORIOS.

---

## 7. Endpoint `POST /api/arboles/{id}/validar`

Devuelve, para cada curso del árbol, el estado en Moodle:

```json
{
  "ok": true,
  "conflictos": [
    {
      "shortname": "CCSM-2026-2027-CN-6",
      "fullname": "Ciencias Naturales - 6",
      "estado": "existe_con_estudiantes",
      "estudiantes": 28,
      "temas_nuevos": 2
    },
    {
      "shortname": "CCSM-2026-2027-MA-6",
      "fullname": "Matemáticas - 6",
      "estado": "existe_sin_estudiantes",
      "estudiantes": 0,
      "temas_nuevos": 0
    },
    {
      "shortname": "CCSM-2026-2027-LE-6",
      "fullname": "Lenguaje - 6",
      "estado": "nuevo",
      "estudiantes": 0,
      "temas_nuevos": 4
    }
  ]
}
```

Estados posibles: `nuevo`, `existe_sin_estudiantes` (se recreará), `existe_con_estudiantes` (solo se añaden temas y se actualiza fullname).

---

## 8. Lógica de ejecución (`POST /api/arboles/{id}/ejecutar`)

```
Para cada grado en árbol.grados:
  Para cada curso en grado.cursos:

    shortname_moodle = "{árbol.shortname}-{árbol.periodo}-{curso.shortname}-{grado.shortname}"
    fullname_moodle  = "{curso.nombre} - {grado.shortname}"
    category_path    = "{árbol.categoria_raiz}/{árbol.institucion}/{curso.nombre}"

    existente = buscar course por shortname_moodle

    SI existente Y tiene estudiantes:
      → update_course(fullname_moodle)
      → Determinar temas_a_añadir = temas del árbol que no están en el curso
      → PobladorService::poblarCurso(shortname, temas_a_añadir)

    SI existente Y sin estudiantes:
      → delete_course(existente.id)   [confirmación ya recibida vía /validar]
      → CursosService::crearCurso(...)
      → PobladorService::poblarCurso(shortname, todos_los_temas)

    SI no existe:
      → CursosService::crearCurso(..., startdate, enddate)
      → PobladorService::poblarCurso(shortname, todos_los_temas)
```

`CursosService::crearCurso()` se extiende para aceptar `startdate` y `enddate`.

---

## 9. Frontend — estructura del editor

```
┌─────────────────────────────────────────────────────────────┐
│  [← Árboles]  CCSM 2026-2027 · San Marino      [Ejecutar]  │
├──────────────────┬──────────────────────────────────────────┤
│  Panel izquierdo │  Panel central (editor de curso)         │
│  ─────────────── │  ──────────────────────────────────────  │
│  Metadatos [✎]   │  Grado 6 › Ciencias Naturales            │
│  ─────────────── │  Template: PL-CC-CN  [cambiar]           │
│  GRADOS          │  Inicio: 2026-01-26  Fin: 2026-11-27     │
│  ├ 6 [+área]     │  ──────────────────────────────────────  │
│  │ ├ CN ←activo  │  TEMAS ASIGNADOS     REPOSITORIOS        │
│  │ └ MA          │  ┌────────────────┐  ┌────────────────┐  │
│  ├ 7             │  │ ⠿ Tema 1 [✕]  │  │ ▸ CC           │  │
│  └ [+ Grado]     │  │ ⠿ Tema 2 [✕]  │  │   ▸ CN         │  │
│                  │  │ [arrastrar →] │  │     › Tema 1   │  │
│                  │  └────────────────┘  │     › Tema 2   │  │
│                  │                      └────────────────┘  │
└──────────────────┴──────────────────────────────────────────┘
```

**Componentes Angular:**

| Componente | Descripción |
|------------|-------------|
| `ArbolesList` | Tabla con acciones (editar, duplicar, ejecutar, eliminar) |
| `ArbolEditor` | Contenedor principal con dos paneles |
| `MetadatosForm` | Formulario inline: nombre, shortname, periodo, institución, cat. raíz |
| `GradosList` | Panel izquierdo: CRUD de grados y áreas |
| `CursoEditor` | Panel central: template, fechas, lista de temas asignados |
| `RepositorioPanel` | Panel derecho: árbol de secciones para drag-and-drop |
| `ArbolValidacion` | Modal: muestra conflictos y pide confirmación antes de ejecutar |
| `ArbolEjecucion` | Modal: progreso en tiempo real + reporte de resultados |

**Drag-and-drop:** Se usa el drag-and-drop nativo de PrimeNG (`p-tree` con `[draggableNodes]="true"`, `[droppableNodes]="true"` y `TreeDragDropService`). Tanto el panel de TEMAS ASIGNADOS como el panel de REPOSITORIOS usan `p-tree` con drag-and-drop nativo entre árboles. No se requiere `@angular/cdk`.

---

## 10. Fases de implementación

### Fase 1 — Backend (prioridad)

| Tarea | Archivo | Detalle |
|-------|---------|---------|
| 1.1 | `backend/data/arboles/` | Crear directorio, añadir a `.gitignore` |
| 1.2 | `ArbolCurricularService.php` | CRUD JSON: listar, crear, leer, guardar, eliminar, duplicar |
| 1.3 | `ArbolCurricularService.php` | `getRepositorios()` — secciones de repos por categoría |
| 1.4 | `ArbolCurricularService.php` | `getPlantillas()` — árbol de PLANTILLAS |
| 1.5 | `ArbolCurricularService.php` | `getCategoriasRaiz()` — categorías de primer nivel |
| 1.6 | `ArbolCurricularService.php` | `validarEjecucion()` — detectar conflictos |
| 1.7 | `ArbolCurricularService.php` | `ejecutar()` — crear y poblar cursos |
| 1.8 | `CursosService.php` | Extender `crearCurso()` con `startdate`/`enddate` |
| 1.9 | `api/handlers/arboles.php` | Todos los handlers REST |
| 1.10 | `api/index.php` | Registrar rutas `/api/arboles/*` |

### Fase 2 — Frontend lista + metadatos + grados
- `ArbolesList`, `MetadatosForm`, `GradosList`
- Sin drag-and-drop aún; temas como lista simple editable

### Fase 3 — Drag-and-drop y asignación de temas
- `RepositorioPanel` + drag-and-drop nativo de PrimeNG hacia `CursoEditor`
- Reordenar temas asignados con drag interno
- No se requiere `@angular/cdk`

### Fase 4 — Ejecución
- `ArbolValidacion` + `ArbolEjecucion`
- Integración con `/validar` y `/ejecutar`

### Fase 5 — Duplicación
- Modal de duplicación
- Integración con `/duplicar`

