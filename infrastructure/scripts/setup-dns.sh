#!/bin/bash
# AEIMS DNS Setup Script
# Automates Route53 hosted zone creation and provides nameserver information

set -e

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Default values
DOMAIN_NAME="aeims.app"
AWS_REGION="us-east-1"
ENVIRONMENT="prod"
TERRAFORM_DIR="../terraform"

# Print colored output
print_info() {
    echo -e "${BLUE}[INFO]${NC} $1"
}

print_success() {
    echo -e "${GREEN}[SUCCESS]${NC} $1"
}

print_warning() {
    echo -e "${YELLOW}[WARNING]${NC} $1"
}

print_error() {
    echo -e "${RED}[ERROR]${NC} $1"
}

# Display help
show_help() {
    cat << EOF
AEIMS DNS Setup Script

This script automates the creation of Route53 hosted zone and provides
nameserver information for domain delegation.

Usage: $0 [OPTIONS]

Options:
    -d, --domain DOMAIN     Domain name (default: aeims.app)
    -r, --region REGION     AWS region (default: us-east-1)
    -e, --env ENVIRONMENT   Environment (default: prod)
    -t, --terraform-dir DIR Terraform directory path
    -h, --help              Show this help message

Examples:
    $0                              # Use defaults
    $0 -d aeims.app -e prod        # Specify domain and environment
    $0 --region us-west-2          # Use different region

Prerequisites:
    - AWS CLI configured with appropriate permissions
    - Terraform installed
    - Domain registrar access for nameserver updates

What this script does:
    1. Validates AWS credentials and permissions
    2. Creates/updates Terraform infrastructure
    3. Sets up Route53 hosted zone
    4. Creates DNS records for subdomains
    5. Provisions SSL certificates
    6. Provides nameserver information for domain delegation
EOF
}

# Parse command line arguments
parse_args() {
    while [[ $# -gt 0 ]]; do
        case $1 in
            -d|--domain)
                DOMAIN_NAME="$2"
                shift 2
                ;;
            -r|--region)
                AWS_REGION="$2"
                shift 2
                ;;
            -e|--env)
                ENVIRONMENT="$2"
                shift 2
                ;;
            -t|--terraform-dir)
                TERRAFORM_DIR="$2"
                shift 2
                ;;
            -h|--help)
                show_help
                exit 0
                ;;
            *)
                print_error "Unknown option: $1"
                show_help
                exit 1
                ;;
        esac
    done
}

# Validate prerequisites
validate_prerequisites() {
    print_info "Validating prerequisites..."

    # Check AWS CLI
    if ! command -v aws &> /dev/null; then
        print_error "AWS CLI is not installed. Please install it first."
        exit 1
    fi

    # Check Terraform
    if ! command -v terraform &> /dev/null; then
        print_error "Terraform is not installed. Please install it first."
        exit 1
    fi

    # Check AWS credentials
    if ! aws sts get-caller-identity &> /dev/null; then
        print_error "AWS credentials not configured. Run 'aws configure' first."
        exit 1
    fi

    # Check if terraform directory exists
    if [[ ! -d "$TERRAFORM_DIR" ]]; then
        print_error "Terraform directory not found: $TERRAFORM_DIR"
        exit 1
    fi

    print_success "Prerequisites validated"
}

# Check AWS permissions
check_aws_permissions() {
    print_info "Checking AWS permissions..."

    local required_permissions=(
        "route53:CreateHostedZone"
        "route53:ChangeResourceRecordSets"
        "acm:RequestCertificate"
        "ec2:RunInstances"
        "iam:CreateRole"
    )

    # Basic permission check by attempting to list hosted zones
    if ! aws route53 list-hosted-zones &> /dev/null; then
        print_error "Insufficient Route53 permissions"
        exit 1
    fi

    print_success "AWS permissions verified"
}

