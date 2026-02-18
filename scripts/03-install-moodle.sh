#!/bin/bash
################################################################################
# Moodle 5.1 Installation Script
# Version: 1.0.0
# Date: 2026-02-02
# Description: Downloads and installs Moodle 5.1
################################################################################

set -e

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

# Logging
LOG_FILE="/var/log/moodle-installation.log"
exec > >(tee -a "$LOG_FILE")
exec 2>&1

echo "======================================================================"
echo "Moodle 5.1 Installation"
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

echo -e "\n${BLUE}[1/7] Loading configuration...${NC}"

if [ -f /root/moodle-config.env ]; then
    source /root/moodle-config.env
    echo "✓ Configuration loaded"
else
    echo -e "${RED}ERROR: /root/moodle-config.env not found${NC}"
    echo "This file should have been created by Terraform"
    exit 1
fi

# Validate required variables
REQUIRED_VARS=("DOMAIN_NAME" "ADMIN_EMAIL" "DB_HOST" "DB_NAME" "DB_USER" "DB_PASSWORD")
for var in "${REQUIRED_VARS[@]}"; do
    if [ -z "${!var}" ]; then
        echo -e "${RED}ERROR: Required variable $var is not set${NC}"
        exit 1
    fi
done

echo "  Domain: $DOMAIN_NAME"
echo "  Database: $DB_HOST"

################################################################################
# Check prerequisites
################################################################################

echo -e "\n${BLUE}[2/7] Checking prerequisites...${NC}"

# Check Apache
if ! systemctl is-active --quiet httpd; then
    echo -e "${RED}ERROR: Apache is not running${NC}"
    exit 1
fi
echo "✓ Apache is running"

# Check PHP-FPM
if ! systemctl is-active --quiet php-fpm; then
    echo -e "${RED}ERROR: PHP-FPM is not running${NC}"
    exit 1
fi
echo "✓ PHP-FPM is running"

# Check PHP version
PHP_VERSION=$(php -r "echo PHP_VERSION;" | cut -d'.' -f1,2)
if (( $(echo "$PHP_VERSION < 8.2" | bc -l) )); then
    echo -e "${RED}ERROR: PHP 8.2+ required, found $PHP_VERSION${NC}"
    exit 1
fi
echo "✓ PHP version: $PHP_VERSION"

# Test database connection
echo -e "\n${BLUE}Testing database connection...${NC}"
if ! mysql -h "$DB_HOST" -u "$DB_USER" -p"$DB_PASSWORD" -e "USE $DB_NAME;" 2>/dev/null; then
    echo -e "${RED}ERROR: Cannot connect to database${NC}"
    echo "  Host: $DB_HOST"
    echo "  Database: $DB_NAME"
    echo "  User: $DB_USER"
    exit 1
fi
echo "✓ Database connection successful"

################################################################################
# Download Moodle
################################################################################

echo -e "\n${BLUE}[3/7] Downloading Moodle...${NC}"

MOODLE_DIR="/var/www/html/moodle"
MOODLE_VERSION="${MOODLE_VERSION:-MOODLE_51_STABLE}"

