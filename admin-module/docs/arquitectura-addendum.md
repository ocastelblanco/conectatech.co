# Addendum — Arquitectura Técnica Definitiva
## Presaberes, retroalimentación y ajustes finales

**Complementa:** `arquitectura-tecnica-definitiva.md`  
**Fecha:** Febrero 2026

---

## Resumen de cambios

| Elemento | Estado anterior | Estado actualizado |
|---|---|---|
| Retroalimentación en opción-múltiple | Sin definir | ✅ Vía GIFT con `#feedback` por opción |
| Creación de categorías | Script automático | ✅ Manual en Moodle (una sola vez) |
| Subsección `[presaberes]` | No existía | ✅ Nuevo tipo, mod_label con JS global |
| Formato markdown de cuestionarios | Plantilla básica | ✅ Actualizado con feedback y presaberes |
| JavaScript de interactividad | En cada label | ✅ Global vía Boost Union (decisión de arquitectura) |

---

## 1. Retroalimentación en preguntas de opción múltiple (`[evaluacion]`)

### Decisión: viable sin cambios de arquitectura

El formato GIFT soporta retroalimentación por respuesta de forma nativa, usando `#` después de cada opción:

```
// Formato GIFT con feedback por opción
::P1:: ¿Cuál es la función de la mitocondria? {
  =Producir energía (ATP) #¡Correcto! La mitocondria es la central energética de la célula.
  ~Sintetizar proteínas #Incorrecto. Esa función la cumple el ribosoma.
  ~Almacenar el ADN #El ADN se almacena en el núcleo, no en la mitocondria.
  ~Regular el ciclo celular #Esa función corresponde al centrosoma.
}
```

El signo `=` indica la respuesta correcta; `~` indica incorrecta. El `#` activa la retroalimentación específica. El parser de `GiftConverter.php` debe generar esta sintaxis a partir del markdown.

### Formato markdown actualizado para `[evaluacion]` con `opcion-multiple`

```markdown
## Evaluemos lo aprendido [evaluacion]

### ¿Cuál es la función de la mitocondria?
{tipo: opcion-multiple}
- Producir energía (ATP) [correcta]
   - ¡Correcto! La mitocondria es la central energética de la célula.
- Sintetizar proteínas
   - Incorrecto. Esa función la cumple el ribosoma.
- Almacenar el ADN
   - El ADN se almacena en el núcleo, no en la mitocondria.
- Regular el ciclo celular
   - Esa función corresponde al centrosoma.
```

**Convenciones del parser:**
- El ítem marcado `[correcta]` genera `=` en GIFT.
- El ítem sin marcador genera `~`.
- La sublista del ítem (sangría de 3 espacios) es el feedback `#`.
- Si no hay sublista, el feedback queda vacío en GIFT (válido).

### Formato markdown para el tipo `ensayo` (actual, por defecto)

```markdown
## Evaluemos lo aprendido [evaluacion]

### Describe la función de la célula
Escribe con tus propias palabras qué es la célula y por qué crees que Dios la diseñó con tanta precisión.
```

Sin `{tipo: ...}` explícito → el parser asume ensayo. El texto posterior al H3 es el enunciado.

---

## 2. Nuevo tipo de subsección: Presaberes `[presaberes]`

### Qué es

Un momento pedagógico constructivista al inicio de una sección o subsección, donde el estudiante responde preguntas de opción múltiple para que constate sus conocimientos previos. La actividad es **diagnóstica, no calificable**, y requiere **retroalimentación inmediata por respuesta**.

### Por qué NO es un Cuestionario de Moodle

El `mod_quiz` de Moodle no arroja retroalimentación visible al estudiante en el momento de seleccionar una opción (solo después de enviar). Además, genera calificación, que no aplica aquí.

La solución es un `mod_label` (Área de texto y medios) con HTML interactivo.

### Limitación crítica: HTMLPurifier de Moodle

Moodle aplica HTMLPurifier al mostrar el contenido de labels via `format_text()`. Este proceso **elimina `<script>` y `<style>`** del HTML, independientemente de cómo se haya guardado el contenido. Esto es una restricción de seguridad del núcleo de Moodle, no configurable sin modificar archivos core.

**Consecuencia:** no es posible embeber el JavaScript directamente en el label, incluso generándolo desde el script CLI.

### Solución: separación de responsabilidades

