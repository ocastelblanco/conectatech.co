---
name: provision-infrastructure
description: Provisions complete AWS infrastructure for Moodle 5.1 using Terraform including EC2, RDS, EBS volumes, Security Groups, and IAM roles. Use when starting a new Moodle deployment from scratch or when the user needs to create AWS infrastructure for Moodle. Triggers: "deploy Moodle on AWS", "provision infrastructure", "create EC2 instance", "setup AWS for Moodle", "provision EC2", "set up RDS database", "configure networking and security groups".
---

# Provision AWS Infrastructure for Moodle

## Instructions

### Context

The deployment includes:
- EC2 instance (t4g ARM Graviton for cost efficiency)
- EBS volumes (root + data for /moodledata)
- RDS MariaDB 10.11.15
- Security Groups (EC2 and RDS)
- Elastic IP
- IAM roles with CloudWatch and backup permissions
- Auto-configuration via User Data

### Required files

The infrastructure code is in `terraform/`:
- `main.tf` - Main configuration
- `variables.tf` - 75+ configurable variables
- `ec2.tf` - EC2, EBS, Security Groups
- `rds.tf` - RDS MariaDB configuration
- `outputs.tf` - Resource outputs and next steps

### Steps to execute

1. **Verify prerequisites:**
   - AWS CLI configured (`aws configure`)
   - Terraform installed (>= 1.0)
   - EC2 key pair exists in AWS
   - Domain name ready

2. **Configure variables:**
   ```bash
   cd terraform/
   cp terraform.tfvars.example terraform.tfvars
   ```

   Edit `terraform.tfvars` with:
   - `key_pair_name` - EC2 key pair name
   - `domain_name` - Your domain
   - `admin_email` - Admin email
   - `db_password` - Secure database password
   - `moodle_admin_password` - Secure Moodle password
   - `allowed_ssh_cidrs` - Your IP for SSH access

3. **Validate configuration:**
   ```bash
   terraform init
   terraform validate
   terraform plan
   ```

4. **Apply infrastructure:**
   ```bash
   terraform apply
   ```

   Review the plan and type `yes` to confirm.

5. **Save outputs:**
   ```bash
   terraform output > infrastructure-outputs.txt
   terraform output -json > infrastructure-outputs.json
   ```

6. **Extract key information:**
   - Elastic IP: Use for DNS A record
   - Instance ID: For AWS console management
   - RDS Endpoint: Database connection string
   - SSH Command: Connection command

### Auto-calculated optimizations

The Terraform code automatically calculates:
- **PHP-FPM max_children** based on RAM
- **SWAP size** based on available memory
- **Apache worker settings** based on instance type
- **Cost estimates** for the deployment

### Instance sizing guide

| Instance | RAM | Users | Cost/month | Use Case |
|----------|-----|-------|------------|----------|
| t4g.medium | 4GB | 100-300 | ~$24 | Basic production |
| t4g.large | 8GB | 300-1000 | ~$48 | Recommended |
| t4g.xlarge | 16GB | 1000+ | ~$96 | High traffic |

### Important notes

- **Security:** Change `allowed_ssh_cidrs` from `0.0.0.0/0` to your IP
- **Passwords:** Use strong passwords for DB and Moodle admin
- **DNS:** Point domain to Elastic IP before SSL configuration
- **Cost:** Total infrastructure ~$45-90/month depending on instance size
- **Backups:** Automated backups configured via RDS and planned snapshots

### Post-provisioning

After successful provisioning:
1. Update DNS A record: domain -> Elastic IP
2. Connect via SSH using the provided command
3. Proceed to server setup with `/setup-moodle-server` skill
4. Configuration file created at `/root/moodle-config.env` on server

### Troubleshooting

**Error: Key pair not found**
- Create key pair: `aws ec2 create-key-pair --key-name moodle-key`

**Error: Terraform state locked**
- Wait for other operation to complete or force unlock

**Error: Insufficient permissions**
- Verify IAM user has EC2, RDS, VPC permissions

**Error: Resource limit exceeded**
- Check AWS service quotas in console

### Expected output

After successful execution:
- EC2 instance running
- Elastic IP associated
- RDS database available
- Security groups configured
- Data volume mounted at /moodledata
- SWAP configured (if enabled)
- Configuration saved in /root/moodle-config.env

## Example usage

**User:** "I need to deploy Moodle on AWS for 500 users"

**Assistant:** I'll help you provision AWS infrastructure for Moodle optimized for 500 users.

Based on your user count, I recommend:
- Instance: t4g.large (8GB RAM)
- Database: db.t4g.small
- Estimated cost: ~$72/month

Let me start by configuring the Terraform variables...

[Proceeds to configure terraform.tfvars]
[Validates configuration]
[Executes terraform apply]
[Provides outputs and next steps]

---

**User:** "The terraform apply failed with permission error"

**Assistant:** Let me troubleshoot the permission error.

[Checks AWS credentials]
[Verifies IAM permissions required]
[Suggests specific policy to add]
[Retries after confirmation]
