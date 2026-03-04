# Instrucciones de Proyecto — admin-module
## Para Claude Code

Este documento es la referencia operativa principal para implementar el módulo de administración de ConectaTech. Los documentos de arquitectura en `admin-module/docs/` contienen el diseño detallado; este documento establece **dónde** va cada cosa y **cómo** arranca el trabajo.

---

## Contexto del proyecto

- **Plataforma:** Moodle 5.1.3 en AWS EC2 (`t4g.small`, `us-east-1c`)
- **IP elástica:** `54.86.113.27`
- **Dominio principal:** `https://conectatech.co` → `/var/www/html/moodle/`
- **Dominio admin:** `https://admin.conectatech.co` → `/var/www/html/admin/` *(por crear)*
- **Acceso SSH:** `ssh -i ~/.ssh/ClaveIM.pem ec2-user@54.86.113.27`
- **AWS CLI profile:** `im` (cross-account role en cuenta `648232846223`)
- **Usuario PHP-FPM:** `apache` (Amazon Linux 2023)

## Repositorio local

El repositorio Git de todo el proyecto está en la carpeta `conectatech.co/` de la máquina local. Este módulo vive en la subcarpeta `admin-module/`:

```
conectatech.co/
└── admin-module/
    ├── docs/           ← documentación de arquitectura (ya existe)
    ├── backend/        ← scripts PHP CLI (crear ahora)
    ├── frontend/       ← aplicación web Angular (fase futura)
    └── plugins/        ← plugins Moodle (fase futura, si aplica)
```

En el servidor, el contenido de `admin-module/backend/` se despliega en `/var/www/html/admin/backend/`.

---

## Paso 0 — Infraestructura del subdominio (prerequisito)

Antes de crear cualquier archivo PHP, hay que preparar el servidor para servir `admin.conectatech.co`. Ejecutar en orden:

### 0.1 — Crear el directorio en el servidor

```bash
ssh -i ~/.ssh/ClaveIM.pem ec2-user@54.86.113.27

sudo mkdir -p /var/www/html/admin
sudo chown apache:apache /var/www/html/admin
sudo chmod 755 /var/www/html/admin
```

### 0.2 — VirtualHost de Apache

Crear `/etc/httpd/conf.d/admin.conectatech.co.conf`:

```apache
<VirtualHost *:80>
    ServerName admin.conectatech.co
    DocumentRoot /var/www/html/admin

    <Directory /var/www/html/admin>
        Options -Indexes +FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>

    # Redirigir todo HTTP → HTTPS
    RewriteEngine On
    RewriteCond %{HTTPS} off
    RewriteRule ^ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]
</VirtualHost>

<VirtualHost *:443>
    ServerName admin.conectatech.co
    DocumentRoot /var/www/html/admin

    SSLEngine on
    SSLCertificateFile    /etc/letsencrypt/live/admin.conectatech.co/fullchain.pem
    SSLCertificateKeyFile /etc/letsencrypt/live/admin.conectatech.co/privkey.pem

    <Directory /var/www/html/admin>
        Options -Indexes +FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>

    # Los scripts PHP en backend/ NO son accesibles desde la web
    <Directory /var/www/html/admin/backend>
        Require all denied
    </Directory>
</VirtualHost>
```

**Nota crítica:** `backend/` está bloqueado por Apache con `Require all denied`. Los scripts PHP solo se ejecutan via CLI (`sudo -u apache php ...`), nunca desde el navegador.

### 0.3 — Certificado SSL para el subdominio

```bash
# Obtener certificado (Let's Encrypt)
sudo certbot certonly --apache -d admin.conectatech.co

# Verificar
sudo certbot certificates

# Recargar Apache
sudo systemctl reload httpd
```

### 0.4 — Registro DNS

Añadir en el panel DNS del dominio `conectatech.co`:

| Tipo | Nombre | Valor | TTL |
|---|---|---|---|
| A | `admin` | `54.86.113.27` | 300 |

Verificar propagación antes de continuar:
```bash
dig admin.conectatech.co +short
# Debe responder: 54.86.113.27
```

### 0.5 — Ajuste del Security Group de AWS

El puerto 443 ya está abierto para todo el tráfico público (configurado para Moodle). No se requiere cambio en el Security Group.

---

## Rutas definitivas del proyecto

### En el repositorio local (`conectatech.co/admin-module/backend/`)

```
backend/
├── config/
│   ├── courses.csv
│   └── semantic-blocks.json
├── lib/
│   ├── MoodleBootstrap.php
│   ├── MarkdownParser.php
│   ├── HtmlConverter.php
│   ├── PresaberesHtmlBuilder.php
│   ├── GiftConverter.php
│   └── MoodleContentBuilder.php
├── test/
│   └── sample-section.md
├── logs/
│   └── .gitkeep
├── procesar-markdown.php
└── report-ultimo.json
```

