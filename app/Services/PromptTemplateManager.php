<?php

namespace App\Services;

use App\Models\Conversation;
use App\Models\PromptTemplate;
use App\Models\PromptTemplateUsage;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class PromptTemplateManager
{
    /**
     * Get available templates for a user
     */
    public function getAvailableTemplates(User $user, ?string $category = null): Collection
    {
        $cacheKey = "user_templates_{$user->id}".($category ? "_{$category}" : '');

        return Cache::remember($cacheKey, 300, function () use ($user, $category) {
            $query = PromptTemplate::where(function ($query) use ($user) {
                $query->where('user_id', $user->id)
                    ->orWhere('is_public', true);
            })->where('is_active', true);

            if ($category) {
                $query->where('category', $category);
            }

            return $query->orderBy('usage_count', 'desc')
                ->orderBy('average_rating', 'desc')
                ->get();
        });
    }

    /**
     * Render a template with variables
     */
    public function renderTemplate(PromptTemplate $template, array $variables = []): string
    {
        $startTime = microtime(true);

        try {
            $renderedContent = $template->renderTemplate($variables);

            // Record successful usage
            $this->recordUsage($template, auth()->user(), $variables, $renderedContent, true, $startTime);

            return $renderedContent;
        } catch (\Exception $e) {
            Log::error('Template rendering failed', [
                'template_id' => $template->id,
                'variables' => $variables,
                'error' => $e->getMessage(),
            ]);

            // Record failed usage
            $this->recordUsage($template, auth()->user(), $variables, '', false, $startTime);

            throw $e;
        }
    }

    /**
     * Create a new template
     */
    public function createTemplate(array $data, User $user): PromptTemplate
    {
        $data['user_id'] = $user->id;

        return PromptTemplate::create($data);
    }

    /**
     * Duplicate an existing template
     */
    public function duplicateTemplate(PromptTemplate $template, User $user, ?string $newName = null): PromptTemplate
    {
        if (! $template->isUsableByUser($user)) {
            throw new \Exception('You do not have permission to duplicate this template.');
        }

        return $template->duplicate($user, $newName);
    }

    /**
     * Get popular templates
     */
    public function getPopularTemplates(int $limit = 10): Collection
    {
        return Cache::remember('popular_templates', 3600, function () use ($limit) {
            return PromptTemplate::active()
                ->public()
                ->popular($limit)
                ->get();
        });
    }

    /**
     * Get recently used templates for a user
     */
    public function getRecentlyUsedTemplates(User $user, int $limit = 5): Collection
    {
        return Cache::remember("recent_templates_{$user->id}", 300, function () use ($user, $limit) {
            return PromptTemplate::whereHas('templateUsages', function ($query) use ($user) {
                $query->where('user_id', $user->id);
            })
                ->with(['templateUsages' => function ($query) use ($user) {
                    $query->where('user_id', $user->id)
                        ->latest()
                        ->limit(1);
                }])
                ->get()
                ->sortByDesc(function ($template) {
                    return $template->templateUsages->first()?->created_at;
                })
                ->take($limit);
        });
    }

    /**
     * Search templates
     */
    public function searchTemplates(string $query, User $user, array $filters = []): Collection
    {
        $templateQuery = PromptTemplate::where(function ($q) use ($user) {
            $q->where('user_id', $user->id)
                ->orWhere('is_public', true);
        })
            ->where('is_active', true)
            ->where(function ($q) use ($query) {
                $q->where('name', 'like', "%{$query}%")
                    ->orWhere('description', 'like', "%{$query}%")
                    ->orWhere('template_content', 'like', "%{$query}%")
                    ->orWhereJsonContains('tags', $query);
            });

        // Apply filters
        if (isset($filters['category']) && $filters['category'] !== 'all') {
            $templateQuery->where('category', $filters['category']);
        }

        if (isset($filters['min_rating'])) {
            $templateQuery->where('average_rating', '>=', $filters['min_rating']);
        }

        if (isset($filters['min_usage'])) {
            $templateQuery->where('usage_count', '>=', $filters['min_usage']);
        }

        return $templateQuery->orderBy('usage_count', 'desc')
            ->orderBy('average_rating', 'desc')
            ->get();
    }

    /**
     * Get template statistics
     */
    public function getTemplateStatistics(): array
    {
        return Cache::remember('template_statistics', 3600, function () {
            return [
                'total_templates' => PromptTemplate::count(),
                'active_templates' => PromptTemplate::active()->count(),
                'public_templates' => PromptTemplate::public()->count(),
                'total_usages' => PromptTemplateUsage::count(),
                'successful_usages' => PromptTemplateUsage::successful()->count(),
                'average_rating' => PromptTemplateUsage::withRating()->avg('rating'),
                'most_popular' => PromptTemplate::popular(5)->get(['id', 'name', 'usage_count']),
                'categories' => PromptTemplate::selectRaw('category, COUNT(*) as count')
                    ->groupBy('category')
                    ->pluck('count', 'category'),
                'recent_activity' => PromptTemplateUsage::recentUsage(7)->count(),
            ];
        });
    }

    /**
     * Rate a template usage
     */
    public function rateTemplate(PromptTemplate $template, User $user, float $rating, ?string $feedback = null): void
    {
        if ($rating < 1 || $rating > 5) {
            throw new \InvalidArgumentException('Rating must be between 1 and 5');
        }

        // Find the most recent usage by this user
        $usage = PromptTemplateUsage::where('prompt_template_id', $template->id)
            ->where('user_id', $user->id)
            ->latest()
            ->first();

        if ($usage) {
            $usage->update([
                'rating' => $rating,
                'feedback' => $feedback,
            ]);
        } else {
            // Create a new usage record for rating
            PromptTemplateUsage::create([
                'prompt_template_id' => $template->id,
                'user_id' => $user->id,
                'rendered_content' => '',
                'rating' => $rating,
                'feedback' => $feedback,
                'success' => true,
            ]);
        }

        // Clear cache
        $this->clearUserCache($user);
    }

    /**
     * Get template recommendations for a user
     */
    public function getRecommendations(User $user, int $limit = 5): Collection
    {
        return Cache::remember("recommendations_{$user->id}", 1800, function () use ($user, $limit) {
            // Get user's template usage patterns
            $userCategories = PromptTemplateUsage::where('user_id', $user->id)
                ->with('promptTemplate')
                ->get()
                ->pluck('promptTemplate.category')
                ->countBy()
                ->sortDesc()
                ->keys()
                ->take(3);

            // Find popular templates in those categories that user hasn't used
            $usedTemplateIds = PromptTemplateUsage::where('user_id', $user->id)
                ->pluck('prompt_template_id');

            return PromptTemplate::whereIn('category', $userCategories)
                ->whereNotIn('id', $usedTemplateIds)
                ->where(function ($query) use ($user) {
                    $query->where('is_public', true)
                        ->orWhere('user_id', $user->id);
                })
                ->where('is_active', true)
                ->where('usage_count', '>', 0)
                ->orderBy('average_rating', 'desc')
                ->orderBy('usage_count', 'desc')
                ->limit($limit)
                ->get();
        });
    }

    /**
     * Record template usage
     */
    protected function recordUsage(
        PromptTemplate $template,
        User $user,
        array $variables,
        string $renderedContent,
        bool $success,
        float $startTime,
        ?Conversation $conversation = null
    ): void {
        $executionTime = round((microtime(true) - $startTime) * 1000);

        PromptTemplateUsage::create([
            'prompt_template_id' => $template->id,
            'user_id' => $user->id,
            'conversation_id' => $conversation?->id,
            'variables_used' => $variables,
            'rendered_content' => $renderedContent,
            'execution_time_ms' => $executionTime,
            'success' => $success,
        ]);

        // Clear relevant caches
        $this->clearUserCache($user);
    }

    /**
     * Clear user-specific caches
     */
    protected function clearUserCache(User $user): void
    {
        Cache::forget("user_templates_{$user->id}");
        Cache::forget("recent_templates_{$user->id}");
        Cache::forget("recommendations_{$user->id}");
        Cache::forget('template_statistics');
        Cache::forget('popular_templates');
    }

    /**
     * Seed default templates
     */
    public function seedDefaultTemplates(User $user): void
    {
        $defaultTemplates = [
            [
                'name' => 'Code Review Request',
                'category' => 'development',
                'description' => 'Template for requesting code reviews with specific focus areas',
                'template_content' => 'Please review this {{language}} code for {{focus_areas}}. Pay special attention to {{specific_concerns}}. Here is the code:\n\n{{code}}',
                'is_public' => true,
                'tags' => ['code-review', 'development', 'quality'],
            ],
            [
                'name' => 'Bug Report Analysis',
                'category' => 'debugging',
                'description' => 'Template for analyzing and troubleshooting bug reports',
                'template_content' => 'Analyze this bug report and provide debugging steps:\n\nBug Description: {{bug_description}}\nSteps to Reproduce: {{reproduction_steps}}\nExpected Behavior: {{expected_behavior}}\nActual Behavior: {{actual_behavior}}\nEnvironment: {{environment}}',
                'is_public' => true,
                'tags' => ['debugging', 'analysis', 'troubleshooting'],
            ],
            [
                'name' => 'Data Analysis Request',
                'category' => 'data_analysis',
                'description' => 'Template for requesting data analysis and insights',
                'template_content' => 'Analyze the following {{data_type}} data and provide insights about {{analysis_goals}}. Focus on {{key_metrics}} and identify any {{patterns_to_find}}. Data: {{data}}',
                'is_public' => true,
                'tags' => ['data', 'analysis', 'insights'],
            ],
            [
                'name' => 'Feature Planning',
                'category' => 'planning',
                'description' => 'Template for planning new software features',
                'template_content' => 'Help me plan a new feature: {{feature_name}}\n\nGoals: {{feature_goals}}\nTarget Users: {{target_users}}\nConstraints: {{constraints}}\n\nPlease provide:\n1. Implementation approach\n2. Technical considerations\n3. Potential challenges\n4. Timeline estimate',
                'is_public' => true,
                'tags' => ['planning', 'features', 'development'],
            ],
            [
                'name' => 'API Documentation Generator',
                'category' => 'technical',
                'description' => 'Template for generating API documentation',
                'template_content' => 'Generate documentation for this {{api_type}} API endpoint:\n\nEndpoint: {{endpoint}}\nMethod: {{method}}\nDescription: {{description}}\nParameters: {{parameters}}\nResponse: {{response_format}}\n\nPlease provide complete documentation including examples.',
                'is_public' => true,
                'tags' => ['documentation', 'api', 'technical'],
            ],
        ];

        foreach ($defaultTemplates as $templateData) {
            $templateData['user_id'] = $user->id;
            PromptTemplate::create($templateData);
        }
    }
}
