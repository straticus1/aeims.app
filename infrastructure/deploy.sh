#!/bin/bash
# AEIMS Complete Deployment Script
# Orchestrates Terraform and Ansible deployment

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
TERRAFORM_DIR="infrastructure/terraform"
ANSIBLE_DIR="infrastructure/ansible"
SKIP_TERRAFORM=false
SKIP_ANSIBLE=false

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
AEIMS Complete Deployment Script

Usage: $0 [OPTIONS]

Options:
    -d, --domain DOMAIN         Domain name (default: aeims.app)
    -r, --region REGION         AWS region (default: us-east-1)
    -e, --env ENVIRONMENT       Environment (default: prod)
    --skip-terraform            Skip Terraform deployment
    --skip-ansible              Skip Ansible deployment
    -h, --help                  Show this help message

Examples:
    $0                          # Full deployment
    $0 --skip-terraform         # Only run Ansible
    $0 --skip-ansible           # Only run Terraform
    $0 -d aeims.app -e prod     # Specify domain and environment

Prerequisites:
    - AWS CLI configured
    - Terraform installed
    - Ansible installed
    - SSH key configured
    - terraform.tfvars file created

What this script does:
    1. Validates prerequisites
    2. Deploys infrastructure with Terraform
    3. Configures server with Ansible
    4. Provides deployment summary and next steps
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
            --skip-terraform)
                SKIP_TERRAFORM=true
                shift
                ;;
            --skip-ansible)
                SKIP_ANSIBLE=true
                shift
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

    # Check required tools
    local tools=("aws" "terraform" "ansible" "jq")
    for tool in "${tools[@]}"; do
        if ! command -v "$tool" &> /dev/null; then
            print_error "$tool is not installed"
            exit 1
        fi
    done

    # Check AWS credentials
    if ! aws sts get-caller-identity &> /dev/null; then
        print_error "AWS credentials not configured"
        exit 1
    fi

    # Check terraform.tfvars
    if [[ ! -f "$TERRAFORM_DIR/terraform.tfvars" ]]; then
        print_error "terraform.tfvars not found in $TERRAFORM_DIR/"
        print_warning "Copy terraform.tfvars.example to terraform.tfvars and update values"
        exit 1
    fi

    print_success "Prerequisites validated"
}

# Deploy infrastructure with Terraform
deploy_terraform() {
    if [[ "$SKIP_TERRAFORM" == true ]]; then
        print_warning "Skipping Terraform deployment"
        return 0
    fi

    print_info "Deploying infrastructure with Terraform..."

    cd "$TERRAFORM_DIR"

    # Initialize Terraform
    print_info "Initializing Terraform..."
    terraform init

    # Validate configuration
    print_info "Validating Terraform configuration..."
    terraform validate

    # Plan deployment
    print_info "Planning infrastructure..."
    terraform plan \
        -var="domain_name=$DOMAIN_NAME" \
        -var="aws_region=$AWS_REGION" \
        -var="environment=$ENVIRONMENT" \
        -out=tfplan

    # Ask for confirmation
    echo
    read -p "Do you want to apply the Terraform plan? (yes/no): " confirm
    case $confirm in
        [Yy]es|[Yy])
            print_info "Applying Terraform plan..."
            terraform apply tfplan
            ;;
        *)
            print_warning "Terraform deployment cancelled"
            cd ..
            exit 0
            ;;
    esac

    cd ..
    print_success "Infrastructure deployed successfully"
}

