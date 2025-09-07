#!/bin/bash

# TPT Government Platform - Kubernetes Deployment Script
# This script deploys the complete TPT Government Platform to Kubernetes

set -e

# Configuration
NAMESPACE="tpt-gov"
RELEASE_NAME="tpt-gov-platform"
DOCKER_REGISTRY="your-registry.com"
DOCKER_REPO="tpt-gov-platform"
TAG="${1:-latest}"

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

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

    # Check if kubectl is installed
    if ! command -v kubectl &> /dev/null; then
        log_error "kubectl is not installed. Please install kubectl first."
        exit 1
    fi

    # Check if helm is installed
    if ! command -v helm &> /dev/null; then
        log_error "Helm is not installed. Please install Helm first."
        exit 1
    fi

    # Check if docker is installed
    if ! command -v docker &> /dev/null; then
        log_error "Docker is not installed. Please install Docker first."
        exit 1
    fi

    # Check Kubernetes connection
    if ! kubectl cluster-info &> /dev/null; then
        log_error "Cannot connect to Kubernetes cluster. Please check your kubeconfig."
        exit 1
    fi

    log_success "Prerequisites check passed"
}

# Create namespace
create_namespace() {
    log_info "Creating namespace: $NAMESPACE"

    kubectl create namespace $NAMESPACE --dry-run=client -o yaml | kubectl apply -f -

    # Label namespace for network policies
    kubectl label namespace $NAMESPACE name=$NAMESPACE --overwrite

    log_success "Namespace created"
}

# Setup Helm repositories
setup_helm_repos() {
    log_info "Setting up Helm repositories..."

    # Add required Helm repositories
    helm repo add ingress-nginx https://kubernetes.github.io/ingress-nginx
    helm repo add cert-manager https://charts.jetstack.io
    helm repo add prometheus-community https://prometheus-community.github.io/helm-charts
    helm repo add grafana https://grafana.github.io/helm-charts
    helm repo add elastic https://helm.elastic.co
    helm repo add external-secrets https://charts.external-secrets.io
    helm repo update

    log_success "Helm repositories configured"
}

# Install cert-manager for SSL certificates
install_cert_manager() {
    log_info "Installing cert-manager..."

    # Install cert-manager CRDs
    kubectl apply -f https://github.com/cert-manager/cert-manager/releases/download/v1.12.0/cert-manager.crds.yaml

    # Install cert-manager
    helm upgrade --install cert-manager cert-manager/cert-manager \
        --namespace cert-manager \
        --create-namespace \
        --version v1.12.0 \
        --set installCRDs=true \
        --wait

    # Create Let's Encrypt cluster issuer
    cat <<EOF | kubectl apply -f -
apiVersion: cert-manager.io/v1
kind: ClusterIssuer
metadata:
  name: letsencrypt-prod
spec:
  acme:
    server: https://acme-v02.api.letsencrypt.org/directory
    email: admin@tpt.gov.local
    privateKeySecretRef:
      name: letsencrypt-prod
    solvers:
    - http01:
        ingress:
          class: nginx
EOF

    log_success "Cert-manager installed"
}

# Install ingress controller
install_ingress_controller() {
    log_info "Installing NGINX Ingress Controller..."

    helm upgrade --install ingress-nginx ingress-nginx/ingress-nginx \
        --namespace ingress-nginx \
        --create-namespace \
        --version 4.7.1 \
        --set controller.replicaCount=2 \
        --set controller.metrics.enabled=true \
        --set controller.metrics.serviceMonitor.enabled=true \
        --set controller.metrics.serviceMonitor.namespace=monitoring \
        --set controller.config.use-forwarded-headers=true \
        --set controller.config.proxy-real-ip-cidr="0.0.0.0/0" \
        --set controller.config.use-gzip=true \
        --set controller.config.gzip-types="text/plain text/css application/json application/javascript text/xml application/xml application/xml+rss text/javascript" \
        --wait

    log_success "NGINX Ingress Controller installed"
}

