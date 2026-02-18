# Visión General de Arquitectura

## 🏗️ Arquitectura del Sistema

### Diagrama de Componentes

```
Internet
   │
   ▼
┌─────────────────────────────────────────────┐
│           Route 53 (DNS)                    │
│     moodle.yourdomain.com                   │
└──────────────┬──────────────────────────────┘
               │
               ▼
┌─────────────────────────────────────────────┐
│    CloudFront CDN (Opcional)                │
│    - Cache de assets estáticos              │
│    - Imágenes, CSS, JS                      │
└──────────────┬──────────────────────────────┘
               │
               ▼
┌─────────────────────────────────────────────┐
│         Elastic IP: 52.x.x.x                │
│         (Dirección pública fija)            │
└──────────────┬──────────────────────────────┘
               │
               ▼
┌──────────────────────────────────────────────────────────┐
│                  EC2 Instance                            │
│  ┌────────────────────────────────────────────────────┐  │
│  │  VPC: 172.31.0.0/16 (Default VPC)                  │  │
│  │  Subnet: us-east-1a (Public)                       │  │
│  │  Security Group: moodle-sg                         │  │
│  │    - 22/tcp  (SSH) desde IP específica            │  │
│  │    - 80/tcp  (HTTP) desde 0.0.0.0/0               │  │
│  │    - 443/tcp (HTTPS) desde 0.0.0.0/0              │  │
│  └────────────────────────────────────────────────────┘  │
│                                                          │
│  ┌────────────────────────────────────────────────────┐  │
│  │         Sistema Operativo                          │  │
│  │  Amazon Linux 2023 (ARM64)                         │  │
│  │  ┌──────────────────────────────────────────────┐  │  │
│  │  │  EBS Root Volume (gp3, 15GB)                 │  │  │
│  │  │  /dev/xvda - Sistema operativo               │  │  │
│  │  └──────────────────────────────────────────────┘  │  │
│  │  ┌──────────────────────────────────────────────┐  │  │
│  │  │  EBS Data Volume (gp3, 25-50GB)              │  │  │
│  │  │  /dev/xvdf - /moodledata                     │  │  │
│  │  │  - Archivos de usuarios                      │  │  │
│  │  │  - Cache de Moodle                           │  │  │
│  │  │  - Backups locales                           │  │  │
│  │  └──────────────────────────────────────────────┘  │  │
│  └────────────────────────────────────────────────────┘  │
│                                                          │
│  ┌────────────────────────────────────────────────────┐  │
│  │            Stack LAMP                              │  │
│  │  ┌──────────────────────────────────────────────┐  │  │
│  │  │  Apache 2.4.66 (MPM Event)                   │  │  │
│  │  │  - mod_ssl, mod_rewrite, mod_deflate         │  │  │
│  │  │  - Virtual Host configurado                  │  │  │
│  │  │  - Let's Encrypt SSL/TLS                     │  │  │
│  │  └──────────────────────────────────────────────┘  │  │
│  │  ┌──────────────────────────────────────────────┐  │  │
│  │  │  PHP 8.4.x (PHP-FPM)                         │  │  │
│  │  │  - Optimizado para memoria disponible        │  │  │
│  │  │  - OPcache configurado                       │  │  │
│  │  │  - Extensiones: gd, zip, soap, intl, etc.   │  │  │
│  │  └──────────────────────────────────────────────┘  │  │
│  │  ┌──────────────────────────────────────────────┐  │  │
│  │  │  Moodle 5.1                                  │  │  │
│  │  │  /var/www/html/moodle                        │  │  │
│  │  │  - DocumentRoot: /public                     │  │  │
│  │  │  - Core de Moodle                            │  │  │
│  │  │  - Plugins (sobre /public)                   │  │  │
│  │  │  - Themes                                    │  │  │
│  │  │  - Routing Engine                            │  │  │
│  │  └──────────────────────────────────────────────┘  │  │
│  └────────────────────────────────────────────────────┘  │
│                                                          │
│  ┌────────────────────────────────────────────────────┐  │
│  │         Servicios del Sistema                      │  │
│  │  - Cron: Scheduled tasks cada minuto              │  │
│  │  - Logrotate: Rotación de logs                    │  │
│  │  - Certbot: Renovación SSL automática            │  │
│  │  - CloudWatch Agent: Métricas y logs              │  │
│  └────────────────────────────────────────────────────┘  │
└──────────────┬───────────────────────────────────────────┘
               │
               ▼
┌─────────────────────────────────────────────┐
│      RDS MariaDB 10.11.15+                  │
│  ┌───────────────────────────────────────┐  │
│  │  Security Group: moodle-rds-sg        │  │
│  │    - 3306/tcp desde moodle-sg         │  │
│  └───────────────────────────────────────┘  │
│                                             │
│  - Multi-AZ: Opcional                       │
│  - Automated Backups: 7 días                │
│  - db.t4g.micro / db.t4g.small             │
│  - Storage: 20-100 GB gp3                   │
└─────────────────────────────────────────────┘
```

