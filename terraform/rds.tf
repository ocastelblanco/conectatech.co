# RDS MariaDB Configuration for Moodle 5.1
# NOTE: Moodle 5.1 database requirements:
# - MariaDB 10.11+ (current: 10.11.15 ✓)
# - MySQL 8.4+
# - PostgreSQL 15+

#############################################
# RDS Security Group
#############################################

resource "aws_security_group" "rds" {
  count = var.create_rds ? 1 : 0

  name_prefix = "${local.name_prefix}-rds-sg-"
  description = "Security group for Moodle RDS instance"
  vpc_id      = local.vpc_id

  # MariaDB access from EC2 security group only
  ingress {
    description     = "MariaDB from Moodle EC2"
    from_port       = 3306
    to_port         = 3306
    protocol        = "tcp"
    security_groups = [aws_security_group.moodle.id]
  }

  # No outbound rules needed for RDS
  egress {
    description = "Allow all outbound (required for replication if Multi-AZ)"
    from_port   = 0
    to_port     = 0
    protocol    = "-1"
    cidr_blocks = ["0.0.0.0/0"]
  }

  tags = merge(
    local.common_tags,
    {
      Name = "${local.name_prefix}-rds-sg"
    }
  )

  lifecycle {
    create_before_destroy = true
  }
}

#############################################
# DB Subnet Group
#############################################

# Get all subnets in the VPC for subnet group
data "aws_subnets" "vpc_subnets" {
  count = var.create_rds ? 1 : 0

  filter {
    name   = "vpc-id"
    values = [local.vpc_id]
  }
}

resource "aws_db_subnet_group" "moodle" {
  count = var.create_rds ? 1 : 0

  name_prefix = "${local.name_prefix}-db-subnet-"
  description = "Subnet group for Moodle RDS"
  subnet_ids  = data.aws_subnets.vpc_subnets[0].ids

  tags = merge(
    local.common_tags,
    {
      Name = "${local.name_prefix}-db-subnet-group"
    }
  )
}

#############################################
# DB Parameter Group
#############################################

resource "aws_db_parameter_group" "moodle" {
  count = var.create_rds ? 1 : 0

  name_prefix = "${local.name_prefix}-mariadb-"
  family      = "mariadb10.11"
  description = "Custom parameter group for Moodle MariaDB"

  # Moodle-specific optimizations
  # All parameters use pending-reboot to avoid static parameter errors
  parameter {
    name         = "max_connections"
    value        = "200"
    apply_method = "pending-reboot"
  }

  parameter {
    name         = "innodb_buffer_pool_size"
    value        = "{DBInstanceClassMemory*3/4}"
    apply_method = "pending-reboot"
  }

  parameter {
    name         = "innodb_log_file_size"
    value        = "134217728" # 128MB
    apply_method = "pending-reboot"
  }

  parameter {
    name         = "character_set_server"
    value        = "utf8mb4"
    apply_method = "pending-reboot"
  }

  parameter {
    name         = "collation_server"
    value        = "utf8mb4_unicode_ci"
    apply_method = "pending-reboot"
  }

  parameter {
    name         = "max_allowed_packet"
    value        = "67108864" # 64MB for large content
    apply_method = "pending-reboot"
  }

  parameter {
    name         = "innodb_file_per_table"
    value        = "1"
    apply_method = "pending-reboot"
  }

  parameter {
    name         = "innodb_flush_log_at_trx_commit"
    value        = "2" # Better performance, acceptable durability
    apply_method = "pending-reboot"
  }

  parameter {
    name         = "query_cache_type"
    value        = "0" # Disabled (deprecated in MariaDB 10.x)
    apply_method = "pending-reboot"
  }

  tags = merge(
    local.common_tags,
    {
      Name = "${local.name_prefix}-db-params"
    }
  )

  lifecycle {
    create_before_destroy = true
  }
}

#############################################
# RDS Instance
#############################################

