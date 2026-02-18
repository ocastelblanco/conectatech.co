#!/bin/bash
################################################################################
# Moodle 5.1 Backup Configuration Script
# Version: 1.0.0
# Date: 2026-02-02
# Description: Configures automated backups for Moodle
################################################################################

set -e

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

LOG_FILE="/var/log/moodle-backup-setup.log"
exec > >(tee -a "$LOG_FILE")
exec 2>&1

echo "======================================================================"
echo "Moodle Backup Configuration"
echo "Started: $(date)"
echo "======================================================================"

if [ "$EUID" -ne 0 ]; then
    echo -e "${RED}ERROR: Please run as root or with sudo${NC}"
    exit 1
fi

################################################################################
# Load configuration
################################################################################

echo -e "\n${BLUE}[1/5] Loading configuration...${NC}"

if [ -f /root/moodle-config.env ]; then
    source /root/moodle-config.env
    echo "✓ Configuration loaded"
fi

# Set defaults
BACKUP_DIR="${BACKUP_DIR:-/var/backups/moodle}"
RETENTION_DAYS="${RETENTION_DAYS:-7}"

################################################################################
# Create backup directories
################################################################################

echo -e "\n${BLUE}[2/5] Creating backup directories...${NC}"

mkdir -p "$BACKUP_DIR"/{database,moodledata,config}
chown -R root:root "$BACKUP_DIR"
chmod -R 700 "$BACKUP_DIR"

echo "✓ Backup directory: $BACKUP_DIR"

################################################################################
# Create backup scripts
################################################################################

echo -e "\n${BLUE}[3/5] Creating backup scripts...${NC}"

# Database backup script
cat > /usr/local/bin/moodle-backup-db.sh << 'DBBACKUP'
#!/bin/bash
set -e

# Load config
source /root/moodle-config.env 2>/dev/null || true

BACKUP_DIR="/var/backups/moodle/database"
RETENTION_DAYS=7
TIMESTAMP=$(date +%Y%m%d-%H%M%S)
BACKUP_FILE="$BACKUP_DIR/moodle-db-$TIMESTAMP.sql.gz"

echo "=== Moodle Database Backup: $(date) ===" >> /var/log/moodle-backup.log

# Create backup
mysqldump -h "${DB_HOST}" -u "${DB_USER}" -p"${DB_PASSWORD}" \
    "${DB_NAME}" \
    --single-transaction \
    --quick \
    --lock-tables=false \
    | gzip > "$BACKUP_FILE"

if [ $? -eq 0 ]; then
    echo "✓ Database backup created: $BACKUP_FILE" >> /var/log/moodle-backup.log
    ls -lh "$BACKUP_FILE" >> /var/log/moodle-backup.log
else
    echo "✗ Database backup failed" >> /var/log/moodle-backup.log
    exit 1
fi

# Delete old backups
find "$BACKUP_DIR" -name "moodle-db-*.sql.gz" -mtime +$RETENTION_DAYS -delete
echo "✓ Old backups cleaned (retention: $RETENTION_DAYS days)" >> /var/log/moodle-backup.log
DBBACKUP

chmod +x /usr/local/bin/moodle-backup-db.sh
echo "✓ Database backup script created"

# Moodledata backup script
cat > /usr/local/bin/moodle-backup-data.sh << 'DATABACKUP'
#!/bin/bash
set -e

BACKUP_DIR="/var/backups/moodle/moodledata"
RETENTION_DAYS=7
TIMESTAMP=$(date +%Y%m%d-%H%M%S)
BACKUP_FILE="$BACKUP_DIR/moodledata-$TIMESTAMP.tar.gz"

echo "=== Moodledata Backup: $(date) ===" >> /var/log/moodle-backup.log

# Create backup (exclude cache and temp)
tar -czf "$BACKUP_FILE" \
    --exclude='/moodledata/cache/*' \
    --exclude='/moodledata/localcache/*' \
    --exclude='/moodledata/temp/*' \
    --exclude='/moodledata/sessions/*' \
    /moodledata

if [ $? -eq 0 ]; then
    echo "✓ Moodledata backup created: $BACKUP_FILE" >> /var/log/moodle-backup.log
    ls -lh "$BACKUP_FILE" >> /var/log/moodle-backup.log
else
    echo "✗ Moodledata backup failed" >> /var/log/moodle-backup.log
    exit 1
fi

# Delete old backups
find "$BACKUP_DIR" -name "moodledata-*.tar.gz" -mtime +$RETENTION_DAYS -delete
echo "✓ Old backups cleaned (retention: $RETENTION_DAYS days)" >> /var/log/moodle-backup.log
DATABACKUP

chmod +x /usr/local/bin/moodle-backup-data.sh
echo "✓ Moodledata backup script created"

# Config backup script
cat > /usr/local/bin/moodle-backup-config.sh << 'CONFIGBACKUP'
#!/bin/bash
set -e

BACKUP_DIR="/var/backups/moodle/config"
TIMESTAMP=$(date +%Y%m%d-%H%M%S)
CONFIG_BACKUP="$BACKUP_DIR/config-$TIMESTAMP.tar.gz"

echo "=== Config Backup: $(date) ===" >> /var/log/moodle-backup.log

# Backup important configs
tar -czf "$CONFIG_BACKUP" \
    /var/www/html/moodle/config.php \
    /etc/httpd/conf.d/moodle*.conf \
    /etc/php-fpm.d/www.conf \
    /etc/php.d/99-moodle.ini \
    /root/moodle-config.env \
    2>/dev/null || true

