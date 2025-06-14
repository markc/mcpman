# Stage 01: Laravel Foundation & Environment Setup

## Overview
Establish the Laravel 12 foundation with proper environment configuration, database setup, and testing framework integration.

## Prerequisites
- PHP 8.2+
- Composer
- Node.js & NPM
- SQLite support

## Step-by-Step Implementation

### 1. Fresh Laravel Installation
```bash
composer create-project laravel/laravel mcpman
cd mcpman
```

### 2. Environment Configuration
```bash
# Copy and configure environment
cp .env.example .env

# Update .env with these key settings:
DB_CONNECTION=sqlite
DB_DATABASE=database/database.sqlite

# Generate application key
php artisan key:generate
```

### 3. Database Setup
```bash
# Create SQLite database file
touch database/database.sqlite

# Test database connection
php artisan migrate
```

### 4. Install Pest Testing Framework
```bash
# Install Pest PHP
composer require pestphp/pest --dev
composer require pestphp/pest-plugin-laravel --dev

# Initialize Pest
./vendor/bin/pest --init
```

### 5. Basic Configuration Updates

**composer.json** - Add development scripts:
```json
{
    "scripts": {
        "dev": [
            "Composer\\Config::disableProcessTimeout",
            "@php artisan serve &",
            "@php artisan queue:listen --tries=1 &", 
            "@php artisan pail --timeout=0 &",
            "npm run dev"
        ],
        "test": [
            "@php artisan test"
        ]
    }
}
```

**tests/Pest.php** - Update test configuration:
```php
<?php

uses(Tests\TestCase::class)->in('Feature');

// Add any global test configuration here
```

### 6. Basic Validation
```bash
# Test the installation
php artisan serve
# Visit http://localhost:8000 - should show Laravel welcome page

# Run tests
composer run test
```

## Expected Outcomes

After completing Stage 01:

✅ Laravel 12 application running on `http://localhost:8000`  
✅ SQLite database configured and migrations working  
✅ Pest testing framework installed and configured  
✅ Development environment ready for Filament integration  
✅ Basic composer scripts for development workflow  

## Common Issues & Solutions

### SQLite Permission Issues
```bash
# Ensure proper permissions
chmod 664 database/database.sqlite
chmod 775 database/
```

### PHP Extension Requirements
```bash
# Ensure required extensions are installed
php -m | grep -E "(pdo_sqlite|sqlite3)"
```

## Next Stage
Proceed to **Stage 02: Filament v4.0 Core Integration** to install and configure Filament with proper v4.0 patterns and namespace handling.

## Files Created/Modified
- `.env` - Database and environment configuration
- `database/database.sqlite` - SQLite database file  
- `composer.json` - Development scripts
- `tests/Pest.php` - Testing configuration

## Git Checkpoint
```bash
git add .
git commit -m "Stage 01: Laravel foundation and environment setup

- Configure SQLite database
- Install Pest testing framework  
- Add development composer scripts
- Validate basic Laravel installation"
```