# 🌊 Digital Ocean Deployment Guide

This guide provides step-by-step instructions for deploying the TPT Government Platform on Digital Ocean, making it accessible to government agencies who prefer Digital Ocean's cost-effective and compliant cloud infrastructure.

## 🎯 Why Digital Ocean for Government?

### ✅ **Government-Friendly Features**
- **FedRAMP Moderate Authorized** - Meets federal security standards
- **HIPAA Compliant** - Suitable for health-related government services
- **GDPR Ready** - European data protection compliance
- **Transparent Pricing** - No hidden costs or complex billing
- **99.99% Uptime SLA** - Reliable for critical government services

### 💰 **Cost-Effective**
- **Starting at $6/month** for basic deployment
- **Predictable pricing** - No surprise bills
- **Free bandwidth** - Generous data transfer allowances
- **Managed services** - Reduce operational costs

### 🛡️ **Security & Compliance**
- **SOC 2 Type II Certified** - Enterprise-grade security
- **ISO 27001 Certified** - Information security management
- **Data encryption** at rest and in transit
- **Regular security audits** and penetration testing

## 🚀 Quick Start (15 Minutes)

### Step 1: Create Digital Ocean Account

1. Go to [digitalocean.com](https://digitalocean.com)
2. Sign up for an account
3. Verify your email
4. Add payment method (government P-Card accepted)

### Step 2: One-Click App Deployment

**Option A: Using Digital Ocean Marketplace (Recommended)**

1. Go to **Marketplace** → **Search for "TPT Government"**
2. Click **Create TPT Government Droplet**
3. Choose your plan:
   - **Basic**: $12/month (1 GB RAM, 1 vCPU) - Development
   - **Standard**: $24/month (2 GB RAM, 1 vCPU) - Small agency
   - **Professional**: $48/month (4 GB RAM, 2 vCPUs) - Medium agency
   - **Enterprise**: $96/month (8 GB RAM, 4 vCPUs) - Large agency

4. Select **Region** (choose closest to your users):
   - East Coast: New York, Virginia
   - West Coast: San Francisco, Los Angeles
   - Central: Chicago, Dallas

5. Configure **Authentication**:
   - Choose SSH key (recommended for security)
   - Or use password (less secure but easier)

6. **Launch Application**

**Option B: Manual Droplet Setup**

```bash
# Create Ubuntu 22.04 droplet
# SSH into your droplet
ssh root@your-droplet-ip

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

## 🏗️ Production Architecture

### Single Server Setup (Most Agencies)

```
┌─────────────────────────────────────┐
│         Digital Ocean Droplet       │
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

### High Availability Setup (Large Agencies)

```
┌─────────────────┐    ┌─────────────────┐
│   Load Balancer │    │   Load Balancer │
│   (DO Managed)  │    │   (DO Managed)  │
└─────────────────┘    └─────────────────┘
         │                        │
    ┌────┴────┐              ┌────┴────┐
    │  App    │              │  App    │
    │ Droplet │              │ Droplet │
    └─────────┘              └─────────┘
         │                        │
    ┌────┴────┐              ┌────┴────┐
    │ Managed │              │ Managed │
    │ MySQL   │              │ MySQL   │
    │ Cluster │              │ Cluster │
    └─────────┘              └─────────┘
```

## 📋 Detailed Setup Guide

### 1. Domain & DNS Configuration

**Using Digital Ocean DNS (Recommended)**

1. Go to **Networking** → **Domains**
2. Add your domain (e.g., `govagency.local`)
3. Create DNS records:
   ```
   Type: A
   Name: @
   Value: your-droplet-ip

   Type: CNAME
   Name: www
   Value: @

   Type: CNAME
   Name: api
   Value: @
   ```

**Using External DNS**
- Point your domain's A record to your droplet IP
- Allow 24-48 hours for DNS propagation

### 2. SSL Certificate Setup

**Option A: Let's Encrypt (Free & Automatic)**

```bash
# Install Certbot
sudo apt install certbot python3-certbot-nginx -y

# Get SSL certificate
sudo certbot --nginx -d yourdomain.com -d www.yourdomain.com

# Auto-renewal (runs twice daily)
sudo systemctl status certbot.timer
```

**Option B: Digital Ocean Managed SSL**

1. Go to **Load Balancers** (if using)
2. Enable **SSL Termination**
3. Upload your certificate or use DO's managed certificates

### 3. Database Configuration

**Option A: Digital Ocean Managed Database (Recommended)**

1. Go to **Databases** → **Create Database Cluster**
2. Choose **MySQL 8**
3. Select plan ($15/month for basic)
4. Configure:
   - **Database Name**: tpt_gov_db
   - **Database User**: tpt_gov_user
   - **Enable SSL**: Required for security

5. Get connection details from **Connection Details**

**Option B: Self-Hosted Database**

```bash
# In your .env file
DB_HOST=localhost
DB_PORT=3306
DB_NAME=tpt_gov_db
DB_USER=tpt_gov_user
DB_PASSWORD=your_secure_password
```

### 4. Firewall Configuration

**Digital Ocean Cloud Firewall (Recommended)**

1. Go to **Networking** → **Firewalls**
2. Create new firewall: **TPT Government Firewall**
3. Inbound Rules:
   ```
   HTTP    TCP    80     0.0.0.0/0
   HTTPS   TCP    443    0.0.0.0/0
   SSH     TCP    22     your-ip/32
   ```
4. Outbound Rules:
   ```
   All TCP/UDP    All Ports    0.0.0.0/0
   ```
5. Apply to your droplet

### 5. Backup Strategy

**Automated Backups**

1. **Database Backups** (Daily)
   ```bash
   # Create backup script
   sudo nano /usr/local/bin/backup-db.sh

   #!/bin/bash
   DATE=$(date +%Y%m%d_%H%M%S)
   mysqldump -u tpt_gov_user -p tpt_gov_db > /backups/db_$DATE.sql
   ```

2. **File Backups** (Daily)
   ```bash
   # Backup uploads and configs
   tar -czf /backups/files_$DATE.tar.gz /var/www/html/uploads /var/www/html/config
   ```

3. **Digital Ocean Snapshots** (Weekly)
   - Go to your droplet
   - **Actions** → **Take Snapshot**
   - Schedule weekly snapshots

### 6. Monitoring Setup

**Basic Monitoring**

1. **Enable Digital Ocean Monitoring**
   - Go to **Droplet** → **Monitoring**
   - Enable all metrics

2. **Install Monitoring Agent**
   ```bash
   # Install DO monitoring agent
   curl -sSL https://repos.insights.digitalocean.com/install.sh | sudo bash
   ```

3. **Set up Alerts**
   - CPU usage > 80%
   - Memory usage > 85%
   - Disk usage > 90%
   - Service down notifications

## 🔧 Configuration Files

### Environment Configuration (.env)

```bash
# Application
APP_NAME="Your Government Agency"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://youragency.gov.local

# Database (use DO Managed Database details)
DB_HOST=db-mysql-nyc1-12345-do-user-1234567-0.b.db.ondigitalocean.com
DB_PORT=25060
DB_NAME=tpt_gov_db
DB_USER=tpt_gov_user
DB_PASSWORD=your-db-password

# Redis (DO Managed Redis if using)
REDIS_HOST=redis-nyc1-12345-do-user-1234567-0.b.db.ondigitalocean.com
REDIS_PORT=25061
REDIS_PASSWORD=your-redis-password

# Security
JWT_SECRET=your-256-bit-jwt-secret
ENCRYPTION_KEY=your-256-bit-encryption-key

# Email (use SMTP or SendGrid)
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

## 📊 Scaling Options

### Vertical Scaling (Increase Droplet Size)

1. **Power off droplet** (required for resizing)
2. Go to **Droplet** → **Resize**
3. Choose larger plan
4. Power on droplet
5. Update any static IPs if needed

### Horizontal Scaling (Multiple Droplets)

1. **Create Load Balancer**
   - Go to **Load Balancers** → **Create Load Balancer**
   - Add your droplets as backend targets
   - Configure health checks

2. **Add More Droplets**
   - Create identical droplets
   - Configure them identically
   - Add to load balancer

3. **Database Scaling**
   - Upgrade to larger managed database plan
   - Or set up database cluster

## 🔒 Security Best Practices

### Network Security
- ✅ Use Digital Ocean Cloud Firewalls
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

## 📈 Performance Optimization

### Digital Ocean Specific Optimizations

1. **Use SSD Storage** - All droplets include SSD storage
2. **Enable CDN** - Use Digital Ocean Spaces CDN
3. **Optimize Images** - Use DO Spaces for static assets
4. **Database Indexing** - Optimize queries for performance
5. **Redis Caching** - Use DO Managed Redis

### Monitoring & Alerts

1. **Resource Monitoring**
   - CPU, memory, disk usage
   - Network traffic
   - Database connections

2. **Application Monitoring**
   - Response times
   - Error rates
   - User activity
   - API usage

3. **Security Monitoring**
   - Failed login attempts
   - Suspicious IP addresses
   - SSL certificate expiry
   - Security updates

## 💰 Cost Optimization

### Monthly Cost Breakdown

**Small Agency Setup (~$50/month)**
- Droplet (2GB): $24/month
- Managed Database: $15/month
- Load Balancer: $10/month
- SSL Certificate: Free
- Backups: $5/month

**Medium Agency Setup (~$150/month)**
- Droplet (4GB): $48/month
- Managed Database: $30/month
- Load Balancer: $10/month
- Spaces (Storage): $5/month
- Monitoring: $5/month

**Large Agency Setup (~$300/month)**
- 2x Droplets (4GB each): $96/month
- Managed Database Cluster: $60/month
- Load Balancer: $10/month
- Spaces (Storage): $20/month
- Monitoring: $10/month

### Cost Saving Tips

1. **Use Reserved Instances** for long-term deployments
2. **Monitor usage** and scale down when possible
3. **Use object storage** for large files
4. **Implement caching** to reduce database load
5. **Schedule backups** during off-peak hours

## 🆘 Troubleshooting

### Common Issues

**❌ "Connection refused"**
```bash
# Check if services are running
docker-compose ps

# Check logs
docker-compose logs -f app

# Restart services
docker-compose restart
```

**❌ "SSL certificate expired"**
```bash
# Renew Let's Encrypt certificate
sudo certbot renew

# Restart nginx
sudo systemctl restart nginx
```

**❌ "Database connection failed"**
```bash
# Check database status
sudo systemctl status mysql

# Test connection
mysql -h localhost -u tpt_gov_user -p tpt_gov_db
```

**❌ "Out of memory"**
```bash
# Check memory usage
free -h

# Increase swap space
sudo fallocate -l 1G /swapfile
sudo chmod 600 /swapfile
sudo mkswap /swapfile
sudo swapon /swapfile
```

### Getting Help

1. **Digital Ocean Documentation**: [docs.digitalocean.com](https://docs.digitalocean.com)
2. **TPT Government Docs**: [docs.tpt.gov](https://docs.tpt.gov)
3. **Community Support**: [community.tpt.gov](https://community.tpt.gov)
4. **Professional Services**: Contact TPT support

## 🎉 Success Metrics

Track these KPIs for successful deployment:

**📈 Performance**
- Page load time < 2 seconds
- Uptime > 99.9%
- Zero security incidents
- User satisfaction > 95%

**💰 Cost Efficiency**
- Cost per user < $1/month
- Resource utilization > 70%
- Backup success rate = 100%
- Incident response time < 1 hour

**🔒 Security**
- All security scans pass
- No data breaches
- Compliance audit success
- User trust scores > 90%

---

## 🚀 Ready to Deploy?

**Follow these steps:**

1. **Create Digital Ocean Account** (2 minutes)
2. **Launch Droplet** (5 minutes)
3. **Configure Domain & SSL** (10 minutes)
4. **Deploy Application** (5 minutes)
5. **Set up Monitoring** (5 minutes)

**Total Time: ~30 minutes**

Your government platform will be live and serving citizens!

---

[← Deployment Options](../README.md#deployment-options) | [Getting Started](../getting-started.md) | [Admin Guide](../admin/quick-start.md)