La plantilla de Oliver se divide en dos partes permanentes:

| Parte | Dónde vive | Frecuencia de cambio |
|---|---|---|
| **JavaScript** (lógica de interactividad) | Boost Union → Apariencia → JavaScript personalizado | Una sola vez |
| **CSS** (estilos de las opciones) | `conectatech-post.scss` | Una sola vez |
| **HTML** (estructura con datos de cada pregunta) | `mod_label` generado por el script | Cada vez que hay contenido nuevo |

El JavaScript en Boost Union se carga en **todas las páginas del sitio** y escucha los elementos `[data-quiz-block]` del DOM. Cuando el label con el HTML del presaberes se renderiza en la página, el JS ya está disponible y el widget funciona automáticamente.

### Sobre los atributos `data-*`

Los atributos `data-*` en elementos HTML (distintos de `<script>`) tienen soporte en HTMLPurifier y son generalmente preservados por Moodle en versiones recientes. Sin embargo, esto puede variar según la configuración de la instancia.

**Estrategia de fallback** si `data-feedback` es eliminado: en lugar de `data-feedback="texto"`, se embebe el feedback como un `<span>` oculto dentro del div de la opción, y el JS lo lee con `querySelector('.option-feedback')`. El HTML es ligeramente más verboso pero igualmente funcional.

El script PHP debe incluir ambas estrategias y la preferida se configura en `semantic-blocks.json` (nuevo campo `presaberes_feedback_mode: "data-attribute" | "hidden-span"`).

### Marcador en el H2

Siguiendo la convención establecida, la subsección de Presaberes se identifica con `[presaberes]` en el título H2:

```markdown
## Prueba inicial [presaberes]
## ¿Qué sabes sobre la célula? [presaberes]
## Mis ideas previas [presaberes]
```

El texto antes del marcador es el nombre visible de la subsección en Moodle.

---

## 3. Formato markdown para subsecciones `[presaberes]`

### Estructura jerárquica

```
H2 [presaberes]     → subsección (mod_subsection)
  H3                → nombre del bloque de pregunta (se convierte en aria-label del bloque)
    H4 "Contexto"   → párrafo de contexto de la pregunta (opcional)
    H4 "Pregunta"   → metadatos + enunciado de la pregunta
      H5            → texto del enunciado
      Lista         → opciones de respuesta
        Sublista    → feedback de la opción (texto indentado con 3 espacios)
```

### Ejemplo completo

```markdown
## Prueba inicial [presaberes]

### Idea general del tema

#### Contexto
Observas una planta, un perro y un ser humano. Aunque son muy diferentes, todos están vivos.

#### Pregunta
{tipo: opcion-multiple}

##### ¿Cuál crees que es la característica que todos los seres vivos tienen en común?

- Tienen huesos
   - Algunos seres vivos no tienen huesos. ¡Sigamos explorando!
- Se mueven de la misma forma
   - No todos los seres vivos se mueven igual. Piensa en las plantas...
- Están formados por células [correcta]
   - ¡Muy bien! Todos los seres vivos, sin excepción, están formados por células.
- Necesitan dormir
   - El descanso varía mucho entre los seres vivos. ¡Sigue pensando!

### Estructura celular

#### Contexto
Al observar una imagen ampliada de una célula, se notan varias partes con formas distintas.

#### Pregunta
{tipo: opcion-multiple}

##### ¿Por qué crees que la célula tiene diferentes partes en su interior?

- Para verse más compleja
   - La complejidad de la célula tiene un propósito. ¿Cuál crees que es?
- Porque cada parte cumple una función distinta [correcta]
   - ¡Correcto! Cada organelo tiene una función específica, como los órganos en tu cuerpo.
- Porque todas hacen lo mismo
   - Si todas hicieran lo mismo, la célula no funcionaría bien.
- Solo por protección
   - La protección es una función, pero no la única. Hay mucho más dentro.
```

### Convenciones del parser para `[presaberes]`

| Elemento | Regla |
|---|---|
| H4 `Contexto` | Es opcional. Si existe, se renderiza como `<p class="lead">` dentro del bloque |
| H4 `Pregunta` | Obligatorio. Contiene los metadatos `{tipo: ...}` en la siguiente línea |
| H5 | Texto del enunciado. Se renderiza como `<legend>` dentro del `<fieldset>` |
| Ítem de lista principal | Opción de respuesta |
| `[correcta]` en el ítem | `data-type="correct"` en el div de la opción |
| Sublista del ítem | `data-feedback="..."` de esa opción |
| ID único del bloque | Generado automáticamente: `{shortname}-s{N}-sub{M}-q{Q}` |

