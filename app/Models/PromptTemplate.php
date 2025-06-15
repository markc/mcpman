<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class PromptTemplate extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'category',
        'template_content',
        'variables',
        'instructions',
        'tags',
        'is_active',
        'is_public',
        'usage_count',
        'average_rating',
        'user_id',
        'metadata',
    ];

    protected $casts = [
        'variables' => 'array',
        'tags' => 'array',
        'is_active' => 'boolean',
        'is_public' => 'boolean',
        'usage_count' => 'integer',
        'average_rating' => 'decimal:2',
        'metadata' => 'array',
        'deleted_at' => 'datetime',
    ];

    protected $attributes = [
        'is_active' => true,
        'is_public' => false,
        'usage_count' => 0,
        'average_rating' => 0,
        'variables' => '[]',
        'tags' => '[]',
        'metadata' => '[]',
    ];

    /**
     * Relationships
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function conversations(): HasMany
    {
        return $this->hasMany(Conversation::class);
    }

    public function templateUsages(): HasMany
    {
        return $this->hasMany(PromptTemplateUsage::class);
    }

    /**
     * Scopes
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopePublic(Builder $query): Builder
    {
        return $query->where('is_public', true);
    }

    public function scopeByCategory(Builder $query, string $category): Builder
    {
        return $query->where('category', $category);
    }

    public function scopePopular(Builder $query, int $limit = 10): Builder
    {
        return $query->orderBy('usage_count', 'desc')->limit($limit);
    }

    public function scopeHighRated(Builder $query, float $minRating = 4.0): Builder
    {
        return $query->where('average_rating', '>=', $minRating);
    }

    public function scopeRecentlyUsed(Builder $query, int $days = 7): Builder
    {
        return $query->whereHas('templateUsages', function ($query) use ($days) {
            $query->where('created_at', '>=', now()->subDays($days));
        });
    }

    public function scopeByUser(Builder $query, int $userId): Builder
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Helper Methods
     */
    public function renderTemplate(array $variables = []): string
    {
        $content = $this->template_content;

        // Replace variables in the template
        foreach ($variables as $key => $value) {
            $placeholder = '{{'.$key.'}}';
            $content = str_replace($placeholder, $value, $content);
        }

        return $content;
    }

    public function getRequiredVariables(): array
    {
        return $this->variables ?? [];
    }

    public function incrementUsage(): void
    {
        $this->increment('usage_count');
        $this->touch(); // Update updated_at timestamp
    }

    public function updateRating(float $rating): void
    {
        $totalRatings = $this->templateUsages()->whereNotNull('rating')->count();
        $sumRatings = $this->templateUsages()->whereNotNull('rating')->sum('rating');

        if ($totalRatings > 0) {
            $this->average_rating = $sumRatings / $totalRatings;
            $this->save();
        }
    }

    public function getVariablesFromContent(): array
    {
        preg_match_all('/\{\{(\w+)\}\}/', $this->template_content, $matches);

        return array_unique($matches[1] ?? []);
    }

    public function isUsableByUser(User $user): bool
    {
        return $this->is_public || $this->user_id === $user->id;
    }

    public function duplicate(User $newOwner, ?string $newName = null): self
    {
        $newTemplate = $this->replicate();
        $newTemplate->name = $newName ?? $this->name.' (Copy)';
        $newTemplate->slug = null; // Will be auto-generated
        $newTemplate->user_id = $newOwner->id;
        $newTemplate->is_public = false;
        $newTemplate->usage_count = 0;
        $newTemplate->average_rating = 0;
        $newTemplate->save();

        return $newTemplate;
    }

    /**
     * Categories
     */
    public static function getCategories(): array
    {
        return [
            'general' => 'General Purpose',
            'development' => 'Software Development',
            'data_analysis' => 'Data Analysis',
            'creative' => 'Creative Writing',
            'technical' => 'Technical Documentation',
            'debugging' => 'Debugging & Troubleshooting',
            'code_review' => 'Code Review',
            'planning' => 'Project Planning',
            'research' => 'Research & Investigation',
            'automation' => 'Task Automation',
        ];
    }

    /**
     * Boot method for model events
     */
    protected static function boot(): void
    {
        parent::boot();

        static::creating(function ($template) {
            if (empty($template->slug)) {
                $template->slug = \Illuminate\Support\Str::slug($template->name);
            }

            // Extract variables from template content
            if (empty($template->variables)) {
                $template->variables = $template->getVariablesFromContent();
            }
        });

        static::updating(function ($template) {
            if ($template->isDirty('name') && empty($template->slug)) {
                $template->slug = \Illuminate\Support\Str::slug($template->name);
            }

            // Update variables if content changed
            if ($template->isDirty('template_content')) {
                $template->variables = $template->getVariablesFromContent();
            }
        });
    }
}
