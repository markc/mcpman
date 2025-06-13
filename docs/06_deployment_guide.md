# Deployment Guide

This guide covers deploying Laravel Loop Filament with MCP integration to production environments.

## Pre-Deployment Checklist

### System Requirements
- **PHP 8.2+** with required extensions
- **Web server**: Apache 2.4+ or Nginx 1.18+
- **Database**: MySQL 8.0+, PostgreSQL 13+, or optimized SQLite
- **Redis**: For caching and session management
- **SSL Certificate**: Required for production
- **Process Manager**: Supervisor for queue workers

### Required PHP Extensions
```bash
# Check required extensions
php -m | grep -E "(pdo|mbstring|openssl|tokenizer|xml|ctype|json|bcmath|curl|fileinfo|gd)"

# Install missing extensions (Ubuntu/Debian)
sudo apt install php8.2-{pdo,mbstring,openssl,tokenizer,xml,ctype,json,bcmath,curl,fileinfo,gd,redis,mysql}
```

## Environment Setup

### 1. Server Preparation

#### Ubuntu/Debian Server Setup
```bash
# Update system
sudo apt update && sudo apt upgrade -y

# Install PHP 8.2
sudo add-apt-repository ppa:ondrej/php
sudo apt update
sudo apt install php8.2-fpm php8.2-cli php8.2-common

# Install web server (Nginx example)
sudo apt install nginx

# Install database
sudo apt install mysql-server-8.0

# Install Redis
sudo apt install redis-server

# Install Composer
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer

# Install Node.js
curl -fsSL https://deb.nodesource.com/setup_18.x | sudo -E bash -
sudo apt install nodejs
```

### 2. Application Deployment

#### Clone and Setup Application
```bash
# Clone repository
cd /var/www
sudo git clone <repository-url> laravel-loop-filament
cd laravel-loop-filament

# Set permissions
sudo chown -R www-data:www-data /var/www/laravel-loop-filament
sudo chmod -R 755 /var/www/laravel-loop-filament
sudo chmod -R 775 storage bootstrap/cache

# Install dependencies
sudo -u www-data composer install --optimize-autoloader --no-dev
sudo -u www-data npm ci --only=production
```

#### Environment Configuration
```bash
# Copy and configure environment
sudo -u www-data cp .env.example .env
sudo -u www-data php artisan key:generate

# Edit .env file
sudo nano .env
```

### 3. Database Configuration

#### MySQL Setup
```bash
# Create database and user
sudo mysql -u root -p
```

```sql
CREATE DATABASE laravel_loop_filament;
CREATE USER 'filament_user'@'localhost' IDENTIFIED BY 'secure_password';
GRANT ALL PRIVILEGES ON laravel_loop_filament.* TO 'filament_user'@'localhost';
FLUSH PRIVILEGES;
EXIT;
```

#### Environment Database Configuration
```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=laravel_loop_filament
DB_USERNAME=filament_user
DB_PASSWORD=secure_password
```

#### Run Migrations
```bash
sudo -u www-data php artisan migrate --force
sudo -u www-data php artisan db:seed --force
```

## Web Server Configuration

### Nginx Configuration

Create `/etc/nginx/sites-available/laravel-loop-filament`:

```nginx
server {
    listen 80;
    server_name your-domain.com www.your-domain.com;
    root /var/www/laravel-loop-filament/public;
    index index.php index.html;

    # Security headers
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-XSS-Protection "1; mode=block" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header Referrer-Policy "no-referrer-when-downgrade" always;
    add_header Content-Security-Policy "default-src 'self' http: https: data: blob: 'unsafe-inline'" always;

    # Gzip compression
    gzip on;
    gzip_vary on;
    gzip_min_length 1024;
    gzip_types
        text/plain
        text/css
        text/xml
        text/javascript
        application/javascript
        application/xml+rss
        application/json;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }

    error_page 404 /index.php;

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
        fastcgi_hide_header X-Powered-By;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }

    # Cache static assets
    location ~* \.(jpg|jpeg|png|gif|ico|css|js|pdf|txt)$ {
        expires 1y;
        add_header Cache-Control "public, immutable";
    }

    # Rate limiting for API endpoints
    location /api/ {
        limit_req zone=api burst=20 nodelay;
        try_files $uri $uri/ /index.php?$query_string;
    }
}
```

