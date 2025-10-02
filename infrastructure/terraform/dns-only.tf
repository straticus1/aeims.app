# Simple DNS-only configuration to get nameservers quickly
terraform {
  required_version = ">= 1.0"
  required_providers {
    aws = {
      source  = "hashicorp/aws"
      version = "~> 5.0"
    }
    random = {
      source  = "hashicorp/random"
      version = "~> 3.1"
    }
  }
}

provider "aws" {
  region = "us-east-1"
}

# Route53 Hosted Zone for aeims.app
resource "aws_route53_zone" "aeims" {
  name = "aeims.app"

  tags = {
    Name        = "aeims-hosted-zone"
    Environment = "prod"
    Project     = "AEIMS"
  }
}

# Output the nameservers
output "nameservers" {
  description = "Route53 nameservers for aeims.app"
  value       = aws_route53_zone.aeims.name_servers
}