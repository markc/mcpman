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
        'metadata',
        'user_id',
    ];

    protected $casts = [
        'auth_config' => 'array',
        'capabilities' => 'array',
        'metadata' => 'array',
        'last_connected_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
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

    public function disconnect(): void
    {
        $this->update(['status' => 'inactive']);
    }
}
