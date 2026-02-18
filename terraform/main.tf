# Moodle 5.1 AWS Infrastructure - Main Configuration
# Version: 1.0.0
# Date: 2026-02-02

terraform {
  required_version = ">= 1.0"

  required_providers {
    aws = {
      source  = "hashicorp/aws"
      version = "~> 5.0"
    }
  }

  # Optional: Configure backend for state management
  # Uncomment and configure for production use
  # backend "s3" {
  #   bucket         = "my-terraform-state-bucket"
  #   key            = "moodle/terraform.tfstate"
  #   region         = "us-east-1"
  #   encrypt        = true
  #   dynamodb_table = "terraform-state-lock"
  # }
}

provider "aws" {
  region  = var.aws_region
  profile = var.aws_profile

  default_tags {
    tags = var.tags
  }
}

#############################################
# Data Sources
#############################################

# Get default VPC if not specified
data "aws_vpc" "default" {
  default = var.vpc_id == "" ? true : false
  id      = var.vpc_id != "" ? var.vpc_id : null
}

# Get default subnet if not specified
data "aws_subnets" "default" {
  filter {
    name   = "vpc-id"
    values = [data.aws_vpc.default.id]
  }
}

# Get latest Amazon Linux 2023 ARM64 AMI
data "aws_ami" "amazon_linux_2023_arm" {
  most_recent = true
  owners      = ["amazon"]

  filter {
    name   = "name"
    values = ["al2023-ami-2023.*-arm64"]
  }

  filter {
    name   = "architecture"
    values = ["arm64"]
  }

  filter {
    name   = "virtualization-type"
    values = ["hvm"]
  }

  filter {
    name   = "root-device-type"
    values = ["ebs"]
  }
}

# Get current AWS account ID
data "aws_caller_identity" "current" {}

# Get current AWS region
data "aws_region" "current" {}

# Get availability zones
data "aws_availability_zones" "available" {
  state = "available"
}

#############################################
# Local Variables
#############################################

locals {
  # Resource naming
  name_prefix = "${var.project_name}-${var.environment}"

  # Network
  vpc_id    = var.vpc_id != "" ? var.vpc_id : data.aws_vpc.default.id
  subnet_id = var.subnet_id != "" ? var.subnet_id : data.aws_subnets.default.ids[0]

  # AMI
  ami_id = var.ami_id != "" ? var.ami_id : data.aws_ami.amazon_linux_2023_arm.id

  # Auto-calculate PHP-FPM max_children based on instance type
  instance_memory = {
    "t4g.nano"   = 512
    "t4g.micro"  = 1024
    "t4g.small"  = 2048
    "t4g.medium" = 4096
    "t4g.large"  = 8192
    "t4g.xlarge" = 16384
  }

  memory_mb            = lookup(local.instance_memory, var.instance_type, 4096)
  system_memory_mb     = 800
  available_memory_mb  = local.memory_mb - local.system_memory_mb
  memory_per_process   = 200
  calculated_max_children = floor(local.available_memory_mb / local.memory_per_process)
  php_fpm_max_children = var.php_fpm_max_children > 0 ? var.php_fpm_max_children : local.calculated_max_children

  # Auto-calculate SWAP size
  calculated_swap_gb = local.memory_mb < 2048 ? ceil(local.memory_mb / 512) : (local.memory_mb <= 8192 ? ceil(local.memory_mb / 1024) : 8)
  swap_size_gb      = var.swap_size_gb > 0 ? var.swap_size_gb : local.calculated_swap_gb

  # Common tags
  common_tags = merge(
    var.tags,
    {
      Name        = local.name_prefix
      Terraform   = "true"
      Environment = var.environment
    }
  )
}

#############################################
# Outputs for User Info
#############################################

output "configuration_summary" {
  description = "Summary of calculated configuration"
  value = {
    instance_type        = var.instance_type
    memory_mb           = local.memory_mb
    php_fpm_max_children = local.php_fpm_max_children
    swap_size_gb        = local.swap_size_gb
    ami_id              = local.ami_id
    vpc_id              = local.vpc_id
    subnet_id           = local.subnet_id
  }
}
