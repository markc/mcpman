#!/bin/bash

# LEMP + Laravel 12 + Filament v4 Setup Script
# For Debian 13 Trixie

set -euo pipefail

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Logging function
log() {
    echo -e "${GREEN}[$(date +'%Y-%m-%d %H:%M:%S')] $1${NC}"
}

warn() {
    echo -e "${YELLOW}[$(date +'%Y-%m-%d %H:%M:%S')] WARNING: $1${NC}"
}

error() {
    echo -e "${RED}[$(date +'%Y-%m-%d %H:%M:%S')] ERROR: $1${NC}"
    exit 1
}

# Configuration variables
PHP_VERSION="8.4"
LARAVEL_VERSION="12.*"
DOMAIN_NAME=${DOMAIN_NAME:-"laravel.local"}
DB_NAME=${DB_NAME:-"laravel_app"}
DB_USER=${DB_USER:-"laravel_user"}
DB_PASS=${DB_PASS:-$(openssl rand -base64 32)}
ROOT_DB_PASS=${ROOT_DB_PASS:-$(openssl rand -base64 32)}

log "Starting LEMP + Laravel 12 + Filament v4 installation..."

# Update system
log "Updating system packages..."
apt update && apt upgrade -y

# Install required packages
log "Installing required packages..."
apt install -y software-properties-common curl wget gnupg2 lsb-release ca-certificates

# PHP 8.4 is available natively in Debian 13 Trixie
log "PHP ${PHP_VERSION} is available natively in Debian 13 Trixie..."

# Install Nginx
log "Installing Nginx..."
apt install -y nginx

# Install PHP 8.4 and extensions
log "Installing PHP ${PHP_VERSION} and extensions..."
apt install -y \
    php${PHP_VERSION}-fpm \
    php${PHP_VERSION}-cli \
    php${PHP_VERSION}-common \
    php${PHP_VERSION}-mysql \
    php${PHP_VERSION}-zip \
    php${PHP_VERSION}-gd \
    php${PHP_VERSION}-mbstring \
    php${PHP_VERSION}-curl \
    php${PHP_VERSION}-xml \
    php${PHP_VERSION}-bcmath \
    php${PHP_VERSION}-json \
    php${PHP_VERSION}-intl \
    php${PHP_VERSION}-readline \
    php${PHP_VERSION}-imagick \
    php${PHP_VERSION}-redis \
    php${PHP_VERSION}-sqlite3 \
    php${PHP_VERSION}-tokenizer \
    php${PHP_VERSION}-fileinfo \
    php${PHP_VERSION}-ctype \
    php${PHP_VERSION}-dom \
    php${PHP_VERSION}-simplexml

# Install MariaDB
log "Installing MariaDB..."
apt install -y mariadb-server mariadb-client

# Install Redis
log "Installing Redis..."
apt install -y redis-server

# Install Node.js and npm (for Laravel frontend compilation only)
log "Installing Node.js and npm (frontend compilation only)..."
curl -fsSL https://deb.nodesource.com/setup_20.x | bash -
apt install -y nodejs

# Install Composer
log "Installing Composer..."
curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# Configure PHP
log "Configuring PHP..."
PHP_INI="/etc/php/${PHP_VERSION}/fpm/php.ini"
cp "$PHP_INI" "$PHP_INI.backup"

# PHP optimizations for Laravel
sed -i 's/;cgi.fix_pathinfo=1/cgi.fix_pathinfo=0/' "$PHP_INI"
sed -i 's/upload_max_filesize = 2M/upload_max_filesize = 100M/' "$PHP_INI"
sed -i 's/post_max_size = 8M/post_max_size = 100M/' "$PHP_INI"
sed -i 's/max_execution_time = 30/max_execution_time = 300/' "$PHP_INI"
sed -i 's/max_input_vars = 1000/max_input_vars = 3000/' "$PHP_INI"
sed -i 's/memory_limit = 128M/memory_limit = 512M/' "$PHP_INI"

# Configure PHP-FPM
log "Configuring PHP-FPM..."
FPM_CONF="/etc/php/${PHP_VERSION}/fpm/pool.d/www.conf"
cp "$FPM_CONF" "$FPM_CONF.backup"

sed -i 's/user = www-data/user = nginx/' "$FPM_CONF"
sed -i 's/group = www-data/group = nginx/' "$FPM_CONF"
sed -i 's/listen.owner = www-data/listen.owner = nginx/' "$FPM_CONF"
sed -i 's/listen.group = www-data/listen.group = nginx/' "$FPM_CONF"

