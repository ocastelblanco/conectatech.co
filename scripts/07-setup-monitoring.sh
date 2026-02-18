#!/bin/bash
################################################################################
# Moodle 5.1 Monitoring Setup Script
# Version: 1.0.0
# Date: 2026-02-02
# Description: Configures CloudWatch monitoring and alerting
################################################################################

set -e

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

LOG_FILE="/var/log/moodle-monitoring-setup.log"
exec > >(tee -a "$LOG_FILE")
exec 2>&1

echo "======================================================================"
echo "Moodle Monitoring Setup"
echo "Started: $(date)"
echo "======================================================================"

if [ "$EUID" -ne 0 ]; then
    echo -e "${RED}ERROR: Please run as root or with sudo${NC}"
    exit 1
fi

################################################################################
# Install CloudWatch Agent
################################################################################

echo -e "\n${BLUE}[1/4] Installing CloudWatch Agent...${NC}"

# Download and install CloudWatch Agent
wget -q https://s3.amazonaws.com/amazoncloudwatch-agent/amazon_linux/arm64/latest/amazon-cloudwatch-agent.rpm -O /tmp/amazon-cloudwatch-agent.rpm

rpm -U /tmp/amazon-cloudwatch-agent.rpm

echo "✓ CloudWatch Agent installed"

################################################################################
# Configure CloudWatch Agent
################################################################################

echo -e "\n${BLUE}[2/4] Configuring CloudWatch Agent...${NC}"

cat > /opt/aws/amazon-cloudwatch-agent/etc/amazon-cloudwatch-agent.json << 'CW_CONFIG'
{
  "agent": {
    "metrics_collection_interval": 60,
    "run_as_user": "cwagent"
  },
  "logs": {
    "logs_collected": {
      "files": {
        "collect_list": [
          {
            "file_path": "/var/log/httpd/moodle-error.log",
            "log_group_name": "/aws/ec2/moodle/apache-error",
            "log_stream_name": "{instance_id}",
            "retention_in_days": 7
          },
          {
            "file_path": "/var/log/httpd/moodle-access.log",
            "log_group_name": "/aws/ec2/moodle/apache-access",
            "log_stream_name": "{instance_id}",
            "retention_in_days": 7
          },
          {
            "file_path": "/var/log/php-fpm/error.log",
            "log_group_name": "/aws/ec2/moodle/php-fpm",
            "log_stream_name": "{instance_id}",
            "retention_in_days": 7
          },
          {
            "file_path": "/var/log/memory-monitor.log",
            "log_group_name": "/aws/ec2/moodle/memory",
            "log_stream_name": "{instance_id}",
            "retention_in_days": 7
          }
        ]
      }
    }
  },
  "metrics": {
    "namespace": "Moodle/EC2",
    "metrics_collected": {
      "cpu": {
        "measurement": [
          {
            "name": "cpu_usage_idle",
            "rename": "CPU_IDLE",
            "unit": "Percent"
          },
          {
            "name": "cpu_usage_iowait",
            "rename": "CPU_IOWAIT",
            "unit": "Percent"
          }
        ],
        "metrics_collection_interval": 60,
        "totalcpu": false
      },
      "disk": {
        "measurement": [
          {
            "name": "used_percent",
            "rename": "DISK_USED",
            "unit": "Percent"
          },
          {
            "name": "inodes_free",
            "rename": "DISK_INODES_FREE",
            "unit": "Count"
          }
        ],
        "metrics_collection_interval": 60,
        "resources": [
          "*"
        ]
      },
      "diskio": {
        "measurement": [
          {
            "name": "io_time",
            "unit": "Milliseconds"
          }
        ],
        "metrics_collection_interval": 60,
        "resources": [
          "*"
        ]
      },
      "mem": {
        "measurement": [
          {
            "name": "mem_used_percent",
            "rename": "MEMORY_USED",
            "unit": "Percent"
          },
          {
            "name": "mem_available",
            "rename": "MEMORY_AVAILABLE",
            "unit": "Megabytes"
          }
        ],
        "metrics_collection_interval": 60
      },
      "swap": {
        "measurement": [
          {
            "name": "swap_used_percent",
            "rename": "SWAP_USED",
            "unit": "Percent"
          }
        ],
        "metrics_collection_interval": 60
      },
      "netstat": {
        "measurement": [
          {
            "name": "tcp_established",
            "rename": "TCP_ESTABLISHED",
            "unit": "Count"
          },
          {
            "name": "tcp_time_wait",
            "rename": "TCP_TIME_WAIT",
            "unit": "Count"
          }
        ],
        "metrics_collection_interval": 60
      }
    }
  }
}
CW_CONFIG

