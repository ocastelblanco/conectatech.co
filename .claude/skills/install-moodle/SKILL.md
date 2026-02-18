---
name: install-moodle
description: Downloads, installs, and configures Moodle 5.1 on the prepared server with database setup and initial configuration. Use when server is configured and RDS database is available, or when the user needs to install Moodle from source, configure the database, set up cron jobs, or create the admin user. Triggers: "install Moodle", "deploy Moodle application", "setup Moodle database", "configure Moodle", "Moodle cron", "create admin user".
---

# Install Moodle 5.1

## Instructions

### Context

This skill performs:
- Downloads Moodle 5.1 from official GitHub repository
- Creates and configures config.php
- Runs CLI installer to set up database
- Configures cron for scheduled tasks
- Sets up proper permissions
- Creates initial admin user

### Required prerequisites

- Server configured with LAMP stack
- RDS MariaDB database available
- Apache and PHP-FPM running
- `/moodledata` directory created with subdirectories
- Configuration file at `/root/moodle-config.env`

### Available automation

Script location: `scripts/03-install-moodle.sh`

The script fully automates the installation process.

### Installation methods

**Method 1: Automated (Recommended)**
```bash
sudo scripts/03-install-moodle.sh
```

**Method 2: Manual** (follow steps below)

### Manual installation steps

1. **Download Moodle:**
   ```bash
   cd /var/www/html
   sudo git clone --depth=1 --branch MOODLE_51_STABLE \
       https://github.com/moodle/moodle.git moodle
   ```

2. **Set permissions:**
   ```bash
   sudo chown -R apache:apache /var/www/html/moodle
   sudo chmod -R 755 /var/www/html/moodle
   sudo chown -R apache:apache /moodledata
   sudo chmod -R 770 /moodledata
   ```

3. **Create config.php:**
   ```bash
   sudo vim /var/www/html/moodle/config.php
   ```

   Use this template (adjust values from `/root/moodle-config.env`):
   ```php
   <?php
   unset($CFG);
   global $CFG;
   $CFG = new stdClass();

   // Database
   $CFG->dbtype    = 'mariadb';
   $CFG->dblibrary = 'native';
   $CFG->dbhost    = 'your-rds-endpoint.rds.amazonaws.com';
   $CFG->dbname    = 'moodle';
   $CFG->dbuser    = 'moodleadmin';
   $CFG->dbpass    = 'your-password';
   $CFG->prefix    = 'mdl_';
   $CFG->dboptions = array(
       'dbpersist' => 0,
       'dbport' => 3306,
       'dbcollation' => 'utf8mb4_unicode_ci',
   );

   // Site
   $CFG->wwwroot   = 'https://yourdomain.com';
   $CFG->dataroot  = '/moodledata';
   $CFG->admin     = 'admin';
   $CFG->directorypermissions = 0770;

   // Performance
   $CFG->cachedir = '/moodledata/cache';
   $CFG->localcachedir = '/moodledata/localcache';
   $CFG->tempdir = '/moodledata/temp';
   $CFG->session_handler_class = '\core\session\database';
   $CFG->enablecaching = true;
   $CFG->cachejs = true;
   $CFG->themedesignermode = false;

   // Security
   $CFG->passwordsaltmain = 'GENERATE-RANDOM-SALT';

   require_once(__DIR__ . '/lib/setup.php');
   ```

   Generate salt: `openssl rand -hex 32`

4. **Set config.php permissions:**
   ```bash
   sudo chown apache:apache /var/www/html/moodle/config.php
   sudo chmod 640 /var/www/html/moodle/config.php
   ```

5. **Run CLI installer:**
   ```bash
   sudo -u apache /usr/bin/php /var/www/html/moodle/admin/cli/install_database.php \
       --lang=en \
       --adminuser=admin \
       --adminpass=YourSecurePassword \
       --adminemail=admin@yourdomain.com \
       --fullname="My Moodle Site" \
       --shortname="Moodle" \
       --summary="Learning Management System" \
       --agree-license
   ```

6. **Configure cron:**
   ```bash
   sudo vim /etc/cron.d/moodle
   ```

   Add:
   ```cron
   * * * * * apache /usr/bin/php /var/www/html/moodle/admin/cli/cron.php > /dev/null 2>&1
   ```

   Set permissions:
   ```bash
   sudo chmod 644 /etc/cron.d/moodle
   ```

7. **Test cron:**
   ```bash
   sudo -u apache /usr/bin/php /var/www/html/moodle/admin/cli/cron.php
   ```

### Configuration details

**Database settings:**
- Type: MariaDB (native driver)
- Collation: utf8mb4_unicode_ci (required for Moodle 5.1)
- Prefix: mdl_ (standard)

