# EC2 Instance Configuration for Moodle 5.1

#############################################
# Security Group
#############################################

resource "aws_security_group" "moodle" {
  name_prefix = "${local.name_prefix}-sg-"
  description = "Security group for Moodle EC2 instance"
  vpc_id      = local.vpc_id

  # SSH access
  ingress {
    description = "SSH from allowed CIDRs"
    from_port   = 22
    to_port     = 22
    protocol    = "tcp"
    cidr_blocks = var.allowed_ssh_cidrs
  }

  # HTTP access
  ingress {
    description = "HTTP from anywhere"
    from_port   = 80
    to_port     = 80
    protocol    = "tcp"
    cidr_blocks = ["0.0.0.0/0"]
  }

  # HTTPS access
  ingress {
    description = "HTTPS from anywhere"
    from_port   = 443
    to_port     = 443
    protocol    = "tcp"
    cidr_blocks = ["0.0.0.0/0"]
  }

  # Allow all outbound
  egress {
    description = "Allow all outbound traffic"
    from_port   = 0
    to_port     = 0
    protocol    = "-1"
    cidr_blocks = ["0.0.0.0/0"]
  }

  tags = merge(
    local.common_tags,
    {
      Name = "${local.name_prefix}-sg"
    }
  )

  lifecycle {
    create_before_destroy = true
  }
}

#############################################
# IAM Role for EC2
#############################################

# IAM role for EC2 instance
resource "aws_iam_role" "moodle_ec2" {
  name_prefix = "${local.name_prefix}-ec2-role-"

  assume_role_policy = jsonencode({
    Version = "2012-10-17"
    Statement = [
      {
        Action = "sts:AssumeRole"
        Effect = "Allow"
        Principal = {
          Service = "ec2.amazonaws.com"
        }
      }
    ]
  })

  tags = local.common_tags
}

# Attach CloudWatch agent policy
resource "aws_iam_role_policy_attachment" "cloudwatch" {
  role       = aws_iam_role.moodle_ec2.name
  policy_arn = "arn:aws:iam::aws:policy/CloudWatchAgentServerPolicy"
}

# Attach SSM policy for Systems Manager
resource "aws_iam_role_policy_attachment" "ssm" {
  role       = aws_iam_role.moodle_ec2.name
  policy_arn = "arn:aws:iam::aws:policy/AmazonSSMManagedInstanceCore"
}

# Custom policy for snapshots and backups
resource "aws_iam_role_policy" "moodle_backups" {
  name_prefix = "${local.name_prefix}-backups-"
  role        = aws_iam_role.moodle_ec2.id

  policy = jsonencode({
    Version = "2012-10-17"
    Statement = [
      {
        Effect = "Allow"
        Action = [
          "ec2:CreateSnapshot",
          "ec2:CreateTags",
          "ec2:DescribeSnapshots",
          "ec2:DescribeVolumes"
        ]
        Resource = "*"
      }
    ]
  })
}

# Instance profile
resource "aws_iam_instance_profile" "moodle" {
  name_prefix = "${local.name_prefix}-profile-"
  role        = aws_iam_role.moodle_ec2.name

  tags = local.common_tags
}

#############################################
# Data EBS Volume
#############################################

resource "aws_ebs_volume" "moodle_data" {
  availability_zone = "us-east-1c"
  size              = var.data_volume_size
  type              = var.ebs_volume_type
  iops              = var.ebs_volume_type == "gp3" ? var.ebs_iops : null
  throughput        = var.ebs_volume_type == "gp3" ? var.ebs_throughput : null
  encrypted         = true

  tags = merge(
    local.common_tags,
    {
      Name       = "${local.name_prefix}-data"
      MountPoint = "/moodledata"
      VolumeType = "data"
    }
  )
}

#############################################
# User Data Script
#############################################

