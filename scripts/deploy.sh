#!/bin/bash
# TPT Government Platform - Deployment Script
# Comprehensive deployment automation for production environments

set -e  # Exit on any error

# Configuration
DEPLOY_ENV=${1:-production}
TIMESTAMP=$(date +%Y%m%d_%H%M%S)
BACKUP_DIR="/opt/tpt-gov/backups"
LOG_FILE="/var/log/tpt-gov/deploy_$TIMESTAMP.log"

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Logging function
log() {
    echo -e "${BLUE}[$(date +'%Y-%m-%d %H:%M:%S')] $1${NC}" | tee -a "$LOG_FILE"
}

error() {
    echo -e "${RED}[ERROR] $1${NC}" | tee -a "$LOG_FILE"
    exit 1
}

success() {
    echo -e "${GREEN}[SUCCESS] $1${NC}" | tee -a "$LOG_FILE"
}

warning() {
    echo -e "${YELLOW}[WARNING] $1${NC}" | tee -a "$LOG_FILE"
}

# Pre-deployment checks
pre_deployment_checks() {
    log "🔍 Running pre-deployment checks..."

    # Check if running as root or with sudo
    if [[ $EUID -eq 0 ]]; then
        error "This script should not be run as root for security reasons"
    fi

    # Check required commands
    local required_commands=("docker" "docker-compose" "git" "curl" "openssl")
    for cmd in "${required_commands[@]}"; do
        if ! command -v "$cmd" &> /dev/null; then
            error "Required command '$cmd' is not installed"
        fi
    done

    # Check available disk space (minimum 5GB)
    local available_space=$(df / | tail -1 | awk '{print $4}')
    if (( available_space < 5242880 )); then  # 5GB in KB
        error "Insufficient disk space. Need at least 5GB available"
    fi

    # Check network connectivity
    if ! curl -s --connect-timeout 5 https://registry-1.docker.io > /dev/null; then
        error "Cannot connect to Docker registry. Check internet connection"
    fi

    success "Pre-deployment checks passed"
}

# Backup current deployment
backup_current_deployment() {
    log "💾 Creating backup of current deployment..."

    # Create backup directory
    sudo mkdir -p "$BACKUP_DIR"

    # Backup database
    if [[ -f ".env" ]]; then
        source .env
        if [[ -n "$DB_HOST" && -n "$DB_NAME" && -n "$DB_USER" && -n "$DB_PASS" ]]; then
            log "Backing up database..."
            mysqldump -h"$DB_HOST" -u"$DB_USER" -p"$DB_PASS" "$DB_NAME" > "$BACKUP_DIR/db_backup_$TIMESTAMP.sql"
        fi
    fi

    # Backup configuration files
    if [[ -d "config" ]]; then
        cp -r config "$BACKUP_DIR/config_backup_$TIMESTAMP"
    fi

    # Backup uploaded files
    if [[ -d "storage" ]]; then
        cp -r storage "$BACKUP_DIR/storage_backup_$TIMESTAMP"
    fi

    success "Backup completed: $BACKUP_DIR"
}

# Setup environment
setup_environment() {
    log "🔧 Setting up deployment environment..."

    # Create necessary directories
    sudo mkdir -p /opt/tpt-gov/{logs,cache,sessions,uploads}
    sudo mkdir -p /var/log/tpt-gov
    sudo mkdir -p /etc/tpt-gov/ssl

    # Set proper permissions
    sudo chown -R $USER:$USER /opt/tpt-gov
    sudo chown -R $USER:$USER /var/log/tpt-gov

    # Create .env file if it doesn't exist
    if [[ ! -f ".env" ]]; then
        cp .env.example .env
        warning "Created .env file from template. Please configure your environment variables"
    fi

    success "Environment setup completed"
}

# Build and deploy containers
deploy_containers() {
    log "🐳 Building and deploying containers..."

    # Pull latest images
    docker-compose pull

    # Build custom images
    docker-compose build --no-cache

    # Run database migrations
    log "Running database migrations..."
    docker-compose run --rm app php artisan migrate --force

    # Seed database if needed
    if [[ "$DEPLOY_ENV" == "staging" ]]; then
        docker-compose run --rm app php artisan db:seed
    fi

    # Clear and cache configuration
    docker-compose run --rm app php artisan config:cache
    docker-compose run --rm app php artisan route:cache
    docker-compose run --rm app php artisan view:cache

    # Start services
    docker-compose up -d

    # Wait for services to be healthy
    log "Waiting for services to be healthy..."
    local max_attempts=30
    local attempt=1

    while [[ $attempt -le $max_attempts ]]; do
        if docker-compose ps | grep -q "healthy\|running"; then
            success "All services are healthy"
            break
        fi

        log "Waiting for services... (attempt $attempt/$max_attempts)"
        sleep 10
        ((attempt++))
    done

    if [[ $attempt -gt $max_attempts ]]; then
        error "Services failed to become healthy within timeout"
    fi
}

