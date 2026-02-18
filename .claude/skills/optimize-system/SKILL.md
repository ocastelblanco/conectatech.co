---
name: optimize-system
description: Optimizes PHP-FPM, Apache, and system resources based on available RAM. Includes automatic configuration of memory limits, SWAP, and monitoring. Use when experiencing performance issues or after Moodle installation. Triggers: "optimize server", "fix memory issues", "tune PHP-FPM", "prevent OOM", "improve performance".
---

# Optimize Moodle System

## Instructions

### Context

This skill implements proven optimization strategies from the ACG Calidad project where a t4g.medium (4GB RAM) server was optimized to handle 300+ concurrent users without OOM kills.

**Key optimizations:**
- Auto-calculated PHP-FPM settings based on RAM
- Apache MPM Event configuration
- SWAP configuration (only if needed)
- Memory monitoring (hourly)
- OPcache optimization
- System resource limits

### Required prerequisites

- Moodle installed and working
- Root/sudo access
- System has been running for at least 5 minutes

### Available automation

Script location: `scripts/05-optimize-system.sh`

The script automatically:
1. Detects total RAM
2. Calculates optimal PHP-FPM settings
3. Configures Apache MPM Event
4. Sets up SWAP (if RAM <= 8GB)
5. Installs memory monitoring
6. Restarts services

### Auto-calculation logic

**PHP-FPM max_children calculation:**
```
Total RAM (MB) - System overhead (800MB) = Available RAM
Available RAM / Memory per PHP process (200MB) = max_children

Bounds: min 5, max 100
```

**Examples:**
- 4GB RAM: 15 max_children
- 8GB RAM: 35 max_children
- 16GB RAM: 75 max_children

**SWAP size calculation:**
```
RAM < 2GB:   SWAP = 2 x RAM
RAM 2-8GB:   SWAP = RAM
RAM > 8GB:   SWAP = 8GB (maximum)
```

### Optimization steps

**1. Detect system resources:**
```bash
TOTAL_RAM_MB=$(free -m | awk '/^Mem:/{print $2}')
echo "Total RAM: ${TOTAL_RAM_MB} MB"
```

**2. Calculate PHP-FPM settings:**
```bash
AVAILABLE_RAM=$((TOTAL_RAM_MB - 800))
MAX_CHILDREN=$((AVAILABLE_RAM / 200))

# Bounds
[[ $MAX_CHILDREN -lt 5 ]] && MAX_CHILDREN=5
[[ $MAX_CHILDREN -gt 100 ]] && MAX_CHILDREN=100

START_SERVERS=$((MAX_CHILDREN / 5))
MIN_SPARE=$((MAX_CHILDREN / 7))
MAX_SPARE=$((MAX_CHILDREN / 3))
```

**3. Configure PHP-FPM:**
```bash
# Backup original
sudo cp /etc/php-fpm.d/www.conf /etc/php-fpm.d/www.conf.backup

# Apply optimizations
sudo sed -i "s/^pm.max_children = .*/pm.max_children = $MAX_CHILDREN/" /etc/php-fpm.d/www.conf
sudo sed -i "s/^pm.start_servers = .*/pm.start_servers = $START_SERVERS/" /etc/php-fpm.d/www.conf
sudo sed -i "s/^pm.min_spare_servers = .*/pm.min_spare_servers = $MIN_SPARE/" /etc/php-fpm.d/www.conf
sudo sed -i "s/^pm.max_spare_servers = .*/pm.max_spare_servers = $MAX_SPARE/" /etc/php-fpm.d/www.conf
```

**4. Configure PHP settings:**
Create `/etc/php.d/99-moodle.ini`:
```ini
; Memory and uploads
memory_limit = 384M
post_max_size = 100M
upload_max_filesize = 100M

; Timeouts
max_execution_time = 300
max_input_time = 300

; OPcache
opcache.enable = 1
opcache.memory_consumption = 256
opcache.interned_strings_buffer = 16
opcache.max_accelerated_files = 10000
opcache.revalidate_freq = 60
```

**5. Configure Apache MPM Event:**
Create `/etc/httpd/conf.d/mpm.conf`:
```apache
<IfModule mpm_event_module>
    ServerLimit              16
    StartServers             3
    MinSpareThreads          25
    MaxSpareThreads          75
    ThreadsPerChild          25
    MaxRequestWorkers        400
    MaxConnectionsPerChild   10000

    KeepAlive On
    MaxKeepAliveRequests 100
    KeepAliveTimeout 5
</IfModule>
```

