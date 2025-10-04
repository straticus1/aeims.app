# SES Bounce Processing Integration for AEIMS
# Add this to your main Terraform configuration

# DynamoDB Tables for SES Bounce Management
resource "aws_dynamodb_table" "ses_bounces" {
  name           = "${var.project_name}-ses-bounces-${var.environment}"
  billing_mode   = "PAY_PER_REQUEST"
  hash_key       = "emailAddress"
  range_key      = "timestamp"

  attribute {
    name = "emailAddress"
    type = "S"
  }

  attribute {
    name = "timestamp"
    type = "S"
  }

  tags = {
    Name        = "${var.project_name}-ses-bounces"
    Environment = var.environment
    Purpose     = "SES bounce tracking"
  }
}

resource "aws_dynamodb_table" "ses_complaints" {
  name           = "${var.project_name}-ses-complaints-${var.environment}"
  billing_mode   = "PAY_PER_REQUEST"
  hash_key       = "emailAddress"
  range_key      = "timestamp"

  attribute {
    name = "emailAddress"
    type = "S"
  }

  attribute {
    name = "timestamp"
    type = "S"
  }

  tags = {
    Name        = "${var.project_name}-ses-complaints"
    Environment = var.environment
    Purpose     = "SES complaint tracking"
  }
}

resource "aws_dynamodb_table" "ses_suppression_list" {
  name           = "${var.project_name}-ses-suppression-list-${var.environment}"
  billing_mode   = "PAY_PER_REQUEST"
  hash_key       = "emailAddress"

  attribute {
    name = "emailAddress"
    type = "S"
  }

  tags = {
    Name        = "${var.project_name}-ses-suppression-list"
    Environment = var.environment
    Purpose     = "SES suppression list"
  }
}

# SNS Topics for SES Notifications
resource "aws_sns_topic" "ses_bounces" {
  name = "${var.project_name}-ses-bounces-${var.environment}"

  tags = {
    Name        = "${var.project_name}-ses-bounces"
    Environment = var.environment
  }
}

resource "aws_sns_topic" "ses_complaints" {
  name = "${var.project_name}-ses-complaints-${var.environment}"

  tags = {
    Name        = "${var.project_name}-ses-complaints"
    Environment = var.environment
  }
}

# Lambda Function for Bounce Processing
resource "aws_lambda_function" "bounce_processor" {
  filename         = "bounce-processor.zip"
  function_name    = "${var.project_name}-bounce-processor-${var.environment}"
  role            = aws_iam_role.bounce_processor_role.arn
  handler         = "bounce-processor.handler"
  runtime         = "nodejs18.x"
  timeout         = 30
  source_code_hash = data.archive_file.bounce_processor_zip.output_base64sha256

  environment {
    variables = {
      BOUNCE_TABLE_NAME      = aws_dynamodb_table.ses_bounces.name
      COMPLAINT_TABLE_NAME   = aws_dynamodb_table.ses_complaints.name
      SUPPRESSION_TABLE_NAME = aws_dynamodb_table.ses_suppression_list.name
      PROJECT_NAME           = var.project_name
      ENVIRONMENT           = var.environment
    }
  }

  tags = {
    Name        = "${var.project_name}-bounce-processor"
    Environment = var.environment
  }
}

# Package bounce processor code
data "archive_file" "bounce_processor_zip" {
  type        = "zip"
  source_dir  = "${path.module}/../aws-bounce"
  output_path = "${path.module}/bounce-processor.zip"
  excludes    = ["README.md", "*.zip", "test-*"]
}

# IAM Role for Bounce Processor
resource "aws_iam_role" "bounce_processor_role" {
  name = "${var.project_name}-bounce-processor-role-${var.environment}"

  assume_role_policy = jsonencode({
    Version = "2012-10-17"
    Statement = [
      {
        Action = "sts:AssumeRole"
        Effect = "Allow"
        Principal = {
          Service = "lambda.amazonaws.com"
        }
      }
    ]
  })
}