# Configure SSL certificates
configure_ssl() {
    log "🔒 Configuring SSL certificates..."

    local domain=$(grep APP_URL .env | cut -d '=' -f2 | tr -d ' ')
    local ssl_dir="/etc/tpt-gov/ssl"

    if [[ -z "$domain" ]]; then
        warning "No APP_URL found in .env. Skipping SSL configuration"
        return
    fi

    # Check if certificates already exist
    if [[ -f "$ssl_dir/$domain.crt" && -f "$ssl_dir/$domain.key" ]]; then
        log "SSL certificates already exist"
        return
    fi

    # Generate self-signed certificate for development/staging
    if [[ "$DEPLOY_ENV" != "production" ]]; then
        log "Generating self-signed SSL certificate..."
        sudo openssl req -x509 -nodes -days 365 -newkey rsa:2048 \
            -keyout "$ssl_dir/$domain.key" \
            -out "$ssl_dir/$domain.crt" \
            -subj "/C=US/ST=State/L=City/O=Organization/CN=$domain"

        sudo chmod 600 "$ssl_dir/$domain.key"
        sudo chmod 644 "$ssl_dir/$domain.crt"
    else
        # For production, use Let's Encrypt
        log "Setting up Let's Encrypt SSL certificate..."
        sudo certbot certonly --standalone -d "$domain" --agree-tos --email admin@$domain --non-interactive

        # Copy certificates to ssl directory
        sudo cp "/etc/letsencrypt/live/$domain/fullchain.pem" "$ssl_dir/$domain.crt"
        sudo cp "/etc/letsencrypt/live/$domain/privkey.pem" "$ssl_dir/$domain.key"
    fi

    success "SSL certificates configured"
}

# Configure firewall
configure_firewall() {
    log "🔥 Configuring firewall..."

    # Check if ufw is available
    if command -v ufw &> /dev/null; then
        sudo ufw --force enable
        sudo ufw allow ssh
        sudo ufw allow 80
        sudo ufw allow 443
        sudo ufw --force reload
        success "UFW firewall configured"
    elif command -v firewall-cmd &> /dev/null; then
        sudo firewall-cmd --permanent --add-service=ssh
        sudo firewall-cmd --permanent --add-service=http
        sudo firewall-cmd --permanent --add-service=https
        sudo firewall-cmd --reload
        success "Firewalld configured"
    else
        warning "No supported firewall found. Please configure manually"
    fi
}