if [ -f "$CONFIG_BACKUP" ]; then
    echo "✓ Config backup created: $CONFIG_BACKUP" >> /var/log/moodle-backup.log

    # Keep only last 30 days of config backups
    find "$BACKUP_DIR" -name "config-*.tar.gz" -mtime +30 -delete
fi
CONFIGBACKUP

chmod +x /usr/local/bin/moodle-backup-config.sh
echo "✓ Config backup script created"

################################################################################
# Configure cron jobs
################################################################################

echo -e "\n${BLUE}[4/5] Configuring backup schedule...${NC}"

cat > /etc/cron.d/moodle-backups << 'CRON'
# Moodle Automated Backups

# Database backup - Daily at 3 AM
0 3 * * * root /usr/local/bin/moodle-backup-db.sh

# Moodledata backup - Weekly on Sunday at 4 AM
0 4 * * 0 root /usr/local/bin/moodle-backup-data.sh

# Config backup - Weekly on Sunday at 5 AM
0 5 * * 0 root /usr/local/bin/moodle-backup-config.sh
CRON

chmod 644 /etc/cron.d/moodle-backups

echo "✓ Backup schedule configured:"
echo "  - Database: Daily at 3 AM"
echo "  - Moodledata: Weekly (Sunday 4 AM)"
echo "  - Config: Weekly (Sunday 5 AM)"

################################################################################
# Test backups
################################################################################

echo -e "\n${BLUE}[5/5] Testing backup scripts...${NC}"

echo "Testing database backup..."
if /usr/local/bin/moodle-backup-db.sh; then
    echo "✓ Database backup test successful"
else
    echo -e "${YELLOW}WARNING: Database backup test failed${NC}"
fi

echo ""
echo "Testing config backup..."
if /usr/local/bin/moodle-backup-config.sh; then
    echo "✓ Config backup test successful"
else
    echo -e "${YELLOW}WARNING: Config backup test failed${NC}"
fi

################################################################################
# Create restore script
################################################################################

echo -e "\n${BLUE}Creating restore script...${NC}"

cat > /usr/local/bin/moodle-restore.sh << 'RESTORE'
#!/bin/bash
################################################################################
# Moodle Restore Script
################################################################################

set -e

if [ "$EUID" -ne 0 ]; then
    echo "ERROR: Please run as root"
    exit 1
fi

BACKUP_DIR="/var/backups/moodle"

echo "======================================================================"
echo "Moodle Restore Utility"
echo "======================================================================"

# List available backups
echo ""
echo "Database backups:"
ls -lh "$BACKUP_DIR/database/" | tail -5

echo ""
echo "Moodledata backups:"
ls -lh "$BACKUP_DIR/moodledata/" | tail -5

echo ""
echo "Config backups:"
ls -lh "$BACKUP_DIR/config/" | tail -5

echo ""
echo "======================================================================"
echo "To restore:"
echo ""
echo "Database:"
echo "  gunzip < /path/to/backup.sql.gz | mysql -h HOST -u USER -pPASS DATABASE"
echo ""
echo "Moodledata:"
echo "  tar -xzf /path/to/moodledata-backup.tar.gz -C /"
echo ""
echo "Config:"
echo "  tar -xzf /path/to/config-backup.tar.gz -C /"
echo "======================================================================"
RESTORE

chmod +x /usr/local/bin/moodle-restore.sh
echo "✓ Restore script created: /usr/local/bin/moodle-restore.sh"

################################################################################
# Summary
################################################################################

echo -e "\n${GREEN}======================================================================"
echo "Backup Configuration Complete!"
echo "======================================================================${NC}"

echo -e "\n💾 Backup Configuration:"
echo "  Location: $BACKUP_DIR"
echo "  Retention: $RETENTION_DAYS days (database)"
echo "  Retention: $RETENTION_DAYS days (moodledata)"
echo "  Retention: 30 days (config)"

echo -e "\n📅 Backup Schedule:"
echo "  Database: Daily at 3:00 AM"
echo "  Moodledata: Weekly (Sunday 4:00 AM)"
echo "  Config: Weekly (Sunday 5:00 AM)"

echo -e "\n🔧 Backup Scripts:"
echo "  Database: /usr/local/bin/moodle-backup-db.sh"
echo "  Moodledata: /usr/local/bin/moodle-backup-data.sh"
echo "  Config: /usr/local/bin/moodle-backup-config.sh"
echo "  Restore: /usr/local/bin/moodle-restore.sh"

echo -e "\n📝 Useful Commands:"
echo "  Run database backup: sudo /usr/local/bin/moodle-backup-db.sh"
echo "  Run data backup: sudo /usr/local/bin/moodle-backup-data.sh"
echo "  List backups: sudo /usr/local/bin/moodle-restore.sh"
echo "  View backup log: sudo tail -f /var/log/moodle-backup.log"

echo -e "\n⚠️  Important Notes:"
echo "  1. Test restores periodically to ensure backups are valid"
echo "  2. Consider offsite backup to S3 or similar"
echo "  3. Monitor disk space: df -h $BACKUP_DIR"
echo "  4. Database backups are compressed (.sql.gz)"

echo -e "\n✅ Next Steps:"
echo "  1. Setup monitoring: ./07-setup-monitoring.sh"
echo "  2. Verify first automated backup tomorrow"
echo "  3. Consider S3 sync for offsite backups"
echo ""

echo "Completed: $(date)"
echo "Log saved to: $LOG_FILE"

exit 0
