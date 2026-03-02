# **Guía Técnica de Automatización para Moodle 5.1.3: Arquitectura de Scripts de Fase 1 y Orquestación en AWS**

La evolución de Moodle hacia su versión 5.1.3 marca un punto de inflexión crítico en la arquitectura de los sistemas de gestión de aprendizaje (LMS). Este cambio no es simplemente una actualización incremental, sino una reestructuración fundamental orientada a modernizar el manejo de archivos, mejorar la seguridad del núcleo y optimizar el rendimiento mediante el uso de PHP 8.3.1 Para un desarrollador o una inteligencia artificial encargada de generar scripts de automatización, comprender la nueva estructura de directorios /public es el primer paso indispensable. Esta arquitectura separa el código ejecutable y las bibliotecas del punto de entrada web, lo que reduce drásticamente la superficie de ataque al impedir el acceso directo a archivos sensibles como config.php.3

La implementación de la Fase 1, que comprende la carga masiva de usuarios y cursos junto con la clonación de contenidos, debe ejecutarse bajo premisas de alta eficiencia y escalabilidad. La decisión de utilizar las APIs nativas de Moodle en lugar de herramientas de terceros como Moosh responde a una necesidad de reusabilidad a largo plazo y una menor dependencia de binarios externos que podrían no alinearse perfectamente con los hooks de la versión 5.1.3.2 A continuación, se detalla el marco técnico, los requisitos de infraestructura y la lógica de programación necesarios para que una IA de código genere scripts robustos y alineados con los estándares de ingeniería de Moodle.

## **Arquitectura de Directorios y Configuración del Entorno PHP 8.3**

Moodle 5.1 introduce un cambio de paradigma en la disposición de sus archivos mediante la creación de la carpeta /public. Anteriormente, la raíz del sitio web coincidía con la raíz del código fuente, lo que exponía archivos de configuración y librerías si el servidor web no estaba configurado con reglas de exclusión estrictas.3 En la versión 5.1.3, el DocumentRoot de Apache o la directiva root de Nginx deben apuntar obligatoriamente a esta nueva subcarpeta /public.1

Este cambio afecta directamente a la creación de scripts CLI (Command Line Interface). Aunque el código del script resida fuera de la carpeta pública por seguridad, la inclusión de la configuración base debe realizarse de forma que el sistema reconozca las nuevas variables globales. Moodle 5.1 introduce $CFG-\>root como una variable de solo lectura que identifica la raíz de la instalación, diferenciándola de $CFG-\>dirroot y $CFG-\>wwwroot.2

### **Requisitos Técnicos del Servidor y PHP**

Para garantizar la ejecución fluida de procesos masivos, el entorno debe cumplir con especificaciones estrictas de PHP 8.3. La versión 5.1.3 requiere la extensión sodium para operaciones criptográficas y una configuración elevada de max\_input\_vars para procesar formularios y archivos CSV extensos.1

| Parámetro | Valor Requerido / Recomendado | Justificación Técnica |
| :---- | :---- | :---- |
| Versión PHP | 8.3.x (64-bit únicamente) | Soporte para tipos de retorno estáticos y optimización de JIT.1 |
| max\_input\_vars | \>= 5000 | Necesario para procesar grandes volúmenes de datos en la creación de cursos.1 |
| Extensión sodium | Habilitada | Requisito base para el nuevo sistema de seguridad de Moodle 5.1.1 |
| Prefijo DB | Máximo 10 caracteres | Limitación introducida para compatibilidad con identificadores de índices largos.1 |
| DocumentRoot | /moodle/public/ | Aislamiento de archivos core del acceso web directo.3 |

La infraestructura debe estar preparada para manejar procesos de larga duración. Al trabajar en entornos de AWS, es común encontrar limitaciones de tiempo en los procesos PHP. Por ello, los scripts de la Fase 1 deben diseñarse para ser ejecutados vía CLI, lo que evita las restricciones de tiempo de ejecución de los servidores web y permite el acceso directo a los recursos del sistema sin la sobrecarga de las sesiones HTTP.7

## **Fase 1-A: Carga Masiva de Usuarios y Cursos Shell desde CSV**