**Session handling:**
- Handler: Database (recommended for single server)
- Timeout: 120 seconds lock acquisition

**Cache configuration:**
- Type: File-based
- Locations: /moodledata/cache, /moodledata/localcache

**Performance settings:**
- Cache JS: Enabled
- YUI combo loading: Enabled
- Theme designer mode: DISABLED (important for production)

### Important security notes

1. **config.php permissions:** Must be 640 (apache:apache)
2. **Password salt:** Generate unique random salt
3. **Admin password:** Use strong password (min 12 chars)
4. **WWW root:** Use HTTPS URL (after SSL configuration)
5. **Theme designer mode:** Always OFF in production

### Verification steps

After installation:

1. **Check files:**
   ```bash
   ls -la /var/www/html/moodle/version.php
   ls -la /moodledata/
   ```

2. **Verify database:**
   ```bash
   mysql -h $DB_HOST -u $DB_USER -p"$DB_PASSWORD" $DB_NAME \
       -e "SHOW TABLES LIKE 'mdl_%';" | wc -l
   ```
   Should show 400+ tables.

3. **Test web access:**
   ```bash
   curl http://localhost/moodle/
   ```

4. **Check cron:**
   ```bash
   sudo tail -f /var/log/cron
   ```

### Configuracion Apache para Moodle 5.1

**IMPORTANTE:** Moodle 5.1 requiere que el DocumentRoot de Apache apunte al subdirectorio `/public`:

```apache
<VirtualHost *:80>
    ServerName yourdomain.com
    DocumentRoot /var/www/html/moodle/public

    <Directory /var/www/html/moodle/public>
        Options -Indexes +FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>
```

El nuevo motor de enrutamiento (Routing Engine) de Moodle 5.1 procesa todas las solicitudes a traves del directorio `/public`. El directorio `/public` se crea automaticamente al clonar el repositorio de Moodle.

**Nota:** Los paths CLI no cambian. Siguen en `/var/www/html/moodle/admin/cli/...` y `config.php` permanece en `/var/www/html/moodle/config.php`.

### Post-installation tasks

1. **Configurar Apache DocumentRoot** a `/var/www/html/moodle/public`
2. **Create Apache virtual host** (see `/configure-ssl` skill)
3. **Configure SSL/HTTPS** (required before production)
4. **Optimize system** (see `/optimize-system` skill)
5. **Complete Moodle setup in browser:**
   - Access https://yourdomain.com
   - Login with admin credentials
   - Complete initial configuration wizard
   - Configure site settings

### Disk space requirements

- Moodle code: ~500 MB
- Initial database: ~100 MB
- Moodledata (empty): ~5 GB reserved
- **Total:** ~6 GB

### Common issues

**Error: Could not connect to database**
```bash
# Test RDS connection
mysql -h $DB_HOST -u $DB_USER -p

# Check Security Group allows 3306 from EC2
aws ec2 describe-security-groups --group-ids sg-xxxxx
```

**Error: Permission denied writing to dataroot**
```bash
# Fix permissions
sudo chown -R apache:apache /moodledata
sudo chmod -R 770 /moodledata
sudo restorecon -Rv /moodledata  # SELinux
```

**Error: Cron not running**
```bash
# Check cron file
cat /etc/cron.d/moodle

# Check cron service
sudo systemctl status crond

# View cron logs
sudo tail -f /var/log/cron
```

### Expected output

```
Moodle 5.1.x downloaded from GitHub
Permissions configured
config.php created and secured
Database installed (400+ tables created)
Admin user created
Cron configured and tested
Installation successful

Admin credentials:
  Username: admin
  Password: [as configured]
  Email: admin@yourdomain.com

Next steps:
  1. Configure SSL: /configure-ssl
  2. Optimize system: /optimize-system
  3. Access site: https://yourdomain.com
```

## Example usage

**User:** "Server is set up, now install Moodle 5.1 with admin username 'moodleadmin'"

**Assistant:** I'll install Moodle 5.1 with the admin username 'moodleadmin'.

[Loads configuration from /root/moodle-config.env]
[Downloads Moodle 5.1 from GitHub]
[Creates config.php with proper settings]
[Runs CLI installer with custom admin username]
[Configures cron]
[Tests installation]

Moodle 5.1 installed successfully!

- Version: 5.1.x (Build: 20251006)
- Admin user: moodleadmin
- Database: 427 tables created
- Cron: Running every minute

Before accessing the site, you should:
1. Configure SSL/HTTPS (use `/configure-ssl`)
2. Optimize the system (use `/optimize-system`)
