<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PromptTemplateUsage extends Model
{
    protected $fillable = [
        'prompt_template_id',
        'user_id',
        'conversation_id',
        'variables_used',
        'rendered_content',
        'rating',
        'feedback',
        'execution_time_ms',
        'success',
        'metadata',
    ];

    protected $casts = [
        'variables_used' => 'array',
        'rating' => 'decimal:1',
        'execution_time_ms' => 'integer',
        'success' => 'boolean',
        'metadata' => 'array',
    ];

    protected $attributes = [
        'variables_used' => '[]',
        'success' => true,
        'metadata' => '[]',
    ];

    /**
     * Relationships
     */
    public function promptTemplate(): BelongsTo
    {
        return $this->belongsTo(PromptTemplate::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    /**
     * Scopes
     */
    public function scopeSuccessful($query)
    {
        return $query->where('success', true);
    }

    public function scopeWithRating($query)
    {
        return $query->whereNotNull('rating');
    }

    public function scopeRecentUsage($query, int $days = 30)
    {
        return $query->where('created_at', '>=', now()->subDays($days));
    }

    /**
     * Boot method for model events
     */
    protected static function boot(): void
    {
        parent::boot();

        static::created(function ($usage) {
            // Update template usage count and rating
            $usage->promptTemplate->incrementUsage();

            if ($usage->rating) {
                $usage->promptTemplate->updateRating($usage->rating);
            }
        });

        static::updated(function ($usage) {
            // Update template rating if rating changed
            if ($usage->isDirty('rating') && $usage->rating) {
                $usage->promptTemplate->updateRating($usage->rating);
            }
        });
    }
}
