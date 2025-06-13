# Troubleshooting Guide

This guide covers common issues and solutions for Laravel Loop Filament with MCP integration.

## General Troubleshooting

### Enable Debug Mode

For development debugging, enable debug mode in `.env`:
```env
APP_DEBUG=true
LOG_LEVEL=debug
```

**⚠️ Warning**: Never enable debug mode in production as it exposes sensitive information.

### Check Application Logs

```bash
# View latest logs
tail -f storage/logs/laravel.log

# Search for specific errors
grep -i "error" storage/logs/laravel.log

# Check specific log level
grep -i "critical" storage/logs/laravel.log
```

### Clear Application Caches

```bash
# Clear all caches
php artisan optimize:clear

# Clear specific caches
php artisan config:clear
php artisan route:clear
php artisan view:clear
php artisan event:clear
```

## Installation Issues

### Composer Issues

#### Memory Exhaustion
```bash
# Increase memory limit temporarily
COMPOSER_MEMORY_LIMIT=-1 composer install

# Or install without development dependencies
composer install --no-dev --optimize-autoloader
```

#### Package Conflicts
```bash
# Update dependencies
composer update

# Check for conflicts
composer why-not filament/filament

# Clear composer cache
composer clear-cache
```

### Permission Problems

#### Storage Permission Errors
```bash
# Fix storage permissions (Linux/macOS)
chmod -R 775 storage bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache

# For development with different user
sudo chown -R $USER:www-data storage bootstrap/cache
sudo chmod -R 775 storage bootstrap/cache
```

#### Database File Permissions (SQLite)
```bash
# Create database file if missing
touch database/database.sqlite

# Set proper permissions
chmod 664 database/database.sqlite
chown www-data:www-data database/database.sqlite
```

### Database Issues

#### Migration Failures
```bash
# Check database connection
php artisan tinker
>>> DB::connection()->getPdo();

# Reset migrations (⚠️ destroys data)
php artisan migrate:fresh

# Rollback specific migration
php artisan migrate:rollback --step=1

# Check migration status
php artisan migrate:status
```

#### SQLite Lock Issues
```bash
# Check if database file is locked
lsof database/database.sqlite

# Kill processes holding locks
sudo fuser -k database/database.sqlite

# Recreate database file
rm database/database.sqlite
touch database/database.sqlite
php artisan migrate
```

## Filament v4.0 Specific Issues

### Asset Loading Problems

#### Filament Assets Not Found
```bash
# Rebuild Filament assets
php artisan filament:optimize

# Clear view cache
php artisan view:clear

# Rebuild application assets
npm run build
```

#### Version Mismatch Errors
```bash
# Check Filament version
composer show filament/filament

# Update to latest v4.0
composer update filament/filament

# Ensure no v3.x dependencies
composer show | grep filament
```

### Navigation Issues

#### Resources Not Appearing
```php
// Check resource registration in AdminPanelProvider.php
public function panel(Panel $panel): Panel
{
    return $panel
        ->discoverResources(in: app_path('Filament/Resources'), for: 'App\\Filament\\Resources');
}
```

#### Page Access Denied
```php
// Check user permissions
if (auth()->user()->can('view-admin')) {
    // User has access
}

// Or check FilamentUser interface implementation
class User extends Authenticatable implements FilamentUser
{
    public function canAccessPanel(Panel $panel): bool
    {
        return true; // Adjust logic as needed
    }
}
```

### Form Component Issues

#### KeyValue Component Not Working
```php
// Ensure proper Filament v4 syntax
KeyValue::make('metadata')
    ->keyLabel('Key')
    ->valueLabel('Value')
    ->columnSpanFull()
```

#### Select Component Problems
```php
// Use proper relationship syntax
Select::make('dataset_id')
    ->relationship('dataset', 'name')
    ->searchable()
    ->preload()
```

## MCP Integration Issues

### Server Connection Problems

#### API Key Authentication Failures
```bash
# Check API key in database
php artisan tinker
>>> App\Models\ApiKey::where('key', 'your-key')->first()

# Create test API key
>>> App\Models\ApiKey::create(['name' => 'Test', 'key' => 'mcp_test123', 'permissions' => ['datasets' => 'read,write'], 'rate_limits' => ['requests_per_minute' => '60'], 'is_active' => true, 'user_id' => 1])
```