locals {
  user_data = <<-EOF
    #!/bin/bash
    set -e

    # Log output
    exec > >(tee /var/log/user-data.log)
    exec 2>&1

    echo "=== Moodle EC2 Initialization Started ==="
    echo "Timestamp: $(date)"
    echo "Instance Type: ${var.instance_type}"
    echo "PHP-FPM max_children: ${local.php_fpm_max_children}"
    echo "SWAP size: ${local.swap_size_gb}GB"

    # Wait for volumes to be available
    sleep 10

    # Format and mount data volume if not already mounted
    if ! grep -q "/moodledata" /etc/fstab; then
        echo "=== Configuring data volume ==="

        # Wait for device
        while [ ! -e /dev/nvme1n1 ]; do
            echo "Waiting for data volume..."
            sleep 2
        done

        # Check if volume has a filesystem
        if ! blkid /dev/nvme1n1; then
            echo "Formatting data volume..."
            mkfs -t ext4 /dev/nvme1n1
        fi

        # Create mount point
        mkdir -p /moodledata

        # Get UUID
        UUID=$(blkid -s UUID -o value /dev/nvme1n1)

        # Add to fstab
        echo "UUID=$UUID /moodledata ext4 defaults,nofail 0 2" >> /etc/fstab

        # Mount
        mount -a

        echo "Data volume mounted successfully"
    fi

    # Configure SWAP if enabled
    %{if var.enable_swap}
    if [ ! -f /swapfile ]; then
        echo "=== Configuring SWAP (${local.swap_size_gb}GB) ==="
        dd if=/dev/zero of=/swapfile bs=1M count=$((${local.swap_size_gb} * 1024)) status=progress
        chmod 600 /swapfile
        mkswap /swapfile
        swapon /swapfile
        echo '/swapfile none swap sw 0 0' >> /etc/fstab
        echo 'vm.swappiness=10' >> /etc/sysctl.conf
        sysctl -p
        echo "SWAP configured successfully"
    fi
    %{endif}

    # Update system
    echo "=== Updating system packages ==="
    dnf update -y

    # Install basic utilities
    echo "=== Installing utilities ==="
    dnf install -y \
        git \
        wget \
        curl \
        unzip \
        tar \
        vim \
        htop \
        tree \
        jq

    # Store configuration for use by setup scripts
    cat > /root/moodle-config.env << 'CONFIG'
    # Moodle Configuration
    DOMAIN_NAME="${var.domain_name}"
    ADMIN_EMAIL="${var.admin_email}"
    DB_HOST="${var.create_rds ? aws_db_instance.moodle[0].address : "localhost"}"
    DB_NAME="${var.db_name}"
    DB_USER="${var.db_username}"
    DB_PASSWORD="${var.db_password}"
    MOODLE_VERSION="${var.moodle_version}"
    MOODLE_ADMIN_USER="${var.moodle_admin_user}"
    MOODLE_ADMIN_PASSWORD="${var.moodle_admin_password}"
    MOODLE_SITE_NAME="${var.moodle_site_name}"
    PHP_FPM_MAX_CHILDREN="${local.php_fpm_max_children}"
    CONFIG

    chmod 600 /root/moodle-config.env

    # NOTE: Moodle 5.1 uses /var/www/html/moodle/public as DocumentRoot
    # Apache configuration must point to the /public subdirectory

    echo "=== EC2 Initialization Completed ==="
    echo "Next steps: Run setup scripts from skills/scripts/"

  EOF
}

#############################################
# EC2 Instance
#############################################

resource "aws_instance" "moodle" {
  ami                    = local.ami_id
  instance_type          = var.instance_type
  key_name               = var.key_pair_name
  subnet_id              = local.subnet_id
  vpc_security_group_ids = [aws_security_group.moodle.id]
  iam_instance_profile   = aws_iam_instance_profile.moodle.name

  # Root volume
  root_block_device {
    volume_type           = var.ebs_volume_type
    volume_size           = var.root_volume_size
    iops                  = var.ebs_volume_type == "gp3" ? var.ebs_iops : null
    throughput            = var.ebs_volume_type == "gp3" ? var.ebs_throughput : null
    encrypted             = true
    delete_on_termination = false

    tags = merge(
      local.common_tags,
      {
        Name       = "${local.name_prefix}-root"
        VolumeType = "root"
      }
    )
  }

  # User data for initial setup
  user_data = local.user_data

  # Enable detailed monitoring if requested
  monitoring = var.enable_detailed_monitoring

  # Metadata options for IMDSv2
  metadata_options {
    http_endpoint               = "enabled"
    http_tokens                 = "required"
    http_put_response_hop_limit = 1
  }

  tags = merge(
    local.common_tags,
    {
      Name = "${local.name_prefix}-ec2"
    }
  )

  lifecycle {
    ignore_changes = [
      ami,
      user_data
    ]
  }

  depends_on = [
    aws_ebs_volume.moodle_data
  ]
}

#############################################
# EBS Volume Attachment
#############################################

resource "aws_volume_attachment" "moodle_data" {
  device_name = "/dev/sdf"
  volume_id   = aws_ebs_volume.moodle_data.id
  instance_id = aws_instance.moodle.id

  # Force detach on destroy
  force_detach = true
}

#############################################
# Elastic IP
#############################################

resource "aws_eip" "moodle" {
  domain   = "vpc"
  instance = aws_instance.moodle.id

  tags = merge(
    local.common_tags,
    {
      Name = "${local.name_prefix}-eip"
    }
  )

  depends_on = [aws_instance.moodle]
}

#############################################
# Route 53 Record (Optional)
#############################################

resource "aws_route53_record" "moodle" {
  count = var.create_route53_record ? 1 : 0

  zone_id = var.route53_zone_id
  name    = var.domain_name
  type    = "A"
  ttl     = 300
  records = [aws_eip.moodle.public_ip]
}

#############################################
# Outputs
#############################################

output "ec2_instance_id" {
  description = "ID of the EC2 instance"
  value       = aws_instance.moodle.id
}

output "ec2_public_ip" {
  description = "Public IP (Elastic IP) of the instance"
  value       = aws_eip.moodle.public_ip
}

output "ec2_private_ip" {
  description = "Private IP of the instance"
  value       = aws_instance.moodle.private_ip
}

output "data_volume_id" {
  description = "ID of the data EBS volume"
  value       = aws_ebs_volume.moodle_data.id
}

output "security_group_id" {
  description = "ID of the security group"
  value       = aws_security_group.moodle.id
}

output "ssh_command" {
  description = "SSH command to connect to the instance"
  value       = "ssh -i ~/.ssh/${var.key_pair_name}.pem ec2-user@${aws_eip.moodle.public_ip}"
}