# Install monitoring stack
install_monitoring() {
    log_info "Installing monitoring stack..."

    # Install Prometheus
    helm upgrade --install prometheus prometheus-community/prometheus \
        --namespace monitoring \
        --create-namespace \
        --version 19.3.0 \
        --set server.persistentVolume.enabled=true \
        --set server.persistentVolume.size=50Gi \
        --set server.retention=30d \
        --wait

    # Install Grafana
    helm upgrade --install grafana grafana/grafana \
        --namespace monitoring \
        --version 6.50.0 \
        --set persistence.enabled=true \
        --set persistence.size=10Gi \
        --set adminPassword='admin123!' \
        --set service.type=ClusterIP \
        --wait

    log_success "Monitoring stack installed"
}

# Install external secrets operator
install_external_secrets() {
    log_info "Installing External Secrets Operator..."

    helm upgrade --install external-secrets external-secrets/external-secrets \
        --namespace external-secrets-system \
        --create-namespace \
        --version 0.8.1 \
        --wait

    log_success "External Secrets Operator installed"
}

# Build and push Docker image
build_and_push_image() {
    log_info "Building and pushing Docker image..."

    # Build Docker image
    docker build -t $DOCKER_REGISTRY/$DOCKER_REPO:$TAG .

    # Push to registry
    docker push $DOCKER_REGISTRY/$DOCKER_REPO:$TAG

    log_success "Docker image built and pushed"
}

# Deploy application
deploy_application() {
    log_info "Deploying TPT Government Platform..."

    # Apply Kubernetes manifests in order
    kubectl apply -f k8s/pvc.yaml
    kubectl apply -f k8s/configmap.yaml
    kubectl apply -f k8s/secrets.yaml
    kubectl apply -f k8s/service.yaml
    kubectl apply -f k8s/deployment.yaml
    kubectl apply -f k8s/ingress.yaml
    kubectl apply -f k8s/hpa.yaml

    # Wait for deployment to be ready
    kubectl rollout status deployment/tpt-government-platform -n $NAMESPACE --timeout=600s

    log_success "Application deployed successfully"
}

# Setup network policies
setup_network_policies() {
    log_info "Setting up network policies..."

    cat <<EOF | kubectl apply -f -
apiVersion: networking.k8s.io/v1
kind: NetworkPolicy
metadata:
  name: tpt-gov-default-deny-all
  namespace: $NAMESPACE
spec:
  podSelector: {}
  policyTypes:
  - Ingress
  - Egress
---
apiVersion: networking.k8s.io/v1
kind: NetworkPolicy
metadata:
  name: tpt-gov-allow-internal
  namespace: $NAMESPACE
spec:
  podSelector: {}
  policyTypes:
  - Ingress
  - Egress
  ingress:
  - from:
    - namespaceSelector:
        matchLabels:
          name: $NAMESPACE
    - namespaceSelector:
        matchLabels:
          name: ingress-nginx
    - namespaceSelector:
        matchLabels:
          name: monitoring
  egress:
  - to:
    - namespaceSelector:
        matchLabels:
          name: $NAMESPACE
    - namespaceSelector:
        matchLabels:
          name: kube-system
  - to: []
    ports:
    - protocol: TCP
      port: 53
    - protocol: UDP
      port: 53
    - protocol: TCP
      port: 443
    - protocol: TCP
      port: 80
EOF

    log_success "Network policies configured"
}

# Setup RBAC
setup_rbac() {
    log_info "Setting up RBAC..."

    cat <<EOF | kubectl apply -f -
apiVersion: v1
kind: ServiceAccount
metadata:
  name: tpt-gov-service-account
  namespace: $NAMESPACE
  labels:
    app: tpt-government-platform
---
apiVersion: rbac.authorization.k8s.io/v1
kind: Role
metadata:
  name: tpt-gov-role
  namespace: $NAMESPACE
rules:
- apiGroups: [""]
  resources: ["pods", "services", "endpoints", "configmaps", "secrets"]
  verbs: ["get", "list", "watch"]
- apiGroups: ["apps"]
  resources: ["deployments", "replicasets"]
  verbs: ["get", "list", "watch"]
---
apiVersion: rbac.authorization.k8s.io/v1
kind: RoleBinding
metadata:
  name: tpt-gov-role-binding
  namespace: $NAMESPACE
subjects:
- kind: ServiceAccount
  name: tpt-gov-service-account
  namespace: $NAMESPACE
roleRef:
  kind: Role
  name: tpt-gov-role
  apiGroup: rbac.authorization.k8s.io
EOF

    log_success "RBAC configured"
}