#### Invalid JSON-RPC Requests
```bash
# Test with valid JSON-RPC request
curl -X POST http://localhost:8000/api/mcp \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer mcp_test123" \
  -d '{
    "jsonrpc": "2.0",
    "id": "test-1",
    "method": "tools/list"
  }'
```

#### Route Not Found
```bash
# Check if API routes are loaded
php artisan route:list | grep mcp

# Clear route cache
php artisan route:clear

# Ensure routes/api.php is included
# Check in bootstrap/app.php or RouteServiceProvider
```

### Client Connection Issues

#### Connection Timeout
```php
// Increase timeout in McpClient
private function sendHttpRequest(array $request): array
{
    $response = Http::timeout(60) // Increased from 30
        ->withHeaders($headers)
        ->post($this->connection->endpoint_url, $request);
}
```

#### Transport Type Errors
```bash
# Check supported transport types
php artisan tinker
>>> $connection = App\Models\McpConnection::first()
>>> $client = new App\Services\McpClient($connection)
>>> $client->testConnection()
```

#### WebSocket Connection Failures
```bash
# Check if WebSocket server is running
netstat -an | grep :8001

# Test WebSocket connection manually
wscat -c ws://localhost:8001/mcp
```

### Tool Execution Problems

#### Tool Not Found
```json
// Check available tools
{
  "jsonrpc": "2.0",
  "method": "tools/list",
  "id": "1"
}

// Verify tool name matches exactly
{
  "jsonrpc": "2.0",
  "method": "tools/call",
  "params": {
    "name": "create_dataset", // Must match exactly
    "arguments": {}
  },
  "id": "2"
}
```

#### Parameter Validation Errors
```php
// Check tool parameter schema
$validator = Validator::make($args, [
    'name' => 'required|string|max:255',
    'type' => 'required|in:json,csv,xml,yaml,text'
]);

if ($validator->fails()) {
    // Handle validation errors
    return $this->errorResponse(
        'Validation failed: ' . implode(', ', $validator->errors()->all()),
        -32602
    );
}
```

## Performance Issues

### Slow Database Queries

#### Enable Query Logging
```php
// Add to AppServiceProvider boot method
if (app()->environment('local')) {
    DB::listen(function ($query) {
        Log::info('Query: ' . $query->sql . ' [' . implode(', ', $query->bindings) . '] - ' . $query->time . 'ms');
    });
}
```

#### Optimize Database Indexes
```php
// Add indexes to migrations
$table->index(['status', 'user_id']); // For filtering
$table->index(['created_at']); // For ordering
$table->fullText(['title', 'content']); // For search (MySQL only)
```

### Memory Issues

#### PHP Memory Limit
```php
// Check current memory limit
ini_get('memory_limit');

// Increase in php.ini
memory_limit = 512M

// Or temporarily in code
ini_set('memory_limit', '512M');
```

#### Large Dataset Processing
```php
// Use chunking for large datasets
Dataset::chunk(100, function ($datasets) {
    foreach ($datasets as $dataset) {
        // Process each dataset
    }
});

// Use lazy collections
Dataset::lazy()->each(function ($dataset) {
    // Process each dataset
});
```

### Queue Issues

#### Jobs Not Processing
```bash
# Check queue worker status
php artisan queue:work --once

# Check failed jobs
php artisan queue:failed

# Retry failed jobs
php artisan queue:retry all

# Clear failed jobs
php artisan queue:flush
```

#### Supervisor Configuration
```bash
# Check supervisor status
sudo supervisorctl status

# Restart workers
sudo supervisorctl restart laravel-worker:*

# Check supervisor logs
sudo tail -f /var/log/supervisor/supervisord.log
```

## Security Issues

### CSRF Token Mismatch

#### Session Configuration
```env
# Ensure session driver is properly configured
SESSION_DRIVER=file
SESSION_LIFETIME=120

# For multiple servers, use database or redis
SESSION_DRIVER=redis
```

#### CORS Issues
```php
// Configure CORS in config/cors.php
'allowed_origins' => ['https://your-domain.com'],
'allowed_methods' => ['GET', 'POST', 'PUT', 'DELETE'],
'allowed_headers' => ['Content-Type', 'Authorization'],
```

### File Upload Issues

#### Upload Size Limits
```php
// Check PHP limits
phpinfo(); // Look for upload_max_filesize and post_max_size

// Increase in php.ini
upload_max_filesize = 50M
post_max_size = 50M
max_execution_time = 300
```

