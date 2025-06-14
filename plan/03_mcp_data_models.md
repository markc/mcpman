# Stage 03: MCP Data Models & Migrations

## Overview
Create the core data models for Model Context Protocol integration: Dataset, Document, ApiKey, and McpConnection with proper relationships and Filament-compatible attributes.

## MCP Data Architecture

The MCP Manager uses four core entities:

1. **Dataset** - Collections of documents/data for MCP resources
2. **Document** - Individual documents within datasets  
3. **ApiKey** - Authentication keys for MCP server access
4. **McpConnection** - Outgoing connections to Claude Code instances

## Step-by-Step Implementation

### 1. Create Model Migrations

```bash
# Create migrations for MCP entities
php artisan make:migration create_datasets_table
php artisan make:migration create_documents_table  
php artisan make:migration create_api_keys_table
php artisan make:migration create_mcp_connections_table
```

### 2. Dataset Migration

**database/migrations/xxxx_create_datasets_table.php**:
```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('datasets', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->enum('type', ['json', 'csv', 'xml', 'yaml', 'text'])->default('json');
            $table->enum('status', ['active', 'inactive', 'processing'])->default('active');
            $table->json('schema')->nullable(); // JSON schema definition
            $table->json('metadata')->nullable(); // Additional metadata
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->timestamps();
            
            $table->index(['status', 'type']);
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('datasets');
    }
};
```

### 3. Document Migration

**database/migrations/xxxx_create_documents_table.php**:
```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('documents', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('slug')->unique();
            $table->longText('content');
            $table->enum('type', ['markdown', 'html', 'text', 'json'])->default('markdown');
            $table->enum('status', ['published', 'draft', 'archived'])->default('draft');
            $table->json('tags')->nullable(); // Array of tags
            $table->json('metadata')->nullable(); // Additional metadata
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('dataset_id')->nullable()->constrained()->onDelete('set null');
            $table->timestamps();
            
            $table->index(['status', 'type']);
            $table->index('user_id');
            $table->index('dataset_id');
            $table->fullText(['title', 'content']); // For search functionality
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('documents');
    }
};
```

### 4. ApiKey Migration

**database/migrations/xxxx_create_api_keys_table.php**:
```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('api_keys', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('key')->unique(); // The actual API key
            $table->json('permissions'); // Array of allowed operations
            $table->json('rate_limits')->nullable(); // Rate limiting configuration
            $table->boolean('is_active')->default(true);
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->integer('usage_count')->default(0);
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->timestamps();
            
            $table->index(['is_active', 'expires_at']);
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('api_keys');
    }
};
```

### 5. McpConnection Migration

**database/migrations/xxxx_create_mcp_connections_table.php**:
```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mcp_connections', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('endpoint_url');
            $table->enum('transport_type', ['stdio', 'http', 'websocket'])->default('stdio');
            $table->json('auth_config')->nullable(); // Authentication configuration
            $table->json('capabilities')->nullable(); // Available capabilities
            $table->enum('status', ['active', 'inactive', 'error'])->default('inactive');
            $table->timestamp('last_connected_at')->nullable();
            $table->text('last_error')->nullable();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->timestamps();
            
            $table->index(['status', 'transport_type']);
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mcp_connections');
    }
};
```

### 6. Dataset Model

**app/Models/Dataset.php**:
```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Dataset extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug', 
        'description',
        'type',
        'status',
        'schema',
        'metadata',
        'user_id',
    ];

    protected function casts(): array
    {
        return [
            'schema' => 'array',
            'metadata' => 'array',
        ];
    }

    protected static function boot()
    {
        parent::boot();
        
        static::creating(function ($dataset) {
            if (empty($dataset->slug)) {
                $dataset->slug = Str::slug($dataset->name);
            }
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(Document::class);
    }
}
```

### 7. Document Model