Define rate limiting in `/etc/nginx/nginx.conf`:
```nginx
http {
    # Rate limiting
    limit_req_zone $binary_remote_addr zone=api:10m rate=10r/s;
    
    # Include other configs...
}
```

Enable the site:
```bash
sudo ln -s /etc/nginx/sites-available/laravel-loop-filament /etc/nginx/sites-enabled/
sudo nginx -t
sudo systemctl reload nginx
```

### Apache Configuration

Create `/etc/apache2/sites-available/laravel-loop-filament.conf`:

```apache
<VirtualHost *:80>
    ServerName your-domain.com
    ServerAlias www.your-domain.com
    DocumentRoot /var/www/laravel-loop-filament/public

    <Directory /var/www/laravel-loop-filament/public>
        AllowOverride All
        Require all granted
    </Directory>

    # Security headers
    Header always set X-Frame-Options "SAMEORIGIN"
    Header always set X-XSS-Protection "1; mode=block"
    Header always set X-Content-Type-Options "nosniff"
    Header always set Referrer-Policy "no-referrer-when-downgrade"

    # Compression
    LoadModule deflate_module modules/mod_deflate.so
    <Location />
        SetOutputFilter DEFLATE
        SetEnvIfNoCase Request_URI \
            \.(?:gif|jpe?g|png)$ no-gzip dont-vary
        SetEnvIfNoCase Request_URI \
            \.(?:exe|t?gz|zip|bz2|sit|rar)$ no-gzip dont-vary
    </Location>

    # Cache static assets
    <LocationMatch "\.(jpg|jpeg|png|gif|ico|css|js|pdf|txt)$">
        ExpiresActive On
        ExpiresDefault "access plus 1 year"
        Header append Cache-Control "public, immutable"
    </LocationMatch>

    ErrorLog ${APACHE_LOG_DIR}/laravel-loop-filament_error.log
    CustomLog ${APACHE_LOG_DIR}/laravel-loop-filament_access.log combined
</VirtualHost>
```

Enable the site:
```bash
sudo a2ensite laravel-loop-filament
sudo a2enmod rewrite headers expires deflate
sudo systemctl reload apache2
```

## SSL Configuration

### Using Let's Encrypt (Certbot)

```bash
# Install Certbot
sudo apt install certbot python3-certbot-nginx

# Generate certificate
sudo certbot --nginx -d your-domain.com -d www.your-domain.com

# Test auto-renewal
sudo certbot renew --dry-run
```

### Manual SSL Certificate

If using a purchased certificate, update your Nginx configuration:

```nginx
server {
    listen 443 ssl http2;
    listen [::]:443 ssl http2;
    server_name your-domain.com www.your-domain.com;

    ssl_certificate /path/to/your/certificate.crt;
    ssl_certificate_key /path/to/your/private.key;
    ssl_session_timeout 1d;
    ssl_session_cache shared:MozTLS:10m;
    ssl_session_tickets off;

    ssl_protocols TLSv1.2 TLSv1.3;
    ssl_ciphers ECDHE-ECDSA-AES128-GCM-SHA256:ECDHE-RSA-AES128-GCM-SHA256:ECDHE-ECDSA-AES256-GCM-SHA384:ECDHE-RSA-AES256-GCM-SHA384;
    ssl_prefer_server_ciphers off;

    # HSTS
    add_header Strict-Transport-Security "max-age=63072000" always;

    # Rest of configuration...
}

# Redirect HTTP to HTTPS
server {
    listen 80;
    listen [::]:80;
    server_name your-domain.com www.your-domain.com;
    return 301 https://$server_name$request_uri;
}
```

## Process Management

### Queue Workers with Supervisor

Create `/etc/supervisor/conf.d/laravel-loop-filament.conf`:

```ini
[program:laravel-loop-filament-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/laravel-loop-filament/artisan queue:work redis --sleep=3 --tries=3 --max-time=3600
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=4
redirect_stderr=true
stdout_logfile=/var/www/laravel-loop-filament/storage/logs/worker.log
stopwaitsecs=3600
```

Enable and start:
```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start laravel-loop-filament-worker:*
```

### Scheduler (Cron)

Add to crontab for www-data user:
```bash
sudo crontab -u www-data -e
```

