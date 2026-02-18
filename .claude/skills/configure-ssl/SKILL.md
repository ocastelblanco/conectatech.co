---
name: configure-ssl
description: Configures SSL/TLS with Let's Encrypt free certificate, implements security headers, and achieves A+ SSL Labs rating. Use when Moodle is installed and domain points to server, or when the user needs HTTPS, certificate renewal, or security headers. Triggers: "enable HTTPS", "configure SSL", "get SSL certificate", "secure site with TLS", "Let's Encrypt", "certificate renewal", "security headers", "HSTS".
---

# Configure SSL/HTTPS

## Instructions

### Context

This skill configures:
- Let's Encrypt SSL certificate (free, auto-renewing)
- Apache SSL virtual host
- Security headers for A+ rating
- HTTP to HTTPS redirect
- Automatic certificate renewal
- Moodle config update for HTTPS

### Required prerequisites

- Domain name pointing to server IP (DNS configured)
- Apache installed and running
- Port 80 and 443 open in Security Group
- Firewall allows HTTP/HTTPS traffic
- Certbot installed

### Available automation

Script location: `scripts/04-configure-ssl.sh`

The script automates:
1. DNS verification
2. Let's Encrypt certificate acquisition
3. Apache SSL configuration
4. Security headers implementation
5. HTTP to HTTPS redirect
6. Moodle config update
7. Renewal testing

### Critical requirement

**DNS MUST be configured first:**
```bash
# Your domain must resolve to server IP
dig +short yourdomain.com
# Should return: your.elastic.ip
```

If DNS is not configured, SSL acquisition will fail.

### Steps to execute

**1. Verify DNS:**
```bash
# Server IP
SERVER_IP=$(curl -s ifconfig.me)

# Domain resolution
DOMAIN_IP=$(dig +short yourdomain.com | tail -1)

# Must match
if [ "$DOMAIN_IP" != "$SERVER_IP" ]; then
    echo "ERROR: DNS mismatch!"
    echo "Domain resolves to: $DOMAIN_IP"
    echo "Server IP is: $SERVER_IP"
    exit 1
fi
```

**2. Create base virtual host:**
```bash
sudo vim /etc/httpd/conf.d/moodle.conf
```

```apache
<VirtualHost *:80>
    ServerName yourdomain.com
    ServerAdmin admin@yourdomain.com
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
</VirtualHost>
```

**3. Test and reload Apache:**
```bash
sudo httpd -t
sudo systemctl reload httpd
```

**4. Obtain SSL certificate:**
```bash
sudo certbot --apache \
    --non-interactive \
    --agree-tos \
    --email admin@yourdomain.com \
    --domains yourdomain.com \
    --redirect
```

Certbot will:
- Create SSL certificate
- Configure Apache SSL vhost
- Set up HTTP to HTTPS redirect
- Configure auto-renewal

**5. Enhance SSL configuration:**

Edit `/etc/httpd/conf.d/moodle-le-ssl.conf` and add before `</VirtualHost>`:

```apache
# Security Headers
Header always set Strict-Transport-Security "max-age=31536000; includeSubDomains; preload"
Header always set X-Frame-Options "SAMEORIGIN"
Header always set X-Content-Type-Options "nosniff"
Header always set X-XSS-Protection "1; mode=block"
Header always set Referrer-Policy "strict-origin-when-cross-origin"

# Remove server signature
ServerSignature Off
ServerTokens Prod
```

**6. Test and reload:**
```bash
sudo httpd -t
sudo systemctl reload httpd
```

**7. Update Moodle config:**
```bash
sudo vim /var/www/html/moodle/config.php
```

Change:
```php
$CFG->wwwroot = 'https://yourdomain.com';  # https, not http!
```

Purge caches:
```bash
sudo -u apache php /var/www/html/moodle/admin/cli/purge_caches.php
```

**8. Verify renewal timer:**
```bash
sudo systemctl status certbot-renew.timer
sudo systemctl enable certbot-renew.timer

# Test renewal
sudo certbot renew --dry-run
```

### Security headers explained

| Header | Purpose | Value |
|--------|---------|-------|
| HSTS | Force HTTPS for 1 year | max-age=31536000 |
| X-Frame-Options | Prevent clickjacking | SAMEORIGIN |
| X-Content-Type-Options | Prevent MIME sniffing | nosniff |
| X-XSS-Protection | Enable XSS filter | 1; mode=block |
| Referrer-Policy | Control referrer info | strict-origin |