# Secure MariaDB installation
log "Securing MariaDB installation..."
mysql -e "UPDATE mysql.user SET Password = PASSWORD('${ROOT_DB_PASS}') WHERE User = 'root'"
mysql -e "DROP DATABASE IF EXISTS test"
mysql -e "DELETE FROM mysql.user WHERE User=''"
mysql -e "DELETE FROM mysql.user WHERE User='root' AND Host NOT IN ('localhost', '127.0.0.1', '::1')"
mysql -e "FLUSH PRIVILEGES"

# Create database and user for Laravel
log "Creating database and user for Laravel..."
mysql -u root -p"${ROOT_DB_PASS}" -e "CREATE DATABASE ${DB_NAME}"
mysql -u root -p"${ROOT_DB_PASS}" -e "CREATE USER '${DB_USER}'@'localhost' IDENTIFIED BY '${DB_PASS}'"
mysql -u root -p"${ROOT_DB_PASS}" -e "GRANT ALL PRIVILEGES ON ${DB_NAME}.* TO '${DB_USER}'@'localhost'"
mysql -u root -p"${ROOT_DB_PASS}" -e "FLUSH PRIVILEGES"

# Configure Nginx
log "Configuring Nginx for Laravel..."
cat > /etc/nginx/sites-available/laravel << EOF
server {
    listen 80;
    listen [::]:80;
    server_name ${DOMAIN_NAME} www.${DOMAIN_NAME};
    root /var/www/laravel/public;

    add_header X-Frame-Options "SAMEORIGIN";
    add_header X-Content-Type-Options "nosniff";

    index index.php;

    charset utf-8;

    location / {
        try_files \$uri \$uri/ /index.php?\$query_string;
    }

    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }

    error_page 404 /index.php;

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php${PHP_VERSION}-fpm.sock;
        fastcgi_param SCRIPT_FILENAME \$realpath_root\$fastcgi_script_name;
        include fastcgi_params;
        fastcgi_hide_header X-Powered-By;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }

    # Security headers
    add_header X-XSS-Protection "1; mode=block";
    add_header Referrer-Policy "strict-origin-when-cross-origin";
    add_header Permissions-Policy "geolocation=(), microphone=(), camera=()";
}
EOF

# Enable the site
ln -sf /etc/nginx/sites-available/laravel /etc/nginx/sites-enabled/
rm -f /etc/nginx/sites-enabled/default

# Test Nginx configuration
nginx -t || error "Nginx configuration test failed"

# Create Laravel application
log "Creating Laravel 12 application..."
cd /var/www
rm -rf laravel

# Install Laravel 12
composer create-project laravel/laravel laravel "${LARAVEL_VERSION}" --prefer-dist

cd /var/www/laravel

# Set proper permissions
chown -R nginx:nginx /var/www/laravel
chmod -R 755 /var/www/laravel
chmod -R 775 /var/www/laravel/storage
chmod -R 775 /var/www/laravel/bootstrap/cache

# Configure Laravel environment
log "Configuring Laravel environment..."
cp .env.example .env

# Generate application key
php artisan key:generate

# Update .env file
sed -i "s/DB_DATABASE=laravel/DB_DATABASE=${DB_NAME}/" .env
sed -i "s/DB_USERNAME=root/DB_USERNAME=${DB_USER}/" .env
sed -i "s/DB_PASSWORD=/DB_PASSWORD=${DB_PASS}/" .env
sed -i "s/APP_URL=http:\/\/localhost/APP_URL=http:\/\/${DOMAIN_NAME}/" .env

# Install Filament v4
log "Installing Filament v4..."
composer require filament/filament

# Install Filament admin panel
php artisan filament:install --panels

# Install additional Filament plugins
log "Installing Filament plugins..."
composer require filament/spatie-laravel-media-library-plugin
composer require filament/spatie-laravel-settings-plugin
composer require filament/spatie-laravel-tags-plugin

# Run Laravel migrations
log "Running Laravel migrations..."
php artisan migrate --force

# Create Filament admin user
log "Creating Filament admin user..."
php artisan make:filament-user --name="Admin User" --email="admin@${DOMAIN_NAME}" --password="admin123"

# Install and compile frontend assets
log "Installing and compiling frontend assets..."
npm install
npm run build

# Configure Redis
log "Configuring Redis..."
sed -i 's/# maxmemory <bytes>/maxmemory 256mb/' /etc/redis/redis.conf
sed -i 's/# maxmemory-policy noeviction/maxmemory-policy allkeys-lru/' /etc/redis/redis.conf

# Configure queue worker (using database driver)
log "Setting up Laravel queue worker..."
cat > /etc/systemd/system/laravel-worker.service << EOF
[Unit]
Description=Laravel Queue Worker
After=network.target

[Service]
Type=simple
User=nginx
Group=nginx
Restart=always
ExecStart=/usr/bin/php /var/www/laravel/artisan queue:work --sleep=3 --tries=3 --max-time=3600
StandardOutput=journal
StandardError=journal

