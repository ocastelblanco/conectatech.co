# Manual: insertar una imagen desde el CDN

> Dirigido a: editores y creadores de contenido de ConectaTech
> Requiere: cuenta en el panel de administración con rol de **administrador de Moodle**
> Última actualización: 2026-08-11

---

## 1. ¿Qué es esto?

Es el proceso para subir una imagen a la biblioteca de archivos de ConectaTech (el CDN) y luego usarla dentro del contenido de un curso: portadas de libro, íconos, fotografías, diagramas, etc.

A diferencia del [visor de PDF](./insertar-pdf-viewer.md), las imágenes **no tienen un paso de "crear recurso" automático**. El proceso es:

1. **Cargar la imagen** a la biblioteca de archivos (el CDN de ConectaTech) → obtienes una URL.
2. **Usar esa URL** para insertar la imagen donde la necesites: dentro del Markdown que se procesa en **Crear Contenido**, o directamente en el editor de texto de Moodle.

No se necesita tocar Moodle directamente ni escribir código para el paso 1. El paso 2 sí requiere escribir una línea sencilla (si usas Markdown) o pegar la URL en un cuadro del editor (si usas Moodle directamente) — ambas formas se explican abajo.

---

## 2. Antes de empezar

- Debes tener acceso al panel de administración: `https://admin.conectatech.co`, con una cuenta que tenga rol de administrador en Moodle. Si no puedes iniciar sesión o no ves el menú **Activos CDN**, contacta al equipo técnico.
- Ten lista la **imagen** en uno de estos formatos: `.png`, `.jpg` / `.jpeg`, `.webp` o `.gif`. Otros formatos (por ejemplo `.svg` o `.bmp`) no se pueden cargar desde el panel.
- Idealmente, usa imágenes ya optimizadas para web (peso razonable, no fotos sin comprimir de varios MB) para que las páginas del curso carguen rápido.

---

## 3. Paso 1 — Cargar la imagen a la biblioteca de archivos

1. En el panel de administración, entra al menú **Activos CDN** (icono de carpeta, en la barra lateral izquierda).
2. Cambia a la pestaña **Imágenes** (junto a **PDFs**, en la parte superior).
3. Haz clic en el botón **Cargar activo** (arriba a la derecha).
4. En la ventana que se abre:
   - Arrastra la imagen al recuadro punteado, o haz clic sobre él para buscarla en tu computador.
   - En el campo **Título**, escribe un nombre descriptivo (por defecto se autocompleta con el nombre del archivo, pero conviene cambiarlo por algo identificable, por ejemplo: *"Portada — Cartilla Ciencias Naturales Grado 6"*).
5. Haz clic en **Cargar**.

En cuanto termina la carga, el panel:

- Muestra el mensaje **"Imagen cargada — URL copiada al portapapeles"**.
- Copia automáticamente la URL pública de la imagen a tu portapapeles (puedes pegarla de una vez con `Ctrl+V` / `Cmd+V` donde la necesites).

> La imagen queda disponible en la tabla de la pestaña **Imágenes**, donde puedes verla en cualquier momento, buscarla por título, renombrarla, volver a copiar su URL o eliminarla.

Si necesitas volver a copiar la URL más adelante (por ejemplo, otro día, para otro curso), ve a **Activos CDN → Imágenes**, busca la imagen en la tabla y haz clic en el ícono **🔗 Copiar URL** de esa fila.

---

## 4. Paso 2 — Insertar la imagen en el curso

Hay dos formas de usar la URL que copiaste, según cómo estés creando el contenido.

### Opción A — Dentro de un archivo Markdown (flujo "Crear Contenido")

Si el curso se está armando con el flujo de **Crear Contenido** (el que convierte un archivo `.md` en secciones de Moodle), inserta la imagen escribiendo esta línea en el lugar del documento donde debe aparecer:

```
![Texto alternativo de la imagen](URL-QUE-COPIASTE)
```

Ejemplo real:

```
![Portada del libro Ciencias Naturales grado 6](https://assets.conectatech.co/recursos/img/portada-cn-6.jpg)
```

- El **texto alternativo** (entre `[ ]`) describe la imagen para lectores de pantalla y para cuando la imagen no carga — siempre escribe algo breve y descriptivo, nunca lo dejes vacío.
- La **URL** (entre `( )`) es exactamente la que copiaste en el paso 1. Pégala tal cual, sin espacios ni saltos de línea en medio.
- Si esta línea está **sola en su propio párrafo** (con una línea en blanco antes y después), la imagen se mostrará como bloque independiente, centrada en su propio espacio.
- También puedes insertarla **en medio de un párrafo de texto**, escribiendo la misma sintaxis dentro de la oración; en ese caso queda alineada con el texto en vez de en bloque aparte.