**6. Configure SWAP (if needed):**
```bash
if [ "$TOTAL_RAM_MB" -le 8192 ]; then
    SWAP_SIZE_GB=$((TOTAL_RAM_MB / 1024))
    [[ $SWAP_SIZE_GB -lt 1 ]] && SWAP_SIZE_GB=1
    [[ $SWAP_SIZE_GB -gt 8 ]] && SWAP_SIZE_GB=8

    # Create SWAP
    sudo dd if=/dev/zero of=/swapfile bs=1M count=$((SWAP_SIZE_GB * 1024))
    sudo chmod 600 /swapfile
    sudo mkswap /swapfile
    sudo swapon /swapfile
    echo '/swapfile none swap sw 0 0' | sudo tee -a /etc/fstab

    # Configure swappiness
    echo 'vm.swappiness=10' | sudo tee -a /etc/sysctl.conf
    sudo sysctl -p
fi
```

**7. Set up memory monitoring:**
Create `/usr/local/bin/memory-monitor.sh`:
```bash
#!/bin/bash
LOG_FILE="/var/log/memory-monitor.log"
TIMESTAMP=$(date "+%Y-%m-%d %H:%M:%S")

# Rotate if > 10MB
if [ -f $LOG_FILE ] && [ $(stat -c%s "$LOG_FILE") -gt 10485760 ]; then
    mv $LOG_FILE ${LOG_FILE}.old
fi

{
    echo "=== $TIMESTAMP ==="
    free -h
    echo "--- PHP-FPM: $(ps aux | grep php-fpm | grep -v grep | wc -l) processes"
    echo "--- Cron: $(ps aux | grep 'cron.php' | grep -v grep | wc -l) processes"
    echo ""
} >> $LOG_FILE
```

Schedule hourly:
```bash
echo "0 * * * * root /usr/local/bin/memory-monitor.sh" | sudo tee /etc/cron.d/memory-monitor
```

**8. Restart services:**
```bash
sudo systemctl enable php-fpm
sudo systemctl restart php-fpm
sudo systemctl restart httpd
```

### Verification

After optimization:
```bash
# Check PHP-FPM config
sudo grep -E '^pm\.(max_children|start_servers)' /etc/php-fpm.d/www.conf

# Check services
sudo systemctl status php-fpm
sudo systemctl status httpd

# Check memory
free -h

# Check SWAP
swapon --show

# Monitor for 5 minutes
watch -n 30 'free -h && ps aux | grep php-fpm | wc -l'
```

### Performance expectations

**Before optimization (default config):**
- PHP-FPM: 50 max_children
- Memory: Runs out after 2 days
- Result: OOM kills, site downtime

**After optimization (t4g.medium, 4GB):**
- PHP-FPM: 15 max_children
- Memory: 2-3 GB always available
- SWAP: Rarely used (< 100 MB)
- Result: Stable for weeks

### Important monitoring

**Check these regularly:**
```bash
# Memory status
free -h

# Memory history
sudo tail -100 /var/log/memory-monitor.log

# Check for OOM kills
sudo dmesg | grep -i "out of memory"

# PHP-FPM process count
ps aux | grep php-fpm | grep -v grep | wc -l
```

### When to upgrade instance

Signs you need more RAM:
- Memory available < 500 MB consistently
- SWAP usage > 500 MB regularly
- PHP-FPM processes hitting max_children limit
- Slow page loads
- OOM kills in dmesg

### Rollback

If optimization causes issues:
```bash
# Restore PHP-FPM config
sudo cp /etc/php-fpm.d/www.conf.backup /etc/php-fpm.d/www.conf
sudo systemctl restart php-fpm

# Disable SWAP (if needed)
sudo swapoff /swapfile
sudo rm /swapfile
# Remove from /etc/fstab
```

### Real-world case study

**ACG Calidad Moodle (documented in docs/05-optimization.md):**
- Server: t4g.medium (4GB RAM)
- Users: 300+ concurrent
- Problem: OOM kills every 2 days
- Solution: This optimization
- Result: Stable for weeks, 2-3GB free RAM

### Expected output

```
System resources detected: 4096 MB RAM
Calculated PHP-FPM settings:
  - max_children: 15
  - start_servers: 3
  - min_spare_servers: 2
  - max_spare_servers: 5
PHP-FPM configuration updated
PHP settings optimized
Apache MPM Event configured
SWAP configured: 2GB
Memory monitoring installed (runs hourly)
Services restarted successfully

Current resource usage:
  Total RAM: 4.0 GB
  Used: 640 MB (16%)
  Available: 2.8 GB
  SWAP: 2.0 GB (1 MB used)

Optimization complete! System is configured for optimal performance.
```

## Example usage

**User:** "My Moodle server keeps crashing with out of memory errors"

**Assistant:** I'll optimize your system to prevent OOM kills. This is a common issue with default PHP-FPM settings.

[Detects RAM: 4GB]
[Calculates optimal settings]
[Applies optimization script]

I've optimized your system based on the 4GB RAM available:
- PHP-FPM max_children reduced from 50 to 15 (prevents memory overflow)
- SWAP added (2GB safety net)
- Memory monitoring installed

Your system now has 2.8GB free RAM and should be stable. I've also set up hourly monitoring so you can track memory usage.

Check memory status anytime with: `free -h`
View history: `sudo tail -100 /var/log/memory-monitor.log`
