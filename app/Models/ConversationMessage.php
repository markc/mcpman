<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ConversationMessage extends Model
{
    use HasFactory;

    protected $fillable = [
        'conversation_id',
        'role',
        'content',
        'metadata',
        'context',
        'tool_name',
        'tool_arguments',
        'tool_result',
        'sequence_number',
        'sent_at',
    ];

    protected $casts = [
        'metadata' => 'array',
        'context' => 'array',
        'tool_arguments' => 'array',
        'tool_result' => 'array',
        'sent_at' => 'datetime',
    ];

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function isUserMessage(): bool
    {
        return $this->role === 'user';
    }

    public function isAssistantMessage(): bool
    {
        return $this->role === 'assistant';
    }

    public function isSystemMessage(): bool
    {
        return $this->role === 'system';
    }

    public function isToolCall(): bool
    {
        return $this->role === 'tool_call';
    }

    public function isToolResult(): bool
    {
        return $this->role === 'tool_result';
    }

    public function isError(): bool
    {
        return $this->role === 'error';
    }

    public function getMetadata(?string $key = null)
    {
        if ($key) {
            return $this->metadata[$key] ?? null;
        }

        return $this->metadata ?? [];
    }

    public function setMetadata(string $key, $value): void
    {
        $metadata = $this->metadata ?? [];
        $metadata[$key] = $value;
        $this->update(['metadata' => $metadata]);
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

    public function getFormattedContent(): string
    {
        if ($this->isToolCall()) {
            return "🔧 **Tool Call:** {$this->tool_name}\n```json\n".
                   json_encode($this->tool_arguments, JSON_PRETTY_PRINT).
                   "\n```";
        }

        if ($this->isToolResult()) {
            $success = $this->getMetadata('success') ? '✅' : '❌';

            return "{$success} **Tool Result:** {$this->tool_name}\n".$this->content;
        }

        if ($this->isError()) {
            return '❌ **Error:** '.$this->content;
        }

        if ($this->isSystemMessage()) {
            return '🔔 **System:** '.$this->content;
        }

        return $this->content;
    }

    public function getWordCount(): int
    {
        return str_word_count(strip_tags($this->content));
    }

    public function getCharacterCount(): int
    {
        return strlen($this->content);
    }

    public function exportMessage(): array
    {
        return [
            'id' => $this->id,
            'role' => $this->role,
            'content' => $this->content,
            'tool_name' => $this->tool_name,
            'tool_arguments' => $this->tool_arguments,
            'tool_result' => $this->tool_result,
            'metadata' => $this->metadata,
            'context' => $this->context,
            'sequence_number' => $this->sequence_number,
            'sent_at' => $this->sent_at?->toISOString(),
            'word_count' => $this->getWordCount(),
            'character_count' => $this->getCharacterCount(),
        ];
    }

    // Scopes
    public function scopeByRole($query, string $role)
    {
        return $query->where('role', $role);
    }

    public function scopeUserMessages($query)
    {
        return $query->where('role', 'user');
    }

    public function scopeAssistantMessages($query)
    {
        return $query->where('role', 'assistant');
    }

    public function scopeToolMessages($query)
    {
        return $query->whereIn('role', ['tool_call', 'tool_result']);
    }

    public function scopeWithTool($query, string $toolName)
    {
        return $query->where('tool_name', $toolName);
    }

    public function scopeInSequence($query)
    {
        return $query->orderBy('sequence_number');
    }

    public function scopeRecent($query, int $hours = 24)
    {
        return $query->where('sent_at', '>=', now()->subHours($hours));
    }
}
