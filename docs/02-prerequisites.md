# Prerequisitos para Deployment de Moodle 5.1

Lista completa de requisitos y preparación antes de ejecutar el deployment.

## 🔐 Cuenta AWS

### Requisitos Mínimos

- Cuenta AWS activa
- Usuario IAM con permisos necesarios
- Billing alerts configuradas (recomendado)

### Permisos IAM Necesarios

El usuario/role debe tener permisos para:

```json
{
  "Version": "2012-10-17",
  "Statement": [
    {
      "Effect": "Allow",
      "Action": [
        "ec2:*",
        "rds:*",
        "iam:CreateRole",
        "iam:CreatePolicy",
        "iam:AttachRolePolicy",
        "iam:PassRole",
        "route53:*",
        "cloudfront:*",
        "cloudwatch:*",
        "sns:*",
        "s3:*"
      ],
      "Resource": "*"
    }
  ]
}
```

**Alternativa:** Usar `AdministratorAccess` policy (solo para desarrollo/testing)

### AWS CLI Configuración

```bash
# Instalar AWS CLI v2
# macOS
brew install awscli

# Linux
curl "https://awscli.amazonaws.com/awscli-exe-linux-aarch64.zip" -o "awscliv2.zip"
unzip awscliv2.zip
sudo ./aws/install

# Verificar instalación
aws --version

# Configurar credenciales
aws configure --profile moodle
# AWS Access Key ID: tu-access-key
# AWS Secret Access Key: tu-secret-key
# Default region: us-east-1
# Default output format: json

# Verificar configuración
aws sts get-caller-identity --profile moodle
```

## 🔑 EC2 Key Pair

### Crear Key Pair

**Opción 1: AWS Console**
1. EC2 → Key Pairs → Create key pair
2. Name: `moodle-keypair`
3. Type: RSA
4. Format: .pem
5. Download y guardar en `~/.ssh/moodle-keypair.pem`

**Opción 2: AWS CLI**
```bash
aws ec2 create-key-pair \
  --key-name moodle-keypair \
  --query 'KeyMaterial' \
  --output text \
  --profile moodle > ~/.ssh/moodle-keypair.pem

chmod 400 ~/.ssh/moodle-keypair.pem
```

### Verificar Key Pair

```bash
aws ec2 describe-key-pairs \
  --key-names moodle-keypair \
  --profile moodle
```

## 🌐 Dominio

### Requisitos

- Dominio registrado (puede ser de cualquier registrar)
- Acceso a DNS management

### Opciones

**Opción A: Dominio en Route 53** (Recomendado)
- Terraform puede crear registros DNS automáticamente
- Integración nativa con AWS

**Opción B: Dominio Externo (GoDaddy, Namecheap, etc.)**
- Crear manualmente registro A después del deployment
- Apuntar a Elastic IP provisioned por Terraform

### Preparación Route 53 (Si aplica)

```bash
# Crear hosted zone
aws route53 create-hosted-zone \
  --name yourdomain.com \
  --caller-reference $(date +%s) \
  --profile moodle

# Anotar el zone ID para variables.tf
```

## 💻 Herramientas Locales

### Terraform

```bash
# macOS
brew install terraform

# Linux
wget https://releases.hashicorp.com/terraform/1.6.0/terraform_1.6.0_linux_amd64.zip
unzip terraform_1.6.0_linux_amd64.zip
sudo mv terraform /usr/local/bin/

# Verificar
terraform version
```

### Git

```bash
# macOS
brew install git

# Linux
sudo dnf install git -y  # Amazon Linux/RHEL
sudo apt install git -y  # Ubuntu/Debian

# Verificar
git --version
```

### Otras Utilidades

```bash
# jq (JSON processor)
brew install jq  # macOS
sudo dnf install jq -y  # Linux

# SSH cliente (normalmente pre-instalado)
ssh -V
```

## 📁 Preparación de Archivos

### 1. Clonar/Descargar Skills

```bash
# Si tienes el repositorio
git clone https://github.com/your-repo/moodle-aws-skills.git
cd moodle-aws-skills/skills/

# O copiar la carpeta skills/ a tu máquina
```

### 2. Configurar Variables

