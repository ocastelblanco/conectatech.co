# Tech Specs — ConectaTech.co
> Especificaciones técnicas · Audiencia: IA / autor del proyecto
> Idioma: español colombiano (términos técnicos en inglés) · Nivel: referencia
> Última actualización: 2026-04-14
> Ver PRD §2 para el contexto de negocio de cada componente.

---

## Tabla de contenidos

1. [Arquitectura general](#1-arquitectura-general)
2. [Stack tecnológico](#2-stack-tecnológico)
3. [Estructura del repositorio](#3-estructura-del-repositorio)
4. [Frontend — Panel de administración](#4-frontend--panel-de-administración)
5. [Backend y APIs](#5-backend-y-apis)
6. [Pipeline de contenido Markdown → Moodle](#6-pipeline-de-contenido-markdown--moodle)
7. [Infraestructura y despliegue](#7-infraestructura-y-despliegue)
8. [Autenticación y seguridad](#8-autenticación-y-seguridad)
9. [Gestión de secretos y variables de entorno](#9-gestión-de-secretos-y-variables-de-entorno)
10. [Convenciones de código y git flow](#10-convenciones-de-código-y-git-flow)
11. [Roadmap técnico](#11-roadmap-técnico)

---

## 1. Arquitectura general

```
┌─────────────────────────────────────────────────────────────────────────┐
│  CLIENTE (browser)                                                       │
│                                                                          │
│  admin.conectatech.co          conectatech.co           /activar        │
│  Angular SPA (admin)           Moodle LMS (estudiantes) (página pública)│
└──────────┬─────────────────────────┬──────────────────────┬─────────────┘
           │ XHR + cookies           │ HTTP                 │ XHR
           ▼                         ▼                      ▼
┌──────────────────────────────────────────────────────────────────────────┐
│  EC2 · Amazon Linux 2023 · 54.86.113.27                                  │
│                                                                          │
│  Apache 2.4 (HTTPS via Let's Encrypt)                                    │
│                                                                          │
│  ┌─────────────────────┐    ┌──────────────────────────────────────────┐ │
│  │ /var/www/html/admin │    │ /var/www/html/moodle                     │ │
│  │                     │    │ (DocumentRoot: moodle/public)            │ │
│  │  frontend/   (SPA)  │    │                                          │ │
│  │  api/        (PHP)  │◄───┤  Alias /admin-api → /admin/api/         │ │
│  │  backend/    (PHP)  │    │                                          │ │
│  └─────────────────────┘    └──────────────────────────────────────────┘ │
│                                                                          │
│  PHP-FPM (usuario: apache) · MySQL 8 (BD: moodle)                       │
└──────────────────────────────────────────────────────────────────────────┘
           │                              │
           ▼                              ▼
┌──────────────────┐         ┌────────────────────────────────────────────┐
│  S3 Bucket       │         │  AWS API Gateway HTTP                      │
│  assets.         │◄────────┤  api.conectatech.co                        │
│  conectatech.co  │         │  → Lambda conectatech-api-pdfs (Node 22)   │
│  + CloudFront    │         │    (gestión del índice de PDFs)            │
│  CDN             │         └────────────────────────────────────────────┘
└──────────────────┘
```

**Flujo de la cookie de sesión:** La API REST debe estar en `conectatech.co/admin-api/*` (no en `admin.conectatech.co`) porque Moodle establece la cookie `MoodleSession` con `domain=conectatech.co`. Si la API estuviera en el subdominio admin, el browser no enviaría la cookie y la autenticación fallaría.

---

## 2. Stack tecnológico

| Componente | Tecnología | Versión | Propósito |
|---|---|---|---|
| LMS | Moodle | 5.1.3 | Plataforma educativa; gestión de cursos, actividades, usuarios |
| Frontend admin | Angular | 21.2.x | SPA del panel de administración |
| UI components | PrimeNG + PrimeIcons | 21.1.x / 7.0.x | Componentes UI del panel |
| UI themes | @primeuix/themes (Aura) | 2.0.x | Tema base, personalizado con preset ConectaTech |
| CSS utilities | Tailwind CSS | 4.2.x | Utilidades CSS; coexiste con PrimeNG via cssLayer |
| HTTP client | Angular HttpClient + Fetch | — | Peticiones a la API REST |
| Backend API | PHP | 8.x | API REST; carga Moodle internamente para acceder a su BD |
| Backend CLI | PHP + Moodle bootstrap | 8.x | Scripts de procesamiento de contenido |
| Base de datos | MySQL | 8.x | BD de Moodle; prefijo de tablas `mdl_` |
| Servidor web | Apache HTTP | 2.4 | Reverse proxy y servidor estático |
| Servidor | EC2 | Amazon Linux 2023 | Cómputo principal |
| CDN | CloudFront + S3 | — | Servicio de assets estáticos y PDFs |
| API pública | AWS API Gateway + Lambda | Node.js 22 | CRUD del índice de PDFs |
| Infraestructura AWS | — | — | Cuenta `648232846223`, región `us-east-1`, profile `im` |
| SSL | Let's Encrypt | — | Certificados HTTPS; renovación automática |
| Control de versiones | Git + GitHub | — | Repositorio del código fuente |

---

## 3. Estructura del repositorio

```
conectatech.co/
├── CLAUDE.md                  # Instrucciones permanentes para IA
├── PRD.md                     # Requisitos de producto
├── tech-specs.md              # Este archivo
├── MEMORY.md                  # Estado y ADRs (rehidratación de sesión)
├── TODO.md                    # Motor JIT: 2 tareas atómicas
├── README.md                  # Descripción general del repo
│
├── admin-module/              # Panel de administración completo
│   ├── frontend/              # Angular 21 SPA
│   │   └── src/app/
│   │       ├── core/          # Guards, interceptors, servicios singleton
│   │       ├── features/      # Un directorio por vista/ruta
│   │       ├── layout/        # Shell (admin) y GestorShell (gestor)
│   │       └── shared/        # Componentes y utilidades reutilizables
│   │
│   ├── api/                   # API REST PHP
│   │   ├── index.php          # Router principal
│   │   ├── bootstrap.php      # Inicialización Moodle para la API
│   │   ├── auth.php           # Verificación de sesión y roles
│   │   └── handlers/          # Un archivo PHP por recurso
│   │       ├── activacion.php
│   │       ├── activos.php
│   │       ├── arboles.php
│   │       ├── cursos.php
│   │       ├── gestor.php
│   │       ├── markdown.php
│   │       ├── matriculas.php
│   │       ├── organizaciones.php
│   │       ├── pines.php
│   │       └── reportes.php
│   │
│   ├── backend/               # Scripts CLI y librería PHP
│   │   ├── procesar-markdown.php  # CLI: procesa un .md en un curso
│   │   ├── crear-cursos.php       # CLI: crea cursos desde JSON
│   │   ├── matricular.php         # CLI: matriculación masiva desde CSV
│   │   ├── poblar-cursos.php      # CLI: clonar secciones entre cursos
│   │   ├── install-pines.php      # CLI: instalar tablas ct_* en Moodle
│   │   ├── config/                # JSON de configuración del pipeline
│   │   │   ├── semantic-blocks.json  # Definición de bloques semánticos
│   │   │   ├── categories.json       # Categorías Moodle de referencia
│   │   │   └── *.csv / *.json        # Datos de seed (no datos de prod)
│   │   ├── lib/               # Clases PHP reutilizables
│   │   │   ├── MarkdownParser.php
│   │   │   ├── HtmlConverter.php
│   │   │   ├── PresaberesHtmlBuilder.php
│   │   │   ├── GiftConverter.php
│   │   │   ├── MoodleContentBuilder.php  # Orquestador del pipeline
│   │   │   ├── MoodleBootstrap.php
│   │   │   ├── MoodleSectionCloner.php
│   │   │   ├── MarkdownService.php       # Fachada del pipeline
│   │   │   ├── ArbolCurricularService.php
│   │   │   ├── ActivacionService.php
│   │   │   ├── CsvLoader.php
│   │   │   ├── CursosService.php
│   │   │   ├── GestorAuth.php
│   │   │   ├── GestorService.php
│   │   │   ├── MatriculasService.php
│   │   │   ├── OrganizacionService.php
│   │   │   ├── PinesService.php
│   │   │   └── PobladorService.php
│   │   └── data/              # SOLO en servidor (NO en git)
│   │       └── arboles/       # JSONs de árboles curriculares (datos prod)
│   │
│   └── docs/                  # Documentación del módulo admin
│
├── api-service/               # Lambda para API pública de PDFs
│   └── functions/pdfs/
│       ├── index.mjs          # Handler Lambda (Node.js 22 ESM)
│       └── package.json
│
├── docs/                      # Documentación del proyecto
│   ├── instrucciones-inicio.md
│   ├── infraestructura-cdn.md
│   └── infraestructura-servidor.md
│
├── snippets/                  # HTML/JS snippets para Moodle
├── templates/                 # Templates de cursos y secciones
├── terraform/                 # (vacío / experimental)
├── scripts/                   # Scripts de operaciones AWS
├── config/                    # Configuración general del repo
└── viewer-pdf/                # Aplicación visor PDF (SPA estática en CDN)
```

**Datos que NO están en el repositorio (solo en servidor):**
- `backend/data/arboles/*.json` — árboles curriculares (datos de producción)
- `backend/logs/` — logs del pipeline
- Archivos `.md` de cursos (pueden o no estar en el repo según el caso)

---

## 4. Frontend — Panel de administración

### 4.1 Arquitectura del frontend

| Patrón | Aplicación |
|---|---|
| **Componentes standalone** | Todos los componentes son standalone (sin NgModule) |
| **Signals** | Estado reactivo local en componentes (`signal`, `computed`, `effect`) |
| **OnPush change detection** | Aplicado en componentes de listas grandes para rendimiento |
| **Lazy loading** | Cada ruta carga su componente con `loadComponent()` |
| **HTTP Client con Fetch API** | `withFetch()` habilitado en `provideHttpClient` |
| **Interceptor de credenciales** | `credentialsInterceptor` añade `{ withCredentials: true }` a todas las peticiones |

### 4.2 Rutas y navegación

| Ruta | Componente | Guard | Notas |
|---|---|---|---|
| `/` | Redirect a `/auth-check` | — | — |
| `/auth-check` | `AuthCheckComponent` | Ninguno | Detecta rol y redirige a `/dashboard` o `/gestor` |
| `/dashboard` | `DashboardComponent` | `authGuard` | Panel principal del administrador |
| `/matriculas` | `MatriculasComponent` | `authGuard` | Carga CSV y procesa matrículas masivas |
| `/contenido` | `ContenidoComponent` | `authGuard` | Gestión de cursos y contenido Moodle |
| `/arboles` | `ArbolesListComponent` | `authGuard` | Lista de árboles curriculares |
| `/arboles/:id` | `ArbolEditorComponent` | `authGuard` | Editor del árbol curricular |
| `/activos` | `ActivosComponent` | `authGuard` | Biblioteca de PDFs y recursos |
| `/organizaciones` | `OrganizacionesComponent` | `authGuard` | CRUD de organizaciones |
| `/pines` | `PinesComponent` | `authGuard` | Gestión de paquetes y pines |
| `/pines/reporte` | `PinesReporteComponent` | `authGuard` | Reporte de uso de pines |
| `/gestor` | `GestorShellComponent` | `gestorGuard` | Shell del portal del gestor |
| `/gestor/dashboard` | `GestorDashboardComponent` | `gestorGuard` | — |
| `/gestor/grupos` | `GestorGruposComponent` | `gestorGuard` | — |
| `/gestor/pines` | `GestorPinesComponent` | `gestorGuard` | — |
| `/activar` | `ActivarComponent` | Ninguno | Página pública de activación de pins |

**Guards:**
- `authGuard`: verifica sesión Moodle activa con rol administrador (`GET /admin-api/auth`)
- `gestorGuard`: verifica sesión Moodle activa con rol gestor

### 4.3 Sistema de estilos

| Capa | Tecnología | Orden CSS |
|---|---|---|
| Reset / base | Tailwind CSS base | 1 — `tailwind-base` |
| Componentes UI | PrimeNG (Aura preset) | 2 — `primeng` |
| Utilidades | Tailwind CSS utilities | 3 — `tailwind-utilities` |

El tema personalizado `ConectaTechPreset` extiende Aura con:
- Color primario: `#4A90E2` (sky-500 customizado)
- Border-radius de botones: `8px`, cards: `12px`, inputs: `8px`
- Dark mode via clase `.dark` (no `prefers-color-scheme`)

### 4.4 Deploy del frontend

```bash
# 1. Build
cd admin-module/frontend
npm run build
# Output: dist/frontend/browser/

# 2. Deploy (excluir api/ y backend/ del servidor)
rsync -e "ssh -i ~/.ssh/ClaveIM.pem" -a --delete \
  --exclude=api --exclude=backend \
  dist/frontend/browser/ \
  ec2-user@54.86.113.27:/var/www/html/admin/
```

**CRÍTICO:** `--delete` sin los `--exclude` elimina `api/` y `backend/` del servidor.

---

## 5. Backend y APIs

### 5.1 API REST principal (`/admin-api/*`)

Todos los endpoints requieren sesión Moodle activa + rol administrador, excepto los marcados como **[público]** o **[gestor]**.

| Método | Endpoint | Handler | Descripción |
|---|---|---|---|
| GET | `/admin-api/ping` | inline | Health check sin auth |
| GET | `/admin-api/cursos` | `cursos.php` | Listar cursos `[?category=path]` |
| POST | `/admin-api/cursos/crear` | `cursos.php` | Crear cursos desde array JSON |
| POST | `/admin-api/cursos/poblar` | `cursos.php` | Poblar cursos desde mapping JSON |
| POST | `/admin-api/matriculas` | `matriculas.php` | Crear usuarios y matricular |
| POST | `/admin-api/markdown` | `markdown.php` | Procesar Markdown en curso repositorio |
| GET | `/admin-api/reportes/{nombre}` | `reportes.php` | Último reporte JSON de una operación |
| GET | `/admin-api/activos/cursos-repositorio` | `activos.php` | Cursos repositorio con secciones |
| POST | `/admin-api/activos/crear-visor` | `activos.php` | Crea visor PDF (mod_label) en Moodle |
| GET | `/admin-api/organizaciones` | `organizaciones.php` | Listar organizaciones |
| POST | `/admin-api/organizaciones` | `organizaciones.php` | Crear organización |
| PUT | `/admin-api/organizaciones/{id}` | `organizaciones.php` | Renombrar / reasignar categoría |
| DELETE | `/admin-api/organizaciones/{id}` | `organizaciones.php` | Eliminar organización (cascade) |
| GET | `/admin-api/organizaciones/{id}/gestor-pines` | `organizaciones.php` | Listar pines de gestor |
| POST | `/admin-api/organizaciones/{id}/gestor-pines` | `organizaciones.php` | Crear pin de gestor |
| DELETE | `/admin-api/gestor-pines/{hash}` | `organizaciones.php` | Anular pin de gestor pendiente |
| GET | `/admin-api/paquetes` | `pines.php` | Listar paquetes `[?org_id=X]` |
| POST | `/admin-api/paquetes` | `pines.php` | Crear paquete + generar pines |
| POST | `/admin-api/paquetes/{id}/asignar` | `pines.php` | Reasignar paquete a organización |
| GET | `/admin-api/pines/reporte` | `pines.php` | Reporte de uso `[?org_id=X&package_id=Y]` |
| POST | `/admin-api/activar/resolver` | `activacion.php` | **[público]** Resolver pin por hash |
| POST | `/admin-api/activar/gestor` | `activacion.php` | **[público]** Crear cuenta de gestor |
| POST | `/admin-api/activar/login` | `activacion.php` | **[público]** Verificar credenciales Moodle |
| POST | `/admin-api/activar/pin` | `activacion.php` | **[público]** Matricular usuario en curso del pin |
| GET | `/admin-api/arboles` | `arboles.php` | Listar árboles curriculares |
| POST | `/admin-api/arboles` | `arboles.php` | Crear árbol curricular |
| GET | `/admin-api/arboles/{id}` | `arboles.php` | Obtener árbol curricular |
| PUT | `/admin-api/arboles/{id}` | `arboles.php` | Actualizar árbol curricular |
| DELETE | `/admin-api/arboles/{id}` | `arboles.php` | Eliminar árbol curricular |
| POST | `/admin-api/arboles/{id}/desplegar` | `arboles.php` | Desplegar árbol en Moodle |
| GET | `/admin-api/gestor/auth` | `gestor.php` | **[gestor]** Verificar sesión gestor |
| GET | `/admin-api/gestor/dashboard` | `gestor.php` | **[gestor]** Estadísticas del gestor |
| GET | `/admin-api/gestor/grupos` | `gestor.php` | **[gestor]** Grupos de la organización |
| GET | `/admin-api/gestor/pines` | `gestor.php` | **[gestor]** Pines de la organización |
| POST | `/admin-api/gestor/pines/{hash}/asignar` | `gestor.php` | **[gestor]** Asignar pin a estudiante |
| GET | `/admin-api/matriculas` | `matriculas.php` | **[gestor]** Listar matrículas |

**Arquitectura del bootstrap PHP:**
1. Apache recibe petición en `conectatech.co/admin-api/*`
2. Alias Apache → `/var/www/html/admin/api/`
3. `index.php` parsea la ruta y carga el handler correspondiente
4. `bootstrap.php` inicializa Moodle (`require config.php`) para acceder a `$DB`, `$CFG`, etc.
5. `auth.php` verifica `MoodleSession` cookie + `is_siteadmin()` o rol gestor
6. El handler ejecuta la lógica de negocio usando las APIs de Moodle directamente

**Output buffering:** `ob_start()` al inicio de `index.php` captura cualquier HTML que Moodle pueda imprimir durante su inicialización. Se descarta antes de enviar el JSON.

### 5.2 API pública de recursos (`api.conectatech.co`)

| Método | Endpoint | Auth | Descripción |
|---|---|---|---|
| GET | `/pdfs` | — | Listar PDFs disponibles |
| GET | `/pdfs/{id}` | — | Obtener metadatos de un PDF |
| POST | `/pdfs` | Origen `admin.conectatech.co` | Crear entrada de PDF |
| PUT | `/pdfs/{id}` | Origen `admin.conectatech.co` | Actualizar metadatos |
| DELETE | `/pdfs/{id}` | Origen `admin.conectatech.co` | Eliminar PDF del índice |

**Auth:** CORS origin-based. El handler Lambda verifica el origen de la petición:
- `admin.conectatech.co` → CRUD completo
- `conectatech.co` → solo GET
- Otros → 403

El índice se almacena en `s3://assets.conectatech.co/recursos/pdf/index.json`.

### 5.3 Servicios externos

| Servicio | Estado | Uso actual | Uso futuro |
|---|---|---|---|
| Moodle (local) | ✅ Activo | BD, cursos, usuarios, cuestionarios, roles | — |
| S3 `assets.conectatech.co` | ✅ Activo | PDFs, visor PDF, assets estáticos | Importación masiva de PDFs |
| CloudFront `E2KULI3BS0YJDX` | ✅ Activo | CDN del bucket de assets | — |
| Lambda `conectatech-api-pdfs` | ✅ Activo | CRUD del índice de PDFs | — |
| API Gateway `7xv6etd54i` | ✅ Activo | Exposición HTTP de la Lambda | — |
| Let's Encrypt | ✅ Activo | SSL automático | — |
| Route 53 | ✅ Activo | DNS de todos los dominios | — |
| SES (Simple Email Service) | ❌ No configurado | — | Notificaciones por correo |

### 5.4 Tablas de base de datos propias (prefijo `ct_`)

| Tabla | Propósito |
|---|---|
| `ct_organizaciones` | Organizaciones suscritas (colegios) |
| `ct_gestor` | Cuentas de gestores con sus organizaciones |
| `ct_paquetes` | Paquetes de cursos por organización |
| `ct_pines` | Pines de acceso para estudiantes (ciclo de vida completo) |
| `ct_gestor_pines` | Pines de activación de gestores |

---

## 6. Pipeline de contenido Markdown → Moodle

Ver PRD §5.1 para el contexto de negocio.

### 6.1 Clases del pipeline

| Clase | Responsabilidad |
|---|---|
| `MarkdownParser` | Lee el `.md` y construye el árbol de secciones/subsecciones/bloques |
| `HtmlConverter` | Convierte bloques semánticos a HTML usando `semantic-blocks.json` |
| `PresaberesHtmlBuilder` | Genera el HTML interactivo de la actividad de presaberes |
| `GiftConverter` | Convierte preguntas de opción múltiple a formato GIFT de Moodle |
| `MoodleContentBuilder` | Orquestador: crea secciones, labels, quizzes y preguntas ensayo en Moodle |
| `MarkdownService` | Fachada: acepta string o ruta de archivo; gestiona el ciclo completo |

### 6.2 Convenciones del Markdown

```markdown
# Título de sección (H1) → crea una sección en Moodle
## Título de subsección (H2) → crea una subsección delegada (mod_subsection)
### Título de sub-subsección (H3) → dentro de subsección
```

Los bloques semánticos se definen con comentarios HTML:
```markdown
<!-- presaberes -->
pregunta y opciones aquí
<!-- /presaberes -->

<!-- reflexion -->
texto de reflexión
<!-- /reflexion -->
```

Los tipos de pregunta en secciones de evaluación:
```markdown
### Pregunta de opción múltiple
tipo: opcion-multiple
enunciado: ¿Cuál es la respuesta?
- Opción incorrecta
- = Opción correcta (prefijo =)
  feedback: Explicación del feedback

### Pregunta de ensayo con adjunto
tipo: ensayo
variante: adjunto
enunciado: Describe con un archivo...

### Pregunta de ensayo de texto
tipo: ensayo
variante: texto
enunciado: Describe con tus palabras...
```

### 6.3 Gotchas críticos del pipeline

| Situación | Causa | Solución |
|---|---|---|
| Secciones creadas parcialmente (corte silencioso) | Un error de BD dentro de una transacción delegada de Moodle activa `$DB->force_rollback = true`, bloqueando todas las operaciones siguientes | Truncar `question.name` a 255 chars (`mb_substr`); llamar `$DB->force_transaction_rollback()` en catch blocks |
| `numsections` no refleja todas las secciones H1 | Las subsecciones delegadas ocupan slots de número de sección, empujando los H1 a posiciones altas | `finalizeCourse()` usa `MAX(section)` de `course_sections`, no `count($sections)` |
| `Undefined constant "STDERR"` | `fwrite(STDERR, ...)` solo funciona en CLI; la API es web | Usar `error_log()` siempre |
| Output HTML de Moodle corrompe el JSON de respuesta | Moodle imprime HTML en algunos contextos de inicialización | `ob_start()` al inicio de `index.php` y `ob_end_clean()` antes de JSON |

---

## 7. Infraestructura y despliegue

### 7.1 Servidor EC2

| Parámetro | Valor |
|---|---|
| IP elástica | `54.86.113.27` |
| OS | Amazon Linux 2023 |
| Usuario SSH | `ec2-user` |
| Clave SSH | `~/.ssh/ClaveIM.pem` |
| PHP-FPM usuario | `apache` |
| Moodle root | `/var/www/html/moodle/` |
| Moodle DocumentRoot | `/var/www/html/moodle/public` |
| Admin root | `/var/www/html/admin/` |
| Security Group | `sg-039bcb1cb3a57db7f` (SSH solo desde IP fija) |
| VirtualHost admin SSL | `/etc/httpd/conf.d/admin.conectatech.co.conf` |
| VirtualHost Moodle SSL | `/etc/httpd/conf.d/moodle-le-ssl.conf` |

**Conectar por SSH:**
```bash
ssh -i ~/.ssh/ClaveIM.pem ec2-user@54.86.113.27
```

**Si SSH da timeout:** la IP del cliente cambió. Actualizar el Security Group:
```bash
# Revocar IP anterior
aws ec2 revoke-security-group-ingress --profile im \
  --group-id sg-039bcb1cb3a57db7f \
  --protocol tcp --port 22 --cidr <IP_VIEJA>/32

# Autorizar nueva IP
aws ec2 authorize-security-group-ingress --profile im \
  --group-id sg-039bcb1cb3a57db7f \
  --protocol tcp --port 22 --cidr <IP_NUEVA>/32
```

### 7.2 Proceso de deploy

**Regla de oro:** Nunca hacer `rsync --delete` sin los `--exclude` correctos ni `rm -rf` en `/var/www/html/admin/`.

#### Deploy del frontend Angular

```bash
# 1. Build local
cd admin-module/frontend && npm run build

# 2. Si el servidor tiene archivos de ec2-user (post-rsync anterior):
#    No es necesario cambiar permisos antes del primer rsync.
#    Si da "Permission denied", ver paso de cambio de propietario abajo.

# 3. Sincronizar solo el build (preservar api/ y backend/)
rsync -e "ssh -i ~/.ssh/ClaveIM.pem" -a --delete \
  --exclude=api --exclude=backend \
  dist/frontend/browser/ \
  ec2-user@54.86.113.27:/var/www/html/admin/

# 4. Restaurar permisos para Apache
ssh -i ~/.ssh/ClaveIM.pem ec2-user@54.86.113.27 \
  "sudo chown -R apache:apache /var/www/html/admin && sudo chmod -R 755 /var/www/html/admin"
```

#### Deploy del backend PHP (api/ y backend/)

```bash
# Si rsync falla con "Permission denied" (archivos pertenecen a apache):
ssh -i ~/.ssh/ClaveIM.pem ec2-user@54.86.113.27 \
  "sudo chown -R ec2-user:ec2-user /var/www/html/admin"

# Sincronizar API
rsync -e "ssh -i ~/.ssh/ClaveIM.pem" -a \
  admin-module/api/ \
  ec2-user@54.86.113.27:/var/www/html/admin/api/

# Sincronizar backend
rsync -e "ssh -i ~/.ssh/ClaveIM.pem" -a \
  admin-module/backend/ \
  ec2-user@54.86.113.27:/var/www/html/admin/backend/

# Restaurar propietario para Apache
ssh -i ~/.ssh/ClaveIM.pem ec2-user@54.86.113.27 \
  "sudo chown -R apache:apache /var/www/html/admin && sudo chmod -R 755 /var/www/html/admin"
```

#### Deploy de la Lambda (API pública de PDFs)

```bash
cd api-service/functions/pdfs
zip -r function.zip index.mjs package.json node_modules/
aws lambda update-function-code --profile im \
  --function-name conectatech-api-pdfs \
  --zip-file fileb://function.zip
```

#### Ejecutar scripts CLI en el servidor

Los scripts PHP del backend deben ejecutarse como usuario `apache` (para que Moodle reconozca la ruta del CLI):

```bash
ssh -i ~/.ssh/ClaveIM.pem ec2-user@54.86.113.27
sudo -u apache php /var/www/html/admin/backend/procesar-markdown.php \
  --file /ruta/al/archivo.md \
  --course shortname-del-curso
```

### 7.3 Variables de entorno / configuración

No hay archivo `.env` explícito. La configuración vive en:
- `moodle/config.php` — credenciales de BD y configuración de Moodle (en servidor, nunca en git)
- `backend/config/*.json` — configuración del pipeline (sí en git, sin secretos)

### 7.4 Infraestructura AWS relevante

| Recurso | ID | Propósito |
|---|---|---|
| Route 53 zona `conectatech.co` | `Z0767805255ZNR9CRNWLH` | DNS de todos los dominios |
| S3 bucket | `assets.conectatech.co` | Assets estáticos y PDFs |
| CloudFront distribution | `E2KULI3BS0YJDX` | CDN del bucket |
| CloudFront CNAME | `dvlgey5i48r61.cloudfront.net` | — |
| Response Headers Policy | `65f8d048-1426-492f-9acd-ccc02c4b1151` | CSP con `frame-ancestors` para iframes en Moodle |
| API Gateway | `7xv6etd54i` | API pública de PDFs |
| API Gateway dominio | `d-j9xoul7vac.execute-api.us-east-1.amazonaws.com` | Custom domain de la API |
| Lambda | `conectatech-api-pdfs` | Handler de PDFs (arm64, Node.js 22, 256 MB) |
| IAM Role Lambda | `conectatech-api-lambda-role` | S3 CRUD + ListBucket en `assets.conectatech.co` |
| ACM Cert | `arn:aws:acm:us-east-1:648232846223:certificate/a47014a1-092e-4e1a-a2eb-406a3a9f642c` | SSL de `api.conectatech.co` |

---

## 8. Autenticación y seguridad

### 8.1 Tres niveles de auth

| Nivel | Quién | Mecanismo | Verificación server-side |
|---|---|---|---|
| **Público** | Cualquier visitante | Ninguna | — |
| **Gestor** | Representante de organización | Cookie `MoodleSession` + consulta a `ct_gestor` | `GestorAuth::verificar()` — consulta BD |
| **Administrador** | Equipo IdeasMaestras | Cookie `MoodleSession` + `is_siteadmin()` | `auth.php` — llama API Moodle |

### 8.2 CORS

La API acepta peticiones de:
- `https://admin.conectatech.co` — panel de administración
- `https://conectatech.co` — LMS (para endpoints públicos)

```php
$allowedOrigins = ['https://admin.conectatech.co', 'https://conectatech.co'];
```

Nunca `Access-Control-Allow-Origin: *`.

### 8.3 Content Security Policy (CloudFront)

El CDN incluye `frame-ancestors https://conectatech.co https://admin.conectatech.co` para permitir que los iframes del visor PDF funcionen dentro de Moodle y el panel admin.

---

## 9. Gestión de secretos y variables de entorno

| Secreto / Variable | Dónde vive | Contexto |
|---|---|---|
| Credenciales BD MySQL | `/var/www/html/moodle/config.php` | Solo en servidor, nunca en git |
| Clave SSH EC2 | `~/.ssh/ClaveIM.pem` | Solo en máquina local del developer |
| AWS credentials | `~/.aws/config` (profile `im`) | Solo en máquina local |
| Moodle `wwwroot` | `moodle/config.php` | `https://conectatech.co` |
| `$CFG->dirroot` | `moodle/config.php` | `/var/www/html/moodle` |
| IDs de AWS (no secretos) | `MEMORY.md`, `tech-specs.md` | Documentados públicamente en el repo |

**Regla:** Ningún secreto (contraseña, token, clave privada) va en el repositorio git.

---

## 10. Convenciones de código y git flow

### 10.1 PHP (backend)

- Una clase por archivo, nombre de archivo = nombre de clase
- Clases sin namespace (se cargan por include en `index.php`)
- `error_log()` para logging — nunca `fwrite(STDERR, ...)` (falla en contexto web)
- Todos los scripts CLI comienzan con `define('CLI_SCRIPT', true)` antes de cargar Moodle
- Los scripts CLI deben ejecutarse como usuario `apache`: `sudo -u apache php script.php`

### 10.2 Angular (frontend)

- Componentes standalone (sin NgModule)
- Signals para estado local reactivo
- `OnPush` en componentes con listas grandes
- `loadComponent()` para lazy loading en todas las rutas
- Nombres de archivos en kebab-case: `mi-componente.component.ts`
- Prettier con `printWidth: 100`, `singleQuote: true`

### 10.3 Git flow

Ver CLAUDE.md §Git Flow para Agentes IA (reglas obligatorias).

**Resumen:**
- Rama principal: `main` (producción)
- No existe rama de desarrollo separada; los hotfixes van directo a `main`
- Toda nueva funcionalidad: branch `feature/descripcion`, `fix/descripcion`, `docs/descripcion`, `refactor/descripcion`
- Al terminar: `gh pr create --base main`
- Ningún agente hace merge

**Commits:** formato `tipo(alcance): descripción en español`

---

## 11. Roadmap técnico

Ver PRD §6 para el contexto de negocio de cada item.

| Feature | Archivos a crear / modificar | Dependencias técnicas |
|---|---|---|
| **Sección 0 de cursos finales** | `features/arboles/arbol-editor.component.ts`, `handlers/arboles.php`, `lib/ArbolCurricularService.php` | UI de edición en el árbol; campo de contenido por curso final; deploy en Moodle al desplegar el árbol |
| **Reportes de progreso** | `features/reportes/` (nuevo), `handlers/reportes.php` (ampliar) | API Moodle de completion; queries a `mdl_course_completions` |
| **Tipos de pregunta GIFT adicionales** | `lib/GiftConverter.php`, `lib/MarkdownParser.php` | Definir convención Markdown para cada tipo; implementar en `GiftConverter` |
| **Notificaciones por correo** | `lib/NotificacionService.php` (nuevo), AWS SES | Configurar SES, IAM policy, templates de correo |
| **Auth gestor con JWT** | `api/auth.php`, `lib/GestorAuth.php`, `core/guards/gestor.guard.ts` | Decisión de biblioteca JWT PHP; eliminar dependencia de cookies Moodle para gestores |
