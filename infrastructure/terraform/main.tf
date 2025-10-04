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
    values = ["ubuntu/images/hvm-ssd/ubuntu-jammy-22.04-amd64-server-*"]
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

# Use existing subnet if specified - pick first public subnet
data "aws_subnet" "existing_public" {
  count = var.use_existing_vpc ? 1 : 0
  vpc_id = local.vpc_id

  filter {
    name   = "tag:Type"
    values = ["public"]
  }

  filter {
    name   = "availability-zone"
    values = ["us-east-1a"]
  }
}

# Get all public subnets for ALB (needs multiple AZs)
data "aws_subnets" "existing_public_all" {
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

  # HTTP from ALB only
  ingress {
    from_port       = 80
    to_port         = 80
    protocol        = "tcp"
    security_groups = [aws_security_group.aeims_alb.id]
    description     = "HTTP from ALB"
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

  associate_public_ip_address = false

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

# Note: ALB manages its own public IPs automatically
# EIPs are not directly attachable to ALBs

# Use existing Route53 zone (created by dns-only.tf) - use the Terraform managed one
data "aws_route53_zone" "aeims" {
  zone_id = "Z10072032GZWNZRSG9VVW"  # The Terraform managed aeims.app hosted zone
}

# Route53 A Records for ALB
resource "aws_route53_record" "aeims_main" {
  zone_id = data.aws_route53_zone.aeims.zone_id
  name    = var.domain_name
  type    = "A"

  alias {
    name                   = aws_lb.aeims_alb.dns_name
    zone_id                = aws_lb.aeims_alb.zone_id
    evaluate_target_health = true
  }
}

resource "aws_route53_record" "aeims_www" {
  zone_id = data.aws_route53_zone.aeims.zone_id
  name    = "www.${var.domain_name}"
  type    = "A"

  alias {
    name                   = aws_lb.aeims_alb.dns_name
    zone_id                = aws_lb.aeims_alb.zone_id
    evaluate_target_health = true
  }
}

resource "aws_route53_record" "aeims_admin" {
  zone_id = data.aws_route53_zone.aeims.zone_id
  name    = "admin.${var.domain_name}"
  type    = "A"

  alias {
    name                   = aws_lb.aeims_alb.dns_name
    zone_id                = aws_lb.aeims_alb.zone_id
    evaluate_target_health = true
  }
}

resource "aws_route53_record" "aeims_support" {
  zone_id = data.aws_route53_zone.aeims.zone_id
  name    = "support.${var.domain_name}"
  type    = "A"

  alias {
    name                   = aws_lb.aeims_alb.dns_name
    zone_id                = aws_lb.aeims_alb.zone_id
    evaluate_target_health = true
  }
}

resource "aws_route53_record" "aeims_api" {
  zone_id = data.aws_route53_zone.aeims.zone_id
  name    = "api.${var.domain_name}"
  type    = "A"

  alias {
    name                   = aws_lb.aeims_alb.dns_name
    zone_id                = aws_lb.aeims_alb.zone_id
    evaluate_target_health = true
  }
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
  zone_id         = data.aws_route53_zone.aeims.zone_id
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

# Security Group for ALB
resource "aws_security_group" "aeims_alb" {
  name_prefix = "aeims-alb-"
  vpc_id      = local.vpc_id

  description = "Security group for AEIMS Application Load Balancer"

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

  # All outbound traffic
  egress {
    from_port   = 0
    to_port     = 0
    protocol    = "-1"
    cidr_blocks = ["0.0.0.0/0"]
    description = "All outbound traffic"
  }

  tags = {
    Name        = "aeims-alb-sg"
    Environment = var.environment
    Project     = "AEIMS"
  }
}

# Get public subnets for ALB (requires at least 2 AZs)
data "aws_subnets" "alb_subnets" {
  filter {
    name   = "vpc-id"
    values = [local.vpc_id]
  }
  filter {
    name   = "tag:Type"
    values = ["public"]
  }
}

# Application Load Balancer
resource "aws_lb" "aeims_alb" {
  name               = "aeims-alb-${var.environment}"
  internal           = false
  load_balancer_type = "application"
  security_groups    = [aws_security_group.aeims_alb.id]
  subnets            = data.aws_subnets.alb_subnets.ids

  enable_deletion_protection = false

  tags = {
    Name        = "aeims-alb"
    Environment = var.environment
    Project     = "AEIMS"
  }
}

# Target Group for EC2 instances (keep existing)
resource "aws_lb_target_group" "aeims_web" {
  name     = "aeims-web-tg-${var.environment}"
  port     = 80
  protocol = "HTTP"
  vpc_id   = local.vpc_id

  health_check {
    enabled             = true
    healthy_threshold   = var.healthy_threshold
    interval            = var.health_check_interval
    matcher             = "200"
    path                = var.health_check_path
    port                = "traffic-port"
    protocol            = "HTTP"
    timeout             = var.health_check_timeout
    unhealthy_threshold = var.unhealthy_threshold
  }

  tags = {
    Name        = "aeims-web-tg"
    Environment = var.environment
    Project     = "AEIMS"
  }
}

# Target Group for ECS containers (new separate one)
resource "aws_lb_target_group" "aeims_ecs" {
  name        = "aeims-ecs-tg-${var.environment}"
  port        = 80
  protocol    = "HTTP"
  target_type = "ip"
  vpc_id      = local.vpc_id

  health_check {
    enabled             = true
    healthy_threshold   = var.healthy_threshold
    interval            = var.health_check_interval
    matcher             = "200"
    path                = "/"
    port                = "traffic-port"
    protocol            = "HTTP"
    timeout             = var.health_check_timeout
    unhealthy_threshold = var.unhealthy_threshold
  }

  tags = {
    Name        = "aeims-ecs-tg"
    Environment = var.environment
    Project     = "AEIMS"
  }
}

# Listener rule to route traffic to ECS target group
resource "aws_lb_listener_rule" "aeims_ecs" {
  listener_arn = aws_lb_listener.aeims_https.arn
  priority     = 100

  action {
    type             = "forward"
    target_group_arn = aws_lb_target_group.aeims_ecs.arn
  }

  condition {
    path_pattern {
      values = ["*"]
    }
  }

  tags = {
    Name        = "aeims-ecs-rule"
    Environment = var.environment
    Project     = "AEIMS"
  }
}

# Target Group Attachment - Removed for ECS service
# resource "aws_lb_target_group_attachment" "aeims_web" {
#   target_group_arn = aws_lb_target_group.aeims_web.arn
#   target_id        = aws_instance.aeims_web.id
#   port             = 80
# }

# HTTP Listener (redirect to HTTPS)
resource "aws_lb_listener" "aeims_http" {
  load_balancer_arn = aws_lb.aeims_alb.arn
  port              = "80"
  protocol          = "HTTP"

  default_action {
    type = "redirect"

    redirect {
      port        = "443"
      protocol    = "HTTPS"
      status_code = "HTTP_301"
    }
  }
}

# HTTPS Listener with SNI support
resource "aws_lb_listener" "aeims_https" {
  load_balancer_arn = aws_lb.aeims_alb.arn
  port              = "443"
  protocol          = "HTTPS"
  ssl_policy        = "ELBSecurityPolicy-TLS-1-2-2017-01"
  certificate_arn   = aws_acm_certificate_validation.aeims.certificate_arn

  default_action {
    type             = "forward"
    target_group_arn = aws_lb_target_group.aeims_ecs.arn
  }
}

# Additional SSL certificates for SNI (wildcard and subdomains)
resource "aws_lb_listener_certificate" "aeims_wildcard" {
  listener_arn    = aws_lb_listener.aeims_https.arn
  certificate_arn = aws_acm_certificate_validation.aeims.certificate_arn
}

# ACM Certificate for flirts.nyc and nycflirts.com
resource "aws_acm_certificate" "flirts_nyc" {
  domain_name               = "flirts.nyc"
  subject_alternative_names = [
    "*.flirts.nyc",
    "www.flirts.nyc",
    "nycflirts.com",
    "*.nycflirts.com",
    "www.nycflirts.com"
  ]
  validation_method = "DNS"
  tags = {
    Name        = "flirts-domains-ssl-cert"
    Environment = var.environment
    Project     = "AEIMS"
  }
  lifecycle {
    create_before_destroy = true
  }
}

# Route53 hosted zone for flirts.nyc
resource "aws_route53_zone" "flirts_nyc" {
  name = "flirts.nyc"
  tags = {
    Name        = "flirts-nyc-zone"
    Environment = var.environment
    Project     = "AEIMS"
  }
}

# Route53 hosted zone for nycflirts.com
resource "aws_route53_zone" "nycflirts_com" {
  name = "nycflirts.com"
  tags = {
    Name        = "nycflirts-com-zone"
    Environment = var.environment
    Project     = "AEIMS"
  }
}

# Route53 validation records for flirts.nyc ACM certificate
resource "aws_route53_record" "flirts_nyc_cert_validation" {
  for_each = {
    for dvo in aws_acm_certificate.flirts_nyc.domain_validation_options : dvo.domain_name => {
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
  # Route to correct zone based on domain
  zone_id = strcontains(each.key, "nycflirts.com") ? aws_route53_zone.nycflirts_com.zone_id : aws_route53_zone.flirts_nyc.zone_id
}

# ACM Certificate validation for flirts.nyc
resource "aws_acm_certificate_validation" "flirts_nyc" {
  certificate_arn         = aws_acm_certificate.flirts_nyc.arn
  validation_record_fqdns = [for record in aws_route53_record.flirts_nyc_cert_validation : record.fqdn]
}

# Route53 A record for flirts.nyc pointing to ALB
resource "aws_route53_record" "flirts_nyc" {
  zone_id = aws_route53_zone.flirts_nyc.zone_id
  name    = "flirts.nyc"
  type    = "A"

  alias {
    name                   = aws_lb.aeims_alb.dns_name
    zone_id                = aws_lb.aeims_alb.zone_id
    evaluate_target_health = true
  }
}

# Route53 A record for www.flirts.nyc
resource "aws_route53_record" "flirts_nyc_www" {
  zone_id = aws_route53_zone.flirts_nyc.zone_id
  name    = "www.flirts.nyc"
  type    = "A"

  alias {
    name                   = aws_lb.aeims_alb.dns_name
    zone_id                = aws_lb.aeims_alb.zone_id
    evaluate_target_health = true
  }
}

# Route53 A record for nycflirts.com pointing to ALB
resource "aws_route53_record" "nycflirts_com" {
  zone_id = aws_route53_zone.nycflirts_com.zone_id
  name    = "nycflirts.com"
  type    = "A"

  alias {
    name                   = aws_lb.aeims_alb.dns_name
    zone_id                = aws_lb.aeims_alb.zone_id
    evaluate_target_health = true
  }
}

# Route53 A record for www.nycflirts.com
resource "aws_route53_record" "nycflirts_com_www" {
  zone_id = aws_route53_zone.nycflirts_com.zone_id
  name    = "www.nycflirts.com"
  type    = "A"

  alias {
    name                   = aws_lb.aeims_alb.dns_name
    zone_id                = aws_lb.aeims_alb.zone_id
    evaluate_target_health = true
  }
}

# Add flirts.nyc certificate to ALB listener for SNI
resource "aws_lb_listener_certificate" "flirts_nyc" {
  listener_arn    = aws_lb_listener.aeims_https.arn
  certificate_arn = aws_acm_certificate_validation.flirts_nyc.certificate_arn
}

# ALB Listener Rule for flirts.nyc and nycflirts.com
resource "aws_lb_listener_rule" "flirts_nyc" {
  listener_arn = aws_lb_listener.aeims_https.arn
  priority     = 50

  action {
    type             = "forward"
    target_group_arn = aws_lb_target_group.aeims_ecs.arn
  }

  condition {
    host_header {
      values = [
        "flirts.nyc",
        "www.flirts.nyc",
        "nycflirts.com",
        "www.nycflirts.com"
      ]
    }
  }

  tags = {
    Name        = "flirts-domains-rule"
    Environment = var.environment
    Project     = "AEIMS"
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

# ECS Cluster
resource "aws_ecs_cluster" "aeims_cluster" {
  name = "aeims-cluster"

  tags = {
    Name        = "aeims-cluster"
    Environment = var.environment
    Project     = "AEIMS"
  }
}

# ECS Task Definition
resource "aws_ecs_task_definition" "aeims_app" {
  family                   = "aeims-app"
  network_mode             = "awsvpc"
  requires_compatibilities = ["FARGATE"]
  cpu                      = "256"
  memory                   = "512"
  execution_role_arn       = aws_iam_role.aeims_ecs_execution_role.arn

  container_definitions = jsonencode([
    {
      name      = "aeims"
      image     = "515966511618.dkr.ecr.us-east-1.amazonaws.com/afterdarksys/aeims:1759390579"
      essential = true
      portMappings = [
        {
          containerPort = 80
          protocol      = "tcp"
        }
      ]
      logConfiguration = {
        logDriver = "awslogs"
        options = {
          "awslogs-group"         = aws_cloudwatch_log_group.aeims_ecs_logs.name
          "awslogs-region"        = var.aws_region
          "awslogs-stream-prefix" = "ecs"
        }
      }
      environment = [
        {
          name  = "DOMAIN_NAME"
          value = var.domain_name
        }
      ]
    }
  ])

  tags = {
    Name        = "aeims-app"
    Environment = var.environment
    Project     = "AEIMS"
  }
}

# ECS Service
resource "aws_ecs_service" "aeims_service" {
  name            = "aeims-service"
  cluster         = aws_ecs_cluster.aeims_cluster.id
  task_definition = aws_ecs_task_definition.aeims_app.arn
  desired_count   = 1
  launch_type     = "FARGATE"

  network_configuration {
    subnets          = [data.aws_subnet.existing_public[0].id]
    security_groups  = [aws_security_group.aeims_ecs.id]
    assign_public_ip = true
  }

  load_balancer {
    target_group_arn = aws_lb_target_group.aeims_ecs.arn
    container_name   = "aeims"
    container_port   = 80
  }

  depends_on = [aws_lb_listener.aeims_https, aws_lb_listener_rule.aeims_ecs]

  tags = {
    Name        = "aeims-service"
    Environment = var.environment
    Project     = "AEIMS"
  }
}

# ECS Security Group
resource "aws_security_group" "aeims_ecs" {
  name_prefix = "aeims-ecs-"
  vpc_id      = local.vpc_id

  ingress {
    from_port       = 80
    to_port         = 80
    protocol        = "tcp"
    security_groups = [aws_security_group.aeims_alb.id]
  }

  egress {
    from_port   = 0
    to_port     = 0
    protocol    = "-1"
    cidr_blocks = ["0.0.0.0/0"]
  }

  tags = {
    Name        = "aeims-ecs-sg"
    Environment = var.environment
    Project     = "AEIMS"
  }
}

# ECS Execution Role
resource "aws_iam_role" "aeims_ecs_execution_role" {
  name = "aeims-ecs-execution-role-${var.environment}"

  assume_role_policy = jsonencode({
    Version = "2012-10-17"
    Statement = [
      {
        Action = "sts:AssumeRole"
        Effect = "Allow"
        Principal = {
          Service = "ecs-tasks.amazonaws.com"
        }
      }
    ]
  })

  tags = {
    Name        = "aeims-ecs-execution-role"
    Environment = var.environment
    Project     = "AEIMS"
  }
}

# Attach ECS execution role policy
resource "aws_iam_role_policy_attachment" "aeims_ecs_execution_role_policy" {
  role       = aws_iam_role.aeims_ecs_execution_role.name
  policy_arn = "arn:aws:iam::aws:policy/service-role/AmazonECSTaskExecutionRolePolicy"
}

# ECS Log Group
resource "aws_cloudwatch_log_group" "aeims_ecs_logs" {
  name              = "/ecs/aeims-app"
  retention_in_days = 30

  tags = {
    Name        = "aeims-ecs-logs"
    Environment = var.environment
    Project     = "AEIMS"
  }
}