#!/bin/bash
################################################################################
# Moodle 5.1 Infrastructure Provisioning Script
# Version: 1.0.0
# Date: 2026-02-02
# Description: Wrapper for Terraform to provision AWS infrastructure
################################################################################

set -e

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

# Directories
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(cd "$SCRIPT_DIR/.." && pwd)"
TERRAFORM_DIR="$PROJECT_ROOT/terraform"
CONFIG_DIR="$PROJECT_ROOT/config"

echo "======================================================================"
echo "Moodle 5.1 Infrastructure Provisioning"
echo "Started: $(date)"
echo "======================================================================"

################################################################################
# Check prerequisites
################################################################################

echo -e "\n${BLUE}[1/6] Checking prerequisites...${NC}"

# Check Terraform
if ! command -v terraform &> /dev/null; then
    echo -e "${RED}ERROR: Terraform is not installed${NC}"
    echo "Install from: https://www.terraform.io/downloads"
    exit 1
fi
echo "✓ Terraform: $(terraform version -json | jq -r '.terraform_version')"

# Check AWS CLI
if ! command -v aws &> /dev/null; then
    echo -e "${RED}ERROR: AWS CLI is not installed${NC}"
    echo "Install from: https://aws.amazon.com/cli/"
    exit 1
fi
echo "✓ AWS CLI: $(aws --version | cut -d' ' -f1)"

# Check jq
if ! command -v jq &> /dev/null; then
    echo -e "${YELLOW}WARNING: jq is not installed (optional but recommended)${NC}"
else
    echo "✓ jq: $(jq --version)"
fi

################################################################################
# Load and validate configuration
################################################################################

echo -e "\n${BLUE}[2/6] Loading configuration...${NC}"

# Check if terraform.tfvars exists
if [ ! -f "$TERRAFORM_DIR/terraform.tfvars" ]; then
    echo -e "${RED}ERROR: terraform.tfvars not found${NC}"
    echo ""
    echo "Please create it from the example:"
    echo "  cd $TERRAFORM_DIR"
    echo "  cp terraform.tfvars.example terraform.tfvars"
    echo "  vim terraform.tfvars  # Edit with your values"
    exit 1
fi

echo "✓ Configuration file found: terraform.tfvars"

# Extract key values (basic validation)
KEY_PAIR=$(grep -E "^key_pair_name" "$TERRAFORM_DIR/terraform.tfvars" | cut -d'"' -f2)
DOMAIN=$(grep -E "^domain_name" "$TERRAFORM_DIR/terraform.tfvars" | cut -d'"' -f2)
INSTANCE_TYPE=$(grep -E "^instance_type" "$TERRAFORM_DIR/terraform.tfvars" | cut -d'"' -f2)

if [ -z "$KEY_PAIR" ] || [ "$KEY_PAIR" = "my-key-pair" ]; then
    echo -e "${RED}ERROR: key_pair_name not configured in terraform.tfvars${NC}"
    exit 1
fi

if [ -z "$DOMAIN" ] || [ "$DOMAIN" = "moodle.example.com" ]; then
    echo -e "${RED}ERROR: domain_name not configured in terraform.tfvars${NC}"
    exit 1
fi

echo "  Key Pair: $KEY_PAIR"
echo "  Domain: $DOMAIN"
echo "  Instance: $INSTANCE_TYPE"

# Verify AWS credentials
echo -e "\n${BLUE}Verifying AWS credentials...${NC}"
AWS_ACCOUNT=$(aws sts get-caller-identity --query Account --output text 2>/dev/null)
if [ $? -ne 0 ]; then
    echo -e "${RED}ERROR: AWS credentials not configured or invalid${NC}"
    echo "Run: aws configure"
    exit 1
fi
echo "✓ AWS Account: $AWS_ACCOUNT"

# Verify key pair exists in AWS
echo -e "\n${BLUE}Verifying EC2 key pair...${NC}"
if ! aws ec2 describe-key-pairs --key-names "$KEY_PAIR" &>/dev/null; then
    echo -e "${RED}ERROR: Key pair '$KEY_PAIR' not found in AWS${NC}"
    echo "Create it with:"
    echo "  aws ec2 create-key-pair --key-name $KEY_PAIR --query 'KeyMaterial' --output text > ~/.ssh/${KEY_PAIR}.pem"
    echo "  chmod 400 ~/.ssh/${KEY_PAIR}.pem"
    exit 1
fi
echo "✓ Key pair exists in AWS"

