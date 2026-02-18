# Sistema de Backups para Moodle 5.1

Guía completa de estrategias de backup, recuperación y disaster recovery.

## 🎯 Objetivos

- Proteger datos contra pérdidas
- Permitir recovery point objective (RPO) < 24 horas
- Recovery time objective (RTO) < 1 hora
- Automatizar backups sin intervención manual
- Minimizar impacto en producción

## 📋 Estrategia de Backup

### 3-2-1 Rule

✅ **3** copias de datos
✅ **2** medios diferentes
✅ **1** copia offsite

**Implementación:**
- Original: Datos en producción (EC2 + RDS)
- Backup 1: Snapshots EBS + RDS automated backups
- Backup 2: Dumps locales (/var/backups/moodle)
- Offsite: S3 (opcional pero recomendado)

### Componentes a Respaldar

| Componente | Frecuencia | Retención | Tamaño Aprox | Prioridad |
|------------|------------|-----------|--------------|-----------|
| Base de datos | Diario | 7 días | 100-500 MB | 🔴 Crítico |
| Moodledata | Semanal | 7 días | 5-50 GB | 🔴 Crítico |
| Código Moodle | Semanal | 30 días | 500 MB | 🟡 Importante |
| Configuración | Semanal | 30 días | < 1 MB | 🟡 Importante |
| EBS Snapshots | Diario | 7 días | Incremental | 🟢 Conveniente |

## 🚀 Configuración Automatizada

### Script de Setup

```bash
# Ejecutar script de configuración
sudo /path/to/skills/scripts/06-setup-backups.sh
```

Este script configura:
- ✅ Directorios de backup
- ✅ Scripts de backup (DB, data, config)
- ✅ Cron jobs automáticos
- ✅ Rotación de backups antiguos
- ✅ Script de restore

## 💾 Backup de Base de Datos

### Método Automatizado (Recomendado)

```bash
# Ya configurado por script
# Cron: Diario a las 3 AM
# Script: /usr/local/bin/moodle-backup-db.sh
# Ubicación: /var/backups/moodle/database/
# NOTA: Los backups de base de datos NO son afectados por cambios en Moodle 5.1
```

### Backup Manual

```bash
# Backup simple
mysqldump -h $DB_HOST -u $DB_USER -p"$DB_PASSWORD" $DB_NAME | gzip > moodle-$(date +%Y%m%d).sql.gz

# Backup optimizado (recomendado)
mysqldump -h $DB_HOST -u $DB_USER -p"$DB_PASSWORD" \
    --single-transaction \
    --quick \
    --lock-tables=false \
    --routines \
    --triggers \
    $DB_NAME | gzip > moodle-$(date +%Y%m%d).sql.gz

# Con progress bar (opcional)
mysqldump -h $DB_HOST -u $DB_USER -p"$DB_PASSWORD" \
    --single-transaction \
    --quick \
    --lock-tables=false \
    $DB_NAME | pv | gzip > moodle-$(date +%Y%m%d).sql.gz
```

**Opciones explicadas:**
- `--single-transaction`: Consistency sin bloquear tablas (InnoDB)
- `--quick`: No bufferea todo en memoria
- `--lock-tables=false`: No bloquea (importante para producción)
- `--routines`: Incluye stored procedures
- `--triggers`: Incluye triggers

### Backup Incremental (Avanzado)

Para bases de datos muy grandes:

```bash
# Habilitar binary logs en RDS
# AWS Console → RDS → Parameter Groups
# binlog_format = ROW
# backup_retention_period > 0

# Configurar backup incremental
sudo vim /usr/local/bin/moodle-backup-db-incremental.sh
```

```bash
#!/bin/bash
BACKUP_DIR="/var/backups/moodle/database/incremental"
FULL_BACKUP_DAY=0  # Sunday

mkdir -p "$BACKUP_DIR"

DAY=$(date +%w)

if [ $DAY -eq $FULL_BACKUP_DAY ]; then
    # Full backup semanal
    mysqldump [opciones] > "$BACKUP_DIR/full-$(date +%Y%m%d).sql.gz"
else
    # Incremental (binary logs)
    mysqlbinlog --read-from-remote-server \
        --host=$DB_HOST \
        --user=$DB_USER \
        --password="$DB_PASSWORD" \
        --raw \
        --result-file="$BACKUP_DIR/binlog-$(date +%Y%m%d)-" \
        mysql-bin.*
fi
```

