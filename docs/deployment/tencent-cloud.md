# 🇨🇳 Tencent Cloud Deployment Guide

This guide provides comprehensive instructions for deploying the TPT Government Platform on Tencent Cloud, offering robust infrastructure for China and international deployments with strong compliance certifications and competitive pricing.

## 🎯 Why Tencent Cloud for Government?

### ✅ **China & International Compliance**
- **Multi-Level Protection Scheme (MLPS)** - China's highest security certification
- **Trusted Cloud Certification** - Government-approved cloud platform
- **ISO 27001 Certified** - Information security management
- **GDPR Compliant** - European data protection
- **CSA STAR Certified** - Cloud security alliance

### 🌏 **Global & China Focus**
- **Extensive coverage** - 20+ regions worldwide including China
- **China data residency** - Meet local data requirements
- **Multi-language support** - Chinese and English interfaces
- **24/7 support** - Global and local technical assistance
- **Hybrid cloud** - Connect with on-premises infrastructure

### 💰 **Competitive Pricing**
- **Cost-effective** - Lower prices than Western providers
- **Pay-as-you-go** - No long-term commitments
- **Volume discounts** - Automatic savings for consistent usage
- **Free tier available** - Get started with no cost
- **Regional optimization** - Pricing based on location

## 🚀 Quick Start (15 Minutes)

### Step 1: Tencent Cloud Account Setup

