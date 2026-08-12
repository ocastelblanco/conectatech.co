# Manual: insertar un visor de PDF en un curso

> Dirigido a: editores y creadores de contenido de ConectaTech
> Requiere: cuenta en el panel de administración con rol de **administrador de Moodle**
> Última actualización: 2026-08-11 (actualizado con soporte de subsecciones)

---

## 1. ¿Qué es el visor de PDF?

Es una herramienta que permite mostrar un documento PDF dentro de un curso de Moodle como si fuera un libro digital (efecto *flipbook*, página por página), en lugar de obligar al estudiante a descargar el archivo.

Sirve, por ejemplo, para:

- Insertar una **guía, cartilla o libro completo** en una sección del curso.
- Insertar **solo un capítulo o un rango de páginas** de un PDF más grande (por ejemplo, páginas 12 a 20 de un libro de 200 páginas), sin tener que cortar el archivo.

El proceso tiene **dos pasos** que se hacen desde el mismo panel de administración:

1. **Cargar el PDF** a la biblioteca de archivos (el CDN de ConectaTech).
2. **Crear el visor** dentro de un curso repositorio, indicando en qué sección debe aparecer.

No se necesita tocar Moodle directamente ni escribir código: todo se hace desde el panel.

---

## 2. Antes de empezar

- Debes tener acceso al panel de administración: `https://admin.conectatech.co`, con una cuenta que tenga rol de administrador en Moodle. Si no puedes iniciar sesión o no ves el menú **Activos CDN**, contacta al equipo técnico.
- Ten a la mano el **archivo PDF** ya listo (el archivo definitivo — el título se puede editar después, pero el contenido del PDF no se edita desde el panel).
- Debes saber en qué **curso repositorio** y en qué **sección** quieres que aparezca el visor. Solo se pueden usar cursos de la categoría **REPOSITORIOS**, y únicamente las secciones que ya tengan un **nombre/título asignado** aparecerán como opción (las secciones sin nombre no se muestran en el selector). Si la sección que necesitas no aparece en la lista, pide al equipo técnico que le asigne un nombre en Moodle antes de continuar.
- Si en vez de agregar el visor al final de la sección quieres colocarlo **dentro de una subsección específica** (y reemplazar lo que esa subsección tenga), ten claro de antemano cuál es esa subsección — el reemplazo no se puede deshacer.

---

## 3. Paso 1 — Cargar el PDF a la biblioteca de archivos

1. En el panel de administración, entra al menú **Activos CDN** (icono de carpeta, en la barra lateral izquierda).
2. Asegúrate de estar en la pestaña **PDFs** (junto a **Imágenes**, en la parte superior).
3. Haz clic en el botón **Cargar activo** (arriba a la derecha).
4. En la ventana que se abre:
   - Arrastra el archivo PDF al recuadro punteado, o haz clic sobre él para buscarlo en tu computador.
   - En el campo **Título**, escribe el nombre con el que se identificará el archivo en la biblioteca (por defecto se autocompleta con el nombre del archivo, pero puedes cambiarlo por algo más descriptivo, por ejemplo: *"Cartilla de Ciencias Naturales — Grado 6"*).
5. Haz clic en **Cargar**. Verás una barra de progreso mientras el archivo se sube.

Cuando termine la carga, aparecerá automáticamente una ventana preguntando:

> **"PDF cargado exitosamente. ¿Deseas crear un visor interactivo en Moodle para este PDF?"**

Tienes dos opciones:

- **Crear visor en Moodle** → continúa directamente con el [Paso 2](#4-paso-2--crear-el-visor-en-un-curso).
- **Solo copiar URL** → cierra la ventana y copia al portapapeles el enlace directo del PDF (útil si solo necesitas la URL para otro uso, sin insertarlo como visor todavía). Puedes crear el visor más adelante siguiendo el paso 4 de esta sección.

> El PDF cargado queda disponible en la tabla de la pestaña **PDFs**, donde puedes verlo en cualquier momento, buscarlo por título, renombrarlo, copiar su URL o eliminarlo.

---

## 4. Paso 2 — Crear el visor en un curso

Si no creaste el visor justo después de cargar el archivo, puedes hacerlo en cualquier momento:

1. Ve a **Activos CDN → PDFs**.
2. Busca el archivo en la tabla.
3. En la columna **Acciones**, haz clic en el ícono de **▶ Crear visor en Moodle**.

Se abrirá la ventana **"Crear visor en Moodle"** con estos campos:

| Campo | Qué hacer |
|---|---|
| **Título del recurso en Moodle** | Nombre que verá el estudiante en el curso. Se autocompleta con el título del PDF, pero puedes ajustarlo (por ejemplo, agregar "Capítulo 3" si es solo una parte del libro). |
| **Curso repositorio** | Selecciona el curso donde debe aparecer el visor. Puedes escribir para filtrar por nombre. |
| **Sección** | Selecciona la sección del curso donde se insertará el recurso. Solo aparecen las secciones que ya tienen nombre asignado en Moodle. |
| **Subsección** (selector opcional) | Déjalo en blanco para el comportamiento normal, o elige una subsección existente si quieres reemplazar su contenido (ver abajo). |
| **Limitar rango de páginas** (casilla opcional) | Actívala solo si quieres mostrar una parte del PDF, no el documento completo. |

### Si eliges una subsección (opcional)

Debajo del selector de **Sección** hay un selector opcional de **Subsección**, que solo muestra opciones si la sección elegida ya tiene subsecciones creadas en Moodle.

- **Si lo dejas en blanco** (opción por defecto): el visor se agrega **al final de la sección**, sin tocar nada más — es el comportamiento normal descrito arriba.
- **Si eliges una subsección**: el visor **reemplaza todo el contenido que esa subsección tenga actualmente** (borra lo que había y deja únicamente el visor en su lugar). Es útil para actualizar un visor que ya insertaste antes, o para colocar el PDF en un punto exacto y ya organizado del curso.

Como reemplazar contenido no se puede deshacer, el panel te pedirá una **confirmación explícita** antes de ejecutar el reemplazo: aparecerá un mensaje indicando el nombre de la subsección y pidiéndote confirmar. Si no estás seguro, haz clic en **Cancelar** y verifica primero qué contiene esa subsección en Moodle.

> Para volver a dejar el selector de subsección vacío después de haberlo elegido por error, usa la ✕ que aparece dentro del campo.

### Si activas "Limitar rango de páginas"

Aparecen dos campos adicionales:

- **Página inicio**: primera página visible (ejemplo: `12`).
- **Página fin**: última página visible (ejemplo: `20`).

El estudiante **no podrá navegar fuera de ese rango** — el visor se lo impide automáticamente. Si dejas la casilla sin marcar, el estudiante podrá ver el PDF completo, de principio a fin.

> Usa esta opción cuando el PDF cargado es un libro o compilado extenso y en esta sección del curso solo corresponde un capítulo o fragmento específico.

4. Cuando todos los campos estén completos, el botón **Crear visor** se habilita. Haz clic en él.
5. Verás la confirmación **"Visor creado — Recurso añadido a Moodle exitosamente"**.

Con esto, el visor ya quedó insertado como un recurso **"Área de texto y medios"**: al final de la sección elegida, o reemplazando el contenido de la subsección, según lo que hayas seleccionado.

---

## 5. Verificar que quedó bien

1. Entra al curso en Moodle (`https://conectatech.co`) con un usuario que tenga acceso (o como administrador).
2. Ve a la sección donde creaste el visor.
3. Deberías ver el recurso con el título que le diste, y debajo un recuadro con el PDF cargado en modo libro (pasando páginas).
4. Si limitaste el rango de páginas, confirma que el visor arranca en la página de inicio indicada y que no te deja avanzar más allá de la página de fin.

Si el recuadro aparece vacío o en blanco, revisa la sección [Problemas comunes](#6-problemas-comunes).

---

## 6. Problemas comunes

| Situación | Causa probable | Qué hacer |
|---|---|---|
| No aparece el curso que busco en el selector | El curso no pertenece a la categoría **REPOSITORIOS** en Moodle | Pide al equipo técnico que confirme la categoría del curso |
| No aparece la sección que necesito | La sección no tiene nombre asignado en Moodle | Pide al equipo técnico que le asigne un título a esa sección |
| El botón "Crear visor" no se activa | Falta completar título, curso o sección | Revisa que los tres campos obligatorios tengan valor |
| El visor aparece en blanco dentro del curso | El PDF aún no terminó de sincronizarse en el CDN, o hubo un error de carga | Espera unos minutos y recarga la página del curso; si persiste, contacta al equipo técnico |
| Subí el PDF pero no veo la ventana para crear el visor | Cerraste la ventana de confirmación por error | Ve a **Activos CDN → PDFs**, busca el archivo y usa el ícono ▶ **Crear visor en Moodle** en la fila correspondiente |
| No aparece ninguna opción en el selector de Subsección | La sección elegida no tiene subsecciones creadas en Moodle | Es normal — deja el selector en blanco para insertar el visor al final de la sección, o pide al equipo técnico que cree la subsección primero |
| Elegí una subsección por error y confirmé el reemplazo | Se borró el contenido anterior de esa subsección al aceptar la confirmación | Esta acción no se puede deshacer desde el panel; contacta al equipo técnico para revisar si el contenido anterior puede recuperarse desde otra fuente (por ejemplo, el Markdown original) |

---

## 7. Preguntas frecuentes

**¿Puedo usar el mismo PDF en varios cursos o secciones?**
Sí. El PDF se carga una sola vez a la biblioteca; puedes usar el botón ▶ **Crear visor en Moodle** las veces que necesites, eligiendo un curso/sección distinto cada vez.

**¿Puedo insertar dos rangos distintos del mismo PDF (por ejemplo, un capítulo en cada sección)?**
Sí. Repite el paso 2 seleccionando el mismo PDF, pero con un rango de páginas y una sección diferentes cada vez.

**¿Qué pasa si renombro el PDF en la biblioteca después de crear un visor?**
El visor ya insertado en el curso no cambia de título automáticamente — el título del recurso en Moodle es independiente del título del archivo en la biblioteca.

**¿Puedo eliminar un PDF de la biblioteca si ya tiene visores creados en cursos?**
Evítalo: si eliminas el archivo del CDN, los visores que ya están insertados en los cursos dejarán de mostrar el documento. Antes de eliminar un PDF, confirma que ningún curso lo esté usando.

**¿Hay límite de tamaño para el PDF?**
La carga se hace directo al almacenamiento en la nube (no pasa por el panel), así que documentos grandes también funcionan. Si un archivo muy pesado tarda demasiado en cargar o falla, contacta al equipo técnico.

**¿Puedo editar o reemplazar un visor que ya creé?**
No hay un botón de "editar" para un visor ya insertado en una sección (sin subsección) — en ese caso, la forma de actualizarlo es crear uno nuevo y borrar el anterior manualmente desde Moodle. Pero si el visor original se insertó **dentro de una subsección**, sí puedes actualizarlo: repite el proceso de "Crear visor en Moodle" eligiendo esa misma subsección — el contenido anterior se reemplaza automáticamente (con confirmación previa).
