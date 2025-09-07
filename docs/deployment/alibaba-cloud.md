# 🇨🇳 Alibaba Cloud Deployment Guide

This guide provides comprehensive instructions for deploying the TPT Government Platform on Alibaba Cloud, offering robust infrastructure for Asia-Pacific deployments with strong compliance certifications and competitive pricing.

## 🎯 Why Alibaba Cloud for Government?

### ✅ **Asia-Pacific Compliance**
- **Multi-Level Protection Scheme (MLPS)** - China's highest security certification
- **Financial Grade Security** - Banking and financial industry standards
- **ISO 27001 Certified** - Information security management
- **GDPR Compliant** - European data protection
- **CSA STAR Certified** - Cloud security alliance

### 🌏 **Asia-Pacific Focus**
- **Extensive regional coverage** - 20+ regions across Asia-Pacific
- **Low latency** - Optimized for Asian users
- **Local data residency** - Meet regional data requirements
- **Multi-language support** - Native Chinese and English interfaces
- **24/7 local support** - Regional technical assistance

### 💰 **Competitive Pricing**
- **Cost-effective for Asia** - Lower prices than Western providers
- **Pay-as-you-go** - No long-term commitments
- **Volume discounts** - Automatic savings for consistent usage
- **Free tier available** - Get started with no cost
- **Regional pricing** - Optimized for local markets

## 🚀 Quick Start (15 Minutes)

### Step 1: Alibaba Cloud Account Setup

