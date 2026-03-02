# Creación de flujos de automatización - Moodle

## Contexto

Quiero automatizar la creación de grandes volúmenes de información como recursos de un curso en Moodle.

Mi idea es crear una serie de cursos repositorio, que no están visibles para los usuarios, con múltiples secciones, cada una de ellas con uno o más recursos y actividades.

Voy a crear los siguientes 17 cursos repositorios:

- Ciencias Naturales
   - Grados 4º y 5º
   - Grados 6º y 7º
   - Grados 8º y 9º
- Física
   - Grados 10º y 11º
- Química
   - Grados 10º y 11º
- Matemáticas
   - Grados 4º y 5º
   - Grados 6º y 7º
   - Grados 8º y 9º
   - Grados 10º y 11º
- Lenguaje
   - Grados 4º y 5º
   - Grados 6º y 7º
   - Grados 8º y 9º
   - Grados 10º y 11º
- Ciencias Sociales
   - Grados 4º y 5º
   - Grados 6º y 7º
   - Grados 8º y 9º
   - Grados 10º y 11º

Cada uno de estos 17 cursos repositorio tendría entre 30 y 40 secciones, unas 600 secciones en total.

La estructura de cada sección es la misma (pueden ser 4 o 5 subsecciones, incluyendo la subsección final):

- Recurso tipo **Área de texto y medios**
- Subseccion 1
   - Recurso tipo **Área de texto y medios**
- Subseccion 2
   - Recurso tipo **Área de texto y medios**
- Subseccion 3
   - Recurso tipo **Área de texto y medios**
- Subseccion final
   - Actividad tipo **Cuestionario**
   - Actividad tipo **Cuestionario**
   - Recurso tipo **Área de texto y medios**

Los contenidos de cada curso repositorio están en un documento de Google Drive; es decir, 17 Google Docs en total.

## Propuesta

Mediante llamados a la API (o usando un endpoint en Google Apps Script) convertiré y descargaré cada documento en un archivo markdown, en el que el título de primer nivel indica el nombre y el inicio de una sección, los títulos de segundo nivel indican los nombres e inicio de cada subsección.

Cada recurso tipo **Área de texto y medios** (sea de sección o subsección) tiene unas partes distinguibles que, ya montadas en Moodle, tendrán una apariencia muy definida en la hoja de estilos de Moodle. Por ejemplo, uno de estos recursos podría verse, ya en formato HTML, como:

```html
<div class="resaltado cita-biblica">
  <p><em>“Porque en él fueron creadas todas las cosas, las que hay en los cielos y las que hay en la tierra, visibles e invisibles; sean tronos, sean dominios, sean principados, sean potestades; todo fue creado por medio de él y para él. Y él es antes de todas las cosas, y en él todas las cosas subsisten.”</em></p>
  <p><strong>Colosenses 1:16-17</strong></p>
</div>
<div class="que-tiene-que-ver-esto-contigo">
  <h3><strong>¿Qué tiene que ver esto contigo?</strong></h3>
  <p><strong>Tu vida no es casualidad</strong>. Si Dios sostiene la célula, también sostiene tu historia. <strong>Lo invisible en ti </strong>—tus pensamientos, emociones y sueños— tiene valor porque fue creado por Él.</p>
</div>
<div class="resaltado cierre-espiritual">
  <h3>Cierre espiritual</h3>
  <p><em>“Señor, gracias porque en ti todo subsiste, desde lo más grande hasta lo más pequeño. Ayúdame a valorar el don de la vida y a reconocer que incluso en lo invisible Tú muestras tu poder y propósito. Amén.”</em></p>
</div>
```

Para este ejemplo, el fragmento de markdown correspondiente sería:

```markdown
### Texto bíblico guía
_“Porque en él fueron creadas todas las cosas, las que hay en los cielos y las que hay en la tierra, visibles e invisibles; sean tronos, sean dominios, sean principados, sean potestades; todo fue creado por medio de él y para él. Y él es antes de todas las cosas, y en él todas las cosas subsisten.”_
**Colosenses 1:16-17**

### ¿Qué tiene que ver esto contigo?
**Tu vida no es casualidad**. Si Dios sostiene la célula, también sostiene tu historia. **Lo invisible en ti** —tus pensamientos, emociones y sueños— tiene valor porque fue creado por Él.

### Cierre espiritual
_“Señor, gracias porque en ti todo subsiste, desde lo más grande hasta lo más pequeño. Ayúdame a valorar el don de la vida y a reconocer que incluso en lo invisible Tú muestras tu poder y propósito. Amén.”_
```

Un documento modelo markdown (en este ejemplo, solo 2 secciones) podría ser:

