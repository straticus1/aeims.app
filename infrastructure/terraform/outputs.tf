# AEIMS Terraform Outputs

output "instance_id" {
  description = "ID of the EC2 instance"
  value       = aws_instance.aeims_web.id
}

output "instance_public_ip" {
  description = "Public IP address of the EC2 instance"
  value       = aws_eip.aeims_web.public_ip
}

output "instance_private_ip" {
  description = "Private IP address of the EC2 instance"
  value       = aws_instance.aeims_web.private_ip
}

output "instance_public_dns" {
  description = "Public DNS name of the EC2 instance"
  value       = aws_instance.aeims_web.public_dns
}

output "domain_name" {
  description = "Domain name for AEIMS"
  value       = var.domain_name
}

output "name_servers" {
  description = "Route53 name servers for domain delegation"
  value       = aws_route53_zone.aeims.name_servers
}

output "hosted_zone_id" {
  description = "Route53 hosted zone ID"
  value       = aws_route53_zone.aeims.zone_id
}

output "ssl_certificate_arn" {
  description = "ARN of the SSL certificate"
  value       = aws_acm_certificate.aeims.arn
}

output "ssl_certificate_status" {
  description = "Status of SSL certificate validation"
  value       = aws_acm_certificate_validation.aeims.certificate_arn != "" ? "ISSUED" : "PENDING"
}

output "vpc_id" {
  description = "ID of the VPC"
  value       = local.vpc_id
}

output "subnet_id" {
  description = "ID of the public subnet"
  value       = local.subnet_id
}

output "security_group_id" {
  description = "ID of the web security group"
  value       = aws_security_group.aeims_web.id
}

output "backup_bucket_name" {
  description = "Name of the S3 backup bucket"
  value       = aws_s3_bucket.aeims_backups.bucket
}

output "uploads_bucket_name" {
  description = "Name of the S3 uploads bucket"
  value       = aws_s3_bucket.aeims_uploads.bucket
}

output "iam_role_arn" {
  description = "ARN of the IAM role for EC2"
  value       = aws_iam_role.aeims_ec2_role.arn
}

output "log_group_name" {
  description = "Name of the CloudWatch log group"
  value       = aws_cloudwatch_log_group.aeims_logs.name
}

output "ssh_command" {
  description = "SSH command to connect to the instance"
  value       = "ssh -i ~/.ssh/aeims-key ubuntu@${aws_eip.aeims_web.public_ip}"
}

output "website_urls" {
  description = "URLs for accessing the AEIMS website"
  value = {
    main     = "https://${var.domain_name}"
    www      = "https://www.${var.domain_name}"
    admin    = "https://admin.${var.domain_name}"
    support  = "https://support.${var.domain_name}"
    api      = "https://api.${var.domain_name}"
  }
}

output "dns_records" {
  description = "DNS records created"
  value = {
    main     = "${var.domain_name} -> ${aws_eip.aeims_web.public_ip}"
    www      = "www.${var.domain_name} -> ${aws_eip.aeims_web.public_ip}"
    admin    = "admin.${var.domain_name} -> ${aws_eip.aeims_web.public_ip}"
    support  = "support.${var.domain_name} -> ${aws_eip.aeims_web.public_ip}"
    api      = "api.${var.domain_name} -> ${aws_eip.aeims_web.public_ip}"
  }
}

output "infrastructure_summary" {
  description = "Summary of created infrastructure"
  value = {
    instance_type    = var.instance_type
    region          = var.aws_region
    environment     = var.environment
    monitoring      = var.enable_monitoring
    backup_enabled  = var.enable_backup
    ssl_enabled     = true
    domains_count   = 5
  }
}

# Security outputs
output "security_group_rules" {
  description = "Security group rules summary"
  value = {
    inbound_ports = ["22 (SSH)", "80 (HTTP)", "443 (HTTPS)", "8080 (Alt HTTP)", "8443 (WebSocket)"]
    ssh_access    = "Restricted to admin IPs"
    web_access    = "Public (ports 80, 443)"
  }
}

# Backup and monitoring outputs
output "backup_configuration" {
  description = "Backup configuration details"
  value = {
    backup_bucket     = aws_s3_bucket.aeims_backups.bucket
    retention_days    = var.backup_retention_days
    encryption       = "AES256"
    versioning       = "Enabled"
  }
}

output "monitoring_configuration" {
  description = "Monitoring configuration details"
  value = {
    log_group         = aws_cloudwatch_log_group.aeims_logs.name
    log_retention     = "${aws_cloudwatch_log_group.aeims_logs.retention_in_days} days"
    detailed_monitoring = var.enable_monitoring
  }
}

# Quick setup commands
output "quick_setup_commands" {
  description = "Commands to quickly set up the environment"
  value = [
    "# Connect to instance:",
    "ssh -i ~/.ssh/aeims-key ubuntu@${aws_eip.aeims_web.public_ip}",
    "",
    "# Check website status:",
    "curl -I https://${var.domain_name}",
    "",
    "# View logs:",
    "aws logs tail ${aws_cloudwatch_log_group.aeims_logs.name} --follow",
    "",
    "# Update DNS nameservers with these values:",
    join(", ", aws_route53_zone.aeims.name_servers)
  ]
}

# Domain delegation instructions
output "domain_delegation_instructions" {
  description = "Instructions for domain delegation"
  value = {
    message = "Update your domain registrar's nameservers to:"
    nameservers = aws_route53_zone.aeims.name_servers
    note = "DNS propagation may take 24-48 hours. You can check progress with: dig NS ${var.domain_name}"
  }
}