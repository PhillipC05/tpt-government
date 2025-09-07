# 🌐 Google Cloud Platform Deployment Guide

This guide provides comprehensive instructions for deploying the TPT Government Platform on Google Cloud Platform (GCP), leveraging Google's AI/ML capabilities and strong compliance certifications for government workloads.

## 🎯 Why Google Cloud for Government?

### ✅ **Strong Government Compliance**
- **FedRAMP High Authorized** - Highest security authorization level
- **DoD IL 5 Compliant** - Department of Defense compliance
- **ISO 27001 Certified** - Information security management
- **HIPAA Compliant** - Healthcare data protection
- **SOC 2 Type II Certified** - Enterprise-grade security
- **CSA STAR Level 2 Certified** - Cloud security alliance

### 🤖 **AI/ML Leadership**
- **Vertex AI** - Advanced AI/ML capabilities
- **AutoML** - Automated machine learning
- **BigQuery ML** - SQL-based machine learning
- **Document AI** - Advanced document processing
- **Natural Language AI** - Text analysis and processing

### ☁️ **Enterprise-Grade Infrastructure**
- **99.99% uptime SLA** - Highest reliability guarantee
- **Global network** - 35+ regions worldwide
- **Auto-scaling** - Automatic resource adjustment
- **Multi-region** - Disaster recovery capabilities
- **Edge network** - Global content delivery

### 💰 **Cost Optimization**
- **Always Free tier** - Get started with no cost
- **Committed use discounts** - Up to 57% savings
- **Sustained use discounts** - Automatic cost optimization
- **Preemptible VMs** - Up to 80% savings for batch workloads

## 🚀 Quick Start (15 Minutes)

### Step 1: Google Cloud Account Setup

