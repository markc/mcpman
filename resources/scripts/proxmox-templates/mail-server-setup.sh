#!/bin/bash

# Complete Mail Server + Web Stack Setup Script
# Nginx + PHP 8.4-FPM + MariaDB + Postfix + Dovecot + Sieve + SpamProbe
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
DOMAIN_NAME=${DOMAIN_NAME:-"mail.local"}
HOSTNAME=${HOSTNAME:-"mail"}
DB_NAME=${DB_NAME:-"mail_server"}
DB_USER=${DB_USER:-"mail_user"}
DB_PASS=${DB_PASS:-$(openssl rand -base64 32)}
ROOT_DB_PASS=${ROOT_DB_PASS:-$(openssl rand -base64 32)}
POSTMASTER_PASS=${POSTMASTER_PASS:-$(openssl rand -base64 16)}

log "Starting Complete Mail Server + Web Stack installation..."

# Update system
log "Updating system packages..."
apt update && apt upgrade -y

# Set hostname
log "Setting hostname to ${HOSTNAME}..."
hostnamectl set-hostname "${HOSTNAME}"
echo "127.0.1.1 ${HOSTNAME}.${DOMAIN_NAME} ${HOSTNAME}" >> /etc/hosts

# Install required packages
log "Installing required packages..."
apt install -y software-properties-common curl wget gnupg2 lsb-release ca-certificates

# PHP 8.4 and Dovecot 2.4.1 are available natively in Debian 13 Trixie
log "PHP ${PHP_VERSION} and Dovecot 2.4.1 are available natively in Debian 13 Trixie..."

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
    php${PHP_VERSION}-imap \
    php${PHP_VERSION}-redis

# Install MariaDB
log "Installing MariaDB..."
apt install -y mariadb-server mariadb-client

# Install Redis
log "Installing Redis..."
apt install -y redis-server

# Install mail server components
log "Installing mail server components..."
apt install -y \
    postfix \
    postfix-mysql \
    dovecot-core \
    dovecot-imapd \
    dovecot-pop3d \
    dovecot-lmtpd \
    dovecot-mysql \
    dovecot-managesieved \
    dovecot-sieve \
    spamassassin \
    spamprobe \
    opendkim \
    opendkim-tools \
    policyd-spf

# Install additional utilities
log "Installing additional utilities..."
apt install -y \
    certbot \
    fail2ban \
    rsyslog \
    logrotate

# Configure PHP
log "Configuring PHP..."
PHP_INI="/etc/php/${PHP_VERSION}/fpm/php.ini"
cp "$PHP_INI" "$PHP_INI.backup"

sed -i 's/;cgi.fix_pathinfo=1/cgi.fix_pathinfo=0/' "$PHP_INI"
sed -i 's/upload_max_filesize = 2M/upload_max_filesize = 50M/' "$PHP_INI"
sed -i 's/post_max_size = 8M/post_max_size = 50M/' "$PHP_INI"
sed -i 's/max_execution_time = 30/max_execution_time = 300/' "$PHP_INI"
sed -i 's/memory_limit = 128M/memory_limit = 256M/' "$PHP_INI"

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

# Create mail database and user
log "Creating mail database and user..."
mysql -u root -p"${ROOT_DB_PASS}" << EOF
CREATE DATABASE ${DB_NAME};
CREATE USER '${DB_USER}'@'localhost' IDENTIFIED BY '${DB_PASS}';
GRANT ALL PRIVILEGES ON ${DB_NAME}.* TO '${DB_USER}'@'localhost';
FLUSH PRIVILEGES;
EOF

# Create mail database schema
log "Creating mail database schema..."
mysql -u root -p"${ROOT_DB_PASS}" "${DB_NAME}" << 'EOF'
CREATE TABLE virtual_domains (
    id int(11) NOT NULL auto_increment,
    name varchar(50) NOT NULL,
    PRIMARY KEY (id)
);

CREATE TABLE virtual_users (
    id int(11) NOT NULL auto_increment,
    domain_id int(11) NOT NULL,
    password varchar(106) NOT NULL,
    email varchar(120) NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY email (email),
    FOREIGN KEY (domain_id) REFERENCES virtual_domains(id) ON DELETE CASCADE
);

CREATE TABLE virtual_aliases (
    id int(11) NOT NULL auto_increment,
    domain_id int(11) NOT NULL,
    source varchar(100) NOT NULL,
    destination varchar(100) NOT NULL,
    PRIMARY KEY (id),
    FOREIGN KEY (domain_id) REFERENCES virtual_domains(id) ON DELETE CASCADE
);
EOF

