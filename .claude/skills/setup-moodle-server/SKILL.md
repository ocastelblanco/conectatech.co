---
name: setup-moodle-server
description: Installs and configures complete LAMP stack (Apache 2.4 + PHP 8.4 + MariaDB client) on Amazon Linux 2023 for Moodle 5.1. Use when infrastructure is provisioned and EC2 instance is ready, or when the user needs to configure a fresh server, install PHP extensions, or configure firewall and SELinux. Triggers: "setup server", "install LAMP", "configure Apache PHP", "prepare server for Moodle", "install PHP extensions", "configure firewall", "configure SELinux".
---

# Setup Moodle Server

## Instructions

### Context

This skill configures:
- Apache 2.4 HTTP server
- PHP 8.4 with FPM and all Moodle-required extensions
- MariaDB client (for RDS connection)
- System utilities (git, certbot, monitoring tools)
- Firewall rules
- SELinux policies for Moodle

### Required prerequisites

- Infrastructure provisioned (EC2 instance running)
- SSH access to the server
- Root or sudo privileges
- Internet connectivity for package installation

### Available automation

Script location: `scripts/02-setup-server.sh`

The script automates all steps described below.

### Steps to execute

1. **Connect to server:**
   ```bash
   ssh -i ~/.ssh/your-key.pem ec2-user@<elastic-ip>
   ```

2. **Update system:**
   ```bash
   sudo dnf update -y
   ```

3. **Install Apache:**
   ```bash
   sudo dnf install -y httpd httpd-tools mod_ssl
   sudo systemctl enable httpd
   sudo systemctl start httpd
   ```

4. **Install PHP 8.4 with extensions:**
   ```bash
   sudo dnf install -y \
       php php-fpm php-cli php-common \
       php-mysqlnd php-gd php-xml php-mbstring \
       php-intl php-zip php-soap php-json \
       php-curl php-opcache php-xmlrpc php-sodium

   sudo systemctl enable php-fpm
   sudo systemctl start php-fpm
   ```

5. **Install MariaDB client:**
   ```bash
   sudo dnf install -y mariadb105
   ```

6. **Install additional tools:**
   ```bash
   sudo dnf install -y \
       git wget curl unzip vim htop tree \
       certbot python3-certbot-apache
   ```

7. **Configure firewall:**
   ```bash
   sudo systemctl start firewalld
   sudo systemctl enable firewalld
   sudo firewall-cmd --permanent --add-service=http
   sudo firewall-cmd --permanent --add-service=https
   sudo firewall-cmd --reload
   ```

8. **Create directory structure:**
   ```bash
   sudo mkdir -p /var/www/html/moodle
   sudo mkdir -p /moodledata/{cache,localcache,sessions,temp,repository,backup}
   sudo chown -R apache:apache /var/www/html/moodle
   sudo chown -R apache:apache /moodledata
   sudo chmod -R 755 /var/www/html/moodle
   sudo chmod -R 770 /moodledata
   ```

   **Nota Moodle 5.1:** Al clonar Moodle, el directorio `/public` se crea automaticamente. Apache DocumentRoot debe apuntar a `/var/www/html/moodle/public`.

9. **Configure SELinux:**
   ```bash
   sudo setsebool -P httpd_can_network_connect 1
   sudo setsebool -P httpd_can_network_connect_db 1
   sudo semanage fcontext -a -t httpd_sys_rw_content_t "/moodledata(/.*)?"
   sudo restorecon -Rv /moodledata
   ```

10. **Verify installation:**
    ```bash
    # Check versions
    httpd -v
    php -v
    mysql --version

    # Check services
    sudo systemctl status httpd
    sudo systemctl status php-fpm
    ```

### Configuration validation

After setup, verify:
- Apache running on port 80
- PHP-FPM running
- All PHP extensions loaded
- Firewall configured (ports 80, 443 open)
- SELinux configured for Moodle
- Directories created with correct permissions
- Database connectivity works (test to RDS)

### Testing connectivity

Test database connection:
```bash
# Load config
source /root/moodle-config.env

# Test connection
mysql -h $DB_HOST -u $DB_USER -p"$DB_PASSWORD" -e "SELECT 1;"
```

### Automated execution

Use the provided script:
```bash
sudo scripts/02-setup-server.sh
```

The script includes:
- Comprehensive logging
- Error handling
- Progress indicators
- Validation checks
- Summary report

### Important notes

- **Requires:** Root/sudo access
- **Internet:** Must have connectivity for downloads
- **Restarts:** May restart services during configuration
- **Logs:** Saved to `/var/log/moodle-setup.log`

### Software versions

- **OS:** Amazon Linux 2023
- **Apache:** 2.4.65+
- **PHP:** 8.4.x
- **MariaDB Client:** 10.11.15+

### Post-setup

After successful setup:
1. Server is ready for Moodle installation
2. Proceed with `/install-moodle` skill
3. Access http://<server-ip>/info.php to verify PHP (delete after)
4. Configure SSL before going to production

### Troubleshooting

**Error: Package not found**
- Update package cache: `sudo dnf makecache`
- Check repository configuration

**Error: Service fails to start**
- Check logs: `sudo journalctl -u httpd -n 50`
- Verify port not in use: `sudo netstat -tulpn | grep :80`

**Error: SELinux denying access**
- Check denials: `sudo ausearch -m avc -ts recent`
- Temporarily disable for testing: `sudo setenforce 0`

**Error: Cannot connect to RDS**
- Verify Security Group allows 3306 from EC2
- Check RDS endpoint in config
- Test network: `telnet $DB_HOST 3306`

### Expected output

```
Server updated
Apache 2.4.65 installed and running
PHP 8.4.x installed with all extensions
MariaDB client installed
Additional tools installed
Firewall configured (HTTP, HTTPS open)
Directory structure created
SELinux configured
Database connection successful
```

## Example usage

**User:** "My infrastructure is ready, now I need to set up the server for Moodle"

**Assistant:** I'll set up the complete LAMP stack on your server for Moodle 5.1.

First, let me connect to your server...

[Connects via SSH]
[Runs setup script or executes commands]
[Validates each component]
[Provides summary of installed software]

Your server is now ready! Here's what was installed:
- Apache 2.4.65
- PHP 8.4.14 with all required extensions
- MariaDB client for RDS connection
- System utilities and certbot

Next step: Install Moodle with the `/install-moodle` skill.