---

## 4. HTML generado para cada bloque de Presaberes

Este es el HTML que el script PHP genera para cada pregunta dentro de un `[presaberes]`. No contiene `<script>` ni `<style>`.

```html
<!-- Bloque de pregunta — generado por el script PHP -->
<div class="moodle-quiz-container p-3 mb-4 border rounded bg-white shadow-sm"
     data-quiz-block
     aria-label="Idea general del tema">

  <!-- Contexto (si existe) -->
  <div class="mb-3">
    <p class="mb-0 lead">Observas una planta, un perro y un ser humano. Aunque son muy
    diferentes, todos están vivos.</p>
  </div>

  <!-- Pregunta y opciones -->
  <fieldset class="border-0 p-0">
    <legend class="h5 font-weight-bold mb-3 text-dark">
      ¿Cuál crees que es la característica que todos los seres vivos tienen en común?
    </legend>

    <div class="options-group">

      <div class="respuesta-option custom-control custom-radio mb-3 p-2 rounded border border-light"
           data-type="incorrect"
           data-feedback="Algunos seres vivos no tienen huesos. ¡Sigamos explorando!">
        <input type="radio" id="repo-cn-6-7-s1-sub1-q1-opt1"
               name="repo-cn-6-7-s1-sub1-q1" class="custom-control-input" value="opt1">
        <label class="custom-control-label w-100" for="repo-cn-6-7-s1-sub1-q1-opt1">
          <span class="option-text">Tienen huesos</span>
        </label>
      </div>

      <div class="respuesta-option custom-control custom-radio mb-3 p-2 rounded border border-light"
           data-type="incorrect"
           data-feedback="No todos los seres vivos se mueven igual. Piensa en las plantas...">
        <input type="radio" id="repo-cn-6-7-s1-sub1-q1-opt2"
               name="repo-cn-6-7-s1-sub1-q1" class="custom-control-input" value="opt2">
        <label class="custom-control-label w-100" for="repo-cn-6-7-s1-sub1-q1-opt2">
          <span class="option-text">Se mueven de la misma forma</span>
        </label>
      </div>

      <div class="respuesta-option custom-control custom-radio mb-3 p-2 rounded border border-light"
           data-type="correct"
           data-feedback="¡Muy bien! Todos los seres vivos, sin excepción, están formados por células.">
        <input type="radio" id="repo-cn-6-7-s1-sub1-q1-opt3"
               name="repo-cn-6-7-s1-sub1-q1" class="custom-control-input" value="opt3">
        <label class="custom-control-label w-100" for="repo-cn-6-7-s1-sub1-q1-opt3">
          <span class="option-text">Están formados por células</span>
        </label>
      </div>

      <div class="respuesta-option custom-control custom-radio mb-3 p-2 rounded border border-light"
           data-type="incorrect"
           data-feedback="El descanso varía mucho entre los seres vivos. ¡Sigue pensando!">
        <input type="radio" id="repo-cn-6-7-s1-sub1-q1-opt4"
               name="repo-cn-6-7-s1-sub1-q1" class="custom-control-input" value="opt4">
        <label class="custom-control-label w-100" for="repo-cn-6-7-s1-sub1-q1-opt4">
          <span class="option-text">Necesitan dormir</span>
        </label>
      </div>

    </div>
  </fieldset>

  <!-- Área de retroalimentación (llenada por JavaScript) -->
  <div class="retroalimentacion mt-3" aria-live="polite"></div>

</div>
<!-- Fin del bloque de pregunta -->
```

El HTML de **todas las preguntas** de un bloque `[presaberes]` se concatena en el `intro` de un único `mod_label`, que ocupa la sección delegada del `mod_subsection` correspondiente.

---

## 5. JavaScript global (Boost Union — una sola vez)

Este código se pega en `Administración del sitio > Apariencia > Boost Union > JavaScript personalizado`. Se ejecuta en todas las páginas y activa el comportamiento interactivo en cualquier elemento `[data-quiz-block]` que encuentre.