# Insert default domain and postmaster account
log "Creating default domain and postmaster account..."
POSTMASTER_HASH=$(doveadm pw -s SHA512-CRYPT -p "${POSTMASTER_PASS}")
mysql -u root -p"${ROOT_DB_PASS}" "${DB_NAME}" << EOF
INSERT INTO virtual_domains (name) VALUES ('${DOMAIN_NAME}');
INSERT INTO virtual_users (domain_id, email, password) VALUES (1, 'postmaster@${DOMAIN_NAME}', '${POSTMASTER_HASH}');
EOF

# Configure Postfix
log "Configuring Postfix..."
cp /etc/postfix/main.cf /etc/postfix/main.cf.backup

cat > /etc/postfix/main.cf << EOF
# Basic configuration
myhostname = ${HOSTNAME}.${DOMAIN_NAME}
mydomain = ${DOMAIN_NAME}
myorigin = \$mydomain
inet_interfaces = all
inet_protocols = ipv4
mydestination = localhost

# Virtual domains
virtual_transport = lmtp:unix:private/dovecot-lmtp
virtual_mailbox_domains = mysql:/etc/postfix/mysql-virtual-mailbox-domains.cf
virtual_mailbox_maps = mysql:/etc/postfix/mysql-virtual-mailbox-maps.cf
virtual_alias_maps = mysql:/etc/postfix/mysql-virtual-alias-maps.cf

# TLS configuration
smtpd_tls_cert_file = /etc/ssl/certs/ssl-cert-snakeoil.pem
smtpd_tls_key_file = /etc/ssl/private/ssl-cert-snakeoil.key
smtpd_use_tls = yes
smtpd_tls_auth_only = yes
smtp_tls_security_level = may
smtpd_tls_security_level = may
smtpd_tls_protocols = !SSLv2, !SSLv3

# SASL authentication
smtpd_sasl_type = dovecot
smtpd_sasl_path = private/auth
smtpd_sasl_auth_enable = yes

# Security restrictions
smtpd_helo_restrictions = permit_mynetworks, permit_sasl_authenticated, reject_invalid_helo_hostname, reject_non_fqdn_helo_hostname
smtpd_recipient_restrictions = permit_mynetworks, permit_sasl_authenticated, reject_non_fqdn_recipient, reject_unknown_recipient_domain, reject_unauth_destination
smtpd_sender_restrictions = permit_mynetworks, permit_sasl_authenticated, reject_non_fqdn_sender, reject_unknown_sender_domain

# Message size limit (50MB)
message_size_limit = 52428800

# DKIM
milter_protocol = 2
milter_default_action = accept
smtpd_milters = inet:localhost:12301
non_smtpd_milters = inet:localhost:12301
EOF

# Create Postfix MySQL configuration files
log "Creating Postfix MySQL configuration files..."

cat > /etc/postfix/mysql-virtual-mailbox-domains.cf << EOF
user = ${DB_USER}
password = ${DB_PASS}
hosts = 127.0.0.1
dbname = ${DB_NAME}
query = SELECT 1 FROM virtual_domains WHERE name='%s'
EOF

cat > /etc/postfix/mysql-virtual-mailbox-maps.cf << EOF
user = ${DB_USER}
password = ${DB_PASS}
hosts = 127.0.0.1
dbname = ${DB_NAME}
query = SELECT 1 FROM virtual_users WHERE email='%s'
EOF

cat > /etc/postfix/mysql-virtual-alias-maps.cf << EOF
user = ${DB_USER}
password = ${DB_PASS}
hosts = 127.0.0.1
dbname = ${DB_NAME}
query = SELECT destination FROM virtual_aliases WHERE source='%s'
EOF

# Set permissions for Postfix config files
chmod 640 /etc/postfix/mysql-*.cf
chgrp postfix /etc/postfix/mysql-*.cf

# Configure Dovecot
log "Configuring Dovecot..."
cp /etc/dovecot/dovecot.conf /etc/dovecot/dovecot.conf.backup

cat > /etc/dovecot/dovecot.conf << EOF
# Protocols
protocols = imap pop3 lmtp sieve

# Listeners
service imap-login {
    inet_listener imap {
        port = 143
    }
    inet_listener imaps {
        port = 993
        ssl = yes
    }
}

service pop3-login {
    inet_listener pop3 {
        port = 110
    }
    inet_listener pop3s {
        port = 995
        ssl = yes
    }
}

service lmtp {
    unix_listener /var/spool/postfix/private/dovecot-lmtp {
        group = postfix
        mode = 0600
        user = postfix
    }
}

