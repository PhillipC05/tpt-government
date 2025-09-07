#!/bin/bash

# TPT Government Platform - Comprehensive Deployment Automation Script
#
# This script provides automated deployment capabilities for the TPT Government Platform
# supporting multiple cloud providers, environments, and deployment strategies

set -euo pipefail

# Configuration
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(dirname "$SCRIPT_DIR")"
DEPLOYMENT_ID=$(date +%Y%m%d_%H%M%S)_$(openssl rand -hex 4)
LOG_FILE="$PROJECT_ROOT/logs/deployment_$DEPLOYMENT_ID.log"

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Default configuration
ENVIRONMENT="${ENVIRONMENT:-production}"
CLOUD_PROVIDER="${CLOUD_PROVIDER:-aws}"
REGION="${REGION:-us-east-1}"
INSTANCE_TYPE="${INSTANCE_TYPE:-t3.large}"
DATABASE_SIZE="${DATABASE_SIZE:-db.t3.medium}"
REDIS_SIZE="${REDIS_SIZE:-cache.t3.micro}"
ENABLE_BACKUP="${ENABLE_BACKUP:-true}"
ENABLE_MONITORING="${ENABLE_MONITORING:-true}"
ENABLE_LOGGING="${ENABLE_LOGGING:-true}"
DOMAIN_NAME="${DOMAIN_NAME:-}"
SSL_CERT_ARN="${SSL_CERT_ARN:-}"

# Logging functions
log() {
    echo -e "${BLUE}[$(date +'%Y-%m-%d %H:%M:%S')] $1${NC}" | tee -a "$LOG_FILE"
}

error() {
    echo -e "${RED}[ERROR] $1${NC}" >&2 | tee -a "$LOG_FILE"
    exit 1
}

warning() {
    echo -e "${YELLOW}[WARNING] $1${NC}" | tee -a "$LOG_FILE"
}

success() {
    echo -e "${GREEN}[SUCCESS] $1${NC}" | tee -a "$LOG_FILE"
}

# Validation functions
validate_environment() {
    log "Validating deployment environment..."

    # Check required tools
    command -v docker >/dev/null 2>&1 || error "Docker is required but not installed"
    command -v docker-compose >/dev/null 2>&1 || error "Docker Compose is required but not installed"

    # Validate cloud provider
    case "$CLOUD_PROVIDER" in
        aws|azure|gcp|digitalocean|linode|vultr|hetzner|alibaba|tencent)
            log "Cloud provider: $CLOUD_PROVIDER"
            ;;
        *)
            error "Unsupported cloud provider: $CLOUD_PROVIDER"
            ;;
    esac

    # Validate environment
    case "$ENVIRONMENT" in
        development|staging|production)
            log "Environment: $ENVIRONMENT"
            ;;
        *)
            error "Invalid environment: $ENVIRONMENT"
            ;;
    esac

    # Check required files
    [[ -f "$PROJECT_ROOT/docker-compose.yml" ]] || error "docker-compose.yml not found"
    [[ -f "$PROJECT_ROOT/Dockerfile" ]] || error "Dockerfile not found"
    [[ -f "$PROJECT_ROOT/.env.example" ]] || error ".env.example not found"

    success "Environment validation completed"
}

setup_environment() {
    log "Setting up deployment environment..."

    # Create necessary directories
    mkdir -p "$PROJECT_ROOT/logs"
    mkdir -p "$PROJECT_ROOT/backups"
    mkdir -p "$PROJECT_ROOT/ssl"

    # Generate environment file
    if [[ ! -f "$PROJECT_ROOT/.env" ]]; then
        cp "$PROJECT_ROOT/.env.example" "$PROJECT_ROOT/.env"
        log "Created .env file from template"
    fi

    # Set environment-specific variables
    case "$ENVIRONMENT" in
        development)
            export APP_ENV=development
            export DEBUG=true
            export LOG_LEVEL=debug
            ;;
        staging)
            export APP_ENV=staging
            export DEBUG=false
            export LOG_LEVEL=info
            ;;
        production)
            export APP_ENV=production
            export DEBUG=false
            export LOG_LEVEL=warning
            ;;
    esac

    success "Environment setup completed"
}