1. **Create Alibaba Cloud Account**
   - Go to [alibabacloud.com](https://alibabacloud.com)
   - Sign up for free account
   - Verify email and phone
   - Complete real-name verification (required for China regions)

2. **Set up Billing**
   - Add payment method (Alipay, credit card, or bank transfer)
   - Enable free trial credits ($300+ available)
   - Set billing alerts and budget controls

3. **Install Alibaba Cloud CLI**
   ```bash
   # Install CLI
   curl -fsSL https://aliyuncli.alicdn.com/aliyun-cli-linux-latest-amd64.tgz | tar -xzv
   sudo mv aliyun /usr/local/bin/

   # Configure
   aliyun configure
   ```

### Step 2: Launch ECS Instance

**Option A: Alibaba Cloud Console (GUI)**

1. **Go to Elastic Compute Service → Instances**
   - Click "Create Instance"
   - Choose region (Singapore, Hong Kong, or Tokyo for international)

2. **Choose Instance Type**
   ```
   ecs.t6-c1m1.large: 2 vCPU, 2GB RAM - ~$20/month (Singapore)
   ecs.c6.large: 2 vCPU, 4GB RAM - ~$35/month (Singapore)
   ecs.g6.large: 2 vCPU, 8GB RAM - ~$70/month (Singapore)
   ecs.c6.xlarge: 4 vCPU, 8GB RAM - ~$70/month (Singapore)
   ```

3. **Configure Basic Settings**
   - **Instance name**: tpt-gov-server
   - **Image**: Ubuntu 22.04 64-bit
   - **Storage**: 40GB SSD (ESSD AutoPL)

4. **Configure Networking**
   - **Network type**: VPC
   - **Security group**: Create new
   - **Public IP**: Allocate automatically

5. **Configure Security Group**
   ```
   Inbound rules:
   - SSH (22) from your IP
   - HTTP (80) from 0.0.0.0/0
   - HTTPS (443) from 0.0.0.0/0
   ```

6. **Launch Instance**

**Option B: Alibaba Cloud CLI (Automated)**

```bash
# Create VPC first
aliyun vpc CreateVpc --VpcName tpt-gov-vpc --CidrBlock 10.0.0.0/16

# Create VSwitch
aliyun vpc CreateVSwitch --VpcId vpc-id --VSwitchName tpt-gov-vswitch \
  --CidrBlock 10.0.0.0/24 --ZoneId ap-southeast-1a

# Create security group
aliyun ecs CreateSecurityGroup --VpcId vpc-id --SecurityGroupName tpt-gov-sg

# Create instance
aliyun ecs CreateInstance --InstanceName tpt-gov-server \
  --InstanceType ecs.c6.large \
  --ImageId ubuntu_22_04_x64_20G_alibase_20230919.vhd \
  --VSwitchId vswitch-id \
  --SecurityGroupId sg-id \
  --InternetMaxBandwidthOut 100 \
  --Password your-password
```

### Step 3: Deploy Application

```bash
# Connect to your instance
ssh root@your-alibaba-ip

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

## 🏗️ Production Architecture on Alibaba Cloud

### Single Region Setup (Most Agencies)

```
┌─────────────────────────────────────────────────┐
│            Alibaba Cloud Region (Singapore)     │
├─────────────────────────────────────────────────┤
│  ┌─────────────┐ ┌─────────────┐ ┌─────────────┐ │
│  │   SLB       │ │   ECS       │ │   RDS       │ │
│  │  (Load      │ │  (Server)   │ │  (MySQL)    │ │
│  │ Balancer)   │ │             │ │            │ │
│  └─────────────┘ └─────────────┘ └─────────────┘ │
│  ┌─────────────┐ ┌─────────────┐ ┌─────────────┐ │
│  │   Redis     │ │   OSS       │ │   CDN       │ │
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
│  SLB → ECS → RDS│    │  SLB → ECS → RDS│
│  Redis          │    │  Redis          │
│  OSS            │    │  OSS (Replica)  │
└─────────────────┘    └─────────────────┘
         │                        │
         └──── Global Traffic Manager ───┘
```

## 📋 Detailed Setup Guide

### 1. VPC and Networking

**Create VPC (Best Practice)**

1. **Go to Virtual Private Cloud → VPCs**
2. Create VPC:
   - **Name**: tpt-gov-vpc
   - **IPv4 CIDR block**: 10.0.0.0/16
   - **Region**: Singapore

3. **Create VSwitch**
   - **Name**: tpt-gov-vswitch
   - **Zone**: Singapore Zone A
   - **IPv4 CIDR block**: 10.0.0.0/24

4. **Create NAT Gateway**
   - For outbound internet access from private subnets
   - Attach to VPC

### 2. Security Groups

**Create Security Group**

1. **Go to Elastic Compute Service → Security Groups**
2. Create security group:
   - **Name**: tpt-gov-sg
   - **VPC**: tpt-gov-vpc

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

### 3. Server Load Balancer (SLB)

**Create SLB Instance**

1. **Go to Server Load Balancer → Instances**
2. Create SLB:
   - **Name**: tpt-gov-slb
   - **Region**: Singapore
   - **Primary zone**: Singapore Zone A
   - **Backup zone**: Singapore Zone B
   - **Instance type**: Shared-performance

3. **Configure Listeners**
   - **HTTP listener**: Port 80
   - **HTTPS listener**: Port 443 (upload SSL certificate)

4. **Configure Backend Servers**
   - Add your ECS instances
   - Health check: `/health` endpoint
   - Session persistence: Disabled

### 4. ApsaraDB RDS (MySQL)

**Create RDS Instance**

1. **Go to ApsaraDB RDS → Instances**
2. Create instance:
   - **Engine**: MySQL 8.0
   - **Edition**: High-availability
   - **Instance class**: mysql.n2.small.1 (1 core, 2GB RAM) - ~$25/month

3. **Configure Network**
   - **VPC**: tpt-gov-vpc
   - **VSwitch**: tpt-gov-vswitch
   - **Security group**: tpt-gov-sg

4. **Configure Account**
   - **Database account**: tpt_gov_user
   - **Password**: Secure password
   - **Privileges**: Read/Write

5. **Configure Backup**
   - **Backup cycle**: Daily
   - **Retention period**: 7 days
   - **Backup time**: 02:00-06:00

### 5. ApsaraDB Redis

**Create Redis Instance**

1. **Go to ApsaraDB Redis → Instances**
2. Create instance:
   - **Engine**: Redis 6.0
   - **Architecture**: Cluster
   - **Instance class**: redis.logic.shard.1g.2db.0rodb.4proxy - ~$30/month

3. **Configure Network**
   - **VPC**: tpt-gov-vpc
   - **VSwitch**: tpt-gov-vswitch

4. **Configure Security**
   - **Password**: Enable
   - **SSL encryption**: Enable

### 6. Object Storage Service (OSS)

**Create OSS Bucket**

1. **Go to Object Storage Service → Buckets**
2. Create bucket:
   - **Bucket name**: tpt-gov-documents-[account-id]
   - **Region**: Singapore
   - **Storage class**: Standard
   - **Access control**: Private

3. **Configure CORS**
   ```json
   [
     {
       "AllowedOrigin": ["https://yourdomain.gov.local"],
       "AllowedMethod": ["GET", "POST", "PUT", "DELETE"],
       "AllowedHeader": ["*"],
       "ExposeHeader": ["*"],
       "MaxAgeSeconds": 3600
     }
   ]
   ```

### 7. Content Delivery Network (CDN)

**Create CDN Domain**

1. **Go to Alibaba Cloud CDN → Domain Names**
2. Add domain:
   - **Domain name**: cdn.yourdomain.gov.local
   - **Origin type**: IP
   - **Origin address**: Your SLB IP

3. **Configure Caching**
   - **Cache expiration**: 1 year for static files
   - **Cache key**: Default
   - **HTTPS configuration**: Enable

## 🔧 Configuration Files

### Environment Configuration (.env)

```bash
# Application
APP_NAME="Your Government Agency"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://youragency.gov.local

# Database (Alibaba RDS)
DB_HOST=rm-uf6w8m123456.mysql.rds.aliyuncs.com
DB_PORT=3306
DB_NAME=tpt_gov_db
DB_USER=tpt_gov_user
DB_PASSWORD=your-rds-password

# Redis (Alibaba Redis)
REDIS_HOST=r-uf6w8m123456.redis.rds.aliyuncs.com
REDIS_PORT=6379
REDIS_PASSWORD=your-redis-password

# OSS Storage
OSS_ACCESS_KEY_ID=your-oss-access-key
OSS_ACCESS_KEY_SECRET=your-oss-secret-key
OSS_ENDPOINT=oss-ap-southeast-1.aliyuncs.com
OSS_BUCKET=tpt-gov-documents

# Security
JWT_SECRET=your-256-bit-jwt-secret
ENCRYPTION_KEY=your-256-bit-encryption-key

# Email (SendGrid or Alibaba DirectMail)
MAIL_MAILER=smtp
MAIL_HOST=smtp.sendgrid.net
MAIL_PORT=587
MAIL_USERNAME=apikey
MAIL_PASSWORD=your-sendgrid-api-key
```

### Alibaba Cloud CLI Configuration

```bash
# Configure CLI
aliyun configure

# Set default region
aliyun configure set --region ap-southeast-1

# Create RAM user for application
aliyun ram CreateUser --UserName tpt-gov-user

# Attach policies
aliyun ram AttachPolicyToUser \
  --UserName tpt-gov-user \
  --PolicyName AliyunOSSFullAccess \
  --PolicyType System
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
   - ECS instance metrics
   - SLB performance
   - RDS database metrics
   - Redis cache metrics

3. **Configure Alerts**
   - CPU usage > 80%
   - Memory usage > 85%
   - HTTP 5xx errors > 5/minute
   - Database connections > 80%

## 🔒 Security Best Practices

### Identity and Access Management

**RAM (Resource Access Management)**

```bash
# Create RAM user
aliyun ram CreateUser --UserName tpt-gov-app-user

# Create custom policy
aliyun ram CreatePolicy --PolicyName tpt-gov-policy \
  --PolicyDocument '{
    "Version": "1",
    "Statement": [
      {
        "Effect": "Allow",
        "Action": [
          "ecs:DescribeInstances",
          "oss:GetObject",
          "oss:PutObject"
        ],
        "Resource": "*"
      }
    ]
  }'

# Attach policy
aliyun ram AttachPolicyToUser \
  --UserName tpt-gov-app-user \
  --PolicyName tpt-gov-policy \
  --PolicyType Custom
```

### Network Security

**Web Application Firewall (WAF)**

1. **Go to Web Application Firewall**
2. Create instance:
   - **Name**: tpt-gov-waf
   - **Edition**: Pro

3. **Configure Protection**
   - Enable OWASP rules
   - Configure rate limiting
   - Set up bot management
   - Enable SSL/TLS protection

**Anti-DDoS**

1. **Go to Anti-DDoS → Instances**
2. Enable Anti-DDoS protection:
   - **Type**: Anti-DDoS Pro
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

1. **RDS Encryption**
   - Enable TDE (Transparent Data Encryption)
   - Use KMS key for encryption

2. **OSS Encryption**
   - Enable server-side encryption
   - Use KMS-managed keys

## 💰 Cost Optimization

### Monthly Cost Breakdown

**Small Agency Setup (~$60/month)**
- ECS (c6.large): $35/month
- RDS (MySQL): $25/month
- SLB: Free (first 15 LCUs)
- OSS (100GB): $2/month
- Total: ~$60/month

**Medium Agency Setup (~$150/month)**
- ECS Auto Scaling (2-4 instances): $70/month
- RDS (MySQL): $50/month
- Redis: $30/month
- SLB: $10/month
- OSS (1TB): $20/month
- Total: ~$150/month

**Large Agency Setup (~$300/month)**
- ECS Auto Scaling (4-10 instances): $140/month
- RDS (MySQL High Availability): $100/month
- Redis Cluster: $60/month
- SLB: $20/month
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
   - Use OSS Infrequent Access for old data
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

### Log Service

**Centralized Logging**

1. **Go to Log Service → Projects**
2. Create project:
   - **Project name**: tpt-gov-logs
   - **Region**: Singapore

3. **Configure Logstores**
   - Application logs
   - System logs
   - Security logs
   - Access logs

### ActionTrail

**Audit Logging**

1. **Go to ActionTrail → Trails**
2. Create trail:
   - **Trail name**: tpt-gov-audit
   - **OSS bucket**: tpt-gov-logs

3. **Configure Events**
   - All API calls
   - Console operations
   - Resource changes

## 🚨 Troubleshooting

### Common Issues

**❌ SSH Connection Failed**
```bash
# Check security group rules
aliyun ecs DescribeSecurityGroups --RegionId ap-southeast-1

# Verify instance status
aliyun ecs DescribeInstances --RegionId ap-southeast-1

# Check VPC configuration
aliyun vpc DescribeVpcs --RegionId ap-southeast-1
```

**❌ SLB Health Checks Failing**
```bash
# Check backend server health
aliyun slb DescribeHealthStatus \
  --LoadBalancerId your-slb-id \
  --RegionId ap-southeast-1

# Verify application health endpoint
curl http://localhost/health
```

**❌ Database Connection Issues**
```bash
# Test RDS connectivity
mysql -h rm-uf6w8m123456.mysql.rds.aliyuncs.com \
  -u tpt_gov_user -p tpt_gov_db

# Check security group
aliyun rds DescribeDBInstanceNetInfo \
  --DBInstanceId rm-uf6w8m123456 \
  --RegionId ap-southeast-1
```

**❌ OSS Access Denied**
```bash
# Verify RAM permissions
aliyun ram ListPoliciesForUser --UserName tpt-gov-user

# Test OSS access
aliyun oss ls oss://tpt-gov-documents/
```

### Getting Help

1. **Alibaba Cloud Support**: 24/7 global support
2. **Documentation**: help.aliyun.com
3. **Community Forum**: forum.aliyun.com
4. **Status Page**: status.aliyun.com

## 🎯 Success Metrics

Track these KPIs for successful Alibaba Cloud deployment:

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

## 🚀 Advanced Alibaba Cloud Features

### Function Compute

**Serverless Computing**
1. **Go to Function Compute → Services**
2. Create service:
   - **Name**: tpt-gov-functions
   - **Runtime**: Node.js 16

3. **Deploy Functions**
   - API endpoints
   - Background processing
   - Event-driven tasks

### Container Service for Kubernetes (ACK)

**Managed Kubernetes**
1. **Go to Container Service → Clusters**
2. Create cluster:
   - **Cluster name**: tpt-gov-k8s
   - **Region**: Singapore

3. **Configure Node Pools**
   - Managed node pools
   - Auto-scaling enabled
   - Spot instances for cost optimization

### DataWorks

**Data Analytics and Processing**
1. **Go to DataWorks → Workspaces**
2. Create workspace:
   - **Name**: tpt-gov-analytics
   - **Region**: Singapore

3. **Configure Data Integration**
   - Import application logs
   - Create analytics workflows
   - Set up scheduled reports

---

## 🎉 Ready to Deploy on Alibaba Cloud?

**Follow these steps:**

1. **Create Alibaba Cloud Account** (5 minutes)
2. **Set up VPC and Security** (10 minutes)
3. **Launch ECS Instance** (5 minutes)
4. **Configure RDS Database** (10 minutes)
5. **Deploy Application** (5 minutes)
6. **Set up SLB Load Balancer** (10 minutes)

**Total Time: ~45 minutes**

Your government platform will be running on Alibaba Cloud's robust Asia-Pacific infrastructure!

---

[← Hetzner Guide](hetzner.md) | [Getting Started](../getting-started.md) | [Tencent Cloud Guide](tencent-cloud.md)
