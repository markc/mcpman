<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Conversation extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'session_id',
        'user_id',
        'mcp_connection_id',
        'context',
        'settings',
        'status',
        'message_count',
        'last_activity_at',
        'started_at',
    ];

    protected $casts = [
        'context' => 'array',
        'settings' => 'array',
        'last_activity_at' => 'datetime',
        'started_at' => 'datetime',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function (Conversation $conversation) {
            if (! $conversation->session_id) {
                $conversation->session_id = 'conv_'.Str::random(32);
            }
            if (! $conversation->title) {
                $conversation->title = 'Conversation '.now()->format('M j, Y g:i A');
            }
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function mcpConnection(): BelongsTo
    {
        return $this->belongsTo(McpConnection::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(ConversationMessage::class)->orderBy('sequence_number');
    }

    public function latestMessage(): HasMany
    {
        return $this->hasMany(ConversationMessage::class)->latest('sent_at');
    }

    public function updateActivity(): void
    {
        $this->update([
            'last_activity_at' => now(),
            'message_count' => $this->messages()->count(),
        ]);
    }

    public function addMessage(array $messageData): ConversationMessage
    {
        $sequenceNumber = $this->messages()->max('sequence_number') + 1;

        $message = $this->messages()->create([
            'role' => $messageData['role'],
            'content' => $messageData['content'],
            'metadata' => $messageData['metadata'] ?? null,
            'context' => $messageData['context'] ?? null,
            'tool_name' => $messageData['tool_name'] ?? null,
            'tool_arguments' => $messageData['tool_arguments'] ?? null,
            'tool_result' => $messageData['tool_result'] ?? null,
            'sequence_number' => $sequenceNumber,
            'sent_at' => $messageData['sent_at'] ?? now(),
        ]);

        $this->updateActivity();

        return $message;
    }

    public function getContext(?string $key = null)
    {
        if ($key) {
            return $this->context[$key] ?? null;
        }

        return $this->context ?? [];
    }

    public function setContext(string $key, $value): void
    {
        $context = $this->context ?? [];
        $context[$key] = $value;
        $this->update(['context' => $context]);
    }

    public function getSetting(?string $key = null)
    {
        if ($key) {
            return $this->settings[$key] ?? null;
        }

        return $this->settings ?? [];
    }

    public function setSetting(string $key, $value): void
    {
        $settings = $this->settings ?? [];
        $settings[$key] = $value;
        $this->update(['settings' => $settings]);
    }

    public function archive(): void
    {
        $this->update(['status' => 'archived']);
    }

    public function end(): void
    {
        $this->update(['status' => 'ended']);
    }

    public function resume(): void
    {
        $this->update(['status' => 'active']);
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function isArchived(): bool
    {
        return $this->status === 'archived';
    }

    public function isEnded(): bool
    {
        return $this->status === 'ended';
    }

    public function getDurationInMinutes(): int
    {
        $start = $this->started_at;
        $end = $this->last_activity_at ?? now();

        return $start->diffInMinutes($end);
    }

    public function getMessagesByRole(string $role): \Illuminate\Database\Eloquent\Collection
    {
        return $this->messages()->where('role', $role)->get();
    }

    public function getToolUsageStats(): array
    {
        $toolMessages = $this->messages()
            ->whereIn('role', ['tool_call', 'tool_result'])
            ->whereNotNull('tool_name')
            ->get();

        $stats = [];
        foreach ($toolMessages as $message) {
            $toolName = $message->tool_name;
            if (! isset($stats[$toolName])) {
                $stats[$toolName] = ['calls' => 0, 'successes' => 0, 'errors' => 0];
            }

            if ($message->role === 'tool_call') {
                $stats[$toolName]['calls']++;
            } elseif ($message->role === 'tool_result') {
                if (isset($message->metadata['success']) && $message->metadata['success']) {
                    $stats[$toolName]['successes']++;
                } else {
                    $stats[$toolName]['errors']++;
                }
            }
        }

        return $stats;
    }

    public function exportConversation(): array
    {
        return [
            'id' => $this->id,
            'session_id' => $this->session_id,
            'title' => $this->title,
            'status' => $this->status,
            'started_at' => $this->started_at?->toISOString(),
            'last_activity_at' => $this->last_activity_at?->toISOString(),
            'duration_minutes' => $this->getDurationInMinutes(),
            'message_count' => $this->message_count,
            'connection' => [
                'id' => $this->mcpConnection->id,
                'name' => $this->mcpConnection->name,
                'transport_type' => $this->mcpConnection->transport_type,
            ],
            'context' => $this->context,
            'settings' => $this->settings,
            'tool_usage' => $this->getToolUsageStats(),
            'messages' => $this->messages->map(fn ($msg) => [
                'role' => $msg->role,
                'content' => $msg->content,
                'tool_name' => $msg->tool_name,
                'tool_arguments' => $msg->tool_arguments,
                'sent_at' => $msg->sent_at?->toISOString(),
                'sequence_number' => $msg->sequence_number,
            ])->toArray(),
        ];
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeArchived($query)
    {
        return $query->where('status', 'archived');
    }

    public function scopeForUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeForConnection($query, $connectionId)
    {
        return $query->where('mcp_connection_id', $connectionId);
    }

    public function scopeRecentlyActive($query, $hours = 24)
    {
        return $query->where('last_activity_at', '>=', now()->subHours($hours));
    }
}
