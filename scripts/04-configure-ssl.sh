#!/bin/bash
################################################################################
# Moodle 5.1 SSL Configuration Script
# Version: 1.0.0
# Date: 2026-02-02
# Description: Configures Apache with Let's Encrypt SSL/TLS
################################################################################

set -e

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

# Logging
LOG_FILE="/var/log/moodle-ssl-setup.log"
exec > >(tee -a "$LOG_FILE")
exec 2>&1

echo "======================================================================"
echo "Moodle SSL/TLS Configuration"
echo "Started: $(date)"
echo "======================================================================"

# Check if running as root
if [ "$EUID" -ne 0 ]; then
    echo -e "${RED}ERROR: Please run as root or with sudo${NC}"
    exit 1
fi

################################################################################
# Load configuration
################################################################################

echo -e "\n${BLUE}[1/6] Loading configuration...${NC}"

if [ -f /root/moodle-config.env ]; then
    source /root/moodle-config.env
    echo "✓ Configuration loaded"
else
    echo -e "${RED}ERROR: /root/moodle-config.env not found${NC}"
    exit 1
fi

if [ -z "$DOMAIN_NAME" ] || [ -z "$ADMIN_EMAIL" ]; then
    echo -e "${RED}ERROR: DOMAIN_NAME and ADMIN_EMAIL must be set${NC}"
    exit 1
fi

echo "  Domain: $DOMAIN_NAME"
echo "  Admin Email: $ADMIN_EMAIL"

################################################################################
# Verify DNS
################################################################################

echo -e "\n${BLUE}[2/6] Verifying DNS configuration...${NC}"

# Get server's public IP
SERVER_IP=$(curl -s ifconfig.me)
echo "Server IP: $SERVER_IP"

# Resolve domain
DOMAIN_IP=$(dig +short "$DOMAIN_NAME" | tail -1)

if [ -z "$DOMAIN_IP" ]; then
    echo -e "${RED}ERROR: Cannot resolve $DOMAIN_NAME${NC}"
    echo "Make sure your domain's DNS points to $SERVER_IP"
    exit 1
fi

if [ "$DOMAIN_IP" != "$SERVER_IP" ]; then
    echo -e "${YELLOW}WARNING: DNS mismatch${NC}"
    echo "  Domain resolves to: $DOMAIN_IP"
    echo "  Server IP is: $SERVER_IP"
    echo ""
    read -p "Continue anyway? (yes/no): " -r
    if [[ ! $REPLY =~ ^[Yy][Ee][Ss]$ ]]; then
        echo "Aborted. Please update your DNS and try again."
        exit 1
    fi
else
    echo "✓ DNS is correctly configured"
fi

################################################################################
# Create Apache Virtual Host (HTTP only, for Let's Encrypt verification)
################################################################################

echo -e "\n${BLUE}[3/6] Creating Apache virtual host...${NC}"

VHOST_FILE="/etc/httpd/conf.d/moodle.conf"

cat > "$VHOST_FILE" << EOF
# Moodle Virtual Host Configuration
# Generated: $(date)

<VirtualHost *:80>
    ServerName $DOMAIN_NAME
    ServerAdmin $ADMIN_EMAIL

    DocumentRoot /var/www/html/moodle/public

    <Directory /var/www/html/moodle/public>
        Options -Indexes +FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>

    # Logging
    ErrorLog /var/log/httpd/moodle-error.log
    CustomLog /var/log/httpd/moodle-access.log combined

    # PHP-FPM
    <FilesMatch \.php$>
        SetHandler "proxy:unix:/run/php-fpm/www.sock|fcgi://localhost"
    </FilesMatch>
</VirtualHost>
EOF

# Test Apache configuration
echo "Testing Apache configuration..."
if httpd -t; then
    echo "✓ Apache configuration valid"
else
    echo -e "${RED}ERROR: Apache configuration invalid${NC}"
    exit 1
fi

# Reload Apache
systemctl reload httpd
echo "✓ Virtual host configured"

################################################################################
# Obtain Let's Encrypt SSL Certificate
################################################################################

echo -e "\n${BLUE}[4/6] Obtaining Let's Encrypt SSL certificate...${NC}"
echo "This may take a few minutes..."
echo ""

# Run certbot
certbot --apache \
    --non-interactive \
    --agree-tos \
    --email "$ADMIN_EMAIL" \
    --domains "$DOMAIN_NAME" \
    --redirect

if [ $? -eq 0 ]; then
    echo -e "${GREEN}✓ SSL certificate obtained successfully${NC}"
else
    echo -e "${RED}ERROR: Failed to obtain SSL certificate${NC}"
    echo ""
    echo "Common issues:"
    echo "  1. DNS not properly configured"
    echo "  2. Port 80 not accessible from internet"
    echo "  3. Domain already has certificate (check /etc/letsencrypt/)"
    exit 1
fi

################################################################################
# Enhance SSL configuration
################################################################################

echo -e "\n${BLUE}[5/6] Enhancing SSL configuration...${NC}"

# Certbot should have modified the vhost file, let's enhance it
SSL_VHOST_FILE="/etc/httpd/conf.d/moodle-le-ssl.conf"

