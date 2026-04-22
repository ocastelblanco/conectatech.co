# PRD — ConectaTech.co
> Product Requirements Document · Audiencia: IA / autor del proyecto
> Idioma: español colombiano · Nivel: referencia
> Última actualización: 2026-04-14

---

## Tabla de contenidos

1. [Visión del producto](#1-visión-del-producto)
2. [Contexto y problema que resuelve](#2-contexto-y-problema-que-resuelve)
3. [Usuarios y audiencias](#3-usuarios-y-audiencias)
4. [Objetivos del producto](#4-objetivos-del-producto)
5. [Funcionalidades actuales](#5-funcionalidades-actuales)
6. [Roadmap de funcionalidades futuras](#6-roadmap-de-funcionalidades-futuras)
7. [Casos de uso principales](#7-casos-de-uso-principales)
8. [Requisitos no funcionales](#8-requisitos-no-funcionales)
9. [Restricciones y decisiones de diseño](#9-restricciones-y-decisiones-de-diseño)
10. [Glosario de negocio](#10-glosario-de-negocio)

---

## 1. Visión del producto

| Atributo | Valor |
|---|---|
| **Nombre** | ConectaTech.co |
| **Tipo** | Plataforma educativa B2B con portal de administración |
| **Público principal** | Colegios y organizaciones educativas de Colombia |
| **Público secundario** | Estudiantes de las organizaciones suscritas |
| **Idioma de la plataforma** | Español colombiano |
| **URLs de producción** | `https://conectatech.co` (LMS) · `https://admin.conectatech.co` (panel admin) · `https://assets.conectatech.co` (recursos) · `https://api.conectatech.co` (API pública de recursos) |
| **Propietario técnico** | IdeasMaestras — [Oliver Castelblanco](https://ocastelblanco.com) |

**Visión en una oración:** ConectaTech.co es una plataforma educativa que permite a colegios colombianos ofrecer a sus estudiantes cursos digitales de alta calidad mediante un modelo de acceso por pines, gestionado por un representante institucional.

---

## 2. Contexto y problema que resuelve

### El problema

Los colegios colombianos no cuentan con infraestructura tecnológica propia para ofrecer educación digital estructurada. Comprar y configurar un LMS es costoso y requiere personal técnico. Contratar plataformas internacionales resulta en contenido descontextualizado y precios en dólares.

### La solución

ConectaTech.co centraliza la gestión en una plataforma Moodle administrada por IdeasMaestras. Los colegios compran **paquetes de acceso** (cursos + cupos) y los distribuyen a sus estudiantes mediante **pines de activación**. Un **gestor** (representante del colegio) administra la asignación de pines sin necesidad de conocimientos técnicos.

### Flujo general del negocio

```
IdeasMaestras crea contenido (Markdown → Moodle)
        ↓
Se crean paquetes de cursos por organización
        ↓
El gestor del colegio recibe un pin de gestor
        ↓
El gestor activa pines de estudiante y los entrega
        ↓
El estudiante activa su pin → se crea su cuenta → queda matriculado
        ↓
El estudiante accede a los cursos en conectatech.co
```

---

## 3. Usuarios y audiencias

| Perfil | Quién es | Qué necesita | Accede desde |
|---|---|---|---|
| **Administrador** | Equipo IdeasMaestras (1 persona) | Crear y publicar contenido, gestionar organizaciones, monitorear el sistema | Panel admin (`admin.conectatech.co`) |
| **Gestor** | Representante del colegio o institución educativa | Ver y asignar pines a estudiantes, consultar grupos, ver reportes de uso | Portal gestor en el panel admin |
| **Estudiante** | Alumno del colegio suscrito | Activar su pin, acceder a sus cursos, completar actividades | LMS (`conectatech.co`) |
| **IA / agente** | Claude Code u otro agente IA | Leer contexto completo del proyecto para implementar o documentar sin preguntas adicionales | Este repositorio |

---

## 4. Objetivos del producto

| Objetivo | Indicador de éxito | Estado |
|---|---|---|
| Publicar contenido educativo estructurado en Moodle desde documentos Markdown | El pipeline Markdown → Moodle procesa un documento y crea secciones, subsecciones, cuestionarios y actividades sin intervención manual | ✅ Implementado |
| Permitir que colegios compren y gestionen accesos sin intervención técnica del administrador | El gestor puede asignar pines desde su portal sin asistencia de IdeasMaestras | ✅ Implementado |
| Facilitar la activación de cuentas para estudiantes nuevos | El estudiante activa su pin desde una URL pública y queda matriculado automáticamente | ✅ Implementado |
| Construir y mantener árboles curriculares que definen la estructura de cursos | El administrador puede crear, editar y desplegar árboles curriculares desde el panel | ✅ Implementado (parcialmente — sección 0 de cursos finales pendiente) |
| Centralizar la gestión de archivos PDF y recursos digitales | El administrador sube PDFs y los vincula a cursos; el visor se muestra embebido en Moodle | ✅ Implementado |
| Escalar el acceso a recursos sin incrementar costos de servidor | Los PDFs y videos se sirven desde un CDN, no desde el servidor Moodle | ✅ Implementado |

---

## 5. Funcionalidades actuales

### 5.1 Pipeline Markdown → Moodle (ingesta de contenido)

El administrador escribe el contenido de un curso en formato Markdown con convenciones semánticas propias. El sistema procesa el documento y crea automáticamente en Moodle:

- **Secciones del curso** (H1 del Markdown)
- **Subsecciones delegadas** (H2 del Markdown, usando `mod_subsection`)
- **Bloques de contenido** (texto, listas, código, presaberes, reflexiones, etc.)
- **Cuestionarios** con preguntas de opción múltiple (formato GIFT) y ensayo
- **Visor de presaberes** con HTML interactivo

Flujo:

```
archivo .md
    ↓ MarkdownParser        → árbol de secciones / subsecciones / bloques
    ↓ HtmlConverter         → bloques semánticos a HTML
    ↓ PresaberesHtmlBuilder → HTML interactivo para actividad de diagnóstico
    ↓ GiftConverter         → preguntas de opción múltiple a formato GIFT
    ↓ MoodleContentBuilder  → secciones, labels, quiz y actividades ensayo en Moodle
```

Se invoca desde la API (`POST /admin-api/markdown`) o desde línea de comandos (`procesar-markdown.php`).

**Gotcha crítico:** Si el procesamiento de una sección falla dentro de una transacción delegada (p.ej. por un nombre de pregunta >255 caracteres), Moodle bloquea todas las operaciones de base de datos siguientes. El pipeline incluye mecanismos defensivos de limpieza de transacción.

---

### 5.2 Árbol curricular (estructura de cursos)

El administrador define la estructura de un programa educativo completo como un árbol jerárquico:

```
Árbol curricular
  └─ Área de conocimiento
       └─ Unidad / Grado
            └─ Curso repositorio (contenido)
            └─ Curso final (para estudiantes)
```

La herramienta permite:
- Crear, editar y eliminar nodos del árbol
- Definir la relación entre cursos repositorio y cursos finales
- Desplegar el árbol en Moodle (crea categorías y cursos)
- Los árboles se guardan como JSON en el servidor (`backend/data/arboles/`)

**Pendiente:** Los cursos finales necesitan contenido en su sección 0 (portada). Ver §6 (Roadmap).

---

### 5.3 Gestión de matrículas masivas

El administrador puede cargar un archivo CSV con datos de usuarios (nombre, cédula, curso, grupo) y el sistema:
- Crea las cuentas de usuario si no existen (username = número de cédula)
- Matricula a los usuarios en los cursos indicados
- Asigna grupos dentro del curso
- Reporta errores fila por fila

---

### 5.4 Activos y visor de PDF

El administrador gestiona una biblioteca de PDFs que pueden vincularse a cualquier sección de Moodle. El visor se muestra embebido como iframe directamente en la plataforma educativa.

- Los PDFs se almacenan en el servicio de almacenamiento en la nube
- El visor PDF es una aplicación web independiente en el CDN (`assets.conectatech.co/herramientas/visor-pdf/`)
- La API pública de recursos (`api.conectatech.co`) gestiona el índice de PDFs disponibles

---

### 5.5 Organizaciones

El administrador gestiona las instituciones educativas suscritas:
- Crear / renombrar / eliminar organizaciones
- Cada organización tiene asociada una categoría de cursos en Moodle
- Al eliminar una organización, se elimina en cascada toda la información relacionada

---

### 5.6 Sistema de pines

Sistema de acceso controlado que permite a las organizaciones distribuir cursos a sus estudiantes:

**Ciclo de vida de un pin:**

```
disponible → asignado (a paquete y org) → activo (estudiante lo recibió)
    ↑                                           ↓
    └─────────────────── usado (completado) ────┘
```

- El **paquete** agrupa pines para una organización + un conjunto de cursos
- El **gestor** ve sus pines activos y puede asignarlos
- El **estudiante** activa su pin desde una URL pública

---

### 5.7 Portal del gestor

Interfaz dentro del panel de administración para el representante del colegio. Acceso mediante un "pin de gestor" (diferente al pin de estudiante). El gestor puede:
- Ver el dashboard con estadísticas de su organización
- Consultar y gestionar grupos de estudiantes
- Ver y administrar los pines disponibles para su organización

Flujo de acceso del gestor:
```
Administrador crea pin de gestor → Gestor recibe URL de activación
    → Gestor crea su cuenta → Gestor inicia sesión con sus credenciales Moodle
    → Gestor accede a /gestor/* en el panel admin
```

---

### 5.8 Activación pública de estudiantes

Página pública (`/activar`) donde un estudiante ingresa su pin y:
1. El sistema verifica el pin y muestra a qué curso corresponde
2. Si el estudiante no tiene cuenta: la crea (cédula como username)
3. Si ya tiene cuenta: inicia sesión
4. El sistema matricula al estudiante en el curso del pin
5. El pin queda marcado como "usado"

---

## 6. Roadmap de funcionalidades futuras

| Funcionalidad | Descripción | Prioridad |
|---|---|---|
| **Revisión del sistema de pines** *(en progreso — cliente activo)* | Vigencia por duración desde activación (3/6/12 meses), rol Moodle exclusivo `ct_gestor` (solo lectura: contenidos, participantes, calificaciones), ajustes de UX en flujos de asignación y activación | **Prioridad 1** |
| **Previsualizador de contenido Markdown** | Componente de árbol visual (`p-tree` de PrimeNG) que muestra la estructura del Markdown antes de cargarlo en Moodle: secciones (H1), subsecciones (H2) y cuestionarios, con íconos por tipo de recurso Moodle (`Área de texto y medios`, `Cuestionario`, etc.). Permite reordenar los nodos con drag & drop antes de ejecutar el pipeline. | Alta |
| **Sección 0 de cursos finales** | Al desplegar un árbol curricular, cada curso final debe tener contenido de portada/bienvenida. El panel admin necesita una UI para definir ese contenido por curso dentro del editor de árboles. | Alta |
| **Reportes de progreso** | Dashboard con métricas de avance de estudiantes por organización: completitud de cursos, calificaciones promedio, actividad reciente | Alta |
| **Tipos de pregunta adicionales** | El pipeline Markdown soporta actualmente solo opción múltiple y ensayo. Pendiente: verdadero/falso, emparejamiento, respuesta corta, numérica | Media |
| **Renovación y reutilización de pines** | Flujo para que el administrador recupere pines "usados" de cursos completados y los reasigne a nuevos estudiantes | Media |
| **Notificaciones por correo** | Enviar correo al gestor cuando se crea un paquete para su organización; al estudiante cuando se activa su pin | Media |
| **Importación de PDFs masiva** | Subir múltiples PDFs desde el panel y vincularlos automáticamente a secciones de Moodle usando metadatos del archivo | Media |
| **Autenticación del gestor con token JWT** | Reemplazar el flujo actual basado en credenciales Moodle + cookies por tokens JWT independientes para mayor seguridad y escalabilidad | Baja |
| **Soporte multitenancy real** | Aislar los datos de diferentes organizaciones a nivel de base de datos (hoy están en la misma instancia Moodle) | Baja |
| **App móvil** | App iOS/Android para estudiantes (hoy el LMS Moodle tiene app móvil nativa, pero sin el flujo de activación personalizado) | Baja |

---

## 7. Casos de uso principales

| Actor | Acción | Resultado esperado |
|---|---|---|
| Administrador | Sube un archivo Markdown de un curso | El sistema procesa el archivo y crea todas las secciones, subsecciones, cuestionarios y actividades en Moodle automáticamente |
| Administrador | Crea un árbol curricular y lo despliega | Moodle crea las categorías y cursos vacíos con la estructura correcta |
| Administrador | Carga un CSV de matrículas | El sistema crea las cuentas faltantes, matricula a los usuarios y reporta errores |
| Administrador | Crea un paquete para una organización | El sistema genera los pines y los asocia a los cursos del paquete |
| Gestor | Entra al portal con sus credenciales | Ve el dashboard de su organización con estadísticas de uso de pines |
| Gestor | Asigna un pin a un estudiante | El pin pasa de "disponible" a "activo" y el gestor ve el PIN para entregárselo al estudiante |
| Estudiante | Ingresa su pin en `/activar` | El sistema crea su cuenta (si es nuevo) o lo identifica (si ya existe), lo matricula en el curso y el pin queda "usado" |
| Estudiante | Accede a un curso en conectatech.co | Ve las secciones del curso con el contenido publicado, los cuestionarios y los visores de PDF |
| Administrador | Busca un PDF en la biblioteca de activos | Ve la lista de PDFs disponibles, puede previsualizarlos y vincularlos a secciones de Moodle |

---

## 8. Requisitos no funcionales

| Categoría | Requisito |
|---|---|
| **Disponibilidad** | El LMS debe estar disponible 99% del tiempo en horario escolar (6 AM–10 PM hora Colombia) |
| **Rendimiento** | Las páginas del panel admin deben cargar en menos de 3 segundos. La API debe responder en menos de 2 segundos para operaciones simples |
| **Seguridad** | Acceso al panel admin restringido a sesión Moodle con rol administrador. El portal del gestor requiere autenticación por credenciales Moodle. Los endpoints de escritura verifican identidad en el servidor |
| **Escalabilidad** | Los PDFs y recursos estáticos se sirven desde CDN, no desde el servidor principal |
| **Compatibilidad** | El panel admin debe funcionar en Chrome y Firefox modernos (desktop). El LMS Moodle es responsivo |
| **Mantenibilidad** | El contenido educativo se escribe en Markdown (sin intervención técnica para actualizar); los cambios se propagan automáticamente al LMS |
| **Privacidad** | Los datos de estudiantes (cédula, nombre) se almacenan únicamente en la base de datos del servidor propio; no se comparten con terceros |
| **Backup** | La base de datos Moodle y los archivos del servidor se respaldan periódicamente |

---

## 9. Restricciones y decisiones de diseño

| Restricción | Razón | Consecuencia |
|---|---|---|
| **El LMS es Moodle** | Ecosistema educativo establecido, amplia adopción en Colombia, soporte de actividades nativas (cuestionarios, GIFT, foros) | No se puede reemplazar el LMS por otro sistema sin reescribir el pipeline completo |
| **El panel admin vive en un subdominio separado** (`admin.conectatech.co`) | Separar las preocupaciones: el LMS es para estudiantes, el panel es para administración | La API REST debe estar bajo `conectatech.co/admin-api` (no bajo el subdominio admin) para que la cookie de sesión Moodle sea válida |
| **El username del estudiante es su número de cédula** | Identificación única colombiana, evita duplicados, facilita soporte | Rango de cédulas válidas: 1.000.000–1.999.999.999; se valida al crear cuentas |
| **Los árboles curriculares se guardan en el servidor como JSON** | Simplicidad de implementación inicial; Moodle no tiene una estructura nativa equivalente | No están en el repositorio git (son datos de producción). Si el servidor se pierde sin backup, los árboles se pierden |
| **El contenido Markdown es la fuente de verdad** | Permite versionar el contenido educativo en git, editarlo en cualquier editor de texto | Al reprocesar un archivo Markdown, el curso en Moodle se **reemplaza completamente** (resetCourse()) |
| **No hay entorno de staging** | Proyecto unipersonal, complejidad de mantener dos entornos | Todos los cambios van directo a producción; las pruebas se hacen localmente antes del deploy |
| **La instancia Moodle es única** | Costo y complejidad de mantener múltiples instancias | Todos los colegios comparten la misma instancia; el aislamiento se logra con categorías y roles de Moodle |

---

## 10. Glosario de negocio

| Término | Definición |
|---|---|
| **Pin** | Código único de acceso que se le entrega a un estudiante para activar su matrícula en un curso. Tiene un ciclo de vida: disponible → activo → usado |
| **Pin de gestor** | Código especial (diferente al pin de estudiante) que permite al representante de un colegio crear su cuenta y acceder al portal del gestor |
| **Paquete** | Conjunto de pines asociados a un grupo de cursos para una organización específica. El administrador lo crea y el gestor lo administra |
| **Organización** | Institución educativa (colegio, empresa de formación) suscrita a ConectaTech.co |
| **Gestor** | Representante de una organización con acceso al portal del gestor. Administra los pines de su organización |
| **Árbol curricular** | Estructura jerárquica que define cómo se organizan las áreas de conocimiento, unidades y cursos de un programa educativo |
| **Curso repositorio** | Curso en Moodle que contiene el contenido educativo (secciones, actividades). No tiene estudiantes matriculados directamente |
| **Curso final** | Curso en Moodle al que se matriculan los estudiantes. Recibe su contenido del curso repositorio mediante una sincronización (clonación) |
| **Pipeline de contenido** | Proceso automatizado que convierte un documento Markdown en secciones, actividades y cuestionarios dentro de Moodle |
| **Presaberes** | Actividad de diagnóstico inicial de un módulo: preguntas para evaluar el conocimiento previo del estudiante antes del contenido |
| **Bloque semántico** | Elemento del Markdown con significado pedagógico: presaberes, reflexión, caso de estudio, actividad, etc. Se convierte en HTML interactivo |
| **Visor de PDF** | Aplicación web embebida en Moodle que muestra un documento PDF en un iframe, servido desde el CDN |
| **CDN** | Sistema de distribución de contenido estático (PDFs, imágenes, el visor). Permite servir archivos a los estudiantes sin cargar el servidor principal |