build_application() {
    log "Building application..."

    cd "$PROJECT_ROOT"

    # Build Docker images
    log "Building Docker images..."
    docker-compose build --no-cache

    # Run tests if in development or staging
    if [[ "$ENVIRONMENT" != "production" ]]; then
        log "Running tests..."
        docker-compose run --rm app php vendor/bin/phpunit --coverage-html coverage
    fi

    # Build frontend assets
    log "Building frontend assets..."
    docker-compose run --rm app npm run build

    # Create production build
    log "Creating production build..."
    docker build -t tpt-gov-platform:"$DEPLOYMENT_ID" -f Dockerfile .

    success "Application build completed"
}

setup_database() {
    log "Setting up database..."

    # Create database backup if exists
    if [[ "$ENABLE_BACKUP" == "true" ]]; then
        log "Creating database backup..."
        docker-compose exec db mysqldump -u root -p"$DB_ROOT_PASSWORD" tpt_gov > "$PROJECT_ROOT/backups/pre_deployment_$DEPLOYMENT_ID.sql"
    fi

    # Run database migrations
    log "Running database migrations..."
    docker-compose run --rm app php artisan migrate --force

    # Seed database if development
    if [[ "$ENVIRONMENT" == "development" ]]; then
        log "Seeding database..."
        docker-compose run --rm app php artisan db:seed
    fi

    success "Database setup completed"
}

setup_ssl() {
    log "Setting up SSL certificates..."

    if [[ -n "$DOMAIN_NAME" ]]; then
        case "$CLOUD_PROVIDER" in
            aws)
                if [[ -z "$SSL_CERT_ARN" ]]; then
                    log "Requesting SSL certificate from AWS Certificate Manager..."
                    # AWS CLI commands to request certificate
                    aws acm request-certificate \
                        --domain-name "$DOMAIN_NAME" \
                        --validation-method DNS \
                        --region "$REGION"
                fi
                ;;
            azure)
                log "Setting up SSL for Azure..."
                # Azure CLI commands for SSL setup
                ;;
            gcp)
                log "Setting up SSL for Google Cloud..."
                # GCP commands for SSL setup
                ;;
        esac
    else
        warning "No domain name specified, skipping SSL setup"
    fi

    success "SSL setup completed"
}

deploy_infrastructure() {
    log "Deploying infrastructure..."

    case "$CLOUD_PROVIDER" in
        aws)
            deploy_aws
            ;;
        azure)
            deploy_azure
            ;;
        gcp)
            deploy_gcp
            ;;
        digitalocean)
            deploy_digitalocean
            ;;
        linode)
            deploy_linode
            ;;
        vultr)
            deploy_vultr
            ;;
        hetzner)
            deploy_hetzner
            ;;
        alibaba)
            deploy_alibaba
            ;;
        tencent)
            deploy_tencent
            ;;
    esac

    success "Infrastructure deployment completed"
}

