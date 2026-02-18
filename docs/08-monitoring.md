# Monitoreo y Observabilidad para Moodle 5.1

Guía completa de monitoreo con CloudWatch, alertas y troubleshooting.

## 🎯 Objetivos

- Visibilidad completa del sistema
- Detección proactiva de problemas
- Alertas automatizadas
- Métricas de performance
- Logging centralizado
- Dashboards informativos

## 📊 Componentes de Monitoreo

### Stack Completo

```
┌─────────────────────────────────────────────────┐
│              CloudWatch Console                 │
│  ┌─────────────┐  ┌──────────┐  ┌────────────┐ │
│  │ Dashboards  │  │  Alarms  │  │  Insights  │ │
│  └─────────────┘  └──────────┘  └────────────┘ │
└────────────┬────────────────────────────────────┘
             │
             ▼
┌─────────────────────────────────────────────────┐
│           CloudWatch Agent (EC2)                │
│  ┌──────────────┐         ┌─────────────────┐  │
│  │   Metrics    │         │      Logs       │  │
│  │  CPU, Memory │         │ Apache, PHP-FPM │  │
│  │  Disk, Net   │         │ System, Custom  │  │
│  └──────────────┘         └─────────────────┘  │
└─────────────────────────────────────────────────┘
             │
             ▼
┌─────────────────────────────────────────────────┐
│              Moodle Server                      │
│  Apache | PHP-FPM | Moodle | Scripts           │
└─────────────────────────────────────────────────┘
```

## 🚀 Configuración Automatizada

### Script de Setup

```bash
sudo /path/to/skills/scripts/07-setup-monitoring.sh
```

Configura:
- ✅ CloudWatch Agent
- ✅ Logs collection
- ✅ Custom metrics
- ✅ Health check scripts
- ✅ Log rotation

## 📈 CloudWatch Agent

### Instalación Manual

```bash
# Download agent
wget https://s3.amazonaws.com/amazoncloudwatch-agent/amazon_linux/arm64/latest/amazon-cloudwatch-agent.rpm

# Install
sudo rpm -U ./amazon-cloudwatch-agent.rpm

# Verificar
/opt/aws/amazon-cloudwatch-agent/bin/amazon-cloudwatch-agent-ctl \
    -a query -m ec2 -c default -s
```

### Configuración

El script crea `/opt/aws/amazon-cloudwatch-agent/etc/amazon-cloudwatch-agent.json`:

```json
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
          }
        ],
        "metrics_collection_interval": 60
      },
      "disk": {
        "measurement": [
          {
            "name": "used_percent",
            "rename": "DISK_USED",
            "unit": "Percent"
          }
        ],
        "metrics_collection_interval": 60,
        "resources": ["*"]
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
          }
        ],
        "metrics_collection_interval": 60
      }
    }
  }
}
```

### Iniciar Agent

```bash
# Start
sudo /opt/aws/amazon-cloudwatch-agent/bin/amazon-cloudwatch-agent-ctl \
    -a fetch-config \
    -m ec2 \
    -s \
    -c file:/opt/aws/amazon-cloudwatch-agent/etc/amazon-cloudwatch-agent.json

# Status
sudo systemctl status amazon-cloudwatch-agent

# Enable on boot
sudo systemctl enable amazon-cloudwatch-agent
```

## 📊 Métricas Clave

### EC2 Standard Metrics (Gratis)

| Métrica | Descripción | Threshold Normal |
|---------|-------------|------------------|
| CPUUtilization | % CPU usado | < 80% |
| NetworkIn | Bytes recibidos | Varía |
| NetworkOut | Bytes enviados | Varía |
| DiskReadOps | IOPS lectura | < 3000 |
| DiskWriteOps | IOPS escritura | < 3000 |
| StatusCheckFailed | Checks fallidos | 0 |

### Custom Metrics (CloudWatch Agent)