service auth {
    unix_listener /var/spool/postfix/private/auth {
        group = postfix
        mode = 0660
        user = postfix
    }
    unix_listener auth-userdb {
        group = mail
        mode = 0600
        user = vmail
    }
}

service auth-worker {
    user = vmail
}

service dict {
    unix_listener dict {
        group = mail
        mode = 0600
        user = vmail
    }
}

# SSL settings
ssl = required
ssl_cert = </etc/ssl/certs/ssl-cert-snakeoil.pem
ssl_key = </etc/ssl/private/ssl-cert-snakeoil.key

# Mail location
mail_location = maildir:/var/mail/vhosts/%d/%n
mail_privileged_group = mail

# Authentication
disable_plaintext_auth = yes
auth_mechanisms = plain login

passdb {
    driver = sql
    args = /etc/dovecot/dovecot-sql.conf.ext
}

userdb {
    driver = static
    args = uid=vmail gid=vmail home=/var/mail/vhosts/%d/%n
}

# Sieve plugin
plugin {
    sieve = ~/.dovecot.sieve
    sieve_dir = ~/sieve
}

# Namespace inbox
namespace inbox {
    type = private
    inbox = yes
    location = 
    mailbox Drafts {
        special_use = \Drafts
    }
    mailbox Junk {
        special_use = \Junk
    }
    mailbox Sent {
        special_use = \Sent
    }
    mailbox "Sent Messages" {
        special_use = \Sent
    }
    mailbox Trash {
        special_use = \Trash
    }
}

# Protocols configuration
protocol lmtp {
    mail_plugins = \$mail_plugins sieve
}

protocol imap {
    mail_plugins = \$mail_plugins imap_sieve
}
EOF

# Create Dovecot SQL configuration
cat > /etc/dovecot/dovecot-sql.conf.ext << EOF
driver = mysql
connect = host=127.0.0.1 dbname=${DB_NAME} user=${DB_USER} password=${DB_PASS}
default_pass_scheme = SHA512-CRYPT
password_query = SELECT email as user, password FROM virtual_users WHERE email='%u';
EOF

# Create vmail user for email storage
log "Creating vmail user..."
groupadd -g 5000 vmail
useradd -g vmail -u 5000 vmail -d /var/mail/vhosts -m

# Create mail directories
mkdir -p /var/mail/vhosts/${DOMAIN_NAME}
chown -R vmail:vmail /var/mail/vhosts
chmod -R 700 /var/mail/vhosts

# Configure DKIM
log "Configuring DKIM..."
mkdir -p /etc/opendkim/keys/${DOMAIN_NAME}

opendkim-genkey -t -s mail -d ${DOMAIN_NAME} -D /etc/opendkim/keys/${DOMAIN_NAME}/

cat > /etc/opendkim.conf << EOF
AutoRestart             Yes
AutoRestartRate         10/1h
UMask                   002
Syslog                  yes
SyslogSuccess           Yes
LogWhy                  Yes

Canonicalization        relaxed/simple

ExternalIgnoreList      refile:/etc/opendkim/TrustedHosts
InternalHosts           refile:/etc/opendkim/TrustedHosts
KeyTable                refile:/etc/opendkim/KeyTable
SigningTable            refile:/etc/opendkim/SigningTable

Mode                    sv
PidFile                 /var/run/opendkim/opendkim.pid
SignatureAlgorithm      rsa-sha256

UserID                  opendkim:opendkim

Socket                  inet:12301@localhost
EOF

echo "127.0.0.1" > /etc/opendkim/TrustedHosts
echo "localhost" >> /etc/opendkim/TrustedHosts
echo "${DOMAIN_NAME}" >> /etc/opendkim/TrustedHosts

echo "mail._domainkey.${DOMAIN_NAME} ${DOMAIN_NAME}:mail:/etc/opendkim/keys/${DOMAIN_NAME}/mail.private" > /etc/opendkim/KeyTable
echo "*@${DOMAIN_NAME} mail._domainkey.${DOMAIN_NAME}" > /etc/opendkim/SigningTable

chown -R opendkim:opendkim /etc/opendkim
chmod -R 700 /etc/opendkim/keys

# Configure SpamProbe
log "Configuring SpamProbe..."
mkdir -p /etc/spamprobe
su vmail -c "spamprobe auto-train"

