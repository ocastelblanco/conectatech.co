# Respuestas

## 1. Formato de preguntas en el Markdown

Usaremos, principalmente, los siguientes tipos de pregunta:

- Opción múltiple
- Verdadero / Falso
- Emparejamiento
- Respuesta corta
- Numérica
- Ensayo, con las variantes:
   - Campo de texto sin documento adjunto
   - Documento adjunto sin campo de texto
   - Campo de texto con documento adjunto

**NOTA IMPORTANTE:** este listado puede cambiar, porque aún estamos definiendo el modelo de contenidos; por lo mismo, es posible modificar los modelos iniciales en el documento de Google, para que, luego de ser convertido a markdown, sea más fácil de interpretar.

## 2. Mapeo completo de bloques semánticos

El mapeo, en este momento, incluye:

| Título de tercer nivel | CSS principal | CSS adicional | H3 visible |
|---|---|---|---|
| Texto bíblico guía | `cita-biblica` | `resaltado` | NO |
| Introducción motivadora | `introduccion-motivadora` | NO | NO |
| ¿Qué tiene que ver esto contigo? | `que-tiene-que-ver-esto-contigo` | NO | SI |
| Objetivos de aprendizaje con sentido cristiano | `objetivos-de-aprendizaje-con-sentido-cristiano` | NO | SI |
| Cierre espiritual | `cierre-espiritual` | `resaltado` | SI |
| Reflexiona | `reflexiona` | NO | SI |
| Ponte en acción | `ponte-en-acción` | NO | SI |


**NOTA IMPORTANTE:** este mapeo puede cambiar. En este momento aún estamos definiendo la estructura y algunos modelos pueden cambiar o se pueden añadir. Es importante que el sistema tenga una forma de determinar este mapeo en un documento aparte del código, por ejemplo, en un JSON.

## 3. Comportamiento al re-ejecutar

Los contenidos de la plataforma se irán nutriendo con el tiempo, como parte de la propuesta de valor que le daremos a nuestros clientes. Así que el curso repositorio de Ciencias Naturales de 6º y 7º, por ejemplo, podría iniciar con 18 recursos y, luego de 6 meses, podríamos añadir 4 más.

Los cursos repositorios, por lo tanto, se identificarían por `shortname` ya que, luego de haber sido creado, un curso repositorio no se elimina ni cambia de grado o materia.

En caso de que se procese un markdown con un título de primer nivel (H1) que ya exista en el curso repositorio, se debería sobreescribir. De esa forma, podemos mantener los archivos de Google Docs (y los markdowns generados desde ellos) como fuente de verdad y sincronizar información de forma constante.

## 4. Nombre de los cursos repositorio

Habrá un mapa explícito (puede ser un CSV) con la relación URL → Nombre largo del curso → shortname. Cuando volquemos todo esto a una aplicación web, desde la app podremos modificar el mapa.

## 5. Cuenta de servicio de Google

No he pensado en esto, realmente. Hazme la propuesta más sencilla que encuentres, teniendo en cuenta que la cuenta de Google que usaremos es gratuita.

Si consideras que puede ser complejo la implementación del sistema de descarga/conversión automática, podemos obviar el paso y descargar los documentos manualmente, subirlos al servidor y procesarlos desde ahí. Convertir 17 documentos no es un trabajo extremo y lo puede realizar cualquier persona con un mínimo de habilidades en el entorno Google Drive.

## 6. Cuestionarios

Inicialmente, no, no tendrán retroalimentación por respuesta. En este momento, los cuestionarios solo tienen preguntas tipo **Ensayo**, que serán leídas por el profesor, sin generar calificación automática. Pero, debido a que el modelo podrá complejizarse en los siguientes meses, aumenté el tipo de preguntas posible en el punto [Formato de preguntas en el Markdown](#formato-de-preguntas-en-el-markdown).

Como comentaba anteriormente, aún no tenemos un formato definido en Google Docs para los cuestionarios, así que estamos a tiempo de proponer una plantilla que facilite la conversión a documentos en formato GIFT.

## NOTA FINAL

Ten en cuenta que Claude Code tiene acceso completo a las instancias EC2 y RDS (y demás servicios AWS) a través de AWS CLI.