resource "aws_db_instance" "moodle" {
  count = var.create_rds ? 1 : 0

  # Basic Configuration
  identifier_prefix = "${local.name_prefix}-db-"
  engine            = "mariadb"
  engine_version    = var.db_engine_version
  instance_class    = var.db_instance_class

  # Database
  db_name  = var.db_name
  username = var.db_username
  password = var.db_password

  # Storage
  allocated_storage     = var.db_allocated_storage
  max_allocated_storage = var.db_max_allocated_storage
  storage_type          = "gp3"
  storage_encrypted     = true

  # Network
  db_subnet_group_name   = aws_db_subnet_group.moodle[0].name
  vpc_security_group_ids = [aws_security_group.rds[0].id]
  publicly_accessible    = var.db_publicly_accessible
  multi_az               = var.db_multi_az

  # Parameter and Option Groups
  parameter_group_name = aws_db_parameter_group.moodle[0].name

  # Backup
  backup_retention_period = var.db_backup_retention_period
  backup_window           = var.db_backup_window
  maintenance_window      = var.db_maintenance_window
  skip_final_snapshot     = false
  final_snapshot_identifier = "${local.name_prefix}-final-snapshot-${formatdate("YYYY-MM-DD-hhmm", timestamp())}"

  # Enable automated backups
  delete_automated_backups = false

  # Copy tags to snapshots
  copy_tags_to_snapshot = true

  # Monitoring
  enabled_cloudwatch_logs_exports = ["error", "general", "slowquery"]
  monitoring_interval             = var.enable_detailed_monitoring ? 60 : 0
  monitoring_role_arn            = var.enable_detailed_monitoring ? aws_iam_role.rds_monitoring[0].arn : null

  # Performance Insights
  performance_insights_enabled = false # Can enable for additional cost

  # Updates
  auto_minor_version_upgrade = true
  allow_major_version_upgrade = false

  # Deletion protection
  deletion_protection = true # Prevent accidental deletion

  tags = merge(
    local.common_tags,
    {
      Name = "${local.name_prefix}-rds"
    }
  )

  lifecycle {
    ignore_changes = [
      final_snapshot_identifier,
      password
    ]
  }
}

#############################################
# RDS Monitoring IAM Role (if detailed monitoring enabled)
#############################################

resource "aws_iam_role" "rds_monitoring" {
  count = var.create_rds && var.enable_detailed_monitoring ? 1 : 0

  name_prefix = "${local.name_prefix}-rds-monitoring-"

  assume_role_policy = jsonencode({
    Version = "2012-10-17"
    Statement = [
      {
        Action = "sts:AssumeRole"
        Effect = "Allow"
        Principal = {
          Service = "monitoring.rds.amazonaws.com"
        }
      }
    ]
  })

  tags = local.common_tags
}

resource "aws_iam_role_policy_attachment" "rds_monitoring" {
  count = var.create_rds && var.enable_detailed_monitoring ? 1 : 0

  role       = aws_iam_role.rds_monitoring[0].name
  policy_arn = "arn:aws:iam::aws:policy/service-role/AmazonRDSEnhancedMonitoringRole"
}

#############################################
# Outputs
#############################################

output "rds_endpoint" {
  description = "RDS instance endpoint"
  value       = var.create_rds ? aws_db_instance.moodle[0].endpoint : "N/A (RDS not created)"
}

output "rds_address" {
  description = "RDS instance address (hostname)"
  value       = var.create_rds ? aws_db_instance.moodle[0].address : "N/A (RDS not created)"
}

output "rds_port" {
  description = "RDS instance port"
  value       = var.create_rds ? aws_db_instance.moodle[0].port : "N/A (RDS not created)"
}

output "rds_database_name" {
  description = "Name of the default database"
  value       = var.create_rds ? aws_db_instance.moodle[0].db_name : "N/A (RDS not created)"
}

output "rds_username" {
  description = "Master username"
  value       = var.create_rds ? aws_db_instance.moodle[0].username : "N/A (RDS not created)"
  sensitive   = true
}

output "rds_arn" {
  description = "ARN of the RDS instance"
  value       = var.create_rds ? aws_db_instance.moodle[0].arn : "N/A (RDS not created)"
}

output "rds_connection_string" {
  description = "MySQL connection command"
  value       = var.create_rds ? "mysql -h ${aws_db_instance.moodle[0].address} -u ${var.db_username} -p ${var.db_name}" : "N/A (RDS not created)"
  sensitive   = true
}
