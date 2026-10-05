# Moodle — Runbook de actualización y registro histórico

> Documento vivo. Contiene el procedimiento estándar reutilizable para actualizar Moodle core y las
> extensiones locales (temas, plugins `local_*`), más un registro histórico de cada actualización
> realizada, con sus gotchas y decisiones. Actualizar la sección de contexto y el registro después de
> cada operación — no crear un archivo nuevo por actualización.

---

## Cómo usar este documento

1. Antes de actualizar, releer **Gotchas y advertencias acumuladas** — casi todos los problemas ya
   ocurrieron una vez y están documentados ahí.
2. Ejecutar el **Procedimiento estándar** (Moodle core) o el de **extensiones locales**, según aplique.
3. Al terminar (o si algo falla), añadir una entrada nueva en **Registro de actualizaciones** con fecha,
   versión origen/destino, incidencias y resultado.
4. Si apareció un gotcha nuevo, añadirlo a la sección de advertencias para la próxima vez.

---

## Contexto del servidor (referencia reutilizable)

| Parámetro | Valor |
|---|---|
| IP EC2 | `54.86.113.27` |
| SSH | `command ssh -i ~/.ssh/ClaveCT.pem ec2-user@54.86.113.27` |
| Raíz Moodle | `/var/www/html/moodle/` |
| DocumentRoot Apache | `/var/www/html/moodle/public/` |
| `config.php` | `/var/www/html/moodle/config.php` (NO en `public/`) |
| Scripts CLI | `/var/www/html/moodle/admin/cli/` (NO en `public/admin/cli/`) |
| Moodledata | `/moodledata/` |
| Usuario PHP-FPM | `apache` |
| Temas custom instalados | `boost_union` (no viene en el tarball oficial). `moove` se desinstaló el 2026-10-05 |
| Plugins locales custom | `local_usagereports` (repo propio, no viene en el tarball oficial) |
| Security Group SSH | `sg-039bcb1cb3a57db7f` (solo IP autorizada — ver advertencias) |
| Backup DB manual | `mysqldump` a `/var/backups/moodle/database/` (no existe script `/usr/local/bin/moodle-backup-db.sh`) |
| Base de datos | RDS `conectatech-prod-db-20260217043350287600000001` (MariaDB, db.t4g.micro, Single-AZ) |
| Parameter group RDS | `conectatech-prod-mariadb-20260217032230294200000001` (familia `mariadb10.11`) |
| Directorio de staging | `~/moodle-staging/` (en disco). **No usar `/tmp`** — es tmpfs (RAM), ver advertencias |

> **CRÍTICO:** Usar siempre `command ssh` (no `ssh` a secas) — el shell local tiene un hook que envuelve
> SSH y rompe los subshells de rsync/pipes.