## 📋 Componentes Detallados

### 1. Compute (EC2)

**Instancia Principal:**
- **Tipo:** t4g.medium (4 vCPU, 4 GB RAM) - Básico
- **Tipo:** t4g.large (2 vCPU, 8 GB RAM) - Recomendado
- **Arquitectura:** ARM64 (Graviton2) - 20% más económico
- **AMI:** Amazon Linux 2023
- **Placement:** Single AZ (alta disponibilidad no requerida para single server)

**¿Por qué ARM64/Graviton?**
- 20-40% mejor precio/performance vs x86
- Menor consumo energético
- Compatibilidad completa con LAMP stack
- Ideal para workloads web

**Dimensionamiento:**

| Usuarios Concurrentes | Instancia | RAM | vCPU | Costo/mes |
|------------------------|-----------|-----|------|-----------|
| 50-100 | t4g.small | 2 GB | 2 | ~$12 |
| 100-300 | t4g.medium | 4 GB | 2 | ~$24 |
| 300-1000 | t4g.large | 8 GB | 2 | ~$48 |
| 1000+ | t4g.xlarge | 16 GB | 4 | ~$96 |

### 2. Storage (EBS)

**Root Volume (Sistema Operativo):**
- **Tipo:** gp3 (última generación)
- **Tamaño:** 15 GB
- **IOPS:** 3000 (baseline)
- **Throughput:** 125 MB/s
- **Encriptación:** Habilitada
- **Snapshot:** Diario

**Data Volume (Moodledata):**
- **Tipo:** gp3
- **Tamaño:** 25-50 GB (crece según contenido)
- **Mount:** /moodledata
- **IOPS:** 3000-16000 (configurable)
- **Contenido:**
  - Archivos subidos por usuarios
  - Cache de Moodle (localcache)
  - Sesiones (si se usa file-based)
  - Backups temporales

**¿Por qué gp3 vs gp2?**
- Hasta 20% más barato
- Performance desacoplado del tamaño
- Ajustable sin redimensionar volumen

**Cálculo de Moodledata:**
```
Base: 5 GB (instalación limpia)
+ (Número de usuarios × 100 MB promedio)
+ (Cursos × 200 MB promedio)
+ Buffer 30%
```

Ejemplo: 500 usuarios, 50 cursos
```
5 GB + (500 × 0.1 GB) + (50 × 0.2 GB) + 30% = ~75 GB
```

### 3. Database (RDS MariaDB)

**Configuración:**
- **Engine:** MariaDB 10.11.15 (LTS)
- **Instancia:** db.t4g.micro / db.t4g.small
- **Storage:** 20 GB gp3 (auto-scaling hasta 100 GB)
- **Multi-AZ:** No (single server, se puede habilitar)
- **Backups:** Automáticos, 7 días retención
- **Maintenance Window:** Domingo 3-4 AM

**¿Por qué RDS vs Local?**
- ✅ Backups automáticos
- ✅ Parches de seguridad automáticos
- ✅ Alta disponibilidad con Multi-AZ
- ✅ Read replicas para escalabilidad
- ✅ Monitoreo integrado
- ❌ Más costoso (~$12/mes vs $0)