1. **Create Tencent Cloud Account**
   - Go to [cloud.tencent.com](https://cloud.tencent.com)
   - Sign up for free account
   - Verify email and phone
   - Complete real-name verification (required for China regions)

2. **Set up Billing**
   - Add payment method (WeChat Pay, credit card, or bank transfer)
   - Enable free trial credits (¥100+ available)
   - Set billing alerts and budget controls

3. **Install Tencent Cloud CLI**
   ```bash
   # Install CLI
   curl -fsSL https://cli.tencentcloudapi.com/install.sh | sh

   # Configure
   tccli configure
   ```

### Step 2: Launch CVM Instance

**Option A: Tencent Cloud Console (GUI)**

1. **Go to Cloud Virtual Machine → Instances**
   - Click "Create"
   - Choose region (Singapore, Hong Kong, or Shanghai for China)

2. **Choose Instance Type**
   ```
   Standard S5: 2 vCPU, 2GB RAM - ~$20/month (Singapore)
   Standard S5: 2 vCPU, 4GB RAM - ~$35/month (Singapore)
   Standard S5: 4 vCPU, 8GB RAM - ~$70/month (Singapore)
   Standard S5: 8 vCPU, 16GB RAM - ~$140/month (Singapore)
   ```

3. **Configure Basic Settings**
   - **Instance name**: tpt-gov-server
   - **Image**: Ubuntu Server 22.04 LTS
   - **System disk**: 50GB SSD
   - **Data disk**: Optional additional storage

4. **Configure Networking**
   - **Network**: Create VPC
   - **Subnet**: Auto-create
   - **Security group**: Create new
   - **Public IP**: Allocate

5. **Configure Security Group**
   ```
   Inbound rules:
   - SSH (22) from your IP
   - HTTP (80) from 0.0.0.0/0
   - HTTPS (443) from 0.0.0.0/0
   ```

6. **Launch Instance**

**Option B: Tencent Cloud CLI (Automated)**

```bash
# Create VPC first
tccli vpc CreateVpc --VpcName tpt-gov-vpc --CidrBlock 10.0.0.0/16

# Create subnet
tccli vpc CreateSubnet --VpcId vpc-id --SubnetName tpt-gov-subnet \
  --CidrBlock 10.0.0.0/24 --Zone ap-singapore-1

# Create security group
tccli cvm CreateSecurityGroup --GroupName tpt-gov-sg --GroupDescription "TPT Gov Security Group"

# Create instance
tccli cvm RunInstances --InstanceName tpt-gov-server \
  --InstanceType S5.MEDIUM4 \
  --ImageId img-22tl60va \
  --SecurityGroupIds '["sg-id"]' \
  --LoginSettings '{"Password":"your-password"}' \
  --InternetAccessible '{"InternetMaxBandwidthOut":100}'
```

### Step 3: Deploy Application

```bash
# Connect to your instance
ssh root@your-tencent-ip

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

## 🏗️ Production Architecture on Tencent Cloud

### Single Region Setup (Most Agencies)

```
┌─────────────────────────────────────────────────┐
│            Tencent Cloud Region (Singapore)     │
├─────────────────────────────────────────────────┤
│  ┌─────────────┐ ┌─────────────┐ ┌─────────────┐ │
│  │   CLB       │ │   CVM       │ │   TDSQL     │ │
│  │  (Load      │ │  (Server)   │ │  (MySQL)    │ │
│  │ Balancer)   │ │             │ │            │ │
│  └─────────────┘ └─────────────┘ └─────────────┘ │
│  ┌─────────────┐ ┌─────────────┐ ┌─────────────┐ │
│  │   Redis     │ │   COS       │ │   CDN       │ │
│  │  (Cache)    │ │  (Storage)  │ │             │ │
│  └─────────────┘ └─────────────┘ └─────────────┘ │
└─────────────────────────────────────────────────┘
```

### Multi-Region Setup (Large Agencies)

```
┌─────────────────┐    ┌─────────────────┐
│   Singapore     │    │   Hong Kong     │
│   (Primary)     │    │   (DR)          │
├─────────────────┤    ├─────────────────┤
│  CLB → CVM → TDSQL│   │  CLB → CVM → TDSQL│
│  Redis           │    │  Redis           │
│  COS             │    │  COS (Replica)   │
└─────────────────┘    └─────────────────┘
         │                        │
         └──── Global Traffic Manager ───┘
```

## 📋 Detailed Setup Guide

### 1. VPC and Networking

**Create VPC (Best Practice)**

1. **Go to Virtual Private Cloud → VPC**
2. Create VPC:
   - **Name**: tpt-gov-vpc
   - **IPv4 CIDR**: 10.0.0.0/16
   - **Region**: Singapore

3. **Create Subnet**
   - **Name**: tpt-gov-subnet
   - **Zone**: Singapore Zone 1
   - **IPv4 CIDR**: 10.0.0.0/24

4. **Create NAT Gateway**
   - For outbound internet access from private subnets
   - Attach to VPC and subnet

### 2. Security Groups

**Create Security Group**

1. **Go to Cloud Virtual Machine → Security Groups**
2. Create security group:
   - **Name**: tpt-gov-sg
   - **Project**: Default project

3. **Configure Rules**
   ```
   Inbound:
   - SSH (22) - Source: your-office-ip/32
   - HTTP (80) - Source: 0.0.0.0/0
   - HTTPS (443) - Source: 0.0.0.0/0
   - MySQL (3306) - Source: 10.0.0.0/16 (VPC internal)

   Outbound:
   - All protocols - Destination: 0.0.0.0/0
   ```

### 3. Cloud Load Balancer (CLB)

**Create CLB Instance**

1. **Go to Cloud Load Balancer → Instances**
2. Create CLB:
   - **Name**: tpt-gov-clb
   - **Region**: Singapore
   - **Type**: Public network
   - **Network**: tpt-gov-vpc

3. **Configure Listeners**
   - **HTTP listener**: Port 80
   - **HTTPS listener**: Port 443 (upload SSL certificate)

4. **Configure Backend Servers**
   - Add your CVM instances
   - Health check: `/health` endpoint
   - Session persistence: Disabled

### 4. TencentDB for MySQL (TDSQL)

**Create TDSQL Instance**

1. **Go to TencentDB → MySQL**
2. Create instance:
   - **Engine**: MySQL 8.0
   - **Architecture**: High-availability
   - **Instance spec**: 1-core 2GB - ~$25/month

3. **Configure Network**
   - **VPC**: tpt-gov-vpc
   - **Subnet**: tpt-gov-subnet
   - **Security group**: tpt-gov-sg

4. **Configure Account**
   - **Database account**: tpt_gov_user
   - **Password**: Secure password
   - **Privileges**: Read/Write

5. **Configure Backup**
   - **Backup cycle**: Daily
   - **Retention period**: 7 days
   - **Backup time**: 02:00-06:00

### 5. TencentDB Redis

**Create Redis Instance**

1. **Go to TencentDB → Redis**
2. Create instance:
   - **Engine**: Redis 6.2
   - **Architecture**: Cluster
   - **Instance spec**: 1GB memory - ~$30/month

3. **Configure Network**
   - **VPC**: tpt-gov-vpc
   - **Subnet**: tpt-gov-subnet

4. **Configure Security**
   - **Password**: Enable
   - **SSL encryption**: Enable

### 6. Cloud Object Storage (COS)

**Create COS Bucket**

1. **Go to Cloud Object Storage → Buckets**
2. Create bucket:
   - **Bucket name**: tpt-gov-documents-[account-id]
   - **Region**: Singapore
   - **Access permission**: Private

3. **Configure CORS**
   ```json
   [
     {
       "AllowedOrigins": ["https://yourdomain.gov.local"],
       "AllowedMethods": ["GET", "POST", "PUT", "DELETE"],
       "AllowedHeaders": ["*"],
       "MaxAgeSeconds": 3600
     }
   ]
   ```

### 7. Content Delivery Network (CDN)

**Create CDN Domain**

1. **Go to Content Delivery Network → Domain Management**
2. Add domain:
   - **Domain**: cdn.yourdomain.gov.local
   - **Origin type**: IP
   - **Origin address**: Your CLB IP

3. **Configure Caching**
   - **Cache expiration**: 1 year for static files
   - **HTTPS configuration**: Enable
   - **Compression**: Enable

## 🔧 Configuration Files

### Environment Configuration (.env)

```bash
# Application
APP_NAME="Your Government Agency"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://youragency.gov.local

# Database (TencentDB MySQL)
DB_HOST=cdb-uf6w8m123456.tencentcdb.com
DB_PORT=3306
DB_NAME=tpt_gov_db
DB_USER=tpt_gov_user
DB_PASSWORD=your-tdsql-password

# Redis (TencentDB Redis)
REDIS_HOST=crs-uf6w8m123456.tencentcdb.com
REDIS_PORT=6379
REDIS_PASSWORD=your-redis-password

# COS Storage
COS_SECRET_ID=your-cos-secret-id
COS_SECRET_KEY=your-cos-secret-key
COS_REGION=ap-singapore
COS_BUCKET=tpt-gov-documents

# Security
JWT_SECRET=your-256-bit-jwt-secret
ENCRYPTION_KEY=your-256-bit-encryption-key

# Email (SendGrid or Tencent Cloud SES)
MAIL_MAILER=smtp
MAIL_HOST=smtp.sendgrid.net
MAIL_PORT=587
MAIL_USERNAME=apikey
MAIL_PASSWORD=your-sendgrid-api-key
```

### Tencent Cloud CLI Configuration

```bash
# Configure CLI
tccli configure

# Set default region
tccli configure set --region ap-singapore

# Create CAM user for application
tccli cam CreateUser --Name tpt-gov-user --ConsoleLogin 0

# Attach policies
tccli cam AttachUserPolicy \
  --UserName tpt-gov-user \
  --PolicyId QcloudCOSFullAccess
```

## 📊 Scaling and Performance

### Auto Scaling

**Create Scaling Group**

1. **Go to Auto Scaling → Scaling Groups**
2. Create scaling group:
   - **Name**: tpt-gov-asg
   - **Min instances**: 2
   - **Max instances**: 10
   - **Desired instances**: 3

3. **Configure Scaling Rules**
   - **Scale-out rule**: CPU > 70% for 5 minutes
   - **Scale-in rule**: CPU < 30% for 10 minutes
   - **Cooldown time**: 300 seconds

### Cloud Monitor

**Set up Monitoring**

1. **Go to Cloud Monitor → Dashboard**
2. Create dashboard:
   - CVM instance metrics
   - CLB performance
   - TDSQL database metrics
   - Redis cache metrics

3. **Configure Alerts**
   - CPU usage > 80%
   - Memory usage > 85%
   - HTTP 5xx errors > 5/minute
   - Database connections > 80%

## 🔒 Security Best Practices

### Identity and Access Management

**Cloud Access Management (CAM)**

```bash
# Create CAM user
tccli cam CreateUser --Name tpt-gov-app-user --ConsoleLogin 0

# Create custom policy
tccli cam CreatePolicy --PolicyName tpt-gov-policy \
  --PolicyDocument '{
    "version": "2.0",
    "statement": [
      {
        "effect": "allow",
        "action": [
          "cvm:DescribeInstances",
          "cos:GetObject",
          "cos:PutObject"
        ],
        "resource": "*"
      }
    ]
  }'