```markdown
# Nombre de la sección 1

## Recurso raíz sección 1

### Título contenido 1 recurso raíz sección 1
Contenido 1 recurso raíz sección 1

### Título contenido 2 recurso raíz sección 1
Contenido 2 recurso raíz sección 1

### Título contenido 3 recurso raíz sección 1
Contenido 3 recurso raíz sección 1

## Nombre de subsección 1 sección 1

### Título contenido 1 subseccion 1 sección 1
Contenido 1 subseccion 1 sección 1

### Título contenido 2 subseccion 1 sección 1
Contenido 2 subseccion 1 sección 1

### Título contenido 3 subseccion 1 sección 1
Contenido 3 subseccion 1 sección 1

## Nombre de subsección 2 sección 1

### Título contenido 1 subseccion 2 sección 1
Contenido 1 subseccion 2 sección 1

### Título contenido 2 subseccion 2 sección 1
Contenido 2 subseccion 2 sección 1

### Título contenido 3 subseccion 2 sección 1
Contenido 3 subseccion 2 sección 1

## Nombre de subsección 3 sección 1

### Título contenido 1 subseccion 3 sección 1
Contenido 1 subseccion 3 sección 1

### Título contenido 2 subseccion 3 sección 1
Contenido 2 subseccion 3 sección 1

### Título contenido 3 subseccion 3 sección 1
Contenido 3 subseccion 3 sección 1


# Nombre de la sección 2

## Recurso raíz sección 2

### Título contenido 1 recurso raíz sección 2
Contenido 1 recurso raíz sección 2

### Título contenido 2 recurso raíz sección 2
Contenido 2 recurso raíz sección 2

### Título contenido 3 recurso raíz sección 2
Contenido 3 recurso raíz sección 2

## Nombre de subsección 2 sección 2

### Título contenido 1 subseccion 1 sección 2
Contenido 1 subseccion 1 sección 2

### Título contenido 2 subseccion 1 sección 2
Contenido 2 subseccion 1 sección 2

### Título contenido 3 subseccion 1 sección 2
Contenido 3 subseccion 1 sección 2

## Nombre de subsección 2 sección 2

### Título contenido 1 subseccion 2 sección 2
Contenido 1 subseccion 2 sección 2

### Título contenido 2 subseccion 2 sección 2
Contenido 2 subseccion 2 sección 2

### Título contenido 3 subseccion 2 sección 2
Contenido 3 subseccion 2 sección 2

## Nombre de subsección 3 sección 2

### Título contenido 1 subseccion 3 sección 2
Contenido 1 subseccion 3 sección 2

### Título contenido 2 subseccion 3 sección 2
Contenido 2 subseccion 3 sección 2

### Título contenido 3 subseccion 3 sección 2
Contenido 3 subseccion 3 sección 2
```

### Primer flujo de automatización

El volúmen de contenido es muy grande y usar la interfaz de Moodle, creando las secciones, los recursos, copiando y pegando los HTML (o, peor aún, convirtiendo los markdown en HTML de forma manual), va a ser muy costoso en tiempo y dinero.

Necesito, por lo tanto, crear un flujo de automatización para todo el proceso que, de forma ideal, permita:

1. Dada la URL del documento Google Doc (tipo `https://docs.google.com/document/d/1pmTIu1012eXW89rPogG3jWXUGnOp6ZJ2cbNnv_yBj5Y/edit?tab=t.0`), descargarlo en formato markdown.
2. Crear un curso repositorio (en la categoría **Repositorios**) de Moodle, por cada documento descargado, usando el nombre del archivo descargado (o el nombre que se le indique de forma explícita) para generar el nombre y descripción del curso correspondiente.
3. Crear una sección en el curso, por cada título de primer nivel del documento, usando dicho título para generar el nombre de la sección.
4. Si se trata del primer título de segundo nivel de una sección, crear un recurso **Área de texto y medios**, convirtiendo el markdown en HTML, siguiendo algunas reglas específicas;: por ejemplo, en el markdown, lo que esté dentro de un título de tercer nivel "Texto bíblico guía" no genera un `<h3>` y la clase del `<div>` correspondiente será `cita-biblica`.
5. Si se trata de otro título de segundo nivel, crear la subsección correspondiente, usando el título para generar el nombre de la subsección.
6. Crear un recurso **Área de texto y medios** para la subsección correspondiente, siguiendo las mismas reglas del punto 4.
7. En el caso de la última subsección de cada sección, crear las actividades **Cuestionario** en la subsección correspondiente, a partir de un formato markdown por definir.
8. Generar un reporte con los resultados del proceso de creación y generación de contenidos.

**NOTA IMPORTANTE:** Este primer flujo requiere que, si se llegan a construir nuevos contenidos (secciones) para cursos que ya existen, se adjunten al final de cada curso.

### Segundo flujo de automatización

El siguiente flujo de automatización que requiero, para la creación masiva de cursos y usuarios (estudiantes y profesores), y la matriculación de los últimos en los cursos correspondientes, está propuesto en el documento `Manual Técnico Moodle Fase 1.md`.

**NOTA IMPORTANTE:** Este segundo flujo se repetirá de forma constante cada período (año escolar) y, en cada caso, se generarán los cursos y se matricularán los usuarios al inicio, y se eliminarán los cursos y se desmatricularán los usuarios al final.

## Pasos a dar

Necesito que, luego de leer este documento y los que consideres necesarios de la biblioteca, me ayudes a dar los siguientes pasos:

1. Validar las posibilidades y alcances de la propuesta. Si existen algunos pasos que no se puede realizar (por limitaciones de Moodle o de otro tipo), plantear una solución alternativa, práctica, segura y eficiente.
2. Construir scripts que serán almacenados directamente en el servidor que aloja a Moodle, de tal forma que se pueda tener un flujo mixto: parte del proceso se realiza en mi equipo local, parte en el servidor, todo por consola y usando archivos markdown o CSV. Este paso lo puede dar Claude Code, por lo tanto, necesitaré que me ayudes con las instrucciones técnicas precisas para eso.
3. Construir un frontend, alojado en la misma instancia EC2 que Moodle, para gestionar todos los procesos mencionados, que reutilice los scripts generados en el paso 2, que ya habrán sido probados y afinados correctamente. Este paso también lo puede dar Claude Code.