| Métrica | Namespace | Threshold |
|---------|-----------|-----------|
| CPU_IDLE | Moodle/EC2 | > 20% |
| MEMORY_USED | Moodle/EC2 | < 80% |
| MEMORY_AVAILABLE | Moodle/EC2 | > 500 MB |
| DISK_USED | Moodle/EC2 | < 80% |
| SWAP_USED | Moodle/EC2 | < 50% |
| TCP_ESTABLISHED | Moodle/EC2 | < 1000 |

### RDS Metrics

| Métrica | Threshold |
|---------|-----------|
| CPUUtilization | < 80% |
| DatabaseConnections | < 80% of max |
| FreeableMemory | > 500 MB |
| ReadLatency | < 10 ms |
| WriteLatency | < 10 ms |
| FreeStorageSpace | > 20% |

## 🚨 Alarmas CloudWatch

### Configuración via Terraform

Ya incluidas en `terraform/cloudwatch.tf` si `enable_cloudwatch_alarms = true`

### Crear Alarma Manual

```bash
# High CPU Alarm
aws cloudwatch put-metric-alarm \
    --alarm-name moodle-high-cpu \
    --alarm-description "CPU utilization exceeds 80%" \
    --namespace AWS/EC2 \
    --metric-name CPUUtilization \
    --dimensions Name=InstanceId,Value=i-xxxxx \
    --statistic Average \
    --period 300 \
    --threshold 80 \
    --comparison-operator GreaterThanThreshold \
    --evaluation-periods 2 \
    --alarm-actions arn:aws:sns:us-east-1:ACCOUNT:alerts

# Low Memory Alarm
aws cloudwatch put-metric-alarm \
    --alarm-name moodle-low-memory \
    --alarm-description "Available memory below 500MB" \
    --namespace Moodle/EC2 \
    --metric-name MEMORY_AVAILABLE \
    --dimensions Name=InstanceId,Value=i-xxxxx \
    --statistic Average \
    --period 300 \
    --threshold 500 \
    --comparison-operator LessThanThreshold \
    --evaluation-periods 2 \
    --alarm-actions arn:aws:sns:us-east-1:ACCOUNT:alerts

# High Disk Usage
aws cloudwatch put-metric-alarm \
    --alarm-name moodle-high-disk \
    --alarm-description "Disk usage exceeds 80%" \
    --namespace Moodle/EC2 \
    --metric-name DISK_USED \
    --dimensions Name=InstanceId,Value=i-xxxxx,Name=path,Value=/ \
    --statistic Average \
    --period 300 \
    --threshold 80 \
    --comparison-operator GreaterThanThreshold \
    --evaluation-periods 1 \
    --alarm-actions arn:aws:sns:us-east-1:ACCOUNT:alerts

# RDS High CPU
aws cloudwatch put-metric-alarm \
    --alarm-name rds-high-cpu \
    --alarm-description "RDS CPU exceeds 80%" \
    --namespace AWS/RDS \
    --metric-name CPUUtilization \
    --dimensions Name=DBInstanceIdentifier,Value=your-db \
    --statistic Average \
    --period 300 \
    --threshold 80 \
    --comparison-operator GreaterThanThreshold \
    --evaluation-periods 2 \
    --alarm-actions arn:aws:sns:us-east-1:ACCOUNT:alerts
```

### SNS Topic para Notificaciones

```bash
# Crear topic
aws sns create-topic --name moodle-alerts

# Subscribe email
aws sns subscribe \
    --topic-arn arn:aws:sns:us-east-1:ACCOUNT:moodle-alerts \
    --protocol email \
    --notification-endpoint admin@yourdomain.com

# Confirmar subscription en email
```

## 📋 Logs

### Log Groups Configurados

