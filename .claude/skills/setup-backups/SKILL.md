---
name: setup-backups
description: Configures automated backup system for Moodle database, moodledata files, and configuration following the 3-2-1 backup strategy with S3 storage. Use when production deployment is ready or when the user needs automated backups, retention policies, or S3 backup storage. Triggers: "setup backups", "configure backup schedule", "backup to S3", "3-2-1 backup strategy", "backup retention", "database backup", "moodledata backup".
---

# Setup Moodle Backups

## Instructions

### Context

This skill implements a production-ready backup system with:
- Daily automated database backups
- Weekly full moodledata backups
- Configuration file backups
- Local and S3 storage (3-2-1 strategy)
- Automatic retention management
- Backup verification and logging

### 3-2-1 Backup Strategy

**Rule:** 3 copies, 2 different media types, 1 offsite location

**Implementation:**
1. **Primary copy**: Live data on EBS volume
2. **Local backup**: Compressed backups on EBS
3. **Offsite backup**: S3 for disaster recovery

### Required prerequisites

- Moodle installed and running
- S3 bucket created for backups
- IAM role with S3 write permissions
- Sufficient disk space (20-30GB for local backups)
- Configuration file at `/root/moodle-config.env`

### Available automation

Script location: `scripts/06-setup-backups.sh`

The script fully automates backup configuration.

### Backup components

**1. Database backups:**
- Frequency: Daily at 2:00 AM
- Method: mysqldump with compression
- Retention: 7 daily, 4 weekly, 12 monthly
- Storage: Local + S3

**2. Moodledata backups:**
- Frequency: Weekly (Sunday 3:00 AM)
- Method: tar with gzip compression
- Retention: 4 weekly, 6 monthly
- Storage: Local + S3

**3. Configuration backups:**
- Frequency: Daily with database backup
- Files: config.php, Apache configs, PHP-FPM settings
- Retention: 30 days
- Storage: Local + S3

### Backup schedule summary

| Component | Schedule | Local Retention | S3 Retention |
|-----------|----------|-----------------|--------------|
| Database | Daily 2:00 AM | 7 days | 30 days |
| Config | Daily 2:30 AM | 30 days | 30 days |
| Moodledata | Sunday 3:00 AM | 4 weeks | 12 weeks |

### Storage requirements

**Local storage (EBS):**
- Database: 7 days x 200 MB = ~1.4 GB
- Moodledata: 4 weeks x 15 GB = ~60 GB
- Config: 30 days x 1 MB = ~30 MB
- **Total:** ~65-70 GB

**S3 storage:**
- Database: 30 days x 200 MB = ~6 GB
- Moodledata: 12 weeks x 15 GB = ~180 GB
- Config: 30 days x 1 MB = ~30 MB
- **Total:** ~190 GB
- **Monthly cost:** ~$4-5 USD (Standard tier)

### Monitoring backups

**Check backup status:**
```bash
# View backup log
sudo tail -100 /var/log/moodle-backups.log

# Check last backup times
stat /backups/database/moodle-db-*.sql.gz | tail -1
stat /backups/moodledata/moodledata-*.tar.gz | tail -1

# Check backup sizes
du -sh /backups/*

# Check S3 contents
aws s3 ls s3://your-bucket/ --recursive --human-readable
```

### Restore procedures

**Restore database:**
```bash
# List available backups
ls -lh /backups/database/

# Or from S3
aws s3 ls s3://your-bucket/database/

# Restore from local
gunzip < /backups/database/moodle-db-YYYYMMDD-HHMMSS.sql.gz | \
    mysql -h $DB_HOST -u $DB_USER -p"$DB_PASSWORD" $DB_NAME

# Or restore from S3
aws s3 cp s3://your-bucket/database/moodle-db-YYYYMMDD-HHMMSS.sql.gz - | \
    gunzip | \
    mysql -h $DB_HOST -u $DB_USER -p"$DB_PASSWORD" $DB_NAME
```

**Restore moodledata:**
```bash
# Stop services first
sudo systemctl stop httpd php-fpm

# Backup current data
sudo mv /moodledata /moodledata.old

# Restore from local
sudo tar -xzf /backups/moodledata/moodledata-YYYYMMDD-HHMMSS.tar.gz -C /

# Or restore from S3
aws s3 cp s3://your-bucket/moodledata/moodledata-YYYYMMDD-HHMMSS.tar.gz - | \
    sudo tar -xzf - -C /

# Fix permissions
sudo chown -R apache:apache /moodledata
sudo chmod -R 770 /moodledata

# Restart services
sudo systemctl start php-fpm httpd
```

### Common issues

**Backup fails with "disk full":**
```bash
# Check disk space
df -h /backups

# Clean old backups manually
find /backups -name "*.gz" -mtime +7 -delete

# Consider increasing EBS volume size
```

**S3 upload fails:**
```bash
# Check IAM permissions
aws s3 ls s3://your-bucket/

# Check network connectivity
curl -I https://s3.amazonaws.com

# Verify IAM role attached to EC2
aws sts get-caller-identity
```

### Security considerations

1. **Backup encryption:** Enable S3 bucket encryption (AES-256 or KMS)
2. **Access control:** Restrict IAM permissions to specific S3 bucket
3. **File permissions:** Backups contain sensitive data (640/750 permissions)
4. **Secure credentials:** Never store DB password in backup scripts
5. **S3 versioning:** Enable versioning for additional protection

### Expected output

```
Backup directories created
Database backup script configured
Moodledata backup script configured
Config backup script configured
Cron jobs scheduled
Backup monitoring script created
S3 bucket configured

Backup schedule:
  - Database: Daily at 2:00 AM (7 days local, 30 days S3)
  - Config: Daily at 2:30 AM (30 days)
  - Moodledata: Sunday at 3:00 AM (4 weeks local, 12 weeks S3)

Storage requirements:
  - Local: ~70 GB
  - S3: ~190 GB (~$5/month)

Test backups:
  sudo /usr/local/bin/backup-moodle-db.sh
  sudo tail /var/log/moodle-backups.log

Monitor:
  sudo backup-status.sh
```

## Example usage

**User:** "Set up automated backups for my Moodle site. I want daily database backups and weekly file backups stored in S3."

**Assistant:** I'll configure automated backups with S3 storage following the 3-2-1 backup strategy.

[Loads configuration from /root/moodle-config.env]
[Creates backup directories]
[Creates database backup script with S3 upload]
[Creates moodledata backup script]
[Creates config backup script]
[Configures cron jobs]
[Tests database backup]

Backup system configured successfully!

**Schedule:**
- Database: Daily at 2:00 AM -> kept 7 days local, 30 days S3
- Config: Daily at 2:30 AM -> kept 30 days
- Moodledata: Sundays at 3:00 AM -> kept 4 weeks local, 12 weeks S3

**Storage:**
- Local: ~70 GB (managed automatically)
- S3: ~190 GB (~$5/month)

**First backup created:**
- Database: moodle-db-20260202-140532.sql.gz (156 MB)
- Uploaded to: s3://acg-moodle-backups/database/

**Monitor backups:**
```bash
sudo tail -f /var/log/moodle-backups.log
```

Your data is now protected with the 3-2-1 strategy: 3 copies, 2 media types, 1 offsite.
