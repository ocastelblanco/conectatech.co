# MEMORY.md — ConectaTech.co
> Documento de rehidratación de sesión · Leer al inicio de cada sesión
> Última actualización: 2026-04-27

---

## 1. Estado actual del proyecto

| Atributo | Valor |
|---|---|
| **Versión frontend** | 1.2.0 (`admin-module/frontend/package.json`) |
| **Moodle** | 5.2 (Build: 20260420) |
| **URL producción LMS** | `https://conectatech.co` |
| **URL panel admin** | `https://admin.conectatech.co` |
| **URL CDN** | `https://assets.conectatech.co` |
| **URL API pública** | `https://api.conectatech.co` |
| **URL API interna** | `https://conectatech.co/admin-api/` |
| **Rama principal** | `main` |
| **Última sesión relevante** | 2026-04-27 — Moodle 5.1.3 → 5.2, Boost Union SCSS fix |

---

## 1b. Filesystem de Moodle en el servidor (descubierto 2026-04-27)

> Esta sección evita tener que re-explorar el servidor para entender la estructura.

### Raíz de Moodle: `/var/www/html/moodle/`

```
/var/www/html/moodle/           ← Raíz de instalación (NO es el DocumentRoot)
├── config.php                  ← Credenciales de BD (en raíz, fuera de web)
├── admin/cli/                  ← Scripts CLI (upgrade.php, purge_caches.php, etc.)
│   └── upgrade.php             ← Ruta CLI: /var/www/html/moodle/admin/cli/upgrade.php (SIN /public/)
├── lib/setup.php               ← Bootstrap de arranque (carga public/lib/setup.php)
├── vendor/                     ← Dependencias Composer (en raíz, NO en public/)
├── local/                      ← Symlink/directorio local (no-web)
└── public/                     ← DocumentRoot real (Apache apunta aquí)
    ├── version.php             ← Versión de Moodle
    ├── index.php
    ├── config.php              ← Alias/symlink al config.php de raíz
    ├── admin/                  ← Admin web de Moodle
    ├── lib/                    ← Librerías web de Moodle
    │   └── classes/output/     ← Nueva clase \core\output\theme_config (Moodle 5.2)
    ├── theme/
    │   └── boost_union/        ← Tema Boost Union (externo, instalado manualmente)
    ├── question/type/          ← Tipos de pregunta (qtype_*)
    └── local/                  ← Plugins locales (web-accessible)
```

### Datos de Moodle: `/moodledata/`

```
/moodledata/                                ← $CFG->dataroot
└── localcache/
    └── theme/
        └── [themerev]/                     ← Número de revisión del tema (Unix timestamp)
            └── boost_union/
                └── css/
                    └── all_[rev].css       ← CSS compilado (1.27 MB = SCSS propio compilado)
                                            ← (1.9 MB = fallback precompilado del tema)
```

**Indicador rápido:** Si el CSS de `boost_union` mide ~1.9 MB → está sirviendo el fallback (SCSS no compiló). Si mide ~1.27 MB → SCSS compilado correctamente.

### Base de datos (RDS — NO es localhost)

```
Host: conectatech-prod-db-20260217043350287600000001.cuz8c66mcaes.us-east-1.rds.amazonaws.com
Usuario: moodleadmin
BD: moodle
Prefijo de tablas: mdl_  (nuestras tablas: mdl_ct_organization, mdl_ct_pin, etc.)
```

**IMPORTANTE:** `mysql -u root` falla (no hay MySQL local). Usar siempre:
```bash
mysql -h conectatech-prod-db-20260217043350287600000001.cuz8c66mcaes.us-east-1.rds.amazonaws.com \
  -u moodleadmin -p[pass] moodle -e "..."
```

Para obtener la contraseña sin hardcodearla:
```bash
sudo -u apache php -r "
  define('CLI_SCRIPT', true);
  define('ABORT_AFTER_CONFIG', true);
  require('/var/www/html/moodle/config.php');
  echo \$CFG->dbpass . PHP_EOL;
" 2>/dev/null
```

---

## 1c. Plugins externos activados en Moodle (2026-04-27)

### Boost Union — Tema activo

