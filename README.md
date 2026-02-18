# ConectaTech.co - Plataforma Moodle en AWS

Infraestructura de código abierto como servicio (IaC) para desplegar una plataforma completa de aprendizaje **Moodle 5.1** en Amazon Web Services (AWS) con alta disponibilidad, seguridad y optimización de costos.

## Resumen Ejecutivo

**ConectaTech.co** es una solución empresarial lista para producción que proporciona:

- ✅ **Moodle 5.1** con arquitectura moderna `/public` DocumentRoot
- ✅ **Infraestructura escalable** en AWS (EC2, RDS, EBS, CloudFront)
- ✅ **Seguridad de nivel empresarial** (SSL/TLS, firewalls, encriptación)
- ✅ **Automatización completa** con Terraform y scripts bash
- ✅ **Costo optimizado** desde $33.60/mes (t4g.small) hasta $88.85/mes (t4g.large)
- ✅ **Monitoreo integral** con CloudWatch y alarmas automáticas
- ✅ **Backups automatizados** con snapshots EBS y RDS

### Configuración Actual Desplegada

| Componente | Especificación | Detalles |
|-----------|----------------|----------|
| **EC2** | t4g.small (2 vCPU, 2 GB RAM) | Instance ID: `i-0238341b5897b8e8f` |
| **SO** | Amazon Linux 2023 ARM64 | Zona: us-east-1c |
| **IP Pública** | 54.86.113.27 (Elastic IP) | Disponible permanentemente |
| **RDS** | MariaDB 10.11.15 db.t4g.micro | Endpoint: `conectatech-prod-db-[...].cuz8c66mcaes.us-east-1.rds.amazonaws.com` |
| **Almacenamiento** | EBS gp3 40GB (15GB OS + 25GB datos) | Volúmenes encriptados |
| **Moodle** | Versión 5.1.3 Build 20260216 | Instalado y funcional |
| **Web** | Apache 2.4.66 + PHP 8.3.29 + PHP-FPM | Optimizado para 50-100 usuarios |
| **SSL** | Let's Encrypt | A+ rating, auto-renovable, expira 2026-05-18 |
| **Dominio** | conectatech.co | Registrado y apuntando a 54.86.113.27 |

## Tabla de Contenidos

