# Multi-Site AEIMS Infrastructure
# Terraform configuration for deploying site-specific login subdomains
# and routing for the AEIMS platform

terraform {
  required_version = ">= 1.0"
  required_providers {
    aws = {
      source  = "hashicorp/aws"
      version = "~> 5.0"
    }
    cloudflare = {
      source  = "cloudflare/cloudflare"
      version = "~> 4.0"
    }
  }
}

# Variables
variable "environment" {
  description = "Environment name (dev, staging, prod)"
  type        = string
  default     = "prod"
}

variable "domain_name" {
  description = "Base domain name for AEIMS"
  type        = string
  default     = "aeims.app"
}

variable "cloudflare_zone_id" {
  description = "Cloudflare Zone ID for the domain"
  type        = string
}

variable "sites" {
  description = "List of sites to configure"
  type = list(object({
    domain = string
    theme  = string
    enabled = bool
  }))
  default = [
    {
      domain  = "nycflirts.com"
      theme   = "nyc"
      enabled = true
    },
    {
      domain  = "flirts.nyc"
      theme   = "nyc"
      enabled = true
    }
  ]
}

# Data sources
data "aws_caller_identity" "current" {}
data "aws_region" "current" {}

# Get existing sites from filesystem
data "external" "discover_sites" {
  program = ["python3", "-c", <<EOT
import json
import os
import sys

sites_path = "${path.root}/../../../aeims/sites"
sites = []

if os.path.exists(sites_path):
    for item in os.listdir(sites_path):
        if item not in ['.', '..', '_archived'] and os.path.isdir(os.path.join(sites_path, item)):
            sites.append({
                'domain': item,
                'theme': 'default',
                'enabled': True
            })

print(json.dumps({'sites': json.dumps(sites)}))
EOT
  ]
}

locals {
  discovered_sites = jsondecode(data.external.discover_sites.result.sites)
  all_sites = concat(var.sites, local.discovered_sites)

  # Remove duplicates
  unique_sites = { for site in local.all_sites : site.domain => site }
}

# ALB for load balancing across AEIMS instances
resource "aws_lb" "aeims_alb" {
  name               = "aeims-${var.environment}-alb"
  internal           = false
  load_balancer_type = "application"
  security_groups    = [aws_security_group.alb_sg.id]
  subnets           = [aws_subnet.public_a.id, aws_subnet.public_b.id]

  enable_deletion_protection = var.environment == "prod"

  tags = {
    Name = "aeims-${var.environment}-alb"
    Environment = var.environment
  }
}

# Target group for AEIMS.app
resource "aws_lb_target_group" "aeims_app_tg" {
  name     = "aeims-app-${var.environment}"
  port     = 80
  protocol = "HTTP"
  vpc_id   = aws_vpc.aeims_vpc.id

  health_check {
    enabled             = true
    healthy_threshold   = 2
    interval            = 30
    matcher             = "200"
    path                = "/health.php"
    port                = "traffic-port"
    protocol            = "HTTP"
    timeout             = 5
    unhealthy_threshold = 2
  }

  tags = {
    Name = "aeims-app-${var.environment}"
  }
}

# Target group for telephony platform
resource "aws_lb_target_group" "telephony_tg" {
  name     = "telephony-${var.environment}"
  port     = 3000
  protocol = "HTTP"
  vpc_id   = aws_vpc.aeims_vpc.id

  health_check {
    enabled             = true
    healthy_threshold   = 2
    interval            = 30
    matcher             = "200"
    path                = "/health"
    port                = "traffic-port"
    protocol            = "HTTP"
    timeout             = 5
    unhealthy_threshold = 2
  }

  tags = {
    Name = "telephony-${var.environment}"
  }
}

# ALB Listeners
resource "aws_lb_listener" "aeims_https" {
  load_balancer_arn = aws_lb.aeims_alb.arn
  port              = "443"
  protocol          = "HTTPS"
  ssl_policy        = "ELBSecurityPolicy-TLS-1-2-2017-01"
  certificate_arn   = aws_acm_certificate.aeims_cert.arn

  default_action {
    type             = "forward"
    target_group_arn = aws_lb_target_group.aeims_app_tg.arn
  }
}

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

# Listener rules for site-specific routing
resource "aws_lb_listener_rule" "site_login_routing" {
  for_each = local.unique_sites

  listener_arn = aws_lb_listener.aeims_https.arn
  priority     = 100 + index(keys(local.unique_sites), each.key)

  action {
    type             = "forward"
    target_group_arn = aws_lb_target_group.aeims_app_tg.arn
  }

  condition {
    host_header {
      values = ["login.${each.value.domain}"]
    }
  }

  tags = {
    Name = "login-${each.value.domain}"
    Site = each.value.domain
  }
}