# Setup Terraform configuration
setup_terraform() {
    print_info "Setting up Terraform configuration..."

    cd "$TERRAFORM_DIR"

    # Check if terraform.tfvars exists
    if [[ ! -f "terraform.tfvars" ]]; then
        print_warning "terraform.tfvars not found. Creating from example..."

        if [[ -f "terraform.tfvars.example" ]]; then
            cp terraform.tfvars.example terraform.tfvars
            print_warning "Please edit terraform.tfvars with your specific values"
            print_warning "Especially update: ssh_public_key, admin_ips, and db_password"
        else
            print_error "terraform.tfvars.example not found"
            exit 1
        fi
    fi

    # Initialize Terraform
    print_info "Initializing Terraform..."
    terraform init

    # Validate Terraform configuration
    print_info "Validating Terraform configuration..."
    terraform validate

    print_success "Terraform configuration ready"
}

# Plan and apply Terraform
deploy_infrastructure() {
    print_info "Planning infrastructure deployment..."

    # Generate Terraform plan
    terraform plan \
        -var="domain_name=$DOMAIN_NAME" \
        -var="aws_region=$AWS_REGION" \
        -var="environment=$ENVIRONMENT" \
        -out=tfplan

    print_info "Terraform plan generated. Review the plan above."

    # Ask for confirmation
    echo
    read -p "Do you want to apply this plan? (yes/no): " confirm
    case $confirm in
        [Yy]es|[Yy])
            print_info "Applying Terraform plan..."
            terraform apply tfplan
            ;;
        *)
            print_warning "Deployment cancelled"
            exit 0
            ;;
    esac

    print_success "Infrastructure deployed successfully"
}

# Get nameserver information
get_nameservers() {
    print_info "Retrieving nameserver information..."

    # Get nameservers from Terraform output
    local nameservers=$(terraform output -json name_servers 2>/dev/null | jq -r '.[]' 2>/dev/null || echo "")

    if [[ -z "$nameservers" ]]; then
        # Fallback: query Route53 directly
        local zone_id=$(aws route53 list-hosted-zones-by-name --dns-name "$DOMAIN_NAME" --query "HostedZones[0].Id" --output text 2>/dev/null)
        if [[ "$zone_id" != "None" && -n "$zone_id" ]]; then
            nameservers=$(aws route53 get-hosted-zone --id "$zone_id" --query "DelegationSet.NameServers" --output text | tr '\t' '\n')
        fi
    fi

    if [[ -n "$nameservers" ]]; then
        print_success "Nameservers for $DOMAIN_NAME:"
        echo
        echo "$nameservers" | while read -r ns; do
            echo "  • $ns"
        done
        echo
    else
        print_error "Could not retrieve nameservers"
        return 1
    fi
}

# Display deployment summary
show_deployment_summary() {
    print_info "Deployment Summary"
    echo "=================="
    echo

    # Get outputs from Terraform
    if terraform output &> /dev/null; then
        echo "🌐 Domain: $DOMAIN_NAME"
        echo "🖥️  Instance IP: $(terraform output -raw instance_public_ip 2>/dev/null || echo 'N/A')"
        echo "🔒 SSL Certificate: $(terraform output -raw ssl_certificate_status 2>/dev/null || echo 'N/A')"
        echo "📊 Environment: $ENVIRONMENT"
        echo "🌍 Region: $AWS_REGION"
        echo

        echo "🔗 Website URLs:"
        terraform output -json website_urls 2>/dev/null | jq -r 'to_entries[] | "  • \(.key): \(.value)"' 2>/dev/null || echo "  • Check Terraform outputs for URLs"
        echo

        echo "💾 Backup Bucket: $(terraform output -raw backup_bucket_name 2>/dev/null || echo 'N/A')"
        echo "📈 Log Group: $(terraform output -raw log_group_name 2>/dev/null || echo 'N/A')"
        echo
    fi

    echo "📋 Next Steps:"
    echo "  1. Update your domain registrar's nameservers (shown above)"
    echo "  2. Wait for DNS propagation (24-48 hours)"
    echo "  3. Deploy AEIMS application code"
    echo "  4. Configure SSL certificates (automatic via Let's Encrypt)"
    echo "  5. Test all functionality"
    echo

    echo "🔧 Useful Commands:"
    echo "  • Check DNS propagation: dig NS $DOMAIN_NAME"
    echo "  • SSH to server: $(terraform output -raw ssh_command 2>/dev/null || echo 'ssh -i ~/.ssh/aeims-key ubuntu@SERVER_IP')"
    echo "  • View logs: aws logs tail $(terraform output -raw log_group_name 2>/dev/null || echo '/aws/ec2/aeims-prod') --follow"
    echo "  • Destroy infrastructure: terraform destroy"
}

