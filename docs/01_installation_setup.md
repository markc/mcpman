# Installation & Setup Guide

This guide covers the installation and development setup for Laravel Loop Filament with MCP integration.

## Prerequisites

### System Requirements
- **PHP 8.2+** with required extensions
- **Composer 2.0+** for dependency management
- **Node.js 18+** with npm for frontend assets
- **Git** for version control

### Recommended
- **PHP 8.3** with OPcache enabled
- **Redis** for caching and sessions
- **MySQL 8.0+** or **PostgreSQL 13+** for production

## Installation Steps

### 1. Clone Repository
```bash
git clone <repository-url> laravel-loop-filament
cd laravel-loop-filament
```

### 2. Install Dependencies
```bash
# PHP dependencies
composer install

# Node.js dependencies
npm install
```

### 3. Environment Configuration
```bash
# Copy environment file
cp .env.example .env

# Generate application key
php artisan key:generate

# Create SQLite database
touch database/database.sqlite
```

### 4. Database Setup
```bash
# Run migrations
php artisan migrate

# Seed with sample data (optional)
php artisan db:seed
```

### 5. Build Assets
```bash
# Development build
npm run dev

# OR production build
npm run build
```

## Development Environment

### Starting Development Server
```bash
# Start all services (recommended)
composer run dev

# This runs concurrently:
# - php artisan serve (Laravel server)
# - php artisan queue:listen --tries=1 (Queue worker)
# - php artisan pail --timeout=0 (Real-time logs)
# - npm run dev (Vite development server)
```

### Individual Services
```bash
# Laravel development server
php artisan serve

# Queue worker for background jobs
php artisan queue:listen --tries=1

# Real-time application logs
php artisan pail --timeout=0

# Vite development server
npm run dev
```

## Essential Commands

### Database Management
```bash
# Fresh migration and seed
php artisan migrate:fresh --seed

# Run migrations only
php artisan migrate

# Seed database
php artisan db:seed
```

### Testing
```bash
# Run all tests
composer run test

# Run tests with Pest directly
php artisan test

# Run specific test files
php artisan test tests/Feature/ExampleTest.php
php artisan test tests/Unit/ExampleTest.php
```

### Code Quality
```bash
# Laravel Pint (code formatting)
./vendor/bin/pint

# Clear configuration cache
php artisan config:clear
```

### Asset Management
```bash
# Development assets with hot reload
npm run dev

# Production build
npm run build

# Watch for changes
npm run dev
```

## Initial Admin Setup

### Access Admin Panel
1. Start the development server: `composer run dev`
2. Navigate to `http://localhost:8000/admin`
3. The system will auto-create an admin user in development

### Create First API Key
```bash
php artisan tinker
```

```php
App\Models\ApiKey::create([
    'name' => 'Development Key',
    'key' => 'mcp_dev_' . Str::random(32),
    'permissions' => [
        'datasets' => 'read,write',
        'documents' => 'read,write',
        'connections' => 'read'
    ],
    'rate_limits' => [
        'requests_per_minute' => '60',
        'requests_per_hour' => '1000'
    ],
    'is_active' => true,
    'user_id' => 1
]);
```

## Environment Variables

### Required Variables
```env
APP_NAME="Laravel Loop Filament"
APP_ENV=local
APP_KEY=base64:...
APP_DEBUG=true
APP_TIMEZONE=UTC
APP_URL=http://localhost:8000

DB_CONNECTION=sqlite
DB_DATABASE=/absolute/path/to/database/database.sqlite

QUEUE_CONNECTION=database
```

### Optional MCP Configuration
```env
# MCP Server Configuration
MCP_SERVER_ENABLED=true
MCP_DEFAULT_TIMEOUT=30
MCP_MAX_CONNECTIONS=10

# Logging
LOG_CHANNEL=stack
LOG_STACK=single,daily
LOG_DEPRECATIONS_CHANNEL=null
LOG_LEVEL=debug
```

## Troubleshooting

### Common Issues

#### Permission Errors
```bash
# Fix storage permissions
chmod -R 755 storage bootstrap/cache
```

#### Memory Issues with Composer
```bash
# Increase memory limit temporarily
COMPOSER_MEMORY_LIMIT=-1 composer install
```

#### SQLite File Not Found
```bash
# Ensure database file exists
touch database/database.sqlite
php artisan migrate
```

#### Filament Assets Not Loading
```bash
# Clear and rebuild assets
php artisan filament:optimize-clear
php artisan filament:optimize
npm run build
```

### Database Connection Issues
If using MySQL/PostgreSQL instead of SQLite:

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=laravel_loop_filament
DB_USERNAME=root
DB_PASSWORD=
```

### Performance Optimization
```bash
# Cache configuration
php artisan config:cache

# Cache routes
php artisan route:cache

# Cache views
php artisan view:cache

# Optimize autoloader
composer install --optimize-autoloader --no-dev
```

## Next Steps

1. **Verify Installation**: Access the admin panel at `/admin`
2. **Create API Keys**: Set up authentication for MCP server
3. **Configure MCP Connections**: Set up outgoing connections to Claude Code
4. **Import Sample Data**: Create test datasets and documents
5. **Test MCP Integration**: Use the conversation interface

## Development Tips

### Useful Artisan Commands
```bash
# Clear all caches
php artisan optimize:clear

# Restart queue workers
php artisan queue:restart

# View application routes
php artisan route:list

# Generate IDE helper files
php artisan ide-helper:generate
```

### Debugging
- Use Laravel Telescope for request debugging (install separately)
- Enable query logging in development
- Use `dd()` and `dump()` for variable inspection
- Check logs in `storage/logs/laravel.log`

For additional help, see the [Troubleshooting Guide](07_troubleshooting.md).