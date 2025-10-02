# AEIMS Infrastructure - AWS Terraform Configuration
# Creates EC2 instance, security groups, Route53 hosted zone, and SSL certificate

# Data sources
data "aws_availability_zones" "available" {
  state = "available"
}

data "aws_ami" "ubuntu" {
  most_recent = true
  owners      = ["099720109477"] # Canonical

  filter {
    name   = "name"
    values = ["ubuntu/images/hvm-ssd/ubuntu-22.04-amd64-server-*"]
  }

  filter {
    name   = "virtualization-type"
    values = ["hvm"]
  }
}

# Create VPC (or use existing afterdarksys-vpc)
resource "aws_vpc" "aeims_vpc" {
  count                = var.use_existing_vpc ? 0 : 1
  cidr_block           = var.vpc_cidr
  enable_dns_hostnames = true
  enable_dns_support   = true

  tags = {
    Name        = "aeims-vpc"
    Environment = var.environment
    Project     = "AEIMS"
  }
}

# Use existing VPC if specified
data "aws_vpc" "existing" {
  count = var.use_existing_vpc ? 1 : 0

  filter {
    name   = "tag:Name"
    values = [var.existing_vpc_name]
  }
}

locals {
  vpc_id = var.use_existing_vpc ? data.aws_vpc.existing[0].id : aws_vpc.aeims_vpc[0].id
}

# Internet Gateway
resource "aws_internet_gateway" "aeims_igw" {
  count  = var.use_existing_vpc ? 0 : 1
  vpc_id = local.vpc_id

  tags = {
    Name        = "aeims-igw"
    Environment = var.environment
    Project     = "AEIMS"
  }
}

# Public Subnet
resource "aws_subnet" "aeims_public" {
  count                   = var.use_existing_vpc ? 0 : 1
  vpc_id                  = local.vpc_id
  cidr_block              = var.public_subnet_cidr
  availability_zone       = data.aws_availability_zones.available.names[0]
  map_public_ip_on_launch = true

  tags = {
    Name        = "aeims-public-subnet"
    Environment = var.environment
    Project     = "AEIMS"
  }
}

# Use existing subnet if specified
data "aws_subnet" "existing_public" {
  count = var.use_existing_vpc ? 1 : 0

  filter {
    name   = "vpc-id"
    values = [local.vpc_id]
  }

  filter {
    name   = "tag:Type"
    values = ["public"]
  }
}

locals {
  subnet_id = var.use_existing_vpc ? data.aws_subnet.existing_public[0].id : aws_subnet.aeims_public[0].id
}

# Route Table
resource "aws_route_table" "aeims_public_rt" {
  count  = var.use_existing_vpc ? 0 : 1
  vpc_id = local.vpc_id

  route {
    cidr_block = "0.0.0.0/0"
    gateway_id = aws_internet_gateway.aeims_igw[0].id
  }

  tags = {
    Name        = "aeims-public-rt"
    Environment = var.environment
    Project     = "AEIMS"
  }
}

# Route Table Association
resource "aws_route_table_association" "aeims_public_rta" {
  count          = var.use_existing_vpc ? 0 : 1
  subnet_id      = aws_subnet.aeims_public[0].id
  route_table_id = aws_route_table.aeims_public_rt[0].id
}

# Security Group for AEIMS Web Server
resource "aws_security_group" "aeims_web" {
  name_prefix = "aeims-web-"
  vpc_id      = local.vpc_id

  description = "Security group for AEIMS web server"

  # HTTP
  ingress {
    from_port   = 80
    to_port     = 80
    protocol    = "tcp"
    cidr_blocks = ["0.0.0.0/0"]
    description = "HTTP"
  }

  # HTTPS
  ingress {
    from_port   = 443
    to_port     = 443
    protocol    = "tcp"
    cidr_blocks = ["0.0.0.0/0"]
    description = "HTTPS"
  }

  # SSH (restricted to admin IPs)
  ingress {
    from_port   = 22
    to_port     = 22
    protocol    = "tcp"
    cidr_blocks = var.admin_ips
    description = "SSH for administration"
  }

  # Custom application ports
  ingress {
    from_port   = 8080
    to_port     = 8080
    protocol    = "tcp"
    cidr_blocks = ["0.0.0.0/0"]
    description = "Alternative HTTP port"
  }

  # WebSocket port
  ingress {
    from_port   = 8443
    to_port     = 8443
    protocol    = "tcp"
    cidr_blocks = ["0.0.0.0/0"]
    description = "WebSocket secure port"
  }

  # All outbound traffic
  egress {
    from_port   = 0
    to_port     = 0
    protocol    = "-1"
    cidr_blocks = ["0.0.0.0/0"]
    description = "All outbound traffic"
  }

  tags = {
    Name        = "aeims-web-sg"
    Environment = var.environment
    Project     = "AEIMS"
  }
}

