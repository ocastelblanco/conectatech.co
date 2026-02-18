---
name: setup-monitoring
description: Configures comprehensive monitoring system using AWS CloudWatch for metrics, logs, and alarms to ensure Moodle availability and performance. Use when production deployment needs visibility, alerting, log aggregation, or dashboards. Triggers: "setup monitoring", "configure CloudWatch", "create alarms", "monitor Moodle server", "CloudWatch metrics", "log aggregation", "system health monitoring".
---

# Setup Moodle Monitoring

## Instructions

### Context

This skill implements production-grade monitoring with:
- CloudWatch Agent for custom metrics
- Log aggregation (Apache, PHP-FPM, Moodle, system)
- Proactive alarms with SNS notifications
- Custom dashboards for visualization
- Memory monitoring (critical for t4g instances)
- Moodle-specific health checks

### Required prerequisites

- EC2 instance with IAM role for CloudWatch
- SNS topic for alarm notifications
- CloudWatch Logs access configured
- Sufficient IAM permissions
- Email configured for alarm notifications

### Available automation

Script location: `scripts/07-setup-monitoring.sh`

The script fully automates monitoring configuration.

### Monitoring components

**1. System metrics:**
- CPU utilization (per core and aggregate)
- Memory usage (used, available, percentage)
- Disk usage and I/O
- Network traffic
- Swap usage

**2. Application metrics:**
- Apache connections and requests/second
- PHP-FPM process count and status
- Database connections
- Moodle cron execution status

**3. Log monitoring:**
- Apache access and error logs
- PHP-FPM errors
- Moodle application logs
- System authentication logs
- CloudWatch Agent logs

**4. Alarms:**
- High CPU (>80% for 5 minutes)
- High memory (>85% for 5 minutes)
- Low disk space (<15%)
- Service failures (Apache, PHP-FPM down)
- Database connection failures
- SSL certificate expiration (30 days)

### Alarms summary

| Alarm | Threshold | Action |
|-------|-----------|--------|
| High CPU | >80% for 5 min | Email alert |
| High Memory | >85% for 5 min | Email alert |
| Low Disk | <15% free | Email alert |
| Service Down | 2 failures | Email alert |
| SSL Expiring | <30 days | Email alert |
| Cron Not Running | >10 minutes | Email alert |

### CloudWatch costs

**Typical monthly costs for single server:**
- Custom metrics: 10 metrics x $0.30 = $3.00
- Log ingestion: 5 GB x $0.50 = $2.50
- Log storage: 10 GB x $0.03 = $0.30
- Alarms: 10 alarms x $0.10 = $1.00
- Dashboard: 1 x $3.00 = $3.00
- **Total:** ~$10/month

### Verification steps

1. **Check CloudWatch Agent:**
   ```bash
   sudo systemctl status amazon-cloudwatch-agent

   # View logs
   sudo tail -f /opt/aws/amazon-cloudwatch-agent/logs/amazon-cloudwatch-agent.log
   ```

2. **Verify metrics in CloudWatch:**
   ```bash
   # List custom metrics
   aws cloudwatch list-metrics --namespace Moodle/Production

   # Get latest CPU metric
   aws cloudwatch get-metric-statistics \
       --namespace AWS/EC2 \
       --metric-name CPUUtilization \
       --dimensions Name=InstanceId,Value=i-xxxxx \
       --start-time $(date -u -d '10 minutes ago' +%Y-%m-%dT%H:%M:%S) \
       --end-time $(date -u +%Y-%m-%dT%H:%M:%S) \
       --period 300 \
       --statistics Average
   ```

3. **Check log groups:**
   ```bash
   # List log groups
   aws logs describe-log-groups --log-group-name-prefix /moodle/

   # View recent logs
   aws logs tail /moodle/apache/error --follow
   ```

4. **Test alarms:**
   ```bash
   # Describe alarms
   aws cloudwatch describe-alarms --alarm-name-prefix moodle-

   # Test SNS
   aws sns publish \
       --topic-arn arn:aws:sns:us-east-1:ACCOUNT:moodle-alerts \
       --message "Test alert"
   ```

### Troubleshooting monitoring

**Agent not sending metrics:**
```bash
# Check agent status
sudo /opt/aws/amazon-cloudwatch-agent/bin/amazon-cloudwatch-agent-ctl \
    -a query -m ec2 -c default -s

# Check IAM permissions
aws iam get-role --role-name MoodleInstanceRole

# Test metric manually
aws cloudwatch put-metric-data \
    --namespace Test \
    --metric-name TestMetric \
    --value 1.0
```

**Logs not appearing:**
```bash
# Check log file permissions
ls -la /var/log/httpd/moodle-*.log

# Verify agent has read access
sudo -u cwagent cat /var/log/httpd/moodle-error.log

# Check agent logs
sudo tail /opt/aws/amazon-cloudwatch-agent/logs/amazon-cloudwatch-agent.log
```

**Alarms not triggering:**
```bash
# Check alarm configuration
aws cloudwatch describe-alarms --alarm-names moodle-high-cpu

# Check SNS subscription
aws sns list-subscriptions-by-topic \
    --topic-arn arn:aws:sns:us-east-1:ACCOUNT:moodle-alerts
```

### Expected output

```
CloudWatch Agent installed and configured
Custom metrics collection enabled
Log aggregation configured (5 log sources)
SNS topic created: moodle-alerts
Email subscription confirmed
6 alarms configured:
  - High CPU (>80%)
  - High Memory (>85%)
  - Low Disk Space (<15%)
  - Service Down
  - SSL Certificate Expiring (<30 days)
  - Moodle Cron Not Running (>10 minutes)
Health check script configured (runs every 5 minutes)
CloudWatch dashboard created: Moodle-Production

Monitoring active for:
  - System: CPU, Memory, Disk, Network, Swap
  - Services: Apache, PHP-FPM, Database
  - Application: Moodle cron, Active users, Response time
  - Logs: Apache, PHP-FPM, Moodle, System

View dashboard:
  https://console.aws.amazon.com/cloudwatch/dashboards/Moodle-Production

Estimated cost: ~$10/month
```

## Example usage

**User:** "Set up monitoring for my Moodle server with email alerts for critical issues."

**Assistant:** I'll configure comprehensive CloudWatch monitoring with email alerts.

[Installs CloudWatch Agent]
[Configures custom metrics for CPU, memory, disk]
[Sets up log aggregation for Apache, PHP-FPM, Moodle]
[Creates SNS topic]
[Subscribes user email]
[Creates 6 critical alarms]
[Sets up health check script]
[Creates CloudWatch dashboard]
[Tests monitoring]

Monitoring configured successfully!

**Active monitoring:**
- System metrics: CPU, Memory, Disk, Network (collected every 60s)
- Service health: Apache, PHP-FPM, Database (checked every 5 minutes)
- Logs: Real-time aggregation from 5 sources

**Alarms configured:**
1. High CPU (>80% for 5 min) -> Email alert
2. High Memory (>85% for 5 min) -> Email alert
3. Low Disk Space (<15%) -> Email alert
4. Service Down (2 consecutive failures) -> Email alert
5. SSL Expiring (<30 days) -> Email alert
6. Cron Not Running (>10 minutes) -> Email alert

**Email alerts sent to:** admin@yourdomain.com

**Dashboard:** https://console.aws.amazon.com/cloudwatch/dashboards/Moodle-Production

Check your email to confirm the SNS subscription, then you'll receive alerts for any issues!
