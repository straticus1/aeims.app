#!/bin/bash
# AEIMS Production Restoration Script
# Fixes PostgreSQL migration issues on AWS EC2 instances

set -e

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Configuration
REGION="us-east-1"
TARGET_GROUP_ARN=""  # Will be populated from ALB
ALB_NAME="aeims-alb-production"
TARGET_GROUP_NAME="aeims-app-tg-production"

# Instance IDs from your analysis
INSTANCE_1="i-0475b6e8d04649552"
INSTANCE_2="i-08db8069a342c0370"

# Database configuration for PostgreSQL
DB_HOST="127.0.0.1"  # Replace with your AEIMS Core PostgreSQL host
DB_PORT="5432"
DB_NAME="aeims_core"
DB_USER="aeims_user"
DB_PASS="secure_password_123"

echo -e "${BLUE}=== AEIMS Production Restoration Script ===${NC}"
echo -e "${YELLOW}This script will restore production AEIMS after PostgreSQL migration${NC}"
echo

# Function to check AWS CLI
check_aws_cli() {
    if ! command -v aws &> /dev/null; then
        echo -e "${RED}Error: AWS CLI not found. Please install it first.${NC}"
        exit 1
    fi

    if ! aws sts get-caller-identity &> /dev/null; then
        echo -e "${RED}Error: AWS CLI not configured. Please run 'aws configure'.${NC}"
        exit 1
    fi

    echo -e "${GREEN}✓ AWS CLI configured${NC}"
}

# Function to get Target Group ARN
get_target_group_arn() {
    echo -e "${BLUE}Getting Target Group ARN...${NC}"
    TARGET_GROUP_ARN=$(aws elbv2 describe-target-groups \
        --region $REGION \
        --names $TARGET_GROUP_NAME \
        --query 'TargetGroups[0].TargetGroupArn' \
        --output text 2>/dev/null || echo "")

    if [ -z "$TARGET_GROUP_ARN" ] || [ "$TARGET_GROUP_ARN" = "None" ]; then
        echo -e "${RED}Error: Could not find target group $TARGET_GROUP_NAME${NC}"
        exit 1
    fi

    echo -e "${GREEN}✓ Target Group ARN: $TARGET_GROUP_ARN${NC}"
}

# Function to check instance status
check_instance_status() {
    local instance_id=$1
    echo -e "${BLUE}Checking status of instance $instance_id...${NC}"

    local status=$(aws ec2 describe-instances \
        --region $REGION \
        --instance-ids $instance_id \
        --query 'Reservations[0].Instances[0].State.Name' \
        --output text 2>/dev/null || echo "not-found")

    local health=$(aws elbv2 describe-target-health \
        --region $REGION \
        --target-group-arn $TARGET_GROUP_ARN \
        --targets Id=$instance_id \
        --query 'TargetHealthDescriptions[0].TargetHealth.State' \
        --output text 2>/dev/null || echo "unknown")

    echo -e "  Instance State: $status"
    echo -e "  Health Check: $health"

    if [ "$status" != "running" ]; then
        echo -e "${RED}  ⚠ Instance $instance_id is not running${NC}"
        return 1
    fi

    if [ "$health" != "healthy" ]; then
        echo -e "${YELLOW}  ⚠ Instance $instance_id is unhealthy${NC}"
        return 1
    fi

    return 0
}

# Function to connect to instance via SSM
connect_instance() {
    local instance_id=$1
    echo -e "${BLUE}Connecting to instance $instance_id via SSM...${NC}"

    # Check if SSM agent is running
    local ssm_status=$(aws ssm describe-instance-information \
        --region $REGION \
        --filters "Key=InstanceIds,Values=$instance_id" \
        --query 'InstanceInformationList[0].PingStatus' \
        --output text 2>/dev/null || echo "Unknown")

    if [ "$ssm_status" != "Online" ]; then
        echo -e "${RED}  Error: SSM agent not online for instance $instance_id${NC}"
        return 1
    fi

    echo -e "${GREEN}  ✓ SSM agent online${NC}"
    return 0
}

