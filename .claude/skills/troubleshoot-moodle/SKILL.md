---
name: troubleshoot-moodle
description: Diagnoses and resolves common Moodle deployment and operational issues including performance problems, errors, and service failures. Use when issues are reported or site is not functioning properly. Triggers: "site is down", "503 error", "Moodle slow", "out of memory", "database error", "cron not running".
---

# Troubleshoot Moodle Issues

## Instructions

### Diagnostic approach

1. **Identify symptoms** - What is the user experiencing?
2. **Check services** - Are all services running?
3. **Review logs** - What do error logs show?
4. **Test components** - Isolate the failing component
5. **Apply fix** - Implement solution
6. **Verify** - Confirm issue is resolved

### Quick health check

Run this script to quickly diagnose common issues:

```bash
#!/bin/bash
echo "=== Moodle Health Check ==="

# Services
echo "Services:"
systemctl is-active httpd && echo "  Apache running" || echo "  Apache DOWN"
systemctl is-active php-fpm && echo "  PHP-FPM running" || echo "  PHP-FPM DOWN"

# Memory
echo -e "\nMemory:"
free -h | grep -E "Mem:|Swap:"

# Disk
echo -e "\nDisk:"
df -h / /moodledata | grep -v "Filesystem"

# Database
source /root/moodle-config.env
if mysql -h $DB_HOST -u $DB_USER -p"$DB_PASSWORD" -e "SELECT 1;" &>/dev/null; then
    echo "  Database connected"
else
    echo "  Database connection failed"
fi

# Recent errors
echo -e "\nRecent errors (last 10):"
sudo tail -10 /var/log/httpd/moodle-error.log
```

## Common issues and solutions

### Issue 1: Site returns 503 Service Unavailable

**Symptoms:**
- Browser shows "503 Service Unavailable"
- Site was working before

**Diagnosis:**
```bash
# Check PHP-FPM status
sudo systemctl status php-fpm

# Check for OOM kills
sudo dmesg | grep -i "out of memory"
sudo journalctl -u php-fpm | grep -i "kill"
```

**Common cause:** PHP-FPM stopped (often due to OOM kill)

**Solution:**
```bash
# Restart PHP-FPM
sudo systemctl restart php-fpm

# Verify it's running
sudo systemctl status php-fpm

# If it keeps crashing, use /optimize-system skill
```

### Issue 2: Cannot connect to database

**Symptoms:**
- Error: "Could not connect to the database"
- Moodle shows database error page

**Diagnosis:**
```bash
# Load config
source /root/moodle-config.env

# Test connection
mysql -h $DB_HOST -u $DB_USER -p"$DB_PASSWORD" -e "SELECT 1;"
```

**Common causes:**
1. RDS stopped or rebooting
2. Security Group blocking port 3306
3. Wrong credentials in config.php

**Solutions:**

**If connection fails:**
```bash
# Check RDS status
aws rds describe-db-instances --db-instance-identifier your-db

# Check Security Group
aws ec2 describe-security-groups --group-ids sg-xxxxx

# Verify config.php has correct credentials
sudo grep dbhost /var/www/html/moodle/config.php
```

### Issue 3: Out of Memory errors

**Symptoms:**
- Site randomly goes down
- 503 errors intermittently
- `dmesg` shows OOM killer messages

**Diagnosis:**
```bash
# Check for OOM kills
sudo dmesg | grep -i "out of memory"
sudo dmesg | grep -i "killed process"

# Check current memory
free -h

# Check SWAP
swapon --show

# Check PHP-FPM config
sudo grep "pm.max_children" /etc/php-fpm.d/www.conf
```

**Root cause:** Too many PHP-FPM processes for available RAM

**Solution:**
```bash
# Use /optimize-system skill, or manually reduce max_children
# For 4GB RAM: max_children = 15
# For 8GB RAM: max_children = 35

sudo vim /etc/php-fpm.d/www.conf
# Change: pm.max_children = 15
sudo systemctl restart php-fpm
```

### Issue 4: Slow performance

**Symptoms:**
- Pages take > 5 seconds to load
- Users report timeouts
- Site feels sluggish

**Diagnosis:**
```bash
# Check CPU usage
top -bn1 | head -20

# Check memory
free -h

# Check disk I/O
iostat -x 1 5

# Check Apache connections
netstat -an | grep :80 | grep ESTABLISHED | wc -l

# Check PHP-FPM processes
ps aux | grep php-fpm | wc -l
```

**Common causes:**
1. High CPU usage
2. Memory exhaustion
3. Disk I/O bottleneck
4. Too many concurrent connections
5. Moodle cache not configured

**Solutions:**

**If CPU high:**
```bash
# Identify CPU hogs
ps aux --sort=-%cpu | head -10

# If Moodle cron processes
# Check for stuck cron jobs
ps aux | grep cron.php

# Kill stuck processes
sudo kill -9 <pid>
```

**If cache not optimized:**
```bash
# Purge and rebuild caches
sudo -u apache php /var/www/html/moodle/admin/cli/purge_caches.php
```