### En el servidor (`/var/www/html/admin/backend/`)

Ruta idéntica al repositorio. El despliegue es una copia directa (o `git pull` en el servidor una vez que se configure).

Los archivos que **no** van al repositorio (`.gitignore`):
```
backend/logs/*.log
backend/report-ultimo.json
backend/config/courses.csv   ← puede tener datos sensibles, gestionar con cuidado
```

---

## Bootstrap de Moodle — ruta correcta

Desde cualquier script en `/var/www/html/admin/backend/` (o subcarpetas), la ruta al `config.php` de Moodle es siempre absoluta:

```php
define('CLI_SCRIPT', true);
define('MOODLE_ROOT', '/var/www/html/moodle');

require(MOODLE_ROOT . '/config.php');
```

Usar la ruta absoluta en lugar de `dirname()` relativo evita errores si los scripts se llaman desde directorios diferentes.

**Desde `lib/MoodleBootstrap.php`:**

```php
<?php
define('CLI_SCRIPT', true);

// Ruta absoluta al config.php de Moodle — independiente de dónde se ejecute el script
$moodleConfig = '/var/www/html/moodle/config.php';

if (!file_exists($moodleConfig)) {
    fwrite(STDERR, "ERROR: No se encontró config.php en {$moodleConfig}\n");
    exit(1);
}

require($moodleConfig);
```

---

## Ejecución de scripts

### Sintaxis estándar

```bash
sudo -u apache php /var/www/html/admin/backend/procesar-markdown.php \
    --file /tmp/ciencias-naturales-6-7.md \
    --course repo-cn-6-7
```

### Desde la máquina local (via SSH)

```bash
# Subir el markdown al servidor
scp -i ~/.ssh/ClaveIM.pem ciencias-naturales-6-7.md \
    ec2-user@54.86.113.27:/tmp/

# Ejecutar el script remotamente
ssh -i ~/.ssh/ClaveIM.pem ec2-user@54.86.113.27 \
    "sudo -u apache php /var/www/html/admin/backend/procesar-markdown.php \
     --file /tmp/ciencias-naturales-6-7.md \
     --course repo-cn-6-7"

# Ver el reporte
ssh -i ~/.ssh/ClaveIM.pem ec2-user@54.86.113.27 \
    "cat /var/www/html/admin/backend/report-ultimo.json"
```

---

## Orden de implementación

### Fase A — Fundamentos del parser (sin tocar Moodle)
1. `config/semantic-blocks.json` — versión 1.1 (con `presaberes_feedback_mode`)
2. `config/courses.csv` — mapa de los 17 cursos repositorio
3. `lib/MoodleBootstrap.php` — inicialización de Moodle para CLI
4. `lib/MarkdownParser.php` — tokeniza el `.md` y construye el árbol
5. `lib/HtmlConverter.php` — convierte bloques semánticos a HTML
6. `lib/PresaberesHtmlBuilder.php` — genera HTML interactivo para presaberes
7. `lib/GiftConverter.php` — convierte preguntas a formato GIFT (ensayo + opción múltiple con feedback)
8. `test/sample-section.md` — 2 secciones de prueba con todos los tipos

**Validación de Fase A:** ejecutar el parser sobre `sample-section.md` y verificar que produce el árbol de datos correcto y el HTML/GIFT esperado. Aún sin conexión a Moodle.

### Fase B — Integración con Moodle
9. `lib/MoodleContentBuilder.php` — crea/actualiza cursos, secciones, subsecciones, labels, quizzes
10. `procesar-markdown.php` — script principal que orquesta todo

**Validación de Fase B:** ejecutar `procesar-markdown.php` con `sample-section.md` contra la instancia de Moodle real. Verificar en `https://conectatech.co` que las secciones, subsecciones y recursos se crearon correctamente.

### Fase C — Pulido
11. `logs/` — sistema de logging estructurado
12. `report-ultimo.json` — generación del reporte final
13. Prueba con un documento real de los 17 cursos repositorio

---

## Referencias de arquitectura

Los detalles completos de diseño están en `admin-module/docs/`. Leer en este orden:

1. `validacion-propuesta-automatizacion.md` — viabilidad técnica de cada paso
2. `arquitectura-tecnica-definitiva.md` — diseño completo del sistema (rutas en ese doc son `/var/www/scripts/automation/`; **las rutas correctas son las de este documento**)
3. `arquitectura-addendum.md` — Presaberes, retroalimentación, lógica flexible de parser

