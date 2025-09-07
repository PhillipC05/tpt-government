#!/bin/bash

# TPT Government Platform - Simple Deployment Script
# This script provides an easy way to deploy the platform using Docker Compose

set -e

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

# Configuration
COMPOSE_FILE="docker-compose.prod.yml"
ENV_FILE=".env"

# Logging functions
log_info() {
    echo -e "${BLUE}[INFO]${NC} $1"
}

log_success() {
    echo -e "${GREEN}[SUCCESS]${NC} $1"
}

log_warning() {
    echo -e "${YELLOW}[WARNING]${NC} $1"
}

log_error() {
    echo -e "${RED}[ERROR]${NC} $1"
}

# Check prerequisites
check_prerequisites() {
    log_info "Checking prerequisites..."

    if ! command -v docker &> /dev/null; then
        log_error "Docker is not installed. Please install Docker first."
        exit 1
    fi

    if ! command -v docker-compose &> /dev/null; then
        log_error "Docker Compose is not installed. Please install Docker Compose first."
        exit 1
    fi

    log_success "Prerequisites check passed"
}

# Setup environment
setup_environment() {
    log_info "Setting up environment..."

    if [ ! -f "$ENV_FILE" ]; then
        if [ -f ".env.example" ]; then
            cp .env.example .env
            log_warning "Created .env file from .env.example"
            log_warning "Please edit .env file with your actual configuration values"
            log_warning "Especially database passwords, JWT secrets, and API keys"
        else
            log_error ".env file not found and .env.example not available"
            exit 1
        fi
    fi

    # Create necessary directories
    mkdir -p logs nginx/sites-enabled ssl

    log_success "Environment setup completed"
}

# Build application
build_application() {
    log_info "Building application..."

    # Build Docker images
    docker-compose -f $COMPOSE_FILE build --no-cache

    log_success "Application built successfully"
}

# Start services
start_services() {
    log_info "Starting services..."

    # Start all services
    docker-compose -f $COMPOSE_FILE up -d

    # Wait for services to be healthy
    log_info "Waiting for services to be healthy..."
    sleep 30

    log_success "Services started successfully"
}

# Run database migrations
run_migrations() {
    log_info "Running database migrations..."

    # Run migrations through the app container
    docker-compose -f $COMPOSE_FILE exec -T app php artisan migrate --force

    log_success "Database migrations completed"
}

# Setup application
setup_application() {
    log_info "Setting up application..."

    # Generate application key if not set
    docker-compose -f $COMPOSE_FILE exec -T app php artisan key:generate --force

    # Clear and cache configuration
    docker-compose -f $COMPOSE_FILE exec -T app php artisan config:cache
    docker-compose -f $COMPOSE_FILE exec -T app php artisan route:cache
    docker-compose -f $COMPOSE_FILE exec -T app php artisan view:cache

    # Create storage link
    docker-compose -f $COMPOSE_FILE exec -T app php artisan storage:link

    log_success "Application setup completed"
}

# Health check
health_check() {
    log_info "Performing health checks..."

    # Check if services are running
    if docker-compose -f $COMPOSE_FILE ps | grep -q "Up"; then
        log_success "All services are running"
    else
        log_error "Some services failed to start"
        docker-compose -f $COMPOSE_FILE ps
        exit 1
    fi

    # Check application health
    if curl -f http://localhost/health &> /dev/null; then
        log_success "Application health check passed"
    else
        log_warning "Application health check failed - this may be normal if SSL is not configured"
    fi
}

# Show status
show_status() {
    log_info "Service Status:"
    docker-compose -f $COMPOSE_FILE ps

    log_info ""
    log_info "Useful commands:"
    log_info "  View logs: docker-compose -f $COMPOSE_FILE logs -f"
    log_info "  Stop services: docker-compose -f $COMPOSE_FILE down"
    log_info "  Restart services: docker-compose -f $COMPOSE_FILE restart"
    log_info "  Access container: docker-compose -f $COMPOSE_FILE exec app bash"
}

# Stop services
stop_services() {
    log_info "Stopping services..."
    docker-compose -f $COMPOSE_FILE down
    log_success "Services stopped"
}

# Clean up
cleanup() {
    log_info "Cleaning up..."
    docker-compose -f $COMPOSE_FILE down -v --rmi all
    log_success "Cleanup completed"
}

# Main deployment function
deploy() {
    log_info "🚀 Starting TPT Government Platform deployment..."

    check_prerequisites
    setup_environment
    build_application
    start_services
    run_migrations
    setup_application
    health_check
    show_status

    log_success "🎉 TPT Government Platform deployed successfully!"
    log_info ""
    log_info "Application URLs:"
    log_info "  Main Application: http://localhost"
    log_info "  API: http://localhost/api"
    log_info "  Admin: http://localhost/admin"
    log_info ""
    log_info "Next steps:"
    log_info "1. Update your DNS to point to this server"
    log_info "2. Configure SSL certificates for HTTPS"
    log_info "3. Set up monitoring and alerting"
    log_info "4. Configure backup and disaster recovery"
}

# Handle command line arguments
case "${1:-}" in
    "deploy")
        deploy
        ;;
    "start")
        start_services
        ;;
    "stop")
        stop_services
        ;;
    "restart")
        stop_services
        start_services
        ;;
    "status")
        show_status
        ;;
    "logs")
        docker-compose -f $COMPOSE_FILE logs -f
        ;;
    "shell")
        docker-compose -f $COMPOSE_FILE exec app bash
        ;;
    "migrate")
        run_migrations
        ;;
    "setup")
        setup_application
        ;;
    "cleanup")
        cleanup
        ;;
    "check")
        check_prerequisites
        ;;
    *)
        echo "TPT Government Platform - Deployment Script"
        echo ""
        echo "Usage: $0 [command]"
        echo ""
        echo "Commands:"
        echo "  deploy    - Full deployment (default)"
        echo "  start     - Start services"
        echo "  stop      - Stop services"
        echo "  restart   - Restart services"
        echo "  status    - Show service status"
        echo "  logs      - Show service logs"
        echo "  shell     - Access application container shell"
        echo "  migrate   - Run database migrations"
        echo "  setup     - Setup application"
        echo "  cleanup   - Clean up containers and volumes"
        echo "  check     - Check prerequisites"
        echo ""
        ;;
esac
