# Mantenimiento de Moodle 5.1

Guía completa de mantenimiento preventivo y resolución de problemas.

## 📋 Rutinas de Mantenimiento

### Diarias (Automáticas)

✅ **Backups de Base de Datos**
- Script: `/usr/local/bin/moodle-backup-db.sh`
- Horario: 3:00 AM
- Retención: 7 días
- Ubicación: `/var/backups/moodle/database/`

✅ **Cron de Moodle**
- Frecuencia: Cada minuto
- Monitoreo: Verificar `/var/log/cron`

✅ **Monitoreo de Memoria**
- Script: `/usr/local/bin/memory-monitor.sh`
- Frecuencia: Cada hora
- Log: `/var/log/memory-monitor.log`

### Semanales

✅ **Revisar Logs de Error**
```bash
# Apache errors
sudo tail -100 /var/log/httpd/moodle-error.log

# PHP-FPM errors
sudo tail -100 /var/log/php-fpm/error.log

# System errors
sudo journalctl -p err -S "1 week ago"
```

✅ **Verificar Espacio en Disco**
```bash
# Disk usage
df -h

# Largest directories in moodledata
sudo du -sh /moodledata/*  | sort -h | tail -10

# Clean old logs
sudo find /var/log -name "*.log.*.gz" -mtime +30 -delete
```

✅ **Revisar Actualizaciones**
```bash
# System updates
sudo dnf check-update

# Moodle updates
sudo -u apache php /var/www/html/moodle/admin/cli/check_database_schema.php
```

### Mensuales

✅ **Actualizar Sistema Operativo**
```bash
# Create snapshot first!
sudo dnf update -y
sudo reboot
```

✅ **Revisar Certificado SSL**
```bash
# Check expiry
sudo certbot certificates

# Test renewal
sudo certbot renew --dry-run
```

✅ **Optimizar Base de Datos**
```bash
# Optimize tables
mysql -h $DB_HOST -u $DB_USER -p$DB_PASSWORD $DB_NAME -e "OPTIMIZE TABLE mdl_sessions;"

# Purge old sessions
sudo -u apache php /var/www/html/moodle/admin/cli/cron.php --execute='\core\task\session_cleanup_task'
```

✅ **Revisar Plugins**
```bash
# Check for updates
# Site administration → Server → Notifications
# Site administration → Plugins → Plugins overview
```

## 🔧 Tareas de Optimización

### Limpiar Cache

```bash
# Purge all caches
sudo -u apache php /var/www/html/moodle/admin/cli/purge_caches.php

# Clear specific caches
sudo -u apache php /var/www/html/moodle/admin/cli/purge_caches.php --all
```

### Limpiar Sesiones Antiguas

```bash
# Via CLI
sudo -u apache php /var/www/html/moodle/admin/cli/cron.php --execute='\core\task\session_cleanup_task'

# Manual cleanup (si necesario)
mysql -h $DB_HOST -u $DB_USER -p$DB_PASSWORD $DB_NAME -e "DELETE FROM mdl_sessions WHERE timemodified < UNIX_TIMESTAMP(DATE_SUB(NOW(), INTERVAL 2 DAY));"
```

### Limpiar Papelera

```bash
# Clean deleted files (after 4 days default)
sudo -u apache php /var/www/html/moodle/admin/cli/cron.php --execute='\core\task\file_trash_cleanup_task'
```

## 🔄 Actualizaciones de Moodle

### Actualización Minor (5.1.0 → 5.1.1)

```bash
# 1. Backup first!
sudo /usr/local/bin/moodle-backup-db.sh
sudo tar -czf /var/backups/moodle-code-$(date +%Y%m%d).tar.gz /var/www/html/moodle

# 2. Enable maintenance mode
sudo -u apache php /var/www/html/moodle/admin/cli/maintenance.php --enable

# 3. Update code
cd /var/www/html/moodle
sudo -u apache git fetch origin
sudo -u apache git checkout MOODLE_51_STABLE
sudo -u apache git pull

# 4. Run upgrade
sudo -u apache php /var/www/html/moodle/admin/cli/upgrade.php --non-interactive

# 5. Purge caches
sudo -u apache php /var/www/html/moodle/admin/cli/purge_caches.php

# 6. Disable maintenance mode
sudo -u apache php /var/www/html/moodle/admin/cli/maintenance.php --disable
```

**Nota sobre estructura de directorios:** Los scripts CLI permanecen en `/var/www/html/moodle/admin/cli/` (por encima del directorio `/public` que es el DocumentRoot del servidor web).

### Actualización Major (5.1 → 5.2)

**⚠️ IMPORTANTE:** Probar en staging primero

```bash
# Similar process but:
# - Review compatibility of plugins
# - Check system requirements
# - Plan downtime window
# - Have rollback plan ready
```

