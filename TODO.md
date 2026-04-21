# TODO.md — Motor JIT · ConectaTech.co
> Siempre exactamente 2 tareas atómicas · Última actualización: 2026-04-17 (rev. 3)

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

**Origen:** PRD §6 (roadmap Alta prioridad) · ADR-005 (deuda técnica intencional documentada)

**Problema:** Al desplegar un árbol curricular, los cursos finales en Moodle se crean con la sección 0 vacía (sin portada ni bienvenida). Los estudiantes ven un curso sin contexto introductorio.

**Qué hacer:**

### Paso 1 — Extender el modelo de datos del árbol

En el JSON del árbol (`backend/data/arboles/{id}.json`), cada nodo de tipo "curso-final" debe poder tener un campo opcional `seccion0`:

```json
{
  "id": "...",
  "tipo": "curso-final",
  "nombre": "Nombre del curso",
  "seccion0": {
    "titulo": "Bienvenida al curso",
    "contenido": "HTML o Markdown con el contenido de la portada"
  }
}
```

### Paso 2 — UI en el editor de árboles

En `admin-module/frontend/src/app/features/arboles/arbol-editor.component.ts` (y su template):
- Al seleccionar un nodo de tipo "curso-final", mostrar un panel lateral o modal con campos para editar `seccion0.titulo` y `seccion0.contenido`
- El campo de contenido puede ser un `<textarea>` con soporte Markdown básico (no requiere editor rico por ahora)
- El botón "Guardar árbol" ya existente persiste el cambio vía `PUT /admin-api/arboles/{id}`

### Paso 3 — Deploy: crear sección 0 en Moodle

En `admin-module/backend/lib/ArbolCurricularService.php`, en el método que despliega el árbol:
- Después de crear el curso final en Moodle, verificar si el nodo tiene `seccion0`
- Si existe: actualizar la sección 0 con el `summary` correspondiente

```php
if (!empty($nodo['seccion0'])) {
    $section = course_create_section($course->id, 0);
    $DB->update_record('course_sections', [
        'id'            => $section->id,
        'name'          => $nodo['seccion0']['titulo'] ?? '',
        'summary'       => $nodo['seccion0']['contenido'] ?? '',
        'summaryformat' => FORMAT_HTML,
    ]);
}
```

**Archivos a modificar:**
1. `admin-module/frontend/src/app/features/arboles/arbol-editor.component.ts` (+ template HTML)
2. `admin-module/backend/lib/ArbolCurricularService.php`
3. `admin-module/api/handlers/arboles.php` (si necesita ajustes en el endpoint de despliegue)

**Definición de done:**
- [ ] El editor de árboles muestra campos para título y contenido de la sección 0 al seleccionar un nodo "curso-final"
- [ ] Al guardar el árbol, los datos de `seccion0` se persisten en el JSON del servidor
- [ ] Al desplegar el árbol, la sección 0 de los cursos finales en Moodle tiene el contenido definido
- [ ] Los cursos finales sin `seccion0` definida se despliegan igual que antes (sin regresión)
- [ ] Probado en el servidor de producción con un árbol real

---

## Tarea 2 — [FEATURE] Reporte de progreso de estudiantes

**Origen:** PRD §6 (roadmap Alta prioridad) · No iniciado

**Problema:** El administrador y el gestor no tienen visibilidad del avance de los estudiantes: cuántos completaron el curso, cuáles están activos, cuáles no han empezado.

**Qué hacer:**

### Paso 1 — Endpoint backend

En `admin-module/api/handlers/reportes.php`, agregar el endpoint:

```
GET /admin-api/reportes/progreso?org_id={id}&course_id={id}
```

Respuesta:
```json
{
  "curso": "Nombre del curso",
  "total_matriculados": 45,
  "completados": 12,
  "en_progreso": 28,
  "sin_iniciar": 5,
  "estudiantes": [
    {
      "nombre": "Nombre Apellido",
      "cedula": "1001234567",
      "completado": false,
      "ultimo_acceso": "2026-04-10T14:23:00Z",
      "progreso_pct": 65
    }
  ]
}
```

Usar tablas Moodle: `mdl_user_enrolments`, `mdl_course_completions`, `mdl_user_lastaccess`.

### Paso 2 — Vista en el panel admin

Crear `admin-module/frontend/src/app/features/reportes/progreso/progreso.component.ts`:
- Tabla con filtros por organización y curso
- Columnas: nombre, cédula, completado (✓/✗), último acceso, progreso %
- Exportar a CSV (botón simple, `Blob` + `URL.createObjectURL`)

Agregar ruta en `app.routes.ts`:
```typescript
{
  path: 'reportes/progreso',
  loadComponent: () => import('./features/reportes/progreso/progreso.component')
    .then(m => m.ProgresoComponent)
}
```

**Archivos a modificar / crear:**
1. `admin-module/api/handlers/reportes.php` (ampliar con nuevo endpoint)
2. `admin-module/frontend/src/app/features/reportes/progreso/progreso.component.ts` (nuevo)
3. `admin-module/frontend/src/app/app.routes.ts` (agregar ruta)

**Definición de done:**
- [ ] `GET /admin-api/reportes/progreso?org_id=X&course_id=Y` retorna datos reales de Moodle
- [ ] La vista muestra la tabla con los datos de progreso
- [ ] El filtro por organización funciona (carga los cursos de esa org)
- [ ] El botón de exportar CSV genera un archivo descargable
- [ ] La ruta `/reportes/progreso` es accesible desde el sidebar del panel admin
- [ ] Probado con datos reales de producción

---

## Historial de tareas completadas

| Fecha | Tarea | Descripción breve |
|---|---|---|
| 2026-04-14 | [FIX] Pipeline Markdown truncado | Fix `force_rollback` Moodle, `numsections` MAX(section), `question.name` 255 chars, `ensayo` variante adjunto |
| 2026-04-14 | [DOCS] Sistema de documentación | PRD.md, tech-specs.md, CLAUDE.md actualizado (OWASP + git flow), MEMORY.md, TODO.md |
| 2026-04-15 | [FEATURE] Previsualizador de contenido Markdown | Endpoint `POST /admin-api/markdown/preview`, árbol `p-tree` con drag & drop, dropzone, layout en dos filas (520px / 640px), reconstrucción de contenido en tiempo real |
| 2026-04-17 | [FIX] Árbol de preview y PobladorService | `items_ordered` en MarkdownParser para orden correcto de nodos; `hiddenTitle` derivado de `semantic-blocks.json`; `TreeDragDropService` en providers (fix drag-and-drop); dropzone a la izquierda, card destino condicional; `eliminarPlaceholdersVacios()` en PobladorService |

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

**Cambios en esta sesión:**
- Fixes de calidad sobre el previsualizador: orden de nodos (`items_ordered`), `hiddenTitle` desde config, drag-and-drop, layout, placeholders en PobladorService
- Limpieza manual de secciones «sucias» en cursos MA-6 y MA-7 (artefactos del timeout anterior)
- PR mergeada a main

**Comparación PRD vs MEMORY:**
- Previsualizador y sus fixes: ✅ en producción
- Sin nuevos gaps de seguridad
- Pendientes de Alta prioridad (PRD §6): Sección 0 de cursos finales, Reportes de progreso

**Resultado:** Sin cambio de prioridad — Tarea 1 = Sección 0 de cursos finales. Tarea 2 = Reportes de progreso.