# Run database migrations
run_migrations() {
    log_info "Running database migrations..."

    # Get database pod
    DB_POD=$(kubectl get pods -n $NAMESPACE -l app=mysql -o jsonpath='{.items[0].metadata.name}')

    if [ -z "$DB_POD" ]; then
        log_warning "Database pod not found. Skipping migrations."
        return
    fi

    # Copy migration files to pod
    kubectl cp src/php/migrations $NAMESPACE/$DB_POD:/tmp/migrations

    # Run migrations (this assumes you have a migration runner in your container)
    kubectl exec -n $NAMESPACE $DB_POD -- php artisan migrate --path=/tmp/migrations

    log_success "Database migrations completed"
}

# Setup monitoring dashboards
setup_monitoring_dashboards() {
    log_info "Setting up monitoring dashboards..."

    # Create Grafana datasource for Prometheus
    cat <<EOF | kubectl apply -f -
apiVersion: v1
kind: ConfigMap
metadata:
  name: grafana-datasources
  namespace: monitoring
  labels:
    grafana_datasource: "1"
data:
  prometheus.yml: |
    apiVersion: 1
    datasources:
    - name: Prometheus
      type: prometheus
      url: http://prometheus-server.monitoring.svc.cluster.local
      access: proxy
      isDefault: true
EOF

    log_success "Monitoring dashboards configured"
}

# Health check
health_check() {
    log_info "Performing health checks..."

    # Wait for all pods to be ready
    kubectl wait --for=condition=ready pod --all -n $NAMESPACE --timeout=300s

    # Check application health
    APP_URL=$(kubectl get ingress tpt-government-platform-ingress -n $NAMESPACE -o jsonpath='{.spec.rules[0].host}')
    if curl -f https://$APP_URL/health; then
        log_success "Application health check passed"
    else
        log_warning "Application health check failed"
    fi
}

# Main deployment function
main() {
    log_info "Starting TPT Government Platform deployment..."

    check_prerequisites
    create_namespace
    setup_helm_repos
    install_cert_manager
    install_ingress_controller
    install_monitoring
    install_external_secrets
    build_and_push_image
    setup_rbac
    setup_network_policies
    deploy_application
    run_migrations
    setup_monitoring_dashboards
    health_check

    log_success "🎉 TPT Government Platform deployment completed successfully!"
    log_info ""
    log_info "Application URLs:"
    log_info "  Main Application: https://tpt.gov.local"
    log_info "  API: https://api.tpt.gov.local"
    log_info "  Admin: https://admin.tpt.gov.local"
    log_info ""
    log_info "Monitoring:"
    log_info "  Grafana: http://grafana.monitoring.svc.cluster.local"
    log_info "  Prometheus: http://prometheus.monitoring.svc.cluster.local"
    log_info ""
    log_info "Next steps:"
    log_info "1. Update DNS records to point to the ingress load balancer"
    log_info "2. Configure SSL certificates"
    log_info "3. Set up backup and disaster recovery"
    log_info "4. Configure monitoring alerts"
}

# Handle command line arguments
case "${2:-}" in
    "check")
        check_prerequisites
        ;;
    "build")
        build_and_push_image
        ;;
    "deploy")
        deploy_application
        ;;
    "cleanup")
        log_info "Cleaning up deployment..."
        kubectl delete namespace $NAMESPACE --ignore-not-found=true
        log_success "Cleanup completed"
        ;;
    *)
        main
        ;;
esac
