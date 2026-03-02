# Arquitectura Técnica Definitiva — Automatización ConectaTech Moodle

**Estado:** Validado ✅ — Listo para implementación con Claude Code  
**Plataforma:** Moodle 5.1.3 en AWS EC2 (`conectatech.co`)  
**Fecha:** Febrero 2026

---

## Decisiones de Diseño Finales

### Descarga de Google Docs → Markdown

**Decisión: Descarga manual + procesamiento automático.**

Dado que son 17 documentos y el proceso no es frecuente (solo cuando hay contenido nuevo), la descarga manual es la opción correcta. El flujo es:

1. El editor abre el Google Doc y selecciona `Archivo > Descargar > Markdown (.md)`.
2. Sube el archivo `.md` al servidor (via `scp` o la interfaz web que se construirá en el Paso 3).
3. El sistema procesa el archivo automáticamente desde ahí.

Esto elimina toda la complejidad de OAuth2 y la dependencia de una cuenta de Google, sin coste operativo apreciable.

---

### Autenticación en el servidor

El script PHP CLI se ejecuta en el servidor EC2 como `apache` (el usuario de PHP-FPM de Amazon Linux):

```bash
sudo -u apache php /var/www/scripts/automation/procesar-markdown.php \
    --file /tmp/ciencias-naturales-6-7.md \
    --course repo-cn-6-7
```

Claude Code tiene acceso completo a EC2 via AWS CLI para crear los archivos directamente en la instancia.

---

## Estructura de Archivos del Sistema

```
/var/www/scripts/automation/          ← Fuera del DocumentRoot (no accesible por web)
├── config/
│   ├── courses.csv                   ← Mapa: filename → fullname → shortname
│   └── semantic-blocks.json          ← Mapeo de bloques H3 → CSS (configurable)
├── lib/
│   ├── MoodleBootstrap.php           ← Inicializa el entorno Moodle para CLI
│   ├── MarkdownParser.php            ← Parser de .md → estructura de datos
│   ├── HtmlConverter.php             ← Convierte bloques semánticos a HTML
│   ├── GiftConverter.php             ← Convierte preguntas Markdown → GIFT
│   └── MoodleContentBuilder.php      ← Crea/actualiza cursos, secciones, recursos
├── procesar-markdown.php             ← Script principal (punto de entrada)
├── report-ultimo.json                ← Reporte del último proceso
└── logs/
    └── automation.log
```

---

## Archivo de Configuración: `semantic-blocks.json`

Este archivo es la fuente de verdad del mapeo de bloques. Se puede editar sin tocar el código.

```json
{
  "version": "1.0",
  "blocks": [
    {
      "h3_title": "Texto bíblico guía",
      "css_primary": "cita-biblica",
      "css_additional": ["resaltado"],
      "show_h3": false
    },
    {
      "h3_title": "Introducción motivadora",
      "css_primary": "introduccion-motivadora",
      "css_additional": [],
      "show_h3": false
    },
    {
      "h3_title": "¿Qué tiene que ver esto contigo?",
      "css_primary": "que-tiene-que-ver-esto-contigo",
      "css_additional": [],
      "show_h3": true
    },
    {
      "h3_title": "Objetivos de aprendizaje con sentido cristiano",
      "css_primary": "objetivos-de-aprendizaje-con-sentido-cristiano",
      "css_additional": [],
      "show_h3": true
    },
    {
      "h3_title": "Cierre espiritual",
      "css_primary": "cierre-espiritual",
      "css_additional": ["resaltado"],
      "show_h3": true
    },
    {
      "h3_title": "Reflexiona",
      "css_primary": "reflexiona",
      "css_additional": [],
      "show_h3": true
    },
    {
      "h3_title": "Ponte en acción",
      "css_primary": "ponte-en-accion",
      "css_additional": [],
      "show_h3": true
    }
  ],
  "fallback": {
    "comment": "Si el H3 no está en el mapa, se renderiza como un bloque genérico con el H3 visible",
    "css_primary": "bloque-generico",
    "css_additional": [],
    "show_h3": true
  }
}
```

**Nota sobre `Ponte en acción`:** el título contiene una tilde en la "o" (`acción`). El código debe normalizar las claves del mapeo quitando tildes y convirtiendo a minúsculas para la comparación (la clase CSS usa `accion` sin tilde, como muestra el JSON).

---

