# Visualizador fullscreen

## Objetivo

Construir un visor fullscreen de los contenidos de cada tema por cada unidad, es decir, a partir de los encabezados de primer nivel en el documento Markdown origen.

## Características

- El visor se debe crear automáticamente, en el mismo proceso en el que se crea el contenido del curso repositorio.
- El visor se activará con un botón con el texto **▶ Exponer contenido**, que se encuentra al inicio de cada tema, en un **Área de texto y medios**; cuando el usuario hace clic sobre el botón, el visor se depliega a pantalla completa.
- El usuario podrá navegar por el contenido del visor a través de un índice lateral colapsible y unos botones con íconos de flechas para avanzar y retroceder, que cargan un panel central con los contenidos de cada subtema (encabezado de segundo nivel).
- Tanto el índice lateral como el panel central tendrán scroll vertical si es necesario.
- Se visualizarán únicamente los contenidos que en Moodle se visualizan mediante un **Área de texto y medios**; las evaluaciones no se visualizarán.
- El visor deberá heredar los estilos del curso, definidos desde Moodle en el tema **Boost Union** como SCSS adicionales (ver [carpeta snippets](/Users/ocastelblanco/Documents/ConectaTech/conectatech.co/snippets/). Los estilos adicionales deberán ir incluidos en el visor.

## Esquema básico de UI

Para un tema de **Cosmovisión cristiana**, este sería el esquema UI general:

```
╔══════════════════════════════════════════════════════════════════════╗
║                         TÍTULO DEL TEMA                              ║
║ ┌──────────────────────────────────────────────────────────────────┐ ║
║ │                                          REFERENTE BÍBLICO TEMA  │ ║
║ └──────────────────────────────────────────────────────────────────┘ ║
║                                                                      ║
║ ┌─────────┐ ┌──────────────────────────────────────────────────────┐ ║
║ │ VÍNCULO │ │                   TITULO SUBTEMA                     │ ║
║ │ SUBTEMA │ │ ┌──────────────────────────────────────────────────┐ │ ║
║ └─────────┘ │ │                        REFERENTE BÍBLICO SUBTEMA │ │ ║
║ ┌─────────┐ │ └──────────────────────────────────────────────────┘ │ ║
║ │ VÍNCULO │ │ SUBTÍTULO                                            │ ║
║ │ SUBTEMA │ │ Contenido de texto del subtítulo                     │ ║
║ └─────────┘ │                                                      │ ║
║ ┌─────────┐ │ SUBTÍTULO                                            │ ║
║ │ VÍNCULO │ │ Contenido de texto del subtítulo                     │ ║
║ │ SUBTEMA │ │                                                      │ ║
║ └─────────┘ │ SUBTÍTULO                                            │ ║
║ ┌─────────┐ │ Contenido de texto del subtítulo                     │ ║
║ │ VÍNCULO │ │                                                      │ ║
║ │ SUBTEMA │ │ SUBTÍTULO                                            │ ║
║ └─────────┘ │ Contenido de texto del subtítulo                     │ ║
║             └──────────────────────────────────────────────────────┘ ║
║              < ANTERIOR                                  SIGUIENTE > ║ → Botones de avance y retroceso entre subtemas
╚══════════════════════════════════════════════════════════════════════╝
       ↓                               ↓
   Índice de                    Panel de contenido
   subtemas

```

## Stack tecnológico

Se usará la versión más reciente de Angular 22.x (usa la skill `/angular-developer`), creando una subcarpeta en este repositorio, llamada `fullscreen-viewer`, con su propio `README.md` (usa las skills `/slim-readme` y `/slim-badges`).

## Decisiones de diseño (aprobadas 2026-11-09)

| Tema | Decisión |
|---|---|
| Origen del contenido | En vivo desde Moodle: un endpoint de solo lectura arma el tema leyendo los labels de la sección del curso actual. No se publica nada en el CDN |
| Dónde vive el visor | `https://conectatech.co/ct-visor/` — mismo dominio que Moodle, para compartir la sesión |
| Audiencia | Solo docentes y gestores. El label del botón se crea oculto para estudiantes |
| Cursos existentes | Script CLI de retrocompatibilidad que inserta el botón en los temas ya creados |
| Presaberes | Excluidos del visor, igual que las evaluaciones |
| Versión de Angular | 22.x (verificado: `22.2.5` es `latest` en npm el 2026-11-09) |

### Impedimentos técnicos y solución adoptada

1. **Moodle elimina `<script>`, `<style>` y `onclick` de los labels.** El label lleva solo un enlace con la clase `ct-exponer-btn`; el comportamiento lo pone un listener en el script global de `snippets/conectatech-end-snippet.html` (mismo patrón que presaberes).
2. **Un iframe en el CDN no recibe la sesión de Moodle y dejaría el contenido público.** Por eso el visor se sirve desde el mismo dominio de Moodle y el endpoint exige sesión y permiso sobre el curso.
3. **Los identificadores cambian al clonar del repositorio al curso final.** El botón no lleva identificadores: el script resuelve curso y sección en el momento del clic.
4. **Los estilos de `snippets/` están anclados al DOM de Moodle.** El visor compila los dos SCSS tal cual (más Bootstrap) y reproduce el envoltorio mínimo de Moodle; no se copian estilos. Si los snippets cambian, hay que recompilar y redesplegar el visor.
5. **iPhone no ofrece pantalla completa real para páginas.** Se usa la Fullscreen API donde existe y, como respaldo, una capa fija que ocupa toda la ventana.

### Limitaciones de la primera versión

- Los labels que incrustan el visor PDF (un `<iframe>`) no se muestran dentro del visor.
- El visor refleja lo que hay en Moodle en el momento de abrirlo; no funciona sin conexión.

Detalle técnico: `tech-specs.md` §6.4 · Decisión de arquitectura: `MEMORY.md` ADR-012 · Fases de implementación: `TODO.md`.

