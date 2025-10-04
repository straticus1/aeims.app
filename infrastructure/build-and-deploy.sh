#!/bin/bash
# AEIMS Build and Deploy Script
# Handles Docker build, ECR push, and ECS deployment

set -e

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Configuration
AWS_REGION="us-east-1"
AWS_ACCOUNT_ID="515966511618"
ECR_REPOSITORY="afterdarksys/aeims"
ECS_CLUSTER="aeims-cluster"
ECS_SERVICE="aeims-service"
IMAGE_TAG=$(date +%s)

print_info() {
    echo -e "${BLUE}[INFO]${NC} $1"
}

print_success() {
    echo -e "${GREEN}[SUCCESS]${NC} $1"
}

print_warning() {
    echo -e "${YELLOW}[WARNING]${NC} $1"
}

print_error() {
    echo -e "${RED}[ERROR]${NC} $1"
}

# Build Docker image
build_image() {
    print_info "Building Docker image for AEIMS..."

    cd "$(dirname "$0")/.."

    docker build -f infrastructure/Dockerfile -t aeims:${IMAGE_TAG} .
    docker tag aeims:${IMAGE_TAG} aeims:latest

    print_success "Docker image built successfully"
}

# Login to ECR
ecr_login() {
    print_info "Logging in to Amazon ECR..."

    aws ecr get-login-password --region ${AWS_REGION} | docker login --username AWS --password-stdin ${AWS_ACCOUNT_ID}.dkr.ecr.${AWS_REGION}.amazonaws.com

    print_success "ECR login successful"
}

# Push to ECR
push_to_ecr() {
    print_info "Pushing image to ECR..."

    # Tag for ECR
    docker tag aeims:${IMAGE_TAG} ${AWS_ACCOUNT_ID}.dkr.ecr.${AWS_REGION}.amazonaws.com/${ECR_REPOSITORY}:${IMAGE_TAG}
    docker tag aeims:${IMAGE_TAG} ${AWS_ACCOUNT_ID}.dkr.ecr.${AWS_REGION}.amazonaws.com/${ECR_REPOSITORY}:latest

    # Push to ECR
    docker push ${AWS_ACCOUNT_ID}.dkr.ecr.${AWS_REGION}.amazonaws.com/${ECR_REPOSITORY}:${IMAGE_TAG}
    docker push ${AWS_ACCOUNT_ID}.dkr.ecr.${AWS_REGION}.amazonaws.com/${ECR_REPOSITORY}:latest

    print_success "Image pushed to ECR successfully"
}

# Update ECS task definition
update_task_definition() {
    print_info "Updating ECS task definition..." >&2

    # Update the task definition with new image
    TASK_DEFINITION=$(cat infrastructure/ecs-task-definition.json | \
        jq --arg IMAGE "${AWS_ACCOUNT_ID}.dkr.ecr.${AWS_REGION}.amazonaws.com/${ECR_REPOSITORY}:${IMAGE_TAG}" \
        '.containerDefinitions[0].image = $IMAGE')

    # Register new task definition
    NEW_TASK_DEFINITION=$(aws ecs register-task-definition \
        --region ${AWS_REGION} \
        --cli-input-json "$TASK_DEFINITION" \
        --query 'taskDefinition.taskDefinitionArn' \
        --output text)

    print_success "New task definition registered: $NEW_TASK_DEFINITION" >&2
    echo "$NEW_TASK_DEFINITION"
}

# Update ECS service
update_service() {
    local task_definition_arn=$1

    print_info "Updating ECS service..."

    aws ecs update-service \
        --region ${AWS_REGION} \
        --cluster ${ECS_CLUSTER} \
        --service ${ECS_SERVICE} \
        --task-definition ${task_definition_arn} \
        --force-new-deployment

    print_success "ECS service update initiated"
}

# Wait for deployment
wait_for_deployment() {
    print_info "Waiting for deployment to complete..."

    aws ecs wait services-stable \
        --region ${AWS_REGION} \
        --cluster ${ECS_CLUSTER} \
        --services ${ECS_SERVICE}

    print_success "Deployment completed successfully"
}

# Clean up old images
cleanup_images() {
    print_info "Cleaning up old Docker images..."

    # Remove old local images (keep last 3)
    docker images aeims --format "table {{.Tag}}" | tail -n +4 | xargs -r docker rmi aeims: 2>/dev/null || true

    # Clean up ECR images (keep last 10)
    aws ecr describe-images \
        --region ${AWS_REGION} \
        --repository-name ${ECR_REPOSITORY} \
        --query 'sort_by(imageDetails,&imagePushedAt)[:-10].[imageDigest]' \
        --output text | \
    while read digest; do
        if [ ! -z "$digest" ]; then
            aws ecr batch-delete-image \
                --region ${AWS_REGION} \
                --repository-name ${ECR_REPOSITORY} \
                --image-ids imageDigest=$digest >/dev/null 2>&1 || true
        fi
    done

    print_success "Cleanup completed"
}

# Main deployment function
main() {
    echo "AEIMS Build and Deploy"
    echo "====================="
    echo "Image Tag: ${IMAGE_TAG}"
    echo "ECR Repository: ${ECR_REPOSITORY}"
    echo "ECS Cluster: ${ECS_CLUSTER}"
    echo "ECS Service: ${ECS_SERVICE}"
    echo

    # Check if all required tools are available
    for tool in docker aws jq; do
        if ! command -v $tool &> /dev/null; then
            print_error "$tool is not installed"
            exit 1
        fi
    done

    # Build and deploy
    build_image
    ecr_login
    push_to_ecr

    TASK_DEFINITION_ARN=$(update_task_definition)
    update_service "$TASK_DEFINITION_ARN"
    wait_for_deployment
    cleanup_images

    echo
    print_success "🎉 AEIMS deployment completed successfully!"
    print_info "Image: ${AWS_ACCOUNT_ID}.dkr.ecr.${AWS_REGION}.amazonaws.com/${ECR_REPOSITORY}:${IMAGE_TAG}"
    print_info "Task Definition: $TASK_DEFINITION_ARN"
    echo
    print_info "Check deployment status:"
    echo "  aws ecs describe-services --cluster ${ECS_CLUSTER} --services ${ECS_SERVICE}"
    echo
}

# Handle script arguments
case "${1:-all}" in
    "build")
        build_image
        ;;
    "push")
        ecr_login
        push_to_ecr
        ;;
    "deploy")
        TASK_DEFINITION_ARN=$(update_task_definition)
        update_service "$TASK_DEFINITION_ARN"
        wait_for_deployment
        ;;
    "all"|"")
        main
        ;;
    *)
        echo "Usage: $0 [build|push|deploy|all]"
        echo "  build  - Build Docker image only"
        echo "  push   - Push to ECR only"
        echo "  deploy - Update ECS service only"
        echo "  all    - Complete build and deployment (default)"
        exit 1
        ;;
esac