## Archivo de Configuración: `courses.csv`

```csv
shortname,fullname,category_path
repo-cn-4-5,Ciencias Naturales — Grados 4° y 5°,Repositorios/Ciencias Naturales
repo-cn-6-7,Ciencias Naturales — Grados 6° y 7°,Repositorios/Ciencias Naturales
repo-cn-8-9,Ciencias Naturales — Grados 8° y 9°,Repositorios/Ciencias Naturales
repo-fi-10-11,Física — Grados 10° y 11°,Repositorios/Ciencias Naturales
repo-qu-10-11,Química — Grados 10° y 11°,Repositorios/Ciencias Naturales
repo-ma-4-5,Matemáticas — Grados 4° y 5°,Repositorios/Matemáticas
repo-ma-6-7,Matemáticas — Grados 6° y 7°,Repositorios/Matemáticas
repo-ma-8-9,Matemáticas — Grados 8° y 9°,Repositorios/Matemáticas
repo-ma-10-11,Matemáticas — Grados 10° y 11°,Repositorios/Matemáticas
repo-le-4-5,Lenguaje — Grados 4° y 5°,Repositorios/Lenguaje
repo-le-6-7,Lenguaje — Grados 6° y 7°,Repositorios/Lenguaje
repo-le-8-9,Lenguaje — Grados 8° y 9°,Repositorios/Lenguaje
repo-le-10-11,Lenguaje — Grados 10° y 11°,Repositorios/Lenguaje
repo-cs-4-5,Ciencias Sociales — Grados 4° y 5°,Repositorios/Ciencias Sociales
repo-cs-6-7,Ciencias Sociales — Grados 6° y 7°,Repositorios/Ciencias Sociales
repo-cs-8-9,Ciencias Sociales — Grados 8° y 9°,Repositorios/Ciencias Sociales
repo-cs-10-11,Ciencias Sociales — Grados 10° y 11°,Repositorios/Ciencias Sociales
```

---

## Formato Markdown para Cuestionarios (Propuesta)

El bloque de evaluación se marca con un H2 especial que lo identifica como la **subsección final**. El marcador `[evaluacion]` (entre corchetes) le indica al parser que esta subsección contiene preguntas.

### Ejemplo de Google Doc → Markdown

```markdown
# La célula: unidad básica de la vida

## Recurso raíz sección

### Texto bíblico guía
_"Porque tú formaste mis entrañas; Tú me hiciste en el vientre de mi madre."_
**Salmos 139:13**

### Introducción motivadora
Contenido introductorio de la sección...

## La membrana celular

### ¿Qué tiene que ver esto contigo?
Contenido de la subsección...

## Organelos y funciones

### Objetivos de aprendizaje con sentido cristiano
Contenido...

## Evaluación [evaluacion]

### Pregunta 1
¿Cuál es la función principal de la mitocondria?

### Pregunta 2
Describe con tus propias palabras qué es la célula y por qué crees que Dios la diseñó con tanta precisión.
```

### Convención para marcar la subsección de evaluación

El H2 de evaluación se identifica por contener `[evaluacion]` en el título (sin tilde, case-insensitive). El texto antes del marcador es el nombre visible de la subsección en Moodle. Ejemplos válidos:
- `## Evaluación [evaluacion]`
- `## Evaluemos lo aprendido [evaluacion]`
- `## Actividad de cierre [evaluacion]`

### Tipos de pregunta en el Markdown (propuesta extensible)

El tipo de pregunta se determina por metadatos opcionales entre llaves `{}` después del H3. Si no se especifica, el tipo por defecto es **Ensayo - campo de texto**.

#### Ensayo (actual — por defecto)

```markdown
### Describe la célula
{tipo: ensayo, variante: texto}
Describe con tus propias palabras qué es la célula...
```

Si no hay metadatos, se asume `{tipo: ensayo, variante: texto}`.

#### Opción múltiple (futuro)

```markdown
### ¿Cuál es la función de la mitocondria?
{tipo: opcion-multiple}
- Producir energía (ATP) [correcto]
- Sintetizar proteínas
- Almacenar el ADN
- Regular el ciclo celular
```

#### Verdadero / Falso (futuro)

```markdown
### La membrana celular es permeable a todas las sustancias
{tipo: verdadero-falso, respuesta: falso}
```

#### Emparejamiento (futuro)

