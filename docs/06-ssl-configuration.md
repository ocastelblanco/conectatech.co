# Configuración SSL/TLS para Moodle 5.1

Guía completa para configurar HTTPS con Let's Encrypt y optimizar seguridad SSL/TLS.

## 🎯 Objetivos

- Obtener certificado SSL gratuito de Let's Encrypt
- Configurar Apache para HTTPS
- Optimizar seguridad SSL/TLS (A+ rating)
- Configurar renovación automática
- Implementar security headers

## 📋 Prerequisitos

- ✅ Servidor configurado con Apache
- ✅ Dominio apuntando a IP del servidor
- ✅ Puerto 80 accesible desde internet
- ✅ Port 443 abierto en Security Group
- ✅ Moodle instalado y funcionando

## 🚀 Instalación Automatizada

### Script Recomendado

```bash
# Conectar al servidor
ssh -i ~/.ssh/your-key.pem ec2-user@your-elastic-ip

# Ejecutar script
sudo /path/to/skills/scripts/04-configure-ssl.sh
```

El script automatiza todo el proceso descrito abajo.

## 📖 Configuración Manual

### 1. Verificar DNS

```bash
# Obtener IP del servidor
curl ifconfig.me

# Verificar DNS
dig +short yourdomain.com
nslookup yourdomain.com

# Ambos deben devolver la misma IP
```

**Si no coinciden:**
- Actualizar registros DNS
- Esperar propagación (5-60 minutos)

### 2. Instalar Certbot

```bash
# Certbot ya debería estar instalado
# Si no:
sudo dnf install certbot python3-certbot-apache -y

# Verificar instalación
certbot --version
```

### 3. Configurar Virtual Host Base

Antes de obtener SSL, necesitas un vhost HTTP funcional:

```bash
sudo vim /etc/httpd/conf.d/moodle.conf
```

```apache
<VirtualHost *:80>
    ServerName yourdomain.com
    ServerAdmin admin@yourdomain.com
    # IMPORTANTE: En Moodle 5.1, DocumentRoot apunta a /public
    DocumentRoot /var/www/html/moodle/public

    <Directory /var/www/html/moodle/public>
        Options -Indexes +FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>

    # PHP-FPM
    <FilesMatch \.php$>
        SetHandler "proxy:unix:/run/php-fpm/www.sock|fcgi://localhost"
    </FilesMatch>

    # Logging
    ErrorLog /var/log/httpd/moodle-error.log
    CustomLog /var/log/httpd/moodle-access.log combined
</VirtualHost>
```

```bash
# Test y reload
sudo httpd -t
sudo systemctl reload httpd
```

### 4. Obtener Certificado Let's Encrypt

```bash
# Método automático (recomendado)
sudo certbot --apache \
    --non-interactive \
    --agree-tos \
    --email admin@yourdomain.com \
    --domains yourdomain.com \
    --redirect

# Opciones:
# --apache: Plugin para Apache
# --non-interactive: Sin prompts
# --agree-tos: Acepta términos
# --email: Email para notificaciones
# --domains: Dominio(s) a certificar
# --redirect: Crea redirect HTTP→HTTPS automático
```

**Salida esperada:**
```
Requesting a certificate for yourdomain.com
Successfully received certificate.
Certificate is saved at: /etc/letsencrypt/live/yourdomain.com/fullchain.pem
Key is saved at: /etc/letsencrypt/live/yourdomain.com/privkey.pem
```

### 5. Verificar Configuración HTTPS

Certbot crea automáticamente un nuevo archivo:

```bash
# Ver configuración SSL generada
sudo cat /etc/httpd/conf.d/moodle-le-ssl.conf
```

Contenido típico:
```apache
<IfModule mod_ssl.c>
<VirtualHost *:443>
    ServerName yourdomain.com
    ServerAdmin admin@yourdomain.com
    # IMPORTANTE: En Moodle 5.1, DocumentRoot apunta a /public
    DocumentRoot /var/www/html/moodle/public

    <Directory /var/www/html/moodle/public>
        Options -Indexes +FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>

    <FilesMatch \.php$>
        SetHandler "proxy:unix:/run/php-fpm/www.sock|fcgi://localhost"
    </FilesMatch>

    ErrorLog /var/log/httpd/moodle-error.log
    CustomLog /var/log/httpd/moodle-access.log combined

    # SSL Configuration (added by Certbot)
    SSLEngine on
    Include /etc/letsencrypt/options-ssl-apache.conf
    SSLCertificateFile /etc/letsencrypt/live/yourdomain.com/fullchain.pem
    SSLCertificateKeyFile /etc/letsencrypt/live/yourdomain.com/privkey.pem
</VirtualHost>
</IfModule>
```