El primer pilar de la automatización consiste en la ingesta de datos desde archivos CSV para poblar la plataforma con las entidades base: usuarios y contenedores de cursos vacíos (shells). Esta operación no debe realizarse mediante inserciones SQL directas, ya que Moodle requiere la creación de contextos, registros de auditoría y disparadores de eventos que solo se activan a través de sus APIs internas.9

### **Ingeniería de Datos y Validación de CSV**

La inteligencia artificial debe programar una lógica de validación que asegure que el archivo CSV cumple con los esquemas de Moodle. Para los usuarios, son obligatorios campos como username, firstname, lastname y email. Para los cursos, se requieren fullname, shortname y category. Un aspecto avanzado en Moodle 5.1.3 es la gestión de categorías: el script debe ser capaz de resolver el ID de la categoría basándose en una ruta jerárquica proporcionada en el CSV, utilizando la API de categorías para crear niveles inexistentes si es necesario.12

### **Implementación Programática de la Carga**

Para la creación de usuarios, la función de referencia es user\_create\_user(), contenida en /user/lib.php. Tras la creación, es imperativo asignar al usuario a los contextos adecuados. En el caso de los cursos, se debe utilizar create\_course(), la cual se encarga de instanciar el curso en la tabla mdl\_course, crear las secciones iniciales y generar el contexto único en mdl\_context.10 El fallo en la creación de contextos es el error más común en scripts automatizados y resulta en la imposibilidad de asignar roles posteriormente.10

| Entidad | API Principal | Archivo de Origen | Acción del Sistema |
| :---- | :---- | :---- | :---- |
| Usuario | user\_create\_user($data) | /user/lib.php | Crea registro y dispara eventos de bienvenida.9 |
| Curso | create\_course($data) | /course/lib.php | Genera curso, secciones y contextos de seguridad.10 |
| Categoría | course\_categories::create($data) | /lib/coursecatlib.php | Organiza la estructura jerárquica del sitio.12 |
| Matrícula | enrol\_try\_instances\_enrol() | /enrol/locallib.php | Vincula usuarios a cursos bajo métodos específicos.12 |

La lógica debe incluir una gestión de transacciones mediante $DB-\>start\_delegated\_transaction(). Esto asegura que, si la creación de un curso falla a mitad del proceso (por ejemplo, por un nombre corto duplicado), los cambios realizados hasta ese punto se reviertan, manteniendo la base de datos limpia y consistente.14

## **Fase 1-B: Clonación Nativa de Módulos y Secciones**

La clonación de contenidos desde cursos repositorio es una operación significativamente más compleja que la creación de shells. En Moodle 5.1.3, este proceso debe utilizar exclusivamente la Backup/Restore API nativa. Esta API garantiza que no solo se copien los registros de la base de datos, sino también todos los archivos asociados en el moodledata, las configuraciones de los módulos y las dependencias de los plugins, manteniendo la integridad del material educativo.16

### **Controladores de Respaldo y Restauración**

La clonación se divide técnicamente en dos procesos secuenciales: la creación de un respaldo temporal en memoria y disco, y su posterior restauración en el curso de destino. Para clonar una sección o actividad, el script debe instanciar un backup\_controller. El parámetro crucial aquí es el tipo de respaldo; para secciones se utiliza backup::TYPE\_1SECTION y para actividades individuales backup::TYPE\_1ACTIVITY.19

Un factor de optimización vital en la versión 5.1 es el modo de operación. Se debe instruir a la IA para que utilice backup::MODE\_SAMESITE. Este modo está diseñado específicamente para operaciones internas dentro del mismo servidor Moodle, lo que permite omitir comprobaciones pesadas de seguridad y compatibilidad que solo son necesarias cuando se mueven datos entre diferentes sitios.19

### **Flujo Lógico de Clonación**

1. **Instanciación del Backup:** El controlador se inicializa con el ID del recurso de origen. Se debe desactivar la interactividad (backup::INTERACTIVE\_NO) para permitir la ejecución desatendida.19  
2. **Ejecución del Plan:** El método execute\_plan() genera el paquete de respaldo en el directorio temporal configurado en $CFG-\>backuptempdir.22  
3. **Identificación del Destino:** El ID de la carpeta temporal resultante (backupid) se pasa al restore\_controller.  
4. **Configuración de la Restauración:** Se debe definir el objetivo como backup::TARGET\_EXISTING\_ADDING para integrar el contenido en el curso shell creado en la Fase 1-A sin sobrescribir configuraciones existentes del curso.16  
5. **Reubicación de Contenidos:** Por defecto, Moodle restaura las actividades en la misma posición (sección) que ocupaban en el origen. Si se requiere mover la actividad a una sección diferente, el script debe llamar a la función course\_add\_cm\_to\_section() de /course/lib.php inmediatamente después de la restauración para asegurar que el puntero de la base de datos sea correcto.16