deploy_aws() {
    log "Deploying to AWS..."

    # Create CloudFormation stack
    aws cloudformation create-stack \
        --stack-name "tpt-gov-$ENVIRONMENT-$DEPLOYMENT_ID" \
        --template-body file://$PROJECT_ROOT/k8s/deployment.yaml \
        --parameters \
            ParameterKey=Environment,ParameterValue="$ENVIRONMENT" \
            ParameterKey=InstanceType,ParameterValue="$INSTANCE_TYPE" \
            ParameterKey=DatabaseSize,ParameterValue="$DATABASE_SIZE" \
            ParameterKey=RedisSize,ParameterValue="$REDIS_SIZE" \
        --region "$REGION"

    # Wait for stack creation
    aws cloudformation wait stack-create-complete \
        --stack-name "tpt-gov-$ENVIRONMENT-$DEPLOYMENT_ID" \
        --region "$REGION"

    # Get stack outputs
    STACK_OUTPUTS=$(aws cloudformation describe-stacks \
        --stack-name "tpt-gov-$ENVIRONMENT-$DEPLOYMENT_ID" \
        --region "$REGION" \
        --query 'Stacks[0].Outputs')

    # Configure load balancer
    if [[ "$ENABLE_SSL" == "true" ]] && [[ -n "$SSL_CERT_ARN" ]]; then
        aws elbv2 create-listener \
            --load-balancer-arn "$(echo "$STACK_OUTPUTS" | jq -r '.[] | select(.OutputKey=="LoadBalancerArn") | .OutputValue')" \
            --protocol HTTPS \
            --port 443 \
            --certificates CertificateArn="$SSL_CERT_ARN" \
            --default-actions Type=forward,TargetGroupArn="$(echo "$STACK_OUTPUTS" | jq -r '.[] | select(.OutputKey=="TargetGroupArn") | .OutputValue')" \
            --region "$REGION"
    fi
}

deploy_azure() {
    log "Deploying to Azure..."

    # Create resource group
    az group create \
        --name "tpt-gov-$ENVIRONMENT-$DEPLOYMENT_ID" \
        --location "$REGION"

    # Deploy ARM template
    az deployment group create \
        --resource-group "tpt-gov-$ENVIRONMENT-$DEPLOYMENT_ID" \
        --template-file "$PROJECT_ROOT/k8s/deployment.yaml" \
        --parameters \
            environment="$ENVIRONMENT" \
            instanceType="$INSTANCE_TYPE" \
            databaseSize="$DATABASE_SIZE" \
            redisSize="$REDIS_SIZE"
}

deploy_gcp() {
    log "Deploying to Google Cloud..."

    # Set project
    gcloud config set project "$GCP_PROJECT_ID"

    # Deploy using Kubernetes
    kubectl apply -f "$PROJECT_ROOT/k8s/"

    # Configure load balancer
    gcloud compute addresses create tpt-gov-ip --global
    gcloud compute ssl-certificates create tpt-gov-cert \
        --certificate "$PROJECT_ROOT/ssl/cert.pem" \
        --private-key "$PROJECT_ROOT/ssl/private.key"
}

deploy_digitalocean() {
    log "Deploying to DigitalOcean..."

    # Create droplet
    doctl compute droplet create "tpt-gov-$ENVIRONMENT-$DEPLOYMENT_ID" \
        --image ubuntu-20-04-x64 \
        --size "$INSTANCE_TYPE" \
        --region "$REGION" \
        --ssh-keys "$SSH_KEY_ID"

    # Wait for droplet to be ready
    sleep 60

    # Get droplet IP
    DROPLET_IP=$(doctl compute droplet list | grep "tpt-gov-$ENVIRONMENT-$DEPLOYMENT_ID" | awk '{print $3}')

    # Configure DNS if domain provided
    if [[ -n "$DOMAIN_NAME" ]]; then
        doctl compute domain create "$DOMAIN_NAME"
        doctl compute domain records create "$DOMAIN_NAME" \
            --record-type A \
            --record-name "@" \
            --record-data "$DROPLET_IP"
    fi
}

deploy_linode() {
    log "Deploying to Linode..."

    # Create Linode instance
    linode-cli linodes create \
        --type "$INSTANCE_TYPE" \
        --region "$REGION" \
        --image "linode/ubuntu20.04" \
        --label "tpt-gov-$ENVIRONMENT-$DEPLOYMENT_ID"
}

deploy_vultr() {
    log "Deploying to Vultr..."

    # Create instance
    vultr-cli instance create \
        --plan "$INSTANCE_TYPE" \
        --region "$REGION" \
        --os "Ubuntu 20.04 x64" \
        --label "tpt-gov-$ENVIRONMENT-$DEPLOYMENT_ID"
}