## 🔒 Optimización SSL/TLS

### Security Headers

Agregar al final del VirtualHost HTTPS (antes de `</VirtualHost>`):

```apache
# Security Headers
Header always set Strict-Transport-Security "max-age=31536000; includeSubDomains; preload"
Header always set X-Frame-Options "SAMEORIGIN"
Header always set X-Content-Type-Options "nosniff"
Header always set X-XSS-Protection "1; mode=block"
Header always set Referrer-Policy "strict-origin-when-cross-origin"
Header always set Permissions-Policy "geolocation=(), microphone=(), camera=()"

# Remove server signature
ServerSignature Off
ServerTokens Prod
```

**Descripción de headers:**

| Header | Propósito | Valor |
|--------|-----------|-------|
| `Strict-Transport-Security` | Forzar HTTPS | 1 año, incluye subdominios |
| `X-Frame-Options` | Prevenir clickjacking | Solo mismo origen |
| `X-Content-Type-Options` | Prevenir MIME sniffing | No sniff |
| `X-XSS-Protection` | Filtro XSS del browser | Activado + bloqueo |
| `Referrer-Policy` | Controlar referrer | Strict cross-origin |
| `Permissions-Policy` | APIs del navegador | Deshabilitar peligrosas |

### SSL Configuration File

Let's Encrypt crea `/etc/letsencrypt/options-ssl-apache.conf` con defaults seguros:

```apache
# /etc/letsencrypt/options-ssl-apache.conf

# Protocols
SSLProtocol             all -SSLv2 -SSLv3 -TLSv1 -TLSv1.1
SSLHonorCipherOrder     off

# Cipher suites (Mozilla intermediate)
SSLCipherSuite          ECDHE-ECDSA-AES128-GCM-SHA256:ECDHE-RSA-AES128-GCM-SHA256:ECDHE-ECDSA-AES256-GCM-SHA384:ECDHE-RSA-AES256-GCM-SHA384:ECDHE-ECDSA-CHACHA20-POLY1305:ECDHE-RSA-CHACHA20-POLY1305:DHE-RSA-AES128-GCM-SHA256:DHE-RSA-AES256-GCM-SHA384

# Session cache
SSLSessionCache         shmcb:/run/httpd/sslcache(512000)
SSLSessionCacheTimeout  300

# OCSP Stapling
SSLUseStapling          on
SSLStaplingCache        "shmcb:logs/stapling-cache(150000)"
```

**Para A+ rating, crear archivo personalizado:**

```bash
sudo vim /etc/httpd/conf.d/ssl-improvements.conf
```

```apache
# Enhanced SSL Configuration

# Enable OCSP Stapling
SSLUseStapling on
SSLStaplingResponderTimeout 5
SSLStaplingReturnResponderErrors off
SSLStaplingCache shmcb:/var/run/ocsp(128000)

# Enable HTTP/2
Protocols h2 http/1.1

# Session tickets (disable for forward secrecy)
SSLSessionTickets off

# Compression (disable to prevent CRIME attack)
SSLCompression off
```

### Aplicar Cambios

```bash
# Test configuración
sudo httpd -t

# Si OK, reload
sudo systemctl reload httpd
```

## 🔄 Renovación Automática

### Verificar Timer de Renovación

```bash
# Verificar timer systemd
sudo systemctl status certbot-renew.timer

# Si no está activo:
sudo systemctl enable certbot-renew.timer
sudo systemctl start certbot-renew.timer

# Ver próxima ejecución
sudo systemctl list-timers certbot-renew.timer
```

### Probar Renovación (Dry Run)

```bash
# Simular renovación
sudo certbot renew --dry-run

# Salida esperada:
# Congratulations, all simulated renewals succeeded
```

### Renovación Manual

```bash
# Forzar renovación
sudo certbot renew --force-renewal

# Renovar solo un dominio
sudo certbot renew --cert-name yourdomain.com
```

### Script de Post-Renovación

Crear hook para acciones post-renovación:

```bash
sudo mkdir -p /etc/letsencrypt/renewal-hooks/post
sudo vim /etc/letsencrypt/renewal-hooks/post/reload-apache.sh
```

```bash
#!/bin/bash
# Reload Apache after certificate renewal
systemctl reload httpd
echo "$(date): Apache reloaded after certificate renewal" >> /var/log/certbot-renewal.log
```

```bash
sudo chmod +x /etc/letsencrypt/renewal-hooks/post/reload-apache.sh
```

## 📝 Actualizar Moodle Config

Actualizar `config.php` para usar HTTPS:

```bash
sudo vim /var/www/html/moodle/config.php
```

```php
// Cambiar de http:// a https://
$CFG->wwwroot = 'https://yourdomain.com';

// Force HTTPS
$CFG->sslproxy = false;  // false si SSL termina en Apache (caso normal)

// Si usas proxy/load balancer:
// $CFG->sslproxy = true;
```

**Purge caches:**
```bash
sudo -u apache php /var/www/html/moodle/admin/cli/purge_caches.php
```

## 🧪 Testing y Validación

### Test SSL Labs

Verificar rating SSL:
```
https://www.ssllabs.com/ssltest/analyze.html?d=yourdomain.com
```

**Objetivo:** A o A+ rating

**Criterios A+:**
- ✅ TLS 1.2 o superior
- ✅ Forward secrecy
- ✅ HSTS con long max-age
- ✅ Sin vulnerabilidades conocidas

### Test Security Headers

```bash
# Verificar headers
curl -I https://yourdomain.com

# Con detalles
curl -v https://yourdomain.com 2>&1 | grep -i "< "
```

O usar: https://securityheaders.com/

### Test Redirect HTTP→HTTPS

```bash
# Debe redirigir a HTTPS
curl -I http://yourdomain.com

# Salida esperada:
# HTTP/1.1 301 Moved Permanently
# Location: https://yourdomain.com/
```

### Verificar Certificado

```bash
# Info del certificado
openssl s_client -connect yourdomain.com:443 -servername yourdomain.com < /dev/null 2>/dev/null | openssl x509 -noout -text

# Fecha de expiración
openssl s_client -connect yourdomain.com:443 -servername yourdomain.com < /dev/null 2>/dev/null | openssl x509 -noout -dates

# Issuer
openssl s_client -connect yourdomain.com:443 -servername yourdomain.com < /dev/null 2>/dev/null | openssl x509 -noout -issuer
```

## 🌐 Múltiples Dominios

### Certificado SAN (Subject Alternative Names)

```bash
# Certificado para múltiples dominios
sudo certbot --apache \
    --domains yourdomain.com,www.yourdomain.com,subdomain.yourdomain.com \
    --email admin@yourdomain.com

# O agregar dominio a certificado existente
sudo certbot --apache \
    --cert-name yourdomain.com \
    --expand \
    --domains yourdomain.com,www.yourdomain.com,new.yourdomain.com
```

### Wildcard Certificate

```bash
# Requiere DNS challenge
sudo certbot certonly \
    --manual \
    --preferred-challenges dns \
    --domains "*.yourdomain.com,yourdomain.com" \
    --email admin@yourdomain.com

# Sigue instrucciones para crear registro TXT en DNS
# Después, configurar Apache manualmente
```

## 🚨 Troubleshooting

### Error: Port 80 not accessible

**Causa:** Firewall o Security Group bloqueando

**Solución:**
```bash
# Check Security Group
aws ec2 describe-security-groups --group-ids sg-xxxxx

# Check firewall local
sudo firewall-cmd --list-all

# Abrir puerto si necesario
sudo firewall-cmd --permanent --add-service=http
sudo firewall-cmd --reload
```

### Error: DNS mismatch

**Causa:** DNS no apunta correctamente

**Solución:**
```bash
# Verificar DNS
dig +short yourdomain.com

# Esperar propagación
# Usar: https://dnschecker.org/
```

### Warning: Certificate expired

**Causa:** Renovación automática falló

**Solución:**
```bash
# Renovar manualmente
sudo certbot renew --force-renewal

# Verificar timer
sudo systemctl status certbot-renew.timer

# Ver logs
sudo journalctl -u certbot-renew
```

### Apache no inicia después de SSL

**Causa:** Sintaxis error en config

