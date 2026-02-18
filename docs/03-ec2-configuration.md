# Configuración de EC2 para Moodle 5.1

Guía completa para provisionar y configurar la infraestructura EC2 en AWS.

## 🎯 Objetivos

- Provisionar infraestructura AWS con Terraform
- Configurar instancia EC2 optimizada
- Establecer almacenamiento EBS
- Configurar networking y seguridad
- Preparar servidor para Moodle

## 🏗️ Provisión de Infraestructura

### Opción 1: Terraform (Recomendado)

#### Preparación

```bash
# Navegar al directorio terraform
cd skills/terraform/

# Copiar y editar variables
cp terraform.tfvars.example terraform.tfvars
vim terraform.tfvars
```

#### Variables Clave a Configurar

```hcl
# terraform.tfvars

# AWS Configuration
aws_profile  = "default"  # Tu perfil AWS CLI
aws_region   = "us-east-1"
key_pair_name = "moodle-keypair"  # Debe existir en AWS

# EC2 Instance
instance_type = "t4g.medium"  # o t4g.large
allowed_ssh_cidrs = ["1.2.3.4/32"]  # TU IP!

# RDS Database
db_instance_class = "db.t4g.micro"
db_password = "YourSecurePassword123!"  # CAMBIAR!

# Domain
domain_name = "moodle.yourdomain.com"
admin_email = "admin@yourdomain.com"

# Moodle
moodle_admin_password = "AdminPass123!"  # CAMBIAR!
```

#### Ejecutar Terraform

```bash
# Inicializar
terraform init

# Preview cambios
terraform plan -out=tfplan

# Aplicar
terraform apply tfplan

# Guardar outputs
terraform output -json > infrastructure-outputs.json
```

#### Outputs Importantes

```bash
# Ver outputs
terraform output

# Outputs clave:
# - ec2_public_ip: IP pública (Elastic IP)
# - ec2_instance_id: ID de la instancia
# - rds_address: Endpoint de RDS
# - ssh_command: Comando para conectar
```

### Opción 2: Script Automatizado

```bash
# Usa el script wrapper
cd skills/scripts/
./01-provision-infrastructure.sh

# Este script:
# - Valida configuración
# - Ejecuta Terraform
# - Guarda outputs
# - Muestra next steps
```

## 🖥️ Instancia EC2

### Tipos de Instancia Recomendados

| Instancia | vCPU | RAM | Usuarios | Costo/mes | Uso |
|-----------|------|-----|----------|-----------|-----|
| t4g.small | 2 | 2 GB | 50-100 | ~$12 | Testing/Dev |
| t4g.medium | 2 | 4 GB | 100-300 | ~$24 | Producción básica ⭐ |
| t4g.large | 2 | 8 GB | 300-1000 | ~$48 | Producción recomendada ⭐⭐ |
| t4g.xlarge | 4 | 16 GB | 1000+ | ~$96 | Alto tráfico |

**¿Por qué t4g (ARM/Graviton)?**
- 20-40% más económico vs t3 (x86)
- Mejor performance por dólar
- Menor consumo energético
- Compatible con stack LAMP

### AMI Recomendada

**Amazon Linux 2023 (AL2023) ARM64**

```bash
# Auto-lookup en Terraform (incluido en código)
# Características:
# - Kernel optimizado
# - SELinux habilitado
# - Soporte a largo plazo
# - Actualizaciones de seguridad
# - PHP 8.4, Apache 2.4, MariaDB 10.11
```

**Búsqueda manual:**
```bash
aws ec2 describe-images \
  --owners amazon \
  --filters "Name=name,Values=al2023-ami-2023.*-arm64" \
  --query 'sort_by(Images, &CreationDate)[-1].[ImageId,Name,CreationDate]' \
  --output table
```

### User Data Script

El código Terraform incluye un script de User Data que:

```bash
✅ Formatea y monta volumen de datos (/moodledata)
✅ Configura SWAP (si habilitado)
✅ Actualiza paquetes del sistema
✅ Instala utilidades básicas
✅ Crea archivo de configuración (/root/moodle-config.env)
```

**Nota importante para Moodle 5.1:** El directorio `/moodledata` permanece sin cambios en su ubicación. Sin embargo, el DocumentRoot de Apache ahora apuntará a `/var/www/html/moodle/public` debido al nuevo Routing Engine de Moodle 5.1.

Ver código completo en: [terraform/ec2.tf](../terraform/ec2.tf)