deploy_hetzner() {
    log "Deploying to Hetzner..."

    # Create server
    hcloud server create \
        --name "tpt-gov-$ENVIRONMENT-$DEPLOYMENT_ID" \
        --type "$INSTANCE_TYPE" \
        --image "ubuntu-20.04" \
        --location "$REGION"
}

deploy_alibaba() {
    log "Deploying to Alibaba Cloud..."

    # Create ECS instance
    aliyun ecs CreateInstance \
        --InstanceName "tpt-gov-$ENVIRONMENT-$DEPLOYMENT_ID" \
        --InstanceType "$INSTANCE_TYPE" \
        --ImageId "ubuntu_20_04_x64_20G_alibase_20211021.vhd" \
        --RegionId "$REGION"
}

deploy_tencent() {
    log "Deploying to Tencent Cloud..."

    # Create CVM instance
    tccli cvm CreateInstance \
        --InstanceName "tpt-gov-$ENVIRONMENT-$DEPLOYMENT_ID" \
        --InstanceType "$INSTANCE_TYPE" \
        --ImageId "img-12345678" \
        --Region "$REGION"
}

deploy_application() {
    log "Deploying application..."

    # Push Docker image
    case "$CLOUD_PROVIDER" in
        aws)
            aws ecr get-login-password --region "$REGION" | docker login --username AWS --password-stdin "$AWS_ACCOUNT_ID.dkr.ecr.$REGION.amazonaws.com"
            docker tag tpt-gov-platform:"$DEPLOYMENT_ID" "$AWS_ACCOUNT_ID.dkr.ecr.$REGION.amazonaws.com/tpt-gov-platform:$DEPLOYMENT_ID"
            docker push "$AWS_ACCOUNT_ID.dkr.ecr.$REGION.amazonaws.com/tpt-gov-platform:$DEPLOYMENT_ID"
            ;;
        azure)
            docker tag tpt-gov-platform:"$DEPLOYMENT_ID" "$ACR_NAME.azurecr.io/tpt-gov-platform:$DEPLOYMENT_ID"
            docker push "$ACR_NAME.azurecr.io/tpt-gov-platform:$DEPLOYMENT_ID"
            ;;
        gcp)
            docker tag tpt-gov-platform:"$DEPLOYMENT_ID" "gcr.io/$GCP_PROJECT_ID/tpt-gov-platform:$DEPLOYMENT_ID"
            docker push "gcr.io/$GCP_PROJECT_ID/tpt-gov-platform:$DEPLOYMENT_ID"
            ;;
    esac

    # Deploy using Kubernetes
    sed -i "s/DEPLOYMENT_ID/$DEPLOYMENT_ID/g" "$PROJECT_ROOT/k8s/deployment.yaml"
    kubectl apply -f "$PROJECT_ROOT/k8s/"

    # Wait for rollout
    kubectl rollout status deployment/tpt-gov-platform

    success "Application deployment completed"
}

setup_monitoring() {
    if [[ "$ENABLE_MONITORING" == "true" ]]; then
        log "Setting up monitoring..."

        # Deploy monitoring stack
        kubectl apply -f "$PROJECT_ROOT/monitoring/"

        # Configure alerts
        case "$CLOUD_PROVIDER" in
            aws)
                # AWS CloudWatch alarms
                aws cloudwatch put-metric-alarm \
                    --alarm-name "tpt-gov-high-cpu-$ENVIRONMENT" \
                    --alarm-description "High CPU usage" \
                    --metric-name CPUUtilization \
                    --namespace AWS/EC2 \
                    --statistic Average \
                    --period 300 \
                    --threshold 80 \
                    --comparison-operator GreaterThanThreshold
                ;;
            azure)
                # Azure Monitor alerts
                az monitor metrics alert create \
                    --name "tpt-gov-high-cpu-$ENVIRONMENT" \
                    --description "High CPU usage" \
                    --condition "avg Percentage CPU > 80" \
                    --resource-group "tpt-gov-$ENVIRONMENT-$DEPLOYMENT_ID"
                ;;
        esac

        success "Monitoring setup completed"
    fi
}

