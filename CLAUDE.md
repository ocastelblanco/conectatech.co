# CLAUDE.md
> Instrucciones permanentes para IA · ConectaTech.co
> Idioma: español colombiano · Última actualización: 2026-04-14

---

## Descripción del proyecto

**ConectaTech.co** es una plataforma educativa B2B para colegios colombianos construida sobre Moodle 5.1.3. El panel de administración (Angular 21) permite al equipo de IdeasMaestras gestionar contenido, organizaciones, pines de acceso y matrículas. Los estudiantes acceden a sus cursos directamente en el LMS.

Leer PRD.md para contexto de negocio. Leer tech-specs.md para arquitectura técnica completa. Leer MEMORY.md al inicio de cada sesión para restaurar el estado del proyecto.

## Entorno AWS

- **Account ID**: 648232846223 (cross-account access via role assumption)
- **AWS CLI profile**: `im` (configured in `~/.aws/config` using `AdministradorExterno` role)
- **Region**: us-east-1
- **EC2 key pair**: `~/.ssh/ClaveIM.pem`

Verificar acceso:
```bash
aws sts get-caller-identity --profile im
```

Todos los comandos AWS deben usar `--profile im`.

## Servidor EC2

- **IP**: `54.86.113.27`
- **SSH**: `ssh -i ~/.ssh/ClaveIM.pem ec2-user@54.86.113.27`
- **Moodle**: `/var/www/html/moodle/` (DocumentRoot: `moodle/public`)
- **Admin**: `/var/www/html/admin/` (frontend + api/ + backend/)
- **PHP-FPM usuario**: `apache`

Scripts CLI de backend siempre como usuario `apache`:
```bash
sudo -u apache php /var/www/html/admin/backend/script.php
```

## Estructura del repositorio

```
admin-module/frontend/   → Angular SPA (panel admin)
admin-module/api/        → API REST PHP
admin-module/backend/    → Scripts CLI + librerías PHP
api-service/             → Lambda Node.js (API pública de PDFs)
docs/                    → Documentación de infraestructura
PRD.md                   → Requisitos de producto
tech-specs.md            → Especificaciones técnicas
MEMORY.md                → Estado del proyecto y ADRs
TODO.md                  → Motor JIT: 2 tareas atómicas
```

## Convenciones de código

**PHP:**
- `error_log()` siempre — nunca `fwrite(STDERR, ...)` (falla en contexto web)
- Scripts CLI: `define('CLI_SCRIPT', true)` antes de cargar Moodle
- Una clase por archivo; sin namespace

**Angular:**
- Componentes standalone; signals para estado local; OnPush en listas
- `loadComponent()` para todas las rutas (lazy loading)
- Prettier: `printWidth: 100`, `singleQuote: true`

---

## Seguridad (OWASP)

Las siguientes reglas son **obligatorias** al generar código para este proyecto.
Se derivan de las vulnerabilidades más relevantes para esta arquitectura específica.

### A01 — Control de acceso roto

**Riesgo:** Los guards de Angular son solo UX — cualquier usuario puede llamar endpoints directamente sin pasar por el guard.

**Regla:** Toda operación que modifica datos (POST, PUT, DELETE) **debe** verificar identidad server-side en PHP antes de ejecutar cualquier lógica.

```php
// CORRECTO — verificar ANTES de cualquier operación
require_once API_DIR . '/auth.php';
verificarAdmin();  // lanza 401/403 si no es admin

// INCORRECTO — verificar solo en el frontend
// if (this.authService.isAdmin()) { this.http.post(...) }
```

**Prohibición absoluta:** Nunca crear un endpoint de escritura sin llamar `verificarAdmin()` o `GestorAuth::verificar()` como primera instrucción del handler.

### A02 — Fallos criptográficos

**Riesgo:** Credenciales de BD y tokens en el repositorio git; secretos enviados al cliente Angular.

**Reglas:**
- `moodle/config.php` (credenciales BD) nunca se commitea — vive solo en el servidor
- Las claves SSH (`.pem`) nunca se commitean
- Los IDs de AWS (Account ID, ARNs, Distribution IDs) pueden estar en documentación — no son secretos
- Ningún token, password ni clave privada en código Angular (se ejecuta en el browser)

### A03 — Inyección y XSS

**Riesgo:** La API PHP construye queries y genera HTML a partir de datos del cliente; Angular puede renderizar HTML inseguro.

**Reglas PHP:**
```php
// CORRECTO — parámetros preparados via API de Moodle
$DB->get_record('course', ['shortname' => $shortname]);
$DB->get_records_sql('SELECT * FROM {course} WHERE id = ?', [$id]);

// INCORRECTO — concatenación directa
$DB->get_records_sql("SELECT * FROM {course} WHERE id = $id");
```

**Reglas Angular:**
- Nunca usar `innerHTML` con datos del servidor sin sanitización
- Nunca usar `[innerHTML]` con contenido generado por usuario
- El HTML de las secciones de Moodle lo genera el backend (confiable); el HTML del usuario (formularios) se escapa siempre

### A05 — Configuración de seguridad incorrecta

**Riesgo:** CORS permisivo, headers HTTP faltantes, buckets S3 públicos con escritura.