**Dimensionamiento:**

| Usuarios | Instancia | RAM | Costo/mes |
|----------|-----------|-----|-----------|
| < 500 | db.t4g.micro | 1 GB | ~$12 |
| 500-2000 | db.t4g.small | 2 GB | ~$24 |
| 2000-5000 | db.t4g.medium | 4 GB | ~$48 |

### 4. Networking

**Elastic IP:**
- Dirección IPv4 pública estática
- Persiste entre stop/start de instancia
- Reasignable en minutos (rollback rápido)
- Costo: ~$3.60/mes

**Security Groups:**

**moodle-sg (EC2):**
```
Inbound:
  - 22/tcp   desde <tu-ip>/32 (SSH admin)
  - 80/tcp   desde 0.0.0.0/0 (HTTP)
  - 443/tcp  desde 0.0.0.0/0 (HTTPS)

Outbound:
  - All traffic (para updates, RDS, etc)
```

**moodle-rds-sg (RDS):**
```
Inbound:
  - 3306/tcp desde moodle-sg (solo EC2)

Outbound:
  - Ninguno requerido
```

### 5. CDN (CloudFront) - Opcional

**Propósito:**
- Cache de assets estáticos (CSS, JS, imágenes)
- Reducir latencia global
- Reducir carga en EC2
- Protección DDoS básica

**Configuración:**
- **Origin:** EC2 Elastic IP o dominio
- **Cache Behavior:**
  - `/theme/` → TTL 86400s (24h) - servido desde /public
  - `/pluginfile.php/` → TTL 3600s (1h) - procesado por Routing Engine
  - `/` → No cache (dinámico)
- **Compress:** Habilitado
- **HTTP/2:** Habilitado

**Nota sobre Moodle 5.1:** Los assets ahora se sirven a través del directorio `/public`. El Routing Engine de Moodle 5.1 maneja las solicitudes de forma transparente.

**Costo:**
- Primeros 1TB/mes: $0.085/GB
- 50GB/mes ≈ $4.25/mes

### 6. DNS (Route 53)

**Hosted Zone:**
- $0.50/mes por zona
- $0.40/millón de queries

**Registros:**
```
A     moodle.yourdomain.com     → 52.x.x.x (Elastic IP)
AAAA  moodle.yourdomain.com     → (IPv6 opcional)
CNAME www.moodle.yourdomain.com → moodle.yourdomain.com
```

Si usas CloudFront:
```
A     moodle.yourdomain.com     → d1234.cloudfront.net (Alias)
```

## 🔄 Flujo de Datos

### Request Flow (Sin CDN)

```
Usuario → Route 53 → Elastic IP → Security Group →
Apache → PHP-FPM → Moodle → RDS MariaDB
```

### Request Flow (Con CDN)

```
Usuario → Route 53 → CloudFront (cache hit) → Return
                  ↓ (cache miss)
         Elastic IP → Apache → PHP-FPM → Moodle → RDS
```

### Cron Jobs Flow

```
Cron (cada minuto) → PHP CLI → Moodle CLI →
Scheduled Tasks → Database/Email/etc
```

## 🛡️ Alta Disponibilidad

Esta arquitectura es **Single Server** optimizado. Para alta disponibilidad:

### Opciones de HA (Fuera del scope, pero documentado)

**Nivel 1: Snapshots Frecuentes**
- Snapshots EBS cada 6 horas
- RDS automated backups
- RTO: ~15 minutos
- RPO: hasta 6 horas

**Nivel 2: Multi-AZ RDS**
- Failover automático de DB
- RTO: ~2 minutos
- RPO: 0 (sincrónico)
- Costo: +100% en RDS

**Nivel 3: Multi-EC2 + ALB**
- Application Load Balancer
- Auto Scaling Group (2+ instancias)
- Shared EFS para moodledata
- RTO: ~0 (activo-activo)
- Costo: +300-400%