# Configure monitoring
configure_monitoring() {
    log "📊 Configuring monitoring..."

    # Start monitoring stack
    if [[ -f "monitoring/docker-compose.monitoring.yml" ]]; then
        docker-compose -f monitoring/docker-compose.monitoring.yml up -d
        success "Monitoring stack started"
    fi

    # Configure log rotation
    sudo tee /etc/logrotate.d/tpt-gov > /dev/null <<EOF
/var/log/tpt-gov/*.log {
    daily
    missingok
    rotate 52
    compress
    delaycompress
    notifempty
    create 644 www-data www-data
    postrotate
        systemctl reload nginx
    endscript
}
EOF

    success "Monitoring configured"
}

# Configure backup
configure_backup() {
    log "💾 Configuring automated backups..."

    # Create backup script
    sudo tee /usr/local/bin/tpt-gov-backup > /dev/null <<'EOF'
#!/bin/bash
BACKUP_DIR="/opt/tpt-gov/backups"
TIMESTAMP=$(date +%Y%m%d_%H%M%S)
DB_CONTAINER="tpt-gov_db_1"

# Backup database
docker exec $DB_CONTAINER mysqldump -u root -p"$MYSQL_ROOT_PASSWORD" tpt_gov > "$BACKUP_DIR/db_$TIMESTAMP.sql"

# Backup files
tar -czf "$BACKUP_DIR/files_$TIMESTAMP.tar.gz" -C /opt/tpt-gov uploads storage

# Clean old backups (keep last 30 days)
find "$BACKUP_DIR" -name "*.sql" -mtime +30 -delete
find "$BACKUP_DIR" -name "*.tar.gz" -mtime +30 -delete

echo "Backup completed: $TIMESTAMP"
EOF

    sudo chmod +x /usr/local/bin/tpt-gov-backup

    # Add to cron for daily backups at 2 AM
    (sudo crontab -l ; echo "0 2 * * * /usr/local/bin/tpt-gov-backup") | sudo crontab -

    success "Automated backup configured"
}

# Health checks
run_health_checks() {
    log "🏥 Running health checks..."

    # Check if services are running
    if ! docker-compose ps | grep -q "Up"; then
        error "Some services are not running"
    fi

    # Check application health endpoint
    local max_attempts=10
    local attempt=1

    while [[ $attempt -le $max_attempts ]]; do
        if curl -s -f http://localhost/health > /dev/null; then
            success "Application health check passed"
            break
        fi

        log "Waiting for application to be healthy... (attempt $attempt/$max_attempts)"
        sleep 5
        ((attempt++))
    done

    if [[ $attempt -gt $max_attempts ]]; then
        error "Application health check failed"
    fi

    # Check database connectivity
    if docker-compose exec -T db mysqladmin ping -h localhost > /dev/null 2>&1; then
        success "Database connectivity check passed"
    else
        error "Database connectivity check failed"
    fi
}

# Post-deployment tasks
post_deployment_tasks() {
    log "🎯 Running post-deployment tasks..."

    # Clear application caches
    docker-compose exec -T app php artisan cache:clear
    docker-compose exec -T app php artisan config:clear
    docker-compose exec -T app php artisan route:clear
    docker-compose exec -T app php artisan view:clear

    # Generate application key if not set
    docker-compose exec -T app php artisan key:generate --no-interaction

    # Set proper file permissions
    sudo chown -R www-data:www-data /opt/tpt-gov
    sudo chmod -R 755 /opt/tpt-gov
    sudo chmod -R 777 /opt/tpt-gov/storage /opt/tpt-gov/cache /opt/tpt-gov/sessions

    # Restart web server
    sudo systemctl reload nginx

    success "Post-deployment tasks completed"
}

# Rollback function
rollback() {
    log "🔄 Performing rollback..."

    # Stop current deployment
    docker-compose down

    # Restore from backup if available
    local latest_backup=$(ls -t "$BACKUP_DIR"/db_backup_*.sql 2>/dev/null | head -1)
    if [[ -f "$latest_backup" ]]; then
        log "Restoring database from backup..."
        docker-compose exec -T db mysql -u root -p"$MYSQL_ROOT_PASSWORD" tpt_gov < "$latest_backup"
    fi

    # Restart previous version
    docker-compose up -d

    success "Rollback completed"
}

# Main deployment function
main() {
    log "🚀 Starting TPT Government Platform deployment..."
    log "Environment: $DEPLOY_ENV"
    log "Timestamp: $TIMESTAMP"

    # Trap for cleanup on error
    trap 'error "Deployment failed. Check logs at $LOG_FILE"' ERR

    case "$DEPLOY_ENV" in
        "production")
            pre_deployment_checks
            backup_current_deployment
            setup_environment
            configure_ssl
            configure_firewall
            deploy_containers
            configure_monitoring
            configure_backup
            run_health_checks
            post_deployment_tasks
            ;;
        "staging")
            pre_deployment_checks
            setup_environment
            deploy_containers
            run_health_checks
            post_deployment_tasks
            ;;
        "development")
            setup_environment
            deploy_containers
            run_health_checks
            ;;
        *)
            error "Invalid environment: $DEPLOY_ENV. Use: production, staging, or development"
            ;;
    esac

    success "🎉 Deployment completed successfully!"
    log "📄 Deployment log: $LOG_FILE"
    log "🔗 Application URL: $(grep APP_URL .env | cut -d '=' -f2 | tr -d ' ')"
}

# Command line interface
case "${2:-deploy}" in
    "deploy")
        main
        ;;
    "rollback")
        rollback
        ;;
    "backup")
        backup_current_deployment
        ;;
    "health-check")
        run_health_checks
        ;;
    *)
        echo "Usage: $0 [environment] [command]"
        echo "Environments: production, staging, development"
        echo "Commands: deploy (default), rollback, backup, health-check"
        exit 1
        ;;
esac
