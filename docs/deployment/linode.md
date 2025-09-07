# 🟠 Linode (Akamai) Deployment Guide

This guide provides comprehensive instructions for deploying the TPT Government Platform on Linode (now part of Akamai), offering a cost-effective and reliable alternative to Digital Ocean with strong compliance certifications.

## 🎯 Why Linode for Government?

### ✅ **Government Compliance**
- **SOC 2 Type II Certified** - Enterprise-grade security
- **ISO 27001 Certified** - Information security management
- **GDPR Compliant** - European data protection
- **HIPAA Compliant** - Healthcare data protection
- **PCI DSS Certified** - Payment card industry compliance

### 💰 **Cost-Effective**
- **Starting at $5/month** - Most affordable option
- **Transparent pricing** - No hidden costs
- **Free inbound bandwidth** - Generous data allowances
- **Hourly billing** - Pay only for what you use
- **No long-term contracts** - Cancel anytime

### 🏗️ **Reliable Infrastructure**
- **99.9% uptime SLA** - High availability guarantee
- **Global data centers** - 11 worldwide locations
- **SSD storage** - Fast performance
- **DDoS protection** - Built-in security
- **24/7 support** - Expert technical assistance

## 🚀 Quick Start (10 Minutes)

### Step 1: Linode Account Setup