| Log Group | Source | Retention |
|-----------|--------|-----------|
| /aws/ec2/moodle/apache-error | Apache errors | 7 días |
| /aws/ec2/moodle/apache-access | Apache access | 7 días |
| /aws/ec2/moodle/php-fpm | PHP-FPM errors | 7 días |
| /aws/ec2/moodle/memory | Memory monitoring | 7 días |
| /aws/rds/instance/your-db/error | RDS errors | 7 días |

### CloudWatch Logs Insights

Queries útiles:

```sql
# Top 10 errores PHP
fields @timestamp, @message
| filter @message like /error/
| stats count() by @message
| sort count desc
| limit 10

# Requests por hora (Apache access log)
fields @timestamp
| stats count() by bin(5m)

# Memory usage over time
fields @timestamp, @message
| parse @message /Mem:\s+(?<total>\S+)\s+(?<used>\S+)/
| display @timestamp, used

# Slow queries (si está habilitado en Moodle)
fields @timestamp, @message
| filter @message like /execution took/
| sort @timestamp desc
| limit 20
```

### Log Insights en AWS Console

```
CloudWatch → Logs → Insights → Select log group → Query
```

## 📊 Dashboard CloudWatch

### Crear Dashboard

```bash
aws cloudwatch put-dashboard \
    --dashboard-name Moodle-Production \
    --dashboard-body file://dashboard.json
```

**dashboard.json:**
```json
{
  "widgets": [
    {
      "type": "metric",
      "properties": {
        "metrics": [
          ["AWS/EC2", "CPUUtilization", {"stat": "Average"}],
          ["Moodle/EC2", "MEMORY_USED", {"stat": "Average"}]
        ],
        "period": 300,
        "stat": "Average",
        "region": "us-east-1",
        "title": "EC2 Resources"
      }
    },
    {
      "type": "metric",
      "properties": {
        "metrics": [
          ["AWS/RDS", "CPUUtilization", {"stat": "Average"}],
          [".", "DatabaseConnections", {"stat": "Average"}]
        ],
        "period": 300,
        "stat": "Average",
        "region": "us-east-1",
        "title": "RDS Performance"
      }
    },
    {
      "type": "log",
      "properties": {
        "query": "SOURCE '/aws/ec2/moodle/apache-error'\n| fields @timestamp, @message\n| sort @timestamp desc\n| limit 20",
        "region": "us-east-1",
        "title": "Recent Errors"
      }
    }
  ]
}
```

### Dashboard via Console

1. CloudWatch → Dashboards → Create dashboard
2. Add widgets:
   - Line chart: CPU, Memory
   - Number: Current connections
   - Log widget: Recent errors
   - Alarm status: All alarms

## 🔍 Health Checks

### Script Health Check

```bash
# Ya creado por script de monitoreo
sudo /usr/local/bin/moodle-health-check.sh
```

Verifica:
- ✅ Apache running
- ✅ PHP-FPM running
- ✅ Database connectivity
- ✅ Disk space
- ✅ Memory usage
- ✅ SSL certificate validity
- ✅ Moodle cron status

### Quick Status

```bash
# Script rápido
sudo /usr/local/bin/moodle-status.sh

# Output:
# 🚀 Moodle System Status
# Apache:         ✓ Running
# PHP-FPM:        ✓ Running
# Memory Used:    45%
# Disk Used:      32%
# Load Average:   0.50, 0.45, 0.40
```

### Automated Health Checks

```bash
# Cron cada 5 minutos
echo "*/5 * * * * root /usr/local/bin/moodle-health-check.sh > /tmp/health-status.txt 2>&1" | sudo tee /etc/cron.d/health-check

# Publicar a CloudWatch
sudo vim /usr/local/bin/publish-health-metrics.sh
```