```javascript
// ConectaTech — Presaberes Interactive Quiz
// Boost Union Custom JavaScript — versión 1.0
document.addEventListener('DOMContentLoaded', function () {
  document.querySelectorAll('[data-quiz-block]').forEach(function (block) {
    block.addEventListener('change', function (e) {
      if (!e.target.matches('input[type="radio"]')) return;

      var selected = e.target.closest('.respuesta-option');
      var feedback = block.querySelector('.retroalimentacion');
      var allOptions = block.querySelectorAll('.respuesta-option');
      var type = selected.dataset.type;
      var feedbackText = selected.dataset.feedback;

      // Bloquear todas las opciones
      allOptions.forEach(function (opt) {
        opt.classList.remove('bg-success', 'bg-danger', 'border-success', 'border-danger', 'text-white');
        opt.classList.add('border-light');
        opt.querySelector('input').disabled = true;
        opt.querySelector('label').style.cursor = 'default';
      });

      // Resaltar opción seleccionada
      selected.classList.remove('border-light');
      if (type === 'correct') {
        selected.classList.add('bg-success', 'border-success', 'text-white');
      } else {
        selected.classList.add('bg-danger', 'border-danger', 'text-white');
      }
      selected.querySelectorAll('label, span').forEach(function (el) {
        el.style.color = '#fff';
      });

      // Mostrar feedback
      var emoji = type === 'correct' ? '✅' : '❌';
      var alertType = type === 'correct' ? 'success' : 'warning';
      var label = type === 'correct' ? '¡Excelente!' : 'Incorrecto';
      feedback.innerHTML =
        '<div class="alert alert-' + alertType + ' d-flex align-items-start shadow-sm" role="alert">' +
        '<div class="mr-3 mt-1" style="font-size:1.5rem">' + emoji + '</div>' +
        '<div><strong>' + label + '</strong>' +
        '<p class="mb-0 mt-1">' + feedbackText + '</p></div></div>';

      feedback.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    });
  });
});
```

---

## 6. CSS para Presaberes (post-SCSS)

Añadir al final de `conectatech-post.scss`, en una nueva sección (nº 17):

```scss
// ----------------------------------------------------------------------------
// 17. PRESABERES — Quiz interactivo diagnóstico
// ----------------------------------------------------------------------------

.moodle-quiz-container {
  .respuesta-option {
    transition: all 0.25s ease;
    cursor: pointer;

    &:hover input:not(:disabled) ~ label {
      color: $conecta-tech-blue;
    }
  }

  .custom-control-label {
    cursor: pointer;
    padding-left: 5px;
  }

  // Corrección de color del radio en fondos oscuros (Bootstrap 4)
  .bg-success,
  .bg-danger {
    .custom-control-label::before {
      filter: brightness(1.2);
    }
  }

  .retroalimentacion {
    .alert {
      border-left-width: 4px;
    }
  }
}
```

---

## 7. Actualización al árbol de tipos de subsección

### Regla de clasificación de H2 (lógica flexible)

Dado que `[presaberes]` y cualquier otro marcador pueden aparecer en **cualquier posición** dentro de una sección, la clasificación del tipo de un H2 sigue este orden de prioridad:

```
1. ¿Tiene marcador explícito?
   ├── [evaluacion]   → subseccion-evaluacion  (en cualquier posición)
   ├── [presaberes]   → subseccion-presaberes  (en cualquier posición)
   └── [otro]         → tipo futuro / warning en log

2. ¿No tiene marcador?
   ├── ¿Es el primero sin marcador en esta sección? → recurso-raiz
   └── ¿Ya hubo un H2 sin marcador antes?          → subseccion-regular
```

El estado `recurso-raiz-encontrado` se resetea por cada H1. La primera vez que el parser encuentre un H2 sin marcador dentro de un H1, lo clasifica como `recurso-raiz`. Todos los H2 sin marcador subsiguientes en esa misma sección son `subseccion-regular`.

### Casos cubiertos por esta lógica

| Estructura del documento | Resultado |
|---|---|
| `## Texto bíblico` (primero, sin marcador) | `recurso-raiz` |
| `## Presaberes [presaberes]` (primero) | `subseccion-presaberes` — y el `recurso-raiz` queda vacío (válido) |
| `## [presaberes]`, luego `## Introducción` | presaberes + `recurso-raiz` (primer H2 sin marcador) |
| `## Intro`, `## [presaberes]`, `## Tema 1` | `recurso-raiz`, presaberes, `subseccion-regular` |
| `## Tema 1`, `## [presaberes]`, `## Tema 2`, `## [evaluacion]` | `recurso-raiz`, presaberes, regular, evaluacion |

