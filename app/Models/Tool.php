<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class Tool extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'version',
        'category',
        'input_schema',
        'output_schema',
        'metadata',
        'usage_count',
        'last_used_at',
        'is_active',
        'is_favorite',
        'tags',
        'average_execution_time',
        'success_rate',
        'mcp_connection_id',
        'discovered_by_user_id',
    ];

    protected $casts = [
        'input_schema' => 'array',
        'output_schema' => 'array',
        'metadata' => 'array',
        'tags' => 'array',
        'last_used_at' => 'datetime',
        'is_active' => 'boolean',
        'is_favorite' => 'boolean',
        'average_execution_time' => 'decimal:2',
        'success_rate' => 'decimal:2',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($tool) {
            if (empty($tool->slug)) {
                $tool->slug = Str::slug($tool->name);
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

    public function incrementUsage(?float $executionTime = null, bool $successful = true): void
    {
        $this->increment('usage_count');
        $this->update(['last_used_at' => now()]);

        if ($executionTime !== null) {
            $currentAvg = $this->average_execution_time ?? 0;
            $currentCount = $this->usage_count - 1; // Before increment

            $newAvg = ($currentAvg * $currentCount + $executionTime) / $this->usage_count;
            $this->update(['average_execution_time' => $newAvg]);
        }

        // Update success rate
        if (! $successful) {
            $successfulCount = round(($this->success_rate / 100) * ($this->usage_count - 1));
            $newSuccessRate = ($successfulCount / $this->usage_count) * 100;
            $this->update(['success_rate' => $newSuccessRate]);
        }
    }

    public function getParametersAttribute(): array
    {
        return $this->input_schema['properties'] ?? [];
    }

    public function getRequiredParametersAttribute(): array
    {
        return $this->input_schema['required'] ?? [];
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeFavorites($query)
    {
        return $query->where('is_favorite', true);
    }

    public function scopeByCategory($query, string $category)
    {
        return $query->where('category', $category);
    }

    public function scopeRecentlyUsed($query, int $days = 30)
    {
        return $query->where('last_used_at', '>=', now()->subDays($days));
    }

    public function scopePopular($query, int $minUsage = 10)
    {
        return $query->where('usage_count', '>=', $minUsage);
    }
}
