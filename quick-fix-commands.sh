#!/bin/bash
# AEIMS Quick Fix Commands
# Immediate commands to restore production service

set -e

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

echo -e "${BLUE}=== AEIMS Quick Fix Commands ===${NC}"
echo -e "${YELLOW}Use these commands for immediate production restoration${NC}"
echo

# Instance IDs
INSTANCE_1="i-0475b6e8d04649552"
INSTANCE_2="i-08db8069a342c0370"
REGION="us-east-1"

echo -e "${BLUE}1. Check current instance and target group status:${NC}"
echo "aws ec2 describe-instances --region $REGION --instance-ids $INSTANCE_1 $INSTANCE_2 --query 'Reservations[].Instances[].[InstanceId,State.Name,PublicIpAddress]' --output table"
echo
echo "aws elbv2 describe-target-groups --region $REGION --names aeims-app-tg-production --query 'TargetGroups[0].[TargetGroupName,TargetGroupArn,HealthCheckPath]' --output table"
echo
echo "aws elbv2 describe-target-health --region $REGION --target-group-arn \$(aws elbv2 describe-target-groups --region $REGION --names aeims-app-tg-production --query 'TargetGroups[0].TargetGroupArn' --output text) --query 'TargetHealthDescriptions[].[Target.Id,TargetHealth.State,TargetHealth.Description]' --output table"
echo

echo -e "${BLUE}2. Connect to instances via SSM:${NC}"
echo "aws ssm start-session --region $REGION --target $INSTANCE_1"
echo "aws ssm start-session --region $REGION --target $INSTANCE_2"
echo

echo -e "${BLUE}3. Quick PostgreSQL configuration update (run on each instance):${NC}"
cat << 'EOF'
# Connect via SSM, then run these commands:

# Update environment variables
sudo mkdir -p /var/www/aeims
sudo tee /var/www/aeims/.env > /dev/null << 'ENVEOF'
export DB_HOST=127.0.0.1
export DB_PORT=5432
export DB_NAME=aeims_core
export DB_USER=aeims_user
export DB_PASS=secure_password_123
export ENVIRONMENT=prod
ENVEOF

# Install PostgreSQL client and remove MySQL
sudo apt-get update -y
sudo apt-get install -y postgresql-client php8.2-pgsql
sudo apt-get remove -y mysql-client mysql-server php8.2-mysql || true

# Create basic database config
sudo tee /var/www/aeims/database_config.php > /dev/null << 'PHPEOF'
<?php
$db_config = [
    'host' => $_ENV['DB_HOST'] ?? '127.0.0.1',
    'port' => $_ENV['DB_PORT'] ?? '5432',
    'dbname' => $_ENV['DB_NAME'] ?? 'aeims_core',
    'username' => $_ENV['DB_USER'] ?? 'aeims_user',
    'password' => $_ENV['DB_PASS'] ?? 'secure_password_123'
];

function getDbConnection() {
    global $db_config;
    $dsn = "pgsql:host={$db_config['host']};port={$db_config['port']};dbname={$db_config['dbname']}";
    try {
        $pdo = new PDO($dsn, $db_config['username'], $db_config['password']);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        return $pdo;
    } catch (PDOException $e) {
        error_log("Database connection failed: " . $e->getMessage());
        throw new PDOException("Unable to connect to database");
    }
}
?>
PHPEOF

# Create health check endpoint
sudo tee /var/www/aeims/index.php > /dev/null << 'HEALTHEOF'
<?php
require_once 'database_config.php';

if (isset($_GET['health'])) {
    try {
        $pdo = getDbConnection();
        $stmt = $pdo->query("SELECT 1");
        header('Content-Type: application/json');
        echo json_encode(['status' => 'healthy', 'database' => 'connected']);
    } catch (Exception $e) {
        http_response_code(503);
        header('Content-Type: application/json');
        echo json_encode(['status' => 'unhealthy', 'error' => 'database_connection_failed']);
    }
    exit;
}
?>
<!DOCTYPE html>
<html>
<head><title>AEIMS - Production Ready</title></head>
<body>
    <h1>AEIMS</h1>
    <p>Adult Entertainment Information Management System</p>
    <p>Status: Production Ready with PostgreSQL</p>
</body>
</html>
HEALTHEOF

# Set permissions
sudo chown -R www-data:www-data /var/www/aeims
sudo chmod -R 755 /var/www/aeims

# Update PHP-FPM environment
sudo tee -a /etc/php/8.2/fpm/pool.d/www.conf > /dev/null << 'FPMEOF'
env[DB_HOST] = 127.0.0.1
env[DB_PORT] = 5432
env[DB_NAME] = aeims_core
env[DB_USER] = aeims_user
env[DB_PASS] = secure_password_123
FPMEOF

# Restart services
sudo systemctl restart php8.2-fpm
sudo systemctl restart nginx

# Test local health check
curl -f http://localhost/?health && echo "Health check passed" || echo "Health check failed"

echo "Instance configuration completed!"
EOF

echo
echo -e "${BLUE}4. Bulk deployment via SSM (alternative approach):${NC}"
echo "# Deploy to both instances simultaneously"
echo "aws ssm send-command --region $REGION --instance-ids $INSTANCE_1 $INSTANCE_2 --document-name 'AWS-RunShellScript' --parameters 'commands=[\$(cat quick-deploy-script.sh)]'"
echo

echo -e "${BLUE}5. Monitor deployment progress:${NC}"
echo "# Replace COMMAND-ID with the actual command ID from step 4"
echo "aws ssm list-command-invocations --region $REGION --command-id COMMAND-ID --details --query 'CommandInvocations[].[InstanceId,Status,StatusDetails]' --output table"
echo

echo -e "${BLUE}6. Check health after deployment:${NC}"
echo "# Check target health"
echo "aws elbv2 describe-target-health --region $REGION --target-group-arn \$(aws elbv2 describe-target-groups --region $REGION --names aeims-app-tg-production --query 'TargetGroups[0].TargetGroupArn' --output text)"
echo
echo "# Test website directly"
echo "curl -I https://www.aeims.app/"
echo "curl -f https://www.aeims.app/?health"
echo

echo -e "${BLUE}7. Emergency rollback (if needed):${NC}"
echo "# If new deployment fails, you can:"
echo "# a) Create new instances from AMI"
echo "# b) Update target group to point to new instances"
echo "# c) Terminate failed instances"
echo

echo -e "${BLUE}8. Get deployment logs:${NC}"
echo "# View SSM command output"
echo "aws ssm get-command-invocation --region $REGION --command-id COMMAND-ID --instance-id $INSTANCE_1"
echo "aws ssm get-command-invocation --region $REGION --command-id COMMAND-ID --instance-id $INSTANCE_2"
echo

echo -e "${GREEN}=== Quick Fix Commands Ready ===${NC}"
echo -e "${YELLOW}Choose your approach:${NC}"
echo -e "  • ${BLUE}Manual:${NC} Use SSM sessions and run commands directly"
echo -e "  • ${BLUE}Automated:${NC} Run the main restoration script: ./aws-production-restore.sh"
echo -e "  • ${BLUE}Bulk:${NC} Use SSM send-command for multiple instances"