# Configure Nginx for webmail interface
log "Configuring Nginx for webmail interface..."
cat > /etc/nginx/sites-available/webmail << EOF
server {
    listen 80;
    listen [::]:80;
    server_name ${DOMAIN_NAME} www.${DOMAIN_NAME};
    root /var/www/webmail;

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
ln -sf /etc/nginx/sites-available/webmail /etc/nginx/sites-enabled/
rm -f /etc/nginx/sites-enabled/default

# Create simple webmail interface
log "Creating simple webmail interface..."
mkdir -p /var/www/webmail
cat > /var/www/webmail/index.php << 'EOF'
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mail Server Dashboard</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 40px; background-color: #f5f5f5; }
        .container { max-width: 800px; margin: 0 auto; background: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h1 { color: #333; text-align: center; }
        .info-box { background: #e8f4fd; padding: 20px; border-radius: 5px; margin: 20px 0; }
        .status { display: flex; justify-content: space-between; margin: 10px 0; }
        .status span:first-child { font-weight: bold; }
        .running { color: #28a745; }
        .stopped { color: #dc3545; }
        table { width: 100%; border-collapse: collapse; margin: 20px 0; }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background-color: #f8f9fa; }
    </style>
</head>
<body>
    <div class="container">
        <h1>📧 Mail Server Dashboard</h1>
        
        <div class="info-box">
            <h3>Server Information</h3>
            <table>
                <tr><td><strong>Hostname:</strong></td><td><?php echo gethostname(); ?></td></tr>
                <tr><td><strong>Domain:</strong></td><td><?php echo $_SERVER['HTTP_HOST']; ?></td></tr>
                <tr><td><strong>PHP Version:</strong></td><td><?php echo PHP_VERSION; ?></td></tr>
                <tr><td><strong>Server Time:</strong></td><td><?php echo date('Y-m-d H:i:s T'); ?></td></tr>
            </table>
        </div>

        <div class="info-box">
            <h3>Service Status</h3>
            <?php
            $services = [
                'nginx' => 'Web Server',
                'postfix' => 'SMTP Server',
                'dovecot' => 'IMAP/POP3 Server',
                'opendkim' => 'DKIM Service',
                'mariadb' => 'Database Server',
                'redis-server' => 'Cache Server'
            ];
            
            foreach ($services as $service => $name) {
                $status = shell_exec("systemctl is-active $service 2>/dev/null");
                $status = trim($status);
                $class = ($status === 'active') ? 'running' : 'stopped';
                echo "<div class='status'><span>$name:</span><span class='$class'>$status</span></div>";
            }
            ?>
        </div>

        <div class="info-box">
            <h3>Mail Configuration</h3>
            <p><strong>IMAP:</strong> <?php echo $_SERVER['HTTP_HOST']; ?>:993 (SSL/TLS)</p>
            <p><strong>POP3:</strong> <?php echo $_SERVER['HTTP_HOST']; ?>:995 (SSL/TLS)</p>
            <p><strong>SMTP:</strong> <?php echo $_SERVER['HTTP_HOST']; ?>:587 (STARTTLS)</p>
            <p><strong>SMTP:</strong> <?php echo $_SERVER['HTTP_HOST']; ?>:465 (SSL/TLS)</p>
        </div>

        <div class="info-box">
            <h3>Quick Actions</h3>
            <p>• <strong>View Mail Queue:</strong> <code>mailq</code></p>
            <p>• <strong>Test SMTP:</strong> <code>echo "Test message" | mail -s "Test" user@domain.com</code></p>
            <p>• <strong>View Mail Logs:</strong> <code>tail -f /var/log/mail.log</code></p>
            <p>• <strong>Check Dovecot Status:</strong> <code>doveadm who</code></p>
        </div>
    </div>
</body>
</html>
EOF

chown -R nginx:nginx /var/www/webmail

# Configure Fail2ban
log "Configuring Fail2ban..."
cat > /etc/fail2ban/jail.local << EOF
[DEFAULT]
bantime = 3600
findtime = 600
maxretry = 5

[sshd]
enabled = true

[postfix]
enabled = true

[dovecot]
enabled = true

[nginx-http-auth]
enabled = true
EOF

# Test Nginx configuration
nginx -t || error "Nginx configuration test failed"

# Start and enable services
log "Starting and enabling services..."
systemctl enable nginx php${PHP_VERSION}-fpm mariadb redis-server postfix dovecot opendkim fail2ban
systemctl start nginx php${PHP_VERSION}-fpm mariadb redis-server postfix dovecot opendkim fail2ban

# Configure firewall
if command -v ufw &> /dev/null; then
    log "Configuring firewall..."
    ufw allow 22/tcp
    ufw allow 25/tcp
    ufw allow 80/tcp
    ufw allow 110/tcp
    ufw allow 143/tcp
    ufw allow 443/tcp
    ufw allow 465/tcp
    ufw allow 587/tcp
    ufw allow 993/tcp
    ufw allow 995/tcp
    ufw --force enable
fi

# Create system information file
log "Creating system information file..."
DKIM_PUBLIC=$(cat /etc/opendkim/keys/${DOMAIN_NAME}/mail.txt)

cat > /root/MAIL_SERVER_INFO.md << EOF
# Complete Mail Server Installation

## System Information
- OS: Debian 13 Trixie
- Web Server: Nginx
- PHP: ${PHP_VERSION}
- Database: MariaDB
- SMTP: Postfix
- IMAP/POP3: Dovecot
- DKIM: OpenDKIM
- Anti-Spam: SpamProbe

## Database Credentials
- Database: ${DB_NAME}
- Username: ${DB_USER}
- Password: ${DB_PASS}
- Root Password: ${ROOT_DB_PASS}

## Default Email Account
- Email: postmaster@${DOMAIN_NAME}
- Password: ${POSTMASTER_PASS}

## DNS Records Needed
Add these DNS records for ${DOMAIN_NAME}:

### MX Record
${DOMAIN_NAME}. IN MX 10 ${HOSTNAME}.${DOMAIN_NAME}.

### A Record
${HOSTNAME}.${DOMAIN_NAME}. IN A [YOUR_SERVER_IP]

### DKIM Record
${DKIM_PUBLIC}

### SPF Record
${DOMAIN_NAME}. IN TXT "v=spf1 mx ~all"

### DMARC Record
_dmarc.${DOMAIN_NAME}. IN TXT "v=DMARC1; p=quarantine; rua=mailto:postmaster@${DOMAIN_NAME}"

## Mail Client Configuration
- **IMAP:** ${HOSTNAME}.${DOMAIN_NAME}:993 (SSL/TLS)
- **POP3:** ${HOSTNAME}.${DOMAIN_NAME}:995 (SSL/TLS)
- **SMTP:** ${HOSTNAME}.${DOMAIN_NAME}:587 (STARTTLS) or :465 (SSL/TLS)

## Web Interface
- Dashboard: http://${DOMAIN_NAME}

## Useful Commands
- **Add Email User:** 
  \`\`\`sql
  INSERT INTO virtual_users (domain_id, email, password) 
  VALUES (1, 'user@${DOMAIN_NAME}', '\$(doveadm pw -s SHA512-CRYPT -p password)');
  \`\`\`

- **View Mail Queue:** \`mailq\`
- **Send Test Email:** \`echo "Test" | mail -s "Test Subject" user@domain.com\`
- **Check Service Status:** \`systemctl status postfix dovecot nginx\`
- **View Mail Logs:** \`tail -f /var/log/mail.log\`

## Security Notes
- Change default postmaster password immediately
- Configure SSL certificates for production use
- Review and update firewall rules
- Monitor mail logs regularly
- Keep all software updated

Generated on: $(date)
EOF

# Final status check
log "Performing final status check..."
systemctl is-active --quiet nginx && log "✓ Nginx is running" || error "✗ Nginx failed to start"
systemctl is-active --quiet php${PHP_VERSION}-fpm && log "✓ PHP-FPM is running" || error "✗ PHP-FPM failed to start"
systemctl is-active --quiet mariadb && log "✓ MariaDB is running" || error "✗ MariaDB failed to start"
systemctl is-active --quiet postfix && log "✓ Postfix is running" || error "✗ Postfix failed to start"
systemctl is-active --quiet dovecot && log "✓ Dovecot is running" || error "✗ Dovecot failed to start"
systemctl is-active --quiet opendkim && log "✓ OpenDKIM is running" || error "✗ OpenDKIM failed to start"

log "🎉 Complete Mail Server + Web Stack installation completed successfully!"
log ""
log "📋 Next Steps:"
log "1. Configure DNS records (see /root/MAIL_SERVER_INFO.md)"
log "2. Install SSL certificates with: certbot --nginx -d ${DOMAIN_NAME}"
log "3. Test email functionality"
log "4. Add email users to the database"
log "5. Monitor mail logs: tail -f /var/log/mail.log"
log ""
log "📊 Default Credentials:"
log "   Postmaster: postmaster@${DOMAIN_NAME} / ${POSTMASTER_PASS}"
log "   Database: ${DB_USER} / ${DB_PASS}"
log ""
log "🔐 IMPORTANT: Review /root/MAIL_SERVER_INFO.md for complete setup information!"

log "✅ Setup completed! Review /root/MAIL_SERVER_INFO.md for DNS configuration and next steps."