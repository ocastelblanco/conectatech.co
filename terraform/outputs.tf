# Terraform Outputs - Moodle 5.1 Infrastructure

#############################################
# Summary Output
#############################################

output "deployment_summary" {
  description = "Complete deployment summary"
  value = {
    project     = var.project_name
    environment = var.environment
    region      = var.aws_region
    timestamp   = timestamp()
  }
}

#############################################
# Connection Information
#############################################

output "connection_info" {
  description = "How to connect to your Moodle instance"
  value = {
    ssh_command     = "ssh -i ~/.ssh/${var.key_pair_name}.pem ec2-user@${aws_eip.moodle.public_ip}"
    public_ip       = aws_eip.moodle.public_ip
    domain          = var.domain_name
    moodle_url      = "https://${var.domain_name}"
    admin_username  = var.moodle_admin_user
  }
  sensitive = false
}

#############################################
# Database Connection
#############################################

output "database_info" {
  description = "Database connection information"
  value = var.create_rds ? {
    endpoint    = aws_db_instance.moodle[0].endpoint
    address     = aws_db_instance.moodle[0].address
    port        = aws_db_instance.moodle[0].port
    database    = aws_db_instance.moodle[0].db_name
    username    = aws_db_instance.moodle[0].username
    connection  = "mysql -h ${aws_db_instance.moodle[0].address} -u ${var.db_username} -p ${var.db_name}"
  } : {
    message = "RDS not created (create_rds = false)"
  }
  sensitive = true
}

#############################################
# Resource IDs
#############################################

output "resource_ids" {
  description = "AWS resource identifiers"
  value = {
    ec2_instance_id   = aws_instance.moodle.id
    elastic_ip_id     = aws_eip.moodle.id
    root_volume_id    = aws_instance.moodle.root_block_device[0].volume_id
    data_volume_id    = aws_ebs_volume.moodle_data.id
    security_group_id = aws_security_group.moodle.id
    rds_instance_id   = var.create_rds ? aws_db_instance.moodle[0].id : "N/A"
  }
}

#############################################
# Cost Estimation
#############################################

locals {
  # EC2 costs (approximate, us-east-1)
  ec2_costs = {
    "t4g.nano"   = 2.5
    "t4g.micro"  = 5.0
    "t4g.small"  = 12.0
    "t4g.medium" = 24.0
    "t4g.large"  = 48.0
    "t4g.xlarge" = 96.0
  }

  # RDS costs (approximate, us-east-1)
  rds_costs = {
    "db.t4g.micro"  = 12.0
    "db.t4g.small"  = 24.0
    "db.t4g.medium" = 48.0
    "db.t4g.large"  = 96.0
  }

  ec2_monthly_cost = lookup(local.ec2_costs, var.instance_type, 24.0)
  rds_monthly_cost = var.create_rds ? lookup(local.rds_costs, var.db_instance_class, 12.0) : 0
  ebs_root_cost    = var.root_volume_size * 0.10  # gp3 $0.10/GB
  ebs_data_cost    = var.data_volume_size * 0.10
  elastic_ip_cost  = 3.60
  other_costs      = 2.00  # CloudWatch, etc

  total_monthly_cost = local.ec2_monthly_cost + local.rds_monthly_cost + local.ebs_root_cost + local.ebs_data_cost + local.elastic_ip_cost + local.other_costs
}

output "cost_estimate" {
  description = "Estimated monthly costs in USD"
  value = {
    ec2           = format("$%.2f", local.ec2_monthly_cost)
    rds           = format("$%.2f", local.rds_monthly_cost)
    ebs_root      = format("$%.2f", local.ebs_root_cost)
    ebs_data      = format("$%.2f", local.ebs_data_cost)
    elastic_ip    = format("$%.2f", local.elastic_ip_cost)
    other         = format("$%.2f", local.other_costs)
    total_monthly = format("$%.2f", local.total_monthly_cost)
    note         = "Estimates for us-east-1. Actual costs may vary."
  }
}

#############################################
# Next Steps
#############################################

output "next_steps" {
  description = "What to do after Terraform apply"
  value = <<-EOT

  ====================================================================
  🎉 Infrastructure provisioned successfully!
  ====================================================================

  📋 Next Steps:

  1️⃣  Connect to your instance:
     ${  "ssh -i ~/.ssh/${var.key_pair_name}.pem ec2-user@${aws_eip.moodle.public_ip}"}

  2️⃣  Verify configuration was loaded:
     source /root/moodle-config.env
     cat /root/moodle-config.env

  3️⃣  Run setup scripts (from your local machine):
     cd skills/scripts/
     ./02-setup-server.sh
     ./03-install-moodle.sh
     ./04-configure-ssl.sh
     ./05-optimize-system.sh
     ./06-setup-backups.sh

     ⚠️  IMPORTANT for Moodle 5.1:
     Apache DocumentRoot must point to /var/www/html/moodle/public
     (not /var/www/html/moodle)

  4️⃣  Point your domain to this IP:
     ${var.domain_name} → ${aws_eip.moodle.public_ip}

  5️⃣  Access your Moodle site:
     https://${var.domain_name}
     Username: ${var.moodle_admin_user}
     Password: [configured in variables]

  📊 Estimated Monthly Cost: ${format("$%.2f", local.total_monthly_cost)}

  📚 Documentation: skills/docs/
  🔧 Configuration: skills/config/

  ====================================================================

  EOT
}

#############################################
# Terraform State Info
#############################################

output "terraform_info" {
  description = "Terraform state information"
  value = {
    workspace = terraform.workspace
    version   = "1.0.0"
  }
}
