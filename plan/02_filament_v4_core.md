# Stage 02: Filament v4.0 Core Integration

## Overview
Install and configure Filament v4.0-beta2 with proper namespace handling, theming, and auto-login middleware for local development.

## ⚠️ CRITICAL FILAMENT V4 REQUIREMENTS

**NEVER downgrade to Filament v3.x - this project REQUIRES v4.0-beta2**

## Step-by-Step Implementation

### 1. Install Filament v4.0 Packages
```bash
# Install Filament v4.0-beta2
composer require filament/filament:"^4.0@beta"
composer require filament/schemas:"^4.0@beta"
composer require filament/forms:"^4.0@beta"
composer require filament/tables:"^4.0@beta"
composer require filament/widgets:"^4.0@beta"
```

### 2. Install and Configure Admin Panel
```bash
# Install admin panel
php artisan filament:install --panels

# Choose "admin" as panel name when prompted
```

### 3. Create Admin Panel Provider

**app/Providers/Filament/AdminPanelProvider.php**:
```php
<?php

namespace App\Providers\Filament;

use App\Http\Middleware\AutoLoginForLocal;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Widgets;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\AuthenticateSession;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login()
            ->colors([
                'primary' => Color::Amber, // Set amber as primary color
            ])
            ->darkMode() // Enable dark mode
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\\Filament\\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\\Filament\\Pages')
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\\Filament\\Widgets')
            ->widgets([
                // Remove default widgets for custom MCP dashboard
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
                AutoLoginForLocal::class, // Custom auto-login for development
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
```

### 4. Create Auto-Login Middleware for Development

**app/Http/Middleware/AutoLoginForLocal.php**:
```php
<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class AutoLoginForLocal
{
    public function handle(Request $request, Closure $next): Response
    {
        // Only enable auto-login in local environment
        if (app()->environment('local') && !Auth::check()) {
            $user = User::where('email', 'admin@example.com')->first();
            
            if ($user) {
                Auth::login($user);
            }
        }

        return $next($request);
    }
}
```

### 5. Update User Model for Filament

**app/Models/User.php** - Add Filament user methods:
```php
<?php

namespace App\Models;

use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable implements FilamentUser
{
    use HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function canAccessPanel(Panel $panel): bool
    {
        // Allow all users to access admin panel in development
        return true;
    }
}
```

### 6. Register Providers

**bootstrap/providers.php** - Ensure admin panel provider is registered:
```php
<?php

return [
    App\Providers\AppServiceProvider::class,
    App\Providers\Filament\AdminPanelProvider::class,
];
```

### 7. Create Default Admin User

**database/seeders/DatabaseSeeder.php**:
```php
<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        User::factory()->create([
            'name' => 'Admin User',
            'email' => 'admin@example.com',
            'password' => bcrypt('password'),
        ]);
    }
}
```

### 8. Build Assets and Test
```bash
# Install NPM dependencies
npm install

# Build Filament assets
npm run build

# Seed database with admin user
php artisan db:seed

# Test Filament installation
php artisan serve
```

## Expected Outcomes

After completing Stage 02:

✅ Filament v4.0-beta2 installed with proper namespaces  
✅ Admin panel accessible at `/admin` with amber theme and dark mode  
✅ Auto-login middleware working for local development  
✅ Default admin user created (`admin@example.com` / `password`)  
✅ Asset compilation working with Vite  

## Validation Steps

1. **Visit** `http://localhost:8000/admin`
2. **Verify** automatic login in local environment
3. **Check** amber primary color and dark mode toggle
4. **Confirm** no namespace errors in browser console

## Critical Filament v4 Patterns Established

### Resource Pattern (for later stages):
```php
use Filament\Schemas\Schema; // NOT Form
public static function form(Schema $schema): Schema
```

### Page Pattern (for later stages):
```php
use Filament\Forms\Form; // NOT Schema  
public function someForm(Form $form): Form
```

## Common Issues & Solutions

### Asset Compilation Errors
```bash
# Clear compiled assets
rm -rf public/css/filament public/js/filament
npm run build
```

### Namespace Import Errors
- Always use `Filament\Forms\Components\*` for form inputs
- Use `Filament\Schemas\Schema` for resources
- Use `Filament\Forms\Form` for page forms

## Next Stage
Proceed to **Stage 03: MCP Data Models & Migrations** to create the core data structures for MCP protocol integration.

## Files Created/Modified
- `app/Providers/Filament/AdminPanelProvider.php` - Main admin panel configuration
- `app/Http/Middleware/AutoLoginForLocal.php` - Development auto-login
- `app/Models/User.php` - Filament user interface implementation
- `bootstrap/providers.php` - Provider registration
- `database/seeders/DatabaseSeeder.php` - Default admin user

## Git Checkpoint
```bash
git add .
git commit -m "Stage 02: Filament v4.0 core integration

- Install Filament v4.0-beta2 with proper namespaces
- Configure amber theme with dark mode
- Add auto-login middleware for local development  
- Create default admin user seeder
- Establish critical Filament v4 form patterns"
```