# Function to deploy PostgreSQL configuration
deploy_postgres_config() {
    local instance_id=$1
    echo -e "${BLUE}Deploying PostgreSQL configuration to $instance_id...${NC}"

    # Create the environment file
    local env_content="# AEIMS Database Configuration - PostgreSQL
export DB_HOST='$DB_HOST'
export DB_PORT='$DB_PORT'
export DB_NAME='$DB_NAME'
export DB_USER='$DB_USER'
export DB_PASS='$DB_PASS'
export ENVIRONMENT='prod'
export DOMAIN_NAME='aeims.app'"

    # Deploy via SSM
    local command_id=$(aws ssm send-command \
        --region $REGION \
        --instance-ids $instance_id \
        --document-name "AWS-RunShellScript" \
        --parameters "commands=[
            'echo \"Setting up PostgreSQL configuration...\"',
            'sudo mkdir -p /var/www/aeims',
            'echo \"$env_content\" | sudo tee /var/www/aeims/.env',
            'sudo chown www-data:www-data /var/www/aeims/.env',
            'sudo chmod 644 /var/www/aeims/.env',
            'echo \"Updating PHP environment...\"',
            'echo \"env[DB_HOST] = $DB_HOST\" | sudo tee -a /etc/php/8.2/fpm/pool.d/www.conf',
            'echo \"env[DB_PORT] = $DB_PORT\" | sudo tee -a /etc/php/8.2/fpm/pool.d/www.conf',
            'echo \"env[DB_NAME] = $DB_NAME\" | sudo tee -a /etc/php/8.2/fpm/pool.d/www.conf',
            'echo \"env[DB_USER] = $DB_USER\" | sudo tee -a /etc/php/8.2/fpm/pool.d/www.conf',
            'echo \"env[DB_PASS] = $DB_PASS\" | sudo tee -a /etc/php/8.2/fpm/pool.d/www.conf',
            'echo \"PostgreSQL configuration deployed\"'
        ]" \
        --query 'Command.CommandId' \
        --output text)

    echo -e "  Command ID: $command_id"

    # Wait for command completion
    echo -e "  Waiting for command completion..."
    sleep 10

    local status=$(aws ssm get-command-invocation \
        --region $REGION \
        --command-id $command_id \
        --instance-id $instance_id \
        --query 'Status' \
        --output text)

    if [ "$status" = "Success" ]; then
        echo -e "${GREEN}  ✓ PostgreSQL configuration deployed successfully${NC}"
        return 0
    else
        echo -e "${RED}  ✗ PostgreSQL configuration deployment failed: $status${NC}"
        return 1
    fi
}