resource "aws_iam_role_policy" "bounce_processor_policy" {
  name = "${var.project_name}-bounce-processor-policy-${var.environment}"
  role = aws_iam_role.bounce_processor_role.id

  policy = jsonencode({
    Version = "2012-10-17"
    Statement = [
      {
        Effect = "Allow"
        Action = [
          "logs:CreateLogGroup",
          "logs:CreateLogStream",
          "logs:PutLogEvents"
        ]
        Resource = "arn:aws:logs:*:*:*"
      },
      {
        Effect = "Allow"
        Action = [
          "dynamodb:PutItem",
          "dynamodb:UpdateItem",
          "dynamodb:GetItem",
          "dynamodb:Query",
          "dynamodb:Scan"
        ]
        Resource = [
          aws_dynamodb_table.ses_bounces.arn,
          aws_dynamodb_table.ses_complaints.arn,
          aws_dynamodb_table.ses_suppression_list.arn
        ]
      },
      {
        Effect = "Allow"
        Action = [
          "ses:PutAccountSuppressionAttributes",
          "ses:PutSuppressedDestination",
          "ses:GetSuppressedDestination"
        ]
        Resource = "*"
      }
    ]
  })
}

# SNS Subscriptions
resource "aws_sns_topic_subscription" "bounce_processor_bounces" {
  topic_arn = aws_sns_topic.ses_bounces.arn
  protocol  = "lambda"
  endpoint  = aws_lambda_function.bounce_processor.arn
}

resource "aws_sns_topic_subscription" "bounce_processor_complaints" {
  topic_arn = aws_sns_topic.ses_complaints.arn
  protocol  = "lambda"
  endpoint  = aws_lambda_function.bounce_processor.arn
}

# Lambda Permissions for SNS
resource "aws_lambda_permission" "allow_sns_bounces" {
  statement_id  = "AllowExecutionFromSNSBounces"
  action        = "lambda:InvokeFunction"
  function_name = aws_lambda_function.bounce_processor.function_name
  principal     = "sns.amazonaws.com"
  source_arn    = aws_sns_topic.ses_bounces.arn
}

resource "aws_lambda_permission" "allow_sns_complaints" {
  statement_id  = "AllowExecutionFromSNSComplaints"
  action        = "lambda:InvokeFunction"
  function_name = aws_lambda_function.bounce_processor.function_name
  principal     = "sns.amazonaws.com"
  source_arn    = aws_sns_topic.ses_complaints.arn
}

# SES Configuration Set (for event publishing)
resource "aws_sesv2_configuration_set" "aeims_email" {
  configuration_set_name = "${var.project_name}-email-config-${var.environment}"

  reputation_options {
    reputation_metrics_enabled = true
  }

  delivery_options {
    tls_policy = "Require"
  }

  tags = {
    Name        = "${var.project_name}-email-config"
    Environment = var.environment
  }
}

# SES Event Destinations
resource "aws_sesv2_configuration_set_event_destination" "bounce_destination" {
  configuration_set_name = aws_sesv2_configuration_set.aeims_email.configuration_set_name
  event_destination_name = "bounce-destination"

  event_destination {
    enabled = true
    matching_event_types = ["bounce"]

    sns_destination {
      topic_arn = aws_sns_topic.ses_bounces.arn
    }
  }
}

resource "aws_sesv2_configuration_set_event_destination" "complaint_destination" {
  configuration_set_name = aws_sesv2_configuration_set.aeims_email.configuration_set_name
  event_destination_name = "complaint-destination"

  event_destination {
    enabled = true
    matching_event_types = ["complaint"]

    sns_destination {
      topic_arn = aws_sns_topic.ses_complaints.arn
    }
  }
}

# Outputs
output "ses_configuration_set_name" {
  description = "Name of the SES configuration set"
  value       = aws_sesv2_configuration_set.aeims_email.configuration_set_name
}

output "bounce_processor_function_name" {
  description = "Name of the bounce processor Lambda function"
  value       = aws_lambda_function.bounce_processor.function_name
}

output "suppression_table_name" {
  description = "Name of the SES suppression list table"
  value       = aws_dynamodb_table.ses_suppression_list.name
}