Add this line:
```
* * * * * cd /var/www/laravel-loop-filament && php artisan schedule:run >> /dev/null 2>&1
```

## Production Environment Configuration

### Environment Variables (.env)

```env
APP_NAME="Laravel Loop Filament"
APP_ENV=production
APP_KEY=base64:...
APP_DEBUG=false
APP_URL=https://your-domain.com

LOG_CHANNEL=stack
LOG_DEPRECATIONS_CHANNEL=null
LOG_LEVEL=error

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=laravel_loop_filament
DB_USERNAME=filament_user
DB_PASSWORD=secure_password

BROADCAST_DRIVER=redis
CACHE_DRIVER=redis
FILESYSTEM_DISK=local
QUEUE_CONNECTION=redis
SESSION_DRIVER=redis
SESSION_LIFETIME=120

REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379

MAIL_MAILER=smtp
MAIL_HOST=your-smtp-server
MAIL_PORT=587
MAIL_USERNAME=your-email@domain.com
MAIL_PASSWORD=your-email-password
MAIL_ENCRYPTION=tls

# MCP Configuration
MCP_SERVER_ENABLED=true
MCP_DEFAULT_TIMEOUT=30
MCP_MAX_CONNECTIONS=50
```

### Performance Optimization

```bash
# Optimize application
sudo -u www-data php artisan config:cache
sudo -u www-data php artisan route:cache
sudo -u www-data php artisan view:cache
sudo -u www-data php artisan event:cache

# Build production assets
sudo -u www-data npm run build

# Optimize Composer autoloader
sudo -u www-data composer install --optimize-autoloader --no-dev

# Clear development caches
sudo -u www-data php artisan optimize:clear
```

### PHP Production Configuration

Edit `/etc/php/8.2/fpm/php.ini`:

```ini
# Performance
opcache.enable=1
opcache.memory_consumption=256
opcache.interned_strings_buffer=16
opcache.max_accelerated_files=20000
opcache.revalidate_freq=2
opcache.fast_shutdown=1

# Security
expose_php=Off
display_errors=Off
log_errors=On
error_log=/var/log/php_errors.log

# Limits
memory_limit=512M
upload_max_filesize=50M
post_max_size=50M
max_execution_time=300
max_input_vars=3000

# Session
session.cookie_secure=1
session.cookie_httponly=1
session.use_strict_mode=1
```

Restart PHP-FPM:
```bash
sudo systemctl restart php8.2-fpm
```

## Monitoring and Logging

### Log Configuration

Create log rotation for Laravel logs in `/etc/logrotate.d/laravel-loop-filament`:

```
/var/www/laravel-loop-filament/storage/logs/*.log {
    daily
    missingok
    rotate 30
    compress
    delaycompress
    notifempty
    create 664 www-data www-data
}
```

### System Monitoring

#### Monitor Disk Space
```bash
# Add to crontab
0 */6 * * * df -h | grep -E '9[0-9]%|100%' && echo "Disk space critical" | mail -s "Disk Alert" admin@your-domain.com
```

#### Monitor Application
Create health check script `/var/www/laravel-loop-filament/scripts/health-check.sh`:

```bash
#!/bin/bash
DOMAIN="https://your-domain.com"
RESPONSE=$(curl -s -o /dev/null -w "%{http_code}" $DOMAIN/admin)

if [ $RESPONSE -ne 200 ]; then
    echo "Application down: HTTP $RESPONSE" | mail -s "App Alert" admin@your-domain.com
fi
```

Add to crontab:
```bash
*/5 * * * * /var/www/laravel-loop-filament/scripts/health-check.sh
```

### Database Backup

Create backup script `/var/www/laravel-loop-filament/scripts/backup.sh`:

```bash
#!/bin/bash
DATE=$(date +%Y%m%d_%H%M%S)
BACKUP_DIR="/var/backups/laravel-loop-filament"
DB_NAME="laravel_loop_filament"
DB_USER="filament_user"
DB_PASS="secure_password"

mkdir -p $BACKUP_DIR

# Database backup
mysqldump -u$DB_USER -p$DB_PASS $DB_NAME > $BACKUP_DIR/db_$DATE.sql

# Application backup
tar -czf $BACKUP_DIR/app_$DATE.tar.gz \
    --exclude='node_modules' \
    --exclude='storage/logs/*' \
    --exclude='storage/framework/cache/*' \
    /var/www/laravel-loop-filament

# Keep only last 7 days
find $BACKUP_DIR -type f -mtime +7 -delete

echo "Backup completed: $DATE"
```