```bash
#!/bin/bash
# Get metrics
APACHE_UP=$(systemctl is-active httpd && echo 1 || echo 0)
PHPFPM_UP=$(systemctl is-active php-fpm && echo 1 || echo 0)
MEM_AVAIL=$(free -m | awk '/^Mem:/ {print $7}')
DISK_USED=$(df -h / | awk 'NR==2 {print $5}' | sed 's/%//')

# Publish to CloudWatch
aws cloudwatch put-metric-data \
    --namespace Moodle/Health \
    --metric-name ApacheStatus \
    --value $APACHE_UP \
    --unit None

aws cloudwatch put-metric-data \
    --namespace Moodle/Health \
    --metric-name PHPFPMStatus \
    --value $PHPFPM_UP \
    --unit None

aws cloudwatch put-metric-data \
    --namespace Moodle/Health \
    --metric-name MemoryAvailable \
    --value $MEM_AVAIL \
    --unit Megabytes

aws cloudwatch put-metric-data \
    --namespace Moodle/Health \
    --metric-name DiskUsedPercent \
    --value $DISK_USED \
    --unit Percent
```

## 📱 Notificaciones

### Email (SNS)

Ya configurado si `alarm_email` está en terraform.tfvars

### SMS

```bash
aws sns subscribe \
    --topic-arn arn:aws:sns:us-east-1:ACCOUNT:moodle-alerts \
    --protocol sms \
    --notification-endpoint +1234567890
```

### Slack

```bash
# Lambda function para Slack webhook
# Ver: https://docs.aws.amazon.com/chatbot/latest/adminguide/slack-setup.html

# O usar AWS Chatbot
aws chatbot create-slack-channel-configuration \
    --configuration-name moodle-alerts \
    --slack-team-id T123456 \
    --slack-channel-id C123456 \
    --iam-role-arn arn:aws:iam::ACCOUNT:role/ChatbotRole \
    --sns-topic-arns arn:aws:sns:us-east-1:ACCOUNT:moodle-alerts
```

### PagerDuty

```bash
# Integration via SNS → PagerDuty API
# Configure en PagerDuty console y conectar con SNS
```

## 📈 Application Performance Monitoring

### Moodle Performance Logs

```php
// En config.php
$CFG->perfdebug = 0;  // Desactivado en producción
$CFG->perfinfo = 0;   // Desactivado en producción

// Para debugging temporal
$CFG->perfdebug = 15;  // All performance info
$CFG->perfinfo = 1;    // Show on page
```

### Custom Application Metrics

```bash
# Script para métricas de aplicación
sudo vim /usr/local/bin/moodle-app-metrics.sh
```

```bash
#!/bin/bash
# Moodle application metrics

# Active users (last 5 minutes)
ACTIVE_USERS=$(mysql -h $DB_HOST -u $DB_USER -p"$DB_PASSWORD" $DB_NAME -N -e "
    SELECT COUNT(DISTINCT userid)
    FROM mdl_logstore_standard_log
    WHERE timecreated > UNIX_TIMESTAMP() - 300
")

# Total courses
TOTAL_COURSES=$(mysql -h $DB_HOST -u $DB_USER -p"$DB_PASSWORD" $DB_NAME -N -e "
    SELECT COUNT(*) FROM mdl_course WHERE visible=1
")

# Active sessions
ACTIVE_SESSIONS=$(mysql -h $DB_HOST -u $DB_USER -p"$DB_PASSWORD" $DB_NAME -N -e "
    SELECT COUNT(*) FROM mdl_sessions
    WHERE timemodified > UNIX_TIMESTAMP() - 300
")

# Publish metrics
aws cloudwatch put-metric-data \
    --namespace Moodle/Application \
    --metric-name ActiveUsers \
    --value $ACTIVE_USERS \
    --unit Count

aws cloudwatch put-metric-data \
    --namespace Moodle/Application \
    --metric-name TotalCourses \
    --value $TOTAL_COURSES \
    --unit Count

aws cloudwatch put-metric-data \
    --namespace Moodle/Application \
    --metric-name ActiveSessions \
    --value $ACTIVE_SESSIONS \
    --unit Count
```

## 💰 Costos de Monitoreo