echo "✓ CloudWatch Agent configured"

# Start CloudWatch Agent
/opt/aws/amazon-cloudwatch-agent/bin/amazon-cloudwatch-agent-ctl \
    -a fetch-config \
    -m ec2 \
    -s \
    -c file:/opt/aws/amazon-cloudwatch-agent/etc/amazon-cloudwatch-agent.json

# Enable on boot
systemctl enable amazon-cloudwatch-agent

echo "✓ CloudWatch Agent started"

################################################################################
# Create custom monitoring scripts
################################################################################

echo -e "\n${BLUE}[3/4] Creating custom monitoring scripts...${NC}"

# Health check script
cat > /usr/local/bin/moodle-health-check.sh << 'HEALTH'
#!/bin/bash
################################################################################
# Moodle Health Check Script
################################################################################

echo "======================================================================"
echo "Moodle Health Check - $(date)"
echo "======================================================================"

# Check Apache
if systemctl is-active --quiet httpd; then
    echo "✓ Apache: Running"
else
    echo "✗ Apache: NOT RUNNING"
fi

# Check PHP-FPM
if systemctl is-active --quiet php-fpm; then
    echo "✓ PHP-FPM: Running"
    PHP_PROCESSES=$(ps aux | grep php-fpm | grep -v grep | wc -l)
    echo "  Processes: $PHP_PROCESSES"
else
    echo "✗ PHP-FPM: NOT RUNNING"
fi

# Check disk space
echo ""
echo "Disk Usage:"
df -h / /moodledata | grep -v "Filesystem"

# Check memory
echo ""
echo "Memory Usage:"
free -h | grep -E "Mem:|Swap:"

# Check database connectivity
if [ -f /root/moodle-config.env ]; then
    source /root/moodle-config.env
    if mysql -h "$DB_HOST" -u "$DB_USER" -p"$DB_PASSWORD" -e "SELECT 1;" &>/dev/null; then
        echo ""
        echo "✓ Database: Connected"
    else
        echo ""
        echo "✗ Database: Connection failed"
    fi
fi

# Check Moodle cron
LAST_CRON=$(find /tmp -name "cron.lock" -mmin -2 2>/dev/null)
if [ -n "$LAST_CRON" ]; then
    echo "✓ Moodle Cron: Running (executed in last 2 min)"
else
    echo "? Moodle Cron: Unknown status"
fi

# Check SSL certificate expiry
DOMAIN=$(grep "ServerName" /etc/httpd/conf.d/moodle.conf | awk '{print $2}' | head -1)
if [ -n "$DOMAIN" ]; then
    CERT_FILE="/etc/letsencrypt/live/$DOMAIN/fullchain.pem"
    if [ -f "$CERT_FILE" ]; then
        EXPIRY=$(openssl x509 -enddate -noout -in "$CERT_FILE" | cut -d= -f2)
        EXPIRY_EPOCH=$(date -d "$EXPIRY" +%s)
        NOW_EPOCH=$(date +%s)
        DAYS_LEFT=$(( ($EXPIRY_EPOCH - $NOW_EPOCH) / 86400 ))

        echo ""
        if [ $DAYS_LEFT -lt 30 ]; then
            echo "⚠ SSL Certificate: Expires in $DAYS_LEFT days"
        else
            echo "✓ SSL Certificate: Valid ($DAYS_LEFT days remaining)"
        fi
    fi