```bash
# Terraform variables
cd terraform/
cp terraform.tfvars.example terraform.tfvars
chmod 600 terraform.tfvars
vim terraform.tfvars  # Editar con tus valores

# Scripts variables
cd ../config/
cp variables.sh.example variables.sh
chmod 600 variables.sh
vim variables.sh  # Editar con tus valores
```

### 3. Validar Configuración

```bash
# Cargar variables
source config/variables.sh

# Validar
validate_config

# Mostrar configuración
show_config
```

## 🔒 Seguridad

### Passwords y Secrets

Genera passwords seguros para:

```bash
# Database password
openssl rand -base64 16

# Moodle admin password
openssl rand -base64 16
```

Actualiza en `terraform.tfvars`:
```hcl
db_password                = "tu-password-generado"
moodle_admin_password      = "otro-password-generado"
```

### IP Whitelisting

Obtén tu IP pública:
```bash
curl ifconfig.me
```

Actualiza `allowed_ssh_cidrs` en `terraform.tfvars`:
```hcl
allowed_ssh_cidrs = ["tu.ip.publica/32"]
```

### Proteger Archivos Sensibles

```bash
chmod 600 terraform/terraform.tfvars
chmod 600 config/variables.sh
chmod 400 ~/.ssh/moodle-keypair.pem

# Agregar a .gitignore
echo "terraform.tfvars" >> .gitignore
echo "variables.sh" >> .gitignore
echo "*.pem" >> .gitignore
```

## 💰 Consideraciones de Costos

### Estimación Inicial

Para deployment básico (t4g.medium + db.t4g.micro):
- EC2: ~$24/mes
- RDS: ~$12/mes
- EBS: ~$4/mes
- Otros: ~$5/mes
- **Total: ~$45/mes**

### Configurar Billing Alerts

```bash
# Crear SNS topic para alertas
aws sns create-topic \
  --name billing-alerts \
  --profile moodle

# Subscribir tu email
aws sns subscribe \
  --topic-arn arn:aws:sns:us-east-1:ACCOUNT-ID:billing-alerts \
  --protocol email \
  --notification-endpoint tu-email@example.com \
  --profile moodle

# Crear alarma de billing (requiere región us-east-1)
aws cloudwatch put-metric-alarm \
  --alarm-name billing-alert-50usd \
  --alarm-description "Alert when charges exceed $50" \
  --metric-name EstimatedCharges \
  --namespace AWS/Billing \
  --statistic Maximum \
  --period 21600 \
  --evaluation-periods 1 \
  --threshold 50 \
  --comparison-operator GreaterThanThreshold \
  --dimensions Name=Currency,Value=USD \
  --alarm-actions arn:aws:sns:us-east-1:ACCOUNT-ID:billing-alerts \
  --region us-east-1 \
  --profile moodle
```

## ✅ Checklist Pre-Deployment

### AWS

- [ ] Cuenta AWS activa
- [ ] AWS CLI instalado y configurado
- [ ] IAM user/role con permisos necesarios
- [ ] EC2 key pair creado
- [ ] Billing alerts configuradas

### Dominio

- [ ] Dominio registrado
- [ ] Acceso a DNS management
- [ ] (Opcional) Route 53 hosted zone creada

### Herramientas

- [ ] Terraform instalado (>= 1.0)
- [ ] Git instalado
- [ ] SSH cliente disponible
- [ ] jq instalado

### Configuración

- [ ] `terraform.tfvars` creado y editado
- [ ] `variables.sh` creado y editado
- [ ] Passwords seguros generados
- [ ] IP pública obtenida para SSH whitelist
- [ ] Archivos sensibles protegidos (chmod 600)

### Conocimiento

- [ ] Revisado [architecture-overview.md](01-architecture-overview.md)
- [ ] Entendido el flujo de deployment
- [ ] Estimación de costos revisada
- [ ] Plan de backups considerado

## 🚀 Siguiente Paso

Una vez completado este checklist:

👉 [03-ec2-configuration.md](03-ec2-configuration.md) - Provisionar infraestructura

## 📚 Referencias

- [AWS CLI Documentation](https://docs.aws.amazon.com/cli/)
- [Terraform AWS Provider](https://registry.terraform.io/providers/hashicorp/aws/latest/docs)
- [AWS Free Tier](https://aws.amazon.com/free/)
- [AWS Cost Calculator](https://calculator.aws/)

---

**Fecha:** 2026-02-02
**Versión:** 1.0.0
