#!/bin/bash
#
# Quick deployment script for session fix
# This script builds and deploys the session fix to AWS ECS

set -e

echo "========================================="
echo "AEIMS Session Fix Deployment"
echo "========================================="

# Configuration
AWS_REGION="us-east-1"
AWS_ACCOUNT_ID="515966511618"
ECR_REPO="aeims-app"  # Fixed: was "aeims", should be "aeims-app"
IMAGE_TAG="production-latest"  # Use the tag that task definition expects
ECS_CLUSTER="aeims-cluster"
ECS_SERVICE="aeims-service"

echo "📦 Building Docker image..."
docker build -t ${ECR_REPO}:${IMAGE_TAG} -t ${ECR_REPO}:latest .

echo "🔐 Logging into ECR..."
aws ecr get-login-password --region ${AWS_REGION} | \
    docker login --username AWS --password-stdin ${AWS_ACCOUNT_ID}.dkr.ecr.${AWS_REGION}.amazonaws.com

echo "🏷️  Tagging image..."
docker tag ${ECR_REPO}:${IMAGE_TAG} ${AWS_ACCOUNT_ID}.dkr.ecr.${AWS_REGION}.amazonaws.com/${ECR_REPO}:${IMAGE_TAG}
docker tag ${ECR_REPO}:latest ${AWS_ACCOUNT_ID}.dkr.ecr.${AWS_REGION}.amazonaws.com/${ECR_REPO}:latest

echo "⬆️  Pushing image to ECR..."
docker push ${AWS_ACCOUNT_ID}.dkr.ecr.${AWS_REGION}.amazonaws.com/${ECR_REPO}:${IMAGE_TAG}
docker push ${AWS_ACCOUNT_ID}.dkr.ecr.${AWS_REGION}.amazonaws.com/${ECR_REPO}:latest

echo "🔄 Updating ECS service..."
aws ecs update-service \
    --cluster ${ECS_CLUSTER} \
    --service ${ECS_SERVICE} \
    --force-new-deployment \
    --region ${AWS_REGION}

echo "✅ Deployment initiated!"
echo ""
echo "Monitor deployment status with:"
echo "aws ecs describe-services --cluster ${ECS_CLUSTER} --services ${ECS_SERVICE} --region ${AWS_REGION}"
echo ""
echo "Image pushed: ${AWS_ACCOUNT_ID}.dkr.ecr.${AWS_REGION}.amazonaws.com/${ECR_REPO}:${IMAGE_TAG}"
