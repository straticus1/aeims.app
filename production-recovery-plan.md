# AEIMS Production Recovery Plan
## PostgreSQL Migration Fix for AWS Deployment

### Current Situation
- **Production URL**: https://www.aeims.app (returning 502 errors)
- **ALB**: aeims-alb-production (working, SSL fixed)
- **Target Group**: aeims-app-tg-production (targets failing health checks)
- **Unhealthy Instances**: i-0475b6e8d04649552, i-08db8069a342c0370
- **Root Cause**: Instances still configured for MySQL after PostgreSQL migration

### Recovery Options

#### Option 1: Quick Fix (Recommended - 15 minutes)
Use AWS Systems Manager to deploy PostgreSQL configuration to existing instances.

```bash
# Make scripts executable
chmod +x aws-production-restore.sh quick-fix-commands.sh postgres-deployment-script.sh

# Run automated restoration
./aws-production-restore.sh
```

#### Option 2: Manual SSM Fix (20 minutes)
Connect to each instance manually and run deployment script.

```bash
# Connect to instance 1
aws ssm start-session --region us-east-1 --target i-0475b6e8d04649552

# Upload and run deployment script
curl -o postgres-deployment-script.sh https://raw.githubusercontent.com/your-repo/postgres-deployment-script.sh
chmod +x postgres-deployment-script.sh
sudo ./postgres-deployment-script.sh

# Repeat for instance 2
aws ssm start-session --region us-east-1 --target i-08db8069a342c0370
```

#### Option 3: New Instance Deployment (30 minutes)
Create fresh instances with correct configuration.

```bash
# Launch new instances using existing AMI
aws ec2 run-instances \
    --region us-east-1 \
    --image-id ami-xxxxxxxxx \
    --instance-type t3.medium \
    --key-name your-key \
    --security-group-ids sg-xxxxxxxxx \
    --subnet-id subnet-xxxxxxxxx \
    --user-data file://postgres-deployment-script.sh
```

### Pre-Execution Checklist

- [ ] AWS CLI configured with appropriate permissions
- [ ] SSM access to target instances verified
- [ ] PostgreSQL database (aeims_core) is accessible
- [ ] Database credentials are correct
- [ ] Backup of current configuration (if needed)

### Execution Steps

#### Phase 1: Preparation (5 minutes)
```bash
# Verify AWS access
aws sts get-caller-identity

# Check current instance status
aws ec2 describe-instances --region us-east-1 \
    --instance-ids i-0475b6e8d04649552 i-08db8069a342c0370 \
    --query 'Reservations[].Instances[].[InstanceId,State.Name]' --output table

# Check target group health
aws elbv2 describe-target-health --region us-east-1 \
    --target-group-arn $(aws elbv2 describe-target-groups --region us-east-1 \
    --names aeims-app-tg-production --query 'TargetGroups[0].TargetGroupArn' --output text)
```

#### Phase 2: Deployment (10 minutes)
```bash
# Option A: Automated deployment
./aws-production-restore.sh

# Option B: Manual deployment per instance
./aws-production-restore.sh deploy i-0475b6e8d04649552
./aws-production-restore.sh deploy i-08db8069a342c0370
```

#### Phase 3: Validation (5 minutes)
```bash
# Wait for health checks
sleep 120

# Verify target health
aws elbv2 describe-target-health --region us-east-1 \
    --target-group-arn $(aws elbv2 describe-target-groups --region us-east-1 \
    --names aeims-app-tg-production --query 'TargetGroups[0].TargetGroupArn' --output text)

# Test website
curl -I https://www.aeims.app/
curl -f https://www.aeims.app/?health
```

### Configuration Changes Applied

#### Database Configuration
- **Old**: MySQL connection strings
- **New**: PostgreSQL connection to aeims_core database
- **Host**: 127.0.0.1 (adjust if external)
- **Port**: 5432
- **Database**: aeims_core
- **User**: aeims_user

#### Application Changes
- Remove MySQL PHP extensions
- Install PostgreSQL PHP extensions (php8.2-pgsql)
- Update database_config.php for PostgreSQL
- Add health check endpoint
- Update environment variables

#### Service Configuration
- PHP-FPM pool with PostgreSQL environment variables
- Nginx configuration for health checks
- Updated file permissions
- Service restart procedures

### Rollback Plan (if needed)

If the deployment fails:

1. **Revert to previous AMI**:
```bash
# Launch instances from known-good AMI
aws ec2 run-instances --image-id ami-previous-working
```

2. **Update target group**:
```bash
# Register new instances
aws elbv2 register-targets --target-group-arn $TARGET_GROUP_ARN \
    --targets Id=new-instance-id
```

3. **Remove failed instances**:
```bash
# Deregister old instances
aws elbv2 deregister-targets --target-group-arn $TARGET_GROUP_ARN \
    --targets Id=old-instance-id
```

### Monitoring Commands

#### During Deployment
```bash
# Monitor SSM command execution
aws ssm list-command-invocations --region us-east-1 --command-id COMMAND-ID

# Check service status on instances
aws ssm send-command --region us-east-1 \
    --instance-ids i-0475b6e8d04649552 i-08db8069a342c0370 \
    --document-name "AWS-RunShellScript" \
    --parameters 'commands=["systemctl status nginx php8.2-fpm"]'
```

#### Post-Deployment
```bash
# Continuous health monitoring
watch -n 30 'aws elbv2 describe-target-health --region us-east-1 --target-group-arn $TARGET_GROUP_ARN'

# Application logs
aws ssm send-command --region us-east-1 \
    --instance-ids i-0475b6e8d04649552 \
    --document-name "AWS-RunShellScript" \
    --parameters 'commands=["tail -f /var/log/nginx/aeims_error.log"]'
```

### Success Criteria

- [ ] Both EC2 instances show "healthy" in target group
- [ ] https://www.aeims.app/ returns 200 status
- [ ] Health check endpoint responds: https://www.aeims.app/?health
- [ ] Database connection test passes
- [ ] No 502 errors from ALB
- [ ] SSL certificate working correctly

### Emergency Contacts

- **Technical Lead**: rjc@afterdarksys.com
- **AWS Account**: Check IAM for emergency procedures
- **Database**: Verify AEIMS Core PostgreSQL accessibility

### Post-Recovery Tasks

1. Update Ansible playbooks for PostgreSQL
2. Create new AMI from working instances
3. Update auto-scaling launch template
4. Document configuration changes
5. Set up monitoring alerts for database connectivity

---

**Execution Time**: 15-30 minutes
**Risk Level**: Medium (existing SSL and ALB configuration preserved)
**Success Rate**: High (non-destructive configuration update)