## 💾 Almacenamiento EBS

### Volumen Root (Sistema Operativo)

```hcl
Tipo: gp3
Tamaño: 15 GB
IOPS: 3000 (baseline)
Throughput: 125 MB/s
Encriptado: Sí
```

**Contenido:**
- Sistema operativo
- Software (Apache, PHP, etc.)
- Código de Moodle
- Logs del sistema

### Volumen Data (Moodledata)

```hcl
Tipo: gp3
Tamaño: 25-50 GB (ajustable)
IOPS: 3000-16000 (configurable)
Throughput: 125-1000 MB/s
Encriptado: Sí
Mount: /moodledata
```

**Contenido:**
- Archivos subidos por usuarios
- Cache de Moodle
- Sesiones (opcional)
- Backups temporales

**Cálculo de tamaño:**
```
Base: 5 GB (Moodle limpio)
+ (Usuarios × 100 MB promedio)
+ (Cursos × 200 MB promedio)
+ Buffer 30%

Ejemplo: 500 usuarios, 50 cursos
= 5 + (500×0.1) + (50×0.2) + 30%
= 5 + 50 + 10 = 65 GB × 1.3 = ~85 GB
```

### gp3 vs gp2

| Característica | gp3 | gp2 |
|----------------|-----|-----|
| Costo | ~$0.08/GB | ~$0.10/GB |
| IOPS base | 3000 | Escalado por tamaño |
| IOPS máximo | 16,000 | 16,000 |
| Throughput | Configurable | Escalado por IOPS |
| Performance | Desacoplado | Acoplado a tamaño |

**Recomendación:** Siempre usa gp3 (más barato y flexible)

### Snapshots

```bash
# Crear snapshot manual
aws ec2 create-snapshot \
  --volume-id vol-xxxxx \
  --description "Moodle data before update" \
  --tag-specifications 'ResourceType=snapshot,Tags=[{Key=Name,Value=moodle-data-manual}]'

# Listar snapshots
aws ec2 describe-snapshots \
  --owner-ids self \
  --filters "Name=tag:Name,Values=moodle-*"

# Restaurar desde snapshot
# 1. Crear volumen desde snapshot
# 2. Detach volumen actual
# 3. Attach nuevo volumen
# 4. Mount y verificar
```

## 🌐 Networking

### VPC y Subnets

Por defecto usa **Default VPC** (simplifica setup):

```
VPC: 172.31.0.0/16 (default)
Subnets: Public subnets en cada AZ
Internet Gateway: Preconfigurado
Route Tables: Configuradas
```

**Para VPC personalizada:**
```hcl
# En terraform.tfvars
vpc_id = "vpc-xxxxx"
subnet_id = "subnet-xxxxx"
```

### Elastic IP

**¿Por qué Elastic IP?**
- IP pública estática (persistente)
- Sobrevive stop/start de instancia
- Reasignable en segundos (rollback rápido)
- DNS apunta a IP fija

**Configuración:**
```hcl
resource "aws_eip" "moodle" {
  domain   = "vpc"
  instance = aws_instance.moodle.id
}
```

**Costo:** ~$3.60/mes (gratis si está asociada)

### Security Groups

#### Security Group EC2 (moodle-sg)

```hcl
Inbound:
  SSH (22)    desde tu-ip/32        # Admin access
  HTTP (80)   desde 0.0.0.0/0       # Let's Encrypt validation
  HTTPS (443) desde 0.0.0.0/0       # Public web access

Outbound:
  All traffic                        # Updates, RDS, etc.
```

**Mejora de seguridad:**
```bash
# Restringir SSH después de setup
aws ec2 authorize-security-group-ingress \
  --group-id sg-xxxxx \
  --protocol tcp \
  --port 22 \
  --cidr tu.ip.publica/32

# Remover regla permisiva anterior
aws ec2 revoke-security-group-ingress \
  --group-id sg-xxxxx \
  --protocol tcp \
  --port 22 \
  --cidr 0.0.0.0/0
```

#### Security Group RDS (moodle-rds-sg)

```hcl
Inbound:
  MySQL (3306) desde moodle-sg      # Solo desde EC2

Outbound:
  Ninguno (no necesario)
```

### Firewall Local (firewalld)

Configurado automáticamente por script:

```bash
# Estado
sudo firewall-cmd --state

# Servicios permitidos
sudo firewall-cmd --list-services

# Reglas
sudo firewall-cmd --list-all

# Agregar regla personalizada
sudo firewall-cmd --permanent --add-port=8080/tcp
sudo firewall-cmd --reload
```

## 🔐 IAM y Permisos

### IAM Role para EC2

Terraform crea automáticamente:

```hcl
IAM Role: moodle-prod-ec2-role
Policies attached:
  - CloudWatchAgentServerPolicy    # Métricas y logs
  - AmazonSSMManagedInstanceCore   # Systems Manager
  - Custom: moodle-backups         # Snapshots
```

**Beneficios:**
- No access keys en el servidor
- Rotación automática de credenciales
- Audit trail completo
- Acceso granular

### Policy de Backups

```json
{
  "Version": "2012-10-17",
  "Statement": [
    {
      "Effect": "Allow",
      "Action": [
        "ec2:CreateSnapshot",
        "ec2:CreateTags",
        "ec2:DescribeSnapshots",
        "ec2:DescribeVolumes"
      ],
      "Resource": "*"
    }
  ]
}
```

## 🔧 Configuración Post-Provisión

### 1. Conectar al Servidor

```bash
# Obtener comando SSH
terraform output ssh_command

# O manualmente
ssh -i ~/.ssh/your-key.pem ec2-user@elastic-ip
```

### 2. Verificar User Data

```bash
# Ver log de user-data
sudo cat /var/log/user-data.log

# Verificar configuración creada
sudo cat /root/moodle-config.env

# Verificar montajes
df -h
lsblk
mount | grep moodledata
```

### 3. Verificar SWAP (si habilitado)

```bash
# Ver SWAP
swapon --show
free -h

# Verificar swappiness
cat /proc/sys/vm/swappiness
# Debería ser 10 (configurado por user-data)
```

### 4. Actualizar Sistema

```bash
# Actualizar todos los paquetes
sudo dnf update -y

# Verificar versiones
cat /etc/os-release
uname -r
```

## 🚀 Siguiente: Setup del Servidor

Una vez la infraestructura está provisionada:

```bash
# En tu máquina local
cd skills/scripts/

# Ejecutar setup del servidor (via SSH)
ssh -i ~/.ssh/your-key.pem ec2-user@elastic-ip 'bash -s' < 02-setup-server.sh

# O conectar y ejecutar
ssh -i ~/.ssh/your-key.pem ec2-user@elastic-ip
sudo /path/to/02-setup-server.sh
```

El script instala:
- Apache 2.4
- PHP 8.4 + extensiones
- MariaDB client
- Git, wget, certbot
- Configuración básica

Ver: [02-setup-server.sh](../scripts/02-setup-server.sh)

## 💰 Costos Detallados

### Configuración Básica (t4g.medium)

| Recurso | Especificación | Precio Unitario | Costo/mes |
|---------|----------------|-----------------|-----------|
| EC2 | t4g.medium | $0.0336/hora | $24.48 |
| EBS Root | 15 GB gp3 | $0.08/GB-mes | $1.20 |
| EBS Data | 25 GB gp3 | $0.08/GB-mes | $2.00 |
| Elastic IP | 1 IP asociada | $0.005/hora | $3.60 |
| Data Transfer | 100 GB/mes | Primeros 100GB gratis | $0.00 |
| **Subtotal EC2** | | | **$31.28** |

### Almacenamiento Adicional

| Tipo | Precio | Ejemplo |
|------|--------|---------|
| gp3 IOPS adicional | $0.005/IOPS-mes | 10,000 IOPS = $50 |
| gp3 Throughput | $0.04/MB/s-mes | 500 MB/s = $20 |
| Snapshots | $0.05/GB-mes | 20 GB = $1 |

### Cálculo Total (Ejemplo Real)

```
EC2 t4g.medium:           $24.48
EBS Root 15GB:            $1.20
EBS Data 25GB:            $2.00
Elastic IP:               $3.60
RDS db.t4g.micro:         $12.26
RDS Storage 20GB:         $2.30
CloudWatch (básico):      $1.00
Route 53 (1 zona):        $0.50
─────────────────────────────────
TOTAL:                    $47.34/mes
```

**Con t4g.large:**
```
EC2 t4g.large (+$24):     $48.48
RDS db.t4g.small (+$12):  $24.52
EBS Data 50GB:            $4.00
Otros:                    $10.60
─────────────────────────────────
TOTAL:                    $87.60/mes
```