### Actualización desde Moodle 4.x a 5.1

**⚠️ CRÍTICO:** Este proceso requiere reconfiguración del servidor web

**Requisitos previos:**
- Estar en Moodle 4.2.3 o superior (path mínimo de actualización)
- PHP 8.2.0+ instalado
- Backup completo del sistema

**Pasos:**

```bash
# 1. Backup completo
sudo /usr/local/bin/moodle-backup-db.sh
sudo tar -czf /var/backups/moodle-code-$(date +%Y%m%d).tar.gz /var/www/html/moodle
sudo tar -czf /var/backups/moodledata-$(date +%Y%m%d).tar.gz /moodledata

# 2. Enable maintenance mode
sudo -u apache php /var/www/html/moodle/admin/cli/maintenance.php --enable

# 3. Update code to 5.1
cd /var/www/html/moodle
sudo -u apache git fetch origin
sudo -u apache git checkout MOODLE_51_STABLE
sudo -u apache git pull

# 4. CRÍTICO: Reconfigurar Apache DocumentRoot
# Editar /etc/httpd/conf.d/moodle.conf
# Cambiar: DocumentRoot /var/www/html/moodle
# Por:     DocumentRoot /var/www/html/moodle/public

sudo nano /etc/httpd/conf.d/moodle.conf

# Ejemplo de configuración requerida:
# <VirtualHost *:80>
#     ServerName moodle.yourdomain.com
#     DocumentRoot /var/www/html/moodle/public
#
#     <Directory /var/www/html/moodle/public>
#         Options -Indexes +FollowSymLinks
#         AllowOverride All
#         Require all granted
#     </Directory>
# </VirtualHost>

# 5. Verificar configuración de Apache
sudo apachectl configtest

# 6. Recargar Apache
sudo systemctl reload httpd

# 7. Run upgrade
sudo -u apache php /var/www/html/moodle/admin/cli/upgrade.php --non-interactive

# 8. Verificar migración de plugins
sudo -u apache php /var/www/html/moodle/admin/cli/check_database_schema.php

# 9. Purge caches
sudo -u apache php /var/www/html/moodle/admin/cli/purge_caches.php

# 10. Verificar Routing Engine activo
# Site administration → Development → Experimental → Enable routing engine (debe estar ON)

# 11. Test básico
curl -I http://localhost/
# Debe devolver 200 OK

# 12. Disable maintenance mode
sudo -u apache php /var/www/html/moodle/admin/cli/maintenance.php --disable
```

**Post-actualización:**
1. Verificar que todos los plugins funcionen correctamente
2. Revisar logs de error: `/var/log/httpd/moodle-error.log`
3. Probar funcionalidades críticas: login, navegación de cursos, subida de archivos
4. Verificar acceso a temas y recursos estáticos
5. Confirmar que el Routing Engine está activo y funcionando

**Rollback si hay problemas:**
```bash
# 1. Enable maintenance mode
sudo -u apache php /var/www/html/moodle/admin/cli/maintenance.php --enable

# 2. Restaurar código
cd /var/www/html
sudo rm -rf moodle
sudo tar -xzf /var/backups/moodle-code-YYYYMMDD.tar.gz

# 3. Restaurar DocumentRoot a configuración anterior
sudo nano /etc/httpd/conf.d/moodle.conf
# Cambiar de vuelta: DocumentRoot /var/www/html/moodle

# 4. Recargar Apache
sudo systemctl reload httpd

# 5. Restaurar base de datos
gunzip < /var/backups/moodle/database/moodle-db-YYYYMMDD.sql.gz | \
    mysql -h $DB_HOST -u $DB_USER -p$DB_PASSWORD $DB_NAME

# 6. Disable maintenance mode
sudo -u apache php /var/www/html/moodle/admin/cli/maintenance.php --disable
```

## 🚨 Troubleshooting

### Sitio Lento

**Diagnóstico:**
```bash
# Check CPU and memory
top
free -h

# Check disk I/O
iostat -x 1 5

# Check Apache/PHP processes
ps aux | grep -E 'httpd|php-fpm' | wc -l

# Check database connections
mysql -h $DB_HOST -u $DB_USER -p$DB_PASSWORD -e "SHOW PROCESSLIST;"
```

**Soluciones:**
1. Purge caches
2. Check PHP-FPM max_children
3. Optimize database queries
4. Enable Moodle caching
5. Consider CDN

### Errores 503 Service Unavailable

**Causa común:** PHP-FPM not running

```bash
# Check status
sudo systemctl status php-fpm

# Restart
sudo systemctl restart php-fpm

# Check logs
sudo tail -50 /var/log/php-fpm/error.log

# Check for OOM kills
sudo dmesg | grep -i "out of memory"
```

