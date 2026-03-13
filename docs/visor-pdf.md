# Visor de PDF para Moodle

## Componentes

El proyecto tiene dos componentes:

- Un visor de PDF
- Unas funcionalidades en la aplicación de administración que permitan:
  - Cargar, renombrar, descargar y eliminar archivos PDF del bucket AWS S3 que funciona como CDN (`assets.conectatech.co`).
  - Crear recursos tipo **Área de texto y medios** en secciones específicas de cursos específicos de Moodle, que permitan, mediante un elemento `iframe` visualizar el visor desde Moodle.

## Objetivo

Implementar un visor de PDF interactivo tipo _flipbook_ en Angular 21.x, desplegarlo en el CDN y habilitar la gestión de archivos y la creación automática de recursos en Moodle desde la app de administración actual.

## Visor

### Stack tecnológico

Se desarrollará en Angular 21.x, siguiendo las skills de buenas prácticas `angular-best-practices-21` y `frontend-design`.

Como librería principal, se usará [ngx-extended-pdf-viewer](https://github.com/stephanrauh/ngx-extended-pdf-viewer).

### Características del visor

- Vive en el [CDN de ConectaTech](`https://assets.conectatech.co/herramientas/visor-pdf`).
- Carga un PDF desde el [CDN de ConectaTech](`https://assets.conectatech.co/recursos/pdf/`).
- Si dentro del llamado GET al visor se le indica un rango (formato _start_-_end_), solo permite la visualización de dicho rango de páginas: usa el evento (pageChange) para impedir que el usuario navegue fuera de esos límites y configura [minPage]="start" y [maxPage]="end"; de lo contrario, se podrá visualizar el PDF en su totalidad.
- El parámetro `pageViewMode` será `book` por defecto.

## Índice de recursos PDF

Se generará un índice en formato `JSON` con todos los archivos PDF almacenados en `https://assets.conectatech.co/recursos/pdf/` para facilitar su gestión. La app de administración gestionará, entonces, ese índice, y el visor PDF lo usará para obtener las rutas de los archivos.

El índice deberá almacenar un ID único por cada archivo, de tal forma que se pueda cambiar el nombre del archivo (en el filesystem y en el índice), pero el ID no cambie.

Este índice deberá ser compatible con las estructuras para bases de datos NoSQL tipo DynamoDB o DocumentDB.

## Funcionalidades en la app de administración

### Gestión de PDF en el CDN

Mediante la creación de una API (`https://api.conectatech.co`) basada en API Gateway / Lambda / Node.js 24.x, se podrán gestionar (cargar, descargar, renombrar, eliminar) archivos PDF en el bucket de S3 que funciona como CDN.

**NOTA IMPORTANTE:** La API que se va a crear será de uso general para los servicios de ConectaTech, así que la gestión de PDF será solo la primera de muchas funciones que ofrezca.

### Creación de recursos en Moodle

Crear en cualquier sección de los cursos repositorio un recurso tipo **Área de texto y medios** con las siguientes características:
- El título del recurso será el título del PDF.
- Se usará un `iframe` que incruste el visor, con las siguientes características:
  - Ancho: 100%
  - Altura: variable, de máximo 600px
  - Deberá indicar el ID del PDF (para que obtenga la ruta)
  - Deberá indicar el rango de páginas (si se define al momento de la creación)
  - Deberá cumplir con todos los protocolos de seguridad, tanto de Moodle como de CloudFront (X-Frame-Options o Content-Security-Policy: frame-ancestors).