Una sección **puede no tener `recurso-raiz`** si todos sus H2 tienen marcador explícito. El script maneja esto sin error.

### Tabla resumen de tipos

| Condición | Tipo | Acción en Moodle |
|---|---|---|
| H2 con `[evaluacion]` (cualquier posición) | `subseccion-evaluacion` | `mod_subsection` + `mod_quiz` |
| H2 con `[presaberes]` (cualquier posición) | `subseccion-presaberes` | `mod_subsection` + `mod_label` interactivo |
| Primer H2 sin marcador de la sección | `recurso-raiz` | `mod_label` directo en sección padre |
| H2 sin marcador, después de otro sin marcador | `subseccion-regular` | `mod_subsection` + `mod_label` |

---

## 8. Actualización a `semantic-blocks.json`

Añadir el campo global `presaberes_feedback_mode`:

```json
{
  "version": "1.1",
  "presaberes_feedback_mode": "data-attribute",
  "blocks": [
    ...
  ]
}
```

Valores posibles:
- `"data-attribute"` (por defecto): usa `data-feedback="..."` en el div. Requiere que HTMLPurifier permita `data-*` en la instancia.
- `"hidden-span"`: añade `<span class="option-feedback visually-hidden">...</span>` dentro del label. Funciona en cualquier configuración de Moodle. El JS debe leerlo con `querySelector('.option-feedback')` en lugar de `dataset.feedback`.

Si tras el primer despliegue se comprueba que los `data-*` son eliminados, se cambia este valor y se re-ejecuta el script (el modo actualización sobreescribe el label existente).

---

## 9. Árbol de ficheros actualizado

```
/var/www/scripts/automation/
├── config/
│   ├── courses.csv
│   └── semantic-blocks.json           ← versión 1.1 con presaberes_feedback_mode
├── lib/
│   ├── MoodleBootstrap.php
│   ├── MarkdownParser.php             ← maneja H2/H3/H4/H5 para presaberes
│   ├── HtmlConverter.php             ← genera bloques semánticos regulares
│   ├── PresaberesHtmlBuilder.php     ← NUEVO: genera el HTML interactivo
│   ├── GiftConverter.php             ← genera GIFT con feedback por opción
│   └── MoodleContentBuilder.php
├── procesar-markdown.php
├── report-ultimo.json
└── logs/
    └── automation.log
```

Se añade `PresaberesHtmlBuilder.php` como módulo independiente para encapsular la lógica de generación del HTML interactivo. Esto mantiene `HtmlConverter.php` enfocado en los bloques semánticos regulares.

---

## 10. Tabla de tipos de pregunta — estado final

| Tipo | Sintaxis en markdown | Destino en Moodle | Feedback |
|---|---|---|---|
| Ensayo (texto) | Sin `{tipo}` o `{tipo: ensayo, variante: texto}` | Quiz (`[evaluacion]`) | No (revisión manual) |
| Opción múltiple | `{tipo: opcion-multiple}` + lista con `[correcta]` | Quiz (`[evaluacion]`) vía GIFT | Sí, por opción |
| Presaberes opción múltiple | `{tipo: opcion-multiple}` en bloque `[presaberes]` | `mod_label` HTML interactivo | Sí, inmediato, no calificado |
| Verdadero/Falso | `{tipo: verdadero-falso, respuesta: verdadero}` | Quiz (`[evaluacion]`) vía GIFT | Futuro |
| Emparejamiento | `{tipo: emparejamiento}` + lista `X → Y` | Quiz (`[evaluacion]`) vía GIFT | Futuro |
| Respuesta corta | `{tipo: respuesta-corta}` | Quiz (`[evaluacion]`) vía GIFT | Futuro |
| Numérica | `{tipo: numerica, respuesta: N, tolerancia: T}` | Quiz (`[evaluacion]`) vía GIFT | Futuro |
| Ensayo con adjunto | `{tipo: ensayo, variante: adjunto}` | Quiz (`[evaluacion]`) | Futuro |

Los tipos marcados como "Futuro" no se implementan en la Fase A, pero el parser debe reconocer su sintaxis y loguear una advertencia, sin fallar, para que los documentos actuales sean compatibles cuando se implementen.