```markdown
### Relaciona cada organelo con su función
{tipo: emparejamiento}
- Mitocondria → Produce energía
- Ribosoma → Sintetiza proteínas
- Núcleo → Contiene el ADN
- Vacuola → Almacena sustancias
```

#### Respuesta corta (futuro)

```markdown
### ¿Cómo se llama el proceso por el que la célula se divide?
{tipo: respuesta-corta}
Respuestas aceptadas: mitosis, división celular
```

#### Numérica (futuro)

```markdown
### ¿Cuántos cromosomas tiene una célula humana?
{tipo: numerica, respuesta: 46, tolerancia: 0}
```

#### Ensayo con adjunto (futuro)

```markdown
### Dibuja y rotula las partes de una célula
{tipo: ensayo, variante: adjunto}
Entrega un dibujo a mano con las partes identificadas.
```

---

## Lógica de Procesamiento del Parser

### Algoritmo principal

```
1. Leer el archivo .md
2. Normalizar caracteres UTF-8 (tildes, ñ, comillas tipográficas)
3. Tokenizar por líneas
4. Construir árbol de estructura:
   - Nivel 1 (H1): Sección del curso
   - Nivel 2 (H2): 
       - Si es el primero de la sección → Recurso raíz de la sección
       - Si contiene [evaluacion] → Subsección de evaluación
       - Cualquier otro → Subsección regular
   - Nivel 3 (H3): Bloque semántico dentro del recurso actual

5. Para cada sección (H1):
   a. Buscar si ya existe en el curso (por nombre de sección normalizado)
   b. Si existe → modo ACTUALIZAR (eliminar contenido existente y recrear)
   c. Si no existe → modo CREAR (agregar al final del curso)

6. Para cada subsección (H2 que no sea recurso raíz):
   a. Crear mod_subsection en la sección padre
   b. Obtener el ID de la sección delegada creada

7. Para cada bloque semántico (H3):
   a. Buscar en semantic-blocks.json (normalizado)
   b. Construir el HTML con las clases correspondientes
   c. Si show_h3 = true → incluir <h3> dentro del div
   d. Acumular HTML hasta el siguiente H3 o H2

8. Crear el mod_label con el HTML acumulado en la sección correcta

9. Para subsecciones [evaluacion]:
   a. Crear el mod_quiz en la sección delegada
   b. Convertir cada H3 a una pregunta en el banco de preguntas
   c. Agregar las preguntas al quiz
   d. Configurar: sin calificación automática (tipo Ensayo)

10. Generar reporte JSON con resultados
```

### Normalización de nombres de sección para comparación

Al comparar si una sección H1 ya existe, se normaliza a lowercase sin tildes ni puntuación especial:
- `"La célula: unidad básica de la vida"` → `"la celula unidad basica de la vida"`

Esto previene falsos negativos por diferencias de capitalización o puntuación menor entre versiones del documento.

---

## Configuración de Moodle Necesaria (Previa a los Scripts)

Estos pasos se ejecutan **una sola vez** antes de correr cualquier script:

### 1. Aumentar `maxsections`

```bash
# Via SSH en el servidor
sudo -u apache php /var/www/html/moodle/admin/cli/cfg.php \
    --component=moodlecourse \
    --name=maxsections \
    --set=250
```

### 2. Crear la categoría "Repositorios" y subcategorías

El script la crea automáticamente si no existe, usando `coursecat::create()`. Se verificará al inicio.

### 3. Habilitar mod_subsection (si no está activo)

```bash
sudo -u apache php /var/www/html/moodle/admin/cli/uninstall_plugins.php \
    --plugins=mod_subsection --run  # Solo si necesita reinstalarse
# Verificar que esté habilitado en Administración > Plugins > Módulos de actividad
```

### 4. Generar token de administrador para reportes futuros

Aunque los scripts CLI no lo necesitan (acceden directamente a las APIs internas), se genera un token REST para la futura interfaz web:

- Administración del sitio → Plugins → Servicios web → Gestionar tokens
- Usuario: admin → Servicio: Moodle mobile web service (o servicio custom)

---

## Flujo de Trabajo Operacional

### Primer uso (curso nuevo)

