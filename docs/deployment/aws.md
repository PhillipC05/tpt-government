# ☁️ AWS Deployment Guide

This guide provides comprehensive instructions for deploying the TPT Government Platform on Amazon Web Services (AWS), the most widely used cloud platform with the strongest government compliance certifications.

## 🎯 Why AWS for Government?

### ✅ **Unmatched Government Compliance**
- **FedRAMP High Authorized** - Highest security authorization level
- **DoD IL 5 Compliant** - Meets Department of Defense requirements
- **AWS GovCloud** - Isolated region for U.S. government workloads
- **HIPAA Compliant** - Suitable for health agencies
- **SOC 2 Type II Certified** - Enterprise-grade security
- **ISO 27001 Certified** - Information security management

### 🏗️ **Enterprise-Grade Infrastructure**
- **99.99% uptime SLA** - Highest reliability guarantee
- **Global network** - 25+ regions worldwide
- **Auto-scaling** - Automatic resource adjustment
- **Disaster recovery** - Multi-region failover capabilities
- **Edge locations** - 400+ points of presence

### 💰 **Cost Optimization**
- **Free tier available** - Get started with no cost
- **Reserved instances** - Up to 75% savings for long-term
- **Spot instances** - Up to 90% savings for flexible workloads
- **Savings plans** - Predictable pricing with discounts

## 🚀 Quick Start (20 Minutes)

### Step 1: AWS Account Setup