### CloudWatch Pricing

| Feature | Precio | Incluido |
|---------|--------|----------|
| Metrics | $0.30/metric/mes | Primeras 10 métricas gratis |
| Logs ingestion | $0.50/GB | - |
| Logs storage | $0.03/GB/mes | - |
| Dashboards | $3/dashboard/mes | Primeros 3 gratis |
| Alarms | $0.10/alarm/mes | Primeras 10 gratis |
| Log Insights queries | $0.005/GB scanned | - |

**Estimación mensual:**
```
Custom metrics (20):      $6.00  ($0.30 × 20)
Logs ingestion (5 GB):    $2.50  ($0.50 × 5)
Logs storage (5 GB):      $0.15  ($0.03 × 5)
Dashboards (1):           Free   (< 3)
Alarms (5):               Free   (< 10)
────────────────────────────────────
TOTAL:                    ~$9/mes
```

## 📊 Reporting

### Weekly Report Script

```bash
sudo vim /usr/local/bin/weekly-report.sh
```

```bash
#!/bin/bash
# Weekly Moodle report

WEEK_AGO=$(date -d '7 days ago' +%Y-%m-%d)
TODAY=$(date +%Y-%m-%d)

echo "Moodle Weekly Report: $WEEK_AGO to $TODAY"
echo "========================================"

# Average CPU
aws cloudwatch get-metric-statistics \
    --namespace AWS/EC2 \
    --metric-name CPUUtilization \
    --start-time ${WEEK_AGO}T00:00:00Z \
    --end-time ${TODAY}T00:00:00Z \
    --period 86400 \
    --statistics Average \
    --dimensions Name=InstanceId,Value=i-xxxxx

# Total errors
ERRORS=$(aws logs filter-log-events \
    --log-group-name /aws/ec2/moodle/apache-error \
    --start-time $(date -d '7 days ago' +%s)000 \
    --query 'events[*].message' \
    --output text | wc -l)

echo "Total errors: $ERRORS"

# Send via email
# mail -s "Moodle Weekly Report" admin@domain.com < report.txt
```

## ✅ Best Practices

### Checklist

- [x] CloudWatch Agent instalado y running
- [x] Logs centralizados en CloudWatch
- [x] Alarmas configuradas para métricas críticas
- [x] SNS notifications configuradas
- [x] Dashboard creado
- [x] Health checks automatizados
- [x] Log retention apropiada (7-30 días)
- [x] Costos de monitoreo bajo control
- [x] Runbook de respuesta a incidentes
- [x] Reports automáticos configurados

### Recomendaciones

1. **Revisar alarmas semanalmente**
   - Ajustar thresholds si false positives
   - Agregar nuevas alarmas si necesario

2. **Analizar logs regularmente**
   - Buscar patterns de errores
   - Identificar optimizaciones

3. **Monitorear costos**
   ```bash
   aws cloudwatch get-metric-statistics \
       --namespace AWS/Billing \
       --metric-name EstimatedCharges
   ```

4. **Mantener dashboards actualizados**
   - Agregar métricas relevantes
   - Remover métricas innecesarias

5. **Test alarmas**
   ```bash
   # Trigger test alarm
   aws cloudwatch set-alarm-state \
       --alarm-name moodle-high-cpu \
       --state-value ALARM \
       --state-reason "Testing alarm"
   ```

## 📚 Referencias

- [CloudWatch Documentation](https://docs.aws.amazon.com/cloudwatch/)
- [CloudWatch Agent](https://docs.aws.amazon.com/AmazonCloudWatch/latest/monitoring/Install-CloudWatch-Agent.html)
- [CloudWatch Logs Insights](https://docs.aws.amazon.com/AmazonCloudWatch/latest/logs/AnalyzingLogData.html)
- [Moodle Performance](https://docs.moodle.org/en/Performance)

---

**Fecha:** 2026-02-02
**Versión:** 1.0.0