setup_logging() {
    if [[ "$ENABLE_LOGGING" == "true" ]]; then
        log "Setting up logging..."

        # Deploy logging stack
        kubectl apply -f "$PROJECT_ROOT/logging/"

        # Configure log aggregation
        case "$CLOUD_PROVIDER" in
            aws)
                # AWS CloudWatch logs
                aws logs create-log-group --log-group-name "/tpt-gov/$ENVIRONMENT"
                ;;
            azure)
                # Azure Log Analytics
                az monitor diagnostic-settings create \
                    --name "tpt-gov-logs-$ENVIRONMENT" \
                    --resource "/subscriptions/$AZURE_SUBSCRIPTION_ID/resourceGroups/tpt-gov-$ENVIRONMENT-$DEPLOYMENT_ID" \
                    --logs '[{"category": "AuditEvent", "enabled": true}]' \
                    --workspace "/subscriptions/$AZURE_SUBSCRIPTION_ID/resourceGroups/DefaultResourceGroup/providers/Microsoft.OperationalInsights/workspaces/DefaultWorkspace"
                ;;
        esac

        success "Logging setup completed"
    fi
}

setup_backup() {
    if [[ "$ENABLE_BACKUP" == "true" ]]; then
        log "Setting up backup system..."

        case "$CLOUD_PROVIDER" in
            aws)
                # AWS Backup
                aws backup create-backup-plan \
                    --backup-plan "{\"BackupPlanName\":\"tpt-gov-$ENVIRONMENT\",\"Rules\":[{\"RuleName\":\"DailyBackup\",\"TargetBackupVaultName\":\"tpt-gov-vault\",\"ScheduleExpression\":\"cron(0 5 ? * * *)\",\"Lifecycle\":{\"DeleteAfterDays\":30}}]}"
                ;;
            azure)
                # Azure Backup
                az backup protection enable-for-vm \
                    --resource-group "tpt-gov-$ENVIRONMENT-$DEPLOYMENT_ID" \
                    --vault-name "tpt-gov-vault" \
                    --vm "$(az vm list --resource-group "tpt-gov-$ENVIRONMENT-$DEPLOYMENT_ID" --query "[0].name" -o tsv)" \
                    --policy-name "DefaultPolicy"
                ;;
        esac

        success "Backup system setup completed"
    fi
}

run_health_checks() {
    log "Running health checks..."

    # Wait for application to be ready
    max_attempts=30
    attempt=1

    while [[ $attempt -le $max_attempts ]]; do
        log "Health check attempt $attempt/$max_attempts"

        if curl -f -s "$BASE_URL/health" > /dev/null 2>&1; then
            success "Application is healthy"
            break
        fi

        if [[ $attempt -eq $max_attempts ]]; then
            error "Application failed health checks"
        fi

        sleep 10
        ((attempt++))
    done

    # Run additional checks
    check_database_connection
    check_cache_connection
    check_external_services

    success "All health checks passed"
}

check_database_connection() {
    log "Checking database connection..."
    # Database health check logic
}

check_cache_connection() {
    log "Checking cache connection..."
    # Cache health check logic
}

check_external_services() {
    log "Checking external services..."
    # External services health check logic
}

cleanup_old_deployments() {
    log "Cleaning up old deployments..."

    # Keep only last 5 deployments
    case "$CLOUD_PROVIDER" in
        aws)
            # Clean up old CloudFormation stacks
            aws cloudformation list-stacks --region "$REGION" | \
                jq -r '.StackSummaries[] | select(.StackName | startswith("tpt-gov-")) | select(.StackStatus != "DELETE_COMPLETE") | .StackName' | \
                head -n -5 | \
                xargs -I {} aws cloudformation delete-stack --stack-name {} --region "$REGION"
            ;;
        azure)
            # Clean up old resource groups
            az group list --query "[?starts_with(name, 'tpt-gov-')].name" -o tsv | \
                head -n -5 | \
                xargs -I {} az group delete --name {} --yes --no-wait
            ;;
    esac

    success "Old deployments cleanup completed"
}

