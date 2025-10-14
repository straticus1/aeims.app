# AEIMS Platform CLI

Unified command-line interface for managing the AEIMS platform, including authentication testing, deployments, site management, and monitoring.

## Installation

The CLI is located at the project root:

```bash
./aeims [command] [subcommand] [options]
```

For global access, create a symlink:

```bash
ln -s /Users/ryan/development/aeims.app/aeims /usr/local/bin/aeims
```

## Quick Start

```bash
# List all customers
./aeims auth list

# Test customer login
./aeims auth curl flirts.nyc crossuser password123

# Check platform status
./aeims status

# View recent logs
./aeims logs tail 5

# Run authentication tests
./aeims test auth
```

## Commands

### Authentication (`aeims auth`)

Manage and test customer authentication across all sites.

```bash
# List all customers with credentials
aeims auth list

# Test authentication locally (PHP session)
aeims auth test flirts.nyc flirtyuser password123

# Test authentication via HTTP (real request)
aeims auth curl flirts.nyc flirtyuser password123

# Verify password hash matches
aeims auth verify flirtyuser password123

# Create new customer account
aeims auth create newuser user@email.com mypassword flirts.nyc
```

**Example Output:**
```
=== cURL Authentication Test ===

ℹ Testing https://flirts.nyc/auth.php
ℹ Username: crossuser

✓ HTTP 302 (Redirect)
✓ Location: /dashboard.php (Dashboard!)
✓ Session cookie set
```

### Deployment (`aeims deploy`)

Build, push, and deploy Docker images to production.

```bash
# Build Docker image with custom tag
aeims deploy build auth-fix-20251013

# Push image to ECR
aeims deploy push auth-fix-20251013

# Full release: build + push + deploy
aeims deploy release auth-fix-20251013

# Rollback to previous task definition
aeims deploy rollback 93
```

**Release Process:**
1. Builds Docker image locally
2. Pushes to ECR (515966511618.dkr.ecr.us-east-1.amazonaws.com)
3. Registers new ECS task definition
4. Updates aeims-service to use new task definition
5. Waits for deployment to stabilize

### Site Management (`aeims site`)

Monitor and test AEIMS sites.

```bash
# List all sites
aeims site list

# Test site availability
aeims site test flirts.nyc

# Check SSL certificate for specific site
aeims site ssl flirts.nyc

# Check all SSL certificates
aeims site ssl
```

**Example Output:**
```
=== AEIMS Sites ===

ℹ Found 4 sites:

Flirts NYC (flirts.nyc)
  Type: Customer Site
  URL: https://flirts.nyc

NYC Flirts (nycflirts.com)
  Type: Customer Site
  URL: https://nycflirts.com
```

### Database (`aeims db`)

Database management and maintenance.

```bash
# Connect to production database
aeims db connect

# Create database backup
aeims db backup

# Run migrations (not yet implemented)
aeims db migrate
```

**Database Connection:**
- Host: nitetext-db.c3iuy64is41m.us-east-1.rds.amazonaws.com
- Port: 5432
- Database: aeims_core
- User: nitetext

### Testing (`aeims test`)

Run automated tests.

```bash
# Run authentication tests for all sites
aeims test auth

# Run end-to-end Playwright tests
aeims test e2e

# Run all tests
aeims test all
```

**Auth Tests:**
Tests customer login on:
- flirtyuser on flirts.nyc
- nycuser on nycflirts.com
- crossuser on both sites

### Monitoring (`aeims status` & `aeims logs`)

Monitor platform health and view logs.

```bash
# Show platform status
aeims status

# Tail logs (last 5 minutes, follow)
aeims logs tail 5

# Show only errors (last 30 minutes)
aeims logs errors 30
```

**Status Output:**
```
=== AEIMS Platform Status ===

ℹ Checking ECS service...
[Shows task definition, running count, etc.]

ℹ Checking sites...
  aeims.app: UP (HTTP 200)
  flirts.nyc: UP (HTTP 200)
  nycflirts.com: UP (HTTP 200)
  sexacomms.com: UP (HTTP 200)
```

