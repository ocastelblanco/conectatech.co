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
| Temas custom instalados | `boost_union`, `moove` (no vienen en el tarball oficial) |
| Plugins locales custom | `local_usagereports` (repo propio, no viene en el tarball oficial) |
| Security Group SSH | `sg-039bcb1cb3a57db7f` (solo IP autorizada — ver advertencias) |
| Backup DB manual | `mysqldump` a `/tmp/` (no existe script `/usr/local/bin/moodle-backup-db.sh`) |

> **CRÍTICO:** Usar siempre `command ssh` (no `ssh` a secas) — el shell local tiene un hook que envuelve
> SSH y rompe los subshells de rsync/pipes.

**Última versión conocida de Moodle core en producción:** ver última fila de
[Registro de actualizaciones](#registro-de-actualizaciones-moodle-core).

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
command ssh -i ~/.ssh/ClaveCT.pem ec2-user@54.86.113.27 \
  "cd /tmp && curl -L -o moodle-<VERSION_DESTINO>.tgz \
   'https://packaging.moodle.org/stable<BRANCH>/moodle-<VERSION_DESTINO>.tgz' \
   && tar -xzf moodle-<VERSION_DESTINO>.tgz \
   && echo 'Extracción OK'"

command ssh -i ~/.ssh/ClaveCT.pem ec2-user@54.86.113.27 \
  "grep -E '^\\\$version|^\\\$release' /tmp/moodle/public/version.php"
# version.php vive en /tmp/moodle/public/ en los tarballs recientes (no en la raíz del tarball).
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
  "sudo mv /tmp/moodle /var/www/html/moodle"
```

### 6. Restaurar config.php, temas y plugins locales custom

```bash
# config.php
command ssh -i ~/.ssh/ClaveCT.pem ec2-user@54.86.113.27 \
  "sudo cp /var/www/html/moodle-old/config.php /var/www/html/moodle/config.php"

# Temas custom (no vienen en el tarball oficial)
command ssh -i ~/.ssh/ClaveCT.pem ec2-user@54.86.113.27 \
  "sudo cp -r /var/www/html/moodle-old/public/theme/boost_union \
               /var/www/html/moodle/public/theme/boost_union && \
   sudo cp -r /var/www/html/moodle-old/public/theme/moove \
               /var/www/html/moodle/public/theme/moove"

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
  "sudo rm -rf /var/www/html/moodle-old && sudo rm -f /tmp/moodle-<BRANCH>.tgz"
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
- **Temas de terceros (`boost_union`, `moove`) y el plugin `local_usagereports` NO vienen en el tarball
  oficial** — hay que recopiarlos desde `moodle-old` después de mover el directorio nuevo, o el sitio
  pierde el theming y el reporte de uso al reiniciar.
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
- Si SSH da timeout, la IP local cambió — el Security Group `sg-039bcb1cb3a57db7f` solo autoriza una
  IP fija.
- Verificar espacio en disco antes de empezar (`df -h /var/www/html`) — el tarball + extracción +
  directorio viejo conviven temporalmente (~3x el tamaño de una instalación).

---

## Registro de actualizaciones — Moodle core

| Fecha | Origen | Destino | Resultado | Incidencias |
|---|---|---|---|---|
| 2026-06-23 | 5.2 (Build: 20260420) | 5.2.1+ (Build: 20260630) | ✅ OK | Se planeó build 20260616 pero `latest` ya apuntaba a 20260630 (release intermedio). Hubo que recopiar `boost_union` y `moove` tras el swap. |
| 2026-09-15 (en curso) | 5.2.1+ (Build: 20260630) | 5.2.3 (Build: 20260914) | ⏳ Preparado, pendiente de ejecución (a la señal del usuario) | 5.2.3 incluye fix crítico de gradebook (MDL-89497, freeze de cálculo con penalización) y correcciones de seguridad aún no divulgadas por el equipo de Moodle (se publican ~1 semana después del release para dar tiempo de actualizar). Ruta directa desde 5.2.1+ confirmada como segura por moodledev.io. El alias `latest-502` aún no apuntaba a este build al momento de preparar — se usó la URL de versión exacta. Tarball ya descargado y extraído en `/tmp/moodle` del servidor, versión verificada. |

## Registro de actualizaciones — extensiones locales

| Fecha | Extensión | Origen | Destino | Resultado | Notas |
|---|---|---|---|---|---|
| — | `theme_boost_union` | — | `v5.2-r8` (2026042012) | — | Versión detectada en producción al momento de escribir este registro (2026-09-15); no hay fecha registrada de cuándo se actualizó. |
| — | `theme_moove` | — | `5.2.1` (2026042100) | — | Ídem — versión detectada en producción, sin fecha de actualización registrada. |
| 2026-09-07 | `local_usagereports` | (instalación inicial) | `0.1.0` (2026090700) | ✅ OK | Instalación inicial del plugin de reportes de uso. Ver `docs/local_usagereports-especificacion.md` y ADR-011. |