## 📊 Monitoreo de Recursos

### CloudWatch Metrics

```bash
# Ver métricas de CPU
aws cloudwatch get-metric-statistics \
  --namespace AWS/EC2 \
  --metric-name CPUUtilization \
  --dimensions Name=InstanceId,Value=i-xxxxx \
  --start-time $(date -u -d '1 hour ago' +%Y-%m-%dT%H:%M:%S) \
  --end-time $(date -u +%Y-%m-%dT%H:%M:%S) \
  --period 300 \
  --statistics Average

# Ver métricas de disco
aws cloudwatch get-metric-statistics \
  --namespace AWS/EBS \
  --metric-name VolumeReadOps \
  --dimensions Name=VolumeId,Value=vol-xxxxx \
  --start-time $(date -u -d '1 hour ago' +%Y-%m-%dT%H:%M:%S) \
  --end-time $(date -u +%Y-%m-%dT%H:%M:%S) \
  --period 300 \
  --statistics Sum
```

### Instance Status

```bash
# Status checks
aws ec2 describe-instance-status \
  --instance-ids i-xxxxx

# System logs
aws ec2 get-console-output \
  --instance-id i-xxxxx
```

## 🔄 Operaciones Comunes

### Stop/Start Instancia

```bash
# Stop (conserva Elastic IP)
aws ec2 stop-instances --instance-ids i-xxxxx

# Start
aws ec2 start-instances --instance-ids i-xxxxx

# Verificar estado
aws ec2 describe-instances \
  --instance-ids i-xxxxx \
  --query 'Reservations[0].Instances[0].State.Name'
```

**Nota:** Elastic IP se mantiene, no cambia IP pública

### Resize Instancia

```bash
# 1. Stop instancia
aws ec2 stop-instances --instance-ids i-xxxxx

# 2. Esperar a stopped
aws ec2 wait instance-stopped --instance-ids i-xxxxx

# 3. Cambiar tipo
aws ec2 modify-instance-attribute \
  --instance-id i-xxxxx \
  --instance-type "{\"Value\": \"t4g.large\"}"

# 4. Start
aws ec2 start-instances --instance-ids i-xxxxx
```

### Expandir Volumen EBS

```bash
# 1. Modificar tamaño
aws ec2 modify-volume \
  --volume-id vol-xxxxx \
  --size 50

# 2. Esperar a optimizing
aws ec2 describe-volumes-modifications \
  --volume-ids vol-xxxxx

# 3. En el servidor, expandir filesystem
sudo growpart /dev/nvme1n1 1
sudo resize2fs /dev/nvme1n1
```

## 🛡️ Seguridad y Best Practices

### Checklist de Seguridad

- [x] Security Groups con reglas mínimas necesarias
- [x] SSH solo desde IPs específicas
- [x] Firewall local (firewalld) activo
- [x] SELinux habilitado y configurado
- [x] EBS volumes encriptados
- [x] IAM roles (no access keys)
- [x] Elastic IP (no IP pública aleatoria)
- [x] Logs habilitados (CloudWatch)
- [x] Backups automatizados
- [x] Monitoring activo

### Hardening Adicional

```bash
# Deshabilitar password SSH
sudo sed -i 's/PasswordAuthentication yes/PasswordAuthentication no/' /etc/ssh/sshd_config
sudo systemctl restart sshd

# Configurar fail2ban (opcional)
sudo dnf install fail2ban -y
sudo systemctl enable fail2ban
sudo systemctl start fail2ban

# Auditoría de accesos
sudo ausearch -m USER_LOGIN -ts recent
```

## 📚 Referencias

- [AWS EC2 User Guide](https://docs.aws.amazon.com/ec2/)
- [Amazon Linux 2023](https://docs.aws.amazon.com/linux/al2023/)
- [EBS Volume Types](https://docs.aws.amazon.com/ebs/latest/userguide/ebs-volume-types.html)
- [Terraform AWS Provider](https://registry.terraform.io/providers/hashicorp/aws/latest/docs)

## ✅ Next Steps

1. **Setup Servidor:** [02-setup-server.sh](../scripts/02-setup-server.sh)
2. **Instalar Moodle:** [04-moodle-installation.md](04-moodle-installation.md)
3. **Configurar SSL:** [06-ssl-configuration.md](06-ssl-configuration.md)

---

**Fecha:** 2026-02-11
**Versión:** 1.1.0