# Attach policy
tccli cam AttachUserPolicy \
  --UserName tpt-gov-app-user \
  --PolicyName tpt-gov-policy
```

### Network Security

**Web Application Firewall (WAF)**

1. **Go to Web Application Firewall**
2. Create instance:
   - **Name**: tpt-gov-waf
   - **Edition**: Advanced

3. **Configure Protection**
   - Enable OWASP rules
   - Configure rate limiting
   - Set up bot management
   - Enable SSL/TLS protection

**DDoS Protection**

1. **Go to Anti-DDoS → Instances**
2. Enable Anti-DDoS protection:
   - **Type**: Anti-DDoS Advanced
   - **Protection**: 100 Gbps

### Data Protection

**Key Management Service (KMS)**

1. **Go to Key Management Service**
2. Create key:
   - **Key name**: tpt-gov-encryption-key
   - **Key usage**: Encrypt/Decrypt

3. **Configure Automatic Rotation**
   - Enable key rotation
   - Set rotation period: 365 days

**Data Encryption**

1. **TDSQL Encryption**
   - Enable TDE (Transparent Data Encryption)
   - Use KMS key for encryption

2. **COS Encryption**
   - Enable server-side encryption
   - Use KMS-managed keys

## 💰 Cost Optimization

### Monthly Cost Breakdown

**Small Agency Setup (~$60/month)**
- CVM (S5): $35/month
- TDSQL (MySQL): $25/month
- CLB: Free (first 15 LCUs)
- COS (100GB): $2/month
- Total: ~$60/month

**Medium Agency Setup (~$150/month)**
- CVM Auto Scaling (2-4 instances): $70/month
- TDSQL (MySQL): $50/month
- Redis: $30/month
- CLB: $10/month
- COS (1TB): $20/month
- Total: ~$150/month

**Large Agency Setup (~$300/month)**
- CVM Auto Scaling (4-10 instances): $140/month
- TDSQL (MySQL High Availability): $100/month
- Redis Cluster: $60/month
- CLB: $20/month
- CDN: $30/month
- Total: ~$300/month

### Cost Saving Strategies

1. **Reserved Instances**
   - 1-year reservation: 30% savings
   - 3-year reservation: 50% savings

2. **Resource Optimization**
   - Use burstable instances for variable workloads
   - Enable auto-scaling to match demand
   - Use spot instances for non-critical workloads

3. **Storage Optimization**
   - Use COS Infrequent Access for old data
   - Enable data compression
   - Set up lifecycle policies

4. **Network Optimization**
   - Use CDN to reduce origin requests
   - Optimize images and static content
   - Use regional data transfer

## 📈 Monitoring and Logging

### Cloud Monitor

**Comprehensive Monitoring**

1. **Go to Cloud Monitor → Dashboard**
2. Create custom dashboard:
   - Infrastructure metrics
   - Application performance
   - Security monitoring
   - Cost monitoring

### Cloud Log Service (CLS)

**Centralized Logging**

1. **Go to Cloud Log Service → Log Groups**
2. Create log group:
   - **Log group name**: tpt-gov-logs
   - **Region**: Singapore

3. **Configure Log Topics**
   - Application logs
   - System logs
   - Security logs
   - Access logs

### Audit

**Cloud Audit**

1. **Go to Cloud Audit → Audit Logs**
2. Enable audit logging:
   - **Trail name**: tpt-gov-audit
   - **COS bucket**: tpt-gov-logs

3. **Configure Events**
   - All API calls
   - Console operations
   - Resource changes

## 🚨 Troubleshooting

### Common Issues

**❌ SSH Connection Failed**
```bash
# Check security group rules
tccli cvm DescribeSecurityGroups --Region ap-singapore

