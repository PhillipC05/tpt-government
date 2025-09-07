# 🔵 Vultr Deployment Guide

This guide provides comprehensive instructions for deploying the TPT Government Platform on Vultr, offering a cost-effective and high-performance cloud infrastructure with global data centers and strong compliance certifications.

## 🎯 Why Vultr for Government?

### ✅ **Government Compliance**
- **SOC 2 Type II Certified** - Enterprise-grade security
- **ISO 27001 Certified** - Information security management
- **GDPR Compliant** - European data protection
- **HIPAA Compliant** - Healthcare data protection
- **PCI DSS Certified** - Payment card industry compliance

### 💰 **Cost-Effective**
- **Starting at $6/month** - Very affordable
- **Transparent pricing** - No hidden costs
- **Free bandwidth** - Generous data transfer allowances
- **Hourly billing** - Pay only for what you use
- **No setup fees** - Get started immediately

### 🚀 **High Performance**
- **NVMe SSD storage** - Fastest storage available
- **Global network** - 32 data centers worldwide
- **DDoS protection** - Built-in security
- **99.9% uptime SLA** - High availability guarantee
- **Bare metal options** - For maximum performance

## 🚀 Quick Start (10 Minutes)

### Step 1: Vultr Account Setup

1. **Create Vultr Account**
   - Go to [vultr.com](https://vultr.com)
   - Sign up for free account
   - Verify email and add payment method
   - Complete account verification

2. **Generate API Key (Optional)**
   ```bash
   # For automated deployments
   # Generate API key in Account → API
   ```

### Step 2: Launch Cloud Compute Instance

**Option A: Vultr Customer Portal (GUI)**

1. **Go to Products → Cloud Compute**
   - Click "Deploy New Instance"
   - Choose region (closest to your users)

2. **Choose Server Type**
   - **Cloud Compute** (recommended)
   - **Bare Metal** (for high performance)
   - **Dedicated Cloud** (for compliance)

3. **Select Operating System**
   - **Ubuntu 22.04 LTS x64** (recommended)
   - **Debian 11 x64** (alternative)
   - **CentOS 7 x64** (legacy support)

4. **Choose Server Size**
   ```
   1 vCPU, 1GB RAM, 25GB NVMe - $6/month
   1 vCPU, 2GB RAM, 55GB NVMe - $12/month
   2 vCPU, 4GB RAM, 80GB NVMe - $24/month
   4 vCPU, 8GB RAM, 160GB NVMe - $48/month
   ```

5. **Configure Instance**
   - **Server Hostname**: tpt-gov-server
   - **Label**: TPT Government Platform
   - **Enable IPv6**: Optional
   - **Private Network**: Enable (recommended)

6. **Add SSH Keys**
   - Upload your public SSH key
   - Or use auto-generated key pair

7. **Deploy Instance**

**Option B: Vultr CLI (Automated)**

```bash
# Install Vultr CLI
npm install -g @vultr/vultr-cli

# Authenticate
vultr-cli auth

# Create instance
vultr-cli instance create \
  --region nyc1 \
  --plan vc2-1c-1gb \
  --os 387 \
  --label "tpt-gov-server" \
  --hostname tpt-gov-server \
  --ssh-keys "your-ssh-key-id"
```

### Step 3: Deploy Application

```bash
# Connect to your instance
ssh root@your-vultr-ip

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

## 🏗️ Production Architecture on Vultr

### Single Server Setup (Most Agencies)

```
┌─────────────────────────────────────┐
│         Vultr Instance              │
│         Ubuntu 22.04, 4GB RAM       │
├─────────────────────────────────────┤
│  ┌─────────────┐ ┌─────────────────┐ │
│  │   Nginx     │ │   PHP-FPM       │ │
│  │   (Web)     │ │   (App)         │ │
│  └─────────────┘ └─────────────────┘ │
│  ┌─────────────┐ ┌─────────────────┐ │
│  │  MySQL      │ │   Redis         │ │
│  │  (DB)       │ │   (Cache)       │ │
│  └─────────────┘ └─────────────────┘ │
└─────────────────────────────────────┘
```

### Multi-Server Setup (Growing Agencies)

```
┌─────────────────┐    ┌─────────────────┐
│   Web Server    │    │   Database      │
│   Vultr 4GB     │    │   Vultr 8GB     │
├─────────────────┤    ├─────────────────┤
│  Nginx + App    │    │  MySQL + Redis  │
│  Load Balancer  │    │  Master DB      │
└─────────────────┘    └─────────────────┘
         │
    ┌────┴────┐
    │  Load   │
    │ Balancer│
    │  (HA)   │
    └─────────┘
```

## 📋 Detailed Setup Guide

### 1. Domain and DNS Configuration

**Using Vultr DNS (Recommended)**

1. **Go to Products → DNS**
2. Add domain:
   - **Domain**: youragency.gov.local
   - **IP Address**: your-vultr-ip

3. **Create DNS Records**
   ```
   Type: A
   Name: @
   Data: your-vultr-ip
   TTL: 300

   Type: A
   Name: www
   Data: your-vultr-ip
   TTL: 300

   Type: A
   Name: api
   Data: your-vultr-ip
   TTL: 300

   Type: MX
   Name: @
   Data: 10 mail.youragency.gov.local
   TTL: 300
   ```

**Using External DNS**
- Point your domain's A record to your Vultr IP
- Allow 24-48 hours for DNS propagation

### 2. SSL Certificate Setup

**Option A: Let's Encrypt (Free & Automatic)**

```bash
# Install Certbot
sudo apt install certbot python3-certbot-nginx -y

# Get SSL certificate
sudo certbot --nginx -d youragency.gov.local -d www.youragency.gov.local

# Auto-renewal (runs twice daily)
sudo systemctl status certbot.timer
```

**Option B: Vultr Managed SSL**

Vultr doesn't provide managed SSL, but you can use:
- **Cloudflare** (free SSL proxy)
- **AWS Certificate Manager** (if using Route 53)
- **Commercial SSL certificates**

### 3. Database Configuration

**Option A: Self-Hosted MySQL (Recommended)**

```bash
# Install MySQL
sudo apt install mysql-server -y

# Secure MySQL installation
sudo mysql_secure_installation

# Create database and user
sudo mysql -u root -p

CREATE DATABASE tpt_gov_db;
CREATE USER 'tpt_gov_user'@'localhost' IDENTIFIED BY 'your-secure-password';
GRANT ALL PRIVILEGES ON tpt_gov_db.* TO 'tpt_gov_user'@'localhost';
FLUSH PRIVILEGES;
EXIT;
```

**Option B: Vultr Managed Database**

1. **Go to Products → Managed Databases**
2. Choose MySQL:
   - **Engine**: MySQL 8
   - **Plan**: 1 vCPU, 1GB RAM (~$15/month)
   - **Region**: Same as your instance

3. **Configure Access**
   - **Trusted Sources**: Add your instance IP
   - **SSL**: Required for security

4. **Get Connection Details**
   - Host, port, username, password from Overview tab

### 4. Firewall Configuration

**Vultr Firewall (Recommended)**

1. **Go to Products → Firewall**
2. Create firewall group: **TPT Government Firewall**
3. Configure rules:
   ```
   Inbound Rules:
   - Type: SSH, Protocol: TCP, Port: 22, Source: your-ip/32
   - Type: HTTP, Protocol: TCP, Port: 80, Source: 0.0.0.0/0
   - Type: HTTPS, Protocol: TCP, Port: 443, Source: 0.0.0.0/0
   - Type: Custom, Protocol: TCP, Port: 3306, Source: your-vultr-ip/32 (if separate DB)

   Outbound Rules:
   - Type: All, Protocol: All, Port: All, Destination: 0.0.0.0/0
   ```

4. **Attach to Instance**
   - Assign firewall group to your Vultr instance

### 5. Backup Strategy

**Vultr Automatic Backups**

1. **Go to your Instance → Settings**
2. Enable Automatic Backups:
   - **Schedule**: Daily backups
   - **Retention**: 7 days (free) or extended retention
   - **Cost**: $1/month per instance for extended retention

3. **Manual Snapshots**
   ```bash
   # Create snapshot
   vultr-cli snapshot create --instance-id your-instance-id --description "TPT Gov Backup"

   # List snapshots
   vultr-cli snapshot list
   ```

**Application-Level Backups**

```bash
# Database backup script
sudo nano /usr/local/bin/backup-db.sh

#!/bin/bash
DATE=$(date +%Y%m%d_%H%M%S)
mysqldump -u tpt_gov_user -p tpt_gov_db > /var/backups/db_$DATE.sql

# Make executable
sudo chmod +x /usr/local/bin/backup-db.sh

# Add to cron for daily backups
sudo crontab -e
# Add: 0 2 * * * /usr/local/bin/backup-db.sh
```

### 6. Monitoring Setup

**Vultr Server Monitoring (Free)**

1. **Go to your Instance → Monitoring**
2. Enable monitoring:
   - CPU usage
   - Memory usage
   - Disk usage
   - Network traffic

3. **Configure Alerts**
   - CPU > 80%
   - Memory > 85%
   - Disk > 90%

**Third-Party Monitoring**

```bash
# Install Netdata for detailed monitoring
bash <(curl -Ss https://my-netdata.io/kickstart.sh)

# Or install Prometheus + Grafana
sudo apt install prometheus grafana -y
```

## 🔧 Configuration Files

### Environment Configuration (.env)

```bash
# Application
APP_NAME="Your Government Agency"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://youragency.gov.local

# Database (Self-hosted)
DB_HOST=localhost
DB_PORT=3306
DB_NAME=tpt_gov_db
DB_USER=tpt_gov_user
DB_PASSWORD=your-secure-password

# Database (Vultr Managed)
DB_HOST=vdb-mysql-nyc1-12345.vultrdb.com
DB_PORT=16751
DB_NAME=tpt_gov_db
DB_USER=tpt_gov_user
DB_PASSWORD=your-managed-db-password

# Redis (if using)
REDIS_HOST=localhost
REDIS_PORT=6379
REDIS_PASSWORD=your-redis-password

# File Storage (Vultr Object Storage)
VULTR_OBJECT_STORAGE_ACCESS_KEY=your-access-key
VULTR_OBJECT_STORAGE_SECRET_KEY=your-secret-key
VULTR_OBJECT_STORAGE_REGION=ewr1
VULTR_OBJECT_STORAGE_BUCKET=tpt-gov-documents

# Security
JWT_SECRET=your-256-bit-jwt-secret
ENCRYPTION_KEY=your-256-bit-encryption-key

# Email (SendGrid recommended)
MAIL_MAILER=smtp
MAIL_HOST=smtp.sendgrid.net
MAIL_PORT=587
MAIL_USERNAME=apikey
MAIL_PASSWORD=your-sendgrid-api-key
```

### Nginx Configuration

```nginx
server {
    listen 80;
    server_name youragency.gov.local www.youragency.gov.local;
    return 301 https://$server_name$request_uri;
}

server {
    listen 443 ssl http2;
    server_name youragency.gov.local www.youragency.gov.local;

    # SSL Configuration
    ssl_certificate /etc/letsencrypt/live/youragency.gov.local/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/youragency.gov.local/privkey.pem;

    # Security headers
    add_header X-Frame-Options "DENY" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header X-XSS-Protection "1; mode=block" always;
    add_header Strict-Transport-Security "max-age=31536000; includeSubDomains" always;

    # Root directory
    root /var/www/html/public;
    index index.php index.html;

    # PHP handling
    location ~ \.php$ {
        try_files $uri =404;
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
        fastcgi_index index.php;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
    }

    # Static files
    location ~* \.(js|css|png|jpg|jpeg|gif|ico|svg|woff|woff2|ttf|eot)$ {
        expires 1y;
        add_header Cache-Control "public, immutable";
    }

    # Main application
    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }
}
```

## 📊 Scaling and Performance

### Vertical Scaling (Increase Resources)

1. **Resize Instance**
   - Go to your Instance → Settings → Resize
   - Choose larger plan
   - Confirm resize (requires restart)

2. **Add Block Storage**
   ```bash
   # Create block storage
   vultr-cli block-storage create \
     --region nyc1 \
     --size 50 \
     --label tpt-gov-storage

   # Attach to instance
   vultr-cli block-storage attach \
     --instance-id your-instance-id \
     --storage-id your-storage-id

   # Mount storage
   sudo mkdir /mnt/tpt-storage
   sudo mount /dev/vdb /mnt/tpt-storage
   ```

### Horizontal Scaling (Multiple Instances)

1. **Create Load Balancer**
   - Go to Products → Load Balancers
   - Configure ports (80, 443)
   - Add your instances as backend

2. **Add More Instances**
   - Create identical instances
   - Configure them identically
   - Add to load balancer

3. **Database Scaling**
   - Upgrade to larger instance for database
   - Or use Vultr Managed Database
   - Consider read replicas for high traffic

## 🔒 Security Best Practices

### Network Security
- ✅ Use Vultr Firewalls
- ✅ Enable SSL/TLS for all connections
- ✅ Use SSH keys instead of passwords
- ✅ Disable root SSH access
- ✅ Regular security updates

### Application Security
- ✅ Keep PHP and dependencies updated
- ✅ Use strong passwords and encryption keys
- ✅ Enable all security headers
- ✅ Regular security audits
- ✅ Monitor for suspicious activity

### Data Protection
- ✅ Enable database SSL connections
- ✅ Regular backups with encryption
- ✅ Secure API keys and secrets
- ✅ GDPR compliance features
- ✅ Data retention policies

## 💰 Cost Optimization

### Monthly Cost Breakdown

**Small Agency Setup (~$25/month)**
- Vultr 2GB: $12/month
- SSL Certificate: Free
- Backups: Free (7 days)
- Domain: $10-15/year
- Total: ~$25/month

**Medium Agency Setup (~$55/month)**
- Vultr 4GB: $24/month
- Vultr Managed Database: $15/month
- Load Balancer: $10/month
- Object Storage: $5/month
- Backups: $1/month
- Total: ~$55/month

**Large Agency Setup (~$110/month)**
- Vultr 8GB: $48/month
- Vultr Managed Database: $30/month
- Load Balancer: $10/month
- Object Storage: $15/month
- Additional Instance: $24/month
- Total: ~$110/month

### Cost Saving Strategies

1. **Choose Right Plan Size**
   - Monitor usage with built-in monitoring
   - Resize when needed (no downtime for upgrades)
   - Use smallest viable plan

2. **Optimize Storage**
   - Use Vultr Object Storage for files
   - Compress and optimize images
   - Clean up old backups

3. **Take Advantage of Free Services**
   - Automatic backups (7 days free)
   - Server monitoring (free)
   - DNS hosting (free)

4. **Monitor and Alert**
   - Set up billing alerts
   - Monitor resource usage
   - Optimize based on usage patterns

## 📈 Monitoring and Logging

### Vultr Server Monitoring

**Built-in Monitoring**
- Real-time system metrics
- Historical data and trends
- Custom alerts and notifications
- Resource usage tracking

### Application Logging

```bash
# Configure log rotation
sudo nano /etc/logrotate.d/tpt-gov

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
```

### Third-Party Monitoring

**Uptime Monitoring**
- **UptimeRobot**: Free tier available
- **Pingdom**: Advanced monitoring features
- **New Relic**: Application performance monitoring

## 🚨 Troubleshooting

### Common Issues

**❌ SSH Connection Failed**
```bash
# Check firewall rules
vultr-cli firewall rule list your-firewall-id

# Verify SSH key
ssh -T git@github.com

# Reset root password if needed
vultr-cli instance restart your-instance-id
```

**❌ Application Not Loading**
```bash
# Check if services are running
sudo systemctl status nginx
sudo systemctl status php8.2-fpm
sudo systemctl status mysql

# Check application logs
sudo tail -f /var/log/nginx/error.log
sudo tail -f /var/log/tpt-gov/app.log
```

**❌ Database Connection Issues**
```bash
# Test local database
mysql -u tpt_gov_user -p tpt_gov_db

# Check database service
sudo systemctl status mysql

# Verify connection string in .env
cat .env | grep DB_
```

**❌ SSL Certificate Issues**
```bash
# Check certificate validity
openssl s_client -connect youragency.gov.local:443 -servername youragency.gov.local

# Renew Let's Encrypt certificate
sudo certbot renew

# Check nginx configuration
sudo nginx -t
sudo systemctl reload nginx
```

### Getting Help

1. **Vultr Support**: 24/7 expert support
2. **Community Forum**: vultr.com/community
3. **Documentation**: docs.vultr.com
4. **Status Page**: status.vultr.com

## 🎯 Success Metrics

Track these KPIs for successful Vultr deployment:

**📈 Performance**
- Page load time < 2 seconds
- API response time < 500ms
- Error rate < 0.1%
- Uptime > 99.9%

**💰 Cost Efficiency**
- Cost per user < $1/month
- Resource utilization > 70%
- Monthly cost < budget
- No unexpected charges

**🔒 Security**
- All security scans pass
- No unauthorized access incidents
- SSL certificate valid
- Regular backups successful

## 🚀 Advanced Vultr Features

### Vultr Object Storage

**S3-Compatible Storage**
```bash
# Install s3cmd
sudo apt install s3cmd -y

# Configure for Vultr Object Storage
s3cmd --configure

# Create bucket
s3cmd mb s3://tpt-gov-documents

# Upload files
s3cmd put file.pdf s3://tpt-gov-documents/
```

### Vultr Kubernetes Engine

**Managed Kubernetes**
1. **Go to Products → Kubernetes**
2. Create cluster:
   - Choose region and version
   - Configure node pools
   - Deploy your application

### Vultr Managed Database

**Fully Managed MySQL/PostgreSQL**
- Automatic backups
- High availability options
- Performance monitoring
- Security hardening

---

## 🎉 Ready to Deploy on Vultr?

**Follow these steps:**

1. **Create Vultr Account** (2 minutes)
2. **Launch Instance** (5 minutes)
3. **Configure Domain & DNS** (10 minutes)
4. **Deploy Application** (5 minutes)
5. **Set up SSL Certificate** (5 minutes)

**Total Time: ~30 minutes**

Your government platform will be running on Vultr's high-performance and cost-effective infrastructure!

---

[← Linode Guide](linode.md) | [Getting Started](../getting-started.md) | [Hetzner Guide](hetzner.md)