La clonación nativa evita los problemas de ID de LTI que se abordarán en fases futuras, concentrándose exclusivamente en recursos de tipo página, foro, cuestionario y archivos locales que no dependen de proveedores externos.13

## **El Uso de Adhoc Tasks para Procesos de Larga Duración**

La clonación de cientos de secciones puede saturar los recursos del servidor si se intenta ejecutar de forma síncrona. Moodle 5.1.3 potencia el uso de las adhoc\_task para gestionar estas cargas. En lugar de que el script principal realice la clonación, este debe crear una tarea programada y ponerla en cola.24

El sistema de tareas adhoc ofrece varias ventajas críticas para la Fase 1: permite la ejecución concurrente (si se configura el sistema de cron adecuadamente), proporciona un mecanismo automático de reintento en caso de fallo y permite que la capa local de AWS reciba una confirmación rápida de que el trabajo ha sido aceptado, sin tener que esperar a que se complete físicamente la clonación.24

### **Estructura de una Tarea Adhoc de Clonación**

La clase de la tarea debe extender \\core\\task\\adhoc\_task. El script principal debe pasar los metadatos necesarios (ID de origen, ID de destino, ID del usuario solicitante) a través del método set\_custom\_data(). Es fundamental especificar el userid de la tarea para que el proceso de restauración se ejecute con los permisos del administrador o del creador de cursos correspondiente, evitando bloqueos por falta de capacidad (capability).24

PHP

