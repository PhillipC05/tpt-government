# 🇪🇺 Hetzner Deployment Guide

This guide provides comprehensive instructions for deploying the TPT Government Platform on Hetzner, offering Europe's most cost-effective cloud infrastructure with excellent compliance certifications and data protection standards.

## 🎯 Why Hetzner for Government?

### ✅ **European Compliance**
- **ISO 27001 Certified** - Information security management
- **GDPR Compliant** - Strong European data protection
- **DSGVO Compliant** - German data protection standards
- **TISAX Certified** - Automotive industry security (highest standards)
- **BSI IT-Grundschutz** - German Federal Office for Information Security

### 💰 **Exceptionally Cost-Effective**
- **Starting at €3/month** - Most affordable in Europe
- **No hidden costs** - Transparent pricing
- **Free bandwidth** - Generous data transfer allowances
- **No setup fees** - Get started immediately
- **Monthly billing** - No long-term commitments

### 🏗️ **Reliable Infrastructure**
- **99.9% uptime SLA** - High availability guarantee
- **European data centers** - Frankfurt, Nuremberg, Helsinki
- **SSD storage** - Fast performance
- **DDoS protection** - Built-in security
- **24/7 support** - Expert technical assistance

## 🚀 Quick Start (10 Minutes)

### Step 1: Hetzner Account Setup

