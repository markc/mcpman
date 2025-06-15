<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Resource extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'type',
        'path_or_uri',
        'description',
        'metadata',
        'mime_type',
        'size_bytes',
        'checksum',
        'last_accessed_at',
        'last_modified_at',
        'cached_at',
        'expires_at',
        'is_cached',
        'is_public',
        'is_indexable',
        'permissions',
        'tags',
        'parent_resource_id',
        'mcp_connection_id',
        'discovered_by_user_id',
    ];

    protected $casts = [
        'metadata' => 'array',
        'permissions' => 'array',
        'tags' => 'array',
        'last_accessed_at' => 'datetime',
        'last_modified_at' => 'datetime',
        'cached_at' => 'datetime',
        'expires_at' => 'datetime',
        'is_cached' => 'boolean',
        'is_public' => 'boolean',
        'is_indexable' => 'boolean',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($resource) {
            if (empty($resource->slug)) {
                $resource->slug = Str::slug($resource->name);
            }
        });
    }

    public function mcpConnection(): BelongsTo
    {
        return $this->belongsTo(McpConnection::class);
    }

    public function discoveredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'discovered_by_user_id');
    }

    public function parentResource(): BelongsTo
    {
        return $this->belongsTo(Resource::class, 'parent_resource_id');
    }

    public function childResources(): HasMany
    {
        return $this->hasMany(Resource::class, 'parent_resource_id');
    }

    public function updateLastAccessed(): void
    {
        $this->update(['last_accessed_at' => now()]);
    }

    public function isExpired(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }

    public function isCacheValid(): bool
    {
        return $this->is_cached && ! $this->isExpired();
    }

    public function markAsCached(?int $ttlMinutes = null): void
    {
        $this->update([
            'is_cached' => true,
            'cached_at' => now(),
            'expires_at' => $ttlMinutes ? now()->addMinutes($ttlMinutes) : null,
        ]);
    }

    public function clearCache(): void
    {
        $this->update([
            'is_cached' => false,
            'cached_at' => null,
            'expires_at' => null,
        ]);
    }

    public function getFormattedSizeAttribute(): string
    {
        if (! $this->size_bytes) {
            return 'Unknown';
        }

        $bytes = $this->size_bytes;
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];

        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }

        return round($bytes, 2).' '.$units[$i];
    }

    public function getTypeIconAttribute(): string
    {
        return match ($this->type) {
            'file' => 'heroicon-o-document',
            'directory' => 'heroicon-o-folder',
            'api_endpoint' => 'heroicon-o-globe-alt',
            'database' => 'heroicon-o-circle-stack',
            'image' => 'heroicon-o-photo',
            'video' => 'heroicon-o-video-camera',
            'audio' => 'heroicon-o-musical-note',
            default => 'heroicon-o-document-text',
        };
    }

    public function getTypeColorAttribute(): string
    {
        return match ($this->type) {
            'file' => 'primary',
            'directory' => 'warning',
            'api_endpoint' => 'success',
            'database' => 'info',
            'image' => 'secondary',
            'video' => 'danger',
            'audio' => 'purple',
            default => 'gray',
        };
    }

    // Scopes
    public function scopeOfType($query, string $type)
    {
        return $query->where('type', $type);
    }

    public function scopePublic($query)
    {
        return $query->where('is_public', true);
    }

    public function scopeCached($query)
    {
        return $query->where('is_cached', true);
    }

    public function scopeValidCache($query)
    {
        return $query->where('is_cached', true)
            ->where(function ($q) {
                $q->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            });
    }

    public function scopeExpired($query)
    {
        return $query->where('is_cached', true)
            ->where('expires_at', '<=', now());
    }

    public function scopeByConnection($query, int $connectionId)
    {
        return $query->where('mcp_connection_id', $connectionId);
    }

    public function scopeRootResources($query)
    {
        return $query->whereNull('parent_resource_id');
    }

    public function scopeRecentlyAccessed($query, int $days = 30)
    {
        return $query->where('last_accessed_at', '>=', now()->subDays($days));
    }

    public function scopeIndexable($query)
    {
        return $query->where('is_indexable', true);
    }
}