**Reglas:**
- CORS solo para orígenes explícitos: `['https://admin.conectatech.co', 'https://conectatech.co']`
- Nunca `Access-Control-Allow-Origin: *` en endpoints que aceptan cookies
- El bucket S3 `assets.conectatech.co` permite escritura solo desde la Lambda con el IAM Role correcto — nunca desde el cliente Angular directamente
- `Content-Security-Policy` del CDN incluye `frame-ancestors` — no ampliar sin justificación

### A07 — Fallos de autenticación

**Riesgo:** Endpoints de activación pública (`/activar/*`) sin rate limiting; sesiones de gestor que no expiran.

**Reglas:**
- Los endpoints públicos de activación (`/admin-api/activar/*`) no requieren auth pero deben validar el formato del pin antes de consultar la BD
- Validar cédula en rango válido (1.000.000–1.999.999.999) antes de crear cuentas
- La sesión del gestor hereda la expiración de la sesión Moodle

### A08 — Fallos de integridad del software

**Riesgo:** Datos del cliente usados sin validación de esquema; dependencias npm con vulnerabilidades.

**Reglas:**
- Validar tipo y formato de todos los parámetros de entrada en PHP antes de usar
- Nunca confiar en campos opcionales sin `isset()` o `?? default`
- Ejecutar `npm audit` antes de cada deploy del frontend

### A10 — SSRF (Server-Side Request Forgery)

**Riesgo:** Si algún endpoint PHP hace fetch a una URL construida con input del cliente.

**Regla:** Ningún endpoint de la API acepta URLs como parámetro para hacer peticiones server-side. Las URLs de PDFs se construyen internamente con el ID del recurso, nunca con una URL enviada por el cliente.

### Tabla de prohibiciones absolutas

| Acción prohibida | Razón |
|---|---|
| Endpoint de escritura sin `verificarAdmin()` o `GestorAuth::verificar()` | A01 — acceso no autorizado |
| `$DB->get_records_sql("... WHERE id = $id")` | A03 — inyección SQL |
| `Access-Control-Allow-Origin: *` con `withCredentials: true` | A05 — CORS permisivo |
| Credencial o token en código Angular | A02 — exposición en cliente |
| Commitear `moodle/config.php` o `*.pem` | A02 — credenciales en git |
| Hacer fetch server-side a una URL enviada por el cliente | A10 — SSRF |

---

## Git Flow para Agentes IA

Las siguientes reglas son **obligatorias** para cualquier agente que opere en este repositorio. No existe excepción, aunque el usuario lo solicite explícitamente.

### Ramas protegidas

La rama `main` está protegida. **Ningún agente puede hacer commits directos a ella**, excepto hotfixes documentados y urgentes.

### Protocolo obligatorio antes de cualquier cambio de código

**Paso 1 — Verificar la rama actual:**
```bash
git branch --show-current
```
Si el resultado es `main`, ejecutar el Paso 2.
Si ya hay una feature branch activa, continuar desde el Paso 3.

**Paso 2 — Crear feature branch:**
```bash
git checkout main
git pull origin main
git checkout -b feature/descripcion-corta-en-kebab-case
```

Prefijos válidos:
- `feature/` — nueva funcionalidad
- `fix/` — corrección de bug
- `hotfix/` — corrección urgente (puede ir directo a `main` con PR inmediato)
- `docs/` — solo documentación
- `refactor/` — refactorización sin cambio funcional

**Paso 3 — Hacer los cambios y commitear:**
```bash
# Solo después de verificar que el build del frontend pasa sin errores (si se modificó)
cd admin-module/frontend && npm run build

# Si el build falla: NO commitear. Resolver primero.
git add [archivos específicos]   # Nunca `git add .` o `git add -A`
git commit -m "tipo(alcance): descripción en español colombiano"
```

**Paso 4 — Crear el Pull Request al finalizar:**
```bash
git push -u origin HEAD
gh pr create \
  --base main \
  --title "tipo(alcance): descripción breve" \
  --body "$(cat <<'EOF'
## Cambios realizados
- [bullet con cada cambio]

## Cómo probar
- [pasos verificables]

## Checklist
- [ ] Build del frontend pasa sin errores (si aplica)
- [ ] No hay secretos hardcodeados
- [ ] Reglas OWASP cumplidas
- [ ] Seguí las convenciones de código del proyecto

🤖 Generado con Claude Code
EOF
)"
```

### Deploy después del merge

El deploy al servidor EC2 es manual (no hay CI/CD). Ver tech-specs.md §7.2 para el proceso completo de rsync y cambio de permisos.

### Prohibiciones absolutas

| Acción prohibida | Por qué |
|---|---|
| `git push origin main` directamente | Commit directo a producción |
| `git push --force` en cualquier rama | Destruye historial |
| `git merge` de cualquier PR | Solo humanos pueden aprobar y fusionar |
| `--no-verify` en commits o pushes | Omite hooks de seguridad |
| `git add .` o `git add -A` | Puede incluir secretos o archivos no deseados |
| Commitear `moodle/config.php`, `.env`, `*.pem` | Exposición de credenciales |

### El agente NUNCA debe:
- Fusionar un PR (ni con `gh pr merge`, ni con `git merge`)
- Aprobar su propio PR
- Cerrar un PR sin fusionar si el trabajo está completo — dejarlo abierto para revisión humana
- Crear un PR hacia `main` con cambios no probados localmente
