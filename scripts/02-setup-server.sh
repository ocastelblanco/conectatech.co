#!/bin/bash
################################################################################
# Moodle 5.1 Server Setup Script
# Version: 1.0.0
# Date: 2026-02-02
# Description: Installs and configures LAMP stack (Apache, PHP 8.4, MariaDB client)
################################################################################

set -e

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

# Logging
LOG_FILE="/var/log/moodle-setup.log"
exec > >(tee -a "$LOG_FILE")
exec 2>&1

echo "======================================================================"
echo "Moodle 5.1 Server Setup"
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

echo -e "\n${BLUE}[1/8] Loading configuration...${NC}"

if [ -f /root/moodle-config.env ]; then
    source /root/moodle-config.env
    echo "✓ Configuration loaded from /root/moodle-config.env"
else
    echo -e "${YELLOW}WARNING: /root/moodle-config.env not found${NC}"
    echo "Using defaults for some values"
fi

################################################################################
# Update system
################################################################################

echo -e "\n${BLUE}[2/8] Updating system packages...${NC}"

dnf update -y

echo "✓ System updated"

################################################################################
# Install Apache
################################################################################

echo -e "\n${BLUE}[3/8] Installing Apache 2.4...${NC}"

dnf install -y httpd httpd-tools mod_ssl

# Enable and start Apache
systemctl enable httpd
systemctl start httpd

# Verify installation
APACHE_VERSION=$(httpd -v | head -1)
echo "✓ Apache installed: $APACHE_VERSION"

################################################################################
# Install PHP 8.4
################################################################################

echo -e "\n${BLUE}[4/8] Installing PHP 8.4 and extensions...${NC}"

# Install PHP and required extensions for Moodle
dnf install -y \
    php \
    php-fpm \
    php-cli \
    php-common \
    php-mysqlnd \
    php-gd \
    php-xml \
    php-mbstring \
    php-intl \
    php-zip \
    php-soap \
    php-json \
    php-curl \
    php-opcache \
    php-xmlrpc \
    php-sodium

# Verify installation
PHP_VERSION=$(php -v | head -1)
echo "✓ PHP installed: $PHP_VERSION"

# Enable and start PHP-FPM
systemctl enable php-fpm
systemctl start php-fpm

echo "✓ PHP-FPM started"

################################################################################
# Install MariaDB Client
################################################################################

echo -e "\n${BLUE}[5/8] Installing MariaDB client...${NC}"

dnf install -y mariadb105

# Verify installation
MYSQL_VERSION=$(mysql --version)
echo "✓ MariaDB client installed: $MYSQL_VERSION"

################################################################################
# Install Additional Tools
################################################################################

echo -e "\n${BLUE}[6/8] Installing additional tools...${NC}"

dnf install -y \
    git \
    wget \
    curl \
    unzip \
    vim \
    nano \
    htop \
    tree \
    ncdu \
    rsync \
    certbot \
    python3-certbot-apache

echo "✓ Additional tools installed"

################################################################################
# Configure Firewall
################################################################################

echo -e "\n${BLUE}[7/8] Configuring firewall...${NC}"

# Start and enable firewalld
systemctl start firewalld
systemctl enable firewalld

# Open HTTP and HTTPS ports
firewall-cmd --permanent --add-service=http
firewall-cmd --permanent --add-service=https
firewall-cmd --reload

echo "✓ Firewall configured (HTTP, HTTPS open)"

################################################################################
# Create Directory Structure
################################################################################

echo -e "\n${BLUE}[8/8] Creating directory structure...${NC}"

# Create web root
mkdir -p /var/www/html/moodle

# Create moodledata if not exists
if [ ! -d /moodledata ]; then
    mkdir -p /moodledata
fi

# Set up directory structure in moodledata
mkdir -p /moodledata/{cache,localcache,sessions,temp,repository,backup}

# Set permissions
chown -R apache:apache /var/www/html/moodle
chown -R apache:apache /moodledata
chmod -R 755 /var/www/html/moodle
chmod -R 770 /moodledata

echo "✓ Directory structure created"
echo "  Web root: /var/www/html/moodle (DocumentRoot: /var/www/html/moodle/public)"
echo "  Data directory: /moodledata"

################################################################################
# Configure SELinux
################################################################################

echo -e "\n${BLUE}Configuring SELinux...${NC}"

# Allow Apache to connect to network (for RDS)
setsebool -P httpd_can_network_connect 1
setsebool -P httpd_can_network_connect_db 1

# Set SELinux contexts
semanage fcontext -a -t httpd_sys_rw_content_t "/moodledata(/.*)?"
restorecon -Rv /moodledata

echo "✓ SELinux configured"

################################################################################
# Create PHP info page (for testing)
################################################################################

echo -e "\n${BLUE}Creating PHP info page...${NC}"

cat > /var/www/html/info.php << 'EOF'
<?php
phpinfo();
?>
EOF

chown apache:apache /var/www/html/info.php

echo "✓ PHP info page created at http://your-ip/info.php"

################################################################################
# Test database connection (if RDS configured)
################################################################################

if [ -n "$DB_HOST" ] && [ "$DB_HOST" != "localhost" ]; then
    echo -e "\n${BLUE}Testing RDS connection...${NC}"

    # Test connection
    if mysql -h "$DB_HOST" -u "$DB_USER" -p"$DB_PASSWORD" -e "SELECT 1;" &>/dev/null; then
        echo -e "${GREEN}✓ RDS connection successful${NC}"
    else
        echo -e "${YELLOW}WARNING: Could not connect to RDS${NC}"
        echo "  Host: $DB_HOST"
        echo "  User: $DB_USER"
        echo "Check security group and credentials"
    fi
fi

################################################################################
# Summary
################################################################################

echo -e "\n${GREEN}======================================================================"
echo "Server Setup Complete!"
echo "======================================================================${NC}"

echo -e "\n📦 Installed Software:"
echo "  Apache: $(httpd -v | grep "Server version" | cut -d' ' -f3)"
echo "  PHP: $(php -v | head -1 | cut -d' ' -f2)"
echo "  MariaDB Client: $(mysql --version | cut -d' ' -f6)"

echo -e "\n📁 Directory Structure:"
echo "  Web Root: /var/www/html/moodle"
echo "  DocumentRoot (Apache): /var/www/html/moodle/public"
echo "  Moodledata: /moodledata"

echo -e "\n🔧 Services Status:"
systemctl is-active httpd && echo "  Apache: ✓ Running" || echo "  Apache: ✗ Not running"
systemctl is-active php-fpm && echo "  PHP-FPM: ✓ Running" || echo "  PHP-FPM: ✗ Not running"
systemctl is-active firewalld && echo "  Firewall: ✓ Running" || echo "  Firewall: ✗ Not running"

echo -e "\n🔍 Testing:"
echo "  PHP Info: http://$(curl -s ifconfig.me)/info.php"
echo "  (Delete this file before production: rm /var/www/html/info.php)"

if [ -n "$DOMAIN_NAME" ]; then
    echo -e "\n🌐 DNS Configuration:"
    echo "  Make sure $DOMAIN_NAME points to this server's IP"
fi

echo -e "\n📝 Next Steps:"
echo "  1. Verify PHP installation: curl http://localhost/info.php"
echo "  2. Install Moodle: ./03-install-moodle.sh"
echo "  3. Configure SSL: ./04-configure-ssl.sh"
echo ""

echo "Completed: $(date)"
echo "Log saved to: $LOG_FILE"

exit 0
