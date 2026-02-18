# Variables for Moodle 5.1 AWS Infrastructure
# Configure these values before running terraform apply
#
# NOTE: Moodle 5.1 requirements:
# - PHP 8.2+ (8.2.x, 8.3.x, 8.4.x supported)
# - Apache DocumentRoot must point to /var/www/html/moodle/public (not /var/www/html/moodle)
# - MariaDB 10.11+, MySQL 8.4+, or PostgreSQL 15+

#############################################
# General Configuration
#############################################

variable "project_name" {
  description = "Project name for resource naming"
  type        = string
  default     = "moodle"
}

variable "environment" {
  description = "Environment name (dev, staging, prod)"
  type        = string
  default     = "prod"
}

variable "aws_region" {
  description = "AWS region for resources"
  type        = string
  default     = "us-east-1"
}

variable "aws_profile" {
  description = "AWS CLI profile to use"
  type        = string
  default     = "default"
}

variable "tags" {
  description = "Common tags to apply to all resources"
  type        = map(string)
  default = {
    Project     = "Moodle 5.1"
    ManagedBy   = "Terraform"
    Environment = "Production"
  }
}

#############################################
# Network Configuration
#############################################

variable "vpc_id" {
  description = "VPC ID (leave empty to use default VPC)"
  type        = string
  default     = ""
}

variable "subnet_id" {
  description = "Subnet ID for EC2 instance (leave empty to use default)"
  type        = string
  default     = ""
}

variable "allowed_ssh_cidrs" {
  description = "List of CIDR blocks allowed to SSH"
  type        = list(string)
  default     = ["0.0.0.0/0"] # CHANGE THIS to your IP!
}

#############################################
# EC2 Instance Configuration
#############################################

variable "instance_type" {
  description = "EC2 instance type (t4g.medium recommended for 100-300 users)"
  type        = string
  default     = "t4g.medium"

  validation {
    condition     = can(regex("^t4g\\.", var.instance_type))
    error_message = "Use ARM-based t4g instances for cost optimization."
  }
}

variable "ami_id" {
  description = "AMI ID for Amazon Linux 2023 ARM64 (leave empty for auto-lookup)"
  type        = string
  default     = ""
}

variable "key_pair_name" {
  description = "Name of existing EC2 key pair for SSH access"
  type        = string
}

variable "root_volume_size" {
  description = "Size of root EBS volume in GB"
  type        = number
  default     = 15
}

variable "data_volume_size" {
  description = "Size of data EBS volume for Moodle data (/moodledata) in GB"
  type        = number
  default     = 25
}

variable "ebs_volume_type" {
  description = "EBS volume type (gp3 recommended)"
  type        = string
  default     = "gp3"
}

variable "ebs_iops" {
  description = "IOPS for gp3 volumes (3000-16000)"
  type        = number
  default     = 3000
}

variable "ebs_throughput" {
  description = "Throughput for gp3 volumes in MB/s (125-1000)"
  type        = number
  default     = 125
}

variable "enable_detailed_monitoring" {
  description = "Enable detailed CloudWatch monitoring (additional cost)"
  type        = bool
  default     = false
}

#############################################
# RDS Configuration
#############################################

variable "create_rds" {
  description = "Whether to create RDS instance"
  type        = bool
  default     = true
}

variable "db_instance_class" {
  description = "RDS instance class (db.t4g.micro for < 500 users)"
  type        = string
  default     = "db.t4g.micro"
}

variable "db_engine_version" {
  description = "MariaDB version"
  type        = string
  default     = "10.11.15"
}

variable "db_allocated_storage" {
  description = "Initial storage size in GB"
  type        = number
  default     = 20
}

variable "db_max_allocated_storage" {
  description = "Maximum storage size for autoscaling in GB"
  type        = number
  default     = 100
}

variable "db_name" {
  description = "Database name for Moodle"
  type        = string
  default     = "moodle"
}

variable "db_username" {
  description = "Master username for database"
  type        = string
  default     = "moodleadmin"
  sensitive   = true
}

variable "db_password" {
  description = "Master password for database (min 8 chars)"
  type        = string
  sensitive   = true

  validation {
    condition     = length(var.db_password) >= 8
    error_message = "Database password must be at least 8 characters long."
  }
}