if [ ! -f "$SSL_VHOST_FILE" ]; then
    echo -e "${YELLOW}WARNING: SSL vhost not found at expected location${NC}"
    echo "Certbot may have created it elsewhere"
else
    # Add security headers
    sed -i '/<\/VirtualHost>/i \
    # Security Headers\
    Header always set Strict-Transport-Security "max-age=31536000; includeSubDomains"\
    Header always set X-Frame-Options "SAMEORIGIN"\
    Header always set X-Content-Type-Options "nosniff"\
    Header always set X-XSS-Protection "1; mode=block"\
    Header always set Referrer-Policy "no-referrer-when-downgrade"\
\
    # Disable server signature\
    ServerSignature Off\
    ServerTokens Prod' "$SSL_VHOST_FILE"

    echo "✓ Security headers added"
fi

# Test configuration again
if httpd -t; then
    systemctl reload httpd
    echo "✓ Enhanced SSL configuration applied"
else
    echo -e "${YELLOW}WARNING: Configuration test failed after adding headers${NC}"
    echo "SSL is still working, but headers may not be applied"
fi

################################################################################
# Configure automatic renewal
################################################################################

echo -e "\n${BLUE}[6/6] Configuring automatic certificate renewal...${NC}"

# Certbot timer should be enabled by default, verify
if systemctl is-enabled certbot-renew.timer &>/dev/null; then
    echo "✓ Certbot renewal timer is enabled"
else
    systemctl enable certbot-renew.timer
    systemctl start certbot-renew.timer
    echo "✓ Enabled certbot renewal timer"
fi

# Test renewal process (dry run)
echo "Testing renewal process (dry run)..."
certbot renew --dry-run

if [ $? -eq 0 ]; then
    echo -e "${GREEN}✓ Renewal test successful${NC}"
else
    echo -e "${YELLOW}WARNING: Renewal test failed${NC}"
    echo "Automatic renewal may not work properly"
fi

################################################################################
# Update Moodle config for HTTPS
################################################################################

echo -e "\n${BLUE}Updating Moodle configuration for HTTPS...${NC}"

MOODLE_CONFIG="/var/www/html/moodle/config.php"

if [ -f "$MOODLE_CONFIG" ]; then
    # Update wwwroot to use https
    sed -i "s|http://$DOMAIN_NAME|https://$DOMAIN_NAME|g" "$MOODLE_CONFIG"
    echo "✓ Moodle config updated to use HTTPS"
else
    echo -e "${YELLOW}WARNING: Moodle config not found${NC}"
    echo "Update it manually to use https://$DOMAIN_NAME"
fi

################################################################################
# Test HTTPS access
################################################################################

echo -e "\n${BLUE}Testing HTTPS access...${NC}"

HTTP_CODE=$(curl -s -o /dev/null -w "%{http_code}" "https://$DOMAIN_NAME" --max-time 10)

if [ "$HTTP_CODE" = "200" ] || [ "$HTTP_CODE" = "303" ]; then
    echo -e "${GREEN}✓ HTTPS is working (HTTP $HTTP_CODE)${NC}"
else
    echo -e "${YELLOW}WARNING: HTTPS returned HTTP $HTTP_CODE${NC}"
    echo "The site may need additional configuration"
fi

################################################################################
# Summary
################################################################################

echo -e "\n${GREEN}======================================================================"
echo "SSL Configuration Complete!"
echo "======================================================================${NC}"

echo -e "\n🔒 SSL Certificate:"
echo "  Domain: $DOMAIN_NAME"
echo "  Issuer: Let's Encrypt"
echo "  Auto-renewal: Enabled"

echo -e "\n🌐 Access URLs:"
echo "  Site: https://$DOMAIN_NAME"
echo "  Admin: https://$DOMAIN_NAME/admin"

echo -e "\n📜 Certificate Files:"
echo "  Certificate: /etc/letsencrypt/live/$DOMAIN_NAME/fullchain.pem"
echo "  Private Key: /etc/letsencrypt/live/$DOMAIN_NAME/privkey.pem"

echo -e "\n🔧 Apache Configuration:"
echo "  HTTP VHost: $VHOST_FILE"
echo "  HTTPS VHost: $SSL_VHOST_FILE"
echo "  Error Log: /var/log/httpd/moodle-error.log"
echo "  Access Log: /var/log/httpd/moodle-access.log"

echo -e "\n🔄 Certificate Renewal:"
echo "  Automatic renewal is enabled via systemd timer"
echo "  Test renewal: sudo certbot renew --dry-run"
echo "  Force renewal: sudo certbot renew --force-renewal"

echo -e "\n📝 SSL Test:"
echo "  Test your SSL configuration at:"
echo "  https://www.ssllabs.com/ssltest/analyze.html?d=$DOMAIN_NAME"

echo -e "\n✅ Next Steps:"
echo "  1. Access your Moodle site: https://$DOMAIN_NAME"
echo "  2. Optimize system: ./05-optimize-system.sh"
echo "  3. Setup backups: ./06-setup-backups.sh"
echo ""

echo "Completed: $(date)"
echo "Log saved to: $LOG_FILE"

exit 0