| Atributo | Valor |
|---|---|
| **Versión** | v5.1-r10 (build 2025100620) |
| **Ruta en servidor** | `/var/www/html/moodle/public/theme/boost_union/` |
| **Estado** | Activo (`mdl_config.theme = 'boost_union'`) |
| **brandcolor** | `#1D2B36` (azul medianoche — sobrescribe `$primary` de Bootstrap) |
| **navbarcolor** | `light` |
| **loginformtransparency** | `no` |
| **logincontainerwidth** | `500px` |

**Raw SCSS:** Guardado en `mdl_config_plugins` (`plugin = 'theme_boost_union'`):
- `scsspre` → contenido de `snippets/conectatech-pre.scss` (variables + @font-face)
- `scss` → contenido de `snippets/conectatech-post.scss` (reglas CSS)

**Snippets en repo:** `snippets/conectatech-pre.scss` y `snippets/conectatech-post.scss`

**NOTA:** Cuando reinstales Boost Union o lo reconfiguros, debes pegar el contenido de los snippets en Boost Union → Apariencia → SCSS. El servidor ya tiene los valores en BD pero la UI te permite editarlos.

### local_conectatech — DESINSTALADO

Plugin local personalizado que existía solo en el servidor (nunca commitido al repo). Desinstalado el 2026-04-27 con `uninstall_plugins.php --purge-missing`. No hay rastro en repo ni en BD. Fue reemplazado por **Smart Menu** de Boost Union.

---

## 1d. Fix SCSS Boost Union + Moodle 5.2 (ADR-009)

**Problema:** Tras el upgrade a Moodle 5.2, el Raw SCSS dejó de aplicarse. El tema servía el CSS precompilado (1.9 MB) sin los estilos de ConectaTech.

**Causa raíz:** Moodle 5.2 cambió la arquitectura de compilación SCSS (`\core\output\theme_config` en `lib/classes/output/theme_config.php`). Ahora llama automáticamente al callback del tema padre (Boost) Y al del hijo (Boost Union) — Moodle 5.1 solo llamaba al hijo. Boost Union v5.1 no estaba diseñado para este doble callback, causando que variables de Bootstrap se usaran antes de ser definidas → excepción → fallback al CSS precompilado.

**Errores específicos corregidos:**

| Variable | Error | Origen |
|---|---|---|
| `$white`, `$black` | `Undefined variable $white: line: 1284` | Boost Union generaba CSS de login que usa `$white` antes de que Bootstrap la defina |
| `$logincontainer-shadow` | `Undefined variable $logincontainer-shadow: line: 2207` | Bug de Boost Union v5.1: usada en `post.scss:1786` pero **nunca definida** en lib.php ni en ningún SCSS |

**Fix aplicado:** Añadir al campo `scsspre` (y al snippet `conectatech-pre.scss`) estas líneas al inicio, **antes** de nuestras declaraciones:

```scss
$white: #fff !default;
$black: #000 !default;
$logincontainer-shadow: none !default; // Bug Boost Union v5.1: usado en post.scss pero nunca definido
```

**Verificación:** CSS compilado mide ~1.27 MB (no 1.9 MB). Checklist:
```python
# En el CSS compilado deben estar:
'ff7f50' in css          # btn-primary coral ✅
'1d2b36' in css          # drawer midnight ✅
'--conecta-midnight' in css  # CSS custom properties ✅
'montserrat' in css.lower()  # @font-face ✅
```

**Si el SCSS vuelve a fallar en el futuro:**
1. Habilitar debug: `UPDATE mdl_config SET value='32767' WHERE name='debug'`
2. Acceder a `https://conectatech.co/theme/styles.php/boost_union/[themerev]/all`
3. El error aparece en el log PHP-FPM o en la respuesta
4. Deshabilitar debug: `UPDATE mdl_config SET value='0' WHERE name='debug'`

**NOTA:** Si aparece un nuevo `Undefined variable $xxx`, agregar `$xxx: [valor] !default;` al `scsspre` y al snippet del repo. Esta es la solución estable hasta que Boost Union publique una versión v5.2.

---

## 2. Funcionalidades completadas vs. pendientes

### Completadas ✅