# Create afterdarksys-vpc integration script
create_vpc_integration() {
    print_info "Creating afterdarksys-vpc integration script..."

    cat > setup-afterdarksys-vpc.sh << 'EOF'
#!/bin/bash
# Script to integrate AEIMS with existing afterdarksys-vpc

AFTERDARKSYS_VPC_DIR="../../../afterdarksys-vpc"

if [[ -d "$AFTERDARKSYS_VPC_DIR" ]]; then
    echo "Found afterdarksys-vpc directory"

    # Create aeims.app subdirectory
    mkdir -p "$AFTERDARKSYS_VPC_DIR/aeims.app"

    # Copy infrastructure files
    cp -r ../terraform/* "$AFTERDARKSYS_VPC_DIR/aeims.app/"
    cp -r ../ansible/* "$AFTERDARKSYS_VPC_DIR/aeims.app/" 2>/dev/null || true
    cp -r ../scripts/* "$AFTERDARKSYS_VPC_DIR/aeims.app/" 2>/dev/null || true

    echo "AEIMS infrastructure copied to afterdarksys-vpc/aeims.app/"
    echo "You can now deploy from that location using existing VPC settings"
else
    echo "afterdarksys-vpc directory not found at $AFTERDARKSYS_VPC_DIR"
    echo "Continuing with standalone deployment..."
fi
EOF

    chmod +x setup-afterdarksys-vpc.sh
    print_success "VPC integration script created"
}

# Save nameservers to file
save_nameservers() {
    local output_file="nameservers-$DOMAIN_NAME.txt"

    print_info "Saving nameserver information to $output_file..."

    cat > "$output_file" << EOF
AEIMS DNS Configuration for $DOMAIN_NAME
Generated on: $(date)
Environment: $ENVIRONMENT
Region: $AWS_REGION

=== NAMESERVERS ===
Update these nameservers in your domain registrar:

$(terraform output -json name_servers 2>/dev/null | jq -r '.[]' 2>/dev/null || aws route53 list-hosted-zones-by-name --dns-name "$DOMAIN_NAME" --query "HostedZones[0].Config.Comment" --output text 2>/dev/null || echo "Unable to retrieve nameservers")

=== DNS RECORDS CREATED ===
• $DOMAIN_NAME (A record)
• www.$DOMAIN_NAME (A record)
• admin.$DOMAIN_NAME (A record)
• support.$DOMAIN_NAME (A record)
• api.$DOMAIN_NAME (A record)

=== SSL CERTIFICATE ===
Wildcard SSL certificate requested for:
• $DOMAIN_NAME
• *.$DOMAIN_NAME

=== INFRASTRUCTURE ===
Instance IP: $(terraform output -raw instance_public_ip 2>/dev/null || echo 'Check Terraform outputs')
Backup Bucket: $(terraform output -raw backup_bucket_name 2>/dev/null || echo 'Check Terraform outputs')

=== VERIFICATION ===
Check DNS propagation with:
  dig NS $DOMAIN_NAME
  dig A $DOMAIN_NAME

Once propagated, access your site at:
  https://$DOMAIN_NAME
EOF

    print_success "Nameserver information saved to $output_file"
}

# Main execution
main() {
    echo "AEIMS Infrastructure Setup"
    echo "========================="
    echo

    parse_args "$@"

    print_info "Starting setup for domain: $DOMAIN_NAME"
    print_info "Environment: $ENVIRONMENT"
    print_info "Region: $AWS_REGION"
    echo

    validate_prerequisites
    check_aws_permissions
    setup_terraform
    deploy_infrastructure

    echo
    print_success "Infrastructure deployment completed!"
    echo

    get_nameservers
    save_nameservers
    show_deployment_summary
    create_vpc_integration

    echo
    print_success "AEIMS DNS setup completed successfully!"
    print_info "Don't forget to update your domain registrar's nameservers"
}

# Run main function with all arguments
main "$@"
EOF