**Para este skill: Nivel 1** (Snapshots + SWAP + Monitoreo)

## 💰 Resumen de Costos

### Configuración Básica (100-300 usuarios)

| Servicio | Especificación | Costo/mes |
|----------|----------------|-----------|
| EC2 | t4g.medium | $24.00 |
| EBS Root | 15 GB gp3 | $1.50 |
| EBS Data | 25 GB gp3 | $2.50 |
| RDS | db.t4g.micro | $12.00 |
| Elastic IP | 1 IP | $3.60 |
| Route 53 | 1 zona + queries | $0.50 |
| CloudWatch | Métricas + alarmas | $1.00 |
| **Total** | | **$45.10/mes** |

### Configuración Mejorada (300-1000 usuarios)

| Servicio | Especificación | Costo/mes |
|----------|----------------|-----------|
| EC2 | t4g.large | $48.00 |
| EBS Root | 15 GB gp3 | $1.50 |
| EBS Data | 50 GB gp3 | $5.00 |
| RDS | db.t4g.small | $24.00 |
| Elastic IP | 1 IP | $3.60 |
| Route 53 | 1 zona + queries | $0.50 |
| CloudWatch | Métricas + alarmas | $2.00 |
| CloudFront | ~50 GB/mes | $4.25 |
| **Total** | | **$88.85/mes** |

## 📈 Escalabilidad

### Vertical Scaling (Recomendado para inicio)

**Proceso:**
1. Stop instancia EC2
2. Cambiar instance type
3. Start instancia
4. Downtime: ~2-3 minutos

**Límites:**
- Hasta t4g.2xlarge (8 vCPU, 32 GB)
- Sin cambios de arquitectura

### Horizontal Scaling (Avanzado)

**Requiere:**
- Application Load Balancer
- Auto Scaling Group
- EFS en lugar de EBS para moodledata
- Session storage en Redis/Memcached
- Refactor significativo

## 🔒 Seguridad

### Network Security

- Security Groups como firewall
- NACLs (Network ACLs) por defecto
- SSH solo desde IPs autorizadas
- RDS no expuesto públicamente

### Application Security

- SSL/TLS con Let's Encrypt (A+ rating)
- HTTPS redirect automático
- Security headers (HSTS, X-Frame-Options)
- Moodle security best practices

### Data Security

- EBS encrypted at rest
- RDS encrypted at rest
- Backups encrypted
- SSL/TLS en tránsito (application → RDS)

### Access Security

- IAM roles para EC2 (no access keys)
- MFA en cuenta AWS
- SSH keys, no passwords
- Firewall local (firewalld)

## 📊 Monitoreo

### Métricas Clave

**EC2:**
- CPU Utilization
- Memory Used % (custom)
- Disk Used % (custom)
- Network In/Out

**RDS:**
- CPU Utilization
- Database Connections
- Read/Write Latency
- Free Storage Space

**Aplicación:**
- Apache requests/sec
- PHP-FPM active processes
- Moodle cron execution time
- Error logs count

### Alarmas

- CPU > 80% por 5 min
- Memory available < 500 MB
- Disk > 80% used
- RDS connections > 80% max
- Status check failed

## 🆕 Moodle 5.1: Estructura de Directorios /public

### Cambio Crítico en DocumentRoot

**Anterior (Moodle ≤4.x):**
```
DocumentRoot /var/www/html/moodle
```

**Nuevo (Moodle 5.1+):**
```
DocumentRoot /var/www/html/moodle/public
```

### ¿Por Qué Este Cambio?

1. **Seguridad Mejorada:** Solo archivos en `/public` son accesibles por web
2. **Separación Clara:** Código de aplicación separado de archivos públicos
3. **Mejores Prácticas:** Alineado con frameworks modernos (Laravel, Symfony, etc.)
4. **Routing Engine:** Nuevo motor de procesamiento de solicitudes

### Estructura de Directorios Moodle 5.1