# Key Pair for EC2 access
resource "aws_key_pair" "aeims_key" {
  key_name   = "aeims-key-${var.environment}"
  public_key = var.ssh_public_key

  tags = {
    Name        = "aeims-key"
    Environment = var.environment
    Project     = "AEIMS"
  }
}

# EC2 Instance for AEIMS
resource "aws_instance" "aeims_web" {
  ami                     = data.aws_ami.ubuntu.id
  instance_type          = var.instance_type
  key_name               = aws_key_pair.aeims_key.key_name
  vpc_security_group_ids = [aws_security_group.aeims_web.id]
  subnet_id              = local.subnet_id

  associate_public_ip_address = true

  user_data = templatefile("${path.module}/user_data.sh", {
    domain_name = var.domain_name
    environment = var.environment
  })

  root_block_device {
    volume_type = "gp3"
    volume_size = var.root_volume_size
    encrypted   = true

    tags = {
      Name        = "aeims-root-volume"
      Environment = var.environment
      Project     = "AEIMS"
    }
  }

  tags = {
    Name        = "aeims-web-server"
    Environment = var.environment
    Project     = "AEIMS"
    Domain      = var.domain_name
  }

  lifecycle {
    create_before_destroy = true
  }
}

# Elastic IP for stable public IP
resource "aws_eip" "aeims_web" {
  instance = aws_instance.aeims_web.id
  domain   = "vpc"

  tags = {
    Name        = "aeims-web-eip"
    Environment = var.environment
    Project     = "AEIMS"
  }

  depends_on = [aws_internet_gateway.aeims_igw]
}

# Use existing Route53 zone (created by dns-only.tf)
data "aws_route53_zone" "aeims" {
  name = var.domain_name
}

# Route53 A Record for main domain
resource "aws_route53_record" "aeims_main" {
  zone_id = data.aws_route53_zone.aeims.zone_id
  name    = var.domain_name
  type    = "A"
  ttl     = 300
  records = [aws_eip.aeims_web.public_ip]
}

# Route53 A Record for www subdomain
resource "aws_route53_record" "aeims_www" {
  zone_id = data.aws_route53_zone.aeims.zone_id
  name    = "www.${var.domain_name}"
  type    = "A"
  ttl     = 300
  records = [aws_eip.aeims_web.public_ip]
}

# Route53 A Record for admin subdomain
resource "aws_route53_record" "aeims_admin" {
  zone_id = data.aws_route53_zone.aeims.zone_id
  name    = "admin.${var.domain_name}"
  type    = "A"
  ttl     = 300
  records = [aws_eip.aeims_web.public_ip]
}

# Route53 A Record for support subdomain
resource "aws_route53_record" "aeims_support" {
  zone_id = data.aws_route53_zone.aeims.zone_id
  name    = "support.${var.domain_name}"
  type    = "A"
  ttl     = 300
  records = [aws_eip.aeims_web.public_ip]
}

# Route53 A Record for api subdomain
resource "aws_route53_record" "aeims_api" {
  zone_id = data.aws_route53_zone.aeims.zone_id
  name    = "api.${var.domain_name}"
  type    = "A"
  ttl     = 300
  records = [aws_eip.aeims_web.public_ip]
}

# ACM Certificate for SSL
resource "aws_acm_certificate" "aeims" {
  domain_name               = var.domain_name
  subject_alternative_names = [
    "*.${var.domain_name}",
    "www.${var.domain_name}",
    "admin.${var.domain_name}",
    "support.${var.domain_name}",
    "api.${var.domain_name}"
  ]
  validation_method = "DNS"

  tags = {
    Name        = "aeims-ssl-cert"
    Environment = var.environment
    Project     = "AEIMS"
  }

  lifecycle {
    create_before_destroy = true
  }
}

# Route53 validation records for ACM certificate
resource "aws_route53_record" "aeims_cert_validation" {
  for_each = {
    for dvo in aws_acm_certificate.aeims.domain_validation_options : dvo.domain_name => {
      name   = dvo.resource_record_name
      record = dvo.resource_record_value
      type   = dvo.resource_record_type
    }
  }

  allow_overwrite = true
  name            = each.value.name
  records         = [each.value.record]
  ttl             = 60
  type            = each.value.type
  zone_id         = aws_route53_zone.aeims.zone_id
}