## 📁 Backup de Moodledata

### Método Automatizado

```bash
# Ya configurado: Semanal domingos 4 AM
# Script: /usr/local/bin/moodle-backup-data.sh
# Ubicación: /var/backups/moodle/moodledata/
# NOTA: El directorio /moodledata NO cambia de ubicación en Moodle 5.1
```

### Backup Manual

```bash
# Backup completo (excluye cache/temp)
# NOTA: Las exclusiones de cache/temp permanecen iguales en Moodle 5.1
sudo tar -czf moodledata-$(date +%Y%m%d).tar.gz \
    --exclude='/moodledata/cache/*' \
    --exclude='/moodledata/localcache/*' \
    --exclude='/moodledata/temp/*' \
    --exclude='/moodledata/sessions/*' \
    /moodledata

# Con progress y verificación
sudo tar -czf - \
    --exclude='/moodledata/cache/*' \
    --exclude='/moodledata/localcache/*' \
    --exclude='/moodledata/temp/*' \
    /moodledata | pv > moodledata-$(date +%Y%m%d).tar.gz

# Verificar integridad
tar -tzf moodledata-$(date +%Y%m%d).tar.gz > /dev/null && echo "OK" || echo "ERROR"
```

### Backup del Código Moodle

**NUEVO en Moodle 5.1:** El código ahora incluye el subdirectorio `/public` que debe ser incluido en los backups.

```bash
# Backup del código Moodle (incluye el nuevo directorio /public)
sudo tar -czf moodle-code-$(date +%Y%m%d).tar.gz \
    --exclude='/var/www/html/moodle/.git' \
    /var/www/html/moodle

# Verificar que /public está incluido
tar -tzf moodle-code-$(date +%Y%m%d).tar.gz | grep "public/" | head -5
```

**Importante:** En Moodle 5.1, asegúrate de que los backups del código incluyan el nuevo directorio `/public` junto con todos los archivos en el nivel superior (config.php, admin/, lib/, theme/, etc.).

### Backup Diferencial (Más Rápido)

Solo archivos modificados desde último full backup:

```bash
#!/bin/bash
BACKUP_DIR="/var/backups/moodle/moodledata"
FULL_BACKUP="$BACKUP_DIR/moodledata-full-$(date +%Y%m%d).tar.gz"
DIFF_BACKUP="$BACKUP_DIR/moodledata-diff-$(date +%Y%m%d).tar.gz"
SNAPSHOT_FILE="/var/backups/moodle/.snapshot"

# Full backup si no existe snapshot o es domingo
if [ ! -f "$SNAPSHOT_FILE" ] || [ $(date +%w) -eq 0 ]; then
    tar -czf "$FULL_BACKUP" \
        --exclude-caches \
        --exclude='/moodledata/cache/*' \
        --exclude='/moodledata/temp/*' \
        -g "$SNAPSHOT_FILE" \
        /moodledata
else
    # Differential backup
    tar -czf "$DIFF_BACKUP" \
        --exclude-caches \
        --exclude='/moodledata/cache/*' \
        --exclude='/moodledata/temp/*' \
        -g "$SNAPSHOT_FILE" \
        /moodledata
fi
```

### Sincronización con S3 (Offsite)

```bash
# Instalar AWS CLI si no está
sudo dnf install awscli -y

# Configurar credentials (usar IAM role preferiblemente)
aws configure

# Sync a S3
aws s3 sync /var/backups/moodle/ s3://your-bucket/moodle-backups/ \
    --storage-class STANDARD_IA \
    --exclude "*.log"

# O script automatizado
sudo vim /usr/local/bin/sync-backups-s3.sh
```