```
/var/www/html/moodle/
├── admin/               # Scripts de administración (NO web-accesible)
│   └── cli/            # Comandos CLI (permanecen aquí)
├── auth/               # Plugins de autenticación
├── blocks/             # Bloques de Moodle
├── course/             # Código de cursos
├── lib/                # Librerías core
├── mod/                # Módulos de actividades
├── theme/              # Temas (código)
├── config.php          # Configuración (permanece aquí)
├── public/             # ← NUEVO: DocumentRoot del servidor web
│   ├── index.php      # Punto de entrada principal
│   ├── theme/         # Assets de temas (accesibles)
│   └── ...            # Otros archivos web-accesibles
└── ...
```

### Lo Que NO Cambia

| Elemento | Ubicación | Notas |
|----------|-----------|-------|
| **Moodledata** | `/moodledata` | Sin cambios |
| **Config.php** | `/var/www/html/moodle/config.php` | Permanece en raíz de Moodle |
| **Scripts CLI** | `/var/www/html/moodle/admin/cli/` | Por encima de /public |
| **Plugins core** | `/var/www/html/moodle/{auth,mod,blocks,...}` | Sin cambios |

### Lo Que SÍ Cambia

| Elemento | Cambio |
|----------|--------|
| **Apache DocumentRoot** | Apuntar a `/var/www/html/moodle/public` |
| **Assets estáticos** | Ahora servidos desde `/public/theme/`, `/public/pluginfile.php`, etc. |
| **Routing** | Nuevo Routing Engine procesa todas las solicitudes |

### Configuración de Apache para Moodle 5.1

```apache
<VirtualHost *:80>
    ServerName moodle.yourdomain.com
    DocumentRoot /var/www/html/moodle/public

    <Directory /var/www/html/moodle/public>
        Options -Indexes +FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>

    # Moodledata NO debe ser accesible por web
    <Directory /moodledata>
        Require all denied
    </Directory>
</VirtualHost>
```

### Migración de Plugins

Después de actualizar a Moodle 5.1:

1. **Plugins estándar:** Migran automáticamente
2. **Plugins personalizados:** Pueden requerir ajustes
3. **Verificación:** Ejecutar `admin/cli/check_database_schema.php`
4. **Reubicación manual:** Solo si el plugin tiene assets web específicos

### Routing Engine

El nuevo motor de enrutamiento:
- **Activo por defecto** (fuertemente recomendado)
- Procesa todas las solicitudes a través de `/public/index.php`
- Permite URLs más limpias
- Mejora el rendimiento de solicitudes
- Maneja redirecciones de forma más eficiente

### Requisitos Técnicos Moodle 5.1

| Componente | Mínimo | Recomendado |
|------------|--------|-------------|
| **PHP** | 8.2.0 | 8.3.x o 8.4.x |
| **PostgreSQL** | 15.0 | 16+ |
| **MySQL** | 8.4.0 | 8.4+ |
| **MariaDB** | 10.11.0 | 10.11.15+ ✅ |
| **max_input_vars** | 5000 | 5000+ ✅ |

**Extensiones PHP adicionales:** `sodium` (requerida, ya incluida ✅)

### Camino de Actualización

**Desde Moodle 4.x a 5.1:**

**Requisito:** Estar en Moodle 4.2.3 o superior

**Pasos:**
1. Backup completo (código + base de datos)
2. Activar modo mantenimiento
3. Actualizar código de Moodle a 5.1
4. **Reconfigurar Apache:** DocumentRoot → `/var/www/html/moodle/public`
5. Ejecutar `admin/cli/upgrade.php --non-interactive`
6. Verificar plugins (relocación si es necesario)
7. Limpiar cachés
8. Desactivar modo mantenimiento
9. Verificar funcionamiento del Routing Engine

**IMPORTANTE:** Probar en entorno de staging primero.

## 🎯 Próximos Pasos

1. Revisar [Prerequisitos](02-prerequisites.md)
2. Configurar [EC2](03-ec2-configuration.md)
3. Instalar [Moodle](04-moodle-installation.md)

---

**Fecha:** 2026-02-11
**Versión:** 1.1.0