### Base de Datos No Responde

```bash
# Check RDS status
aws rds describe-db-instances --db-instance-identifier acgdb

# Test connection
mysql -h $DB_HOST -u $DB_USER -p$DB_PASSWORD -e "SELECT 1;"

# Check security group
# Verify EC2 security group can access RDS port 3306
```

### Cron No Se Ejecuta

```bash
# Check cron configuration
cat /etc/cron.d/moodle

# Check cron service
sudo systemctl status crond

# Check cron logs
sudo tail -f /var/log/cron

# Run manually
sudo -u apache /usr/bin/php /var/www/html/moodle/admin/cli/cron.php
```

### Certificado SSL Expirado

```bash
# Check expiry
sudo certbot certificates

# Renew immediately
sudo certbot renew --force-renewal

# Restart Apache
sudo systemctl restart httpd
```

## 📊 Monitoreo de Salud

### Script de Verificación Rápida

```bash
sudo /usr/local/bin/moodle-status.sh
```

### Health Check Completo

```bash
sudo /usr/local/bin/moodle-health-check.sh
```

### CloudWatch Metrics

```bash
# View in AWS Console
# CloudWatch → Dashboards → Moodle

# Or via CLI
aws cloudwatch get-metric-statistics \
    --namespace Moodle/EC2 \
    --metric-name MEMORY_USED \
    --dimensions Name=InstanceId,Value=i-xxxxx \
    --start-time $(date -u -d '1 hour ago' +%Y-%m-%dT%H:%M:%S) \
    --end-time $(date -u +%Y-%m-%dT%H:%M:%S) \
    --period 300 \
    --statistics Average
```

## 💾 Recuperación de Desastres

### Restaurar desde Backup

**Base de Datos:**
```bash
# Find backup
ls -lh /var/backups/moodle/database/

# Restore
gunzip < /var/backups/moodle/database/moodle-db-YYYYMMDD.sql.gz | \
    mysql -h $DB_HOST -u $DB_USER -p$DB_PASSWORD $DB_NAME
```

**Moodledata:**
```bash
# Restore
sudo tar -xzf /var/backups/moodle/moodledata/moodledata-YYYYMMDD.tar.gz -C /
```

**Código:**
```bash
# Via Git
cd /var/www/html/moodle
sudo -u apache git checkout <commit-hash>

# From backup
sudo tar -xzf /var/backups/moodle-code-YYYYMMDD.tar.gz -C /var/www/html/
```

### Rollback de Actualización

```bash
# 1. Enable maintenance mode
sudo -u apache php /var/www/html/moodle/admin/cli/maintenance.php --enable

# 2. Restore database
gunzip < /path/to/backup.sql.gz | mysql -h $DB_HOST -u $DB_USER -p$DB_PASSWORD $DB_NAME

# 3. Restore code
cd /var/www/html/moodle
sudo -u apache git checkout <previous-version>

# 4. Fix permissions
sudo chown -R apache:apache /var/www/html/moodle

# 5. Purge caches
sudo -u apache php /var/www/html/moodle/admin/cli/purge_caches.php

# 6. Disable maintenance mode
sudo -u apache php /var/www/html/moodle/admin/cli/maintenance.php --disable
```

## 📈 Métricas de Performance

### Comandos Útiles

```bash
# Apache connections
sudo netstat -ant | grep :80 | grep ESTABLISHED | wc -l

# PHP-FPM processes
ps aux | grep php-fpm | grep -v grep | wc -l

# Database connections
mysql -h $DB_HOST -u $DB_USER -p$DB_PASSWORD -e "SHOW STATUS LIKE 'Threads_connected';"

# Disk I/O
iostat -x 1 5

# Load average
uptime

# Memory usage
free -h
```

### Logs Importantes

```
/var/log/httpd/moodle-access.log    - Apache access
/var/log/httpd/moodle-error.log     - Apache errors
/var/log/php-fpm/error.log          - PHP errors
/var/log/memory-monitor.log         - Memory usage
/var/log/moodle-backup.log          - Backup status
/var/log/cron                       - Cron execution
```

## 🔒 Seguridad

### Auditoría Mensual

```bash
# Failed login attempts
# Check in Moodle: Reports → Logs

# System updates
sudo dnf check-update | grep -i security

# Open ports
sudo netstat -tuln

# Review users with shell access
cat /etc/passwd | grep -v nologin
```

### Hardening Checklist

- [ ] Firewall configurado (solo 22, 80, 443)
- [ ] SSH key-only (no passwords)
- [ ] SSL certificate válido y actualizado
- [ ] Moodle actualizado a última versión
- [ ] Plugins actualizados
- [ ] Strong password policy enabled
- [ ] Admin password rotated regularly
- [ ] Backups funcionando y verificados
- [ ] Monitoring activo