fi

echo "======================================================================"
HEALTH

chmod +x /usr/local/bin/moodle-health-check.sh
echo "✓ Health check script created"

# Status check script
cat > /usr/local/bin/moodle-status.sh << 'STATUS'
#!/bin/bash
# Quick Moodle status check

echo "🚀 Moodle System Status"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"

# Services
printf "%-20s" "Apache:"
systemctl is-active --quiet httpd && echo "✓ Running" || echo "✗ Stopped"

printf "%-20s" "PHP-FPM:"
systemctl is-active --quiet php-fpm && echo "✓ Running" || echo "✗ Stopped"

# Resources
MEMORY_USED=$(free | awk '/Mem:/ {printf "%.0f%%", $3/$2 * 100}')
DISK_USED=$(df -h / | awk 'NR==2 {print $5}')

printf "%-20s" "Memory Used:"
echo "$MEMORY_USED"

printf "%-20s" "Disk Used:"
echo "$DISK_USED"

# Load average
printf "%-20s" "Load Average:"
uptime | awk -F'load average:' '{print $2}'

echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
STATUS

chmod +x /usr/local/bin/moodle-status.sh
echo "✓ Status script created"

################################################################################
# Configure log rotation
################################################################################

echo -e "\n${BLUE}[4/4] Configuring log rotation...${NC}"

cat > /etc/logrotate.d/moodle << 'LOGROTATE'
# Moodle log rotation

/var/log/moodle-*.log {
    daily
    missingok
    rotate 14
    compress
    delaycompress
    notifempty
    create 0640 root root
    sharedscripts
    postrotate
        systemctl reload httpd > /dev/null 2>&1 || true
    endscript
}

/var/log/memory-monitor.log {
    weekly
    missingok
    rotate 4
    compress
    delaycompress
    notifempty
    create 0640 root root
}

/var/log/moodle-backup.log {
    weekly
    missingok
    rotate 4
    compress
    delaycompress
    notifempty
    create 0640 root root
}
LOGROTATE

echo "✓ Log rotation configured"

################################################################################
# Summary
################################################################################

echo -e "\n${GREEN}======================================================================"
echo "Monitoring Setup Complete!"
echo "======================================================================${NC}"

echo -e "\n📊 CloudWatch Monitoring:"
echo "  Namespace: Moodle/EC2"
echo "  Metrics: CPU, Memory, Disk, Network"
echo "  Logs: Apache, PHP-FPM, Memory Monitor"

echo -e "\n🔧 Monitoring Scripts:"
echo "  Health check: /usr/local/bin/moodle-health-check.sh"
echo "  Quick status: /usr/local/bin/moodle-status.sh"
echo "  Memory monitor: /usr/local/bin/memory-monitor.sh (runs hourly)"

echo -e "\n📝 Useful Commands:"
echo "  Full health check: sudo moodle-health-check.sh"
echo "  Quick status: sudo moodle-status.sh"
echo "  Memory history: sudo tail -100 /var/log/memory-monitor.log"
echo "  Apache logs: sudo tail -f /var/log/httpd/moodle-error.log"
echo "  PHP-FPM logs: sudo tail -f /var/log/php-fpm/error.log"

echo -e "\n📈 CloudWatch Console:"
echo "  View metrics: AWS Console → CloudWatch → Metrics → Moodle/EC2"
echo "  View logs: AWS Console → CloudWatch → Log groups"

echo -e "\n⚠️  Set Up Alarms:"
echo "  Consider creating CloudWatch alarms for:"
echo "  - High CPU usage (> 80%)"
echo "  - Low memory (< 500 MB)"
echo "  - High disk usage (> 80%)"
echo "  - Service failures"

echo -e "\n✅ Monitoring Active:"
echo "  CloudWatch Agent is collecting metrics and logs"
echo "  Memory monitoring runs hourly"
echo "  Log rotation configured"
echo ""

echo "Completed: $(date)"
echo "Log saved to: $LOG_FILE"

exit 0
