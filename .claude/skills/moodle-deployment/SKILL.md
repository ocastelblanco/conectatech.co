---
name: moodle-deployment
description: Complete guide for deploying and managing Moodle 5.1 on AWS. Provides an overview of all available skills and recommended deployment workflow. Use when planning a full deployment, wanting to understand available capabilities, or needing cost estimates and architecture overview. Triggers: "deploy Moodle", "full deployment", "production Moodle", "Moodle on AWS guide", "deployment workflow", "Moodle architecture", "cost estimate".
---

# Moodle 5.1 AWS Deployment Guide

## Available Skills

This project includes 8 specialized skills for Moodle deployment and management:

### Infrastructure & Setup

| Skill | Command | Description |
|-------|---------|-------------|
| [Provision Infrastructure](../provision-infrastructure/SKILL.md) | `/provision-infrastructure` | Creates AWS infrastructure (EC2, RDS, EBS, Security Groups) |
| [Setup Server](../setup-moodle-server/SKILL.md) | `/setup-moodle-server` | Installs LAMP stack (Apache, PHP 8.4, MariaDB client) |
| [Install Moodle](../install-moodle/SKILL.md) | `/install-moodle` | Downloads and configures Moodle 5.1 |
| [Configure SSL](../configure-ssl/SKILL.md) | `/configure-ssl` | Sets up HTTPS with Let's Encrypt |

### Operations & Maintenance

| Skill | Command | Description |
|-------|---------|-------------|
| [Optimize System](../optimize-system/SKILL.md) | `/optimize-system` | Tunes PHP-FPM and system for performance |
| [Setup Backups](../setup-backups/SKILL.md) | `/setup-backups` | Configures automated 3-2-1 backup strategy |
| [Setup Monitoring](../setup-monitoring/SKILL.md) | `/setup-monitoring` | Configures CloudWatch metrics and alarms |
| [Troubleshoot](../troubleshoot-moodle/SKILL.md) | `/troubleshoot-moodle` | Diagnoses and resolves common issues |

## Deployment Workflows

### Full Production Deployment

For a complete production deployment with SSL, backups, and monitoring:

```
1. /provision-infrastructure  --> AWS resources created
2. /setup-moodle-server       --> LAMP stack configured
3. /install-moodle            --> Moodle 5.1 installed
4. /configure-ssl             --> HTTPS enabled
5. /optimize-system           --> Performance tuned
6. /setup-backups             --> Automated backups
7. /setup-monitoring          --> CloudWatch alarms
```

**Prompt example:**
```
Deploy production Moodle 5.1 on AWS with SSL, backups, and monitoring.
Domain: moodle.example.com
Instance: t4g.medium
```

### Quick Development Deployment

For a development or testing environment:

```
1. /provision-infrastructure  --> AWS resources
2. /setup-moodle-server       --> LAMP stack
3. /install-moodle            --> Moodle installed
```

**Prompt example:**
```
Deploy development Moodle 5.1 on AWS t4g.small instance.
```

### Troubleshooting Workflow

When issues are reported:

```
1. /troubleshoot-moodle       --> Diagnose issue
2. /optimize-system           --> If performance related
3. /configure-ssl             --> If SSL related
4. /setup-backups             --> If backup related
```

**Prompt example:**
```
My Moodle site is showing 503 errors. Please diagnose and fix.
```

## Architecture Overview

**Nota Moodle 5.1:** El DocumentRoot de Apache debe apuntar a `/var/www/html/moodle/public` en lugar de `/var/www/html/moodle`. Moodle 5.1 introduce un nuevo motor de enrutamiento (Routing Engine) que procesa todas las solicitudes a traves del directorio `/public`.