```bash
#!/bin/bash
BUCKET="s3://your-bucket/moodle-backups"
LOCAL="/var/backups/moodle"

# Sync database backups (más recientes)
aws s3 sync "$LOCAL/database/" "$BUCKET/database/" \
    --storage-class STANDARD_IA

# Sync moodledata backups (últimos 2)
cd "$LOCAL/moodledata"
ls -t | head -2 | xargs -I {} aws s3 cp {} "$BUCKET/moodledata/{}"\
    --storage-class DEEP_ARCHIVE

# Lifecycle policy para eliminar antiguos
# Configurar en S3 console o:
aws s3api put-bucket-lifecycle-configuration \
    --bucket your-bucket \
    --lifecycle-configuration file://lifecycle.json
```

**lifecycle.json:**
```json
{
  "Rules": [
    {
      "Id": "DeleteOldBackups",
      "Status": "Enabled",
      "Prefix": "moodle-backups/",
      "Expiration": {
        "Days": 90
      }
    }
  ]
}
```

## 📸 EBS Snapshots

### Método Automatizado (Terraform/AWS Backup)

Ya incluido en terraform si `enable_ebs_snapshots = true`

### Snapshot Manual

```bash
# Snapshot de volumen de datos
aws ec2 create-snapshot \
    --volume-id vol-xxxxx \
    --description "Moodle data backup $(date +%Y%m%d)" \
    --tag-specifications 'ResourceType=snapshot,Tags=[{Key=Name,Value=moodle-data-manual},{Key=Date,Value='$(date +%Y%m%d)'}]'

# Snapshot de root volume
aws ec2 create-snapshot \
    --volume-id vol-yyyyy \
    --description "Moodle root backup $(date +%Y%m%d)" \
    --tag-specifications 'ResourceType=snapshot,Tags=[{Key=Name,Value=moodle-root-manual}]'
```

### Data Lifecycle Manager (DLM)

Automatiza snapshots con políticas:

```bash
# Crear lifecycle policy
aws dlm create-lifecycle-policy \
    --execution-role-arn arn:aws:iam::ACCOUNT:role/AWSDataLifecycleManagerDefaultRole \
    --description "Daily snapshots for Moodle" \
    --state ENABLED \
    --policy-details file://snapshot-policy.json
```

**snapshot-policy.json:**
```json
{
  "ResourceTypes": ["VOLUME"],
  "TargetTags": [
    {
      "Key": "Name",
      "Value": "moodle-prod-data"
    }
  ],
  "Schedules": [
    {
      "Name": "DailySnapshots",
      "CreateRule": {
        "Interval": 24,
        "IntervalUnit": "HOURS",
        "Times": ["02:00"]
      },
      "RetainRule": {
        "Count": 7
      },
      "TagsToAdd": [
        {
          "Key": "Type",
          "Value": "DailySnapshot"
        }
      ],
      "CopyTags": true
    }
  ]
}
```

## 🗄️ RDS Automated Backups

### Configuración

Ya incluido en Terraform:
```hcl
backup_retention_period = 7  # días
backup_window = "03:00-04:00"  # UTC
```

### Backup Manual

```bash
# Crear snapshot manual de RDS
aws rds create-db-snapshot \
    --db-instance-identifier your-db \
    --db-snapshot-identifier moodle-manual-$(date +%Y%m%d)

# Ver snapshots
aws rds describe-db-snapshots \
    --db-instance-identifier your-db

# Restaurar desde snapshot
aws rds restore-db-instance-from-db-snapshot \
    --db-instance-identifier moodle-restored \
    --db-snapshot-identifier moodle-manual-20260202
```

## 🔄 Restauración

### Restaurar Base de Datos

```bash
# 1. Listar backups disponibles
ls -lh /var/backups/moodle/database/

# 2. Verificar backup
gunzip < moodle-db-20260202.sql.gz | head -100

# 3. Restaurar (CUIDADO: Sobrescribe datos actuales)
# Hacer backup de DB actual primero!
mysqldump -h $DB_HOST -u $DB_USER -p"$DB_PASSWORD" $DB_NAME > current-backup.sql

# Restaurar desde backup
gunzip < moodle-db-20260202.sql.gz | mysql -h $DB_HOST -u $DB_USER -p"$DB_PASSWORD" $DB_NAME

# 4. Verificar
mysql -h $DB_HOST -u $DB_USER -p"$DB_PASSWORD" $DB_NAME -e "SELECT COUNT(*) FROM mdl_user;"

# 5. Limpiar cache Moodle
sudo -u apache php /var/www/html/moodle/admin/cli/purge_caches.php
```

