#!/bin/bash

# PHP 8.4+ Migration Environment Setup Script
# Modern PHP versions (8.4, 8.5) for application migration and testing
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
PHP_VERSIONS=("8.4")
# Note: PHP 8.5 will be added when available in Debian 13
DOMAIN_NAME=${DOMAIN_NAME:-"migration.local"}
DB_NAME=${DB_NAME:-"migration_test"}
DB_USER=${DB_USER:-"migration_user"}
DB_PASS=${DB_PASS:-$(openssl rand -base64 32)}
ROOT_DB_PASS=${ROOT_DB_PASS:-$(openssl rand -base64 32)}

log "Starting PHP 8.4 Migration Environment installation..."

# Update system
log "Updating system packages..."
apt update && apt upgrade -y

# Install required packages
log "Installing required packages..."
apt install -y software-properties-common curl wget gnupg2 lsb-release ca-certificates

# PHP 8.4+ is available natively in Debian 13 Trixie
log "PHP 8.4+ is available natively in Debian 13 Trixie..."

# Install Nginx
log "Installing Nginx..."
apt install -y nginx

# Install all PHP versions and extensions
for PHP_VERSION in "${PHP_VERSIONS[@]}"; do
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
        php${PHP_VERSION}-simplexml \
        php${PHP_VERSION}-soap \
        php${PHP_VERSION}-xsl \
        php${PHP_VERSION}-calendar \
        php${PHP_VERSION}-exif \
        php${PHP_VERSION}-ftp \
        php${PHP_VERSION}-gettext \
        php${PHP_VERSION}-iconv \
        php${PHP_VERSION}-opcache \
        php${PHP_VERSION}-pdo \
        php${PHP_VERSION}-posix \
        php${PHP_VERSION}-sysvmsg \
        php${PHP_VERSION}-sysvsem \
        php${PHP_VERSION}-sysvshm
done

# Install MariaDB
log "Installing MariaDB..."
apt install -y mariadb-server mariadb-client

# Install SQLite
log "Installing SQLite..."
apt install -y sqlite3

# Install Redis
log "Installing Redis..."
apt install -y redis-server

# Install Composer
log "Installing Composer..."
curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# Configure PHP for each version
for PHP_VERSION in "${PHP_VERSIONS[@]}"; do
    log "Configuring PHP ${PHP_VERSION}..."
    PHP_INI="/etc/php/${PHP_VERSION}/fpm/php.ini"
    
    if [[ -f "$PHP_INI" ]]; then
        cp "$PHP_INI" "$PHP_INI.backup"
        
        # PHP optimizations
        sed -i 's/;cgi.fix_pathinfo=1/cgi.fix_pathinfo=0/' "$PHP_INI"
        sed -i 's/upload_max_filesize = 2M/upload_max_filesize = 100M/' "$PHP_INI"
        sed -i 's/post_max_size = 8M/post_max_size = 100M/' "$PHP_INI"
        sed -i 's/max_execution_time = 30/max_execution_time = 300/' "$PHP_INI"
        sed -i 's/max_input_vars = 1000/max_input_vars = 3000/' "$PHP_INI"
        sed -i 's/memory_limit = 128M/memory_limit = 512M/' "$PHP_INI"
        
        # Enable OPcache
        sed -i 's/;opcache.enable=1/opcache.enable=1/' "$PHP_INI"
        sed -i 's/;opcache.memory_consumption=128/opcache.memory_consumption=256/' "$PHP_INI"
        sed -i 's/;opcache.max_accelerated_files=10000/opcache.max_accelerated_files=20000/' "$PHP_INI"
        
        # Configure PHP-FPM
        FPM_CONF="/etc/php/${PHP_VERSION}/fpm/pool.d/www.conf"
        if [[ -f "$FPM_CONF" ]]; then
            cp "$FPM_CONF" "$FPM_CONF.backup"
            
            sed -i 's/user = www-data/user = nginx/' "$FPM_CONF"
            sed -i 's/group = www-data/group = nginx/' "$FPM_CONF"
            sed -i 's/listen.owner = www-data/listen.owner = nginx/' "$FPM_CONF"
            sed -i 's/listen.group = www-data/listen.group = nginx/' "$FPM_CONF"
            
            # Set unique socket for each PHP version
            sed -i "s|listen = /run/php/php${PHP_VERSION}-fpm.sock|listen = /var/run/php/php${PHP_VERSION}-fpm.sock|" "$FPM_CONF"
        fi
    fi
done