```
+-------------------------------------------------------+
|                     AWS Cloud                          |
|  +--------------------------------------------------+ |
|  |                VPC (10.0.0.0/16)                 | |
|  |                                                  | |
|  |  +----------------+      +------------------+    | |
|  |  |  Public Subnet |      |  Private Subnet  |    | |
|  |  |                |      |                  |    | |
|  |  |  +----------+  |      |  +------------+  |    | |
|  |  |  |   EC2    |  |      |  |    RDS     |  |    | |
|  |  |  | t4g.med  |<-+------+->|  MariaDB   |  |    | |
|  |  |  |  Moodle  |  |      |  | db.t4g.mic |  |    | |
|  |  |  +----------+  |      |  +------------+  |    | |
|  |  +----------------+      +------------------+    | |
|  +--------------------------------------------------+ |
|                                                        |
|  +------------------+    +---------------------+       |
|  |    Elastic IP    |    |    CloudWatch       |       |
|  +------------------+    +---------------------+       |
|                                                        |
|  +------------------+    +---------------------+       |
|  |    S3 Bucket     |    |     Route 53        |       |
|  |   (Backups)      |    |   (DNS optional)    |       |
|  +------------------+    +---------------------+       |
+--------------------------------------------------------+
```

## Cost Estimates

| Component | Specification | Cost/month |
|-----------|--------------|------------|
| EC2 (t4g.medium) | 4 GB RAM, 2 vCPU | ~$24 |
| RDS (db.t4g.micro) | MariaDB | ~$15 |
| EBS (gp3) | 100 GB | ~$8 |
| S3 (backups) | ~200 GB | ~$5 |
| CloudWatch | Metrics + Logs | ~$10 |
| Data Transfer | 100 GB/month | ~$9 |
| **Total** | | **~$71/month** |

## Instance Sizing Guide

| Instance | RAM | Concurrent Users | Use Case |
|----------|-----|------------------|----------|
| t4g.small | 2GB | 50-100 | Development/Testing |
| t4g.medium | 4GB | 100-300 | Small production |
| t4g.large | 8GB | 300-1000 | Medium production |
| t4g.xlarge | 16GB | 1000+ | Large production |

## Prerequisites

### AWS Account Requirements

- Active AWS account
- IAM user with permissions:
  - EC2: Full access
  - RDS: Full access
  - VPC: Full access
  - CloudWatch: Full access
  - S3: Create buckets, write objects
- AWS CLI configured with credentials
- EC2 SSH key pair created

### Local Requirements

- Terraform 1.5+ (for infrastructure provisioning)
- AWS CLI 2.x
- SSH client
- Domain name (for SSL)
- Email address (for SSL and alerts)

## Notas Especificas de Moodle 5.1

### Cambios en la estructura de directorios

- **DocumentRoot:** Apache debe apuntar a `/var/www/html/moodle/public` (no a `/var/www/html/moodle`)
- **Directorio `/public`:** Se crea automaticamente al clonar el repositorio. Contiene el punto de entrada para el nuevo Routing Engine
- **`/moodledata`:** Sin cambios, permanece en `/moodledata`
- **`config.php`:** Sin cambios, permanece en `/var/www/html/moodle/config.php`
- **CLI paths:** Sin cambios, permanecen en `/var/www/html/moodle/admin/cli/...`

### Requisitos de PHP

- **Minimo:** PHP 8.2.0 (anteriormente 8.1)
- **Recomendado:** PHP 8.4.x

### Routing Engine

Moodle 5.1 introduce un nuevo motor de enrutamiento que procesa todas las solicitudes HTTP a traves del directorio `/public`. Esto mejora la seguridad al separar los archivos publicos del codigo fuente.

### Migracion de plugins (upgrades)

Al actualizar desde versiones anteriores a Moodle 5.1:

1. Actualizar Apache DocumentRoot a `/var/www/html/moodle/public`
2. Verificar que PHP cumple con el minimo 8.2.0
3. Verificar compatibilidad de plugins con el nuevo Routing Engine
4. Probar que todas las URLs responden correctamente despues del cambio

## Related Resources

- **Scripts:** `scripts/` - Automation scripts for each skill
- **Terraform:** `terraform/` - Infrastructure as Code
- **Documentation:** `docs/` - Technical guides

## Real-World Experience

These skills are based on actual deployment and optimization of the **ACG Calidad** project:

- **Challenge:** OOM kills causing site downtime
- **Solution:** Implemented `/optimize-system` skill
- **Result:** 99.9%+ uptime, stable memory usage