1. **Create Linode Account**
   - Go to [linode.com](https://linode.com)
   - Sign up for free account
   - Verify email and add payment method
   - Complete account verification

2. **Generate API Token (Optional)**
   ```bash
   # For automated deployments
   # Generate personal access token in Account → API Tokens
   ```

### Step 2: Launch Linode Instance

**Option A: Cloud Manager (GUI)**

1. **Go to Linodes → Create Linode**
   - Click "Create Linode"
   - Choose region (closest to your users)

2. **Choose Linux Distribution**
   - **Ubuntu 22.04 LTS** (recommended)
   - **Debian 11** (alternative)
   - **CentOS 7** (legacy support)

3. **Select Plan**
   ```
   Nanode 1GB: 1GB RAM, 1 CPU, 25GB SSD - $5/month
   Linode 2GB: 2GB RAM, 1 CPU, 50GB SSD - $10/month
   Linode 4GB: 4GB RAM, 2 CPU, 80GB SSD - $20/month
   Linode 8GB: 8GB RAM, 4 CPU, 160GB SSD - $40/month
   ```

4. **Configure Instance**
   - **Label**: tpt-gov-server
   - **Tags**: web, government, tpt
   - **Private IP**: Enable (recommended)

5. **Add SSH Keys**
   - Upload your public SSH key
   - Or use Linode-generated key pair

6. **Boot the Instance**

**Option B: Linode CLI (Automated)**

```bash
# Install Linode CLI
pip3 install linode-cli

# Authenticate
linode-cli

# Create Linode instance
linode-cli linodes create \
  --type g6-nanode-1 \
  --region us-east \
  --image linode/ubuntu22.04 \
  --label tpt-gov-server \
  --tags web,government,tpt \
  --root_pass your-secure-password \
  --authorized_keys "ssh-rsa AAAA... your-key"
```

### Step 3: Deploy Application

```bash
# Connect to your Linode
ssh root@your-linode-ip

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

## 🏗️ Production Architecture on Linode

### Single Server Setup (Most Agencies)

```
┌─────────────────────────────────────┐
│         Linode Instance             │
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
│   Linode 4GB    │    │   Linode 8GB    │
├─────────────────┤    ├─────────────────┤
│  Nginx + App    │    │  MySQL + Redis  │
│  Load Balancer  │    │  Master DB      │
└─────────────────┘    └─────────────────┘
         │
    ┌────┴────┐
    │  Node   │
    │ Balancer│
    │ (HA)    │
    └─────────┘
```

## 📋 Detailed Setup Guide

### 1. Domain and DNS Configuration

**Using Linode DNS (Recommended)**

1. **Go to Domains → Create Domain**
2. Add your domain:
   - **Domain**: youragency.gov.local
   - **SOA Email**: admin@youragency.gov.local
   - **TTL**: 300 (5 minutes)

3. **Create DNS Records**
   ```
   Type: A
   Hostname: @
   Value: your-linode-ip
   TTL: 300

   Type: A
   Hostname: www
   Value: your-linode-ip
   TTL: 300

   Type: A
   Hostname: api
   Value: your-linode-ip
   TTL: 300

   Type: MX
   Hostname: @
   Value: 10 mail.youragency.gov.local
   TTL: 300
   ```

**Using External DNS**
- Point your domain's A record to your Linode IP
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

**Option B: Linode Managed SSL**

Linode doesn't provide managed SSL, but you can use:
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

**Option B: Linode Managed Database**

1. **Go to Databases → Create Database**
2. Choose MySQL:
   - **Engine**: MySQL 8
   - **Plan**: Nanode 1GB (~$15/month)
   - **Region**: Same as your Linode

3. **Configure Access**
   - **Allow List**: Add your Linode IP
   - **SSL**: Required for security

4. **Get Connection Details**
   - Host, port, username, password from Access tab

### 4. Firewall Configuration

**Linode Cloud Firewall (Recommended)**

1. **Go to Firewalls → Create Firewall**
2. Create firewall: **TPT Government Firewall**
3. Configure rules:
   ```
   Inbound Rules:
   - SSH: TCP 22, Sources: your-ip/32
   - HTTP: TCP 80, Sources: 0.0.0.0/0
   - HTTPS: TCP 443, Sources: 0.0.0.0/0
   - MySQL: TCP 3306, Sources: your-linode-ip/32 (if separate DB server)

   Outbound Rules:
   - All: All protocols, Destinations: 0.0.0.0/0
   ```

4. **Attach to Linode**
   - Assign firewall to your Linode instance

### 5. Backup Strategy

**Linode Backup Service**

1. **Go to your Linode → Backups**
2. Enable Linode Backups:
   - **Schedule**: Daily backups
   - **Retention**: 7 days (free) or 30 days ($2/month)
   - **Automatic**: Enabled

3. **Manual Backups**
   ```bash
   # Create manual backup
   linode-cli linodes backup-create your-linode-id

   # List backups
   linode-cli linodes backups-list your-linode-id
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

**Linode Longview (Free)**

1. **Go to Longview → Create Longview Client**
2. Install Longview agent:
   ```bash
   wget -O /tmp/longview.sh https://lv.linode.com/longview.sh
   sudo sh /tmp/longview.sh
   ```

3. **Configure Monitoring**
   - System metrics (CPU, memory, disk, network)
   - Process monitoring
   - Service status
   - Custom alerts

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

# Database (Linode Managed)
DB_HOST=lin-123-456-789.database.linode.com
DB_PORT=3306
DB_NAME=tpt_gov_db
DB_USER=tpt_gov_user
DB_PASSWORD=your-managed-db-password

# Redis (if using)
REDIS_HOST=localhost
REDIS_PORT=6379
REDIS_PASSWORD=your-redis-password

# File Storage (Linode Object Storage)
LINODE_OBJECT_STORAGE_ACCESS_KEY=your-access-key
LINODE_OBJECT_STORAGE_SECRET_KEY=your-secret-key
LINODE_OBJECT_STORAGE_CLUSTER=us-east-1
LINODE_OBJECT_STORAGE_BUCKET=tpt-gov-documents

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

1. **Resize Linode**
   - Shutdown Linode (required for resize)
   - Go to your Linode → Resize
   - Choose larger plan
   - Boot Linode

2. **Add Block Storage**
   ```bash
   # Create block storage volume
   linode-cli volumes create \
     --label tpt-gov-storage \
     --size 50 \
     --region us-east \
     --linode_id your-linode-id

   # Attach to Linode
   sudo mkdir /mnt/tpt-storage
   sudo mount /dev/disk/by-id/scsi-0Linode_Volume_tpt-gov-storage /mnt/tpt-storage
   ```

### Horizontal Scaling (Multiple Linodes)

1. **Create Node Balancer**
   - Go to NodeBalancers → Create NodeBalancer
   - Configure ports (80, 443)
   - Add your Linodes as backend nodes

2. **Add More Linodes**
   - Create identical Linodes
   - Configure them identically
   - Add to NodeBalancer

3. **Database Scaling**
   - Upgrade to larger Linode for database
   - Or use Linode Managed Database
   - Consider read replicas for high traffic

## 🔒 Security Best Practices

### Network Security
- ✅ Use Linode Cloud Firewalls
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
- Linode 2GB: $10/month
- SSL Certificate: Free
- Backups: Free (7 days)
- Domain: $10-15/year
- Total: ~$25/month

**Medium Agency Setup (~$60/month)**
- Linode 4GB: $20/month
- Linode Managed Database: $15/month
- NodeBalancer: $10/month
- Object Storage: $5/month
- Backups: $2/month
- Total: ~$60/month

**Large Agency Setup (~$120/month)**
- Linode 8GB: $40/month
- Linode Managed Database: $30/month
- NodeBalancer: $10/month
- Object Storage: $15/month
- Additional Linode: $20/month
- Total: ~$120/month

### Cost Saving Strategies

1. **Choose Right Plan Size**
   - Monitor usage with Longview
   - Resize when needed (no downtime for upgrades)
   - Use smallest viable plan

2. **Optimize Storage**
   - Use Linode Object Storage for files
   - Compress and optimize images
   - Clean up old backups

3. **Take Advantage of Free Services**
   - Linode Backups (7 days free)
   - Longview monitoring (free)
   - DNS hosting (free)

4. **Monitor and Alert**
   - Set up billing alerts
   - Monitor resource usage
   - Optimize based on usage patterns

## 📈 Monitoring and Logging

### Linode Longview

**System Monitoring**
- Real-time system metrics
- Historical data and trends
- Custom alerts and notifications
- Process and service monitoring

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
linode-cli firewalls rules-list your-firewall-id

# Verify SSH key
ssh -T git@github.com

# Reset root password if needed
linode-cli linodes reboot your-linode-id
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

1. **Linode Support**: 24/7 expert support
2. **Community Forum**: linode.com/community
3. **Documentation**: linode.com/docs
4. **Status Page**: status.linode.com

## 🎯 Success Metrics

Track these KPIs for successful Linode deployment:

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

## 🚀 Advanced Linode Features

### Linode Object Storage

**S3-Compatible Storage**
```bash
# Install s3cmd
sudo apt install s3cmd -y

# Configure for Linode Object Storage
s3cmd --configure

# Create bucket
s3cmd mb s3://tpt-gov-documents

# Upload files
s3cmd put file.pdf s3://tpt-gov-documents/
```

### Linode Managed Kubernetes

**For Container Orchestration**
1. **Go to Kubernetes → Create Cluster**
2. Choose plan and region
3. Configure node pools
4. Deploy your application

### Linode Managed Database

**Fully Managed MySQL/PostgreSQL**
- Automatic backups
- High availability options
- Performance monitoring
- Security hardening

---

## 🎉 Ready to Deploy on Linode?

**Follow these steps:**

1. **Create Linode Account** (2 minutes)
2. **Launch Linode Instance** (5 minutes)
3. **Configure Domain & DNS** (10 minutes)
4. **Deploy Application** (5 minutes)
5. **Set up SSL Certificate** (5 minutes)

**Total Time: ~30 minutes**

Your government platform will be running on Linode's cost-effective and reliable infrastructure!

---

[← Google Cloud Guide](google-cloud.md) | [Getting Started](../getting-started.md) | [Vultr Guide](vultr.md)