variable "db_backup_retention_period" {
  description = "Days to retain automated backups (0-35)"
  type        = number
  default     = 7
}

variable "db_backup_window" {
  description = "Daily backup window (UTC)"
  type        = string
  default     = "03:00-04:00"
}

variable "db_maintenance_window" {
  description = "Weekly maintenance window (UTC)"
  type        = string
  default     = "sun:04:00-sun:05:00"
}

variable "db_multi_az" {
  description = "Enable Multi-AZ deployment for high availability"
  type        = bool
  default     = false
}

variable "db_publicly_accessible" {
  description = "Make RDS publicly accessible (NOT recommended)"
  type        = bool
  default     = false
}

#############################################
# Domain & SSL Configuration
#############################################

variable "domain_name" {
  description = "Domain name for Moodle (e.g., moodle.example.com)"
  type        = string
}

variable "admin_email" {
  description = "Admin email for Let's Encrypt notifications"
  type        = string
}

variable "create_route53_record" {
  description = "Create Route 53 A record for domain"
  type        = bool
  default     = false
}

variable "route53_zone_id" {
  description = "Route 53 hosted zone ID (if create_route53_record is true)"
  type        = string
  default     = ""
}

#############################################
# CloudFront CDN Configuration
#############################################

variable "enable_cloudfront" {
  description = "Enable CloudFront CDN for static assets"
  type        = bool
  default     = false
}

variable "cloudfront_price_class" {
  description = "CloudFront price class (PriceClass_100, PriceClass_200, PriceClass_All)"
  type        = string
  default     = "PriceClass_100" # US, Canada, Europe
}

#############################################
# Monitoring & Alerting
#############################################

variable "enable_cloudwatch_alarms" {
  description = "Create CloudWatch alarms for monitoring"
  type        = bool
  default     = true
}

variable "alarm_email" {
  description = "Email address for alarm notifications"
  type        = string
  default     = ""
}

variable "cpu_alarm_threshold" {
  description = "CPU utilization threshold for alarm (%)"
  type        = number
  default     = 80
}

variable "memory_alarm_threshold" {
  description = "Available memory threshold for alarm (MB)"
  type        = number
  default     = 500
}

variable "disk_alarm_threshold" {
  description = "Disk usage threshold for alarm (%)"
  type        = number
  default     = 80
}

#############################################
# Backup Configuration
#############################################

variable "enable_ebs_snapshots" {
  description = "Enable automated EBS snapshots"
  type        = bool
  default     = true
}

variable "snapshot_retention_days" {
  description = "Days to retain EBS snapshots"
  type        = number
  default     = 7
}

variable "snapshot_schedule" {
  description = "Cron expression for snapshot schedule (UTC)"
  type        = string
  default     = "cron(0 2 * * ? *)" # Daily at 2 AM UTC
}

#############################################
# Optimization Settings
#############################################

variable "php_fpm_max_children" {
  description = "PHP-FPM max_children (0 for auto-calculate based on RAM)"
  type        = number
  default     = 0
}

variable "enable_swap" {
  description = "Configure SWAP space"
  type        = bool
  default     = true
}

variable "swap_size_gb" {
  description = "SWAP size in GB (0 for auto-calculate based on RAM)"
  type        = number
  default     = 0
}

#############################################
# Moodle Configuration
#############################################

variable "moodle_version" {
  description = "Moodle version to install (branch name)"
  type        = string
  default     = "MOODLE_51_STABLE"
}

variable "moodle_admin_user" {
  description = "Moodle admin username"
  type        = string
  default     = "admin"
}

variable "moodle_admin_password" {
  description = "Moodle admin password (min 8 chars, use strong password)"
  type        = string
  sensitive   = true

  validation {
    condition     = length(var.moodle_admin_password) >= 8
    error_message = "Moodle admin password must be at least 8 characters long."
  }
}

variable "moodle_site_name" {
  description = "Moodle site name"
  type        = string
  default     = "My Moodle Site"
}

variable "moodle_site_summary" {
  description = "Moodle site summary/description"
  type        = string
  default     = "Learning Management System"
}
