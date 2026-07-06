# Upgrade Moodle 5.2 → Build 20260616

**Fecha programada:** 2026-06-23 (tarde, bajo tráfico)  
**Versión origen:** 5.2 Build 20260420  
**Versión destino:** 5.2 Build 20260616  
**Tipo de instalación:** tarball (no git)  
**Tiempo estimado de downtime:** ~15 min

---

## Contexto del servidor

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
| Plugins locales custom | ninguno |
| Temas custom | ninguno |

> **CRÍTICO:** Usar siempre `command ssh` (no `ssh` a secas) — el shell local tiene un hook que envuelve SSH y rompe los subshells de rsync/pipes.

---

## Pasos

### 1. Verificar SG y conectividad

Si SSH da timeout, la IP local cambió. Actualizar el Security Group `sg-039bcb1cb3a57db7f`:

```bash
# Obtener IP actual
curl -s https://checkip.amazonaws.com

# Ver IP actualmente autorizada
aws ec2 describe-security-groups --profile ct \
  --group-ids sg-039bcb1cb3a57db7f \
  --query 'SecurityGroups[0].IpPermissions[?FromPort==`22`].IpRanges[].CidrIp' \
  --output text

# Revocar vieja y autorizar nueva
aws ec2 revoke-security-group-ingress --profile ct \
  --group-id sg-039bcb1cb3a57db7f --protocol tcp --port 22 --cidr <OLD_IP>/32
aws ec2 authorize-security-group-ingress --profile ct \
  --group-id sg-039bcb1cb3a57db7f --protocol tcp --port 22 --cidr <NEW_IP>/32
```

---

### 2. Activar modo mantenimiento

```bash
command ssh -i ~/.ssh/ClaveCT.pem ec2-user@54.86.113.27 \
  "sudo -u apache php /var/www/html/moodle/admin/cli/maintenance.php --enable"
```

Verificar que muestra mensaje de mantenimiento en `https://conectatech.co`.

---

### 3. Backup de base de datos

```bash
command ssh -i ~/.ssh/ClaveCT.pem ec2-user@54.86.113.27 \
  "sudo /usr/local/bin/moodle-backup-db.sh"
```

Confirmar que el backup se creó:

```bash
command ssh -i ~/.ssh/ClaveCT.pem ec2-user@54.86.113.27 \
  "ls -lh /var/backups/moodle/database/ | tail -3"
```

---

### 4. Descargar y extraer el nuevo tarball

```bash
command ssh -i ~/.ssh/ClaveCT.pem ec2-user@54.86.113.27 \
  "cd /tmp && curl -L -o moodle-502.tgz \
   'https://packaging.moodle.org/stable502/moodle-latest-502.tgz' \
   && tar -xzf moodle-502.tgz \
   && echo 'Extracción OK'"
```

Verificar que se creó `/tmp/moodle/`:

```bash
command ssh -i ~/.ssh/ClaveCT.pem ec2-user@54.86.113.27 \
  "grep -E '^\\\$version|^\\\$release' /tmp/moodle/version.php"
# Debe mostrar: 2026061600.xx / '5.2 (Build: 20260616)'
```

---

### 5. Renombrar directorio actual y mover el nuevo

```bash
# Renombrar el directorio actual como respaldo
command ssh -i ~/.ssh/ClaveCT.pem ec2-user@54.86.113.27 \
  "sudo mv /var/www/html/moodle /var/www/html/moodle-old"

# Mover el directorio extraído a su lugar
command ssh -i ~/.ssh/ClaveCT.pem ec2-user@54.86.113.27 \
  "sudo mv /tmp/moodle /var/www/html/moodle"
```

---

### 6. Restaurar config.php y ajustar permisos

```bash
# Copiar config.php del directorio respaldado
command ssh -i ~/.ssh/ClaveCT.pem ec2-user@54.86.113.27 \
  "sudo cp /var/www/html/moodle-old/config.php /var/www/html/moodle/config.php"

# Ajustar ownership completo
command ssh -i ~/.ssh/ClaveCT.pem ec2-user@54.86.113.27 \
  "sudo chown -R apache:apache /var/www/html/moodle"
```

---

### 7. Ejecutar upgrade de Moodle

```bash
command ssh -i ~/.ssh/ClaveCT.pem ec2-user@54.86.113.27 \
  "sudo -u apache php /var/www/html/moodle/admin/cli/upgrade.php --non-interactive"
```