// Ejemplo de lógica para la IA  
$task \= new \\local\_automation\\task\\clone\_section\_task();  
$task\-\>set\_custom\_data((object)\[  
    'source\_section' \=\> $sectionid,  
    'target\_course' \=\> $courseid,  
    'options' \=\> \['include\_user\_data' \=\> false\]);  
$task\-\>set\_userid($admin\-\>id);  
\\core\\task\\manager::queue\_adhoc\_task($task);

Este enfoque distribuye la carga de trabajo y asegura que la interfaz web del LMS permanezca receptiva para los usuarios finales mientras los procesos de automatización masiva ocurren en segundo plano.25

## **Evaluación Técnica: APIs Nativas frente a Moosh**

El requerimiento original solicita evaluar el uso de Moosh, sugiriendo su omisión en favor de la reusabilidad. Moosh es una herramienta de línea de comandos extremadamente popular que encapsula muchas funciones de Moodle, pero presenta desafíos significativos en entornos de alta automatización y modernización como Moodle 5.1.3.

| Criterio | Moosh | APIs Nativas (Propuesto) | Impacto en Fase 1 |
| :---- | :---- | :---- | :---- |
| **Dependencia** | Requiere binario externo y mantenimiento por terceros. | Integrado directamente en el núcleo de Moodle. | Las APIs nativas aseguran compatibilidad con PHP 8.3.1 |
| **Reusabilidad** | Difícil de integrar en flujos de tareas adhoc asíncronas. | Diseñado para integrarse con el Task Manager de Moodle. | Facilita la escalabilidad en AWS mediante procesos en background.24 |
| **Auditabilidad** | Puede omitir ciertos hooks de eventos de Moodle. | Dispara todos los eventos estándar de creación y log. | Garantiza que los registros de auditoría de Moodle sean completos.9 |
| **Actualización** | Suele tardar semanas en soportar cambios mayores de estructura. | Inmediatamente compatible con la estructura /public. | Crucial para la transición a Moodle 5.1.3 |

La recomendación final es prescindir de Moosh. Al utilizar PHP 8.3 puro interactuando con las librerías internas de Moodle, se obtiene un control granular sobre el manejo de errores y una integración perfecta con la arquitectura de servicios de AWS. Además, permite a la IA de código generar una solución autónoma que no requiere preinstalaciones complejas en la imagen de la instancia EC2, facilitando la portabilidad del sistema entre diferentes entornos de despliegue.

## **Capa de Orquestación Local: AWS CLI y SSH como Frontend Operativo**

La gestión de los scripts no debe realizarse mediante una interfaz web compleja en esta etapa. En su lugar, se propone una capa de orquestación local que utiliza AWS CLI para la transferencia de archivos y SSH para el disparo controlado de procesos remotos. Este diseño permite tratar al servidor Moodle como una unidad de procesamiento de datos "headless".26

### **Flujo de Trabajo del Operador Local**

El proceso comienza en la máquina local del administrador, donde reside el archivo CSV. Mediante el uso de aws s3 cp, el archivo se carga en un bucket de S3 dedicado. Este bucket actúa como la zona de aterrizaje (landing zone) para los datos de entrada.26

Una vez que el archivo está en S3, el script de orquestación local ejecuta un comando remoto a través de SSH. Este comando no dispara el script de Moodle directamente sobre el archivo en S3, sino que activa un script intermedio en el servidor que descarga el archivo al moodledata/temp de la instancia. Esta separación garantiza que el script PHP de Moodle solo trabaje con archivos locales, lo que es significativamente más rápido y evita problemas de latencia o conectividad durante el procesamiento masivo.7

### **Hardening de la Conexión SSH y AWS**

Para que esta capa sea segura, se deben seguir protocolos de endurecimiento de infraestructura en AWS:

1. **IAM Roles:** La instancia de EC2 que aloja Moodle no debe tener llaves de acceso estáticas. Se debe asignar un rol de IAM a la instancia que le permita leer del bucket de S3.28  
2. **Seguridad SSH:** El acceso debe estar restringido mediante llaves SSH y el grupo de seguridad de la instancia debe permitir tráfico en el puerto 22 únicamente desde las IPs autorizadas de los administradores.28  
3. **Usuario de Ejecución:** Los scripts disparados vía SSH deben ejecutarse utilizando sudo \-u www-data php /path/to/script.php. Ejecutar scripts CLI como usuario root puede corromper los permisos de la carpeta de caché y del moodledata, causando fallos críticos en la interfaz web de Moodle.7

### **El Script de Orquestación Local (Bash/Python)**

El frontend básico se materializa en un script local que automatiza estos pasos. Este script debe recibir parámetros como la ruta del CSV y el ID del repositorio de clonación. Su función es encapsular la complejidad de AWS y SSH, proporcionando una interfaz de línea de comandos sencilla para el operador humano.

| Acción | Comando / Herramienta | Propósito |
| :---- | :---- | :---- |
| Subida | aws s3 sync./input s3://moodle-data/incoming | Sincroniza archivos locales con la nube.26 |
| Ejecución | ssh \-i key.pem user@host 'sudo \-u www-data php...' | Activa el procesamiento en el servidor remoto.7 |
| Monitoreo | aws cloudwatch get-log-events... | Recupera los resultados del proceso para el operador local.28 |
| Limpieza | aws s3 rm s3://moodle-data/incoming \--recursive | Elimina datos sensibles tras el procesamiento.29 |

## **Lógica de Programación para el Script A: Carga de Usuarios y Cursos**

Para que Claude o Gemini generen el código correcto, la lógica del script de la Fase 1-A debe seguir un flujo estrictamente secuencial y altamente tolerante a fallos.

### **Gestión de Errores y Registro de Auditoría**

El script debe abrir el archivo CSV utilizando la clase csv\_import\_reader de Moodle, que maneja automáticamente las variaciones en los delimitadores y la codificación de caracteres. Por cada fila procesada, el script debe:

1. **Verificar Existencia:** Comprobar si el username o shortname ya existen en la base de datos para evitar colisiones.  
2. **Normalización:** Limpiar los datos (espacios en blanco, conversión a minúsculas en correos electrónicos).  
3. **Ejecución de API:** Llamar a user\_create\_user() y create\_course().  
4. **Matriculación Silenciosa:** Si el CSV incluye una columna de rol, el script debe utilizar la API de enrolamiento para asignar al usuario al curso recién creado inmediatamente.12

Es vital que el script genere un archivo de resultados (ej. results.json) que detalle qué registros fueron procesados con éxito y cuáles fallaron, incluyendo el motivo exacto del error. Este archivo de resultados puede ser descargado por la capa local de AWS para informar al administrador del estado final de la carga masiva.26

## **Lógica de Programación para el Script B: Clonación de Contenidos**

El script de la Fase 1-B se centra en la transferencia de valor educativo. La clonación no debe incluir aún LTI Advantage, simplificando la lógica hacia los componentes nativos de Moodle.

### **Configuración del Backup Controller para Clonación Nativa**

Al configurar el backup\_controller, la IA debe asegurarse de que la configuración del plan de respaldo sea mínima pero suficiente. Se deben incluir actividades y archivos, pero se deben excluir explícitamente los datos de usuario (como envíos de tareas o intentos de cuestionarios del curso repositorio) mediante el ajuste de los settings del plan de respaldo.17

PHP

// Ajustes del plan de respaldo para clonación limpia  
$bc \= new backup\_controller(backup::TYPE\_1SECTION, $id, backup::FORMAT\_MOODLE,  
                            backup::INTERACTIVE\_NO, backup::MODE\_SAMESITE, $userid);  
$plan \= $bc\-\>get\_plan();  
$plan\-\>get\_setting('users')-\>set\_value(false);  
$plan\-\>get\_setting('logs')-\>set\_value(false);  
$bc\-\>execute\_plan();

### **El Desafío de los ID de Sección**

En Moodle, las secciones de los cursos no tienen nombres fijos universales, sino que dependen de la tabla mdl\_course\_sections. El script debe ser inteligente: si el curso de destino es un "shell" recién creado, el script debe mapear la sección 1 del origen a la sección 1 del destino. Sin embargo, si el destino ya tiene contenido, el script debe calcular dinámicamente el siguiente número de sección disponible o utilizar la última sección del curso para depositar el material clonado, evitando el solapamiento de contenidos.16

## **Consideraciones sobre la Estructura /public y la Seguridad de los Scripts**

Dado que Moodle 5.1.3 separa el código de la carpeta pública, los scripts de automatización nunca deben colocarse dentro de /public. El lugar ideal para estos scripts es una carpeta personalizada en la raíz del proyecto (por ejemplo, /scripts/automation/), que es inaccesible desde el navegador.3

La inclusión del archivo config.php desde esta ubicación requiere un manejo cuidadoso de las rutas. Se recomienda el uso de dirname(\_\_FILE\_\_, 3\) para alcanzar la raíz del sistema desde subdirectorios profundos. Además, el script debe comenzar con una comprobación estricta de CLI\_SCRIPT:

PHP

define('CLI\_SCRIPT', true);  
require(\_\_DIR\_\_. '/../../config.php');

Esta definición impide que el script sea ejecutado a través de un navegador incluso si se cometiera el error de colocarlo en un directorio web, proporcionando una capa adicional de defensa en profundidad.8

## **Optimización de Recursos en AWS durante la Fase 1**

Al ejecutar estas tareas en AWS, el consumo de memoria y CPU puede dispararse. PHP 8.3 ofrece mejoras en el recolector de basura que son fundamentales aquí. El manual debe indicar a la IA que, al procesar bucles masivos, invoque explícitamente gc\_collect\_cycles() o, preferiblemente, que procese los registros en lotes pequeños, liberando las instancias de los controladores de respaldo y restauración tras cada ciclo mediante el método destroy().22

El olvido de llamar a $controller-\>destroy() es una causa frecuente de agotamiento de memoria en scripts de restauración masiva, ya que estos controladores mantienen en memoria estructuras complejas del plan de respaldo que no se liberan automáticamente hasta el fin de la ejecución del script.22

### **Escalado Vertical vs. Horizontal**

Para la Fase 1, un escalado vertical (instancias EC2 más potentes) suele ser más efectivo que el horizontal, ya que los procesos de restauración de Moodle dependen intensamente de la velocidad de escritura en disco (moodledata) y la latencia de la base de datos. Se recomienda el uso de instancias con discos EBS optimizados para IOPS (io2) si el volumen de clonación es excepcionalmente alto.31

## **Conclusiones y Recomendaciones de Implementación**

La automatización de la Fase 1 para Moodle 5.1.3 representa el cimiento sobre el cual se construirá el ecosistema educativo digital. El éxito de esta implementación radica en la adhesión estricta a las APIs nativas y la comprensión profunda de la nueva estructura de directorios.

1. **Priorizar la API Nativa:** La reusabilidad y estabilidad a largo plazo solo se consiguen evitando herramientas externas y utilizando los controladores de Moodle.16  
2. **Seguridad por Diseño:** El uso de la carpeta /public y la restricción de acceso vía SSH/AWS CLI garantiza que el proceso de automatización no introduzca vulnerabilidades en la plataforma.3  
3. **Procesamiento Asíncrono:** El uso de tareas adhoc es obligatorio para mantener la estabilidad del sistema durante cargas masivas.24  
4. **Capa Local Eficiente:** La combinación de AWS CLI y SSH proporciona un control total con una sobrecarga mínima de infraestructura.

Este manual técnico proporciona el marco necesario para que cualquier inteligencia artificial de código genere una solución de grado industrial, preparada para los desafíos de la educación moderna y optimizada para la nube. La Fase 1 no solo resuelve la carga inicial, sino que establece los estándares de calidad para la futura integración de tecnologías avanzadas como LTI Advantage.

#### **Fuentes citadas**

1. Moodle 5.1, acceso: febrero 22, 2026, [https://moodledev.io/general/releases/5.1](https://moodledev.io/general/releases/5.1)  
2. Code Restructure | Moodle Developer Resources, acceso: febrero 22, 2026, [https://moodledev.io/docs/5.1/guides/restructure](https://moodledev.io/docs/5.1/guides/restructure)  
3. How to Upgrade to Moodle 5.1 (2025 Guide) | Public Folder Structure Explained, acceso: febrero 22, 2026, [https://elearning.3rdwavemedia.com/blog/moodle-5-upgrade-guide-public-folder-structure/7321/](https://elearning.3rdwavemedia.com/blog/moodle-5-upgrade-guide-public-folder-structure/7321/)  
4. Installing Moodle \- MoodleDocs, acceso: febrero 22, 2026, [https://docs.moodle.org/en/Installing\_Moodle](https://docs.moodle.org/en/Installing_Moodle)  
5. Upgrading to Moodle 5.1 via Git, instructions?, acceso: febrero 22, 2026, [https://moodle.org/mod/forum/discuss.php?d=470719](https://moodle.org/mod/forum/discuss.php?d=470719)  
6. Code Restructure | Moodle Developer Resources, acceso: febrero 22, 2026, [https://moodledev.io/docs/5.1/guides/restructure\#cli-scripts](https://moodledev.io/docs/5.1/guides/restructure#cli-scripts)  
7. Administration via command line \- MoodleDocs, acceso: febrero 22, 2026, [https://docs.moodle.org/en/Administration\_via\_command\_line](https://docs.moodle.org/en/Administration_via_command_line)  
8. Moodle in English: Programmatic backup and restore, acceso: febrero 22, 2026, [https://moodle.org/mod/forum/discuss.php?d=282328](https://moodle.org/mod/forum/discuss.php?d=282328)  
9. API Guides \- Moodle Developer Resources, acceso: febrero 22, 2026, [https://moodledev.io/docs/5.1/apis](https://moodledev.io/docs/5.1/apis)  
10. Moodle in English: Programmatically Creating Courses, acceso: febrero 22, 2026, [https://moodle.org/mod/forum/discuss.php?d=307670](https://moodle.org/mod/forum/discuss.php?d=307670)  
11. Create Moodle course dynamically using API \- Stack Overflow, acceso: febrero 22, 2026, [https://stackoverflow.com/questions/32886366/create-moodle-course-dynamically-using-api](https://stackoverflow.com/questions/32886366/create-moodle-course-dynamically-using-api)  
12. Adding a new course \- MoodleDocs, acceso: febrero 22, 2026, [https://docs.moodle.org/en/Adding\_a\_new\_course](https://docs.moodle.org/en/Adding_a_new_course)  
13. Moodle in English: Programatically Create Course Categories & Courses?, acceso: febrero 22, 2026, [https://moodle.org/mod/forum/discuss.php?d=200808](https://moodle.org/mod/forum/discuss.php?d=200808)  
14. Task API \- Moodle Developer Resources, acceso: febrero 22, 2026, [https://moodledev.io/docs/5.0/apis/subsystems/task](https://moodledev.io/docs/5.0/apis/subsystems/task)  
15. Restoring courses under Moodle 2.2 with a restore\_controller gives "error/cannot\_empty\_backup\_temp\_dir", acceso: febrero 22, 2026, [https://moodle.org/mod/forum/discuss.php?d=194308](https://moodle.org/mod/forum/discuss.php?d=194308)  
16. Moodle in English: Restore activity into specific course section ..., acceso: febrero 22, 2026, [https://moodle.org/mod/forum/discuss.php?d=398681](https://moodle.org/mod/forum/discuss.php?d=398681)  
17. Course backup \- MoodleDocs, acceso: febrero 22, 2026, [https://docs.moodle.org/en/Course\_backup](https://docs.moodle.org/en/Course_backup)  
18. Backup API \- Moodle Developer Resources, acceso: febrero 22, 2026, [https://moodledev.io/docs/4.5/apis/subsystems/backup](https://moodledev.io/docs/4.5/apis/subsystems/backup)  
19. backup\_controller Class Reference \- PHP Documentation: Moodle, acceso: febrero 22, 2026, [https://phpdoc.moodledev.io/4.4/d3/ddf/classbackup\_\_controller.html](https://phpdoc.moodledev.io/4.4/d3/ddf/classbackup__controller.html)  
20. restore\_controller Class Reference \- Moodle PHP Documentation, acceso: febrero 22, 2026, [https://phpdoc.moodledev.io/4.1/d1/d6f/classrestore\_\_controller.html](https://phpdoc.moodledev.io/4.1/d1/d6f/classrestore__controller.html)  
21. Restore activity into specific course section \- Moodle.org, acceso: febrero 22, 2026, [https://moodle.org/mod/forum/discuss.php?d=398681\&lang=ga](https://moodle.org/mod/forum/discuss.php?d=398681&lang=ga)  
22. moodle/backup/controller/restore\_controller.class.php at master \- GitHub, acceso: febrero 22, 2026, [https://github.com/totara/moodle/blob/master/backup/controller/restore\_controller.class.php](https://github.com/totara/moodle/blob/master/backup/controller/restore_controller.class.php)  
23. Duplicating Activities in the Course in Moodle™ 4 \- YouTube, acceso: febrero 22, 2026, [https://www.youtube.com/watch?v=0a2yQ6gVRkc](https://www.youtube.com/watch?v=0a2yQ6gVRkc)  
24. Adhoc tasks | Moodle Developer Resources, acceso: febrero 22, 2026, [https://moodledev.io/docs/4.4/apis/subsystems/task/adhoc](https://moodledev.io/docs/4.4/apis/subsystems/task/adhoc)  
25. Should I use Ad-hoc tasks for sending thousands of messages via Message API at a large-scale university? | Moodle.org, acceso: febrero 22, 2026, [https://moodle.org/mod/forum/discuss.php?d=469261](https://moodle.org/mod/forum/discuss.php?d=469261)  
26. Upload data to AWS S3 using linux shell script \- Stack Overflow, acceso: febrero 22, 2026, [https://stackoverflow.com/questions/75600175/upload-data-to-aws-s3-using-linux-shell-script](https://stackoverflow.com/questions/75600175/upload-data-to-aws-s3-using-linux-shell-script)  
27. Uploading files to S3 account from Linux command line \- Super User, acceso: febrero 22, 2026, [https://superuser.com/questions/279986/uploading-files-to-s3-account-from-linux-command-line](https://superuser.com/questions/279986/uploading-files-to-s3-account-from-linux-command-line)  
28. How to Automate Data Upload to Amazon S3 | by Dogukan Ulu \- Medium, acceso: febrero 22, 2026, [https://medium.com/@dogukannulu/how-to-automate-data-upload-to-amazon-s3-ea94d55cdde9](https://medium.com/@dogukannulu/how-to-automate-data-upload-to-amazon-s3-ea94d55cdde9)  
29. Amazon S3 examples using AWS CLI with Bash script, acceso: febrero 22, 2026, [https://docs.aws.amazon.com/code-library/latest/ug/bash\_2\_s3\_code\_examples.html](https://docs.aws.amazon.com/code-library/latest/ug/bash_2_s3_code_examples.html)  
30. Moodle in English: backup\_controller, acceso: febrero 22, 2026, [https://moodle.org/mod/forum/discuss.php?d=397261](https://moodle.org/mod/forum/discuss.php?d=397261)  
31. Session handling \- MoodleDocs, acceso: febrero 22, 2026, [https://docs.moodle.org/en/Session\_handling](https://docs.moodle.org/en/Session_handling)