# Deploy application with Ansible
deploy_ansible() {
    if [[ "$SKIP_ANSIBLE" == true ]]; then
        print_warning "Skipping Ansible deployment"
        return 0
    fi

    print_info "Configuring server with Ansible..."

    cd "$ANSIBLE_DIR"

    # Get instance IP from Terraform
    local instance_ip
    if [[ "$SKIP_TERRAFORM" != true ]]; then
        instance_ip=$(cd ../terraform && terraform output -raw instance_public_ip 2>/dev/null || echo "")
    fi

    if [[ -z "$instance_ip" ]]; then
        print_warning "Could not get instance IP from Terraform"
        read -p "Enter the server IP address: " instance_ip
    fi

    if [[ -z "$instance_ip" ]]; then
        print_error "Server IP is required for Ansible deployment"
        exit 1
    fi

    # Update inventory with actual IP
    sed -i.bak "s/{{ terraform_output_instance_ip }}/$instance_ip/g" inventory.yml

    # Wait for SSH to be ready
    print_info "Waiting for SSH to be ready on $instance_ip..."
    local ssh_ready=false
    local attempts=0
    local max_attempts=30

    while [[ $ssh_ready == false && $attempts -lt $max_attempts ]]; do
        if ssh -o ConnectTimeout=5 -o StrictHostKeyChecking=no ubuntu@"$instance_ip" 'exit' &>/dev/null; then
            ssh_ready=true
        else
            echo -n "."
            sleep 10
            ((attempts++))
        fi
    done

    if [[ $ssh_ready == false ]]; then
        print_error "Could not connect to server via SSH"
        exit 1
    fi

    echo
    print_success "SSH connection established"

    # Run Ansible playbook
    print_info "Running Ansible playbook..."
    ansible-playbook -i inventory.yml deploy.yml \
        -e "domain_name=$DOMAIN_NAME" \
        -e "environment=$ENVIRONMENT" \
        -v

    # Restore inventory file
    mv inventory.yml.bak inventory.yml

    cd ..
    print_success "Server configuration completed"
}

# Display deployment summary
show_deployment_summary() {
    print_info "Deployment Summary"
    echo "=================="
    echo

    if [[ "$SKIP_TERRAFORM" != true ]]; then
        echo "🌐 Domain: $DOMAIN_NAME"

        cd "$TERRAFORM_DIR"
        local instance_ip=$(terraform output -raw instance_public_ip 2>/dev/null || echo 'N/A')
        local nameservers=$(terraform output -json name_servers 2>/dev/null | jq -r '.[]' 2>/dev/null || echo '')

        echo "🖥️  Instance IP: $instance_ip"
        echo "🌍 Region: $AWS_REGION"
        echo "📊 Environment: $ENVIRONMENT"
        echo

        if [[ -n "$nameservers" ]]; then
            echo "📋 Nameservers (update in your domain registrar):"
            echo "$nameservers" | while read -r ns; do
                echo "  • $ns"
            done
            echo
        fi

        cd ..
    fi

    echo "🔗 Website URLs:"
    echo "  • Main site: https://$DOMAIN_NAME"
    echo "  • Admin panel: https://admin.$DOMAIN_NAME"
    echo "  • Support portal: https://support.$DOMAIN_NAME"
    echo "  • API endpoint: https://api.$DOMAIN_NAME"
    echo

    echo "🔑 Admin Credentials:"
    echo "  • Username: admin"
    echo "  • Password: secret (change immediately!)"
    echo

    echo "📋 Next Steps:"
    echo "  1. Update domain registrar nameservers (if not done)"
    echo "  2. Wait for DNS propagation (24-48 hours)"
    echo "  3. Change admin password"
    echo "  4. Test all functionality"
    echo "  5. Configure monitoring and alerts"
    echo

    echo "🔧 Useful Commands:"
    echo "  • Check DNS: dig NS $DOMAIN_NAME"
    echo "  • SSH to server: ssh -i ~/.ssh/aeims-key ubuntu@\$INSTANCE_IP"
    echo "  • View logs: tail -f /var/log/nginx/${DOMAIN_NAME}_*.log"
    echo "  • Restart services: sudo systemctl restart nginx php8.2-fpm"
}

# Main execution
main() {
    echo "AEIMS Complete Deployment"
    echo "========================"
    echo

    parse_args "$@"

    print_info "Starting deployment for domain: $DOMAIN_NAME"
    print_info "Environment: $ENVIRONMENT"
    print_info "Region: $AWS_REGION"
    echo

    validate_prerequisites
    deploy_terraform
    deploy_ansible

    echo
    print_success "AEIMS deployment completed successfully!"
    echo

    show_deployment_summary

    echo
    print_success "🎉 AEIMS is now deployed and ready!"
    print_info "Visit https://$DOMAIN_NAME to see your site"
}

# Run main function with all arguments
main "$@"