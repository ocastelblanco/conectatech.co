# Plan de trabajo: Sección 0 de cursos finales

> **Origen:** PRD §6 (Alta) · ADR-005 · Deuda técnica desde despliegue inicial de árboles curriculares
> **Última actualización:** 2026-04-28

---

## Resumen

Los cursos finales se crean sin contenido en la sección 0 (portada/bienvenida). Este plan agrega:

1. **Editor de árboles:** campo Markdown por curso final + botón "Crear introducción" (lista plana de temas) + botón "Previsualizar" (HTML renderizado en diálogo).
2. **Pipeline de despliegue:** al ejecutar el árbol, si el curso tiene `seccion_0`, se puebla en Moodle usando `course_update_section()`.

Retrocompatible: árboles existentes sin `seccion_0` se despliegan igual.

---

## Archivos a modificar

| # | Archivo | Cambio | Líneas estimadas |
|---|---------|--------|-----------------|
| 1 | `admin-module/frontend/src/app/features/arboles/arbol-editor.component.html` | Textarea Markdown + botones + diálogo de previsualización | +55 |
| 2 | `admin-module/frontend/src/app/features/arboles/arbol-editor.component.ts` | Métodos `generarIntroduccion()` y `previsualizarSeccion0()` + signal `previewHtml` | +35 |
| 3 | `admin-module/backend/lib/PobladorService.php` | Nuevo método `poblarSeccionCero(int $courseId, string $markdown)` | +20 |
| 4 | `admin-module/backend/lib/ArbolCurricularService.php` | Hook post-poblado en las 3 ramas de `ejecutar()` | +15 |

---

## Paso 1 — Frontend: editor de árboles

### 1a. Campo Markdown (`arbol-editor.component.html`)

Después del bloque de fechas (línea 312), agregar:

```html
<!-- Portada / Bienvenida -->
<div class="border-t pt-4 mt-4 space-y-3">
  <div class="flex items-center justify-between">
    <label class="text-sm font-medium text-gray-700">Portada / Bienvenida (sección 0)</label>
    <span class="text-[10px] text-gray-400">Markdown</span>
  </div>

  <textarea pInputTextarea
    class="w-full text-sm font-mono"
    rows="5"
    [ngModel]="cursoActivo()!.seccion_0"
    (ngModelChange)="updateCursoField('seccion_0', $event)"
    placeholder="Contenido de bienvenida en Markdown&#10;Aparecerá en la sección 0 del curso en Moodle"></textarea>

  <div class="flex gap-2">
    <p-button label="Crear introducción"
              icon="pi pi-file-edit"
              severity="secondary"
              size="small"
              (onClick)="generarIntroduccion()" />
    <p-button label="Previsualizar"
              icon="pi pi-eye"
              severity="secondary"
              size="small"
              [disabled]="!cursoActivo()!.seccion_0?.trim()"
              (onClick)="previsualizarSeccion0()" />
  </div>
</div>
```

### 1b. Botón "Crear introducción" (`arbol-editor.component.ts`)

Genera una lista plana Markdown iterando `temas[].titulo`:

```typescript
generarIntroduccion(): void {
  const curso = this.cursoActivo();
  if (!curso || !curso.temas?.length) return;

  let md = `**El curso ${curso.nombre} tiene la siguiente estructura de contenidos:**\n\n`;

  for (const tema of curso.temas) {
    md += `- ${tema.titulo}\n`;
  }

  this.updateCursoField('seccion_0', md.trimEnd());
}
```

**Nota:** Se descartó el grouping automático por `:` porque los datos de `temas[].titulo` pueden contener `:` como parte del título y no hay distinción confiable entre grupo y subtema. Para grouping estructurado se requeriría metadata adicional (ej. campo `grupo` en los temas). Se implementa lista plana con la opción de editar manualmente.

### 1c. Botón "Previsualizar" (`arbol-editor.component.ts` + HTML)

Usa el endpoint existente `POST /admin-api/markdown/preview`:

```typescript
readonly showPreviewSeccion0 = signal(false);
readonly previewHtml = signal<string>('');

previsualizarSeccion0(): void {
  const curso = this.cursoActivo();
  if (!curso?.seccion_0?.trim()) return;

  this.api.previsualizarMarkdown({ content: curso.seccion_0 }).subscribe({
    next: (r) => {
      this.previewHtml.set(r.html ?? '');
      this.showPreviewSeccion0.set(true);
    },
    error: () => {
      this.toast.add({ severity: 'error', summary: 'Error', detail: 'No se pudo previsualizar' });
    },
  });
}
```

Diálogo en el template:

```html
<p-dialog header="Previsualización — Portada del curso"
          [(visible)]="showPreviewSeccion0"
          [modal]="true"
          [style]="{ width: '650px' }"
          [closable]="true">
  <div class="prose prose-sm max-w-none"
       [innerHTML]="previewHtml()"></div>
</p-dialog>
```