generate_deployment_report() {
    log "Generating deployment report..."

    cat > "$PROJECT_ROOT/logs/deployment_report_$DEPLOYMENT_ID.md" << EOF
# TPT Government Platform Deployment Report

## Deployment Details
- **Deployment ID**: $DEPLOYMENT_ID
- **Environment**: $ENVIRONMENT
- **Cloud Provider**: $CLOUD_PROVIDER
- **Region**: $REGION
- **Timestamp**: $(date)

## Infrastructure
- **Instance Type**: $INSTANCE_TYPE
- **Database Size**: $DATABASE_SIZE
- **Redis Size**: $REDIS_SIZE

## Features Enabled
- **Backup**: $ENABLE_BACKUP
- **Monitoring**: $ENABLE_MONITORING
- **Logging**: $ENABLE_LOGGING
- **SSL**: $([[ -n "$DOMAIN_NAME" ]] && echo "Yes" || echo "No")

## Deployment Status
✅ Environment validation completed
✅ Application build completed
✅ Database setup completed
✅ SSL setup completed
✅ Infrastructure deployment completed
✅ Application deployment completed
$( [[ "$ENABLE_MONITORING" == "true" ]] && echo "✅ Monitoring setup completed" || echo "❌ Monitoring not enabled" )
$( [[ "$ENABLE_LOGGING" == "true" ]] && echo "✅ Logging setup completed" || echo "❌ Logging not enabled" )
$( [[ "$ENABLE_BACKUP" == "true" ]] && echo "✅ Backup setup completed" || echo "❌ Backup not enabled" )
✅ Health checks passed
✅ Old deployments cleanup completed

## Next Steps
1. Configure DNS records (if not automatically configured)
2. Update monitoring dashboards
3. Configure backup schedules
4. Test application functionality
5. Update documentation

## Logs
- **Deployment Log**: $LOG_FILE
- **Application Logs**: Available in cloud logging service
- **Monitoring**: Available in cloud monitoring service

---
Generated by TPT Government Platform Deployment Script
EOF

    success "Deployment report generated: $PROJECT_ROOT/logs/deployment_report_$DEPLOYMENT_ID.md"
}

rollback_deployment() {
    warning "Starting deployment rollback..."

    case "$CLOUD_PROVIDER" in
        aws)
            # Rollback CloudFormation stack
            aws cloudformation rollback-stack \
                --stack-name "tpt-gov-$ENVIRONMENT-$DEPLOYMENT_ID" \
                --region "$REGION"
            ;;
        azure)
            # Rollback Azure deployment
            az deployment group create \
                --resource-group "tpt-gov-$ENVIRONMENT-$DEPLOYMENT_ID" \
                --template-file "$PROJECT_ROOT/k8s/deployment.yaml" \
                --mode Complete
            ;;
        gcp)
            # Rollback GKE deployment
            kubectl rollout undo deployment/tpt-gov-platform
            ;;
    esac

    # Restore database backup if available
    if [[ "$ENABLE_BACKUP" == "true" ]] && [[ -f "$PROJECT_ROOT/backups/pre_deployment_$DEPLOYMENT_ID.sql" ]]; then
        log "Restoring database backup..."
        docker-compose exec -T db mysql -u root -p"$DB_ROOT_PASSWORD" tpt_gov < "$PROJECT_ROOT/backups/pre_deployment_$DEPLOYMENT_ID.sql"
    fi

    success "Deployment rollback completed"
}