**Nota sobre rutas en los documentos de arquitectura:** los documentos de arquitectura usan `/var/www/scripts/automation/` como ruta de referencia. Esa ruta es obsoleta. La ruta correcta en todos los casos es `/var/www/html/admin/backend/`.

---

## Configuración previa en Moodle (una sola vez)

```bash
ssh -i ~/.ssh/ClaveIM.pem ec2-user@54.86.113.27

# 1. Aumentar maxsections a 250
sudo -u apache php /var/www/html/moodle/admin/cli/cfg.php \
    --component=moodlecourse \
    --name=maxsections \
    --set=250

# 2. Verificar que mod_subsection está habilitado
sudo -u apache php /var/www/html/moodle/admin/cli/check_database_schema.php

# 3. Limpiar caché después de cambios de configuración
sudo -u apache php /var/www/html/moodle/admin/cli/purge_caches.php
```

Las categorías `Repositorios` y subcategorías se crean manualmente en Moodle antes de ejecutar los scripts.

---

## Moodle 5.x — Lecciones aprendidas (API PHP)

### mod_subsection — sección delegada

`course_sections.itemid` almacena el **instance id** de la tabla `subsection`,
NO el `course_module.id`. Usar `$result->instance` (no `$result->coursemodule`).
Lo mismo aplica en `collectCmIds()`: usar `$cm->instance`.

### Contextos para question bank

`question_get_top_category()` requiere `CONTEXT_MODULE` (nivel 70).
Usar `context_module::instance($quizCmId)` — nunca `context_course`.

### Lookup del id numérico de módulo para add_moduleinfo()

Para `quiz`, el campo `module` de `course_modules` es el id numérico del módulo
en la tabla `modules`. Hacer lookup explícito:
```php
$moduleId = $DB->get_field('modules', 'id', ['name' => 'quiz'], MUST_EXIST);
```

### quiz_update_sumgrades() — deprecada desde Moodle 4.2

```php
$quizSettings = \mod_quiz\quiz_settings::create($quizId);
$quizSettings->get_grade_calculator()->recompute_quiz_sumgrades();
```

### save_question() — formato correcto

- Las opciones van directamente en `$qdata`, NO en `$qdata->options`
- `questiontext` debe ser array: `['text' => '...', 'format' => FORMAT_HTML]`
- Pasar `clone $qdata` como `$form` (segundo argumento) para evitar que
  la sobreescritura de `$question->questiontext` rompa el acceso a `['format']`
- `$qdata->context` debe ser el objeto context (no solo el id)

### mod_label — un recurso por bloque semántico H3

Cada bloque semántico H3 se crea como su propio `mod_label` independiente,
no fusionado con otros. Esto permite revertir o actualizar bloques individuales
sin afectar el resto de la sección.

---

## JavaScript global para Presaberes

Una vez validado el HTML generado por `PresaberesHtmlBuilder.php`, pegar el siguiente código en:
`Administración del sitio → Apariencia → Boost Union → Pestaña JavaScript → JavaScript personalizado`

```javascript
// ConectaTech — Presaberes Interactive Quiz v1.0
document.addEventListener('DOMContentLoaded', function () {
  document.querySelectorAll('[data-quiz-block]').forEach(function (block) {
    block.addEventListener('change', function (e) {
      if (!e.target.matches('input[type="radio"]')) return;
      var selected = e.target.closest('.respuesta-option');
      var feedback = block.querySelector('.retroalimentacion');
      var allOptions = block.querySelectorAll('.respuesta-option');
      var type = selected.dataset.type;
      var feedbackText = selected.dataset.feedback;
      allOptions.forEach(function (opt) {
        opt.classList.remove('bg-success','bg-danger','border-success','border-danger','text-white');
        opt.classList.add('border-light');
        opt.querySelector('input').disabled = true;
        opt.querySelector('label').style.cursor = 'default';
      });
      selected.classList.remove('border-light');
      if (type === 'correct') {
        selected.classList.add('bg-success','border-success','text-white');
      } else {
        selected.classList.add('bg-danger','border-danger','text-white');
      }
      selected.querySelectorAll('label, span').forEach(function(el){ el.style.color='#fff'; });
      var emoji = type === 'correct' ? '✅' : '❌';
      var alertType = type === 'correct' ? 'success' : 'warning';
      var label = type === 'correct' ? '¡Excelente!' : 'Incorrecto';
      feedback.innerHTML =
        '<div class="alert alert-'+alertType+' d-flex align-items-start shadow-sm" role="alert">'+
        '<div class="mr-3 mt-1" style="font-size:1.5rem">'+emoji+'</div>'+
        '<div><strong>'+label+'</strong>'+
        '<p class="mb-0 mt-1">'+feedbackText+'</p></div></div>';
      feedback.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    });
  });
});
```