El proceso puede tardar varios minutos. Salida esperada al final: `Upgrade complete`.

---

### 8. Purgar cachés

```bash
command ssh -i ~/.ssh/ClaveCT.pem ec2-user@54.86.113.27 \
  "sudo -u apache php /var/www/html/moodle/admin/cli/purge_caches.php"
```

---

### 9. Deshabilitar modo mantenimiento

```bash
command ssh -i ~/.ssh/ClaveCT.pem ec2-user@54.86.113.27 \
  "sudo -u apache php /var/www/html/moodle/admin/cli/maintenance.php --disable"
```

---

### 10. Verificación post-upgrade

```bash
# Confirmar versión nueva
command ssh -i ~/.ssh/ClaveCT.pem ec2-user@54.86.113.27 \
  "grep -E '^\\\$version|^\\\$release' /var/www/html/moodle/public/version.php"
# Esperado: 2026061600.xx / '5.2 (Build: 20260616)'

# Verificar que el sitio responde
curl -sI https://conectatech.co | head -3
# Esperado: HTTP/2 200
```

Pruebas manuales:
- Login en `https://conectatech.co` ✓
- Navegar un curso ✓
- Verificar que el panel admin en `https://admin.conectatech.co` funciona ✓

---

### 11. Limpieza (solo si todo funciona)

```bash
command ssh -i ~/.ssh/ClaveCT.pem ec2-user@54.86.113.27 \
  "sudo rm -rf /var/www/html/moodle-old && sudo rm -f /tmp/moodle-502.tgz"
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

# 3. Restaurar BD (si el upgrade CLI llegó a modificarla)
# Ver backup más reciente:
command ssh -i ~/.ssh/ClaveCT.pem ec2-user@54.86.113.27 \
  "ls -lh /var/backups/moodle/database/ | tail -3"
# Restaurar:
command ssh -i ~/.ssh/ClaveCT.pem ec2-user@54.86.113.27 \
  "sudo bash -c 'source /usr/local/bin/moodle-backup-db.sh --env-only 2>/dev/null; \
   gunzip < /var/backups/moodle/database/<ARCHIVO>.sql.gz | \
   mysql -h \$DB_HOST -u \$DB_USER -p\$DB_PASSWORD \$DB_NAME'"

# 4. Deshabilitar mantenimiento
command ssh -i ~/.ssh/ClaveCT.pem ec2-user@54.86.113.27 \
  "sudo -u apache php /var/www/html/moodle/admin/cli/maintenance.php --disable"
```

---

## Notas importantes

- El tarball extrae a un directorio llamado `moodle/` — por eso se extrae en `/tmp/` y se mueve, no se extrae directamente en `/var/www/html/`.
- `config.php` vive en la raíz (`/var/www/html/moodle/config.php`), no en `public/`. El tarball incluye `config-dist.php` en la raíz pero no un `config.php` real.
- Los scripts CLI se invocan desde `/var/www/html/moodle/admin/cli/` (sin `public/`), aunque el DocumentRoot de Apache apunte a `public/`.
- **Temas de terceros instalados:** `boost_union` y `moove` viven en `public/theme/` y NO vienen en el tarball oficial. Después del paso 6 (restaurar config.php) hay que copiarlos desde `moodle-old`:
  ```bash
  command ssh -i ~/.ssh/ClaveCT.pem ec2-user@54.86.113.27 \
    "sudo cp -r /var/www/html/moodle-old/public/theme/boost_union \
                 /var/www/html/moodle/public/theme/boost_union && \
     sudo cp -r /var/www/html/moodle-old/public/theme/moove \
                 /var/www/html/moodle/public/theme/moove && \
     sudo chown -R apache:apache /var/www/html/moodle/public/theme/boost_union \
                                  /var/www/html/moodle/public/theme/moove"
  ```
  Luego re-ejecutar `upgrade.php --non-interactive` y `purge_caches.php`.
- El script de backup `/usr/local/bin/moodle-backup-db.sh` no existe en el servidor — hacer el dump manual con `mysqldump` a `/tmp/` y mover con `sudo mv`. Credenciales en `/var/www/html/moodle/config.php`.
- El `latest` de `moodle-latest-502.tgz` apunta al build más reciente del branch 5.2, no necesariamente al build exacto documentado (en este upgrade se instaló 20260630 en lugar de 20260616).