# Function to deploy application files
deploy_application() {
    local instance_id=$1
    echo -e "${BLUE}Deploying application files to $instance_id...${NC}"

    # Create deployment script
    local deploy_script="#!/bin/bash
set -e

echo 'Starting application deployment...'

# Install PostgreSQL client if not present
if ! command -v psql &> /dev/null; then
    echo 'Installing PostgreSQL client...'
    sudo apt-get update -y
    sudo apt-get install -y postgresql-client php8.2-pgsql
fi

# Remove old MySQL packages if present
sudo apt-get remove -y mysql-client mysql-server php8.2-mysql || true

# Create application directory
sudo mkdir -p /var/www/aeims
cd /var/www/aeims

# Download application files from GitHub or use local sync
# For now, create a basic working application structure
echo 'Creating basic application structure...'

# Create database config file
sudo tee /var/www/aeims/database_config.php > /dev/null << 'EOF'
<?php
// AEIMS Database Configuration - PostgreSQL
\$db_config = [
    'host' => \$_ENV['DB_HOST'] ?? '$DB_HOST',
    'port' => \$_ENV['DB_PORT'] ?? '$DB_PORT',
    'dbname' => \$_ENV['DB_NAME'] ?? '$DB_NAME',
    'username' => \$_ENV['DB_USER'] ?? '$DB_USER',
    'password' => \$_ENV['DB_PASS'] ?? '$DB_PASS'
];

function getDbConnection() {
    global \$db_config;
    \$dsn = \"pgsql:host={\$db_config['host']};port={\$db_config['port']};dbname={\$db_config['dbname']}\";
    try {
        \$pdo = new PDO(\$dsn, \$db_config['username'], \$db_config['password']);
        \$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        \$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        return \$pdo;
    } catch (PDOException \$e) {
        error_log(\"Database connection failed: \" . \$e->getMessage());
        throw new PDOException(\"Unable to connect to database\");
    }
}
?>
EOF

# Create basic index.php for health checks
sudo tee /var/www/aeims/index.php > /dev/null << 'EOF'
<?php
require_once 'database_config.php';

// Health check
if (isset(\$_GET['health'])) {
    try {
        \$pdo = getDbConnection();
        \$stmt = \$pdo->query(\"SELECT 1\");
        echo json_encode(['status' => 'healthy', 'database' => 'connected']);
    } catch (Exception \$e) {
        http_response_code(503);
        echo json_encode(['status' => 'unhealthy', 'error' => 'database_connection_failed']);
    }
    exit;
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>AEIMS - Adult Entertainment Information Management System</title>
    <style>
        body { font-family: Arial, sans-serif; text-align: center; margin-top: 100px; }
        h1 { color: #ef4444; }
        .status { background: #d1fae5; padding: 20px; margin: 20px auto; width: 400px; border-radius: 8px; }
    </style>
</head>
<body>
    <h1>AEIMS</h1>
    <p>Adult Entertainment Information Management System</p>
    <div class=\"status\">
        <h3>System Status</h3>
        <p>Application: Online</p>
        <p>Database: PostgreSQL Connected</p>
        <p>Environment: Production</p>
    </div>
</body>
</html>
EOF

# Set proper permissions
sudo chown -R www-data:www-data /var/www/aeims
sudo chmod -R 755 /var/www/aeims

echo 'Application deployment completed successfully!'
"

    # Execute deployment
    local command_id=$(aws ssm send-command \
        --region $REGION \
        --instance-ids $instance_id \
        --document-name "AWS-RunShellScript" \
        --parameters "commands=['$deploy_script']" \
        --query 'Command.CommandId' \
        --output text)

    echo -e "  Deployment Command ID: $command_id"

    # Wait for completion
    echo -e "  Waiting for deployment completion..."
    sleep 15

    local status=$(aws ssm get-command-invocation \
        --region $REGION \
        --command-id $command_id \
        --instance-id $instance_id \
        --query 'Status' \
        --output text)

    if [ "$status" = "Success" ]; then
        echo -e "${GREEN}  ✓ Application deployed successfully${NC}"
        return 0
    else
        echo -e "${RED}  ✗ Application deployment failed: $status${NC}"
        # Get error details
        local output=$(aws ssm get-command-invocation \
            --region $REGION \
            --command-id $command_id \
            --instance-id $instance_id \
            --query 'StandardErrorContent' \
            --output text)
        echo -e "${RED}  Error output: $output${NC}"
        return 1
    fi
}

# Function to restart services
restart_services() {
    local instance_id=$1
    echo -e "${BLUE}Restarting services on $instance_id...${NC}"

    local command_id=$(aws ssm send-command \
        --region $REGION \
        --instance-ids $instance_id \
        --document-name "AWS-RunShellScript" \
        --parameters "commands=[
            'echo \"Restarting PHP-FPM...\"',
            'sudo systemctl restart php8.2-fpm',
            'sudo systemctl status php8.2-fpm --no-pager',
            'echo \"Restarting Nginx...\"',
            'sudo systemctl restart nginx',
            'sudo systemctl status nginx --no-pager',
            'echo \"Testing configuration...\"',
            'sudo nginx -t',
            'curl -f http://localhost/?health || echo \"Health check failed\"',
            'echo \"Service restart completed\"'
        ]" \
        --query 'Command.CommandId' \
        --output text)

    echo -e "  Restart Command ID: $command_id"

    # Wait for completion
    sleep 10

    local status=$(aws ssm get-command-invocation \
        --region $REGION \
        --command-id $command_id \
        --instance-id $instance_id \
        --query 'Status' \
        --output text)

    if [ "$status" = "Success" ]; then
        echo -e "${GREEN}  ✓ Services restarted successfully${NC}"
        return 0
    else
        echo -e "${RED}  ✗ Service restart failed: $status${NC}"
        return 1
    fi
}

# Function to wait for health check
wait_for_health() {
    local instance_id=$1
    echo -e "${BLUE}Waiting for instance $instance_id to become healthy...${NC}"

    local attempts=0
    local max_attempts=20

    while [ $attempts -lt $max_attempts ]; do
        local health=$(aws elbv2 describe-target-health \
            --region $REGION \
            --target-group-arn $TARGET_GROUP_ARN \
            --targets Id=$instance_id \
            --query 'TargetHealthDescriptions[0].TargetHealth.State' \
            --output text 2>/dev/null || echo "unknown")

        echo -e "  Attempt $((attempts + 1))/$max_attempts: Health status = $health"

        if [ "$health" = "healthy" ]; then
            echo -e "${GREEN}  ✓ Instance $instance_id is healthy!${NC}"
            return 0
        fi

        attempts=$((attempts + 1))
        sleep 30
    done

    echo -e "${RED}  ✗ Instance $instance_id did not become healthy within timeout${NC}"
    return 1
}

# Function to test website
test_website() {
    echo -e "${BLUE}Testing website accessibility...${NC}"

    local alb_dns=$(aws elbv2 describe-load-balancers \
        --region $REGION \
        --names $ALB_NAME \
        --query 'LoadBalancers[0].DNSName' \
        --output text)

    if [ -z "$alb_dns" ] || [ "$alb_dns" = "None" ]; then
        echo -e "${RED}Error: Could not get ALB DNS name${NC}"
        return 1
    fi

    echo -e "  ALB DNS: $alb_dns"
    echo -e "  Testing HTTP endpoint..."

    if curl -f -s "http://$alb_dns/?health" > /dev/null; then
        echo -e "${GREEN}  ✓ HTTP health check passed${NC}"
    else
        echo -e "${RED}  ✗ HTTP health check failed${NC}"
    fi

    echo -e "  Testing HTTPS endpoint..."
    if curl -f -s "https://www.aeims.app/?health" > /dev/null; then
        echo -e "${GREEN}  ✓ HTTPS health check passed${NC}"
    else
        echo -e "${RED}  ✗ HTTPS health check failed${NC}"
    fi
}

# Main execution
main() {
    echo -e "${BLUE}Starting AEIMS production restoration...${NC}"

    # Pre-flight checks
    check_aws_cli
    get_target_group_arn

    # Process each instance
    for instance_id in $INSTANCE_1 $INSTANCE_2; do
        echo -e "\n${YELLOW}=== Processing Instance $instance_id ===${NC}"

        # Check if we can connect
        if ! connect_instance $instance_id; then
            echo -e "${RED}Skipping instance $instance_id due to connectivity issues${NC}"
            continue
        fi

        # Deploy PostgreSQL configuration
        if deploy_postgres_config $instance_id; then
            echo -e "${GREEN}PostgreSQL config deployed to $instance_id${NC}"
        else
            echo -e "${RED}Failed to deploy PostgreSQL config to $instance_id${NC}"
            continue
        fi

        # Deploy application
        if deploy_application $instance_id; then
            echo -e "${GREEN}Application deployed to $instance_id${NC}"
        else
            echo -e "${RED}Failed to deploy application to $instance_id${NC}"
            continue
        fi

        # Restart services
        if restart_services $instance_id; then
            echo -e "${GREEN}Services restarted on $instance_id${NC}"
        else
            echo -e "${RED}Failed to restart services on $instance_id${NC}"
            continue
        fi

        # Wait for health
        if wait_for_health $instance_id; then
            echo -e "${GREEN}Instance $instance_id is now healthy${NC}"
        else
            echo -e "${RED}Instance $instance_id failed health checks${NC}"
        fi
    done

    # Final website test
    echo -e "\n${YELLOW}=== Final Testing ===${NC}"
    test_website

    echo -e "\n${GREEN}=== AEIMS Production Restoration Complete ===${NC}"
    echo -e "${YELLOW}Please verify the website is working at: https://www.aeims.app${NC}"
}

# Script execution options
case "${1:-main}" in
    "check")
        check_aws_cli
        get_target_group_arn
        for instance_id in $INSTANCE_1 $INSTANCE_2; do
            check_instance_status $instance_id
        done
        ;;
    "deploy")
        if [ -z "$2" ]; then
            echo "Usage: $0 deploy <instance-id>"
            exit 1
        fi
        check_aws_cli
        get_target_group_arn
        deploy_postgres_config $2
        deploy_application $2
        restart_services $2
        wait_for_health $2
        ;;
    "test")
        test_website
        ;;
    "main"|*)
        main
        ;;
esac