### Testing SSL configuration

**1. Test HTTPS access:**
```bash
curl -I https://yourdomain.com
```

Should return `HTTP/2 200` or `HTTP/1.1 200`

**2. Test redirect:**
```bash
curl -I http://yourdomain.com
```

Should return `301` redirect to HTTPS

**3. Test headers:**
```bash
curl -I https://yourdomain.com | grep -i "strict-transport"
```

Should show HSTS header

**4. SSL Labs test:**
Visit: `https://www.ssllabs.com/ssltest/analyze.html?d=yourdomain.com`

Target: **A or A+ rating**

**5. Security Headers test:**
Visit: `https://securityheaders.com/?q=yourdomain.com`

Target: **A rating**

### Certificate renewal

Let's Encrypt certificates expire in 90 days but renew automatically:

```bash
# Renewal runs twice daily via systemd timer
sudo systemctl list-timers certbot-renew.timer

# Manual renewal test
sudo certbot renew --dry-run

# Force renewal (if needed)
sudo certbot renew --force-renewal
```

### Post-renewal hook

Create automatic Apache reload after renewal:

```bash
sudo mkdir -p /etc/letsencrypt/renewal-hooks/post
sudo vim /etc/letsencrypt/renewal-hooks/post/reload-apache.sh
```

```bash
#!/bin/bash
systemctl reload httpd
echo "$(date): Apache reloaded after cert renewal" >> /var/log/certbot-renewal.log
```

```bash
sudo chmod +x /etc/letsencrypt/renewal-hooks/post/reload-apache.sh
```

### Certificate locations

After successful configuration:
- Certificate: `/etc/letsencrypt/live/yourdomain.com/fullchain.pem`
- Private key: `/etc/letsencrypt/live/yourdomain.com/privkey.pem`
- Chain: `/etc/letsencrypt/live/yourdomain.com/chain.pem`

### Troubleshooting

**Error: Port 80 not accessible**
```bash
# Check Security Group
aws ec2 describe-security-groups --group-ids sg-xxxxx

# Check firewall
sudo firewall-cmd --list-services
sudo firewall-cmd --permanent --add-service=http
sudo firewall-cmd --reload
```

**Error: DNS validation failed**
```bash
# Verify DNS
dig yourdomain.com
nslookup yourdomain.com

# Wait for DNS propagation (up to 48h, usually minutes)
```

**Error: Certificate already exists**
```bash
# List certificates
sudo certbot certificates

# Renew specific certificate
sudo certbot renew --cert-name yourdomain.com

# Delete and recreate (careful!)
sudo certbot delete --cert-name yourdomain.com
# Then run certbot again
```

**Error: Apache won't start after SSL**
```bash
# Test configuration
sudo httpd -t

# Check error logs
sudo tail -50 /var/log/httpd/error_log

# Verify certificate files exist
sudo ls -la /etc/letsencrypt/live/yourdomain.com/
```

### Expected output

```
DNS verified: yourdomain.com -> 52.x.x.x
Apache virtual host configured
Let's Encrypt certificate obtained
  - Domain: yourdomain.com
  - Expires: 90 days from now
  - Auto-renewal: Enabled
Security headers configured
HTTP to HTTPS redirect active
Moodle config updated to HTTPS
SSL configuration tested

SSL Labs rating: A+
Security Headers rating: A

Certificate details:
  Issuer: Let's Encrypt
  Valid until: [date]
  Auto-renewal: Enabled (systemd timer)

Your Moodle site is now secured with HTTPS!
Access at: https://yourdomain.com
```

## Example usage

**User:** "I need to enable HTTPS for my Moodle site at moodle.example.com"

**Assistant:** I'll configure SSL/HTTPS with Let's Encrypt for moodle.example.com.

First, let me verify your DNS is configured...

[Checks DNS: moodle.example.com -> server IP]
DNS is correctly configured

[Creates Apache virtual host]
[Obtains Let's Encrypt certificate]
[Configures security headers]
[Updates Moodle config]
[Tests configuration]

SSL configured successfully! Your site now has:
- Valid SSL certificate from Let's Encrypt
- A+ rating on SSL Labs
- Auto-renewal enabled (certificate renews automatically)
- All traffic redirected to HTTPS

Access your site at: https://moodle.example.com

Certificate expires in 90 days but will auto-renew.