# ACM Certificate validation
resource "aws_acm_certificate_validation" "aeims" {
  certificate_arn         = aws_acm_certificate.aeims.arn
  validation_record_fqdns = [for record in aws_route53_record.aeims_cert_validation : record.fqdn]
}

# IAM Role for EC2 instance
resource "aws_iam_role" "aeims_ec2_role" {
  name = "aeims-ec2-role-${var.environment}"

  assume_role_policy = jsonencode({
    Version = "2012-10-17"
    Statement = [
      {
        Action = "sts:AssumeRole"
        Effect = "Allow"
        Principal = {
          Service = "ec2.amazonaws.com"
        }
      }
    ]
  })

  tags = {
    Name        = "aeims-ec2-role"
    Environment = var.environment
    Project     = "AEIMS"
  }
}

# IAM Policy for S3 and SES access
resource "aws_iam_policy" "aeims_ec2_policy" {
  name        = "aeims-ec2-policy-${var.environment}"
  description = "Policy for AEIMS EC2 instance"

  policy = jsonencode({
    Version = "2012-10-17"
    Statement = [
      {
        Effect = "Allow"
        Action = [
          "s3:GetObject",
          "s3:PutObject",
          "s3:DeleteObject"
        ]
        Resource = [
          "arn:aws:s3:::aeims-backups-${var.environment}/*",
          "arn:aws:s3:::aeims-uploads-${var.environment}/*"
        ]
      },
      {
        Effect = "Allow"
        Action = [
          "ses:SendEmail",
          "ses:SendRawEmail"
        ]
        Resource = "*"
      },
      {
        Effect = "Allow"
        Action = [
          "logs:CreateLogGroup",
          "logs:CreateLogStream",
          "logs:PutLogEvents"
        ]
        Resource = "arn:aws:logs:*:*:*"
      }
    ]
  })

  tags = {
    Name        = "aeims-ec2-policy"
    Environment = var.environment
    Project     = "AEIMS"
  }
}

# Attach policy to role
resource "aws_iam_role_policy_attachment" "aeims_ec2_policy_attachment" {
  role       = aws_iam_role.aeims_ec2_role.name
  policy_arn = aws_iam_policy.aeims_ec2_policy.arn
}

# Instance profile
resource "aws_iam_instance_profile" "aeims_ec2_profile" {
  name = "aeims-ec2-profile-${var.environment}"
  role = aws_iam_role.aeims_ec2_role.name

  tags = {
    Name        = "aeims-ec2-profile"
    Environment = var.environment
    Project     = "AEIMS"
  }
}

# S3 Buckets for backups and uploads
resource "aws_s3_bucket" "aeims_backups" {
  bucket = "aeims-backups-${var.environment}-${random_string.bucket_suffix.result}"

  tags = {
    Name        = "aeims-backups"
    Environment = var.environment
    Project     = "AEIMS"
  }
}

resource "aws_s3_bucket" "aeims_uploads" {
  bucket = "aeims-uploads-${var.environment}-${random_string.bucket_suffix.result}"

  tags = {
    Name        = "aeims-uploads"
    Environment = var.environment
    Project     = "AEIMS"
  }
}

# Random string for unique bucket names
resource "random_string" "bucket_suffix" {
  length  = 8
  special = false
  upper   = false
}

# S3 bucket encryption
resource "aws_s3_bucket_server_side_encryption_configuration" "aeims_backups_encryption" {
  bucket = aws_s3_bucket.aeims_backups.id

  rule {
    apply_server_side_encryption_by_default {
      sse_algorithm = "AES256"
    }
  }
}

resource "aws_s3_bucket_server_side_encryption_configuration" "aeims_uploads_encryption" {
  bucket = aws_s3_bucket.aeims_uploads.id

  rule {
    apply_server_side_encryption_by_default {
      sse_algorithm = "AES256"
    }
  }
}

# S3 bucket versioning
resource "aws_s3_bucket_versioning" "aeims_backups_versioning" {
  bucket = aws_s3_bucket.aeims_backups.id
  versioning_configuration {
    status = "Enabled"
  }
}

# CloudWatch Log Group
resource "aws_cloudwatch_log_group" "aeims_logs" {
  name              = "/aws/ec2/aeims-${var.environment}"
  retention_in_days = 30

  tags = {
    Name        = "aeims-logs"
    Environment = var.environment
    Project     = "AEIMS"
  }
}