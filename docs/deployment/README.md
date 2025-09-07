# TPT Government Platform - Deployment Guide

This guide covers multiple deployment options for the TPT Government Platform. **Docker is OPTIONAL** - you can deploy the platform using traditional methods if preferred.

## 🚀 Quick Start

### Option 1: Traditional Deployment (Recommended for most environments)

```bash
# 1. Install PHP 8.1+ and Node.js 18+
# 2. Install dependencies
composer install --no-dev --optimize-autoloader
npm ci --only=production

# 3. Configure environment
cp .env.example .env
# Edit .env with your database and other settings

# 4. Set up database
php src/php/migrations/migrate.php

# 5. Build assets
npm run build

# 6. Start the application
php -S localhost:8000 public/index.php
```

### Option 2: Docker Deployment (Optional)

```bash
# 1. Create environment file
cp .env.example .env

# 2. Start with Docker Compose
docker-compose up -d

# 3. Run migrations
docker-compose exec app php migrations/migrate.php
```

## 📋 Prerequisites

### Required (Both Docker and Traditional)

- **PHP 8.1 or higher** with extensions:
  - `pdo_mysql` or `pdo_pgsql`
  - `mbstring`
  - `curl`
  - `json`
  - `zip`
  - `gd` (for image processing)

- **Database**: MySQL 8.0+ or PostgreSQL 13+
- **Web Server**: Apache 2.4+ or Nginx 1.20+
- **SSL Certificate** (recommended for production)

### Optional (Enhanced Features)

- **Redis** 6.0+ (for caching and sessions)
- **Node.js** 18+ (for asset compilation)
- **Docker** 20.10+ (for containerized deployment)

## 🏗️ Traditional Deployment

### 1. Server Setup

```bash
# Ubuntu/Debian example
sudo apt update
sudo apt install php8.1 php8.1-cli php8.1-fpm php8.1-mysql php8.1-curl php8.1-json php8.1-zip php8.1-gd php8.1-mbstring
sudo apt install nginx mysql-server redis-server
sudo apt install nodejs npm
```

### 2. Application Setup

```bash
# Clone repository
git clone https://github.com/tpt/government-platform.git
cd government-platform

# Install dependencies
composer install --no-dev --optimize-autoloader
npm ci --only=production

# Set permissions
sudo chown -R www-data:www-data .
sudo chmod -R 755 .
sudo chmod -R 775 cache sessions logs backups
```

### 3. Database Configuration

```bash
# Create database
mysql -u root -p
CREATE DATABASE tpt_government;
CREATE USER 'tpt_user'@'localhost' IDENTIFIED BY 'secure_password';
GRANT ALL PRIVILEGES ON tpt_government.* TO 'tpt_user'@'localhost';
FLUSH PRIVILEGES;
EXIT;

# Run migrations
php src/php/migrations/migrate.php
```

### 4. Web Server Configuration

#### Nginx Configuration

```nginx
server {
    listen 80;
    server_name your-domain.com;
    root /path/to/government-platform/public;
    index index.php;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        include fastcgi_params;
        fastcgi_pass unix:/var/run/php/php8.1-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
    }

    location ~* \.(js|css|png|jpg|jpeg|gif|ico|svg)$ {
        expires 1y;
        add_header Cache-Control "public, immutable";
    }
}
```

#### Apache Configuration

```apache
<VirtualHost *:80>
    ServerName your-domain.com
    DocumentRoot /path/to/government-platform/public

    <Directory /path/to/government-platform/public>
        AllowOverride All
        Require all granted
    </Directory>

    ErrorLog ${APACHE_LOG_DIR}/tpt_error.log
    CustomLog ${APACHE_LOG_DIR}/tpt_access.log combined
</VirtualHost>
```

### 5. Environment Configuration

```bash
# Copy and configure environment file
cp .env.example .env

# Edit .env with your settings
nano .env
```

Example `.env` configuration:

```env
APP_ENV=production
APP_KEY=your-generated-app-key
APP_URL=https://your-domain.com

DB_CONNECTION=mysql
DB_HOST=localhost
DB_PORT=3306
DB_DATABASE=tpt_government
DB_USERNAME=tpt_user
DB_PASSWORD=your_secure_password

REDIS_HOST=localhost
REDIS_PORT=6379

MAIL_MAILER=smtp
MAIL_HOST=your-smtp-host
MAIL_PORT=587
MAIL_USERNAME=your-email@domain.com
MAIL_PASSWORD=your-email-password
```

## 🐳 Docker Deployment (Optional)

### Quick Start with Docker

```bash
# 1. Configure environment
cp .env.example .env
# Edit .env with your settings

# 2. Start services
docker-compose up -d

# 3. Run database migrations
docker-compose exec app php migrations/migrate.php

# 4. Build frontend assets
docker-compose exec app npm run build
```

### Docker Services

- **app**: PHP 8.1 + Apache (main application)
- **db**: MySQL 8.0 (database)
- **redis**: Redis 7 (cache and sessions)
- **nginx**: Nginx (reverse proxy, optional)
- **node**: Node.js (frontend development, optional)

### Docker Commands

```bash
# View logs
docker-compose logs -f

# Access container shell
docker-compose exec app bash

# Stop services
docker-compose down

# Rebuild and restart
docker-compose up -d --build
```

## 🔧 Advanced Configuration

### SSL/TLS Setup

```bash
# Using Certbot for Let's Encrypt
sudo apt install certbot python3-certbot-nginx
sudo certbot --nginx -d your-domain.com

# Or manually configure SSL
sudo cp your-cert.pem /etc/ssl/certs/
sudo cp your-key.pem /etc/ssl/private/
```

### Performance Optimization

```bash
# Enable OPcache
echo "opcache.enable=1" >> /etc/php/8.1/fpm/php.ini
echo "opcache.memory_consumption=256" >> /etc/php/8.1/fpm/php.ini

# Configure Redis for sessions and cache
# Update .env file with Redis settings
```

### Monitoring Setup

```bash
# Install monitoring tools
sudo apt install prometheus prometheus-node-exporter
sudo apt install grafana

# Configure log aggregation
# Install ELK stack or similar
```

## 🚀 Production Deployment Checklist

- [ ] Domain and SSL certificate configured
- [ ] Database created and migrated
- [ ] Environment variables set
- [ ] File permissions configured
- [ ] Web server configured and restarted
- [ ] Firewall configured
- [ ] Backup strategy implemented
- [ ] Monitoring and logging set up
- [ ] Security headers configured

## 🔍 Troubleshooting

### Common Issues

**Permission Errors:**
```bash
sudo chown -R www-data:www-data /path/to/app
sudo chmod -R 775 cache sessions logs backups
```

**Database Connection Issues:**
```bash
# Check MySQL service
sudo systemctl status mysql

# Test connection
mysql -u tpt_user -p tpt_government
```

**PHP Errors:**
```bash
# Check PHP error logs
tail -f /var/log/php8.1-fpm.log
tail -f /var/log/apache2/error.log
```

## 📚 Additional Resources

- [PHP 8.1 Documentation](https://www.php.net/docs.php)
- [Composer Documentation](https://getcomposer.org/doc/)
- [Nginx Documentation](https://nginx.org/en/docs/)
- [Docker Documentation](https://docs.docker.com/)
- [MySQL Documentation](https://dev.mysql.com/doc/)

## 🆘 Support

For deployment issues, check:
1. Application logs in `logs/` directory
2. Web server error logs
3. Database connection logs
4. PHP error logs

**Docker is completely optional** - the platform is designed to work efficiently in traditional hosting environments without containerization.