################################################################################
# Initialize Terraform
################################################################################

echo -e "\n${BLUE}[3/6] Initializing Terraform...${NC}"

cd "$TERRAFORM_DIR"

terraform init

if [ $? -ne 0 ]; then
    echo -e "${RED}ERROR: Terraform initialization failed${NC}"
    exit 1
fi

echo -e "${GREEN}✓ Terraform initialized${NC}"

################################################################################
# Plan infrastructure
################################################################################

echo -e "\n${BLUE}[4/6] Planning infrastructure...${NC}"

terraform plan -out=tfplan

if [ $? -ne 0 ]; then
    echo -e "${RED}ERROR: Terraform plan failed${NC}"
    exit 1
fi

echo -e "\n${YELLOW}======================================================================"
echo "REVIEW THE PLAN ABOVE"
echo "======================================================================${NC}"
echo ""
echo "Resources to be created:"
terraform show -json tfplan | jq -r '.resource_changes[] | select(.change.actions[] | contains("create")) | "  - \(.type): \(.name)"' 2>/dev/null || echo "  (use jq for detailed list)"

################################################################################
# Confirmation
################################################################################

echo -e "\n${BLUE}[5/6] Confirmation${NC}"
echo ""
echo -e "${YELLOW}You are about to provision the following infrastructure:${NC}"
echo "  AWS Account: $AWS_ACCOUNT"
echo "  Domain: $DOMAIN"
echo "  Instance Type: $INSTANCE_TYPE"
echo "  Key Pair: $KEY_PAIR"
echo ""
echo -e "${YELLOW}This will incur AWS charges. Review the cost estimate above.${NC}"
echo ""

read -p "Do you want to proceed? (yes/no): " -r
echo

if [[ ! $REPLY =~ ^[Yy][Ee][Ss]$ ]]; then
    echo "Deployment cancelled by user"
    rm -f tfplan
    exit 0
fi

################################################################################
# Apply Terraform
################################################################################

echo -e "\n${BLUE}[6/6] Applying Terraform configuration...${NC}"
echo "This may take 5-10 minutes..."
echo ""

terraform apply tfplan

if [ $? -ne 0 ]; then
    echo -e "${RED}ERROR: Terraform apply failed${NC}"
    exit 1
fi

# Clean up plan file
rm -f tfplan

################################################################################
# Extract outputs
################################################################################

echo -e "\n${GREEN}======================================================================"
echo "Infrastructure Provisioned Successfully!"
echo "======================================================================${NC}"

# Get outputs
ELASTIC_IP=$(terraform output -raw ec2_public_ip 2>/dev/null)
INSTANCE_ID=$(terraform output -raw ec2_instance_id 2>/dev/null)
RDS_ENDPOINT=$(terraform output -raw rds_address 2>/dev/null || echo "N/A")

# Save to file for next scripts
OUTPUT_FILE="$CONFIG_DIR/infrastructure-outputs.env"
cat > "$OUTPUT_FILE" << EOF
# Infrastructure outputs from Terraform
# Generated: $(date)

ELASTIC_IP="$ELASTIC_IP"
INSTANCE_ID="$INSTANCE_ID"
RDS_ENDPOINT="$RDS_ENDPOINT"
DOMAIN="$DOMAIN"
KEY_PAIR="$KEY_PAIR"
EOF

chmod 600 "$OUTPUT_FILE"

echo ""
echo "📊 Infrastructure Details:"
echo "  Instance ID: $INSTANCE_ID"
echo "  Public IP: $ELASTIC_IP"
echo "  RDS Endpoint: $RDS_ENDPOINT"
echo ""
echo "📁 Outputs saved to: $OUTPUT_FILE"
echo ""
echo "🔗 DNS Configuration:"
echo "  Point $DOMAIN to $ELASTIC_IP"
echo ""
echo "📝 Next Steps:"
echo ""
echo "  1. Connect to instance:"
echo "     ssh -i ~/.ssh/${KEY_PAIR}.pem ec2-user@$ELASTIC_IP"
echo ""
echo "  2. Run setup script:"
echo "     cd $SCRIPT_DIR"
echo "     ./02-setup-server.sh"
echo ""
echo "  3. Install Moodle:"
echo "     ./03-install-moodle.sh"
echo ""

# Display full outputs
echo -e "\n${BLUE}Full Terraform Outputs:${NC}"
terraform output

echo ""
echo "======================================================================"
echo "Completed: $(date)"
echo "======================================================================"

exit 0