# Verify instance status
tccli cvm DescribeInstances --Region ap-singapore

# Check VPC configuration
tccli vpc DescribeVpcs --Region ap-singapore
```

**❌ CLB Health Checks Failing**
```bash
# Check backend server health
tccli clb DescribeTargetHealth \
  --LoadBalancerId your-clb-id \
  --Region ap-singapore

# Verify application health endpoint
curl http://localhost/health
```

**❌ Database Connection Issues**
```bash
# Test TDSQL connectivity
mysql -h cdb-uf6w8m123456.tencentcdb.com \
  -u tpt_gov_user -p tpt_gov_db

# Check security group
tccli cdb DescribeDBSecurityGroups \
  --InstanceId cdb-uf6w8m123456 \
  --Region ap-singapore
```

**❌ COS Access Denied**
```bash
# Verify CAM permissions
tccli cam ListAttachedUserPolicies --UserName tpt-gov-user

# Test COS access
tccli cos ListBuckets
```

### Getting Help

1. **Tencent Cloud Support**: 24/7 global support
2. **Documentation**: cloud.tencent.com/document
3. **Community Forum**: cloud.tencent.com/developer
4. **Status Page**: status.cloud.tencent.com

## 🎯 Success Metrics

Track these KPIs for successful Tencent Cloud deployment:

**📈 Performance**
- Page load time < 2 seconds
- API response time < 500ms
- Error rate < 0.1%
- Uptime > 99.9%

**💰 Cost Efficiency**
- Cost per user < $2/month
- Resource utilization > 70%
- Reserved instance usage > 30%
- Monthly cost variance < 10%

**🔒 Security**
- All security scans pass
- No unauthorized access incidents
- Compliance audit success rate = 100%
- Incident response time < 15 minutes

## 🚀 Advanced Tencent Cloud Features

### Serverless Cloud Function (SCF)

**Serverless Computing**
1. **Go to Serverless Cloud Function → Functions**
2. Create function:
   - **Name**: tpt-gov-function
   - **Runtime**: Node.js 16

3. **Deploy Functions**
   - API endpoints
   - Background processing
   - Event-driven tasks

### Tencent Kubernetes Engine (TKE)

**Managed Kubernetes**
1. **Go to Tencent Kubernetes Engine → Clusters**
2. Create cluster:
   - **Cluster name**: tpt-gov-k8s
   - **Region**: Singapore

3. **Configure Node Pools**
   - Managed node pools
   - Auto-scaling enabled
   - Spot instances for cost optimization

### Data Lake Compute (DLC)

**Data Analytics and Processing**
1. **Go to Data Lake Compute → Workspaces**
2. Create workspace:
   - **Name**: tpt-gov-analytics
   - **Region**: Singapore

3. **Configure Data Integration**
   - Import application logs
   - Create analytics workflows
   - Set up scheduled reports

---

## 🎉 Ready to Deploy on Tencent Cloud?

**Follow these steps:**

1. **Create Tencent Cloud Account** (5 minutes)
2. **Set up VPC and Security** (10 minutes)
3. **Launch CVM Instance** (5 minutes)
4. **Configure TDSQL Database** (10 minutes)
5. **Deploy Application** (5 minutes)
6. **Set up CLB Load Balancer** (10 minutes)

**Total Time: ~45 minutes**

Your government platform will be running on Tencent Cloud's robust global infrastructure!

---

[← Alibaba Cloud Guide](alibaba-cloud.md) | [Getting Started](../getting-started.md) | [Deployment Options](../README.md#deployment-options)