Add to crontab:
```bash
0 2 * * * /var/www/laravel-loop-filament/scripts/backup.sh
```

## Security Hardening

### Firewall Configuration (UFW)

```bash
# Enable firewall
sudo ufw enable

# Allow SSH (if using)
sudo ufw allow ssh

# Allow HTTP and HTTPS
sudo ufw allow 'Nginx Full'

# Allow specific IPs for admin access (optional)
sudo ufw allow from YOUR_ADMIN_IP to any port 22

# Check status
sudo ufw status verbose
```

### Application Security

#### Hide Server Information
In Nginx, add to server block:
```nginx
server_tokens off;
```

#### Secure File Permissions
```bash
# Set proper permissions
sudo chown -R www-data:www-data /var/www/laravel-loop-filament
sudo find /var/www/laravel-loop-filament -type f -exec chmod 644 {} \;
sudo find /var/www/laravel-loop-filament -type d -exec chmod 755 {} \;
sudo chmod -R 775 /var/www/laravel-loop-filament/storage
sudo chmod -R 775 /var/www/laravel-loop-filament/bootstrap/cache
```

#### Environment Security
```bash
# Protect .env file
sudo chmod 600 /var/www/laravel-loop-filament/.env
sudo chown www-data:www-data /var/www/laravel-loop-filament/.env
```

## Deployment Automation

### Deployment Script

Create `/var/www/laravel-loop-filament/scripts/deploy.sh`:

```bash
#!/bin/bash
set -e

APP_DIR="/var/www/laravel-loop-filament"
cd $APP_DIR

echo "Starting deployment..."

# Put application in maintenance mode
sudo -u www-data php artisan down

# Pull latest changes
sudo -u www-data git pull origin main

# Install/update dependencies
sudo -u www-data composer install --optimize-autoloader --no-dev
sudo -u www-data npm ci --only=production

# Run database migrations
sudo -u www-data php artisan migrate --force

# Clear and rebuild caches
sudo -u www-data php artisan optimize:clear
sudo -u www-data php artisan config:cache
sudo -u www-data php artisan route:cache
sudo -u www-data php artisan view:cache
sudo -u www-data php artisan event:cache

# Build assets
sudo -u www-data npm run build

# Restart services
sudo supervisorctl restart laravel-loop-filament-worker:*
sudo systemctl reload php8.2-fpm
sudo systemctl reload nginx

# Bring application back online
sudo -u www-data php artisan up

echo "Deployment completed successfully!"
```

Make it executable:
```bash
sudo chmod +x /var/www/laravel-loop-filament/scripts/deploy.sh
```

## Troubleshooting Deployment

### Common Issues

#### Permission Problems
```bash
# Fix storage permissions
sudo chown -R www-data:www-data storage bootstrap/cache
sudo chmod -R 775 storage bootstrap/cache
```

#### Queue Worker Issues
```bash
# Check worker status
sudo supervisorctl status laravel-loop-filament-worker:*

# Restart workers
sudo supervisorctl restart laravel-loop-filament-worker:*

# Check worker logs
sudo tail -f /var/www/laravel-loop-filament/storage/logs/worker.log
```

#### Database Connection Issues
```bash
# Test database connection
sudo -u www-data php artisan tinker
>>> DB::connection()->getPdo();
```

#### Performance Issues
```bash
# Check PHP-FPM status
sudo systemctl status php8.2-fpm

# Monitor server resources
htop
iotop
```

### Rollback Procedure

If deployment fails:

```bash
#!/bin/bash
# Rollback script
cd /var/www/laravel-loop-filament

# Put in maintenance mode
sudo -u www-data php artisan down

# Rollback to previous commit
sudo -u www-data git reset --hard HEAD~1

# Restore dependencies
sudo -u www-data composer install --optimize-autoloader --no-dev

# Rollback database if needed
sudo -u www-data php artisan migrate:rollback

# Clear caches
sudo -u www-data php artisan optimize:clear

# Bring back online
sudo -u www-data php artisan up

echo "Rollback completed"
```

For additional deployment troubleshooting, see the [Troubleshooting Guide](07_troubleshooting.md).