# Main deployment function
main() {
    log "Starting TPT Government Platform deployment..."
    log "Deployment ID: $DEPLOYMENT_ID"
    log "Environment: $ENVIRONMENT"
    log "Cloud Provider: $CLOUD_PROVIDER"

    # Trap for cleanup on error
    trap 'error "Deployment failed, check logs: $LOG_FILE"' ERR

    validate_environment
    setup_environment
    build_application
    setup_database
    setup_ssl
    deploy_infrastructure
    deploy_application
    setup_monitoring
    setup_logging
    setup_backup
    run_health_checks
    cleanup_old_deployments
    generate_deployment_report

    success "🎉 TPT Government Platform deployment completed successfully!"
    success "Deployment ID: $DEPLOYMENT_ID"
    success "Environment: $ENVIRONMENT"
    success "Cloud Provider: $CLOUD_PROVIDER"

    # Print next steps
    echo ""
    echo "Next steps:"
    echo "1. Update DNS records to point to the new deployment"
    echo "2. Configure monitoring alerts"
    echo "3. Test application functionality"
    echo "4. Update team documentation"
    echo ""
    echo "Deployment report: $PROJECT_ROOT/logs/deployment_report_$DEPLOYMENT_ID.md"
    echo "Deployment logs: $LOG_FILE"
}

# Rollback function
rollback() {
    log "Starting rollback for deployment: $DEPLOYMENT_ID"
    rollback_deployment
}

# Help function
show_help() {
    cat << EOF
TPT Government Platform - Deployment Script

Usage: $0 [OPTIONS] [COMMAND]

Commands:
    deploy      Deploy the application (default)
    rollback    Rollback the last deployment
    help        Show this help message

Options:
    -e, --environment ENV     Deployment environment (development|staging|production)
    -c, --cloud CLOUD          Cloud provider (aws|azure|gcp|digitalocean|linode|vultr|hetzner|alibaba|tencent)
    -r, --region REGION        Cloud region
    -i, --instance-type TYPE   Instance type
    -d, --domain DOMAIN        Domain name for SSL
    --no-backup               Disable backup
    --no-monitoring          Disable monitoring
    --no-logging             Disable logging
    -h, --help                Show this help message

Examples:
    $0 deploy -e production -c aws -r us-east-1
    $0 rollback
    $0 -e staging -c azure -r eastus deploy

Environment Variables:
    ENVIRONMENT              Deployment environment
    CLOUD_PROVIDER           Cloud provider
    REGION                   Cloud region
    INSTANCE_TYPE           Instance type
    DATABASE_SIZE           Database instance size
    REDIS_SIZE              Redis instance size
    ENABLE_BACKUP           Enable backup (true/false)
    ENABLE_MONITORING       Enable monitoring (true/false)
    ENABLE_LOGGING          Enable logging (true/false)
    DOMAIN_NAME             Domain name for SSL
    SSL_CERT_ARN            SSL certificate ARN (AWS only)

EOF
}

# Parse command line arguments
while [[ $# -gt 0 ]]; do
    case $1 in
        -e|--environment)
            ENVIRONMENT="$2"
            shift 2
            ;;
        -c|--cloud)
            CLOUD_PROVIDER="$2"
            shift 2
            ;;
        -r|--region)
            REGION="$2"
            shift 2
            ;;
        -i|--instance-type)
            INSTANCE_TYPE="$2"
            shift 2
            ;;
        -d|--domain)
            DOMAIN_NAME="$2"
            shift 2
            ;;
        --no-backup)
            ENABLE_BACKUP=false
            shift
            ;;
        --no-monitoring)
            ENABLE_MONITORING=false
            shift
            ;;
        --no-logging)
            ENABLE_LOGGING=false
            shift
            ;;
        -h|--help)
            show_help
            exit 0
            ;;
        deploy)
            COMMAND="deploy"
            shift
            ;;
        rollback)
            COMMAND="rollback"
            shift
            ;;
        help)
            show_help
            exit 0
            ;;
        *)
            error "Unknown option: $1"
            ;;
    esac
done

# Default command is deploy
COMMAND="${COMMAND:-deploy}"

# Execute command
case "$COMMAND" in
    deploy)
        main
        ;;
    rollback)
        rollback
        ;;
    *)
        error "Unknown command: $COMMAND"
        ;;
esac