[Install]
WantedBy=multi-user.target
EOF

systemctl enable laravel-worker
systemctl start laravel-worker

# Configure task scheduler
log "Setting up Laravel task scheduler..."
(crontab -u nginx -l 2>/dev/null; echo "* * * * * cd /var/www/laravel && php artisan schedule:run >> /dev/null 2>&1") | crontab -u nginx -

# Start and enable services
log "Starting and enabling services..."
systemctl enable nginx php${PHP_VERSION}-fpm mariadb redis-server
systemctl start nginx php${PHP_VERSION}-fpm mariadb redis-server

# Configure firewall (if ufw is installed)
if command -v ufw &> /dev/null; then
    log "Configuring firewall..."
    ufw allow 22/tcp
    ufw allow 80/tcp
    ufw allow 443/tcp
    ufw --force enable
fi

# Create info file
log "Creating system information file..."
cat > /var/www/laravel/SYSTEM_INFO.md << EOF
# Laravel 12 + Filament v4 Development Environment

## System Information
- OS: Debian 13 Trixie
- Web Server: Nginx
- PHP: ${PHP_VERSION}
- Database: MariaDB
- Cache: Redis
- Framework: Laravel 12
- Admin Panel: Filament v4

## Database Credentials
- Database: ${DB_NAME}
- Username: ${DB_USER}
- Password: ${DB_PASS}
- Root Password: ${ROOT_DB_PASS}

## URLs
- Application: http://${DOMAIN_NAME}
- Admin Panel: http://${DOMAIN_NAME}/admin

## Default Admin Credentials
- Email: admin@${DOMAIN_NAME}
- Password: admin123

## Important Directories
- Application Root: /var/www/laravel
- Nginx Config: /etc/nginx/sites-available/laravel
- PHP Config: /etc/php/${PHP_VERSION}/fpm/php.ini
- Laravel Logs: /var/www/laravel/storage/logs/

## Useful Commands
- Restart Services: systemctl restart nginx php${PHP_VERSION}-fpm mariadb redis-server
- View Logs: tail -f /var/www/laravel/storage/logs/laravel.log
- Queue Status: systemctl status laravel-worker
- Clear Cache: cd /var/www/laravel && php artisan cache:clear

## Security Notes
- Change default admin password immediately
- Update database credentials if needed
- Configure SSL/TLS certificates for production
- Review and update firewall rules

Generated on: $(date)
EOF

chown nginx:nginx /var/www/laravel/SYSTEM_INFO.md

# Final status check
log "Performing final status check..."
systemctl is-active --quiet nginx && log "✓ Nginx is running" || error "✗ Nginx failed to start"
systemctl is-active --quiet php${PHP_VERSION}-fpm && log "✓ PHP-FPM is running" || error "✗ PHP-FPM failed to start"
systemctl is-active --quiet mariadb && log "✓ MariaDB is running" || error "✗ MariaDB failed to start"
systemctl is-active --quiet redis-server && log "✓ Redis is running" || error "✗ Redis failed to start"
systemctl is-active --quiet laravel-worker && log "✓ Laravel queue worker is running" || warn "✗ Laravel queue worker failed to start"

# Test Laravel installation
log "Testing Laravel installation..."
cd /var/www/laravel
php artisan --version || error "Laravel installation test failed"

log "🎉 LEMP + Laravel 12 + Filament v4 installation completed successfully!"
log ""
log "📋 Next Steps:"
log "1. Update your DNS or hosts file to point ${DOMAIN_NAME} to this server"
log "2. Visit http://${DOMAIN_NAME} to see your Laravel application"
log "3. Visit http://${DOMAIN_NAME}/admin to access the Filament admin panel"
log "4. Change the default admin password (admin@${DOMAIN_NAME} / admin123)"
log "5. Review the system information at /var/www/laravel/SYSTEM_INFO.md"
log ""
log "📊 Database Information:"
log "   Database: ${DB_NAME}"
log "   Username: ${DB_USER}"
log "   Password: ${DB_PASS}"
log ""
log "🔐 IMPORTANT: Save these credentials securely!"

# Save credentials to a secure file
cat > /root/.laravel-credentials << EOF
# Laravel Environment Credentials
# Generated on: $(date)

DB_NAME=${DB_NAME}
DB_USER=${DB_USER}
DB_PASS=${DB_PASS}
ROOT_DB_PASS=${ROOT_DB_PASS}
DOMAIN=${DOMAIN_NAME}

# Admin Panel
ADMIN_EMAIL=admin@${DOMAIN_NAME}
ADMIN_PASS=admin123
EOF

chmod 600 /root/.laravel-credentials

log "✅ Setup completed! Credentials saved to /root/.laravel-credentials"