1. **Create AWS Account**
   - Go to [aws.amazon.com](https://aws.amazon.com)
   - Sign up for new account
   - Verify email and phone
   - Set up billing alerts

2. **Enable Multi-Factor Authentication**
   - Go to IAM → Users → Your User
   - Security credentials → MFA → Enable
   - Use authenticator app or hardware key

3. **Create IAM User (Best Practice)**
   ```bash
   # Don't use root account for daily operations
   # Create IAM user with AdministratorAccess
   ```

### Step 2: Launch EC2 Instance

**Option A: EC2 Console (GUI)**

1. **Go to EC2 Dashboard**
   - Search for "EC2" in AWS Console
   - Click "Launch Instance"

2. **Choose AMI**
   - Ubuntu Server 22.04 LTS (HVM)
   - SSD Volume Type
   - 64-bit (x86)

3. **Choose Instance Type**
   ```
   Small Agency: t3.medium (2 vCPU, 4GB RAM) - ~$30/month
   Medium Agency: t3.large (2 vCPU, 8GB RAM) - ~$60/month
   Large Agency: t3.xlarge (4 vCPU, 16GB RAM) - ~$120/month
   ```

4. **Configure Security Group**
   ```
   SSH: 22 (restrict to your IP)
   HTTP: 80 (0.0.0.0/0)
   HTTPS: 443 (0.0.0.0/0)
   ```

5. **Launch and Connect**
   ```bash
   ssh -i your-key.pem ubuntu@your-instance-ip
   ```

**Option B: AWS CLI (Automated)**

```bash
# Install AWS CLI
curl "https://awscli.amazonaws.com/awscli-exe-linux-x86_64.zip" -o "awscliv2.zip"
unzip awscliv2.zip
sudo ./aws/install

# Configure AWS CLI
aws configure

# Launch instance
aws ec2 run-instances \
  --image-id ami-0abcdef1234567890 \
  --count 1 \
  --instance-type t3.medium \
  --key-name your-key-pair \
  --security-groups tpt-gov-sg
```

### Step 3: Deploy Application

```bash
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

## 🏗️ Production Architecture on AWS

### Single Region Setup (Most Agencies)

```
┌─────────────────────────────────────────────────┐
│                AWS Region (us-east-1)           │
├─────────────────────────────────────────────────┤
│  ┌─────────────┐ ┌─────────────┐ ┌─────────────┐ │
│  │   ALB       │ │   EC2       │ │   RDS       │ │
│  │  (Load      │ │  (App)     │ │  (MySQL)    │ │
│  │  Balancer)  │ │            │ │            │ │
│  └─────────────┘ └─────────────┘ └─────────────┘ │
│  ┌─────────────┐ ┌─────────────┐ ┌─────────────┐ │
│  │   ElastiCache│ │   S3       │ │   CloudFront│ │
│  │   (Redis)    │ │  (Storage) │ │   (CDN)     │ │
│  └─────────────┘ └─────────────┘ └─────────────┘ │
└─────────────────────────────────────────────────┘
```

### Multi-Region Setup (Large Agencies)

```
┌─────────────────┐    ┌─────────────────┐
│   us-east-1     │    │   us-west-2     │
│   (Primary)     │    │   (DR)          │
├─────────────────┤    ├─────────────────┤
│  ALB → EC2 → RDS│    │  ALB → EC2 → RDS│
│  ElastiCache    │    │  ElastiCache    │
│  S3             │    │  S3 (Replica)   │
└─────────────────┘    └─────────────────┘
         │                        │
         └──── Route 53 ──────────┘
```

## 📋 Detailed Setup Guide

### 1. VPC and Networking

**Create VPC (Best Practice)**

1. **Go to VPC Dashboard**
   - Create VPC with CIDR: `10.0.0.0/16`
   - Create subnets:
     - Public: `10.0.1.0/24` (us-east-1a)
     - Private: `10.0.2.0/24` (us-east-1a)
     - Public: `10.0.3.0/24` (us-east-1b)
     - Private: `10.0.4.0/24` (us-east-1b)

2. **Create Internet Gateway**
   - Attach to VPC
   - Create route table for public subnets

3. **Create NAT Gateway**
   - For private subnet internet access
   - Place in public subnet

### 2. Security Groups

**Application Load Balancer SG**
```
Inbound:
- HTTP: 80 from 0.0.0.0/0
- HTTPS: 443 from 0.0.0.0/0

Outbound:
- All traffic to 0.0.0.0/0
```

**EC2 Instance SG**
```
Inbound:
- SSH: 22 from your-office-ip/32
- HTTP: 80 from ALB-SG
- HTTPS: 443 from ALB-SG

Outbound:
- All traffic to 0.0.0.0/0
```

**RDS Database SG**
```
Inbound:
- MySQL: 3306 from EC2-SG

Outbound:
- All traffic to 0.0.0.0/0
```

### 3. Application Load Balancer

**Create ALB**

1. **Go to EC2 → Load Balancers**
2. Create Application Load Balancer
3. Configure:
   - **Scheme**: Internet-facing
   - **IP address type**: IPv4
   - **Listeners**: HTTP:80, HTTPS:443

4. **SSL Certificate**
   - Request certificate from AWS Certificate Manager
   - Or upload your own certificate
   - Attach to HTTPS listener

5. **Target Group**
   - Create target group for EC2 instances
   - Health check path: `/health`
   - Port: 80

### 4. RDS Database

**Create RDS Instance**

1. **Go to RDS Dashboard**
2. Create database:
   - **Engine**: MySQL 8.0
   - **Template**: Production
   - **Instance class**: db.t3.medium (~$40/month)
   - **Storage**: 100 GB (gp3)

3. **Security & Networking**
   - Place in private subnet
   - Use database security group
   - Enable encryption at rest
   - Set backup retention: 30 days

4. **Advanced Configuration**
   - Enable automated backups
   - Set maintenance window
   - Enable performance insights
   - Configure parameter group

### 5. ElastiCache (Redis)

**Create Redis Cluster**

1. **Go to ElastiCache Dashboard**
2. Create Redis cluster:
   - **Engine**: Redis 7.0
   - **Node type**: cache.t3.micro (free tier)
   - **Number of nodes**: 1 (for development)

3. **Advanced Settings**
   - Place in private subnet
   - Enable encryption in transit
   - Set backup retention
   - Configure parameter group

### 6. S3 Storage

**Create S3 Bucket**

1. **Go to S3 Dashboard**
2. Create bucket:
   - **Name**: tpt-gov-documents-[account-id]
   - **Region**: us-east-1
   - **Block public access**: On

3. **Configure Bucket Policy**
   ```json
   {
     "Version": "2012-10-17",
     "Statement": [
       {
         "Effect": "Allow",
         "Principal": {
           "AWS": "arn:aws:iam::ACCOUNT-ID:role/tpt-gov-ec2-role"
         },
         "Action": "s3:*",
         "Resource": [
           "arn:aws:s3:::tpt-gov-documents-ACCOUNT-ID",
           "arn:aws:s3:::tpt-gov-documents-ACCOUNT-ID/*"
         ]
       }
     ]
   }
   ```

4. **Enable Versioning**
   - Versioning → Enable
   - Server access logging → Enable

### 7. CloudFront CDN (Optional)

**Create CloudFront Distribution**

1. **Go to CloudFront Dashboard**
2. Create distribution:
   - **Origin domain**: Your ALB DNS name
   - **Origin protocol**: HTTPS only
   - **Viewer protocol**: Redirect HTTP to HTTPS

3. **Configure Caching**
   - Cache static assets for 1 year
   - Cache API responses for 5 minutes
   - Enable compression

## 🔧 Configuration Files

### Environment Configuration (.env)

```bash
# Application
APP_NAME="Your Government Agency"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://youragency.gov.local

# Database (AWS RDS)
DB_HOST=tpt-gov-db.cluster-xyz.us-east-1.rds.amazonaws.com
DB_PORT=3306
DB_NAME=tpt_gov_db
DB_USER=tpt_gov_user
DB_PASSWORD=your-rds-password

# Redis (AWS ElastiCache)
REDIS_HOST=tpt-gov-redis.abcdef.ng.0001.use1.cache.amazonaws.com
REDIS_PORT=6379
REDIS_PASSWORD=your-redis-password

# AWS S3
AWS_ACCESS_KEY_ID=your-access-key
AWS_SECRET_ACCESS_KEY=your-secret-key
AWS_DEFAULT_REGION=us-east-1
AWS_BUCKET=tpt-gov-documents

# Security
JWT_SECRET=your-256-bit-jwt-secret
ENCRYPTION_KEY=your-256-bit-encryption-key

# Email (AWS SES)
MAIL_MAILER=smtp
MAIL_HOST=email-smtp.us-east-1.amazonaws.com
MAIL_PORT=587
MAIL_USERNAME=your-ses-username
MAIL_PASSWORD=your-ses-password
```

### IAM Policy for EC2

```json
{
  "Version": "2012-10-17",
  "Statement": [
    {
      "Effect": "Allow",
      "Action": [
        "s3:GetObject",
        "s3:PutObject",
        "s3:DeleteObject",
        "s3:ListBucket"
      ],
      "Resource": [
        "arn:aws:s3:::tpt-gov-documents-*",
        "arn:aws:s3:::tpt-gov-documents-*/*"
      ]
    },
    {
      "Effect": "Allow",
      "Action": [
        "ses:SendEmail",
        "ses:SendRawEmail"
      ],
      "Resource": "*"
    },
    {
      "Effect": "Allow",
      "Action": [
        "cloudwatch:PutMetricData",
        "cloudwatch:GetMetricData",
        "cloudwatch:ListMetrics"
      ],
      "Resource": "*"
    }
  ]
}
```

## 📊 Scaling and Performance

### Auto Scaling Group

**Create Auto Scaling Group**

1. **Go to EC2 → Auto Scaling Groups**
2. Create launch template:
   - AMI: Your custom AMI with TPT Platform
   - Instance type: t3.medium
   - Security group: tpt-gov-sg
   - IAM role: tpt-gov-ec2-role

3. **Configure Auto Scaling**
   - Minimum: 2 instances
   - Desired: 3 instances
   - Maximum: 10 instances

4. **Scaling Policies**
   - Target tracking: CPU utilization 70%
   - Step scaling: Based on request count
   - Scheduled scaling: Business hours scaling

### CloudWatch Monitoring

**Set up Monitoring**

1. **Go to CloudWatch Dashboard**
2. Create alarms:
   - CPU > 80% for 5 minutes
   - Memory > 85% for 5 minutes
   - HTTP 5xx errors > 5 per minute

3. **Create Dashboards**
   - Application performance metrics
   - Infrastructure monitoring
   - Cost and usage tracking

## 🔒 Security Best Practices

### Network Security
- ✅ Use VPC with private subnets
- ✅ Security groups with least privilege
- ✅ Network ACLs for additional protection
- ✅ AWS WAF for application protection
- ✅ AWS Shield for DDoS protection

### Data Protection
- ✅ Encrypt data at rest (RDS, S3, EBS)
- ✅ Encrypt data in transit (SSL/TLS)
- ✅ AWS KMS for key management
- ✅ Regular security assessments
- ✅ AWS Config for compliance monitoring

### Access Management
- ✅ IAM roles instead of access keys
- ✅ Multi-factor authentication required
- ✅ Least privilege access principle
- ✅ Regular access reviews
- ✅ AWS CloudTrail for audit logging

## 💰 Cost Optimization

### Monthly Cost Breakdown

**Small Agency Setup (~$150/month)**
- EC2 (t3.medium): $30/month
- RDS (db.t3.medium): $40/month
- ALB: $20/month
- S3 (100GB): $3/month
- CloudWatch: $5/month
- Data transfer: $10/month

**Medium Agency Setup (~$400/month)**
- EC2 (t3.large x 2): $120/month
- RDS (db.t3.large): $80/month
- ALB: $20/month
- ElastiCache: $15/month
- S3 (1TB): $25/month
- CloudFront: $10/month

**Large Agency Setup (~$800/month)**
- Auto Scaling (3-10 instances): $300/month
- RDS (db.r5.large): $200/month
- Multi-AZ setup: $100/month
- Advanced monitoring: $50/month
- Backup storage: $50/month

### Cost Saving Strategies

1. **Reserved Instances**
   - 1-year RI: 40% savings
   - 3-year RI: 60% savings

2. **Savings Plans**
   - Compute Savings Plan: Up to 66% savings
   - EC2 Instance Savings Plan: Up to 72% savings

3. **Spot Instances**
   - Up to 90% savings for batch processing
   - Use for development environments

4. **Storage Optimization**
   - S3 Intelligent Tiering: Automatic cost optimization
   - EBS gp3 volumes: Better performance/cost ratio

## 📈 Monitoring and Logging

### AWS Monitoring Services

**CloudWatch**
- Application metrics and logs
- Infrastructure monitoring
- Custom dashboards and alerts
- Log aggregation and analysis

**X-Ray**
- Application tracing
- Performance bottleneck identification
- Service map visualization
- Error tracking and debugging

**AWS Config**
- Resource configuration monitoring
- Compliance checking
- Configuration change tracking
- Automated remediation

### Third-Party Monitoring

**DataDog or New Relic**
- Advanced application monitoring
- Real user monitoring (RUM)
- Synthetic monitoring
- Alerting and incident management

## 🚨 Troubleshooting

### Common Issues

**❌ EC2 Instance Connection Failed**
```bash
# Check security group
aws ec2 describe-security-groups --group-ids sg-123456

# Verify key pair
aws ec2 describe-key-pairs --key-name your-key-pair

# Check instance status
aws ec2 describe-instances --instance-ids i-123456
```

**❌ ALB Health Checks Failing**
```bash
# Check target group health
aws elbv2 describe-target-health --target-group-arn arn:aws:elasticloadbalancing:...

# Verify application health endpoint
curl http://localhost/health
```

**❌ Database Connection Issues**
```bash
# Test RDS connectivity
mysql -h your-db-endpoint -u tpt_gov_user -p

# Check security group
aws rds describe-db-instances --db-instance-identifier tpt-gov-db
```

**❌ S3 Access Denied**
```bash
# Verify IAM permissions
aws iam list-attached-role-policies --role-name tpt-gov-ec2-role

# Test S3 access
aws s3 ls s3://tpt-gov-documents/
```

## 🎯 Success Metrics

Track these KPIs for successful AWS deployment:

**📈 Performance**
- Page load time < 2 seconds
- API response time < 500ms
- Error rate < 0.1%
- Uptime > 99.9%

**💰 Cost Efficiency**
- Cost per user < $5/month
- Resource utilization > 70%
- Reserved instance coverage > 80%
- Monthly cost variance < 10%

**🔒 Security**
- All security scans pass
- No unauthorized access incidents
- Compliance audit success rate = 100%
- Incident response time < 15 minutes

## 🚀 Advanced AWS Features

### AWS GovCloud

**For Federal Agencies**
- Isolated AWS region for U.S. government
- FedRAMP High authorization
- DoD IL 5 compliance
- Data sovereignty guarantee

**Setup Process**
1. Apply for GovCloud access
2. Create GovCloud account
3. Deploy using same instructions
4. Additional compliance configurations

### AWS Organizations

**For Multi-Department Deployments**
- Centralized account management
- Consolidated billing
- Service control policies
- Cross-account access management

### AWS Control Tower

**For Enterprise Governance**
- Automated account provisioning
- Pre-configured security baselines
- Continuous compliance monitoring
- Centralized logging and monitoring

---

## 🎉 Ready to Deploy on AWS?

**Follow these steps:**

1. **Create AWS Account** (5 minutes)
2. **Set up IAM and Security** (10 minutes)
3. **Launch EC2 Instance** (5 minutes)
4. **Configure RDS Database** (10 minutes)
5. **Deploy Application** (5 minutes)
6. **Set up Load Balancer** (10 minutes)

**Total Time: ~45 minutes**

Your government platform will be running on the world's most powerful cloud infrastructure!

---

[← Deployment Options](../README.md#deployment-options) | [Getting Started](../getting-started.md) | [Azure Guide](azure.md)