# Telephony platform routing
resource "aws_lb_listener_rule" "telephony_routing" {
  listener_arn = aws_lb_listener.aeims_https.arn
  priority     = 50

  action {
    type             = "forward"
    target_group_arn = aws_lb_target_group.telephony_tg.arn
  }

  condition {
    host_header {
      values = ["operator.${var.domain_name}"]
    }
  }

  condition {
    path_pattern {
      values = ["/operator/*", "/dashboard*", "/api/*"]
    }
  }
}

# VPC Configuration
resource "aws_vpc" "aeims_vpc" {
  cidr_block           = "10.0.0.0/16"
  enable_dns_hostnames = true
  enable_dns_support   = true

  tags = {
    Name = "aeims-${var.environment}-vpc"
    Environment = var.environment
  }
}

resource "aws_subnet" "public_a" {
  vpc_id                  = aws_vpc.aeims_vpc.id
  cidr_block              = "10.0.1.0/24"
  availability_zone       = "${data.aws_region.current.name}a"
  map_public_ip_on_launch = true

  tags = {
    Name = "aeims-${var.environment}-public-a"
  }
}

resource "aws_subnet" "public_b" {
  vpc_id                  = aws_vpc.aeims_vpc.id
  cidr_block              = "10.0.2.0/24"
  availability_zone       = "${data.aws_region.current.name}b"
  map_public_ip_on_launch = true

  tags = {
    Name = "aeims-${var.environment}-public-b"
  }
}

resource "aws_internet_gateway" "aeims_igw" {
  vpc_id = aws_vpc.aeims_vpc.id

  tags = {
    Name = "aeims-${var.environment}-igw"
  }
}

resource "aws_route_table" "public_rt" {
  vpc_id = aws_vpc.aeims_vpc.id

  route {
    cidr_block = "0.0.0.0/0"
    gateway_id = aws_internet_gateway.aeims_igw.id
  }

  tags = {
    Name = "aeims-${var.environment}-public-rt"
  }
}

resource "aws_route_table_association" "public_a" {
  subnet_id      = aws_subnet.public_a.id
  route_table_id = aws_route_table.public_rt.id
}

resource "aws_route_table_association" "public_b" {
  subnet_id      = aws_subnet.public_b.id
  route_table_id = aws_route_table.public_rt.id
}

# Security Groups
resource "aws_security_group" "alb_sg" {
  name_prefix = "aeims-alb-${var.environment}"
  vpc_id      = aws_vpc.aeims_vpc.id

  ingress {
    from_port   = 80
    to_port     = 80
    protocol    = "tcp"
    cidr_blocks = ["0.0.0.0/0"]
  }

  ingress {
    from_port   = 443
    to_port     = 443
    protocol    = "tcp"
    cidr_blocks = ["0.0.0.0/0"]
  }

  egress {
    from_port   = 0
    to_port     = 0
    protocol    = "-1"
    cidr_blocks = ["0.0.0.0/0"]
  }

  tags = {
    Name = "aeims-alb-${var.environment}-sg"
  }
}

resource "aws_security_group" "aeims_app_sg" {
  name_prefix = "aeims-app-${var.environment}"
  vpc_id      = aws_vpc.aeims_vpc.id

  ingress {
    from_port       = 80
    to_port         = 80
    protocol        = "tcp"
    security_groups = [aws_security_group.alb_sg.id]
  }

  ingress {
    from_port       = 3000
    to_port         = 3000
    protocol        = "tcp"
    security_groups = [aws_security_group.alb_sg.id]
  }

  ingress {
    from_port   = 22
    to_port     = 22
    protocol    = "tcp"
    cidr_blocks = ["10.0.0.0/16"]
  }

  egress {
    from_port   = 0
    to_port     = 0
    protocol    = "-1"
    cidr_blocks = ["0.0.0.0/0"]
  }

  tags = {
    Name = "aeims-app-${var.environment}-sg"
  }
}

# SSL Certificate
resource "aws_acm_certificate" "aeims_cert" {
  domain_name = var.domain_name
  subject_alternative_names = concat(
    [
      "*.${var.domain_name}",
      "operator.${var.domain_name}"
    ],
    [for site in values(local.unique_sites) : "login.${site.domain}"]
  )

  validation_method = "DNS"

  lifecycle {
    create_before_destroy = true
  }

  tags = {
    Name = "aeims-${var.environment}-cert"
  }
}

# Cloudflare DNS Records
resource "cloudflare_record" "aeims_root" {
  zone_id = var.cloudflare_zone_id
  name    = "@"
  value   = aws_lb.aeims_alb.dns_name
  type    = "CNAME"
  ttl     = 1
  proxied = true

  comment = "AEIMS root domain"
}

resource "cloudflare_record" "aeims_www" {
  zone_id = var.cloudflare_zone_id
  name    = "www"
  value   = aws_lb.aeims_alb.dns_name
  type    = "CNAME"
  ttl     = 1
  proxied = true

  comment = "AEIMS www subdomain"
}