**Seguridad (OWASP A03):** El HTML proviene del backend (`markdown_to_html()` de Moodle), no del cliente — es contenido confiable. El usuario solo ingresa Markdown, que el backend convierte de forma controlada.

### 1d. Schema: nuevo nodo curso con `seccion_0`

El método `addCurso()` **no necesita** inicializar `seccion_0` porque `undefined` es compatible con `!empty()` en PHP (retrocompatibilidad). Al guardar el árbol, el campo se persiste como parte del JSON vía el mecanismo existente (`PUT /api/arboles/{id}`).

---

## Paso 2 — Backend: pipeline de despliegue

### 2a. PobladorService::poblarSeccionCero()

Nuevo método público en `PobladorService.php`:

```php
/**
 * Puebla la sección 0 (portada/bienvenida) de un curso final con contenido
 * Markdown convertido a HTML.
 *
 * @param int    $courseId  ID del curso destino en Moodle.
 * @param string $markdown  Contenido Markdown de la portada.
 */
public function poblarSeccionCero(int $courseId, string $markdown): void
{
    global $DB, $CFG;

    require_once($CFG->dirroot . '/course/lib.php');

    if (empty(trim($markdown))) {
        return;
    }

    $html = markdown_to_html(trim($markdown));

    $section0 = $DB->get_record('course_sections', [
        'course' => $courseId,
        'section' => 0,
    ]);

    if (!$section0) {
        error_log("WARN poblarSeccionCero: curso {$courseId} no tiene sección 0");
        return;
    }

    course_update_section($courseId, $section0, [
        'name'    => 'Bienvenida',
        'summary' => $html,
        'visible' => 1,
    ]);

    rebuild_course_cache($courseId, true);
}
```

Usa `markdown_to_html()` nativa de Moodle (wrapper sobre Parsedown). No requiere el pipeline semántico (MarkdownParser/HtmlConverter) porque la portada es texto simple.

### 2b. ArbolCurricularService::ejecutar() — hook

Después de poblar o repoblar un curso en las 3 ramas de `ejecutar()`, verificar si `seccion_0` tiene contenido y poblarlo.

**Rama 1 — `crearYPoblar()` (curso nuevo):**

```php
$this->crearYPoblar(/* ... */);

if (!empty($curso['seccion_0'])) {
    $courseId = $DB->get_field('course', 'id', ['shortname' => $shortnameMoodle], MUST_EXIST);
    $pobladorService->poblarSeccionCero((int)$courseId, $curso['seccion_0']);
}
```

**Rama 2 — `vaciarYRepoblar()` (existe sin estudiantes):**

```php
$this->vaciarYRepoblar(/* ... */);

if (!empty($curso['seccion_0'])) {
    $courseId = $DB->get_field('course', 'id', ['shortname' => $shortnameMoodle], MUST_EXIST);
    $pobladorService->poblarSeccionCero((int)$courseId, $curso['seccion_0']);
}
```

**Rama 3 — existe con estudiantes:**

```php
// Después de añadir los temas nuevos...
if (!empty($curso['seccion_0'])) {
    $pobladorService->poblarSeccionCero((int)$existing->id, $curso['seccion_0']);
}
```

**Retrocompatibilidad:** `!empty($curso['seccion_0'])` es `false` para árboles sin el campo → no ejecuta nada.

---

## Definición de done

- [ ] Editor de árboles muestra textarea Markdown con botones "Crear introducción" y "Previsualizar"
- [ ] "Crear introducción" genera lista plana de los `titulo` de los temas
- [ ] "Previsualizar" abre diálogo con HTML renderizado (vía endpoint existente)
- [ ] Al desplegar el árbol, los cursos con `seccion_0` poblado tienen portada en Moodle
- [ ] Árboles sin `seccion_0` se despliegan exactamente igual que antes
- [ ] Build del frontend (`npm run build`) pasa sin errores

---

## Notas de seguridad (OWASP)

| Regla | Aplicación |
|---|---|
| A01 — Endpoint de escritura | `POST /api/arboles/{id}/ejecutar` ya llama `verificarAdmin()` |
| A02 — Credenciales en cliente | Ninguna; el Markdown se envía al API no al cliente |
| A03 — XSS | `[innerHTML]` recibe HTML del backend (confiable), no del usuario |
| A05 — CORS | Sin cambios; endpoint existente ya configurado |

---

## Dependencias y riesgos

- **Dependencia:** El endpoint `POST /admin-api/markdown/preview` debe existir (implementado en sesión del 2026-04-15). Verificar que maneje `{ content }` y devuelva `{ html }`.
- **Riesgo bajo:** Si `markdown_to_html()` de Moodle lanza excepción por contenido malformado, el pipeline podría fallar. El `catch` global en `ejecutar()` lo maneja (marca error, no detiene los otros cursos).
- **Riesgo nulo:** Árboles existentes sin `seccion_0` — `!empty()` es retrocompatible.
