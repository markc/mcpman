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
        'last_used_at',
        'expires_at',
        'usage_count',
        'user_id',
    ];

    protected $casts = [
        'permissions' => 'array',
        'rate_limits' => 'array',
        'is_active' => 'boolean',
        'last_used_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    protected $hidden = [
        'key',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true)
            ->where(function ($q) {
                $q->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            });
    }

    public function hasPermission(string $permission): bool
    {
        return in_array($permission, $this->permissions ?? []);
    }

    public function updateLastUsed(): void
    {
        $this->update(['last_used_at' => now()]);
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

    public static function generateKey(): string
    {
        return 'mcp_'.Str::random(40);
    }
}