1. **Create Google Cloud Account**
   - Go to [cloud.google.com](https://cloud.google.com)
   - Sign up for free account
   - Verify billing (credit card required but won't be charged)
   - Enable free tier credits ($300 credit for 90 days)

2. **Install Google Cloud SDK**
   ```bash
   # Install gcloud CLI
   curl https://sdk.cloud.google.com | bash
   exec -l $SHELL

   # Initialize and login
   gcloud init
   gcloud auth login
   ```

3. **Create Project**
   ```bash
   # Create new project
   gcloud projects create tpt-gov-project --name="TPT Government Platform"

   # Set as default project
   gcloud config set project tpt-gov-project
   ```

### Step 2: Launch Compute Engine VM

**Option A: Cloud Console (GUI)**

1. **Go to Compute Engine → VM Instances**
   - Click "Create Instance"
   - Name: `tpt-gov-vm`
   - Region: us-central1 (Iowa) or your preferred region
   - Zone: us-central1-a

2. **Choose Machine Type**
   ```
   Small Agency: e2-medium (2 vCPU, 4GB RAM) - ~$25/month
   Medium Agency: e2-standard-4 (4 vCPU, 16GB RAM) - ~$100/month
   Large Agency: e2-standard-8 (8 vCPU, 32GB RAM) - ~$200/month
   ```

3. **Configure Boot Disk**
   - OS: Ubuntu 22.04 LTS
   - Disk size: 50GB SSD
   - Enable deletion protection

4. **Configure Firewall**
   - Allow HTTP traffic
   - Allow HTTPS traffic
   - Allow SSH from your IP

5. **Create Instance**

**Option B: gcloud CLI (Automated)**

```bash
# Create VM instance
gcloud compute instances create tpt-gov-vm \
  --zone=us-central1-a \
  --machine-type=e2-medium \
  --network-tier=PREMIUM \
  --maintenance-policy=MIGRATE \
  --image=ubuntu-2204-jammy-v20230919 \
  --image-project=ubuntu-os-cloud \
  --boot-disk-size=50GB \
  --boot-disk-type=pd-ssd \
  --boot-disk-device-name=tpt-gov-vm \
  --tags=http-server,https-server \
  --metadata=startup-script="#!/bin/bash
  sudo apt update
  sudo apt install -y docker.io docker-compose
  sudo systemctl start docker
  sudo systemctl enable docker"
```

### Step 3: Deploy Application

```bash
# Connect to VM
gcloud compute ssh tpt-gov-vm --zone=us-central1-a

# Clone and deploy
git clone https://github.com/your-org/tpt-gov-platform.git
cd tpt-gov-platform
./deploy.sh deploy
```

## 🏗️ Production Architecture on GCP

### Single Region Setup (Most Agencies)

```
┌─────────────────────────────────────────────────┐
│            Google Cloud Region (us-central1)    │
├─────────────────────────────────────────────────┤
│  ┌─────────────┐ ┌─────────────┐ ┌─────────────┐ │
│  │   Load      │ │   Cloud     │ │   Cloud     │ │
│  │ Balancer    │ │   SQL       │ │   Memorystore│ │
│  │             │ │   (MySQL)   │ │   (Redis)    │ │
│  └─────────────┘ └─────────────┘ └─────────────┘ │
│  ┌─────────────┐ ┌─────────────┐ ┌─────────────┐ │
│  │   Cloud     │ │   Cloud     │ │   Cloud     │ │
│  │  Storage    │ │   CDN       │ │   Monitoring │ │
│  └─────────────┘ └─────────────┘ └─────────────┘ │
└─────────────────────────────────────────────────┘
```

### Multi-Region Setup (Large Agencies)

```
┌─────────────────┐    ┌─────────────────┐
│   us-central1   │    │   us-west1     │
│   (Primary)     │    │   (DR)         │
├─────────────────┤    ├─────────────────┤
│  Load Balancer  │    │  Load Balancer │
│  Instance Group │    │  Instance Group│
│  Cloud SQL      │    │  Cloud SQL     │
│  Cloud Storage  │    │  Cloud Storage │
└─────────────────┘    └─────────────────┘
         │                        │
         └──── Cloud Load Balancing ─────┘
```

## 📋 Detailed Setup Guide

### 1. VPC Network and Firewall

**Create VPC Network**

1. **Go to VPC Network → VPC Networks**
2. Create VPC:
   - **Name**: tpt-gov-vpc
   - **Subnet creation mode**: Auto
   - **Firewall**: Allow SSH, RDP, and ICMP

**Create Firewall Rules**

1. **Go to VPC Network → Firewall**
2. Create rules:
   ```
   Name: allow-http
   Targets: http-server
   Source IP ranges: 0.0.0.0/0
   Protocols/ports: tcp:80

   Name: allow-https
   Targets: https-server
   Source IP ranges: 0.0.0.0/0
   Protocols/ports: tcp:443

   Name: allow-ssh
   Targets: all instances
   Source IP ranges: your-ip/32
   Protocols/ports: tcp:22
   ```

### 2. Load Balancer (Global)

**Create HTTP(S) Load Balancer**

1. **Go to Network Services → Load Balancing**
2. Create load balancer:
   - **Type**: HTTP(S) Load Balancer
   - **Internet facing or internal**: Internet facing

3. **Configure Backend**
   - Create instance group with your VM
   - Health check: `/health` endpoint
   - Port: 80

4. **Configure Frontend**
   - IP address: Create new static IP
   - Protocol: HTTPS
   - Certificate: Create Google-managed SSL certificate

### 3. Cloud SQL (MySQL)

**Create Cloud SQL Instance**

1. **Go to SQL → Create Instance**
2. Choose MySQL:
   - **Instance ID**: tpt-gov-db
   - **Root password**: Set secure password
   - **Database version**: MySQL 8.0
   - **Region**: us-central1

3. **Choose Machine Type**
   ```
   Small: db-f1-micro (0.2 vCPU, 0.6GB) - ~$8/month
   Medium: db-g1-small (0.6 vCPU, 1.7GB) - ~$25/month
   Large: db-n1-standard-2 (2 vCPU, 7.5GB) - ~$80/month
   ```

4. **Configure Networking**
   - **Private IP**: Enable (recommended)
   - **Authorized networks**: Add your VM's IP

5. **Configure Security**
   - **SSL connections**: Required
   - **Automated backups**: Enable, daily
   - **Point-in-time recovery**: Enable

### 4. Memorystore (Redis)

**Create Redis Instance**

1. **Go to Memorystore → Redis**
2. Create instance:
   - **Instance ID**: tpt-gov-redis
   - **Tier**: Basic (for development)
   - **Capacity**: 1GB
   - **Region**: us-central1

3. **Configure Network**
   - **Authorized network**: tpt-gov-vpc
   - **Connect mode**: Private IP

### 5. Cloud Storage

**Create Storage Bucket**

1. **Go to Cloud Storage → Buckets**
2. Create bucket:
   - **Name**: tpt-gov-documents-[project-id]
   - **Location**: Region (us-central1)
   - **Storage class**: Standard
   - **Access control**: Uniform

3. **Configure CORS**
   ```json
   [
     {
       "origin": ["https://yourdomain.gov.local"],
       "method": ["GET", "POST", "PUT", "DELETE"],
       "responseHeader": ["*"],
       "maxAgeSeconds": 3600
     }
   ]
   ```

### 6. Cloud CDN

**Enable Cloud CDN**

1. **Go to Network Services → Cloud CDN**
2. Enable CDN for your load balancer backend
3. Configure cache settings:
   - Cache static content for 1 hour
   - Cache API responses for 5 minutes
   - Enable compression

## 🔧 Configuration Files

### Environment Configuration (.env)

```bash
# Application
APP_NAME="Your Government Agency"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://yourdomain.gov.local

# Database (Cloud SQL)
DB_HOST=/cloudsql/tpt-gov-project:us-central1:tpt-gov-db
DB_PORT=3306
DB_NAME=tpt_gov_db
DB_USER=tpt_gov_user
DB_PASSWORD=your-cloud-sql-password
DB_SOCKET=/cloudsql/tpt-gov-project:us-central1:tpt-gov-db

# Redis (Memorystore)
REDIS_HOST=10.0.0.100  # Private IP from Memorystore
REDIS_PORT=6379
REDIS_PASSWORD=your-redis-password

# Google Cloud Storage
GOOGLE_CLOUD_PROJECT=tpt-gov-project
GOOGLE_CLOUD_STORAGE_BUCKET=tpt-gov-documents
GOOGLE_APPLICATION_CREDENTIALS=/path/to/service-account-key.json

# Security
JWT_SECRET=your-256-bit-jwt-secret
ENCRYPTION_KEY=your-256-bit-encryption-key

# Email (SendGrid or Mailgun)
MAIL_MAILER=smtp
MAIL_HOST=smtp.sendgrid.net
MAIL_PORT=587
MAIL_USERNAME=apikey
MAIL_PASSWORD=your-sendgrid-api-key
```

### Service Account Setup

```bash
# Create service account
gcloud iam service-accounts create tpt-gov-sa \
  --description="TPT Government Platform Service Account" \
  --display-name="TPT Gov SA"

# Grant necessary permissions
gcloud projects add-iam-policy-binding tpt-gov-project \
  --member="serviceAccount:tpt-gov-sa@tpt-gov-project.iam.gserviceaccount.com" \
  --role="roles/storage.objectAdmin"

gcloud projects add-iam-policy-binding tpt-gov-project \
  --member="serviceAccount:tpt-gov-sa@tpt-gov-project.iam.gserviceaccount.com" \
  --role="roles/cloudsql.client"

# Generate key
gcloud iam service-accounts keys create service-account-key.json \
  --iam-account=tpt-gov-sa@tpt-gov-project.iam.gserviceaccount.com
```

## 📊 Scaling and Performance

### Instance Groups and Autoscaling

**Create Managed Instance Group**

1. **Go to Compute Engine → Instance Groups**
2. Create instance group:
   - **Name**: tpt-gov-mig
   - **Location**: Regional
   - **Region**: us-central1

3. **Configure Autoscaling**
   - **Minimum instances**: 2
   - **Maximum instances**: 10
   - **Target CPU utilization**: 70%
   - **Target HTTP load balancing utilization**: 80%

### Cloud Monitoring

**Set up Monitoring**

1. **Go to Monitoring → Dashboards**
2. Create dashboard:
   - VM instance metrics
   - Load balancer metrics
   - Database performance
   - Application metrics

3. **Configure Alerts**
   - CPU usage > 80%
   - Memory usage > 85%
   - HTTP 5xx errors > 5/minute
   - Database connections > 80%

## 🔒 Security Best Practices

### Identity and Access Management

**IAM Roles and Permissions**

```bash
# Create custom role for application
gcloud iam roles create tpt_gov_app_role \
  --project=tpt-gov-project \
  --title="TPT Gov Application Role" \
  --description="Custom role for TPT Government Platform" \
  --permissions=compute.instances.get,storage.objects.get,cloudsql.instances.connect

# Assign role to service account
gcloud projects add-iam-policy-binding tpt-gov-project \
  --member="serviceAccount:tpt-gov-sa@tpt-gov-project.iam.gserviceaccount.com" \
  --role="projects/tpt-gov-project/roles/tpt_gov_app_role"
```

### Network Security

**Cloud Armor**

1. **Go to Network Security → Cloud Armor**
2. Create security policy:
   - **Name**: tpt-gov-security-policy
   - **Default rule**: Allow

3. **Configure Rules**
   - Block known bad IPs
   - Rate limiting for APIs
   - Geographic restrictions
   - OWASP top 10 protection

**VPC Service Controls**

1. **Go to VPC Service Controls**
2. Create service perimeter:
   - **Name**: tpt-gov-perimeter
   - **Projects**: tpt-gov-project

3. **Configure Access Levels**
   - Restrict data exfiltration
   - Control service access
   - Audit data access

### Data Protection

**Cloud KMS**

1. **Go to Security → Key Management**
2. Create key ring:
   - **Name**: tpt-gov-keyring
   - **Location**: us-central1

3. **Create Keys**
   - Database encryption key
   - File encryption key
   - Secret encryption key

**Cloud HSM**

1. **Go to Security → Cloud HSM**
2. Create HSM:
   - **Name**: tpt-gov-hsm
   - **Capacity**: 1 HSM

## 💰 Cost Optimization

### Monthly Cost Breakdown

**Small Agency Setup (~$80/month)**
- VM (e2-medium): $25/month
- Cloud SQL: $20/month
- Load Balancer: $20/month
- Cloud Storage (100GB): $3/month
- Cloud Monitoring: $5/month

**Medium Agency Setup (~$250/month)**
- VM Instance Group (2-4 instances): $100/month
- Cloud SQL: $50/month
- Load Balancer: $20/month
- Cloud Storage (1TB): $25/month
- Cloud CDN: $30/month

**Large Agency Setup (~$500/month)**
- VM Instance Group (4-10 instances): $250/month
- Cloud SQL (high availability): $100/month
- Load Balancer: $30/month
- Advanced monitoring: $50/month
- Backup storage: $50/month

### Cost Saving Strategies

1. **Committed Use Discounts**
   - 1-year commitment: 20% savings
   - 3-year commitment: 40% savings

2. **Sustained Use Discounts**
   - Automatic discounts for consistent usage
   - Up to 30% savings

3. **Preemptible VMs**
   - Up to 80% savings for batch processing
   - Use for development and testing

4. **Storage Optimization**
   - Use Coldline for archival data
   - Enable object versioning strategically
   - Set up lifecycle policies

## 📈 Monitoring and Logging

### Cloud Monitoring

**Cloud Monitoring Workspace**

1. **Go to Monitoring → Settings**
2. Create workspace:
   - **Name**: TPT Government Monitoring
   - **Project**: tpt-gov-project

3. **Configure Dashboards**
   - Infrastructure monitoring
   - Application performance
   - Security monitoring
   - Cost monitoring

### Cloud Logging

**Log Router and Sinks**

1. **Go to Logging → Logs Router**
2. Create sink:
   - **Name**: tpt-gov-logs-sink
   - **Destination**: BigQuery dataset

3. **Configure Exports**
   - Export application logs
   - Export audit logs
   - Export security events

### Error Reporting

**Cloud Error Reporting**

1. **Go to Error Reporting**
2. Configure error notifications:
   - Email alerts for new errors
   - Slack integration
   - PagerDuty integration

## 🚨 Troubleshooting

### Common Issues

**❌ VM Connection Failed**
```bash
# Check firewall rules
gcloud compute firewall-rules list

# Verify VM status
gcloud compute instances list

# Check SSH key
gcloud compute ssh tpt-gov-vm --zone=us-central1-a
```

**❌ Load Balancer Health Checks Failing**
```bash
# Check backend service health
gcloud compute backend-services get-health tpt-gov-backend \
  --region=us-central1

# Verify application health endpoint
curl http://localhost/health
```

**❌ Database Connection Issues**
```bash
# Test Cloud SQL connectivity
gcloud sql connect tpt-gov-db --user=tpt_gov_user

# Check private IP connectivity
gcloud compute ssh tpt-gov-vm --zone=us-central1-a \
  --command="mysql -h 10.0.0.100 -u tpt_gov_user -p"
```

**❌ Cloud Storage Access Denied**
```bash
# Verify service account permissions
gcloud iam service-accounts get-iam-policy \
  tpt-gov-sa@tpt-gov-project.iam.gserviceaccount.com

# Test storage access
gsutil ls gs://tpt-gov-documents/
```

## 🎯 Success Metrics

Track these KPIs for successful GCP deployment:

**📈 Performance**
- Page load time < 2 seconds
- API response time < 500ms
- Error rate < 0.1%
- Uptime > 99.9%

**💰 Cost Efficiency**
- Cost per user < $3/month
- Resource utilization > 70%
- Committed use discount > 20%
- Monthly cost variance < 10%

**🔒 Security**
- All security scanner findings addressed
- No unauthorized access incidents
- Compliance audit success rate = 100%
- Incident response time < 15 minutes

## 🚀 Advanced GCP Features

### AI/ML Integration

**Vertex AI Integration**

1. **Go to Vertex AI → Workbench**
2. Create notebook instance:
   - **Name**: tpt-gov-ml-workbench
   - **Environment**: TensorFlow Enterprise

3. **Configure AI Features**
   - Document classification models
   - Text extraction pipelines
   - Automated workflow optimization

### BigQuery Analytics

**Create BigQuery Dataset**

1. **Go to BigQuery**
2. Create dataset:
   - **Dataset ID**: tpt_gov_analytics
   - **Location**: us-central1

3. **Set up Data Transfer**
   - Import application logs
   - Create analytics dashboards
   - Set up scheduled reports

### Cloud Run (Serverless)

**Deploy Serverless Components**

1. **Go to Cloud Run**
2. Create service:
   - **Name**: tpt-gov-api
   - **Container**: Your API container
   - **Region**: us-central1

3. **Configure Scaling**
   - Minimum instances: 0
   - Maximum instances: 100
   - Concurrency: 80

---

## 🎉 Ready to Deploy on Google Cloud?

**Follow these steps:**

1. **Create GCP Account** (5 minutes)
2. **Set up Project and Billing** (5 minutes)
3. **Launch Compute Engine VM** (5 minutes)
4. **Configure Cloud SQL Database** (10 minutes)
5. **Deploy Application** (5 minutes)
6. **Set up Load Balancer** (10 minutes)

**Total Time: ~40 minutes**

Your government platform will be running on Google's AI-powered cloud infrastructure!

---

[← Azure Guide](azure.md) | [Getting Started](../getting-started.md) | [Linode Guide](linode.md)