# Backup existing installation if present
if [ -d "$MOODLE_DIR" ] && [ "$(ls -A $MOODLE_DIR)" ]; then
    echo -e "${YELLOW}Existing Moodle installation found${NC}"
    BACKUP_DIR="/var/backups/moodle-backup-$(date +%Y%m%d-%H%M%S)"
    mkdir -p "$(dirname $BACKUP_DIR)"
    echo "Backing up to: $BACKUP_DIR"
    cp -a "$MOODLE_DIR" "$BACKUP_DIR"
    rm -rf "$MOODLE_DIR"/*
fi

# Clone Moodle from Git
echo "Cloning Moodle $MOODLE_VERSION..."
cd /var/www/html
if [ ! -d "$MOODLE_DIR/.git" ]; then
    git clone --depth=1 --branch "$MOODLE_VERSION" https://github.com/moodle/moodle.git moodle
else
    cd moodle
    git fetch origin "$MOODLE_VERSION"
    git checkout "$MOODLE_VERSION"
    git pull
fi

# Get Moodle version
cd "$MOODLE_DIR"
MOODLE_RELEASE=$(grep '$release' version.php | cut -d"'" -f2)
echo "✓ Moodle downloaded: $MOODLE_RELEASE"

################################################################################
# Configure permissions
################################################################################

echo -e "\n${BLUE}[4/7] Configuring permissions...${NC}"

chown -R apache:apache "$MOODLE_DIR"
chmod -R 755 "$MOODLE_DIR"

chown -R apache:apache /moodledata
chmod -R 770 /moodledata

echo "✓ Permissions configured"

################################################################################
# Create Moodle config.php
################################################################################

echo -e "\n${BLUE}[5/7] Creating config.php...${NC}"

# Generate a random password salt
PASSWORD_SALT=$(openssl rand -hex 32)

cat > "$MOODLE_DIR/config.php" << EOF
<?php  // Moodle configuration file
unset(\$CFG);
global \$CFG;
\$CFG = new stdClass();

\$CFG->dbtype    = 'mariadb';
\$CFG->dblibrary = 'native';
\$CFG->dbhost    = '$DB_HOST';
\$CFG->dbname    = '$DB_NAME';
\$CFG->dbuser    = '$DB_USER';
\$CFG->dbpass    = '$DB_PASSWORD';
\$CFG->prefix    = 'mdl_';
\$CFG->dboptions = array(
    'dbpersist' => 0,
    'dbport' => 3306,
    'dbsocket' => '',
    'dbcollation' => 'utf8mb4_unicode_ci',
);

\$CFG->wwwroot   = 'https://$DOMAIN_NAME';
\$CFG->dataroot  = '/moodledata';
\$CFG->admin     = 'admin';

\$CFG->directorypermissions = 0770;

// Performance and caching
\$CFG->cachedir = '/moodledata/cache';
\$CFG->localcachedir = '/moodledata/localcache';
\$CFG->tempdir = '/moodledata/temp';

// Session handling
\$CFG->session_handler_class = '\core\session\database';
\$CFG->session_database_acquire_lock_timeout = 120;

// Performance settings
\$CFG->enablecaching = true;
\$CFG->cachejs = true;
\$CFG->yuicomboloading = true;

// Disable theme designer mode (important for production)
\$CFG->themedesignermode = false;

// Password salt
\$CFG->passwordsaltmain = '$PASSWORD_SALT';

require_once(__DIR__ . '/lib/setup.php');
EOF

chown apache:apache "$MOODLE_DIR/config.php"
chmod 640 "$MOODLE_DIR/config.php"

echo "✓ config.php created"

################################################################################
# Run Moodle CLI installer
################################################################################

echo -e "\n${BLUE}[6/7] Running Moodle installer...${NC}"
echo "This may take several minutes..."
echo ""

# Set defaults if not provided
MOODLE_ADMIN_USER="${MOODLE_ADMIN_USER:-admin}"
MOODLE_ADMIN_PASSWORD="${MOODLE_ADMIN_PASSWORD:-Admin123!}"
MOODLE_SITE_NAME="${MOODLE_SITE_NAME:-Moodle LMS}"
MOODLE_SITE_SUMMARY="${MOODLE_SITE_SUMMARY:-Learning Management System}"

# Run installation
sudo -u apache /usr/bin/php "$MOODLE_DIR/admin/cli/install_database.php" \
    --lang=en \
    --adminuser="$MOODLE_ADMIN_USER" \
    --adminpass="$MOODLE_ADMIN_PASSWORD" \
    --adminemail="$ADMIN_EMAIL" \
    --fullname="$MOODLE_SITE_NAME" \
    --shortname="$(echo $MOODLE_SITE_NAME | cut -d' ' -f1)" \
    --summary="$MOODLE_SITE_SUMMARY" \
    --agree-license

if [ $? -eq 0 ]; then
    echo -e "${GREEN}✓ Moodle database installed successfully${NC}"
else
    echo -e "${RED}ERROR: Moodle installation failed${NC}"
    echo "Check the logs above for details"
    exit 1
fi

################################################################################
# Configure Moodle cron
################################################################################

echo -e "\n${BLUE}[7/7] Configuring Moodle cron...${NC}"

CRON_FILE="/etc/cron.d/moodle"

cat > "$CRON_FILE" << EOF
# Moodle scheduled tasks
# Runs every minute as recommended by Moodle
* * * * * apache /usr/bin/php $MOODLE_DIR/admin/cli/cron.php > /dev/null 2>&1
EOF

chmod 644 "$CRON_FILE"

echo "✓ Cron configured to run every minute"

# Run cron once manually to verify
echo "Testing cron execution..."
sudo -u apache /usr/bin/php "$MOODLE_DIR/admin/cli/cron.php" > /tmp/moodle-cron-test.log 2>&1

if [ $? -eq 0 ]; then
    echo "✓ Cron test successful"
else
    echo -e "${YELLOW}WARNING: Cron test had issues, check /tmp/moodle-cron-test.log${NC}"
fi

################################################################################
# Summary
################################################################################

echo -e "\n${GREEN}======================================================================"
echo "Moodle Installation Complete!"
echo "======================================================================${NC}"

echo -e "\n📦 Moodle Details:"
echo "  Version: $MOODLE_RELEASE"
echo "  Directory: $MOODLE_DIR"
echo "  Data Directory: /moodledata"

echo -e "\n🔐 Admin Credentials:"
echo "  Username: $MOODLE_ADMIN_USER"
echo "  Password: $MOODLE_ADMIN_PASSWORD"
echo "  Email: $ADMIN_EMAIL"

echo -e "\n🌐 Access URLs:"
echo "  Site URL: https://$DOMAIN_NAME"
echo "  Admin URL: https://$DOMAIN_NAME/admin"

echo -e "\n⚠️  Important Next Steps:"
echo ""
echo "  1. Configure Apache virtual host:"
echo "     ./04-configure-ssl.sh"
echo "     IMPORTANTE: DocumentRoot debe apuntar a /var/www/html/moodle/public"
echo ""
echo "  2. Optimize system:"
echo "     ./05-optimize-system.sh"
echo ""
echo "  3. Setup backups:"
echo "     ./06-setup-backups.sh"
echo ""
echo "  4. Access Moodle and complete initial setup:"
echo "     https://$DOMAIN_NAME"
echo ""
echo "  5. IMPORTANT: Change admin password after first login!"
echo ""
echo "  6. Moodle 5.1: Verificar que Apache DocumentRoot apunta a"
echo "     /var/www/html/moodle/public (no a /var/www/html/moodle)"
echo ""

echo -e "\n📝 Useful Commands:"
echo "  View cron logs: sudo tail -f /var/log/cron"
echo "  View Moodle logs: sudo tail -f /moodledata/moodledata.log"
echo "  Run cron manually: sudo -u apache php $MOODLE_DIR/admin/cli/cron.php"
echo "  Purge caches: sudo -u apache php $MOODLE_DIR/admin/cli/purge_caches.php"
echo ""

echo "Completed: $(date)"
echo "Log saved to: $LOG_FILE"

exit 0