**Última versión conocida de Moodle core en producción:** ver última fila de
[Registro de actualizaciones](#registro-de-actualizaciones-moodle-core).

---

## Plan vigente — Upgrade 5.2.3 → 5.3 LTS

> **Estado (2026-10-05, noche):** Fase 0 y Fase 1 ✅ hechas. **Siguiente: Fases 2 y 4 a partir del
> 2026-10-06** (migración de MariaDB + ensayo del upgrade en un clon). El upgrade de producción a 5.3
> sigue **bloqueado** hasta que Boost Union publique su versión para 5.3 (Fase 3). Las decisiones sobre
> cambios de 5.3 se toman en paralelo mientras tanto
> ([Decisiones pendientes](#decisiones-pendientes-por-cambios-de-53)).

| Fase | Qué | Estado |
|---|---|---|
| 0 | Validación de requisitos + tarball 5.3 en staging | ✅ 2026-10-05 |
| 1 | Higiene previa sobre 5.2.3 | ✅ 2026-10-05 |
| 2 | MariaDB 10.11.16 → 11.4.13 (ensayo en clon + producción) | ⏳ desde 2026-10-06 |
| 4 | Ensayo del upgrade a 5.3 sobre el mismo clon (con Boost Union `upgrade-503`) | ⏳ desde 2026-10-06 |
| — | Decisiones sobre cambios de 5.3 | ⏳ en paralelo, con insumos del ensayo |
| 3 | Esperar release de Boost Union para 5.3 (+ idealmente 5.3.1) | 🔒 bloqueo externo |
| 5 | Upgrade de producción a 5.3 | 🔒 depende de 3 |

### Versión objetivo

| Dato | Valor |
|---|---|
| Release | Moodle 5.3 LTS — `5.3 (Build: 20261005)`, `$version = 2026100500.00`, branch `503` |
| URL exacta | `https://packaging.moodle.org/stable503/moodle-5.3.tgz` |
| SHA256 | `7e5edf110555956571f40e42acffde0eb23681ebebd212a2fec0fe7d795dd511` |
| Pre-descargado | `~/moodle-staging/moodle-5.3.tgz` en el EC2 (checksum verificado 2026-10-05, sin extraer) |
| Notas oficiales | https://moodledev.io/general/releases/5.3 y `UPGRADING.md` dentro del tarball |

Si cuando se desbloquee ya salió 5.3.1 o posterior, usar esa versión (mismo patrón de URL) y
recalcular el checksum. Es preferible no estrenar el `.0` de una rama en producción.

### Validación de requisitos (2026-10-05, contra `public/admin/environment.xml` de 5.3)

| Requisito 5.3 | Producción | Estado |
|---|---|---|
| Upgrade desde ≥ 4.4 | 5.2.3 | ✅ |
| PHP ≥ 8.3.0, 64 bits (máx. 8.4.x) | PHP 8.3.29, `PHP_INT_SIZE=8` | ✅ |
| Extensiones requeridas (incl. `sodium`, `intl`, `zip`, `gd`…) | Todas presentes | ✅ |
| `max_input_vars` ≥ 5000 | 5000 (php.ini y pool FPM) | ✅ |
| `memory_limit` ≥ 96M | 128M | ✅ |
| **MariaDB ≥ 11.4.0** | **RDS en 10.11.16** | ❌ **Bloqueante 1** → Fase 2 |
| Tablas InnoDB + `ROW_FORMAT` Dynamic/Compressed | 0 tablas fuera de norma | ✅ |
| Espacio en disco | 9,3 GB libres en `/` (tras borrar `moodle-old`) | ✅ |
| `theme_boost_union` compatible 5.3 | `v5.2-r10`, `supported = [502, 502]` | ❌ **Bloqueante 2** → Fase 3 |
| `theme_moove` compatible 5.3 | **Desinstalado** el 2026-10-05 | ✅ (resuelto en Fase 1) |
| `local_usagereports` | Report Builder: ya pasa alias a `set_main_table()`, columnas con `set_is_sortable` explícito | ✅ |
| Código propio (`admin-module/backend/lib`, scripts CLI) | APIs usadas (`create_course`, `course_update_section`, `rebuild_course_cache`, `enrol_get_plugin`, `role_assign`, `quiz_add_quiz_question`, `quiz_settings`, `question_bank::get_qtype`, `context_coursecat`, `core_component::get_component_directory`) sin deprecaciones en 5.3 | ✅ |
| Tema Classic (eliminado en 5.3) | No se usa: 0 cursos/categorías/cohortes/usuarios con tema forzado, `$CFG->theme` no forzado en `config.php` | ✅ |

**Por qué Boost Union bloquea:** Moodle no ejecuta `upgrade.php` si un plugin instalado declara un
`$plugin->supported` sin la rama destino. Además, 5.3 trae cambios fuertes en Boost (barra de
navegación primaria renderizada en React, Noto Sans por defecto, variables SCSS que ahora apuntan a
custom properties por el modo oscuro, nuevo bloque `drawercontrols`) que afectan a cualquier tema hijo.
No basta con "forzar" la versión.

Seguimiento de Boost Union: issue
[#1417 "Moodle 5.3 upgrade"](https://github.com/moodle-an-hochschulen/moodle-theme_boost_union/issues/1417)
(abierto 2026-10-05), desarrollo en la rama `upgrade-503`. Al 2026-10-05 esa rama solo tiene un commit
("Prepare compatibility for Moodle 5.3") que declara `supported = [503, 503]` y
`requires = 2026100500`. **Sirve para el ensayo, nunca para producción.**

```bash
# Revisión rápida del estado de Boost Union
gh api repos/moodle-an-hochschulen/moodle-theme_boost_union/branches -q '.[].name' | grep 503
gh api repos/moodle-an-hochschulen/moodle-theme_boost_union/tags -q '.[0:3][].name'
gh issue view 1417 -R moodle-an-hochschulen/moodle-theme_boost_union --json state -q .state
```

### Fase 0 — Validación y preparación (✅ 2026-10-05)
- Requisitos verificados (tabla anterior), tarball 5.3 descargado y verificado en `~/moodle-staging/`.
- Código propio y `local_usagereports` revisados contra `UPGRADING.md` de 5.3: sin cambios necesarios.

### Fase 1 — Higiene previa (✅ 2026-10-05)

| # | Acción | Resultado |
|---|---|---|
| 1 | Backup de BD | `/var/backups/moodle/database/moodle-pre-higiene53-20261005-2317.sql.gz` (7,9 MB, dump completo) |
| 2 | Retención de backups automáticos de RDS 1 → 7 días | Aplicado de inmediato, sin reinicio |
| 3 | Desinstalar `theme_moove` (instalado pero no activo) | ✅ `uninstall_plugins.php` OK, directorio borrado, 0 filas en `config_plugins` y `files`. Respaldo previo en `/var/backups/moodle/moove-uninstall-20261005/`: 50 ajustes (`config_plugins.json`), 4 archivos (favicon, logos color/blanco y preset `conectatech.scss`) con su `files.json`, y el código (`theme_moove-code.tgz`) |
| 4 | Boost Union `v5.2-r9 → v5.2-r10` | ✅ Cambio único: adopta MDL-88948 (alineación de íconos del índice del curso), que ya está en 5.2.3 (fusionado en `MOODLE_502_STABLE` el 2026-08-24). r9 no tenía cambios locales (diff limpio). Respaldo de r9: `~/moodle-staging/boost_union-v5.2-r9-backup.tgz` |
| 5 | Borrar `/var/www/html/moodle-old` (5.2.1+) | ✅ Antes se comprobó: `config.php` idéntico al de producción, `local_usagereports` idéntico. Liberó 577 MB |
| 6 | Cuenta AWS en Paid Plan | ✅ Confirmado por el usuario |

Verificación tras la Fase 1: `upgrade.php --is-pending` sin pendientes; HTTP 200 en `conectatech.co`,
`/login` y `admin.conectatech.co`; CSS de Boost Union compila (1,3 MB) e incluye las clases propias
del Raw SCSS (`.caratula-libro`, `.quien-escribio-este-libro`, `.resaltado`…), ADR-009 sin regresión;
revisión visual de la portada OK y sin errores en la consola.

Para reinstalar Moove si algún día hiciera falta: descomprimir `theme_moove-code.tgz` en
`public/theme/`, ejecutar `upgrade.php` y recargar los ajustes y archivos desde los JSON del respaldo.

**Deriva menor detectada:** `local_usagereports` en producción difiere del repo solo en el encabezado
`@copyright` (cambiado en el PR #31) y en el README. Sin diferencias funcionales. En el upgrade a 5.3
copiar el plugin **desde el repo** para que quede alineado.

### Fase 2 + Fase 4 — Guion de trabajo (desde 2026-10-06)

Se usa **un solo clon** de la BD para las dos cosas: primero se ensaya la migración a MariaDB 11.4 y
luego, sobre ese mismo clon ya migrado, se ensaya el upgrade de Moodle a 5.3. Producción no se toca
hasta el bloque C.

**Bloque A — Preparación (sin impacto)**
1. Paso 1 del procedimiento estándar (IP / Security Group).
2. Crear el parameter group `mariadb11.4` (paso 1 del
   [procedimiento de RDS](#procedimiento--upgrade-mayor-de-mariadb-en-rds)).
3. Restaurar el último snapshot automático a `conectatech-ensayo-mariadb` (paso 2 del procedimiento de RDS).

**Bloque B — Ensayo sobre el clon (sin impacto)**
1. Upgrade del clon a 11.4.13, midiendo el tiempo hasta `available`. Validaciones del paso 2 del
   procedimiento de RDS (cliente `mysql`, `mysqldump`, conexión PHP/mysqli).
2. **Moodle 5.2.3 contra el clon 11.4** (valida la Fase 2 de punta a punta). En el EC2:
   ```bash
   sudo mkdir -p /srv/ensayo && sudo cp -a /var/www/html/moodle /srv/ensayo/moodle-52 \
     && sudo cp -a /moodledata /srv/ensayo/moodledata && sudo chown -R apache:apache /srv/ensayo
   ```
   En `/srv/ensayo/moodle-52/config.php`:
   - `$CFG->dbhost` = endpoint del clon
   - `$CFG->dataroot` = `/srv/ensayo/moodledata`
   - `$CFG->wwwroot` = `http://localhost:8053`
   - añadir `$CFG->noemailever = true;` **(obligatorio, para que el ensayo nunca envíe correos reales)**

   **Antes de ejecutar nada, comprobar con `grep -E "dbhost|dataroot|wwwroot|noemailever"` que el
   `config.php` del ensayo NO apunta a la BD ni al moodledata de producción.** No correr el cron en el
   ensayo.

   Pruebas: `sudo -u apache php /srv/ensayo/moodle-52/admin/cli/checks.php` y
   `sudo -u apache php /srv/ensayo/moodle-52/admin/cli/upgrade.php --is-pending` (debe decir que no hay
   nada pendiente).

   Se usa `/srv/ensayo` (y no el home de `ec2-user`) para que `apache` pueda leerlo sin abrir permisos
   del home. Disco suficiente: ~800 MB entre código y moodledata.
3. **Upgrade de la copia a 5.3** (Fase 4):
   ```bash
   cd /srv/ensayo && sudo tar -xzf ~/moodle-staging/moodle-5.3.tgz && sudo mv moodle moodle-53
   sudo cp moodle-52/config.php moodle-53/config.php
   # Boost Union de desarrollo (SOLO ensayo)
   curl -sSL https://github.com/moodle-an-hochschulen/moodle-theme_boost_union/archive/refs/heads/upgrade-503.tar.gz \
     | sudo tar -xz && sudo mv moodle-theme_boost_union-upgrade-503 moodle-53/public/theme/boost_union
   # local_usagereports desde el repo (rsync desde la máquina local) o desde moodle-52
   sudo cp -a moodle-52/public/local/usagereports moodle-53/public/local/
   sudo chown -R apache:apache /srv/ensayo
   time sudo -u apache php moodle-53/admin/cli/upgrade.php --non-interactive 2>&1 | sudo tee /srv/ensayo/upgrade-53.log
   ```
   Lanzarlo con `nohup` si la conexión SSH está inestable (ver advertencias).
4. Insumos para las decisiones (anotarlos en este documento):
   - duración del `upgrade.php` y errores o advertencias del log;
   - `sudo -u apache php moodle-53/admin/cli/cfg.php --component=message --name=message_provider_moodle_newlogin_enabled`
     (hoy: `popup,airnotifier`, ver decisión D1);
   - ajustes de Boost/Boost Union que el upgrade haya creado o cambiado (comparar
     `cfg.php --component=theme_boost --json` y `--component=theme_boost_union --json` antes y después).
5. Opcional, revisión visual del ensayo sin publicar nada: servirlo con el servidor embebido de PHP en
   el EC2 y verlo por túnel SSH.
   ```bash
   # En el EC2 (como apache):
   sudo -u apache php -S 127.0.0.1:8053 -t /srv/ensayo/moodle-53/public
   # En la máquina local:
   command ssh -i ~/.ssh/ClaveCT.pem -N -L 8053:127.0.0.1:8053 ec2-user@54.86.113.27
   # Navegar a http://localhost:8053
   ```
   Aún no se ha probado con Moodle 5.x (que enruta parte de sus URLs por `r.php`). Si no funciona, no
   insistir: el objetivo principal del ensayo es el upgrade por CLI.
6. Limpieza del ensayo: borrar `/srv/ensayo` y la instancia
   `conectatech-ensayo-mariadb` (`--skip-final-snapshot`). El clon cuesta mientras exista: no dejarlo
   de un día para otro sin necesidad.

**Bloque C — MariaDB en producción (ventana corta, con aviso)**
Solo si el bloque B salió limpio. Paso 3 del procedimiento de RDS: mantenimiento, `mysqldump` +
snapshot manual, `modify-db-instance`, verificación y fin del mantenimiento. Ventana estimada = tiempo
medido en B.1 + ~10 min. Evitar las 03:00–04:00 UTC (backup automático de RDS y cron de limpieza) y
la ventana de mantenimiento de RDS (domingo 04:00–05:00 UTC).

Registrar el resultado en la tabla de registro (nueva sección para BD) y actualizar el estado de esta
página.

### Decisiones pendientes por cambios de 5.3

Se deciden mientras se espera a Boost Union. El ensayo (B.4) aporta los datos que faltan.

| # | Cambio en 5.3 | Impacto en ConectaTech | Opciones / recomendación inicial |
|---|---|---|---|
| D1 | **Notificación de "nuevo inicio de sesión" pasa de *permitida* a *forzada*** (`lib/db/messages.php`: `newlogin` → `MESSAGE_FORCED` en popup, email y app; las notas de la versión dicen que se activa por defecto y avisa al admin si está desactivada) | Hoy en producción el correo está **desactivado** (`message_provider_moodle_newlogin_enabled = popup,airnotifier`). Si 5.3 activa el correo, cada uno de los ~150 usuarios recibiría un email por cada login desde un dispositivo nuevo (vía SES) | Ver en el ensayo qué valor queda. Recomendación: mantener solo popup/app, salvo que se quiera la alerta de seguridad por correo. Si se activa, avisar antes a los colegios para que no lo tomen por phishing |
| D2 | **Barra de navegación primaria en React** (`core/nav/PrimaryNav`, plantilla `core/primarymoremenu` reemplaza `core/moremenu`) | Boost Union sobreescribe la navbar; depende de cómo la porten. Riesgo para el Raw SCSS que toque la navegación | Revisión visual en el ensayo y en el release de Boost Union. Sin decisión de negocio; es validación |
| D3 | **Modo oscuro experimental** (`theme_boost \| enablecolourmodes`, apagado por defecto) | El Raw SCSS y los colores de marca están pensados para fondo claro | Recomendación: **dejarlo apagado** en 5.3 y reevaluarlo cuando salga de experimental |
| D4 | **Noto Sans como fuente por defecto de Boost** | Ninguno esperado: la marca usa **Inter** de forma explícita (`--bs-body-font-family: "Inter", …`) y los temas hijos que definen su fuente no se ven afectados | Solo confirmar en la revisión visual |
| D5 | **Índice del curso: un solo botón de colapsar/expandir** (desaparece el menú desplegable; el bloque `drawerheadercontent` pasa a ser `drawercontrols`) | Cambio visible para docentes y estudiantes | Informar a los colegios en la nota de cambios. Sin acción técnica |
| D6 | **Login movido a core** (`core/loginform`) | La página de login lleva marca propia | Validar en el ensayo/producción |
| D7 | **Directorio `theme/classic`** | Sigue en disco en 5.2.3 pero se elimina con el core 5.3; el upgrade migra o desinstala sus ajustes. No lo usamos | Nada que decidir; solo verificar que el upgrade lo desinstale sin errores |
| D9 | **Workaround SCSS de Boost Union (ADR-010 en `MEMORY.md`)**: variables `!default` (`$white`, `$black`, `$logincontainer-shadow`…) en `scsspre` | En 5.3 Boost usa `var(--bs-white)`/`var(--bs-black)` en vez de esas variables SCSS. Las definiciones `!default` deberían seguir siendo inofensivas, pero podrían aparecer variables nuevas sin definir | En el ensayo, purgar cachés y confirmar que el CSS compila con el Raw SCSS (buscar `.caratula-libro` en el CSS servido o en el log de compilación). Si ya no hace falta, retirar el workaround en otra tarea |
| D8 | **Comunicación a usuarios** | Cambios visibles (navbar, índice del curso, posibles correos de login) | Preparar una nota breve para colegios y Planeta Lector antes de la Fase 5 |

### Fase 3 — Esperar condiciones (bloqueo externo)
Se desbloquea cuando se cumplan **todas**:
- [ ] Boost Union publica release con `supported` que incluya `503` (rama `MOODLE_503_STABLE` o tag
      `v5.3-r1` en GitHub, o marketplace con "Supports … 5.3").
- [x] Moove desinstalado (2026-10-05).
- [ ] RDS en MariaDB ≥ 11.4 (Fase 2, bloque C).
- [ ] Ensayo de la Fase 4 sin errores bloqueantes.
- [ ] Decisiones D1–D9 tomadas.
- [ ] Recomendado: disponible 5.3.1 o posterior.

### Fase 5 — Upgrade en producción
Seguir el [Procedimiento estándar](#procedimiento-estándar--moodle-core) con `<VERSION_DESTINO>=5.3`
(o el punto vigente) y `<BRANCH>=503`, más estas precauciones específicas:
- Repetir el ensayo de la Fase 4 si entre el ensayo y la fecha real cambió la versión de Boost Union o
  de Moodle.
- Ventana fuera de clases y lejos de las 03:00 UTC.
- Hacer **snapshot manual de RDS** además del `mysqldump` (paso 4).
- En el paso 6, instalar el **release 5.3** de Boost Union (no la copia de `moodle-old`) y
  `local_usagereports` desde el repo.
- Aplicar las decisiones D1 y D3 **antes** de quitar el modo mantenimiento.
- Tras el upgrade, revisar *Administración del sitio → Notificaciones* por si quedan plugins con
  advertencias.

Pruebas post-upgrade adicionales (además de las del paso 10):
- [ ] Tema Boost Union: barra de navegación (ahora React), tipografía, colores de marca y Raw SCSS
      (ADR-009), en escritorio y móvil.
- [ ] Página de login (la plantilla `core/loginform` pasó de Boost a core).
- [ ] Índice del curso: botón único de colapsar/expandir.
- [ ] Cuestionario: intento + revisión (las opciones de revisión del PR #18 siguen aplicando).
- [ ] Activación de pines (`/admin-api/activar/*`) y matrícula desde el panel admin.
- [ ] `crear-cursos.php` / `procesar-markdown.php` sobre un curso de prueba (usan APIs de curso y quiz).
- [ ] Informe "Uso de la plataforma" y su envío programado.
- [ ] Iniciar sesión con un usuario de prueba y confirmar que la notificación de login se comporta
      según D1.

---

## Procedimiento estándar — Moodle core

Reemplazar `<VERSION_DESTINO>` (ej. `5.2.3`), `<BRANCH>` (ej. `502`) y `<TARBALL_URL>` según corresponda.
El tarball "latest" de una rama apunta al build más reciente disponible — no siempre coincide con el
build exacto anunciado en el momento de planear (ver advertencias).

### 1. Verificar SG y conectividad

```bash
curl -s https://checkip.amazonaws.com   # IP local actual

aws ec2 describe-security-groups --profile ct \
  --group-ids sg-039bcb1cb3a57db7f \
  --query 'SecurityGroups[0].IpPermissions[?FromPort==`22`].IpRanges[].CidrIp' \
  --output text

# Si cambió, revocar la vieja y autorizar la nueva
aws ec2 revoke-security-group-ingress --profile ct \
  --group-id sg-039bcb1cb3a57db7f --protocol tcp --port 22 --cidr <OLD_IP>/32
aws ec2 authorize-security-group-ingress --profile ct \
  --group-id sg-039bcb1cb3a57db7f --protocol tcp --port 22 --cidr <NEW_IP>/32
```

### 2. (Preparación, sin downtime) Descargar y verificar el tarball nuevo

Se puede hacer con el sitio en producción, sin afectar a los usuarios.

**Importante:** usar la URL de la versión exacta, no `moodle-latest-<branch>.tgz` — ese alias apunta al
build empaquetado más reciente de la rama, que puede ir varios días rezagado respecto al release/tag
recién anunciado (ver advertencias). Para obtener la URL exacta de una versión:

1. Ir a `https://download.moodle.org/releases/latest/` (o la página de releases de la rama que
   corresponda) y confirmar el número de versión y build anunciados.
2. La URL directa de descarga (sin la página intersticial) tiene el patrón:
   `https://packaging.moodle.org/stable<BRANCH>/moodle-<VERSION_DESTINO>.tgz`
   (se puede llegar a ella siguiendo el redirect 302 de
   `https://download.moodle.org/download.php/direct/stable<BRANCH>/moodle-<VERSION_DESTINO>.tgz`).

```bash
# Descargar a ~/moodle-staging (disco), NUNCA a /tmp (tmpfs = RAM del servidor)
command ssh -i ~/.ssh/ClaveCT.pem ec2-user@54.86.113.27 \
  "mkdir -p ~/moodle-staging && cd ~/moodle-staging \
   && curl -sSL -o moodle-<VERSION_DESTINO>.tgz \
      'https://packaging.moodle.org/stable<BRANCH>/moodle-<VERSION_DESTINO>.tgz' \
   && curl -sSL -o moodle-<VERSION_DESTINO>.tgz.sha256 \
      'https://packaging.moodle.org/stable<BRANCH>/moodle-<VERSION_DESTINO>.tgz.sha256' \
   && cat moodle-<VERSION_DESTINO>.tgz.sha256 && sha256sum moodle-<VERSION_DESTINO>.tgz"
# Los dos hashes deben coincidir. Si no, NO continuar.

# Extraer SOLO el día del upgrade (ocupa ~600 MB)
command ssh -i ~/.ssh/ClaveCT.pem ec2-user@54.86.113.27 \
  "cd ~/moodle-staging && rm -rf moodle && tar -xzf moodle-<VERSION_DESTINO>.tgz \
   && grep -E '^\\\$version|^\\\$release' moodle/public/version.php"
# version.php vive en <extraído>/public/ en los tarballs recientes (no en la raíz del tarball).
# Confirmar que el release coincide EXACTAMENTE con <VERSION_DESTINO> antes de continuar.
```

### 3. Activar modo mantenimiento

```bash
command ssh -i ~/.ssh/ClaveCT.pem ec2-user@54.86.113.27 \
  "sudo -u apache php /var/www/html/moodle/admin/cli/maintenance.php --enable"
```

Verificar que muestra mensaje de mantenimiento en `https://conectatech.co`.

### 4. Backup de base de datos

No existe script de backup automatizado en el servidor — hacer el dump manual. Credenciales de BD en
`/var/www/html/moodle/config.php`.

```bash
command ssh -i ~/.ssh/ClaveCT.pem ec2-user@54.86.113.27 \
  "sudo bash -c 'source <(php -r \"require(\\\"/var/www/html/moodle/config.php\\\"); \
   echo \\\"DB_HOST=\\\".\\\$CFG->dbhost.PHP_EOL; \
   echo \\\"DB_NAME=\\\".\\\$CFG->dbname.PHP_EOL; \
   echo \\\"DB_USER=\\\".\\\$CFG->dboptions[\\\"dbuser\\\"] ?? \\\$CFG->dbuser.PHP_EOL;\"); \
   mysqldump -h \$DB_HOST -u \$DB_USER -p\$DB_PASSWORD --single-transaction \$DB_NAME \
   | gzip > /var/backups/moodle/database/moodle-pre-upgrade-\$(date +%Y%m%d).sql.gz'"
```

> En la práctica ha sido más simple leer credenciales directamente de `config.php` con `cat` y armar el
> `mysqldump` a mano — ver advertencias.

Confirmar que el backup se creó:

```bash
command ssh -i ~/.ssh/ClaveCT.pem ec2-user@54.86.113.27 \
  "ls -lh /var/backups/moodle/database/ | tail -3"
```

### 5. Renombrar directorio actual y mover el nuevo

```bash
command ssh -i ~/.ssh/ClaveCT.pem ec2-user@54.86.113.27 \
  "sudo mv /var/www/html/moodle /var/www/html/moodle-old"

command ssh -i ~/.ssh/ClaveCT.pem ec2-user@54.86.113.27 \
  "sudo mv /home/ec2-user/moodle-staging/moodle /var/www/html/moodle"
```

> Si ya existe un `/var/www/html/moodle-old` de un upgrade anterior, el `mv` del paso anterior lo
> anidaría dentro (`moodle-old/moodle`). Borrar o renombrar el viejo **antes** de empezar (ver checklist
> previo del plan vigente).

### 6. Restaurar config.php, temas y plugins locales custom

```bash
# config.php
command ssh -i ~/.ssh/ClaveCT.pem ec2-user@54.86.113.27 \
  "sudo cp /var/www/html/moodle-old/config.php /var/www/html/moodle/config.php"

# Temas custom (no vienen en el tarball oficial). En un salto de rama (ej. 5.2 → 5.3) NO copiar
# desde moodle-old: instalar el release de Boost Union compatible con la rama destino.
command ssh -i ~/.ssh/ClaveCT.pem ec2-user@54.86.113.27 \
  "sudo cp -r /var/www/html/moodle-old/public/theme/boost_union \
               /var/www/html/moodle/public/theme/boost_union"

# Plugins locales custom (no vienen en el tarball oficial)
command ssh -i ~/.ssh/ClaveCT.pem ec2-user@54.86.113.27 \
  "sudo cp -r /var/www/html/moodle-old/public/local/usagereports \
               /var/www/html/moodle/public/local/usagereports"

# Ownership completo
command ssh -i ~/.ssh/ClaveCT.pem ec2-user@54.86.113.27 \
  "sudo chown -R apache:apache /var/www/html/moodle"
```

### 7. Ejecutar upgrade de Moodle

```bash
command ssh -i ~/.ssh/ClaveCT.pem ec2-user@54.86.113.27 \
  "sudo -u apache php /var/www/html/moodle/admin/cli/upgrade.php --non-interactive"
```

El proceso puede tardar varios minutos. Salida esperada al final: `Upgrade complete`. Este mismo comando
también instala/actualiza `local_usagereports` si su versión cambió.

### 8. Purgar cachés

```bash
command ssh -i ~/.ssh/ClaveCT.pem ec2-user@54.86.113.27 \
  "sudo -u apache php /var/www/html/moodle/admin/cli/purge_caches.php"
```

### 9. Deshabilitar modo mantenimiento

```bash
command ssh -i ~/.ssh/ClaveCT.pem ec2-user@54.86.113.27 \
  "sudo -u apache php /var/www/html/moodle/admin/cli/maintenance.php --disable"
```

### 10. Verificación post-upgrade

```bash
command ssh -i ~/.ssh/ClaveCT.pem ec2-user@54.86.113.27 \
  "grep -E '^\\\$version|^\\\$release' /var/www/html/moodle/public/version.php"

curl -sI https://conectatech.co | head -3   # Esperado: HTTP/2 200
```

Pruebas manuales obligatorias:
- [ ] Login en `https://conectatech.co`
- [ ] Navegar un curso y abrir un cuestionario
- [ ] Panel admin en `https://admin.conectatech.co` (login + carga de datos)
- [ ] **Raw SCSS de Boost Union se sigue aplicando** (ver ADR-009 en `MEMORY.md` — riesgo conocido tras
      cualquier upgrade de core)
- [ ] Informe "Uso de la plataforma" (`local_usagereports`) carga sin error

### 11. Limpieza (solo si todo funciona, esperar al menos unas horas/1 día)

```bash
command ssh -i ~/.ssh/ClaveCT.pem ec2-user@54.86.113.27 \
  "sudo rm -rf /var/www/html/moodle-old && rm -f ~/moodle-staging/moodle-<VERSION_DESTINO>.tgz*"
```

---

## Rollback

Si el upgrade falla o el sitio no funciona después del paso 9:

```bash
# 1. Reactivar mantenimiento si se desactivó
command ssh -i ~/.ssh/ClaveCT.pem ec2-user@54.86.113.27 \
  "sudo -u apache php /var/www/html/moodle/admin/cli/maintenance.php --enable"

# 2. Reemplazar con el directorio respaldado
command ssh -i ~/.ssh/ClaveCT.pem ec2-user@54.86.113.27 \
  "sudo rm -rf /var/www/html/moodle \
   && sudo mv /var/www/html/moodle-old /var/www/html/moodle"

# 3. Restaurar BD (SOLO si upgrade.php llegó a modificar el esquema)
command ssh -i ~/.ssh/ClaveCT.pem ec2-user@54.86.113.27 \
  "ls -lh /var/backups/moodle/database/ | tail -3"
command ssh -i ~/.ssh/ClaveCT.pem ec2-user@54.86.113.27 \
  "gunzip < /var/backups/moodle/database/<ARCHIVO>.sql.gz | \
   mysql -h \$DB_HOST -u \$DB_USER -p\$DB_PASSWORD \$DB_NAME"

# 4. Deshabilitar mantenimiento
command ssh -i ~/.ssh/ClaveCT.pem ec2-user@54.86.113.27 \
  "sudo -u apache php /var/www/html/moodle/admin/cli/maintenance.php --disable"
```

---

## Procedimiento — upgrade mayor de MariaDB en RDS

Escrito para 10.11 → 11.4 (prerrequisito de Moodle 5.3), reutilizable para futuros saltos mayores.
Un upgrade mayor en sitio **no se puede revertir**: el rollback es restaurar un snapshot a una instancia
nueva (con otro endpoint). BD actual ~73 MB, así que el upgrade debería tomar minutos, pero la duración
real se mide en el ensayo.

Variables:
```bash
DB=conectatech-prod-db-20260217043350287600000001
PG_NUEVO=conectatech-prod-mariadb114
DESTINO=11.4.13
```

### 1. Parameter group nuevo (familia `mariadb11.4`) con los mismos valores custom

Valores custom actuales del grupo `mariadb10.11` (verificados 2026-10-05; todos existen y son
modificables en `mariadb11.4`):

```bash
aws rds create-db-parameter-group --profile ct --db-parameter-group-name $PG_NUEVO \
  --db-parameter-group-family mariadb11.4 --description "ConectaTech prod MariaDB 11.4"

aws rds modify-db-parameter-group --profile ct --db-parameter-group-name $PG_NUEVO --parameters \
  "ParameterName=character_set_server,ParameterValue=utf8mb4,ApplyMethod=immediate" \
  "ParameterName=collation_server,ParameterValue=utf8mb4_unicode_ci,ApplyMethod=immediate" \
  "ParameterName=innodb_flush_log_at_trx_commit,ParameterValue=2,ApplyMethod=immediate" \
  "ParameterName=innodb_log_file_size,ParameterValue=134217728,ApplyMethod=immediate" \
  "ParameterName=max_allowed_packet,ParameterValue=67108864,ApplyMethod=immediate" \
  "ParameterName=max_connections,ParameterValue=200,ApplyMethod=immediate" \
  "ParameterName=query_cache_type,ParameterValue=0,ApplyMethod=pending-reboot"
```

Antes de ejecutar, comparar de nuevo con
`aws rds describe-db-parameters --profile ct --db-parameter-group-name conectatech-prod-mariadb-20260217032230294200000001 --source user`
por si alguien cambió algo. Ojo: el default de `innodb_log_file_size` en 11.4 es 2 GB; mantenemos
128 MB a propósito (instancia micro, 20 GB de almacenamiento).

### 2. Ensayo sobre un clon (obligatorio la primera vez)

```bash
SNAP=$(aws rds describe-db-snapshots --profile ct --db-instance-identifier $DB \
  --query 'reverse(sort_by(DBSnapshots,&SnapshotCreateTime))[0].DBSnapshotIdentifier' --output text)
aws rds restore-db-instance-from-db-snapshot --profile ct \
  --db-instance-identifier conectatech-ensayo-mariadb --db-snapshot-identifier $SNAP \
  --db-instance-class db.t4g.micro --no-multi-az --no-publicly-accessible \
  --vpc-security-group-ids $(aws rds describe-db-instances --profile ct --db-instance-identifier $DB \
     --query 'DBInstances[0].VpcSecurityGroups[].VpcSecurityGroupId' --output text) \
  --db-subnet-group-name $(aws rds describe-db-instances --profile ct --db-instance-identifier $DB \
     --query 'DBInstances[0].DBSubnetGroup.DBSubnetGroupName' --output text)
aws rds wait db-instance-available --profile ct --db-instance-identifier conectatech-ensayo-mariadb

time aws rds modify-db-instance --profile ct --db-instance-identifier conectatech-ensayo-mariadb \
  --engine-version $DESTINO --allow-major-version-upgrade \
  --db-parameter-group-name $PG_NUEVO --apply-immediately
aws rds wait db-instance-available --profile ct --db-instance-identifier conectatech-ensayo-mariadb
```

Validar en el clon, desde el EC2:
- `SELECT VERSION();` con el cliente `mysql` y un `mysqldump` de prueba (MariaDB 11.4 cambió defaults
  de TLS en el lado cliente; confirmar que el cliente del EC2 sigue conectando igual).
- Conexión PHP/mysqli: un script CLI mínimo con `new mysqli(...)` contra el endpoint del clon.
- Anotar cuánto tardó el `modify-db-instance` hasta `available` → define el tamaño de la ventana.
- Borrar el clon: `aws rds delete-db-instance --profile ct --db-instance-identifier conectatech-ensayo-mariadb --skip-final-snapshot`

### 3. Producción

1. Modo mantenimiento de Moodle (`maintenance.php --enable`).
2. `mysqldump` (paso 4 del procedimiento estándar) **y** snapshot manual:
   ```bash
   aws rds create-db-snapshot --profile ct --db-instance-identifier $DB \
     --db-snapshot-identifier pre-mariadb114-$(date +%Y%m%d)
   aws rds wait db-snapshot-available --profile ct --db-snapshot-identifier pre-mariadb114-$(date +%Y%m%d)
   ```
3. Upgrade:
   ```bash
   aws rds modify-db-instance --profile ct --db-instance-identifier $DB \
     --engine-version $DESTINO --allow-major-version-upgrade \
     --db-parameter-group-name $PG_NUEVO --apply-immediately
   aws rds wait db-instance-available --profile ct --db-instance-identifier $DB
   aws rds describe-db-instances --profile ct --db-instance-identifier $DB \
     --query 'DBInstances[0].[EngineVersion,DBParameterGroups[0].ParameterApplyStatus]' --output text
   ```
   Si el parameter group queda en `pending-reboot`, reiniciar: `aws rds reboot-db-instance ...` y esperar.
4. `purge_caches.php`, revisar *Administración del sitio → Servidor → Entorno* (debe mostrar MariaDB
   11.4.x en verde), `maintenance.php --disable` y pruebas del paso 10.

### 4. Rollback

Restaurar `pre-mariadb114-<fecha>` a una instancia nueva (mismo SG/subnet group, parameter group
`mariadb10.11` original), cambiar `$CFG->dbhost` en `/var/www/html/moodle/config.php` al nuevo endpoint
y purgar cachés. El endpoint cambia, así que también hay que revisar cualquier otro consumidor de la BD
(scripts de `admin-module/backend` usan el `config.php` de Moodle, no tienen endpoint propio).

---

## Procedimiento — extensiones locales (temas y plugins `local_*`)

Para actualizar solo un tema o plugin local (sin tocar Moodle core), el flujo es:

1. Backup de BD (igual que paso 4 arriba) — cualquier plugin puede correr migraciones de esquema.
2. Copiar la nueva versión del plugin/tema al directorio correspondiente en el servidor
   (`public/theme/<nombre>` o `public/local/<nombre>`), respetando ownership `apache:apache`.
3. `sudo -u apache php /var/www/html/moodle/admin/cli/upgrade.php --non-interactive`
4. `purge_caches.php`
5. Verificación manual del plugin específico.
6. Registrar en la tabla de extensiones abajo.

**No requiere modo mantenimiento** en la mayoría de los casos (Moodle sirve el sitio normalmente durante
la ejecución de `upgrade.php` para plugins menores), salvo que el cambio sea disruptivo — evaluar caso a
caso.

---

## Gotchas y advertencias acumuladas

- **`command ssh` siempre**, nunca `ssh` a secas — el shell local tiene un hook que rompe subshells de
  rsync/pipes con `ssh` directo.
- El tarball de Moodle extrae a un directorio llamado `moodle/` — por eso se extrae en `/tmp/` y se
  mueve, nunca se extrae directamente en `/var/www/html/`.
- `config.php` vive en la raíz (`/var/www/html/moodle/config.php`), **no** en `public/`. El tarball
  incluye `config-dist.php` en la raíz pero no un `config.php` real.
- Los scripts CLI se invocan desde `/var/www/html/moodle/admin/cli/` (sin `public/`), aunque el
  DocumentRoot de Apache apunte a `public/`.
- **El tema de terceros `boost_union` y el plugin `local_usagereports` NO vienen en el tarball
  oficial** — hay que recopiarlos (desde `moodle-old` en upgrades de punto; desde su release
  compatible / el repo en saltos de rama) después de mover el directorio nuevo, o el sitio pierde el
  theming y el reporte de uso. (`moove` también estaba en esta lista hasta su desinstalación el
  2026-10-05.)
- El `latest` de `moodle-latest-<branch>.tgz` apunta al **build más reciente empaquetado de la rama**, no
  necesariamente al release/versión recién anunciada — el empaquetado oficial (con lang packs) puede
  publicarse varios días después del tag de código. Verificar `version.php` después de extraer, antes de
  continuar con el swap de directorios.
  - En el upgrade de 2026-06 esto causó que se instalara el build 20260630 en vez del 20260616
    originalmente planeado (el `latest` había avanzado de más).
  - En la preparación del upgrade a 5.2.3 (2026-09-15) fue el caso inverso: `moodle-latest-502.tgz`
    todavía servía el build 20260911 (5.2.2+) el mismo día en que 5.2.3 (build 20260914) ya estaba
    anunciado. Solución: usar la URL de versión exacta
    `https://packaging.moodle.org/stable502/moodle-5.2.3.tgz` (ver paso 2 del procedimiento) en vez del
    alias `latest`.
- `version.php` en los tarballs recientes vive en `<extraído>/public/version.php`, no en la raíz del
  tarball extraído.
- **No existe** `/usr/local/bin/moodle-backup-db.sh` en el servidor pese a estar documentado en
  procedimientos viejos — el backup de BD siempre se ha hecho con `mysqldump` manual. Si en algún
  momento se crea el script, actualizar esta sección.
- **ADR-009 (Boost Union + Moodle 5.2 SCSS):** tras el upgrade a Moodle 5.2, el Raw SCSS de Boost Union
  dejó de compilar por un cambio en `\core\output\theme_config` (llama al callback SCSS del tema padre Y
  del hijo; Boost Union v5.1 no soportaba el doble callback). Fix aplicado con variables `!default` en el
  `scsspre`. Boost Union ya publicó `v5.2-r8` (compatible nativamente) — confirmar tras cada upgrade que
  el Raw SCSS se sigue aplicando de todos modos, por si aparecen nuevas variables no definidas.
- Si SSH da timeout, la primera sospecha es que la IP local cambió — el Security Group
  `sg-039bcb1cb3a57db7f` solo autoriza una IP fija. Pero también pueden ocurrir timeouts intermitentes
  sin relación con el SG (blips de red transitorios) — antes de tocar el SG, verificar con
  `curl -s https://checkip.amazonaws.com` si la IP realmente cambió respecto a la autorizada; si coincide,
  simplemente reintentar el comando SSH (visto en el upgrade de 2026-09-15, 2-3 timeouts sueltos sin
  causa aparente, resueltos con reintento).
- Verificar espacio en disco antes de empezar (`df -h /var/www/html`) — el tarball + extracción +
  directorio viejo conviven temporalmente (~3x el tamaño de una instalación).
- **`/tmp` en el EC2 es tmpfs (RAM), con un límite de ~924 MB, en un servidor de 1,8 GB.** Descargar y
  extraer el tarball ahí consume ~680 MB de memoria y dejó al servidor con ~380 MB disponibles
  (2026-10-05). Usar siempre `~/moodle-staging/` (disco). Además, tmpfs se borra si se reinicia el EC2.
  Los upgrades anteriores usaron `/tmp` y salieron bien por suerte.
- **Moodle no permite el upgrade si un plugin declara `$plugin->supported` sin la rama destino**
  (ej. Boost Union `[502, 502]` frente a 5.3). Revisar el `version.php` de cada plugin de terceros antes
  de planear un salto de rama (no de punto).
- Los saltos de rama pueden subir el mínimo de base de datos (5.3 pide MariaDB 11.4). Comparar siempre
  `public/admin/environment.xml` del tarball nuevo con la versión del RDS antes de planear.
- Si la IP local cambió, el SSH da timeout de entrada (pasó el 2026-10-05: la IP autorizada era
  `201.245.254.202`, la nueva `186.154.112.115`). El paso 1 lo resuelve.
- Tras un upgrade, `moodle-old` se queda en el servidor hasta que alguien ejecuta el paso 11. Revisar
  que no exista antes del siguiente upgrade.
- **Operaciones largas por SSH: lanzarlas con `nohup` en el servidor y con log**, no en primer plano.
  El 2026-10-05 la conexión se cortó a mitad de un `uninstall_plugins.php` (por suerte no había
  empezado). Patrón:
  `cd /var/www/html/moodle && sudo nohup sudo -u apache php admin/cli/<script>.php ... > ~/moodle-staging/<tarea>.log 2>&1 < /dev/null &`
  y luego revisar el log. Para reconectar ayuda `-o ConnectionAttempts=3 -o ServerAliveInterval=10`.
- `pgrep -f <script>` dentro de un `ssh '...'` se encuentra a sí mismo (el patrón está en la línea de
  comando del propio `bash -c`) y da un falso "sigue corriendo". Usar `ps -eo pid,cmd | grep "[n]ombre"`.
- Después de **borrar el directorio de un plugin** (o cambiar cualquier `version.php`), Moodle dice
  "actualización pendiente" aunque todos los plugins estén al día: cambió el `allversionshash`. Es
  normal. `upgrade.php --non-interactive` solo refresca el hash.
- Tras desinstalar un plugin con `uninstall_plugins.php`, **borrar su directorio**. Si no, Moodle lo
  detecta como plugin nuevo y lo reinstala en el siguiente upgrade.
- El servidor MCP de AWS de Claude Code no apunta a la cuenta de ConectaTech (devuelve 0 instancias
  RDS). Usar la AWS CLI con `--profile ct`.

---

## Registro de actualizaciones — Moodle core

| Fecha | Origen | Destino | Resultado | Incidencias |
|---|---|---|---|---|
| 2026-06-23 | 5.2 (Build: 20260420) | 5.2.1+ (Build: 20260630) | ✅ OK | Se planeó build 20260616 pero `latest` ya apuntaba a 20260630 (release intermedio). Hubo que recopiar `boost_union` y `moove` tras el swap. |
| 2026-09-15 | 5.2.1+ (Build: 20260630) | 5.2.3 (Build: 20260914) | ✅ OK | Downtime ~5 min (21:56–22:01 UTC). 5.2.3 incluye fix crítico de gradebook (MDL-89497) y correcciones de seguridad aún no divulgadas por el equipo de Moodle. Ruta directa desde 5.2.1+ confirmada como segura por moodledev.io. El alias `latest-502` aún no apuntaba a este build al momento de preparar — se usó la URL de versión exacta `packaging.moodle.org/stable502/moodle-5.2.3.tgz`. `upgrade.php --non-interactive` finalizó sin errores. Verificación post-upgrade: HTTP 200 en `conectatech.co` y `admin.conectatech.co`, tema Boost Union con Raw SCSS custom aplicando correctamente (ADR-009 sin regresión, confirmado visualmente), sin errores en consola del navegador, panel admin redirige correctamente a login de Moodle cuando no hay sesión. **Gotcha nuevo:** hubo 2-3 timeouts SSH intermitentes durante la ejecución (no relacionados con el Security Group — la IP autorizada no había cambiado); simples reintentos resolvieron cada caso, sin impacto en el resultado. `/var/www/html/moodle-old` se deja sin borrar por unos días como ventana de rollback antes de ejecutar el paso 11 (limpieza). |

## Registro de actualizaciones — base de datos (RDS)

| Fecha | Origen | Destino | Resultado | Incidencias |
|---|---|---|---|---|
| 2026-10-05 | — | — | ✅ | Retención de backups automáticos 1 → 7 días (sin reinicio). Preparación de la Fase 2. |

## Registro de actualizaciones — extensiones locales

| Fecha | Extensión | Origen | Destino | Resultado | Notas |
|---|---|---|---|---|---|
| — | `theme_boost_union` | — | `v5.2-r8` (2026042012) | — | Versión detectada en producción al momento de escribir este registro (2026-09-15); no hay fecha registrada de cuándo se actualizó. |
| — | `theme_boost_union` | `v5.2-r8` | `v5.2-r9` (2026042013) | — | Detectada en producción el 2026-10-05 sin registro previo de la actualización. `supported = [502, 502]`. |
| 2026-10-05 | `theme_boost_union` | `v5.2-r9` | `v5.2-r10` (2026042014) | ✅ OK | Higiene previa a 5.3 (Fase 1). Tarball del tag de GitHub `v5.2-r10`. `upgrade.php` OK (0,11 s), sin pendientes, Raw SCSS aplicando (ADR-009). Respaldo de r9: `~/moodle-staging/boost_union-v5.2-r9-backup.tgz`. Sin modo mantenimiento (cambio de una plantilla y SCSS). |
| 2026-10-05 | `theme_moove` | `5.2.1` (2026042100) | **desinstalado** | ✅ OK | No estaba activo ni asignado a nada, y no tiene versión para 5.3. Respaldo de ajustes, archivos y código en `/var/backups/moodle/moove-uninstall-20261005/`. |
| — | `theme_moove` | — | `5.2.1` (2026042100) | — | Ídem — versión detectada en producción, sin fecha de actualización registrada. |
| 2026-09-07 | `local_usagereports` | (instalación inicial) | `0.1.0` (2026090700) | ✅ OK | Instalación inicial del plugin de reportes de uso. Ver `docs/local_usagereports-especificacion.md` y ADR-011. |