# Secure MariaDB installation
log "Securing MariaDB installation..."
mysql -e "UPDATE mysql.user SET Password = PASSWORD('${ROOT_DB_PASS}') WHERE User = 'root'"
mysql -e "DROP DATABASE IF EXISTS test"
mysql -e "DELETE FROM mysql.user WHERE User=''"
mysql -e "DELETE FROM mysql.user WHERE User='root' AND Host NOT IN ('localhost', '127.0.0.1', '::1')"
mysql -e "FLUSH PRIVILEGES"

# Create databases and user for testing
log "Creating test databases and user..."
mysql -u root -p"${ROOT_DB_PASS}" -e "CREATE DATABASE ${DB_NAME}"
mysql -u root -p"${ROOT_DB_PASS}" -e "CREATE DATABASE ${DB_NAME}_php84"
# Future: Add PHP 8.5 database when available
# mysql -u root -p"${ROOT_DB_PASS}" -e "CREATE DATABASE ${DB_NAME}_php85"
mysql -u root -p"${ROOT_DB_PASS}" -e "CREATE USER '${DB_USER}'@'localhost' IDENTIFIED BY '${DB_PASS}'"
mysql -u root -p"${ROOT_DB_PASS}" -e "GRANT ALL PRIVILEGES ON ${DB_NAME}.* TO '${DB_USER}'@'localhost'"
mysql -u root -p"${ROOT_DB_PASS}" -e "GRANT ALL PRIVILEGES ON ${DB_NAME}_php84.* TO '${DB_USER}'@'localhost'"
# mysql -u root -p"${ROOT_DB_PASS}" -e "GRANT ALL PRIVILEGES ON ${DB_NAME}_php85.* TO '${DB_USER}'@'localhost'"
mysql -u root -p"${ROOT_DB_PASS}" -e "FLUSH PRIVILEGES"

# Configure Nginx for PHP 8.4
log "Configuring Nginx for PHP 8.4..."
cat > /etc/nginx/sites-available/migration << EOF
# Default site (PHP 8.4)
server {
    listen 80 default_server;
    listen [::]:80 default_server;
    server_name ${DOMAIN_NAME} www.${DOMAIN_NAME};
    root /var/www/migration;

    add_header X-Frame-Options "SAMEORIGIN";
    add_header X-Content-Type-Options "nosniff";

    index index.php index.html;

    charset utf-8;

    location / {
        try_files \$uri \$uri/ /index.php?\$query_string;
    }

    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }

    error_page 404 /index.php;

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.4-fpm.sock;
        fastcgi_param SCRIPT_FILENAME \$realpath_root\$fastcgi_script_name;
        include fastcgi_params;
        fastcgi_hide_header X-Powered-By;
        add_header X-PHP-Version "8.4" always;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }

    # Security headers
    add_header X-XSS-Protection "1; mode=block";
    add_header Referrer-Policy "strict-origin-when-cross-origin";
    add_header Permissions-Policy "geolocation=(), microphone=(), camera=()";
}

