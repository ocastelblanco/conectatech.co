# Infraestructura del servidor — admin.conectatech.co

## Estructura de directorios en el servidor

```
/var/www/html/admin/
├── (archivos del build Angular: index.html, *.js, *.css, media/)
├── api/                        ← directorio REAL (no symlink), sincronizado desde admin-module/api/
│   ├── index.php               ← router principal de la API REST
│   ├── auth.php                ← valida sesión Moodle + rol administrador
│   ├── bootstrap.php           ← arranca Moodle desde CLI_SCRIPT=false
│   └── handlers/
│       ├── arboles.php
│       ├── cursos.php
│       ├── markdown.php
│       ├── matriculas.php
│       └── reportes.php
└── backend/                    ← sincronizado desde admin-module/backend/
    ├── config/
    ├── data/
    │   └── arboles/            ← JSON de árboles curriculares (SOLO EN SERVIDOR, no en repo)
    ├── lib/
    │   ├── ArbolCurricularService.php
    │   ├── CursosService.php
    │   └── ...
    ├── logs/
    └── *.php                   ← scripts CLI (procesar-markdown, crear-cursos, etc.)
```

## Repositorio local → servidor

| Carpeta local | Destino en servidor |
|---|---|
| `admin-module/frontend/dist/frontend/browser/` | `/var/www/html/admin/` (frontend Angular) |
| `admin-module/api/` | `/var/www/html/admin/api/` (API REST) |
| `admin-module/backend/` | `/var/www/html/admin/backend/` (scripts CLI + libs) |

**CRÍTICO:** `admin-module/api/` y `admin-module/backend/` son dos carpetas distintas en el repo local. La API vive en `api/`, NO dentro de `backend/`.

## Apache VirtualHost

### `admin.conectatech.co` → `/etc/httpd/conf.d/admin.conectatech.co.conf`

- Puerto 80: redirige todo a HTTPS (301)
- Puerto 443: sirve el SPA Angular desde `/var/www/html/admin/`
- **SPA routing:** RewriteRules dentro del bloque `<Directory>` redirigen rutas sin archivo real a `/index.html` (fix para deep-linking Angular)
- `backend/` bloqueado con `Require all denied` — scripts PHP solo accesibles via CLI

### Alias en el VirtualHost de Moodle → `/etc/httpd/conf.d/moodle-le-ssl.conf`

```apache
Alias /admin-api /var/www/html/admin/api
```

Esta línea expone la API REST en `https://conectatech.co/admin-api/*`. Está en el VirtualHost del dominio **principal** (conectatech.co), no en el del subdominio admin, porque la API debe servirse desde el mismo origen que Moodle para que las cookies de sesión funcionen correctamente.

## Flujo de autenticación

```
Usuario → admin.conectatech.co
    ↓ Angular carga (SPA)
    ↓ AuthCheckComponent llama GET https://conectatech.co/admin-api/cursos?category=__check__
    ↓ credentials interceptor añade withCredentials: true (envía cookie MoodleSession)
    ↓
  [200 OK] → isAuthenticated = true → navega a /dashboard
  [401/403] → isAuthenticated = false → window.location = 'https://conectatech.co/login'
                                          ↓
                                      Usuario hace login en Moodle
                                          ↓
                                      Regresa manualmente a admin.conectatech.co
```

La cookie `MoodleSession` se emite por `conectatech.co`. Por eso la API **debe** estar en `conectatech.co/admin-api/` y no en `admin.conectatech.co/api/` — de lo contrario el navegador no enviaría la cookie (origen distinto).

## Problema recurrente: SPA routing (Not Found en rutas directas)

Angular maneja el routing en el cliente. Si el usuario navega directamente a `https://admin.conectatech.co/arboles` (o recarga la página), Apache busca un archivo `/var/www/html/admin/arboles` que no existe y devuelve **404 Not Found**.

**Solución permanente** — ya está aplicada en el VirtualHost SSL:

```apache
<Directory /var/www/html/admin>
    ...
    RewriteEngine On
    RewriteBase /
    RewriteRule ^index\.html$ - [L]
    RewriteCond %{REQUEST_FILENAME} !-f
    RewriteCond %{REQUEST_FILENAME} !-d
    RewriteRule . /index.html [L]
</Directory>
```

Las reglas dicen: "si el archivo/directorio pedido no existe en disco, devuelve `index.html`". Angular toma el control desde ahí y renderiza la ruta correcta.

**Si vuelve a ocurrir:** verificar que estas reglas estén presentes en `/etc/httpd/conf.d/admin.conectatech.co.conf` y recargar Apache con `sudo systemctl reload httpd`.

## Procedimiento correcto de deploy

### Frontend (Angular)
```bash
# Desde la raíz del repo:
cd admin-module/frontend && npm run build -- --configuration production
rsync -a --delete \
  --exclude=api --exclude=backend \
  -e "ssh -i ~/.ssh/ClaveIM.pem" \
  dist/frontend/browser/ \
  ec2-user@54.86.113.27:/var/www/html/admin/
ssh -i ~/.ssh/ClaveIM.pem ec2-user@54.86.113.27 \
  "sudo chown -R apache:apache /var/www/html/admin"
```

> **CRÍTICO:** `--delete` elimina del destino cualquier archivo que no exista en el origen. Como `api/` y `backend/` no están en `dist/frontend/browser/`, serían borrados sin los `--exclude`. Siempre incluir ambos excludes.

### API REST
```bash
rsync -a -e "ssh -i ~/.ssh/ClaveIM.pem" \
  admin-module/api/ \
  ec2-user@54.86.113.27:/var/www/html/admin/api/
ssh -i ~/.ssh/ClaveIM.pem ec2-user@54.86.113.27 \
  "sudo chown -R apache:apache /var/www/html/admin/api"
```

### Backend (scripts CLI + libs)
```bash
rsync -a -e "ssh -i ~/.ssh/ClaveIM.pem" \
  admin-module/backend/ \
  ec2-user@54.86.113.27:/var/www/html/admin/backend/
ssh -i ~/.ssh/ClaveIM.pem ec2-user@54.86.113.27 \
  "sudo chown -R apache:apache /var/www/html/admin/backend"
```

## Datos que solo existen en el servidor (no en el repo)

| Ruta | Descripción |
|---|---|
| `/var/www/html/admin/backend/data/arboles/*.json` | Árboles curriculares creados por usuarios |
| `/var/www/html/admin/backend/logs/` | Logs de ejecución |
| `/var/www/html/admin/backend/report-ultimo.json` | Último reporte generado |

Estos archivos **nunca** se sincronizan con rsync porque la dirección es servidor→local y están en `.gitignore`. Antes de cualquier limpieza del servidor, hacer backup manual de `data/arboles/`.

## Certificado SSL

- Emitido por Let's Encrypt para `admin.conectatech.co`
- Archivos en `/etc/letsencrypt/live/admin.conectatech.co/`
- Renovación automática via certbot (cron)
- Vence: 2026-05-31

## DNS

- Zona Route 53: `Z0767805255ZNR9CRNWLH`
- Registro A: `admin` → `54.86.113.27`