### Restaurar Moodledata

```bash
# 1. Enable maintenance mode
sudo -u apache php /var/www/html/moodle/admin/cli/maintenance.php --enable

# 2. Backup actual
sudo tar -czf /tmp/moodledata-current-$(date +%Y%m%d-%H%M).tar.gz /moodledata

# 3. Restaurar
sudo tar -xzf /var/backups/moodle/moodledata/moodledata-20260202.tar.gz -C /

# 4. Fix permissions
sudo chown -R apache:apache /moodledata
sudo chmod -R 770 /moodledata

# 5. Disable maintenance mode
sudo -u apache php /var/www/html/moodle/admin/cli/maintenance.php --disable
```

### Restaurar desde EBS Snapshot

```bash
# 1. Crear volumen desde snapshot
SNAPSHOT_ID="snap-xxxxx"
AZ="us-east-1a"

VOLUME_ID=$(aws ec2 create-volume \
    --snapshot-id $SNAPSHOT_ID \
    --availability-zone $AZ \
    --volume-type gp3 \
    --query 'VolumeId' \
    --output text)

echo "New volume: $VOLUME_ID"

# 2. Esperar a available
aws ec2 wait volume-available --volume-ids $VOLUME_ID

# 3. Stop instancia actual (opcional si usas otro servidor)
aws ec2 stop-instances --instance-ids i-xxxxx
aws ec2 wait instance-stopped --instance-ids i-xxxxx

# 4. Detach volumen actual
aws ec2 detach-volume --volume-id vol-current

# 5. Attach nuevo volumen
aws ec2 attach-volume \
    --volume-id $VOLUME_ID \
    --instance-id i-xxxxx \
    --device /dev/sdf

# 6. Start instancia
aws ec2 start-instances --instance-ids i-xxxxx

# 7. Mount en el servidor (si es necesario)
sudo mount /dev/xvdf /moodledata
```

### Disaster Recovery Completo

Escenario: Pérdida total de servidor

```bash
# 1. Provision nueva infraestructura con Terraform
cd skills/terraform
terraform apply

# 2. Restaurar RDS desde snapshot
aws rds restore-db-instance-from-db-snapshot \
    --db-instance-identifier moodle-restored \
    --db-snapshot-identifier latest-automated-snapshot

# 3. Restaurar EBS desde snapshot
# (Ver proceso arriba)

# 4. Setup servidor
cd ../scripts
./02-setup-server.sh

# 5. Instalar Moodle (sin crear DB, ya existe)
# Modificar config.php para apuntar a DB restaurada

# 6. Test y verificar
```

## 📊 Monitoreo de Backups

### Script de Verificación

```bash
sudo vim /usr/local/bin/verify-backups.sh
```

```bash
#!/bin/bash
BACKUP_DIR="/var/backups/moodle"
EMAIL="admin@yourdomain.com"
LOG="/var/log/backup-verification.log"

echo "=== Backup Verification $(date) ===" >> $LOG

# Check database backups
DB_BACKUP=$(find $BACKUP_DIR/database -name "moodle-db-*.sql.gz" -mtime -1 | wc -l)
if [ $DB_BACKUP -eq 0 ]; then
    echo "WARNING: No database backup in last 24h" >> $LOG
    # Send alert
fi

# Check moodledata backups
DATA_BACKUP=$(find $BACKUP_DIR/moodledata -name "moodledata-*.tar.gz" -mtime -7 | wc -l)
if [ $DATA_BACKUP -eq 0 ]; then
    echo "WARNING: No moodledata backup in last 7 days" >> $LOG
fi

# Check disk space
USAGE=$(df -h $BACKUP_DIR | awk 'NR==2 {print $5}' | sed 's/%//')
if [ $USAGE -gt 80 ]; then
    echo "WARNING: Backup disk usage at ${USAGE}%" >> $LOG
fi

# Check RDS snapshots
RDS_SNAPSHOTS=$(aws rds describe-db-snapshots \
    --db-instance-identifier your-db \
    --query 'length(DBSnapshots)' \
    --output text)

echo "Database backups: $DB_BACKUP" >> $LOG
echo "Moodledata backups: $DATA_BACKUP" >> $LOG
echo "Disk usage: ${USAGE}%" >> $LOG
echo "RDS snapshots: $RDS_SNAPSHOTS" >> $LOG
```