**Solución:**
```bash
# Test config
sudo httpd -t

# Ver error específico
sudo httpd -t 2>&1

# Revisar logs
sudo tail -50 /var/log/httpd/error_log
```

## 📊 Monitoreo SSL

### Script de Verificación

```bash
# Crear script de monitoreo
sudo vim /usr/local/bin/check-ssl-expiry.sh
```

```bash
#!/bin/bash
DOMAIN="yourdomain.com"
CERT_FILE="/etc/letsencrypt/live/$DOMAIN/fullchain.pem"
ALERT_DAYS=30

if [ ! -f "$CERT_FILE" ]; then
    echo "ERROR: Certificate not found"
    exit 1
fi

EXPIRY=$(openssl x509 -enddate -noout -in "$CERT_FILE" | cut -d= -f2)
EXPIRY_EPOCH=$(date -d "$EXPIRY" +%s)
NOW_EPOCH=$(date +%s)
DAYS_LEFT=$(( ($EXPIRY_EPOCH - $NOW_EPOCH) / 86400 ))

echo "SSL Certificate Expiry Check"
echo "Domain: $DOMAIN"
echo "Expires: $EXPIRY"
echo "Days left: $DAYS_LEFT"

if [ $DAYS_LEFT -lt $ALERT_DAYS ]; then
    echo "WARNING: Certificate expires in $DAYS_LEFT days!"
    # Enviar alerta aquí (email, SNS, etc.)
fi
```

```bash
sudo chmod +x /usr/local/bin/check-ssl-expiry.sh

# Agregar a cron (diario)
echo "0 8 * * * root /usr/local/bin/check-ssl-expiry.sh >> /var/log/ssl-check.log 2>&1" | sudo tee /etc/cron.d/ssl-check
```

### CloudWatch Alarm

```bash
# Crear métrica personalizada
aws cloudwatch put-metric-data \
    --namespace Moodle/SSL \
    --metric-name DaysUntilExpiry \
    --value $DAYS_LEFT \
    --dimensions Domain=yourdomain.com

# Crear alarma
aws cloudwatch put-metric-alarm \
    --alarm-name ssl-expiry-warning \
    --alarm-description "SSL certificate expires soon" \
    --namespace Moodle/SSL \
    --metric-name DaysUntilExpiry \
    --dimensions Name=Domain,Value=yourdomain.com \
    --statistic Average \
    --period 86400 \
    --threshold 30 \
    --comparison-operator LessThanThreshold \
    --evaluation-periods 1
```

## 🔐 Best Practices

### Checklist de Seguridad SSL

- [x] TLS 1.2+ enabled, older versions disabled
- [x] Strong cipher suites only
- [x] HSTS header with long max-age
- [x] Security headers implemented
- [x] HTTP→HTTPS redirect active
- [x] Certificate auto-renewal working
- [x] OCSP Stapling enabled
- [x] HTTP/2 enabled
- [x] Certificate monitoring in place
- [x] A+ rating on SSL Labs

### Recomendaciones

1. **Verificar renovación regularmente**
   ```bash
   sudo certbot renew --dry-run
   ```

2. **Monitorear expiración**
   - Script diario
   - CloudWatch alarm
   - Email alerts

3. **Test después de cambios**
   - SSL Labs scan
   - Security Headers check
   - Manual browser test

4. **Backup de certificados**
   ```bash
   sudo tar -czf /var/backups/letsencrypt-$(date +%Y%m%d).tar.gz /etc/letsencrypt
   ```

5. **Documentar cambios**
   - Fecha de emisión
   - Fecha de renovación
   - Cambios en configuración

## 📚 Referencias

- [Let's Encrypt](https://letsencrypt.org/)
- [Certbot Documentation](https://eff-certbot.readthedocs.io/)
- [Mozilla SSL Configuration Generator](https://ssl-config.mozilla.org/)
- [SSL Labs Test](https://www.ssllabs.com/ssltest/)
- [Security Headers](https://securityheaders.com/)

## ✅ Next Steps

1. **Test tu sitio:** https://yourdomain.com
2. **Verificar rating:** SSL Labs scan
3. **Configurar backups:** [07-backups.md](07-backups.md)
4. **Setup monitoring:** [08-monitoring.md](08-monitoring.md)

---

**Fecha:** 2026-02-11
**Versión:** 1.1.0
