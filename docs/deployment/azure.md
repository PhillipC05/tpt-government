# 🔷 Azure Deployment Guide

This guide provides comprehensive instructions for deploying the TPT Government Platform on Microsoft Azure, offering excellent government compliance certifications and seamless integration with Microsoft 365 environments.

## 🎯 Why Azure for Government?

### ✅ **Strong Government Compliance**
- **FedRAMP High Authorized** - Highest security authorization level
- **DoD IL 5 Compliant** - Department of Defense compliance
- **Azure Government** - Isolated cloud for U.S. government
- **GCC High** - Government Community Cloud for contractors
- **ISO 27001 Certified** - Information security management
- **HIPAA Compliant** - Healthcare data protection

### 🏢 **Enterprise Integration**
- **99.99% uptime SLA** - Enterprise-grade reliability
- **60+ global regions** - Worldwide presence
- **Seamless Microsoft 365 integration** - Single sign-on with Office 365
- **Active Directory integration** - Native AD support
- **Azure DevOps integration** - Complete CI/CD pipeline

### 💰 **Cost Optimization**
- **Free tier available** - Get started with no cost
- **Reserved instances** - Up to 72% savings
- **Azure Hybrid Benefit** - Use existing Windows licenses
- **Dev/Test pricing** - 50% discount for development
- **Spot instances** - Up to 90% savings

## 🚀 Quick Start (15 Minutes)

### Step 1: Azure Account Setup