1. **Create Hetzner Account**
   - Go to [hetzner.com](https://hetzner.com)
   - Sign up for free account
   - Verify email and add payment method
   - Complete account verification

2. **Generate API Token (Optional)**
   ```bash
   # For automated deployments
   # Generate API token in Console → Security → API Tokens
   ```

### Step 2: Launch Cloud Server

**Option A: Hetzner Cloud Console (GUI)**

1. **Go to Cloud → Servers**
   - Click "Add Server"
   - Choose location (Germany recommended for GDPR)

2. **Select Server Type**
   - **Cloud Server** (recommended)
   - **Dedicated Server** (for high performance)
   - **Storage Box** (for backups)

3. **Choose Image**
   - **Ubuntu 22.04** (recommended)
   - **Debian 11** (alternative)
   - **CentOS 7** (legacy support)

4. **Select Server Configuration**
   ```
   CX11: 1 vCPU, 2GB RAM, 20GB NVMe - €3.79/month
   CX21: 2 vCPU, 4GB RAM, 40GB NVMe - €6.89/month
   CX31: 2 vCPU, 8GB RAM, 80GB NVMe - €12.89/month
   CX41: 4 vCPU, 16GB RAM, 160GB NVMe - €25.89/month
   ```

5. **Configure Server**
   - **Name**: tpt-gov-server
   - **SSH Keys**: Add your public SSH key
   - **Networks**: Default network
   - **Firewalls**: Create new firewall

6. **Create Server**

**Option B: Hetzner CLI (Automated)**

```bash
# Install Hetzner CLI
curl -fsSL https://github.com/hetznercloud/cli/releases/latest/download/hcloud-linux-amd64.tar.gz | tar -xzv
sudo mv hcloud /usr/local/bin/

# Authenticate
hcloud context create tpt-gov

# Create server
hcloud server create \
  --name tpt-gov-server \
  --type cx21 \
  --image ubuntu-22.04 \
  --location nbg1 \
  --ssh-key your-ssh-key-name
```

### Step 3: Deploy Application

```bash
# Connect to your server
ssh root@your-hetzner-ip

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

## 🏗️ Production Architecture on Hetzner

### Single Server Setup (Most Agencies)

```
┌─────────────────────────────────────┐
│         Hetzner Server              │
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
│   Hetzner CX31  │    │   Hetzner CX41  │
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

**Using Hetzner DNS (Recommended)**

1. **Go to DNS Console → Add Zone**
2. Add domain:
   - **Domain**: youragency.gov.local
   - **TTL**: 86400 (24 hours)

3. **Create DNS Records**
   ```
   Type: A
   Name: @
   Value: your-hetzner-ip
   TTL: 3600

   Type: A
   Name: www
   Value: your-hetzner-ip
   TTL: 3600

   Type: A
   Name: api
   Value: your-hetzner-ip
   TTL: 3600

   Type: MX
   Name: @
   Value: mail.youragency.gov.local
   Priority: 10
   TTL: 3600
   ```

**Using External DNS**
- Point your domain's A record to your Hetzner IP
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

**Option B: Hetzner Managed SSL**

Hetzner doesn't provide managed SSL, but you can use:
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

**Option B: External Managed Database**

Hetzner doesn't offer managed databases, but you can use:
- **AWS RDS** (if you need managed database)
- **Google Cloud SQL**
- **Azure Database**
- **PlanetScale** (MySQL-compatible)

### 4. Firewall Configuration

**Hetzner Cloud Firewall (Recommended)**

1. **Go to Cloud → Firewalls**
2. Create firewall: **TPT Government Firewall**
3. Configure rules:
   ```
   Inbound Rules:
   - Description: SSH, IP: your-ip/32, Port: 22, Protocol: TCP
   - Description: HTTP, IP: 0.0.0.0/0, Port: 80, Protocol: TCP
   - Description: HTTPS, IP: 0.0.0.0/0, Port: 443, Protocol: TCP
   - Description: MySQL, IP: your-hetzner-ip/32, Port: 3306, Protocol: TCP (if separate DB)

   Outbound Rules:
   - Description: All, IP: 0.0.0.0/0, Port: 1-65535, Protocol: TCP
   - Description: All, IP: 0.0.0.0/0, Port: 1-65535, Protocol: UDP
   ```

4. **Attach to Server**
   - Assign firewall to your Hetzner server

### 5. Backup Strategy

**Hetzner Automatic Backups**

1. **Go to your Server → Backups**
2. Enable Automatic Backups:
   - **Schedule**: Daily backups
   - **Retention**: 7 days (free)
   - **Cost**: €0.50/month per TB for extended retention

3. **Manual Snapshots**
   ```bash
   # Create snapshot
   hcloud server create-image your-server-name --type snapshot --description "TPT Gov Backup"

   # List snapshots
   hcloud image list --type snapshot
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

**Hetzner Server Monitoring (Free)**

1. **Go to Cloud → Monitoring**
2. Enable monitoring for your server:
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

# Database (External Managed - if using)
DB_HOST=your-external-db-host
DB_PORT=3306
DB_NAME=tpt_gov_db
DB_USER=tpt_gov_user
DB_PASSWORD=your-managed-db-password

# Redis (if using)
REDIS_HOST=localhost
REDIS_PORT=6379
REDIS_PASSWORD=your-redis-password

# File Storage (Hetzner Storage Box)
HETZNER_STORAGE_BOX_USER=u123456
HETZNER_STORAGE_BOX_PASS=your-storage-password
HETZNER_STORAGE_BOX_URL=u123456.your-storagebox.de
HETZNER_STORAGE_BOX_BUCKET=tpt-gov-documents

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

1. **Resize Server**
   - Go to your Server → Resize
   - Choose larger plan
   - Confirm resize (requires restart)

2. **Add Volumes**
   ```bash
   # Create volume
   hcloud volume create \
     --name tpt-gov-storage \
     --size 50 \
     --location nbg1

   # Attach to server
   hcloud volume attach tpt-gov-storage --server tpt-gov-server

   # Mount volume
   sudo mkdir /mnt/tpt-storage
   sudo mount /dev/sdb /mnt/tpt-storage
   ```

### Horizontal Scaling (Multiple Servers)

1. **Create Load Balancer**
   - Go to Cloud → Load Balancers
   - Configure ports (80, 443)
   - Add your servers as targets

2. **Add More Servers**
   - Create identical servers
   - Configure them identically
   - Add to load balancer

3. **Database Scaling**
   - Upgrade to larger server for database
   - Or use external managed database
   - Consider read replicas for high traffic

## 🔒 Security Best Practices

### Network Security
- ✅ Use Hetzner Cloud Firewalls
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

**Small Agency Setup (~€15/month)**
- Hetzner CX21: €6.89/month
- SSL Certificate: Free
- Backups: Free (7 days)
- Domain: €10-15/year
- Total: ~€15/month

**Medium Agency Setup (~€35/month)**
- Hetzner CX31: €12.89/month
- External Managed DB: €15/month
- Load Balancer: €5/month
- Storage Box: €3/month
- Backups: €1/month
- Total: ~€35/month

**Large Agency Setup (~€70/month)**
- Hetzner CX41: €25.89/month
- External Managed DB: €25/month
- Load Balancer: €5/month
- Storage Box: €10/month
- Additional Server: €12.89/month
- Total: ~€70/month

### Cost Saving Strategies

1. **Choose Right Plan Size**
   - Monitor usage with built-in monitoring
   - Resize when needed (no downtime for upgrades)
   - Use smallest viable plan

2. **Optimize Storage**
   - Use Hetzner Storage Box for files (€1/TB/month)
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

### Hetzner Server Monitoring

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
hcloud firewall describe your-firewall

# Verify SSH key
ssh -T git@github.com

# Reset root password if needed
hcloud server reset-password your-server-name
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

1. **Hetzner Support**: 24/7 expert support
2. **Community Forum**: community.hetzner.com
3. **Documentation**: docs.hetzner.com
4. **Status Page**: status.hetzner.com

## 🎯 Success Metrics

Track these KPIs for successful Hetzner deployment:

**📈 Performance**
- Page load time < 2 seconds
- API response time < 500ms
- Error rate < 0.1%
- Uptime > 99.9%

**💰 Cost Efficiency**
- Cost per user < €0.50/month
- Resource utilization > 70%
- Monthly cost < budget
- No unexpected charges

**🔒 Security**
- All security scans pass
- No unauthorized access incidents
- SSL certificate valid
- Regular backups successful

## 🚀 Advanced Hetzner Features

### Hetzner Storage Box

**S3-Compatible Storage**
```bash
# Install s3cmd
sudo apt install s3cmd -y

# Configure for Hetzner Storage Box
s3cmd --configure

# Create bucket
s3cmd mb s3://tpt-gov-documents

# Upload files
s3cmd put file.pdf s3://tpt-gov-documents/
```

### Hetzner Private Networks

**Secure Internal Networking**
1. **Go to Cloud → Networks**
2. Create private network:
   - **Name**: tpt-gov-network
   - **IP Range**: 10.0.0.0/16

3. **Attach Servers**
   - Add your servers to the private network
   - Configure internal communication

### Hetzner Load Balancers

**High Availability Load Balancing**
1. **Go to Cloud → Load Balancers**
2. Create load balancer:
   - **Name**: tpt-gov-lb
   - **Type**: HTTP/HTTPS

3. **Configure Services**
   - HTTP on port 80
   - HTTPS on port 443
   - Health checks for your servers

---

## 🎉 Ready to Deploy on Hetzner?

**Follow these steps:**

1. **Create Hetzner Account** (2 minutes)
2. **Launch Server** (5 minutes)
3. **Configure Domain & DNS** (10 minutes)
4. **Deploy Application** (5 minutes)
5. **Set up SSL Certificate** (5 minutes)

**Total Time: ~30 minutes**

Your government platform will be running on Europe's most cost-effective cloud infrastructure!

---

[← Vultr Guide](vultr.md) | [Getting Started](../getting-started.md) | [Alibaba Cloud Guide](alibaba-cloud.md)
