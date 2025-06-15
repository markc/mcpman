<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class McpAnalytics extends Model
{
    protected $fillable = [
        'user_id',
        'connection_id',
        'tool_id',
        'conversation_id',
        'event_type',
        'event_data',
        'duration_ms',
        'success',
        'error_message',
        'ip_address',
        'user_agent',
        'session_id',
        'metadata',
    ];

    protected $casts = [
        'event_data' => 'array',
        'duration_ms' => 'integer',
        'success' => 'boolean',
        'metadata' => 'array',
        'created_at' => 'datetime',
    ];

    protected $attributes = [
        'event_data' => '[]',
        'success' => true,
        'metadata' => '[]',
    ];

    /**
     * Relationships
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function connection(): BelongsTo
    {
        return $this->belongsTo(McpConnection::class, 'connection_id');
    }

    public function tool(): BelongsTo
    {
        return $this->belongsTo(Tool::class);
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    /**
     * Scopes
     */
    public function scopeSuccessful(Builder $query): Builder
    {
        return $query->where('success', true);
    }

    public function scopeFailed(Builder $query): Builder
    {
        return $query->where('success', false);
    }

    public function scopeByEventType(Builder $query, string $eventType): Builder
    {
        return $query->where('event_type', $eventType);
    }

    public function scopeByUser(Builder $query, int $userId): Builder
    {
        return $query->where('user_id', $userId);
    }

    public function scopeByConnection(Builder $query, int $connectionId): Builder
    {
        return $query->where('connection_id', $connectionId);
    }

    public function scopeRecentActivity(Builder $query, int $hours = 24): Builder
    {
        return $query->where('created_at', '>=', now()->subHours($hours));
    }

    public function scopeSlowRequests(Builder $query, int $thresholdMs = 5000): Builder
    {
        return $query->where('duration_ms', '>', $thresholdMs);
    }

    /**
     * Event Types
     */
    public static function getEventTypes(): array
    {
        return [
            'connection_test' => 'Connection Test',
            'conversation_start' => 'Conversation Started',
            'conversation_message' => 'Message Sent',
            'tool_execution' => 'Tool Executed',
            'resource_access' => 'Resource Accessed',
            'template_usage' => 'Template Used',
            'api_request' => 'API Request',
            'authentication' => 'Authentication Event',
            'error' => 'Error Occurred',
        ];
    }

    /**
     * Helper Methods
     */
    public static function recordEvent(array $data): self
    {
        return self::create([
            'user_id' => $data['user_id'] ?? auth()->id(),
            'connection_id' => $data['connection_id'] ?? null,
            'tool_id' => $data['tool_id'] ?? null,
            'conversation_id' => $data['conversation_id'] ?? null,
            'event_type' => $data['event_type'],
            'event_data' => $data['event_data'] ?? [],
            'duration_ms' => $data['duration_ms'] ?? null,
            'success' => $data['success'] ?? true,
            'error_message' => $data['error_message'] ?? null,
            'ip_address' => $data['ip_address'] ?? request()->ip(),
            'user_agent' => $data['user_agent'] ?? request()->userAgent(),
            'session_id' => $data['session_id'] ?? session()->getId(),
            'metadata' => $data['metadata'] ?? [],
        ]);
    }

    public function getFormattedDuration(): string
    {
        if (! $this->duration_ms) {
            return 'N/A';
        }

        if ($this->duration_ms < 1000) {
            return $this->duration_ms.'ms';
        }

        return round($this->duration_ms / 1000, 2).'s';
    }

    public function isSlowRequest(int $thresholdMs = 5000): bool
    {
        return $this->duration_ms && $this->duration_ms > $thresholdMs;
    }
}