Cuando termines de editar el Markdown, sigue el flujo normal de **Crear Contenido**: selecciona el curso repositorio destino, revisa la vista previa de la estructura y haz clic en **Procesar en Moodle**.

> **Uso avanzado (opcional):** la sintaxis admite una clase CSS adicional así: `![alt](url){nombre-clase}`. Solo úsala si el equipo técnico te ha indicado explícitamente un nombre de clase para un estilo particular (por ejemplo, imágenes dentro de bloques especiales del curso). Si no te han dado ninguna instrucción al respecto, no agregues nada entre llaves — la imagen se mostrará con el estilo estándar.

### Opción B — Directamente en el editor de Moodle

Si necesitas insertar la imagen en un recurso que ya existe en Moodle (por ejemplo, editando manualmente una **Área de texto y medios**), sin pasar por el flujo de Markdown:

1. Entra a Moodle (`https://conectatech.co`), activa la edición del curso y abre para editar el recurso donde quieres insertar la imagen.
2. En la barra de herramientas del editor de texto, busca el ícono de **Imagen** (suele verse como un cuadro con una montaña pequeña).
3. En la ventana que se abre, selecciona la pestaña o campo para insertar **por URL** (no "Examinar" ni "Subir archivo" — la imagen ya vive en el CDN, no hace falta volver a subirla).
4. Pega la URL que copiaste en el paso 1.
5. Completa el campo de **texto alternativo** con una descripción breve de la imagen.
6. Guarda los cambios del recurso y del curso.

---

## 5. Verificar que quedó bien

1. Entra al curso en Moodle (`https://conectatech.co`) con un usuario que tenga acceso (o como administrador).
2. Ve a la sección o recurso donde insertaste la imagen.
3. Confirma que la imagen se ve correctamente, con buen tamaño y sin aparecer rota (ícono de imagen no encontrada).

Si la imagen no aparece, revisa la sección [Problemas comunes](#6-problemas-comunes).

---

## 6. Problemas comunes

| Situación | Causa probable | Qué hacer |
|---|---|---|
| La imagen aparece rota (ícono roto) en el curso | La URL se pegó incompleta, con espacios, o la imagen fue eliminada de la biblioteca | Copia de nuevo la URL desde **Activos CDN → Imágenes** (ícono 🔗) y vuelve a pegarla completa |
| El panel no me deja cargar el archivo | El formato no es compatible (por ejemplo `.svg`, `.bmp`, `.heic`) | Convierte la imagen a `.png`, `.jpg` o `.webp` antes de cargarla |
| No encuentro la imagen que subí hace tiempo | Puede que la busques en la pestaña equivocada | Verifica que estés en la pestaña **Imágenes** de **Activos CDN**, no en **PDFs** |
| La imagen se ve muy grande o descuadra el texto | No se aplicó ningún ajuste de tamaño | Consulta con el equipo técnico si existe una clase CSS predefinida para tu caso; evita insertar imágenes sin optimizar (revisa el tamaño del archivo antes de cargarlo) |
| Cambié el título de la imagen en la biblioteca y ahora no se ve en el curso | Renombrar el título **no cambia la URL** del archivo, así que no debería romperse — si se rompió, probablemente la imagen fue eliminada, no solo renombrada | Verifica en **Activos CDN → Imágenes** que el archivo siga existiendo, y vuelve a copiar su URL |

---

## 7. Preguntas frecuentes

**¿Puedo usar la misma imagen en varios cursos o secciones?**
Sí. Súbela una sola vez y reutiliza la misma URL las veces que necesites, en cualquier curso.

**¿Renombrar una imagen en la biblioteca cambia su URL?**
No. El título es solo una etiqueta para identificarla en el panel; la dirección del archivo en el CDN no cambia al renombrarla.

**¿Puedo eliminar una imagen de la biblioteca si ya la usé en un curso?**
Evítalo: si eliminas el archivo del CDN, la imagen dejará de verse en todos los lugares donde la insertaste (quedará "rota"). Antes de eliminar una imagen, confirma que ningún curso la esté usando.

**¿Hay límite de tamaño para la imagen?**
La carga se hace directo al almacenamiento en la nube (no pasa por el panel), así que archivos grandes también funcionan técnicamente. Aun así, usa imágenes ya optimizadas para web — los archivos muy pesados hacen que las páginas del curso carguen lento para los estudiantes.

**¿Qué diferencia hay entre insertar la imagen en Markdown y hacerlo directo en Moodle?**
Si el curso se está construyendo o actualizando con el flujo de **Crear Contenido** (Markdown), usa la Opción A — es la forma estándar y queda registrada en el archivo fuente del curso. La Opción B (editor de Moodle) es para ajustes puntuales sobre contenido que ya existe directamente en la plataforma.