1. **Create Azure Account**
   - Go to [azure.microsoft.com](https://azure.microsoft.com)
   - Sign up for free account
   - Verify email and phone
   - Set up billing alerts

2. **Enable Multi-Factor Authentication**
   - Go to Azure Active Directory → Users
   - Select your account → Authentication methods
   - Enable Microsoft Authenticator or phone

3. **Create Resource Group**
   ```bash
   # Create resource group for TPT Platform
   az group create --name tpt-gov-rg --location eastus
   ```

### Step 2: Launch Virtual Machine

**Option A: Azure Portal (GUI)**

1. **Go to Virtual Machines**
   - Search for "Virtual Machines" in Azure Portal
   - Click "Create" → "Azure virtual machine"

2. **Configure Basic Settings**
   - **Resource group**: tpt-gov-rg
   - **VM name**: tpt-gov-vm
   - **Region**: East US (or your preferred region)
   - **Image**: Ubuntu Server 22.04 LTS

3. **Choose Size**
   ```
   Small Agency: Standard_B2s (2 vCPU, 4GB RAM) - ~$30/month
   Medium Agency: Standard_B4ms (4 vCPU, 16GB RAM) - ~$120/month
   Large Agency: Standard_B8ms (8 vCPU, 32GB RAM) - ~$240/month
   ```

4. **Configure Networking**
   - **Virtual network**: Create new (10.0.0.0/16)
   - **Subnet**: default (10.0.0.0/24)
   - **Public IP**: Create new
   - **NSG**: Create new with rules:
     ```
     SSH: 22 (your IP only)
     HTTP: 80 (Internet)
     HTTPS: 443 (Internet)
     ```

5. **Launch VM**

**Option B: Azure CLI (Automated)**

```bash
# Install Azure CLI
curl -sL https://aka.ms/InstallAzureCLIDeb | sudo bash

# Login to Azure
az login

# Create VM
az vm create \
  --resource-group tpt-gov-rg \
  --name tpt-gov-vm \
  --image Ubuntu2204 \
  --admin-username azureuser \
  --generate-ssh-keys \
  --size Standard_B2s \
  --public-ip-sku Standard
```

### Step 3: Deploy Application

```bash
# Connect to VM
ssh azureuser@your-vm-public-ip

# Update system
sudo apt update && sudo apt upgrade -y

# Install Docker and Docker Compose
curl -fsSL https://get.docker.com -o get-docker.sh
sudo sh get-docker.sh
sudo curl -L "https://github.com/docker/compose/releases/download/v2.20.0/docker-compose-$(uname -s)-$(uname -m)" -o /usr/local/bin/docker-compose
sudo chmod +x /usr/local/bin/docker-compose

# Clone and deploy
git clone https://github.com/your-org/tpt-gov-platform.git
cd tpt-gov-platform
./deploy.sh deploy
```

## 🏗️ Production Architecture on Azure

### Single Region Setup (Most Agencies)

```
┌─────────────────────────────────────────────────┐
│              Azure Region (East US)             │
├─────────────────────────────────────────────────┤
│  ┌─────────────┐ ┌─────────────┐ ┌─────────────┐ │
│  │ Application │ │   Azure     │ │   Azure     │ │
│  │   Gateway   │ │   Database  │ │   Cache     │ │
│  │             │ │   (MySQL)   │ │   (Redis)   │ │
│  └─────────────┘ └─────────────┘ └─────────────┘ │
│  ┌─────────────┐ ┌─────────────┐ ┌─────────────┐ │
│  │   Blob      │ │   CDN       │ │   Monitor   │ │
│  │  Storage    │ │             │ │             │ │
│  └─────────────┘ └─────────────┘ └─────────────┘ │
└─────────────────────────────────────────────────┘
```

### High Availability Setup (Large Agencies)

```
┌─────────────────┐    ┌─────────────────┐
│   East US       │    │   West US      │
│   (Primary)     │    │   (DR)         │
├─────────────────┤    ├─────────────────┤
│  App Gateway    │    │  App Gateway   │
│  VM Scale Set   │    │  VM Scale Set  │
│  Azure Database │    │  Azure Database│
│  Blob Storage   │    │  Blob Storage  │
└─────────────────┘    └─────────────────┘
         │                        │
         └──── Traffic Manager ───┘
```

## 📋 Detailed Setup Guide

### 1. Resource Group and Networking

**Create Resource Group**

1. **Go to Resource Groups**
   - Create new resource group: `tpt-gov-rg`
   - Location: East US (or your preferred region)

**Create Virtual Network**

1. **Go to Virtual Networks**
2. Create VNet:
   - **Name**: tpt-gov-vnet
   - **Address space**: 10.0.0.0/16
   - **Subnet**: default (10.0.0.0/24)

**Create Network Security Group**

1. **Go to Network Security Groups**
2. Create NSG with rules:
   ```
   Priority 100: SSH (22) - Your IP only
   Priority 101: HTTP (80) - Any
   Priority 102: HTTPS (443) - Any
   Priority 103: MySQL (3306) - VNet only
   ```

### 2. Application Gateway (Load Balancer)

**Create Application Gateway**

1. **Go to Application Gateways**
2. Create gateway:
   - **Name**: tpt-gov-appgw
   - **Tier**: Standard V2
   - **Capacity**: 2 instances (autoscaling enabled)

3. **Configure Frontend**
   - Public IP address
   - SSL certificate (upload or use Azure Key Vault)

4. **Configure Backend**
   - Target: Your VM private IP
   - Health probe: `/health` endpoint
   - HTTP settings: Port 80

5. **Configure Rules**
   - Basic routing rule
   - SSL termination enabled

### 3. Azure Database for MySQL

**Create MySQL Database**

1. **Go to Azure Database for MySQL**
2. Create flexible server:
   - **Server name**: tpt-gov-db
   - **Admin username**: tpt_gov_admin
   - **Version**: MySQL 8.0
   - **Compute + storage**: Burstable, B1ms (1 vCore, 2GB RAM) - ~$20/month

3. **Configure Networking**
   - **Connectivity method**: Private access (VNet integration)
   - **Virtual network**: tpt-gov-vnet
   - **Firewall rules**: Allow Azure services

4. **Configure Security**
   - **SSL enforcement**: Required
   - **Backup**: Geo-redundant, 7-day retention

### 4. Azure Cache for Redis

**Create Redis Cache**

1. **Go to Azure Cache for Redis**
2. Create cache:
   - **Name**: tpt-gov-redis
   - **Tier**: Basic (C0) - Free tier available
   - **Location**: East US

3. **Configure Advanced Settings**
   - **Non-SSL port**: Disabled (security)
   - **Minimum TLS version**: 1.2
   - **Firewall**: Selected networks only

### 5. Azure Blob Storage

**Create Storage Account**

1. **Go to Storage Accounts**
2. Create account:
   - **Name**: tptgovstorage[unique]
   - **Account kind**: StorageV2 (general purpose v2)
   - **Performance**: Standard
   - **Redundancy**: Geo-redundant storage (GRS)

3. **Create Blob Container**
   - **Name**: documents
   - **Public access level**: Private

4. **Configure CORS**
   ```json
   {
     "origins": ["https://yourdomain.gov.local"],
     "methods": ["GET", "POST", "PUT", "DELETE"],
     "allowedHeaders": ["*"],
     "exposedHeaders": ["*"],
     "maxAgeInSeconds": 3600
   }
   ```

### 6. Azure Front Door (CDN)

**Create Front Door Profile**

1. **Go to Azure Front Door**
2. Create profile:
   - **Name**: tpt-gov-frontdoor
   - **Tier**: Standard

3. **Configure Origin**
   - **Origin type**: Custom
   - **Origin host**: Your Application Gateway
   - **Origin host header**: yourdomain.gov.local

4. **Configure Routing Rules**
   - Route static content to CDN
   - Route API calls to backend
   - Enable caching for static assets

## 🔧 Configuration Files

### Environment Configuration (.env)

```bash
# Application
APP_NAME="Your Government Agency"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://youragency.gov.local

# Database (Azure MySQL)
DB_HOST=tpt-gov-db.mysql.database.azure.com
DB_PORT=3306
DB_NAME=tpt_gov_db
DB_USER=tpt_gov_admin@tpt-gov-db
DB_PASSWORD=your-azure-db-password

# Redis (Azure Cache)
REDIS_HOST=tpt-gov-redis.redis.cache.windows.net
REDIS_PORT=6380
REDIS_PASSWORD=your-redis-access-key

# Azure Blob Storage
AZURE_STORAGE_ACCOUNT=tptgovstorage
AZURE_STORAGE_KEY=your-storage-account-key
AZURE_CONTAINER=documents

# Security
JWT_SECRET=your-256-bit-jwt-secret
ENCRYPTION_KEY=your-256-bit-encryption-key

# Email (SendGrid or Office 365)
MAIL_MAILER=smtp
MAIL_HOST=smtp.sendgrid.net
MAIL_PORT=587
MAIL_USERNAME=apikey
MAIL_PASSWORD=your-sendgrid-api-key
```

### Azure CLI Configuration

```bash
# Login to Azure
az login

# Set subscription
az account set --subscription "your-subscription-id"

# Create service principal for automation
az ad sp create-for-rbac --name "tpt-gov-sp" --role contributor \
    --scopes /subscriptions/your-subscription-id/resourceGroups/tpt-gov-rg
```

## 📊 Scaling and Performance

### Virtual Machine Scale Sets

**Create VM Scale Set**

1. **Go to Virtual Machine Scale Sets**
2. Create scale set:
   - **Name**: tpt-gov-vmss
   - **Image**: Ubuntu Server 22.04 LTS
   - **Size**: Standard_B2s
   - **Capacity**: 2-10 instances

3. **Configure Scaling**
   - **Scale-out rule**: CPU > 70% for 5 minutes
   - **Scale-in rule**: CPU < 30% for 10 minutes
   - **Custom metrics**: Application-specific scaling

### Azure Monitor

**Set up Monitoring**

1. **Go to Azure Monitor**
2. Create dashboard:
   - VM performance metrics
   - Application insights
   - Database performance
   - Network monitoring

3. **Configure Alerts**
   - CPU usage > 80%
   - Memory usage > 85%
   - HTTP 5xx errors > 5/minute
   - Database connections > 80%

## 🔒 Security Best Practices

### Identity and Access Management

**Azure Active Directory Integration**

1. **Go to Azure Active Directory**
2. Create enterprise application:
   - **Name**: TPT Government Platform
   - **Sign-on URL**: https://yourdomain.gov.local

3. **Configure SSO**
   - SAML 2.0 integration
   - User provisioning
   - Group-based access control

**Role-Based Access Control**

```bash
# Assign roles using Azure CLI
az role assignment create \
  --assignee your-user-id \
  --role "Contributor" \
  --scope /subscriptions/your-subscription/resourceGroups/tpt-gov-rg
```

### Network Security

**Azure Firewall**

1. **Go to Azure Firewall**
2. Create firewall:
   - **Name**: tpt-gov-firewall
   - **Tier**: Standard

3. **Configure Rules**
   - Application rules for HTTP/HTTPS
   - Network rules for internal traffic
   - NAT rules for inbound connections

**Azure DDoS Protection**

1. **Go to DDoS Protection**
2. Enable Standard tier protection
3. Configure alerts and monitoring

### Data Protection

**Azure Key Vault**

1. **Go to Key Vault**
2. Create vault:
   - **Name**: tpt-gov-keyvault
   - **Pricing tier**: Standard

3. **Store Secrets**
   - Database passwords
   - API keys
   - SSL certificates

**Azure Backup**

1. **Go to Backup Center**
2. Configure backup policies:
   - VM backup: Daily, 30-day retention
   - Database backup: Hourly, 7-day retention
   - File backup: Weekly, 90-day retention

## 💰 Cost Optimization

### Monthly Cost Breakdown

**Small Agency Setup (~$100/month)**
- VM (B2s): $30/month
- Azure Database: $20/month
- Application Gateway: $25/month
- Blob Storage (100GB): $2/month
- Azure Monitor: $10/month

**Medium Agency Setup (~$300/month)**
- VM Scale Set (2-4 instances): $120/month
- Azure Database: $50/month
- Application Gateway: $25/month
- Blob Storage (1TB): $20/month
- Azure Front Door: $30/month

**Large Agency Setup (~$600/month)**
- VM Scale Set (4-10 instances): $300/month
- Azure Database (high availability): $100/month
- Application Gateway: $50/month
- Advanced monitoring: $50/month
- Backup storage: $50/month

### Cost Saving Strategies

1. **Reserved Instances**
   - 1-year reservation: 40% savings
   - 3-year reservation: 60% savings

2. **Azure Hybrid Benefit**
   - Use existing Windows licenses
   - Up to 40% savings on Windows VMs

3. **Dev/Test Pricing**
   - 50% discount for development environments
   - Separate subscription for dev/test

4. **Spot Instances**
   - Up to 90% savings for non-critical workloads
   - Use for batch processing and testing

## 📈 Monitoring and Logging

### Azure Monitor

**Application Insights**

1. **Go to Application Insights**
2. Create resource:
   - **Name**: tpt-gov-appinsights
   - **Application type**: Web application

3. **Configure Monitoring**
   - Request/response tracking
   - Dependency monitoring
   - Exception tracking
   - Performance metrics

**Log Analytics**

1. **Go to Log Analytics**
2. Create workspace:
   - **Name**: tpt-gov-loganalytics
   - **Pricing tier**: Per GB

3. **Configure Data Collection**
   - VM logs and metrics
   - Application logs
   - Database logs
   - Network logs

### Azure Security Center

**Enable Security Monitoring**

1. **Go to Security Center**
2. Enable Azure Defender for:
   - Virtual machines
   - Databases
   - Storage accounts
   - Key vaults

3. **Configure Security Policies**
   - CIS benchmarks
   - NIST frameworks
   - Custom security policies

## 🚨 Troubleshooting

### Common Issues

**❌ VM Connection Failed**
```bash
# Check NSG rules
az network nsg rule list --resource-group tpt-gov-rg --nsg-name tpt-gov-nsg

# Verify VM status
az vm list --resource-group tpt-gov-rg --show-details
```

**❌ Application Gateway Health Checks Failing**
```bash
# Check backend health
az network application-gateway show-backend-health \
  --resource-group tpt-gov-rg \
  --name tpt-gov-appgw

# Verify application health endpoint
curl http://localhost/health
```

**❌ Database Connection Issues**
```bash
# Test database connectivity
mysql -h tpt-gov-db.mysql.database.azure.com \
  -u tpt_gov_admin@tpt-gov-db \
  -p tpt_gov_db

# Check firewall rules
az mysql server firewall-rule list \
  --resource-group tpt-gov-rg \
  --server-name tpt-gov-db
```

**❌ Blob Storage Access Denied**
```bash
# Verify storage account keys
az storage account keys list \
  --resource-group tpt-gov-rg \
  --account-name tptgovstorage

# Test storage access
az storage blob list \
  --account-name tptgovstorage \
  --container-name documents
```

## 🎯 Success Metrics

Track these KPIs for successful Azure deployment:

**📈 Performance**
- Page load time < 2 seconds
- API response time < 500ms
- Error rate < 0.1%
- Uptime > 99.9%

**💰 Cost Efficiency**
- Cost per user < $4/month
- Resource utilization > 70%
- Reserved instance usage > 80%
- Monthly cost variance < 10%

**🔒 Security**
- All security center recommendations addressed
- No security incidents
- Compliance audit success rate = 100%
- Incident response time < 15 minutes

## 🚀 Advanced Azure Features

### Azure Government Cloud

**For Federal Agencies**
- Isolated Azure environment for U.S. government
- FedRAMP High authorization
- DoD IL 5 compliance
- Data sovereignty guarantee

**Setup Process**
1. Apply for Azure Government access
2. Create Government subscription
3. Deploy using same instructions
4. Additional compliance configurations

### Azure Arc

**For Hybrid Deployments**
- Manage on-premises and cloud resources together
- Unified security and compliance
- Centralized monitoring and governance
- Consistent deployment experience

### Azure Policy

**For Enterprise Governance**
- Automated policy enforcement
- Compliance monitoring
- Resource governance
- Custom policy definitions

---

## 🎉 Ready to Deploy on Azure?

**Follow these steps:**

1. **Create Azure Account** (5 minutes)
2. **Set up Resource Group** (5 minutes)
3. **Launch Virtual Machine** (5 minutes)
4. **Configure Azure Database** (10 minutes)
5. **Deploy Application** (5 minutes)
6. **Set up Application Gateway** (10 minutes)

**Total Time: ~40 minutes**

Your government platform will be running on Microsoft's enterprise-grade cloud!

---

[← AWS Guide](aws.md) | [Getting Started](../getting-started.md) | [Google Cloud Guide](google-cloud.md)