#### File Permission Issues
```bash
# Check storage permissions
ls -la storage/app/

# Fix permissions
chmod -R 775 storage/app/
chown -R www-data:www-data storage/app/
```

## Production Issues

### SSL Certificate Problems

#### Certificate Verification
```bash
# Check certificate status
openssl s_client -connect your-domain.com:443 -servername your-domain.com

# Check certificate expiration
openssl x509 -in /path/to/certificate.crt -text -noout | grep "Not After"
```

#### Mixed Content Warnings
```php
// Force HTTPS in production
if (app()->environment('production')) {
    URL::forceScheme('https');
}
```

### Application Performance

#### Enable OPcache
```php
// Check OPcache status
opcache_get_status();

// Enable in php.ini
opcache.enable=1
opcache.memory_consumption=256
opcache.max_accelerated_files=20000
```

#### Database Connection Pooling
```php
// Configure connection limits in config/database.php
'mysql' => [
    'options' => [
        PDO::ATTR_PERSISTENT => true,
    ],
    'pool' => [
        'min_connections' => 1,
        'max_connections' => 10,
    ],
],
```

## Debugging Tools

### Laravel Debugging

#### Laravel Telescope (Development)
```bash
# Install Telescope
composer require laravel/telescope --dev

# Publish and migrate
php artisan telescope:install
php artisan migrate
```

#### Debug Bar (Development)
```bash
# Install Debug Bar
composer require barryvdh/laravel-debugbar --dev

# The package auto-registers in development
```

### MCP Debugging

#### Enable MCP Logging
```php
// In McpServer.php, add detailed logging
Log::debug('MCP Request', [
    'method' => $method,
    'params' => $params,
    'user_agent' => request()->header('User-Agent'),
    'ip' => request()->ip()
]);
```

#### Monitor MCP Traffic
```bash
# Monitor HTTP traffic (if using HTTP transport)
sudo tcpdump -i any -A 'port 8000 and host localhost'

# Monitor file descriptor usage (for stdio transport)
lsof -p $(pgrep php)
```

### System Monitoring

#### Monitor Server Resources
```bash
# CPU and memory usage
htop

# Disk I/O
iotop

# Network usage
nethogs

# Check disk space
df -h

# Check inodes
df -i
```

#### Application Monitoring
```bash
# Monitor Laravel logs in real-time
tail -f storage/logs/laravel.log

# Monitor web server logs
tail -f /var/log/nginx/error.log
tail -f /var/log/apache2/error.log

# Monitor PHP-FPM logs
tail -f /var/log/php8.2-fpm.log
```

## Emergency Recovery

### Application Recovery

#### Restore from Backup
```bash
# Restore database
mysql -u username -p database_name < backup.sql

# Restore application files
tar -xzf app_backup.tar.gz -C /var/www/

# Fix permissions
chown -R www-data:www-data /var/www/laravel-loop-filament
chmod -R 755 /var/www/laravel-loop-filament
chmod -R 775 storage bootstrap/cache
```

#### Emergency Maintenance Mode
```bash
# Enable maintenance mode
php artisan down --secret="emergency-access-token"

# Access with secret token
https://your-domain.com?secret=emergency-access-token

# Disable maintenance mode
php artisan up
```

### Database Recovery

#### Repair Corrupted Database
```sql
-- For MySQL
REPAIR TABLE datasets;
REPAIR TABLE documents;

-- Check table integrity
CHECK TABLE datasets;
```

#### Reset Application State
```bash
# Nuclear option - reset everything (⚠️ destroys all data)
php artisan migrate:fresh --force
php artisan db:seed --force
php artisan optimize:clear
```

## Getting Help

### Log Analysis
When reporting issues, include:
- Laravel log entries (`storage/logs/laravel.log`)
- Web server error logs
- System resource usage
- Exact error messages and stack traces

### Useful Commands for Support
```bash
# System information
php --version
composer --version
npm --version

# Laravel information
php artisan --version
php artisan about

# Check environment
php artisan env:show

# Database connection test
php artisan migrate:status

# Check file permissions
ls -la storage/
ls -la bootstrap/cache/
```

For complex issues that aren't covered here, create a detailed issue report with:
1. Steps to reproduce the problem
2. Expected vs actual behavior
3. Relevant log entries
4. System environment details
5. Any recent changes made to the system