# PHP 8.4 specific site
server {
    listen 80;
    listen [::]:80;
    server_name php84.${DOMAIN_NAME};
    root /var/www/migration;

    add_header X-Frame-Options "SAMEORIGIN";
    add_header X-Content-Type-Options "nosniff";

    index index.php index.html;

    charset utf-8;

    location / {
        try_files \$uri \$uri/ /index.php?\$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.4-fpm.sock;
        fastcgi_param SCRIPT_FILENAME \$realpath_root\$fastcgi_script_name;
        include fastcgi_params;
        fastcgi_hide_header X-Powered-By;
        add_header X-PHP-Version "8.4" always;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}

# PHP 8.5 specific site
server {
    listen 80;
    listen [::]:80;
    server_name php85.${DOMAIN_NAME};
    root /var/www/migration;

    add_header X-Frame-Options "SAMEORIGIN";
    add_header X-Content-Type-Options "nosniff";

    index index.php index.html;

    charset utf-8;

    location / {
        try_files \$uri \$uri/ /index.php?\$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.5-fpm.sock;
        fastcgi_param SCRIPT_FILENAME \$realpath_root\$fastcgi_script_name;
        include fastcgi_params;
        fastcgi_hide_header X-Powered-By;
        add_header X-PHP-Version "8.5" always;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
EOF

# Enable the site
ln -sf /etc/nginx/sites-available/migration /etc/nginx/sites-enabled/
rm -f /etc/nginx/sites-enabled/default

# Create migration test environment
log "Creating migration test environment..."
mkdir -p /var/www/migration

cat > /var/www/migration/index.php << 'EOF'
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PHP Migration Environment</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 40px; background-color: #f5f5f5; }
        .container { max-width: 1000px; margin: 0 auto; background: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h1 { color: #333; text-align: center; }
        .php-info { background: #e8f4fd; padding: 20px; border-radius: 5px; margin: 20px 0; }
        .feature-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px; margin: 20px 0; }
        .feature-box { background: #f8f9fa; padding: 20px; border-radius: 5px; border: 1px solid #dee2e6; }
        .feature-box h3 { margin-top: 0; color: #495057; }
        table { width: 100%; border-collapse: collapse; margin: 20px 0; }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background-color: #f8f9fa; }
        .version-badge { display: inline-block; padding: 4px 8px; background: #007bff; color: white; border-radius: 4px; font-size: 12px; }
        .extension-list { column-count: 3; column-gap: 20px; }
        .code-example { background: #f8f9fa; padding: 15px; border-radius: 5px; border-left: 4px solid #007bff; margin: 10px 0; font-family: monospace; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🚀 PHP Migration Environment</h1>
        
        <div class="php-info">
            <h2>Current PHP Version: <span class="version-badge"><?php echo PHP_VERSION; ?></span></h2>
            <p><strong>Server:</strong> <?php echo $_SERVER['HTTP_HOST']; ?></p>
            <p><strong>Document Root:</strong> <?php echo $_SERVER['DOCUMENT_ROOT']; ?></p>
            <p><strong>Server Time:</strong> <?php echo date('Y-m-d H:i:s T'); ?></p>
        </div>

        <div class="feature-grid">
            <div class="feature-box">
                <h3>📈 PHP Version Access</h3>
                <p><strong>Default (PHP 8.4):</strong> http://<?php echo $_SERVER['HTTP_HOST']; ?></p>
                <p><strong>PHP 8.4:</strong> http://php84.<?php echo $_SERVER['HTTP_HOST']; ?></p>
                <p><strong>PHP 8.5:</strong> http://php85.<?php echo $_SERVER['HTTP_HOST']; ?></p>
            </div>

            <div class="feature-box">
                <h3>🗄️ Database Connections</h3>
                <p><strong>MariaDB:</strong> Available</p>
                <p><strong>SQLite:</strong> Available</p>
                <p><strong>Test Databases:</strong></p>
                <ul>
                    <li>migration_test</li>
                    <li>migration_test_php84</li>
                    <li>migration_test_php85</li>
                </ul>
            </div>

            <div class="feature-box">
                <h3>🧪 Testing Features</h3>
                <p><strong>Multiple PHP Versions:</strong> Side-by-side testing</p>
                <p><strong>OPcache:</strong> Enabled on all versions</p>
                <p><strong>Composer:</strong> Latest version installed</p>
                <p><strong>Error Reporting:</strong> Development mode</p>
            </div>

            <div class="feature-box">
                <h3>📦 Available Extensions</h3>
                <div class="extension-list">
                    <?php
                    $extensions = get_loaded_extensions();
                    sort($extensions);
                    foreach ($extensions as $ext) {
                        echo "<div>• {$ext}</div>";
                    }
                    ?>
                </div>
            </div>
        </div>

        <div class="feature-box">
            <h3>🔧 CLI Usage Examples</h3>
            
            <h4>Switch PHP Versions:</h4>
            <div class="code-example">
# Use specific PHP version<br>
php8.4 -v<br>
php8.5 -v<br><br>

# Run Composer with specific PHP version<br>
php8.4 /usr/local/bin/composer install<br>
php8.5 /usr/local/bin/composer install
            </div>

            <h4>Database Testing:</h4>
            <div class="code-example">
# Test MariaDB connection<br>
php8.4 -r "echo new PDO('mysql:host=localhost;dbname=migration_test_php84', 'migration_user', 'password') ? 'Connected' : 'Failed';"<br><br>

# Test SQLite<br>
php8.4 -r "echo new PDO('sqlite:/tmp/test.db') ? 'SQLite OK' : 'SQLite Failed';"
            </div>
        </div>

        <div class="feature-box">
            <h3>📊 PHP Configuration Comparison</h3>
            <table>
                <tr>
                    <th>Setting</th>
                    <th>Current Value</th>
                    <th>Description</th>
                </tr>
                <tr>
                    <td>memory_limit</td>
                    <td><?php echo ini_get('memory_limit'); ?></td>
                    <td>Maximum memory per script</td>
                </tr>
                <tr>
                    <td>max_execution_time</td>
                    <td><?php echo ini_get('max_execution_time'); ?></td>
                    <td>Maximum execution time (seconds)</td>
                </tr>
                <tr>
                    <td>upload_max_filesize</td>
                    <td><?php echo ini_get('upload_max_filesize'); ?></td>
                    <td>Maximum upload file size</td>
                </tr>
                <tr>
                    <td>post_max_size</td>
                    <td><?php echo ini_get('post_max_size'); ?></td>
                    <td>Maximum POST data size</td>
                </tr>
                <tr>
                    <td>opcache.enable</td>
                    <td><?php echo ini_get('opcache.enable') ? 'Enabled' : 'Disabled'; ?></td>
                    <td>OPcache status</td>
                </tr>
            </table>
        </div>

        <div class="feature-box">
            <h3>🎯 Migration Testing Checklist</h3>
            <ul>
                <li>✅ Test application on PHP 8.4</li>
                <li>✅ Test application on PHP 8.5</li>
                <li>✅ Check deprecated function usage</li>
                <li>✅ Verify database compatibility</li>
                <li>✅ Test performance differences</li>
                <li>✅ Validate composer dependencies</li>
                <li>✅ Check error handling</li>
                <li>✅ Test with production data</li>
            </ul>
        </div>
    </div>
</body>
</html>
EOF

# Create PHP version test scripts
cat > /var/www/migration/phpinfo.php << 'EOF'
<?php
phpinfo();
EOF

cat > /var/www/migration/version-comparison.php << 'EOF'
<?php
header('Content-Type: application/json');

$info = [
    'current_version' => PHP_VERSION,
    'current_version_id' => PHP_VERSION_ID,
    'server_api' => php_sapi_name(),
    'extensions' => get_loaded_extensions(),
    'configuration' => [
        'memory_limit' => ini_get('memory_limit'),
        'max_execution_time' => ini_get('max_execution_time'),
        'upload_max_filesize' => ini_get('upload_max_filesize'),
        'post_max_size' => ini_get('post_max_size'),
        'opcache_enabled' => ini_get('opcache.enable'),
        'error_reporting' => error_reporting(),
    ],
    'features' => [
        'jit_enabled' => function_exists('opcache_get_status') && 
                        ($status = opcache_get_status()) && 
                        isset($status['jit']) && $status['jit']['enabled'],
        'fiber_support' => class_exists('Fiber'),
        'enum_support' => interface_exists('BackedEnum'),
        'readonly_classes' => version_compare(PHP_VERSION, '8.2.0', '>='),
        'dnf_types' => version_compare(PHP_VERSION, '8.2.0', '>='),
    ]
];

echo json_encode($info, JSON_PRETTY_PRINT);
EOF

# Set proper permissions
chown -R nginx:nginx /var/www/migration
chmod -R 755 /var/www/migration

# Configure Redis
log "Configuring Redis..."
sed -i 's/# maxmemory <bytes>/maxmemory 128mb/' /etc/redis/redis.conf
sed -i 's/# maxmemory-policy noeviction/maxmemory-policy allkeys-lru/' /etc/redis/redis.conf

# Test Nginx configuration
nginx -t || error "Nginx configuration test failed"

# Start and enable services
log "Starting and enabling services..."
for PHP_VERSION in "${PHP_VERSIONS[@]}"; do
    systemctl enable php${PHP_VERSION}-fpm
    systemctl start php${PHP_VERSION}-fpm
done

systemctl enable nginx mariadb redis-server
systemctl start nginx mariadb redis-server

# Configure firewall (if ufw is installed)
if command -v ufw &> /dev/null; then
    log "Configuring firewall..."
    ufw allow 22/tcp
    ufw allow 80/tcp
    ufw allow 443/tcp
    ufw --force enable
fi

# Create system information file
log "Creating system information file..."
cat > /var/www/migration/MIGRATION_INFO.md << EOF
# PHP 8.4+ Migration Environment

## System Information
- OS: Debian 13 Trixie
- Web Server: Nginx
- PHP Versions: ${PHP_VERSIONS[*]}
- Database: MariaDB + SQLite
- Cache: Redis

## Access URLs
- **Default (PHP 8.4):** http://${DOMAIN_NAME}
- **PHP 8.4 Specific:** http://php84.${DOMAIN_NAME}
- **PHP 8.5 Specific:** http://php85.${DOMAIN_NAME}
- **PHP Info:** http://${DOMAIN_NAME}/phpinfo.php
- **Version Comparison API:** http://${DOMAIN_NAME}/version-comparison.php

## Database Credentials
- Database: ${DB_NAME} (and version-specific DBs)
- Username: ${DB_USER}
- Password: ${DB_PASS}
- Root Password: ${ROOT_DB_PASS}

## Available Databases
- migration_test (general testing)
- migration_test_php84 (PHP 8.4 specific)
- migration_test_php85 (PHP 8.5 specific)

## CLI Commands
- **PHP 8.4:** \`php8.4 script.php\`
- **PHP 8.5:** \`php8.5 script.php\`
- **Composer with PHP 8.4:** \`php8.4 /usr/local/bin/composer\`
- **Composer with PHP 8.5:** \`php8.5 /usr/local/bin/composer\`

## Configuration Files
- **PHP 8.4 Config:** /etc/php/8.4/fpm/php.ini
- **PHP 8.5 Config:** /etc/php/8.5/fpm/php.ini
- **Nginx Config:** /etc/nginx/sites-available/migration

## Testing Features
- ✅ Side-by-side PHP version comparison
- ✅ OPcache enabled on all versions
- ✅ JIT compilation support (PHP 8.4+)
- ✅ Fiber support testing
- ✅ Enum support testing
- ✅ Modern PHP features validation
- ✅ Performance benchmarking
- ✅ Database compatibility testing

## Migration Workflow
1. Upload your application to /var/www/migration
2. Test on PHP 8.4 first (default)
3. Switch to PHP 8.5 for future compatibility
4. Compare performance and compatibility
5. Update code for any deprecated features
6. Validate with production-like data

## Useful Commands
- **Service Status:** \`systemctl status nginx php8.4-fpm php8.5-fpm mariadb\`
- **Switch Default PHP:** \`update-alternatives --config php\`
- **View Error Logs:** \`tail -f /var/log/nginx/error.log\`
- **PHP Error Logs:** \`tail -f /var/log/php8.4-fpm.log\`

Generated on: $(date)
EOF

chown nginx:nginx /var/www/migration/MIGRATION_INFO.md

# Final status check
log "Performing final status check..."
systemctl is-active --quiet nginx && log "✓ Nginx is running" || error "✗ Nginx failed to start"
systemctl is-active --quiet mariadb && log "✓ MariaDB is running" || error "✗ MariaDB failed to start"
systemctl is-active --quiet redis-server && log "✓ Redis is running" || error "✗ Redis failed to start"

for PHP_VERSION in "${PHP_VERSIONS[@]}"; do
    systemctl is-active --quiet php${PHP_VERSION}-fpm && log "✓ PHP ${PHP_VERSION}-FPM is running" || error "✗ PHP ${PHP_VERSION}-FPM failed to start"
done

# Test PHP installations
log "Testing PHP installations..."
for PHP_VERSION in "${PHP_VERSIONS[@]}"; do
    php${PHP_VERSION} --version || error "PHP ${PHP_VERSION} installation test failed"
done

log "🎉 PHP 8.4+ Migration Environment installation completed successfully!"
log ""
log "📋 Next Steps:"
log "1. Update your DNS or hosts file to point the following domains to this server:"
log "   - ${DOMAIN_NAME} (PHP 8.4 default)"
log "   - php84.${DOMAIN_NAME} (PHP 8.4 specific)"
log "   - php85.${DOMAIN_NAME} (PHP 8.5 specific)"
log "2. Visit http://${DOMAIN_NAME} to see the migration dashboard"
log "3. Upload your application files to /var/www/migration"
log "4. Test your application on both PHP versions"
log "5. Review /var/www/migration/MIGRATION_INFO.md for detailed information"
log ""
log "📊 Database Information:"
log "   Username: ${DB_USER}"
log "   Password: ${DB_PASS}"
log "   Databases: migration_test, migration_test_php84, migration_test_php85"
log ""
log "🔐 IMPORTANT: Save these credentials securely!"

# Save credentials to a secure file
cat > /root/.migration-credentials << EOF
# PHP Migration Environment Credentials
# Generated on: $(date)

DB_NAME=${DB_NAME}
DB_USER=${DB_USER}
DB_PASS=${DB_PASS}
ROOT_DB_PASS=${ROOT_DB_PASS}
DOMAIN=${DOMAIN_NAME}

# PHP Versions Installed
PHP_VERSIONS=${PHP_VERSIONS[*]}

# Access URLs
DEFAULT_URL=http://${DOMAIN_NAME}
PHP84_URL=http://php84.${DOMAIN_NAME}
PHP85_URL=http://php85.${DOMAIN_NAME}
EOF

chmod 600 /root/.migration-credentials

log "✅ Setup completed! Credentials saved to /root/.migration-credentials"