### Issue 5: Cron not running

**Symptoms:**
- Scheduled tasks not executing
- Admin notifications not sent
- Reports not generating

**Diagnosis:**
```bash
# Check cron file exists
cat /etc/cron.d/moodle

# Check cron service
sudo systemctl status crond

# Check for cron execution in logs
sudo grep cron.php /var/log/cron
```

**Solutions:**

**If cron file missing:**
```bash
# Create cron file
echo '* * * * * apache /usr/bin/php /var/www/html/moodle/admin/cli/cron.php > /dev/null 2>&1' | \
    sudo tee /etc/cron.d/moodle
sudo chmod 644 /etc/cron.d/moodle
```

**If crond not running:**
```bash
sudo systemctl start crond
sudo systemctl enable crond
```

### Issue 6: SSL certificate errors

**Symptoms:**
- Browser shows "Not Secure"
- Certificate expired warning
- HTTPS not working

**Diagnosis:**
```bash
# Check certificate status
sudo certbot certificates

# Check expiry date
openssl x509 -enddate -noout \
    -in /etc/letsencrypt/live/yourdomain.com/fullchain.pem

# Test renewal
sudo certbot renew --dry-run
```

**Solutions:**

**If expired:**
```bash
# Force renewal
sudo certbot renew --force-renewal

# Reload Apache
sudo systemctl reload httpd
```

### Issue 7: File upload errors

**Symptoms:**
- Cannot upload files to Moodle
- "Permission denied" errors
- Files not appearing after upload

**Diagnosis:**
```bash
# Check moodledata permissions
ls -la /moodledata

# Check ownership
stat /moodledata

# Check SELinux
sudo ausearch -m avc -ts recent | grep moodledata

# Check disk space
df -h /moodledata
```

**Solutions:**

**If permissions wrong:**
```bash
sudo chown -R apache:apache /moodledata
sudo chmod -R 770 /moodledata
```

**If SELinux blocking:**
```bash
sudo semanage fcontext -a -t httpd_sys_rw_content_t "/moodledata(/.*)?"
sudo restorecon -Rv /moodledata
```

### Issue 8: Moodle 5.1 muestra pagina en blanco o error 404

**Sintomas:**
- Pagina en blanco al acceder al sitio
- Error 404 en todas las URLs
- Moodle no carga despues de upgrade a 5.1

**Causa comun:** DocumentRoot no apunta a `/public`

**Diagnostico:**
```bash
# Verificar configuracion de Apache
grep -r "DocumentRoot" /etc/httpd/conf.d/

# Debe apuntar a /var/www/html/moodle/public
```

**Solucion:**
```bash
# Editar virtual host
sudo vim /etc/httpd/conf.d/moodle.conf

# Cambiar DocumentRoot de:
#   DocumentRoot /var/www/html/moodle
# A:
#   DocumentRoot /var/www/html/moodle/public

# Tambien actualizar el Directory block:
# <Directory /var/www/html/moodle/public>

sudo systemctl restart httpd
```

**Verificacion:**
```bash
# Confirmar que el directorio /public existe
ls -la /var/www/html/moodle/public/

# Probar acceso
curl -I http://localhost/
```

### View all logs

```bash
# Apache errors
sudo tail -50 /var/log/httpd/moodle-error.log

# PHP-FPM errors
sudo tail -50 /var/log/php-fpm/error.log

# System errors
sudo journalctl -p err -n 50

# Moodle errors (if configured)
sudo tail -50 /moodledata/moodle.log
```

### Prevention checklist

- [ ] Regular backups configured
- [ ] Memory monitoring active
- [ ] CloudWatch alarms set up
- [ ] SSL auto-renewal working
- [ ] Disk space monitored
- [ ] Log rotation configured
- [ ] System updates scheduled
- [ ] Performance baseline established

### When to escalate

**Contact AWS support if:**
- EC2 instance won't start
- EBS volume corruption
- RDS not responding to API calls
- Network connectivity issues

**Contact Moodle community if:**
- Core Moodle bug
- Plugin compatibility issues
- Database schema problems

## Example usage

**User:** "My Moodle site is down, showing 503 error"

**Assistant:** Let me diagnose the 503 error. This usually indicates PHP-FPM isn't running.

[Checks PHP-FPM status]

I found the issue: PHP-FPM was killed by the OOM (Out of Memory) killer.

**Root cause:** Your server ran out of memory because PHP-FPM was configured to allow 50 processes, but your server only has 4GB RAM.

**Immediate fix:**
[Restarts PHP-FPM]
Your site is back online now.

**Permanent solution:**
I'm running the `/optimize-system` skill to prevent this from happening again. This will:
1. Reduce PHP-FPM to 15 processes (safe for 4GB RAM)
2. Add 2GB SWAP as safety net
3. Set up hourly memory monitoring

[Runs optimization]

Done! Your site is now optimized and stable. Check memory anytime with: `free -h`