```bash
# 1. Descargar el Google Doc manualmente como .md
# 2. Subir al servidor
scp -i ~/.ssh/ClaveIM.pem ciencias-naturales-6-7.md \
    ec2-user@conectatech.co:/tmp/

# 3. Ejecutar el script
ssh -i ~/.ssh/ClaveIM.pem ec2-user@conectatech.co
sudo -u apache php /var/www/scripts/automation/procesar-markdown.php \
    --file /tmp/ciencias-naturales-6-7.md \
    --course repo-cn-6-7

# 4. Ver el reporte
cat /var/www/scripts/automation/report-ultimo.json
```

### Re-ejecución (actualización de contenido)

El mismo comando. El script detecta automáticamente qué secciones ya existen y las actualiza, y agrega las nuevas al final.

### Re-ejecución completa (sincronización total)

Para sincronizar todos los cursos de una vez (cuando se hayan revisado múltiples documentos), se puede hacer un loop:

```bash
for file in /tmp/markdowns/*.md; do
    shortname=$(basename "$file" .md)
    sudo -u apache php /var/www/scripts/automation/procesar-markdown.php \
        --file "$file" \
        --course "$shortname"
done
```

---

## Alcance del Paso 2 (Claude Code)

Los scripts que Claude Code debe generar son los siguientes, en orden de implementación:

### Fase A: Fundamentos
1. `lib/MoodleBootstrap.php` — Inicializa el entorno Moodle correctamente para CLI en 5.1.3 con la estructura `/public`
2. `lib/MarkdownParser.php` — Tokeniza el .md y construye el árbol de secciones/bloques
3. `lib/HtmlConverter.php` — Convierte bloques semánticos a HTML usando `semantic-blocks.json`

### Fase B: Integración con Moodle
4. `lib/MoodleContentBuilder.php` — Crea/actualiza cursos, secciones, subsecciones y labels usando las APIs internas de Moodle
5. `lib/GiftConverter.php` — Convierte preguntas Markdown a formato GIFT (inicialmente solo tipo Ensayo)

### Fase C: Script principal y configuración
6. `procesar-markdown.php` — Script principal que orquesta todo
7. `config/semantic-blocks.json` — Configuración inicial
8. `config/courses.csv` — Mapa de cursos
9. `logs/` — Directorio de logs (con `.gitkeep`)

### Pruebas
10. Un archivo de prueba `test/sample-section.md` con 2 secciones completas (incluyendo subsecciones y evaluación) para validar el pipeline completo antes de procesar los 17 documentos reales.

---

## Consideraciones Técnicas para Claude Code

### Bootstrap de Moodle en 5.1.3

La estructura `/public` requiere que el `config.php` se referencie desde la raíz del proyecto, no desde `/public`:

```php
define('CLI_SCRIPT', true);
// config.php está en /var/www/html/moodle/config.php (un nivel arriba de /public)
require(dirname(__DIR__, 2) . '/config.php');
```

La ruta exacta depende de dónde se alojen los scripts. Si están en `/var/www/scripts/automation/`, la ruta a `config.php` es `/var/www/html/moodle/config.php`.

### Usuario de ejecución

Todos los scripts deben ejecutarse como `apache`:

```bash
sudo -u apache php /var/www/scripts/automation/procesar-markdown.php [args]
```

Si se ejecutan como `ec2-user` o `root`, los archivos creados en `moodledata` tendrán permisos incorrectos.

### Gestión de memoria para procesos largos

Al procesar secciones en loop, liberar los controladores de módulo después de cada iteración para evitar agotamiento de memoria:

```php
// Después de crear cada sección completa
gc_collect_cycles();
```

### Transacciones de base de datos

Envolver la creación de cada sección (no del curso completo) en una transacción delegada para que un fallo en la sección N no corrompa las secciones 1..N-1 ya creadas:

```php
$transaction = $DB->start_delegated_transaction();
try {
    // crear sección, subsecciones, recursos...
    $transaction->allow_commit();
} catch (Exception $e) {
    $transaction->rollback($e);
    // registrar en log y continuar con la siguiente sección
}
```

### Actualización de secciones existentes

Al sobreescribir una sección H1 que ya existe, el proceso es:
1. Obtener todos los `course_modules` de la sección (y sus subsecciones delegadas)
2. Eliminarlos con `course_delete_module()` para cada uno
3. Eliminar las subsecciones (también son course_modules) con `course_delete_module()`
4. Actualizar el nombre/summary de la sección raíz
5. Recrear el contenido desde cero

Esto es más limpio y menos propenso a errores que intentar hacer diff y actualizar campos individuales.