**app/Models/Document.php**:
```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class Document extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'slug',
        'content', 
        'type',
        'status',
        'tags',
        'metadata',
        'user_id',
        'dataset_id',
    ];

    protected function casts(): array
    {
        return [
            'tags' => 'array',
            'metadata' => 'array',
        ];
    }

    protected static function boot()
    {
        parent::boot();
        
        static::creating(function ($document) {
            if (empty($document->slug)) {
                $document->slug = Str::slug($document->title);
            }
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function dataset(): BelongsTo
    {
        return $this->belongsTo(Dataset::class);
    }
}
```

### 8. ApiKey Model

**app/Models/ApiKey.php**:
```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class ApiKey extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'key',
        'permissions',
        'rate_limits',
        'is_active',
        'expires_at',
        'last_used_at',
        'usage_count',
        'user_id',
    ];

    protected function casts(): array
    {
        return [
            'permissions' => 'array',
            'rate_limits' => 'array',
            'is_active' => 'boolean',
            'expires_at' => 'datetime',
            'last_used_at' => 'datetime',
        ];
    }

    protected static function boot()
    {
        parent::boot();
        
        static::creating(function ($apiKey) {
            if (empty($apiKey->key)) {
                $apiKey->key = 'mcp_' . Str::random(40);
            }
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function recordUsage(): void
    {
        $this->increment('usage_count');
        $this->update(['last_used_at' => now()]);
    }

    public function isValid(): bool
    {
        return $this->is_active && 
               ($this->expires_at === null || $this->expires_at->isFuture());
    }
}
```

### 9. McpConnection Model

**app/Models/McpConnection.php**:
```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class McpConnection extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'endpoint_url',
        'transport_type',
        'auth_config',
        'capabilities',
        'status',
        'last_connected_at',
        'last_error',
        'user_id',
    ];

    protected function casts(): array
    {
        return [
            'auth_config' => 'array',
            'capabilities' => 'array',
            'last_connected_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function markAsConnected(): void
    {
        $this->update([
            'status' => 'active',
            'last_connected_at' => now(),
            'last_error' => null,
        ]);
    }

    public function markAsError(string $error): void
    {
        $this->update([
            'status' => 'error',
            'last_error' => $error,
        ]);
    }
}
```

### 10. Run Migrations and Test

```bash
# Run the migrations
php artisan migrate

# Test model creation
php artisan tinker
# In tinker:
# User::first()->datasets()->create(['name' => 'Test Dataset', 'description' => 'Test']);
# exit
```

## Expected Outcomes

After completing Stage 03:

✅ Four core MCP models created with proper relationships  
✅ Database migrations defining the complete MCP data structure  
✅ Model relationships configured (User -> Datasets -> Documents)  
✅ JSON casting for flexible metadata and configuration storage  
✅ Auto-slug generation for SEO-friendly URLs  
✅ Connection status tracking for MCP integrations  

## Model Relationship Summary

```
User (1) -> (Many) Datasets
User (1) -> (Many) Documents  
User (1) -> (Many) ApiKeys
User (1) -> (Many) McpConnections
Dataset (1) -> (Many) Documents
```

## Next Stage
Proceed to **Stage 04: Filament v4 Resources** to create admin interfaces using proper Filament v4 patterns with Schema for resources.

## Files Created/Modified
- `database/migrations/*_create_datasets_table.php`
- `database/migrations/*_create_documents_table.php` 
- `database/migrations/*_create_api_keys_table.php`
- `database/migrations/*_create_mcp_connections_table.php`
- `app/Models/Dataset.php`
- `app/Models/Document.php`
- `app/Models/ApiKey.php` 
- `app/Models/McpConnection.php`

## Git Checkpoint
```bash
git add .
git commit -m "Stage 03: MCP data models and migrations

- Create Dataset, Document, ApiKey, McpConnection models
- Add database migrations with proper relationships
- Implement JSON casting for flexible metadata storage
- Add auto-slug generation and connection status tracking
- Establish core MCP data architecture"
```