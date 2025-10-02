# AEIMS Terraform Variables

variable "aws_region" {
  description = "AWS region for resources"
  type        = string
  default     = "us-east-1"
}

variable "environment" {
  description = "Environment name (prod, staging, dev)"
  type        = string
  default     = "prod"
}

variable "domain_name" {
  description = "Domain name for AEIMS (aeims.app)"
  type        = string
  default     = "aeims.app"
}

variable "instance_type" {
  description = "EC2 instance type"
  type        = string
  default     = "t3.medium"
}

variable "root_volume_size" {
  description = "Size of root volume in GB"
  type        = number
  default     = 20
}

variable "ssh_public_key" {
  description = "SSH public key for EC2 access"
  type        = string
}

variable "admin_ips" {
  description = "List of IP addresses allowed SSH access"
  type        = list(string)
  default     = ["0.0.0.0/0"] # Restrict this in production
}

variable "use_existing_vpc" {
  description = "Whether to use existing VPC (afterdarksys-vpc)"
  type        = bool
  default     = true
}

variable "existing_vpc_name" {
  description = "Name of existing VPC to use"
  type        = string
  default     = "afterdarksys-vpc"
}

variable "vpc_cidr" {
  description = "CIDR block for VPC (if creating new)"
  type        = string
  default     = "10.1.0.0/16"
}

variable "public_subnet_cidr" {
  description = "CIDR block for public subnet (if creating new)"
  type        = string
  default     = "10.1.1.0/24"
}

variable "enable_monitoring" {
  description = "Enable detailed monitoring"
  type        = bool
  default     = true
}

variable "backup_retention_days" {
  description = "Number of days to retain backups"
  type        = number
  default     = 30
}

variable "ssl_certificate_arn" {
  description = "ARN of existing SSL certificate (optional)"
  type        = string
  default     = ""
}

variable "db_password" {
  description = "Database password for MySQL/MariaDB"
  type        = string
  sensitive   = true
  default     = ""
}

variable "notification_email" {
  description = "Email for system notifications"
  type        = string
  default     = "rjc@afterdarksys.com"
}

variable "enable_auto_scaling" {
  description = "Enable auto scaling group"
  type        = bool
  default     = false
}

variable "min_capacity" {
  description = "Minimum number of instances in auto scaling group"
  type        = number
  default     = 1
}

variable "max_capacity" {
  description = "Maximum number of instances in auto scaling group"
  type        = number
  default     = 3
}

variable "desired_capacity" {
  description = "Desired number of instances in auto scaling group"
  type        = number
  default     = 1
}

variable "enable_backup" {
  description = "Enable automated backups"
  type        = bool
  default     = true
}

variable "tags" {
  description = "Additional tags to apply to resources"
  type        = map(string)
  default = {
    Project = "AEIMS"
    Owner   = "AfterDarkSystems"
  }
}

# Security variables
variable "enable_waf" {
  description = "Enable AWS WAF protection"
  type        = bool
  default     = false
}

variable "allowed_countries" {
  description = "List of allowed country codes for WAF geo restriction"
  type        = list(string)
  default     = ["US", "CA", "GB", "AU"]
}

variable "rate_limit_requests_per_5min" {
  description = "Rate limit requests per 5 minutes per IP"
  type        = number
  default     = 2000
}

# Database variables
variable "enable_rds" {
  description = "Enable RDS MySQL instance"
  type        = bool
  default     = false
}

variable "db_instance_class" {
  description = "RDS instance class"
  type        = string
  default     = "db.t3.micro"
}

variable "db_allocated_storage" {
  description = "RDS allocated storage in GB"
  type        = number
  default     = 20
}

variable "db_engine_version" {
  description = "MySQL engine version"
  type        = string
  default     = "8.0"
}

# Redis variables
variable "enable_redis" {
  description = "Enable ElastiCache Redis"
  type        = bool
  default     = false
}

variable "redis_node_type" {
  description = "Redis node type"
  type        = string
  default     = "cache.t3.micro"
}

variable "redis_num_cache_nodes" {
  description = "Number of Redis cache nodes"
  type        = number
  default     = 1
}

# Load balancer variables
variable "enable_load_balancer" {
  description = "Enable Application Load Balancer"
  type        = bool
  default     = false
}

variable "health_check_path" {
  description = "Health check path for load balancer"
  type        = string
  default     = "/health"
}

variable "health_check_interval" {
  description = "Health check interval in seconds"
  type        = number
  default     = 30
}

variable "health_check_timeout" {
  description = "Health check timeout in seconds"
  type        = number
  default     = 5
}

variable "healthy_threshold" {
  description = "Number of consecutive successful health checks"
  type        = number
  default     = 2
}

variable "unhealthy_threshold" {
  description = "Number of consecutive failed health checks"
  type        = number
  default     = 2
}