### CloudWatch Custom Metrics

```bash
# Publicar métricas de backup
aws cloudwatch put-metric-data \
    --namespace Moodle/Backups \
    --metric-name DatabaseBackupAge \
    --value $AGE_HOURS \
    --unit Hours

# Crear alarma
aws cloudwatch put-metric-alarm \
    --alarm-name backup-too-old \
    --alarm-description "Database backup older than 48h" \
    --namespace Moodle/Backups \
    --metric-name DatabaseBackupAge \
    --statistic Maximum \
    --period 3600 \
    --threshold 48 \
    --comparison-operator GreaterThanThreshold \
    --evaluation-periods 1
```

## 🧪 Test de Backups

### Verificación Mensual

```bash
# 1. Restaurar en ambiente de test
# (Nunca en producción)

# 2. Verificar integridad de datos
mysql -h test-db -u user -p testdb -e "
    SELECT
        COUNT(*) as users,
        MAX(timecreated) as latest_user
    FROM mdl_user;
"

# 3. Verificar archivos
ls -la /test-moodledata/filedir/ | head -20

# 4. Test funcional básico
# - Login como admin
# - Ver lista de cursos
# - Abrir un curso
# - Descargar un archivo

# 5. Documentar resultado
echo "$(date): Backup test successful" >> /var/log/backup-tests.log
```

## 💰 Costos de Almacenamiento

### S3 Storage Classes

| Class | $/GB-mes | Retrieval | Use Case |
|-------|----------|-----------|----------|
| Standard | $0.023 | Inmediato | Backups recientes |
| Standard-IA | $0.0125 | Inmediato | Backups mensuales |
| Glacier Instant | $0.004 | Inmediato | Compliance |
| Glacier Flexible | $0.0036 | 1-5 min | Archival |
| Glacier Deep | $0.00099 | 12 horas | Long-term |

**Recomendación:**
- Últimos 7 días: Standard ($1.61/70GB)
- 8-30 días: Standard-IA ($0.88/70GB)
- 31-90 días: Glacier Flexible ($0.25/70GB)

### Lifecycle Policy Óptima

```json
{
  "Rules": [
    {
      "Id": "BackupLifecycle",
      "Status": "Enabled",
      "Transitions": [
        {
          "Days": 7,
          "StorageClass": "STANDARD_IA"
        },
        {
          "Days": 30,
          "StorageClass": "GLACIER"
        }
      ],
      "Expiration": {
        "Days": 90
      }
    }
  ]
}
```

## ✅ Best Practices

### Checklist

- [x] Backups automatizados configurados
- [x] Multiple backup types (DB, data, snapshots)
- [x] Offsite backups (S3)
- [x] Retention policies definidas
- [x] Restore procedures documentadas
- [x] Backups tested mensualmente
- [x] Alertas configuradas
- [x] Disk space monitoreado
- [x] Encryption at rest habilitada
- [x] Access logs enabled

### Recomendaciones

1. **Test restauraciones regularmente**
   - Al menos mensual
   - Documentar tiempo de recovery
   - Verificar integridad de datos

2. **Monitorear espacio en disco**
   ```bash
   df -h /var/backups
   du -sh /var/backups/moodle/*
   ```

3. **Implementar 3-2-1 rule completo**
   - Local backups (/var/backups)
   - EBS snapshots
   - S3 offsite

4. **Automatizar todo**
   - No confiar en procesos manuales
   - Scripts + cron + monitoring

5. **Documentar procedimientos**
   - Recovery runbook actualizado
   - Contactos de emergencia
   - RTO/RPO definidos

## 📚 Referencias

- [Moodle Backup Documentation](https://docs.moodle.org/en/Backup)
- [AWS Backup](https://aws.amazon.com/backup/)
- [mysqldump Manual](https://dev.mysql.com/doc/refman/8.0/en/mysqldump.html)
- [AWS Data Lifecycle Manager](https://docs.aws.amazon.com/ebs/latest/userguide/snapshot-lifecycle.html)

---

**Fecha:** 2026-02-11
**Versión:** 1.1.0