## 📞 Contactos de Emergencia

```
AWS Support: https://console.aws.amazon.com/support/
Moodle Community: https://moodle.org/mod/forum/
Security Issues: security@moodle.org
```

## 🆕 Consideraciones Específicas de Moodle 5.1

### Estructura de Directorios /public

**Puntos clave:**
- **DocumentRoot:** `/var/www/html/moodle/public` (no `/var/www/html/moodle`)
- **Config.php:** Permanece en `/var/www/html/moodle/config.php`
- **CLI scripts:** Permanecen en `/var/www/html/moodle/admin/cli/`
- **Moodledata:** Permanece en `/moodledata` (sin cambios)

### Verificación de Routing Engine

```bash
# Verificar que el Routing Engine esté activo
# Site administration → Development → Experimental → Enable routing engine

# O verificar vía CLI
sudo -u apache php /var/www/html/moodle/admin/cli/cfg.php --name=enableroutingenabled
```

### Migración de Plugins Post-5.1

Después de actualizar a 5.1, algunos plugins pueden necesitar relocación:

```bash
# 1. Listar plugins instalados
sudo -u apache php /var/www/html/moodle/admin/cli/uninstall_plugins.php --showallplugins

# 2. Verificar estado de plugins
# Site administration → Plugins → Plugins overview

# 3. Reinstalar plugins problemáticos
# (manual desde la interfaz web)
```

### Troubleshooting Específico de 5.1

**Problema:** Sitio muestra error 404 después de actualizar

**Solución:**
```bash
# Verificar DocumentRoot
grep DocumentRoot /etc/httpd/conf.d/moodle.conf
# Debe mostrar: DocumentRoot /var/www/html/moodle/public

# Verificar permisos en /public
ls -la /var/www/html/moodle/public/
sudo chown -R apache:apache /var/www/html/moodle/public/

# Recargar Apache
sudo systemctl reload httpd
```

**Problema:** Assets estáticos (CSS, JS, imágenes) no cargan

**Solución:**
```bash
# Purge all caches
sudo -u apache php /var/www/html/moodle/admin/cli/purge_caches.php

# Verificar rutas en navegador (deben apuntar a /public/...)
# Ejemplo: http://moodle.domain.com/theme/boost/style.php

# Verificar configuración de Apache permite .htaccess
grep AllowOverride /etc/httpd/conf.d/moodle.conf
# Debe mostrar: AllowOverride All
```

**Problema:** Plugins no funcionan después de actualizar

**Solución:**
```bash
# Verificar esquema de base de datos
sudo -u apache php /var/www/html/moodle/admin/cli/check_database_schema.php

# Upgrade plugins
sudo -u apache php /var/www/html/moodle/admin/cli/upgrade.php --non-interactive

# Verificar en interfaz web
# Site administration → Notifications
```

### Requisitos de PHP para Moodle 5.1

**Mínimo:** PHP 8.2.0
**Recomendado:** PHP 8.3.x o 8.4.x

```bash
# Verificar versión actual
php -v

# Verificar extensiones requeridas
php -m | grep -E 'sodium|curl|gd|intl|mbstring|zip|xml'

# Verificar max_input_vars (debe ser >= 5000)
php -i | grep max_input_vars
```

### Base de Datos - Requisitos 5.1

| Motor | Versión Mínima | Proyecto |
|-------|----------------|----------|
| PostgreSQL | 15.0 | - |
| MySQL | 8.4.0 | - |
| MariaDB | 10.11.0 | ✅ 10.11.15 |

**Verificar versión de MariaDB:**
```bash
mysql -h $DB_HOST -u $DB_USER -p$DB_PASSWORD -e "SELECT VERSION();"
```

## ✅ Checklist de Mantenimiento

### Diario
- [ ] Revisar alertas CloudWatch
- [ ] Verificar backups completados

### Semanal
- [ ] Revisar logs de error
- [ ] Verificar espacio en disco
- [ ] Check SSL certificate status
- [ ] Verificar Routing Engine activo (5.1+)

### Mensual
- [ ] Actualizar sistema operativo
- [ ] Revisar y aplicar actualizaciones de Moodle
- [ ] Optimizar base de datos
- [ ] Test backup restore
- [ ] Security audit
- [ ] Verificar permisos en /public directory (5.1+)

### Post-Actualización a 5.1
- [ ] Verificar DocumentRoot apunta a /public
- [ ] Confirmar Routing Engine activo
- [ ] Verificar carga de assets estáticos
- [ ] Probar login y navegación básica
- [ ] Verificar funcionamiento de plugins
- [ ] Revisar logs de error

---

**Fecha:** 2026-02-11
**Versión:** 1.1.0