resource "cloudflare_record" "operator_subdomain" {
  zone_id = var.cloudflare_zone_id
  name    = "operator"
  value   = aws_lb.aeims_alb.dns_name
  type    = "CNAME"
  ttl     = 1
  proxied = true

  comment = "AEIMS operator dashboard"
}

# Site-specific login subdomains
resource "cloudflare_record" "site_login_subdomains" {
  for_each = local.unique_sites

  zone_id = var.cloudflare_zone_id
  name    = "login.${each.value.domain}"
  value   = aws_lb.aeims_alb.dns_name
  type    = "CNAME"
  ttl     = 1
  proxied = true

  comment = "Site-specific login for ${each.value.domain}"
}

# ECS Cluster for containerized deployment
resource "aws_ecs_cluster" "aeims_cluster" {
  name = "aeims-${var.environment}"

  setting {
    name  = "containerInsights"
    value = "enabled"
  }

  tags = {
    Name = "aeims-${var.environment}"
  }
}

# ECS Task Definition for AEIMS.app
resource "aws_ecs_task_definition" "aeims_app_task" {
  family                   = "aeims-app-${var.environment}"
  requires_compatibilities = ["FARGATE"]
  network_mode             = "awsvpc"
  cpu                      = "512"
  memory                   = "1024"
  execution_role_arn       = aws_iam_role.ecs_execution_role.arn
  task_role_arn           = aws_iam_role.ecs_task_role.arn

  container_definitions = jsonencode([
    {
      name  = "aeims-app"
      image = "${aws_ecr_repository.aeims_app.repository_url}:latest"

      portMappings = [
        {
          containerPort = 80
          hostPort      = 80
          protocol      = "tcp"
        }
      ]

      environment = [
        {
          name  = "ENVIRONMENT"
          value = var.environment
        },
        {
          name  = "SITES_CONFIG"
          value = jsonencode(local.unique_sites)
        }
      ]

      logConfiguration = {
        logDriver = "awslogs"
        options = {
          awslogs-group         = aws_cloudwatch_log_group.aeims_app.name
          awslogs-region        = data.aws_region.current.name
          awslogs-stream-prefix = "ecs"
        }
      }

      healthCheck = {
        command = ["CMD-SHELL", "curl -f http://localhost/health.php || exit 1"]
        interval = 30
        timeout = 5
        retries = 3
        startPeriod = 60
      }
    }
  ])

  tags = {
    Name = "aeims-app-${var.environment}"
  }
}

# ECS Service
resource "aws_ecs_service" "aeims_app_service" {
  name            = "aeims-app-${var.environment}"
  cluster         = aws_ecs_cluster.aeims_cluster.id
  task_definition = aws_ecs_task_definition.aeims_app_task.arn
  desired_count   = 2
  launch_type     = "FARGATE"

  network_configuration {
    subnets          = [aws_subnet.public_a.id, aws_subnet.public_b.id]
    security_groups  = [aws_security_group.aeims_app_sg.id]
    assign_public_ip = true
  }

  load_balancer {
    target_group_arn = aws_lb_target_group.aeims_app_tg.arn
    container_name   = "aeims-app"
    container_port   = 80
  }

  depends_on = [
    aws_lb_listener.aeims_https,
    aws_iam_role_policy_attachment.ecs_execution_role_policy
  ]

  tags = {
    Name = "aeims-app-${var.environment}"
  }
}

# ECR Repository
resource "aws_ecr_repository" "aeims_app" {
  name                 = "aeims-app-${var.environment}"
  image_tag_mutability = "MUTABLE"

  image_scanning_configuration {
    scan_on_push = true
  }

  tags = {
    Name = "aeims-app-${var.environment}"
  }
}

# CloudWatch Log Group
resource "aws_cloudwatch_log_group" "aeims_app" {
  name              = "/ecs/aeims-app-${var.environment}"
  retention_in_days = 30

  tags = {
    Name = "aeims-app-${var.environment}"
  }
}

# IAM Roles
resource "aws_iam_role" "ecs_execution_role" {
  name = "aeims-ecs-execution-${var.environment}"

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
}

resource "aws_iam_role" "ecs_task_role" {
  name = "aeims-ecs-task-${var.environment}"

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
}

resource "aws_iam_role_policy_attachment" "ecs_execution_role_policy" {
  role       = aws_iam_role.ecs_execution_role.name
  policy_arn = "arn:aws:iam::aws:policy/service-role/AmazonECSTaskExecutionRolePolicy"
}

# Outputs
output "alb_dns_name" {
  description = "DNS name of the load balancer"
  value       = aws_lb.aeims_alb.dns_name
}

output "ecr_repository_url" {
  description = "URL of the ECR repository"
  value       = aws_ecr_repository.aeims_app.repository_url
}

output "site_login_urls" {
  description = "Site-specific login URLs"
  value = {
    for site in values(local.unique_sites) : site.domain => "https://login.${site.domain}"
  }
}

output "discovered_sites" {
  description = "Sites discovered from filesystem"
  value = local.discovered_sites
}