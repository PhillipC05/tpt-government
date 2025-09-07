# TPT Government Platform - Multi-Stage Dockerfile
# This Dockerfile is OPTIONAL and provides containerized deployment

# Base stage with common dependencies
FROM php:8.1-apache AS base

# Install system dependencies
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    libzip-dev \
    libcurl4-openssl-dev \
    libssl-dev \
    zip \
    unzip \
    nodejs \
    npm \
    && docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd zip curl

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /var/www/html

# Copy composer files
COPY composer.json composer.lock ./

# Install PHP dependencies
RUN composer install --no-dev --optimize-autoloader --no-interaction

# Copy application code
COPY . .

# Set permissions
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html \
    && chmod -R 775 /var/www/html/cache \
    && chmod -R 775 /var/www/html/sessions \
    && chmod -R 775 /var/www/html/logs \
    && chmod -R 775 /var/www/html/backups

# Configure Apache
RUN a2enmod rewrite headers ssl
COPY docker/apache/000-default.conf /etc/apache2/sites-available/000-default.conf

# Expose port
EXPOSE 80

# Health check
HEALTHCHECK --interval=30s --timeout=10s --start-period=5s --retries=3 \
    CMD curl -f http://localhost/health || exit 1

# ====================================================================================
# Development stage
FROM base AS dev

# Install development dependencies
RUN composer install --no-interaction

# Install Node.js dependencies for development
RUN npm install

# Copy development configuration
COPY docker/apache/dev.conf /etc/apache2/sites-available/000-default.conf

# Enable development modules
RUN a2enmod headers

# Development command
CMD ["apache2-foreground"]

# ====================================================================================
# Application stage (production-ready)
FROM base AS app

# Install production Node.js dependencies and build assets
RUN npm ci --only=production && npm run build

# Remove development files
RUN rm -rf node_modules \
    && rm -rf tests \
    && rm -rf docker \
    && rm -rf .git \
    && rm -rf .github

# Production Apache configuration
COPY docker/apache/prod.conf /etc/apache2/sites-available/000-default.conf

# Security hardening
RUN rm -f /usr/bin/composer \
    && apt-get remove -y git \
    && apt-get autoremove -y \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# Production command
CMD ["apache2-foreground"]

# ====================================================================================
# Node.js stage for frontend development
FROM node:18-alpine AS node

WORKDIR /app

# Copy package files
COPY package*.json ./

# Install dependencies
RUN npm ci

# Copy source code
COPY . .

# Development server
EXPOSE 3000
CMD ["npm", "run", "dev"]

# ====================================================================================
# Nginx stage for production proxy (optional)
FROM nginx:alpine AS nginx

# Copy nginx configuration
COPY docker/nginx/nginx.conf /etc/nginx/nginx.conf
COPY docker/nginx/default.conf /etc/nginx/conf.d/default.conf

# Copy SSL certificates (if available)
COPY docker/nginx/ssl /etc/nginx/ssl

# Copy static files
COPY --from=app /var/www/html/public /var/www/html

# Expose ports
EXPOSE 80 443

# Health check
HEALTHCHECK --interval=30s --timeout=10s --start-period=5s --retries=3 \
    CMD curl -f http://localhost/health || exit 1

# ====================================================================================
# Database migration stage (optional)
FROM base AS migrator

# Copy migration files
COPY src/php/migrations /var/www/html/migrations

# Run migrations
CMD ["php", "migrations/migrate.php"]