## Configuration

### AWS Credentials

Ensure AWS CLI is configured:

```bash
aws configure
# AWS Access Key ID: [your key]
# AWS Secret Access Key: [your secret]
# Default region name: us-east-1
# Default output format: json
```

### Docker

Docker must be installed and running for deployment commands.

### Database Access

Database credentials are hardcoded for production. For local development, update the `connectDB()` function.

## Common Workflows

### Deploy New Authentication Fix

```bash
# 1. Test locally first
./aeims auth test flirts.nyc crossuser password123

# 2. Build and deploy
./aeims deploy release auth-fix-$(date +%Y%m%d-%H%M%S)

# 3. Wait for deployment to complete

# 4. Test in production
./aeims auth curl flirts.nyc crossuser password123

# 5. Check logs for errors
./aeims logs errors 5
```

### Debug Customer Login Issues

```bash
# 1. List all customers and verify credentials exist
./aeims auth list

# 2. Verify password hash
./aeims auth verify crossuser password123

# 3. Test authentication locally
./aeims auth test flirts.nyc crossuser password123

# 4. Test via HTTP (production)
./aeims auth curl flirts.nyc crossuser password123

# 5. Check recent logs
./aeims logs tail 10
```

### Create Test Customer

```bash
# Create customer for flirts.nyc
./aeims auth create testuser test@example.com mypassword flirts.nyc

# Verify it was created
./aeims auth list

# Test login
./aeims auth curl flirts.nyc testuser mypassword
```

### Monitor Production

```bash
# Check overall status
./aeims status

# Watch logs in real-time
./aeims logs tail 5

# Check all sites
./aeims site test aeims.app
./aeims site test flirts.nyc
./aeims site test nycflirts.com
./aeims site test sexacomms.com
```

### Rollback Deployment

```bash
# 1. Check current task definition
./aeims status

# 2. Rollback to previous version
./aeims deploy rollback 93

# 3. Verify rollback
./aeims status
```

## Troubleshooting

### Command Not Found

Make sure the CLI is executable:

```bash
chmod +x /Users/ryan/development/aeims.app/aeims
```

### AWS Errors

Ensure AWS CLI is installed and configured:

```bash
aws --version
aws configure list
```

### Docker Errors

Check Docker is running:

```bash
docker ps
```

### Database Connection Fails

Verify security group allows your IP:

```bash
# Test connection
./aeims db connect
```

### Authentication Tests Fail

1. Verify customer exists: `./aeims auth list`
2. Verify password: `./aeims auth verify username password`
3. Check production logs: `./aeims logs errors 30`

## Architecture

The CLI is a single PHP script that:
- Integrates with CustomerAuth for local auth testing
- Uses AWS CLI for ECS/ECR operations
- Uses cURL for HTTP-based testing
- Provides color-coded output for readability

### Key Components

- **Authentication Testing**: Tests both PHP session-based and HTTP-based authentication
- **Deployment Pipeline**: Automates Docker build, ECR push, and ECS updates
- **Site Monitoring**: Quick health checks for all domains
- **Log Aggregation**: Direct access to CloudWatch logs

### Files

- `/aeims` - Main CLI script
- `/test-auth.php` - Standalone auth testing utility (legacy)
- `/includes/CustomerAuth.php` - Authentication class used by CLI

## Future Enhancements

- [ ] Add migration system for database schema changes
- [ ] Implement blue/green deployments
- [ ] Add performance monitoring commands
- [ ] Create backup restoration workflow
- [ ] Add operator management commands
- [ ] Implement automated testing in CI/CD
- [ ] Add site analytics commands
- [ ] Create customer support tools

## Version History

### v2.0 (2025-10-13)
- Complete rewrite with unified command structure
- Added comprehensive authentication testing
- Integrated deployment pipeline
- Added site monitoring and SSL checks
- Improved error handling and output formatting

### v1.0 (2024-10-01)
- Initial version with basic commands

## Support

For issues or feature requests, contact the development team or check the main project documentation.

---

**AEIMS Platform CLI v2.0**
© 2025 After Dark Systems