- [x] Pipeline Markdown → Moodle (secciones, subsecciones, HTML semántico, GIFT, ensayo)
- [x] Árbol curricular — editor UI + deploy en Moodle
- [x] Matrículas masivas (CSV → usuarios → matrícula → grupos)
- [x] Activos y visor de PDF (biblioteca + embed en Moodle como mod_label)
- [x] Organizaciones (CRUD + cascada)
- [x] Sistema de pines (ciclo de vida completo: disponible → activo → usado)
- [x] Portal del gestor (dashboard, grupos, pines)
- [x] Activación pública de estudiantes (página `/activar`)
- [x] CDN en S3 + CloudFront para assets y PDFs
- [x] API pública `api.conectatech.co` (Lambda, CRUD del índice de PDFs)
- [x] SSL en todos los subdominios (Let's Encrypt, renovación automática)
- [x] SPA routing (rewrite rules Apache para deep-link en `/arboles`, etc.)
- [x] Fix: truncar `question.name` a 255 chars para evitar corte silencioso del pipeline
- [x] Fix: `finalizeCourse()` usa `MAX(section)` — todas las secciones H1 visibles
- [x] Fix: reemplazar `fwrite(STDERR,...)` por `error_log()` en MoodleContentBuilder
- [x] Fix: `$DB->force_transaction_rollback()` defensivo en `processSubsection` catch
- [x] Preguntas ensayo: soporte `variante: texto` (editor) y `variante: adjunto`
- [x] Sistema de documentación (PRD.md, tech-specs.md, CLAUDE.md completo, MEMORY.md, TODO.md)

### Pendientes ⏳

- [ ] **Sección 0 de cursos finales** — UI en editor de árboles para definir portada/bienvenida por curso final (ver ADR-005)
- [ ] Reportes de progreso de estudiantes (completitud, calificaciones)
- [ ] Tipos de pregunta GIFT adicionales (verdadero/falso, emparejamiento, respuesta corta, numérica)
- [ ] Notificaciones por correo (SES)
- [ ] Renovación/reutilización de pines usados
- [ ] Importación masiva de PDFs

---

## 3. Registro de Decisiones de Arquitectura (ADR)

### ADR-001 — Moodle como LMS base
- **Fecha:** Inicio del proyecto (2025)
- **Estado:** Implementado
- **Decisión:** Usar Moodle como plataforma educativa base en lugar de construir un LMS propio
- **Razón:** Ecosistema educativo colombiano conoce Moodle; soporte nativo de GIFT, cuestionarios, roles, matrículas; costo de build de LMS propio es prohibitivo
- **Consecuencias:** El pipeline de contenido (Markdown → Moodle) es complejo porque debe usar las APIs internas de Moodle. Los bugs de Moodle (transacciones delegadas, `force_rollback`) afectan directamente al pipeline. La API REST debe estar en el mismo dominio que Moodle por la cookie de sesión.

### ADR-002 — API REST bajo `conectatech.co/admin-api` (no bajo subdominio admin)
- **Fecha:** 2025, durante implementación de auth
- **Estado:** Implementado
- **Decisión:** Exponer la API en `conectatech.co/admin-api/*` mediante un Alias Apache en el VirtualHost de Moodle, en lugar de `admin.conectatech.co/api/`
- **Razón:** La cookie `MoodleSession` se establece con `domain=conectatech.co`. Si la API estuviera en `admin.conectatech.co`, el browser no enviaría la cookie y la autenticación Moodle fallaría (política same-origin de cookies)
- **Consecuencias:** El VirtualHost de Moodle (`moodle-le-ssl.conf`) tiene un Alias que apunta a `/var/www/html/admin/api/`. Cualquier cambio de dominio requiere revisar esta configuración. El frontend Angular envía `withCredentials: true` vía interceptor.

### ADR-003 — Username del estudiante = número de cédula
- **Fecha:** 2025
- **Estado:** Implementado
- **Decisión:** Usar el número de cédula colombiana como `username` en Moodle
- **Razón:** Identificación única nacional, disponible para todos los estudiantes colombianos, evita duplicados, facilita soporte ("¿cuál es tu cédula?")
- **Consecuencias:** Rango válido: 1.000.000–1.999.999.999. Cédulas fuera de rango se rechazan en el flujo de activación. Estudiantes extranjeros no pueden usar el sistema fácilmente.

### ADR-004 — Árbol curricular guardado en JSON en el servidor (no en BD)
- **Fecha:** 2025
- **Estado:** Implementado
- **Consecuencias conocidas (gotcha):** Los JSONs están en `backend/data/arboles/` en el servidor. **No están en el repositorio git.** Un restore del servidor sin el directorio `data/` pierde los árboles. Hacer backup de ese directorio periódicamente.
- **Razón:** Simplicidad de implementación inicial; evitar crear tablas propias complejas para estructuras jerárquicas
- **Alternativas consideradas:** Tabla `ct_arboles` con JSON column, o tabla normalizada por nodos

### ADR-005 — Sección 0 de cursos finales no implementada (deuda técnica intencional)
- **Fecha:** 2026-03-15 (identificada como pendiente)
- **Estado:** Pendiente de diseño
- **Decisión:** Al desplegar un árbol curricular, los cursos finales se crean vacíos (sin sección 0 de portada)
- **Razón:** Al momento del diseño del árbol curricular, no se definió la UI para capturar el contenido de la sección 0 por cada curso final. Los cursos repositorio no lo necesitan (no tienen estudiantes)
- **Consecuencias:** Los cursos finales en Moodle no tienen portada/bienvenida. **Próxima tarea prioritaria.**

### ADR-006 — No hay entorno de staging
- **Fecha:** Inicio del proyecto
- **Estado:** Implementado (decisión consciente)
- **Decisión:** Un solo entorno de producción; sin staging ni CI/CD
- **Razón:** Proyecto unipersonal; complejidad de mantener dos entornos con Moodle (BD separada, servidor separado) es desproporcionada
- **Consecuencias:** Los cambios se prueban localmente (PHP en servidor de dev local o directamente en EC2) antes del deploy manual via rsync. Mayor riesgo de regresión en producción.

### ADR-007 — Transacciones delegadas de Moodle y `force_rollback`
- **Fecha:** 2026-04-14 (descubierto depurando el pipeline)
- **Estado:** Fix implementado
- **Decisión:** Truncar `question.name` a 255 chars y llamar `$DB->force_transaction_rollback()` en catch blocks del pipeline
- **Razón:** Un error de BD (p.ej. VARCHAR overflow) dentro de `save_question()` activa `$DB->force_rollback = true` en la transacción delegada, bloqueando TODAS las operaciones de BD siguientes de forma silenciosa. El pipeline reportaba "ok" pero no creaba las secciones subsiguientes.
- **Consecuencias:** El pipeline es ahora defensivo: trunca nombres, limpia el estado de transacción roto. Cualquier nueva operación que use transacciones delegadas de Moodle debe incluir un catch con `$DB->force_transaction_rollback()`.

### ADR-009 — Upgrade de Moodle: directorio limpio, no overlay
- **Fecha:** 2026-04-27
- **Estado:** Implementado
- **Decisión:** Para upgrades de versión mayor de Moodle, usar el método de directorio limpio: renombrar el directorio actual a `moodle_old`, instalar la nueva versión en un directorio vacío, copiar solo `config.php` manualmente.
- **Razón:** El método de `cp -a` (overlay) deja archivos huérfanos de la versión anterior en el directorio. Moodle detecta la mezcla de versiones y aborta el upgrade. Además, archivos removidos en la nueva versión (como `qtype_random`) pueden causar errores de inicialización (Moodle 5.2 llama `debugging()` sobre `qtype_random` antes de que `moodlelib.php` esté cargado).
- **Consecuencias:** El proceso de upgrade requiere más pasos pero es confiable. El directorio `moodle_old` puede eliminarse una semana después de confirmar que todo funciona.

### ADR-010 — Boost Union SCSS y compatibilidad con Moodle 5.2
- **Fecha:** 2026-04-27
- **Estado:** Implementado (workaround hasta versión v5.2 de Boost Union)
- **Decisión:** Añadir al campo `scsspre` de Boost Union las variables Bootstrap que Moodle 5.2 necesita antes de que Bootstrap las defina, usando `!default` para no interferir con Bootstrap.
- **Razón:** Moodle 5.2 cambió la arquitectura de compilación SCSS — ahora llama callbacks de padre E hijo automáticamente. Boost Union v5.1 fue diseñado asumiendo que solo se llamaría el callback del hijo, causando que variables de Bootstrap (`$white`, `$black`) y una variable propia no definida (`$logincontainer-shadow`) sean usadas antes de existir. La compilación falla silenciosamente y Moodle sirve el CSS precompilado del tema (sin estilos personalizados).
- **Variables a añadir (documentadas en `snippets/conectatech-pre.scss`):** `$white: #fff !default`, `$black: #000 !default`, `$logincontainer-shadow: none !default`.
- **Consecuencias:** Si en el futuro se instala una versión de Boost Union compatible con Moodle 5.2, estas definiciones son inofensivas por el `!default`. Si aparecen nuevos errores del mismo tipo, agregar la variable faltante a `scsspre` y al snippet del repo.

### ADR-008 — Git flow: hotfixes directos a main, feature branches para lo demás
- **Fecha:** 2026-04-14
- **Estado:** Implementado en CLAUDE.md
- **Decisión:** No hay rama `develop`. Los hotfixes urgentes van directamente a `main` con PR inmediato. Toda nueva funcionalidad usa branch `feature/`, `fix/`, `docs/`, o `refactor/` desde `main`.
- **Razón:** Proyecto unipersonal; rama develop agrega overhead sin beneficio real con un solo developer/agente
- **Consecuencias:** `main` es siempre la rama de referencia para crear branches. El historial de `main` refleja directamente el estado de producción.

---

## 4. Dependencias principales (versiones exactas)

### Frontend Angular

| Paquete | Versión |
|---|---|
| `@angular/*` | `^21.2.2` |
| `primeng` | `^21.1.3` |
| `@primeuix/themes` | `^2.0.3` |
| `primeicons` | `^7.0.0` |
| `tailwindcss` | `^4.2.1` |
| `rxjs` | `~7.8.0` |
| `zone.js` | `~0.15.0` |
| `typescript` | `~5.9.2` |

### Backend PHP

Sin gestor de dependencias (Composer). Las librerías son clases PHP propias. Usa la API interna de Moodle (cargada vía `bootstrap.php` o `MoodleBootstrap.php`).

### Lambda (api-service)

| Paquete | Versión |
|---|---|
| Node.js runtime | 22 (arm64) |
| AWS SDK | Incluido en runtime de Lambda |

---

## 5. Configuraciones vigentes

| Configuración | Valor |
|---|---|
| Route 53 zona `conectatech.co` | `Z0767805255ZNR9CRNWLH` |
| IP elástica EC2 | `54.86.113.27` |
| Security Group SSH | `sg-039bcb1cb3a57db7f` |
| S3 bucket assets | `assets.conectatech.co` |
| CloudFront distribution | `E2KULI3BS0YJDX` |
| CloudFront CNAME | `dvlgey5i48r61.cloudfront.net` |
| CloudFront Response Headers Policy | `65f8d048-1426-492f-9acd-ccc02c4b1151` |
| API Gateway ID | `7xv6etd54i` |
| API Gateway dominio custom | `d-j9xoul7vac.execute-api.us-east-1.amazonaws.com` |
| Lambda function | `conectatech-api-pdfs` (arm64, Node 22, 256 MB) |
| IAM Role Lambda | `conectatech-api-lambda-role` |
| ACM Cert `api.conectatech.co` | `arn:aws:acm:us-east-1:648232846223:certificate/a47014a1-092e-4e1a-a2eb-406a3a9f642c` |
| Moodle `maxsections` | 250 (configurado en Moodle admin) |
| SSL Let's Encrypt | Válido hasta 2026-05-31; renovación automática con certbot |
| Índice PDFs en S3 | `s3://assets.conectatech.co/recursos/pdf/index.json` |
| Visor PDF CDN | `assets.conectatech.co/herramientas/visor-pdf/` |

---

## 6. Patrones de código establecidos

### PHP — bootstrap de la API

```php
// api/index.php — siempre al inicio
ob_start();  // captura cualquier HTML de Moodle
require_once API_DIR . '/bootstrap.php';  // inicializa Moodle
require_once API_DIR . '/auth.php';

// Al final, antes de JSON
ob_end_clean();
echo json_encode($result);
```

### PHP — verificación de auth en handlers

```php
// handlers/cualquier-handler.php
require_once API_DIR . '/auth.php';
verificarAdmin();  // primera línea del handler, antes de cualquier lógica
```

### PHP — operaciones de BD Moodle (siempre parámetros preparados)

```php
global $DB;
$record = $DB->get_record('course', ['shortname' => $shortname], '*', MUST_EXIST);
$rows   = $DB->get_records_sql('SELECT * FROM {course_sections} WHERE course = ?', [$courseId]);
```

### PHP — logging en pipeline (web y CLI)

```php
error_log("ERROR en sección '{$title}': " . $e->getMessage());
// Nunca: fwrite(STDERR, ...) — falla en contexto web
```

### Angular — componente standalone con signal

```typescript
@Component({ standalone: true, changeDetection: ChangeDetectionStrategy.OnPush })
export class MiComponente {
  readonly items = signal<Item[]>([]);
  readonly loading = signal(false);
}
```

### Angular — petición HTTP con credenciales

```typescript
// El interceptor credentials.interceptor.ts añade withCredentials: true automáticamente
// No es necesario especificarlo en cada petición
this.http.post('/admin-api/endpoint', payload).subscribe(...)
```

### rsync deploy (patrón ownership apache ↔ ec2-user)

```bash
# Si el servidor tiene archivos en propiedad de apache y rsync da Permission denied:
ssh -i ~/.ssh/ClaveIM.pem ec2-user@54.86.113.27 \
  "sudo chown -R ec2-user:ec2-user /var/www/html/admin"

rsync -e "ssh -i ~/.ssh/ClaveIM.pem" -a [--delete] [--exclude=...] \
  origen/ ec2-user@54.86.113.27:/var/www/html/admin/destino/

ssh -i ~/.ssh/ClaveIM.pem ec2-user@54.86.113.27 \
  "sudo chown -R apache:apache /var/www/html/admin && sudo chmod -R 755 /var/www/html/admin"
```

---

## 7. Gotchas conocidos

| Situación | Causa | Solución |
|---|---|---|
| Pipeline crea solo algunas secciones y reporta "ok" | Error de BD (p.ej. nombre >255 chars) dentro de transacción delegada de Moodle activa `force_rollback = true`, bloqueando todas las operaciones siguientes | Verificar que `question.name` se trunca a 255 chars; revisar `error_log` del servidor; el `processSubsection` catch llama `$DB->force_transaction_rollback()` |
| No todas las secciones H1 son visibles en Moodle | `numsections` no coincide con MAX(section) — las subsecciones delegadas ocupan slots de número de sección | `finalizeCourse()` usa `MAX(section)` de `course_sections`, no count de H1 |
| `Undefined constant "STDERR"` en la API | `fwrite(STDERR, ...)` solo funciona en CLI | Reemplazar siempre por `error_log()` |
| rsync da "Permission denied" | Los archivos del servidor pertenecen al usuario `apache`, no a `ec2-user` | `sudo chown -R ec2-user:ec2-user /var/www/html/admin` → rsync → restaurar apache |
| Deploy de frontend borra la API del servidor | `rsync --delete` sin `--exclude=api --exclude=backend` | Siempre incluir ambos exclude al sincronizar el frontend |
| SSH da timeout | La IP del cliente cambió; el Security Group SSH solo permite la IP fija | Actualizar SG: revocar IP vieja, autorizar nueva con `aws ec2 revoke/authorize-security-group-ingress --profile im` |
| Deep-link (ej. `/arboles`) da 404 | Las RewriteRules del SPA no están en el VirtualHost SSL | Verificar `<Directory>` del VirtualHost SSL en `admin.conectatech.co.conf`; recargar Apache |
| Moodle redirige al instalar en DocumentRoot | Moodle 5.1+ usa `moodle/public` como DocumentRoot, no `moodle/` | `DocumentRoot /var/www/html/moodle/public` en el VirtualHost |
| La API de Moodle imprime HTML en la respuesta JSON | Moodle puede imprimir HTML en algunos flujos de inicialización | `ob_start()` al inicio de `index.php`; `ob_end_clean()` antes del JSON |
| Los árboles curriculares se pierden en restore del servidor | Los JSONs de `backend/data/arboles/` no están en git | Hacer backup manual del directorio `data/` antes de operaciones de riesgo |
| **Upgrade Moodle con `cp -a` overlay causa "mixed versions"** | `cp -a` no borra archivos del directorio destino que no existen en el origen — quedan archivos de la versión anterior | Renombrar directorio a `moodle_old`, crear directorio nuevo limpio desde el archive, copiar solo `config.php` |
| **`qtype_random` huérfano causa `Undefined function core\debugging()`** | Moodle 5.2 eliminó `qtype_random` pero al hacer upgrade con `cp -a`, el directorio sobrevive. Moodle 5.2 llama `debugging()` antes de que `moodlelib.php` cargue | `sudo rm -rf /var/www/html/moodle/public/question/type/random/` antes de correr `upgrade.php` |
| **GitHub archive de Moodle NO incluye `vendor/`** | El `.tar.gz` de `github.com/moodle/moodle/archive/v5.2.0.tar.gz` es solo el código fuente, sin dependencias Composer | Correr `composer install --no-dev --optimize-autoloader` en el directorio extraído antes del swap |
| **Boost Union SCSS falla silenciosamente en Moodle 5.2** | Moodle 5.2 llama doble callback (padre + hijo), Boost Union v5.1 usa `$white`/`$logincontainer-shadow` antes de que existan | Añadir variables faltantes al `scsspre` con `!default`. Ver sección 1d. |
| **`mysql -u root` falla en EC2** | La BD es RDS, no hay MySQL local | `mysql -h [rds-endpoint] -u moodleadmin -p[pass] moodle -e "..."` |
| **CSS de Boost Union mide 1.9 MB → estilos propios NO se aplican** | La compilación SCSS falló y Moodle sirve el CSS precompilado de fallback del tema | Ver sección 1d para diagnóstico y fix |
| **`sudo -u apache php /tmp/script.php` falla con exit 255 via SSH** | El archivo en `/tmp/` pertenece a `ec2-user` y apache no puede leerlo, o la conexión SSH se corta | Copiar el script con `rsync` y darle permisos antes de ejecutar, o usar `mysql` CLI directamente |

---

## 8. Documentos de referencia

| Archivo | Propósito | Actualizar cuando |
|---|---|---|
| `CLAUDE.md` | Instrucciones permanentes para IA | Nueva convención, tecnología o regla de seguridad |
| `PRD.md` | Requisitos de producto y roadmap | Se completa una feature o cambia el roadmap |
| `tech-specs.md` | Arquitectura técnica y endpoints | Nuevo endpoint, dependencia o cambio de infraestructura |
| `MEMORY.md` (este) | Estado y ADRs | Al cerrar cada sesión relevante |
| `TODO.md` | Motor JIT: 2 tareas atómicas | Al completar cualquiera de las dos tareas activas |
| `docs/instrucciones-inicio.md` | Protocolo de documentación con IA | Al refinar el proceso de documentación |
| `docs/infraestructura-servidor.md` | Detalles del servidor EC2 y Apache | Al cambiar la configuración del servidor |
| `docs/infraestructura-cdn.md` | CDN, S3, CloudFront, Lambda | Al cambiar la infraestructura de assets |

---

## 9. Contexto de la última sesión

**Fecha:** 2026-04-27

**Qué se hizo:**

1. **Upgrade Moodle 5.1.3 → 5.2 (Build: 20260420)**
   - Plugin `local_conectatech` desinstalado del servidor con `uninstall_plugins.php --purge-missing` (nunca estuvo en el repo)
   - Backup BD: `/tmp/moodle-backup-20260427-2333.sql` (34 MB en servidor EC2)
   - Backup archivos Moodle 5.1.3: `/tmp/moodle-511-backup.tar.gz` (76 MB)
   - Directorio `moodle_old`: `/var/www/html/moodle_old/` (puede eliminarse ya)
   - Moodle 5.2 descargado de GitHub, `composer install --no-dev` para vendor/
   - Errores encontrados y resueltos: overlay "mixed versions", `qtype_random` huérfano
   - `upgrade.php` completó 830+ plugins sin errores en ~5 min
   - Tablas `mdl_ct_*` y rol `ct_gestor` (22 capabilities) intactos

2. **Boost Union actualizado e instalado en Moodle 5.2**
   - Boost Union copiado de `moodle_old` a nuevo Moodle, registrado con `upgrade.php`
   - Actualizado de v5.1-r8 a v5.1-r10 (la última versión disponible)
   - Tema activo: confirmado en BD (`theme = 'boost_union'`)

3. **Fix SCSS Boost Union + Moodle 5.2**
   - Problema: CSS compilado medía 1.9 MB (fallback precompilado sin estilos propios)
   - Diagnóstico: Moodle 5.2 cambia el pipeline de callbacks SCSS → variables `$white`, `$black`, `$logincontainer-shadow` usadas antes de definirse → excepción silenciosa → fallback
   - Fix: `$white: #fff !default`, `$black: #000 !default`, `$logincontainer-shadow: none !default` añadidos al `scsspre` en BD y a `snippets/conectatech-pre.scss` en repo
   - CSS compilado: 1.27 MB, con todos los estilos propios (coral, midnight, Montserrat, etc.)
   - PRs: #7 (docs Moodle 5.2), #8 (fix SCSS compat)

**Próxima tarea sugerida:** Ver TODO.md — Tarea 1: Sección 0 de cursos finales.