- [Inicio Rápido](#inicio-rápido)
- [Arquitectura](#arquitectura)
- [Requisitos Previos](#requisitos-previos)
- [Instalación](#instalación)
- [Uso](#uso)
- [Configuración](#configuración)
- [Mantenimiento](#mantenimiento)
- [Solución de Problemas](#solución-de-problemas)
- [Contribuciones](#contribuciones)
- [Licencia](#licencia)

---

## Inicio Rápido

### Para Acceder al Sistema Existente

```bash
# Conectar a la instancia EC2
ssh -i ~/.ssh/ClaveIM.pem ec2-user@54.86.113.27

# O usar el dominio
ssh -i ~/.ssh/ClaveIM.pem ec2-user@conectatech.co

# Acceder a Moodle
https://conectatech.co

# Login de administrador
Usuario: admin
Contraseña: [Configurada en terraform.tfvars]
```

### Para Desplegar Nuevo Entorno

```bash
# 1. Clonar/descargar el repositorio
cd /ruta/a/conectatech.co

# 2. Configurar variables
cd terraform/
cp terraform.tfvars.example terraform.tfvars
# Editar terraform.tfvars con tus valores

# 3. Planificar y desplegar
terraform init
terraform plan
terraform apply

# 4. Ejecutar scripts de configuración
cd ../scripts/
./02-setup-server.sh
./03-install-moodle.sh
./04-configure-ssl.sh
./05-optimize-system.sh
./06-setup-backups.sh
```

---

## Arquitectura

### Diagrama General

```
┌─────────────────────────────────────────────────────────────┐
│                        Internet                              │
└────────────────────┬────────────────────────────────────────┘
                     │
                     ▼
            ┌─────────────────┐
            │   Route 53 DNS  │
            │ conectatech.co  │
            └────────┬────────┘
                     │
                     ▼
      ┌──────────────────────────────┐
      │   Elastic IP: 54.86.113.27   │
      └────────────┬─────────────────┘
                   │
                   ▼
   ┌───────────────────────────────────────┐
   │        EC2 Instance (t4g.small)       │
   │  ┌─────────────────────────────────┐  │
   │  │  Apache 2.4.66 + PHP 8.3.29     │  │
   │  │  Moodle 5.1.3                   │  │
   │  │  DocumentRoot: /var/www/html/.. │  │
   │  │  /moodledata (25GB EBS)         │  │
   │  └────────────┬────────────────────┘  │
   └───────────────┼─────────────────────┘
                   │
                   ▼
   ┌───────────────────────────────────────┐
   │   RDS MariaDB 10.11.15 (db.t4g.micro) │
   │   Database: moodle                    │
   │   Backups: 7 días automáticos         │
   └───────────────────────────────────────┘
```

### Componentes Principales

#### 1. **Compute (EC2)**
- **Instancia:** t4g.small (ARM64 Graviton2)
- **2 vCPU** con capacidad de ráfaga
- **2 GB RAM** + 2 GB SWAP
- **Optimizada para:** 50-100 usuarios concurrentes
- **SO:** Amazon Linux 2023 (LTS)

#### 2. **Storage (EBS)**
- **Volumen raíz:** 15 GB gp3 (sistema operativo)
- **Volumen datos:** 25 GB gp3 (/moodledata - archivos de usuarios)
- **IOPS:** 3000 línea base
- **Throughput:** 125 MB/s
- **Encriptación:** Habilitada en reposo

#### 3. **Database (RDS MariaDB)**
- **Engine:** MariaDB 10.11.15 (LTS compatible)
- **Instancia:** db.t4g.micro (1 GB RAM)
- **Storage:** 20 GB gp3 (auto-escalable hasta 100 GB)
- **Backups:** Automáticos cada 24h, retención 7 días
- **Ventana mantenimiento:** Domingo 3-4 AM UTC
- **No públicamente accesible** (seguridad)

#### 4. **Red y Conectividad**
- **Elastic IP:** 54.86.113.27 (IP estática permanente)
- **Security Groups:** Firewall granular por puerto y protocolo
  - SSH (22): Restringido a IP autorizada
  - HTTP (80): Abierto para redirección a HTTPS
  - HTTPS (443): Abierto para tráfico público
- **RDS:** Solo accesible desde EC2

#### 5. **SSL/TLS**
- **Proveedor:** Let's Encrypt
- **Certificado:** Wildcard automático
- **Rating:** A+ (SSL Labs)
- **Auto-renovación:** Cada 60 días via Certbot
- **Expira:** 2026-05-18 (renovable automáticamente)

#### 6. **Monitoreo**
- **CloudWatch:** Métricas de CPU, memoria, disco, red
- **Alarmas:** CPU > 80%, memoria < 500 MB, disco > 80%
- **Logs:** Agregación centralizada
- **SNS:** Notificaciones por email

---

## Requisitos Previos

### Para Desplegar Nueva Infraestructura

#### Cuenta AWS
- [ ] Cuenta AWS activa
- [ ] IAM user o role con permisos EC2, RDS, IAM, Route53, CloudWatch, SNS, S3
- [ ] AWS CLI v2 instalado y configurado
- [ ] Billing alerts configuradas (recomendado)

#### Herramientas Locales
- [ ] **Terraform** >= 1.0 (`brew install terraform`)
- [ ] **Git** (`brew install git`)
- [ ] **SSH cliente** (macOS/Linux pre-instalado)
- [ ] **jq** (JSON processor, opcional pero recomendado)

#### Credenciales y Acceso
- [ ] EC2 key pair creado (`.pem` guardado en `~/.ssh/`)
- [ ] Contraseñas seguras generadas:
  ```bash
  openssl rand -base64 16  # DB password
  openssl rand -base64 16  # Moodle admin password
  ```
- [ ] IP pública identificada para SSH whitelist:
  ```bash
  curl ifconfig.me
  ```

#### Dominio
- [ ] Dominio registrado (cualquier registrador)
- [ ] Acceso a gestión de DNS
- [ ] Opcionalmente: Route 53 hosted zone creada

### Para Acceder al Sistema Existente

- [ ] SSH key: `~/.ssh/ClaveIM.pem` (permisos 400)
- [ ] Credenciales Moodle (usuario: `admin`)
- [ ] AWS CLI con profile `im` configurado

---

## Instalación

### Paso 1: Configurar AWS CLI

```bash
# Configurar credentials
aws configure --profile im
# AWS Access Key ID: [tu-access-key]
# AWS Secret Access Key: [tu-secret-key]
# Default region: us-east-1
# Default output format: json

# Verificar configuración
aws sts get-caller-identity --profile im
```

### Paso 2: Preparar Terraform

```bash
cd terraform/

# Crear archivo de variables
cat > terraform.tfvars << EOF
# Configuración General
project_name                = "moodle"
environment                 = "prod"
aws_region                  = "us-east-1"
aws_profile                 = "im"

# EC2
key_pair_name               = "ClaveIM"
instance_type               = "t4g.small"
allowed_ssh_cidrs           = ["TU.IP.PUBLICA/32"]

# RDS
db_password                 = "tu-password-generado"
db_username                 = "moodleadmin"

# Moodle
domain_name                 = "conectatech.co"
admin_email                 = "admin@conectatech.co"
moodle_admin_password       = "otro-password-generado"
moodle_site_name            = "ConectaTech - LMS"
moodle_site_summary         = "Plataforma de Aprendizaje Empresarial"

# Route 53 (si aplica)
create_route53_record       = true
route53_zone_id             = "Z123ABC456"  # Tu hosted zone ID

# Opcionales
enable_cloudwatch_alarms    = true
alarm_email                 = "tu-email@example.com"
enable_ebs_snapshots        = true
EOF

# Proteger archivo
chmod 600 terraform.tfvars

# Validar configuración
terraform init
terraform validate
```

### Paso 3: Desplegar Infraestructura

```bash
# Ver plan de cambios
terraform plan -out=tfplan

# Aplicar cambios
terraform apply tfplan

# Guardar outputs
terraform output -json > outputs.json
```

**El despliegue toma:** 5-10 minutos

### Paso 4: Configurar Servidor

```bash
cd ../scripts/

# Setup básico del servidor
./02-setup-server.sh

# Instalar Moodle
./03-install-moodle.sh

# Configurar SSL/TLS
./04-configure-ssl.sh

# Optimizar sistema
./05-optimize-system.sh

# Configurar backups
./06-setup-backups.sh

# Monitoreo (opcional)
./07-setup-monitoring.sh
```

### Paso 5: Verificar Instalación

```bash
# SSH a la instancia
ssh -i ~/.ssh/ClaveIM.pem ec2-user@54.86.113.27

# Verificar servicios
sudo systemctl status apache2
sudo systemctl status php-fpm

# Verificar conectividad a RDS
mysql -h ENDPOINT_RDS -u moodleadmin -p moodle -e "SELECT VERSION();"

# Ver logs de Moodle
sudo tail -f /var/www/html/moodle/var/log/moodle.log
```

---

## Uso

### Acceso a Moodle

**URL:** https://conectatech.co

**Panel de Administración:** https://conectatech.co/admin

**Credenciales por defecto:**
- Usuario: `admin`
- Contraseña: [Definida en `terraform.tfvars`]

### Comandos Útiles

#### SSH a la Instancia

```bash
# Conexión directa
ssh -i ~/.ssh/ClaveIM.pem ec2-user@54.86.113.27

# Por dominio
ssh -i ~/.ssh/ClaveIM.pem ec2-user@conectatech.co
```

#### Gestionar Servicios

```bash
# Apache
sudo systemctl {start|stop|restart|status} apache2
sudo systemctl reload apache2  # Sin downtime

# PHP-FPM
sudo systemctl {start|stop|restart|status} php-fpm

# Moodle cron (ejecutarse inmediatamente)
sudo -u apache php /var/www/html/moodle/admin/cli/cron.php

# Limpiar cachés
sudo -u apache php /var/www/html/moodle/admin/cli/purge_caches.php
```

#### Monitoreo en Consola

```bash
# Estado de recursos
free -h                        # Memoria
df -h                         # Disco
top -b -n 1 | head -n 20      # Procesos
uptime                        # Carga del sistema

# Conexiones a RDS
netstat -an | grep 3306
```

#### Backup Manual de Base de Datos

```bash
FECHA=$(date +%Y%m%d_%H%M%S)
sudo mysqldump -h ENDPOINT_RDS -u moodleadmin -p moodle > backup_${FECHA}.sql
# Transferir a S3 o almacenamiento seguro
```

#### Logs Importantes

```bash
# Moodle application log
sudo tail -f /var/www/html/moodle/var/log/moodle.log

# Apache access log
sudo tail -f /var/log/apache2/access_log

# Apache error log
sudo tail -f /var/log/apache2/error_log

# PHP-FPM errors
sudo tail -f /var/log/php-fpm/error.log

# Sistema general
sudo tail -f /var/log/messages  # Amazon Linux 2023
```

---

## Configuración

### Variables Terraform

#### Variables Críticas

| Variable | Tipo | Obligatorio | Defecto | Descripción |
|----------|------|-----------|---------|------------|
| `key_pair_name` | string | ✅ | - | Nombre del EC2 key pair |
| `domain_name` | string | ✅ | - | Dominio para Moodle (ej: conectatech.co) |
| `db_password` | string | ✅ | - | Contraseña RDS (min 8 caracteres) |
| `moodle_admin_password` | string | ✅ | - | Contraseña admin Moodle (min 8 caracteres) |
| `admin_email` | string | ✅ | - | Email para notificaciones Let's Encrypt |

#### Variables de Personalización

| Variable | Tipo | Defecto | Descripción |
|----------|------|---------|------------|
| `instance_type` | string | t4g.medium | Tipo EC2 (t4g.small, t4g.medium, t4g.large, ...) |
| `root_volume_size` | number | 15 | GB para volumen raíz |
| `data_volume_size` | number | 25 | GB para /moodledata |
| `db_instance_class` | string | db.t4g.micro | Clase RDS |
| `db_backup_retention_period` | number | 7 | Días retención backups |
| `moodle_site_name` | string | My Moodle Site | Nombre del sitio |
| `enable_cloudfront` | bool | false | Habilitar CDN CloudFront |
| `enable_cloudwatch_alarms` | bool | true | Crear alarmas CloudWatch |
| `cpu_alarm_threshold` | number | 80 | % CPU para alarma |

#### Escalado Recomendado

```hcl
# Para 100-300 usuarios
instance_type           = "t4g.medium"  # 4 GB RAM
db_instance_class       = "db.t4g.micro"  # 1 GB RAM
data_volume_size        = 30

# Para 300-1000 usuarios
instance_type           = "t4g.large"   # 8 GB RAM
db_instance_class       = "db.t4g.small"  # 2 GB RAM
data_volume_size        = 50
enable_cloudfront       = true  # Agregar CDN

# Para 1000+ usuarios
instance_type           = "t4g.xlarge"  # 16 GB RAM
db_instance_class       = "db.t4g.medium"  # 4 GB RAM
data_volume_size        = 100
db_multi_az             = true  # Alta disponibilidad
enable_cloudfront       = true
```

### Archivos de Configuración

#### terraform.tfvars
Contiene valores específicos de tu despliegue (PROTEGIDO: no commitear a git)

```bash
# Proteger archivo
chmod 600 terraform.tfvars

# Agregar a .gitignore
echo "terraform.tfvars" >> .gitignore
```

#### config/variables.sh
Script bash con variables compartidas para scripts de setup

```bash
# Cargar variables
source config/variables.sh

# Mostrar configuración
show_config

# Validar configuración
validate_config
```

#### Apache VirtualHost
Ubicación: `/etc/apache2/sites-available/moodle.conf`

```apache
<VirtualHost *:80>
    ServerName conectatech.co
    DocumentRoot /var/www/html/moodle/public

    <Directory /var/www/html/moodle/public>
        Options -Indexes +FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>

    # /moodledata no debe ser accesible
    <Directory /moodledata>
        Require all denied
    </Directory>
</VirtualHost>
```

#### Moodle config.php
Ubicación: `/var/www/html/moodle/config.php`

Generado automáticamente durante instalación. Editar solo si necesario:

```php
// Configuración de base de datos
$CFG->dbtype    = 'mariadb';
$CFG->dblibrary = 'native/mariadb';
$CFG->dbhost    = 'ENDPOINT_RDS';
$CFG->dbname    = 'moodle';
$CFG->dbuser    = 'moodleadmin';
$CFG->dbpass    = '[password]';

// Ubicación moodledata
$CFG->dataroot  = '/moodledata';

// HTTPS forzado
$CFG->wwwroot   = 'https://conectatech.co';
```

---

## Mantenimiento

### Backups

#### Backup Automático (Configurado)

**RDS:**
- Frecuencia: Diaria
- Retención: 7 días
- Backup Window: 3-4 AM UTC
- Ubicación: AWS managed

**EBS:**
- Snapshots automáticos (si habilitado)
- Retención: 7 días
- Frecuencia: Diaria a las 2 AM UTC

#### Backup Manual

```bash
# Backup base de datos
FECHA=$(date +%Y%m%d_%H%M%S)
DBPASS="tu-contraseña"
ENDPOINT="conectatech-prod-db-[...].cuz8c66mcaes.us-east-1.rds.amazonaws.com"

mysqldump -h $ENDPOINT -u moodleadmin -p$DBPASS \
  --single-transaction --routines \
  moodle > backup_moodle_${FECHA}.sql

# Backup moodledata
tar -czf backup_moodledata_${FECHA}.tar.gz /moodledata/

# Transferir a S3
aws s3 cp backup_moodle_${FECHA}.sql s3://mi-bucket-backups/ --profile im
aws s3 cp backup_moodledata_${FECHA}.tar.gz s3://mi-bucket-backups/ --profile im
```

#### Restaurar desde Backup

```bash
# Restaurar base de datos
ENDPOINT="conectatech-prod-db-[...].cuz8c66mcaes.us-east-1.rds.amazonaws.com"
DBPASS="tu-contraseña"

mysql -h $ENDPOINT -u moodleadmin -p$DBPASS moodle < backup_moodle_YYYYMMDD_HHMMSS.sql

# Restaurar moodledata
tar -xzf backup_moodledata_YYYYMMDD_HHMMSS.tar.gz -C /
sudo chown -R apache:apache /moodledata
```

### Parches de Seguridad

#### Actualizar Sistema Operativo

```bash
# Conectar a la instancia
ssh -i ~/.ssh/ClaveIM.pem ec2-user@54.86.113.27

# Aplicar parches
sudo dnf update -y

# Reiniciar si es necesario
sudo reboot

# Verificar actualizaciones pendientes
sudo dnf check-update
```

#### Actualizar Moodle

```bash
# Ver versión actual
sudo -u apache php /var/www/html/moodle/admin/cli/core_component.php

# Cambiar a rama newer (si disponible)
cd /var/www/html/moodle
sudo git fetch origin
sudo git checkout v5.2 (o rama requerida)
sudo -u apache php admin/cli/upgrade.php --non-interactive

# Verificar que funcionó
sudo -u apache php admin/cli/check_database_schema.php
```

#### Actualizar Plugins

```bash
# SSH a instancia
ssh -i ~/.ssh/ClaveIM.pem ec2-user@54.86.113.27

# Versión de Moodle
sudo -u apache php /var/www/html/moodle/admin/cli/plugin_manager.php

# Revisar disponibles
cd /var/www/html/moodle
sudo -u apache php admin/cli/plugin_manager.php --show-available-updates

# Actualizar todos los plugins
sudo -u apache php admin/cli/plugin_manager.php --upgrade-all
```

### Monitoreo de Salud

#### CloudWatch Dashboard

1. Acceder a AWS Console
2. CloudWatch → Dashboards
3. Ver métricas en tiempo real:
   - CPU EC2
   - Memoria disponible
   - Espacio disco
   - Conexiones RDS
   - Latencia de consultas

#### Revisar Alarmas

```bash
# Ver todas las alarmas
aws cloudwatch describe-alarms --profile im

# Ver alarmas en estado ALARM
aws cloudwatch describe-alarms \
  --state-values ALARM \
  --profile im

# Ver historial de alarma específica
aws cloudwatch describe-alarm-history \
  --alarm-name cpu-utilization-alarm \
  --profile im
```

#### Monitoreo de Logs

```bash
# Ver logs recientes en CloudWatch
aws logs tail /aws/ec2/moodle --follow --profile im

# O en el servidor:
sudo journalctl -u php-fpm -f
sudo journalctl -u apache2 -f
```

### Escalado Vertical

Si usuarios crecen y performance degrada:

```bash
# 1. Crear snapshot del volumen raíz (backup preventivo)
# 2. Detener la instancia
aws ec2 stop-instances --instance-ids i-0238341b5897b8e8f --profile im

# 3. Cambiar tipo de instancia
# AWS Console → EC2 → Instance → Instance State → Change Instance Type
# Seleccionar: t4g.medium o t4g.large

# 4. Iniciar instancia
aws ec2 start-instances --instance-ids i-0238341b5897b8e8f --profile im

# 5. Esperar a que arranque (1-2 min)
# 6. Conectar y verificar
ssh -i ~/.ssh/ClaveIM.pem ec2-user@54.86.113.27

# 7. Verificar servicios
sudo systemctl status apache2
sudo systemctl status php-fpm

# Downtime total: 5-10 minutos
```

### Limpieza de Espacios

```bash
# Limpiar cache de Moodle
sudo -u apache php /var/www/html/moodle/admin/cli/purge_caches.php

# Limpiar logs antiguos
sudo logrotate -f /etc/logrotate.conf

# Ver tamaño de moodledata
du -sh /moodledata/

# Eliminar archivos temporales seguros
sudo rm -rf /moodledata/temp/*
sudo rm -rf /moodledata/cache/*
```

---

## Solución de Problemas

### Problema: Moodle no carga (Error 500)

**Síntomas:** Error 500 en navegador

**Solución:**

```bash
# 1. Revisar error log de Apache
sudo tail -f /var/log/apache2/error_log

# 2. Revisar error log de Moodle
sudo tail -f /var/www/html/moodle/var/log/moodle.log

# 3. Verificar PHP-FPM
sudo systemctl status php-fpm
sudo systemctl restart php-fpm

# 4. Verificar permisos
sudo chown -R apache:apache /var/www/html/moodle
sudo chown -R apache:apache /moodledata

# 5. Verificar conectividad a RDS
sudo -u apache php << 'EOF'
$dbhost = 'ENDPOINT_RDS';
$dbuser = 'moodleadmin';
$dbpass = 'password';
$dbname = 'moodle';

try {
    $pdo = new PDO("mysql:host=$dbhost;dbname=$dbname", $dbuser, $dbpass);
    echo "Conexión OK\n";
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
EOF
```

### Problema: Base de datos no responde

**Síntomas:** "Could not connect to database" en Moodle

**Solución:**

```bash
# 1. Verificar que RDS está disponible
aws rds describe-db-instances \
  --db-instance-identifier conectatech-prod-db \
  --profile im | grep DBInstanceStatus

# 2. Verificar security groups
aws ec2 describe-security-groups \
  --group-ids sg-XXXXXXXX \
  --profile im

# 3. Desde EC2, intentar conectar a RDS
mysql -h ENDPOINT_RDS -u moodleadmin -p moodle -e "SELECT 1;"

# 4. Si falla conexión, verificar:
# - RDS está en estado "available"
# - EC2 está en el mismo VPC
# - Security group RDS permite puerto 3306 desde EC2
```

### Problema: Certificado SSL vencido o error HTTPS

**Síntomas:** Advertencia de seguridad en navegador

**Solución:**

```bash
# 1. Verificar estado del certificado
sudo certbot certificates

# 2. Renovar manualmente
sudo certbot renew --dry-run  # Test
sudo certbot renew --force-renewal  # Real

# 3. Verificar Apache después
sudo systemctl reload apache2

# 4. Verificar en línea
curl -I https://conectatech.co  # Debe ser 200 OK
```

### Problema: Bajo rendimiento / carga alta

**Síntomas:** Página lenta, CPU/memoria alta

**Solución:**

```bash
# 1. Verificar recursos disponibles
free -h
df -h
top -b -n 1 | head -20

# 2. Revisar procesos PHP
ps aux | grep php-fpm | wc -l

# 3. Ver conexiones activas a RDS
netstat -an | grep ESTABLISHED | grep 3306 | wc -l

# 4. Ejecutar cron de Moodle (puede estar retrasado)
sudo -u apache php /var/www/html/moodle/admin/cli/cron.php

# 5. Limpiar cachés
sudo -u apache php /var/www/html/moodle/admin/cli/purge_caches.php

# 6. Si persiste: considerar escalado vertical
# Ver sección "Mantenimiento > Escalado Vertical"
```

### Problema: No puedo conectar por SSH

**Síntomas:** "Permission denied" o "Connection refused"

**Solución:**

```bash
# 1. Verificar permisos de key
ls -la ~/.ssh/ClaveIM.pem
# Debe ser: -r--------

chmod 400 ~/.ssh/ClaveIM.pem

# 2. Verificar que seguridad group permite SSH
aws ec2 describe-security-groups \
  --group-ids sg-XXXXXXXX \
  --profile im

# Debe tener regla:
# - Protocol: TCP
# - Port: 22
# - Source: TU-IP/32

# 3. Verificar IP pública de instancia
aws ec2 describe-instances \
  --instance-ids i-0238341b5897b8e8f \
  --profile im | grep PublicIpAddress

# 4. Intentar conexión con verbose
ssh -vvv -i ~/.ssh/ClaveIM.pem ec2-user@54.86.113.27
```

### Problema: Moodledata sin espacio (Error: No space left)

**Síntomas:** "No space left on device" en logs

**Solución:**

```bash
# 1. Ver uso de disco
df -h /moodledata
du -sh /moodledata/*

# 2. Limpiar cachés seguros
sudo rm -rf /moodledata/cache/*
sudo rm -rf /moodledata/temp/*
sudo -u apache php /var/www/html/moodle/admin/cli/purge_caches.php

# 3. Si aún está lleno, expandir volumen
# AWS Console → EC2 → Volumes
# - Click en volumen de /moodledata
# - Modify Volume → aumentar tamaño
# - En instancia: sudo resize2fs /dev/xvdf

# 4. Verificar archivos viejos
find /moodledata -type f -mtime +90 -delete  # Archivos no modificados en 90 días
```

---

## Estructura del Proyecto

```
conectatech.co/
├── terraform/                      # Infraestructura como código
│   ├── main.tf                     # Provider, data sources, locals
│   ├── ec2.tf                      # Instancia EC2, EBS, EIP, SG
│   ├── rds.tf                      # Base de datos RDS MariaDB
│   ├── variables.tf                # Definición de variables
│   ├── outputs.tf                  # Salidas y estimación de costos
│   ├── terraform.tfvars            # Valores configuración (secreto - no commitear)
│   ├── .gitignore                  # Ignorar archivos sensibles
│   └── terraform.lock              # Lock de versiones de providers
│
├── scripts/                        # Scripts de configuración y setup
│   ├── 01-provision-infrastructure.sh  # Provisionar AWS (deprecated: usar Terraform)
│   ├── 02-setup-server.sh          # Instalar dependencias, Apache, PHP
│   ├── 03-install-moodle.sh        # Descargar e instalar Moodle 5.1
│   ├── 04-configure-ssl.sh         # Instalar Let's Encrypt, configurar HTTPS
│   ├── 05-optimize-system.sh       # Optimizar PHP-FPM, SWAP, etc
│   ├── 06-setup-backups.sh         # Configurar snapshots EBS automáticos
│   └── 07-setup-monitoring.sh      # CloudWatch agent, logs centralizados
│
├── docs/                           # Documentación detallada
│   ├── 01-architecture-overview.md     # Arquitectura y componentes
│   ├── 02-prerequisites.md             # Requisitos previos
│   ├── 03-ec2-configuration.md         # Configuración EC2
│   ├── 04-moodle-installation.md       # Instalación Moodle
│   ├── 05-optimization.md              # Optimización de performance
│   ├── 06-ssl-configuration.md         # SSL/TLS Let's Encrypt
│   ├── 07-backups.md                   # Estrategia de backups
│   ├── 08-monitoring.md                # Monitoreo y alertas
│   ├── 09-maintenance.md               # Mantenimiento operacional
│   └── cuenta-aws.md                   # Detalles de cuenta AWS
│
├── config/                         # Archivos de configuración
│   ├── variables.sh                # Variables bash compartidas
│   └── moodle-default.conf         # Template Apache VirtualHost
│
├── .claude/                        # Instrucciones para Claude Code
│   └── CLAUDE.md                   # Guía para asistente IA
│
├── .gitignore                      # Git ignore rules
├── CLAUDE.md                       # Instrucciones del proyecto
└── README.md                       # Este archivo

```

---

## Costos Estimados

### Configuración Actual Desplegada (t4g.small)

| Servicio | Especificación | Costo/mes | Notas |
|----------|----------------|-----------|-------|
| **EC2** | t4g.small (2 vCPU, 2GB) | $12.00 | ARM64, eligible para free tier |
| **RDS** | db.t4g.micro (1GB) | $12.00 | MariaDB, eligible para free tier |
| **EBS Raíz** | 15 GB gp3 | $1.50 | Sistema operativo |
| **EBS Datos** | 25 GB gp3 | $2.50 | /moodledata |
| **Elastic IP** | 1 IP estática | $3.60 | Por IP, no por uso |
| **Route 53** | 1 zona hosted | $0.50 | conectatech.co |
| **CloudWatch** | Métricas + logs | $2.00 | Estimado |
| **SNS** | Notificaciones | $0.50 | Estimado |
| **Otros** | S3, data transfer | $1.00 | Estimado |
| **TOTAL** | | **$35.60/mes** | |

### Configuración Mejorada (t4g.medium + CloudFront)

Para 300-1000 usuarios con CDN:

| Servicio | Especificación | Costo/mes |
|----------|----------------|-----------|
| EC2 | t4g.medium | $24.00 |
| RDS | db.t4g.small | $24.00 |
| EBS | 50 GB total | $5.00 |
| Elastic IP | 1 IP | $3.60 |
| CloudFront | ~50 GB/mes | $4.25 |
| Route 53 + otros | | $2.00 |
| **TOTAL** | | **$62.85/mes** |

### Ahorros con Free Tier

Nuevas cuentas AWS obtienen 12 meses gratis de:
- EC2 t2/t3 (750 horas/mes)
- RDS db.t3/t4g (750 horas/mes)
- EBS (30 GB gp2/gp3)

**Potencial ahorro:** Primeros 12 meses sin costo de EC2+RDS

---

## Tecnología Stack

### Backend
- **SO:** Amazon Linux 2023 (ARM64)
- **Web Server:** Apache 2.4.66 + OpenSSL
- **Runtime:** PHP 8.3.29 + PHP-FPM
- **Cache:** OPcache (PHP), Moodle cache (file-based)
- **Aplicación:** Moodle 5.1.3

### Database
- **Engine:** MariaDB 10.11.15 LTS
- **Storage:** EBS gp3 con encriptación
- **Backups:** RDS Automated Backups
- **Replicación:** (Opcional) Multi-AZ

### Networking
- **DNS:** Route 53
- **IP:** Elastic IP (estática)
- **CDN:** CloudFront (opcional)
- **SSL/TLS:** Let's Encrypt + Certbot

### Monitoreo
- **Métricas:** CloudWatch
- **Logs:** CloudWatch Logs
- **Alertas:** SNS Email
- **Dashboard:** CloudWatch Dashboard

### IaC & Automatización
- **IaC:** Terraform 1.0+
- **Provisioning:** Bash scripts
- **Git:** Control de versiones
- **CI/CD:** (Futuro)

---

## Características Principales

✅ **Moodle 5.1 Moderno**
- Nueva arquitectura `/public` DocumentRoot
- Routing Engine mejorado
- Plugins actualizados

✅ **Alta Disponibilidad**
- Snapshots automáticos cada 6-24 horas
- RDS backups de 7 días
- Health checks y auto-healing

✅ **Escalabilidad**
- Escalado vertical sin downtime (change instance type)
- Auto-scaling del almacenamiento RDS
- CloudFront para assets estáticos

✅ **Seguridad de Nivel Empresarial**
- SSL/TLS A+ (Let's Encrypt)
- Security Groups granulares
- Encriptación en reposo (EBS, RDS)
- IAM roles sin access keys
- IMDSv2 enforcement

✅ **Monitoreo Integral**
- CloudWatch métricas en tiempo real
- Alarmas automáticas por CPU, memoria, disco
- Logs centralizados
- Notifications por SNS

✅ **Costo Optimizado**
- Instancias ARM64 (20-40% más baratas)
- gp3 volumes vs gp2
- Free tier elegible
- Desde $35.60/mes

---

## Requisitos de Moodle 5.1

| Componente | Mínimo | Actual | Estado |
|-----------|--------|--------|--------|
| **PHP** | 8.2.0 | 8.3.29 | ✅ Compatible |
| **MariaDB** | 10.11.0 | 10.11.15 | ✅ Compatible |
| **MySQL** | 8.4.0 | - | N/A (usando MariaDB) |
| **max_input_vars** | 5000 | 5000+ | ✅ Configurado |
| **Extensiones** | sodium, intl, zip, gd | Todas instaladas | ✅ OK |
| **RAM (recomendado)** | 4GB | 2GB + 2GB SWAP | ✅ Suficiente |

---

## Roadmap Futuro

### Corto Plazo (1-3 meses)
- [ ] Migrar estado Terraform a S3 backend con DynamoDB lock
- [ ] Implementar CloudWatch alarms con SNS notifications
- [ ] Script de restauración de backups automatizado
- [ ] Documentación de Moodle personalizada

### Mediano Plazo (3-6 meses)
- [ ] Aumentar a t4g.medium cuando usuarios crezcan
- [ ] Habilitar RDS Multi-AZ para HA
- [ ] CloudFront para assets estáticos
- [ ] Replicación de lectura RDS

### Largo Plazo (6+ meses)
- [ ] Arquitectura multi-AZ con Load Balancer
- [ ] Auto Scaling Group para redundancia
- [ ] EFS compartido para múltiples instancias
- [ ] Redis/Memcached para sesiones distribuidas
- [ ] CI/CD pipeline (GitHub Actions)

---

## Contribuciones

Este proyecto es una solución interna para ConectaTech.co. Para reportar issues o sugerencias:

1. Documentar el problema detalladamente
2. Incluir versiones de software relevantes
3. Proporcionar logs o stack traces si aplica
4. Contactar al equipo de DevOps

---

## Soporte y Contacto

**Responsable del Proyecto:** Equipo DevOps ConectaTech.co

**Contacto técnico:** ops@conectatech.co

**Documentación:** `/docs/`

**Logs en servidor:**
- Moodle: `/var/www/html/moodle/var/log/moodle.log`
- Apache: `/var/log/apache2/{access,error}_log`
- Sistema: `sudo journalctl -n 100`

---

## Licencia

Este proyecto es propiedad de **Ideas Maestras Inc.** (ConectaTech.co)

Todos los derechos reservados. La infraestructura y scripts incluidos están protegidos bajo licencia propietaria.

---

## Información del Documento

- **Fecha de Creación:** 2026-02-17
- **Última Actualización:** 2026-02-17
- **Versión:** 1.0.0
- **Estado de Despliegue:** Production Ready ✅
- **Mantenedor:** Equipo DevOps

---

**¿Necesitas ayuda? Consulta la documentación detallada en `/docs/` o contacta al equipo de soporte.**
