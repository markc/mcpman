<?php

namespace App\Services;

use App\Models\Conversation;
use App\Models\ConversationMessage;
use App\Models\McpConnection;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class ConversationManager
{
    private const CACHE_TTL = 3600; // 1 hour

    public function createConversation(
        User $user,
        McpConnection $connection,
        ?string $title = null,
        array $context = [],
        array $settings = []
    ): Conversation {
        $conversation = Conversation::create([
            'title' => $title ?: $this->generateTitle($connection),
            'user_id' => $user->id,
            'mcp_connection_id' => $connection->id,
            'context' => $context,
            'settings' => array_merge($this->getDefaultSettings(), $settings),
            'status' => 'active',
            'started_at' => now(),
        ]);

        Log::info('Conversation created', [
            'conversation_id' => $conversation->id,
            'user_id' => $user->id,
            'connection_id' => $connection->id,
        ]);

        // Clear cache for user's conversations
        $this->clearUserConversationsCache($user->id);

        return $conversation;
    }

    public function findOrCreateActiveConversation(
        User $user,
        McpConnection $connection,
        ?string $sessionId = null
    ): Conversation {
        $query = Conversation::active()
            ->forUser($user->id)
            ->forConnection($connection->id);

        if ($sessionId) {
            $query->where('session_id', $sessionId);
        }

        $conversation = $query->latest('last_activity_at')->first();

        if (! $conversation) {
            $conversation = $this->createConversation($user, $connection);
        }

        return $conversation;
    }

    public function addMessage(
        Conversation $conversation,
        string $role,
        string $content,
        array $options = []
    ): ConversationMessage {
        $messageData = [
            'role' => $role,
            'content' => $content,
            'metadata' => $options['metadata'] ?? [],
            'context' => $options['context'] ?? [],
            'tool_name' => $options['tool_name'] ?? null,
            'tool_arguments' => $options['tool_arguments'] ?? null,
            'tool_result' => $options['tool_result'] ?? null,
            'sent_at' => $options['sent_at'] ?? now(),
        ];

        $message = $conversation->addMessage($messageData);

        // Update conversation context based on message
        $this->updateConversationContext($conversation, $message);

        Log::info('Message added to conversation', [
            'conversation_id' => $conversation->id,
            'message_id' => $message->id,
            'role' => $role,
            'content_length' => strlen($content),
        ]);

        // Clear relevant caches
        $this->clearConversationCache($conversation->id);
        $this->clearUserConversationsCache($conversation->user_id);

        return $message;
    }

    public function getConversationHistory(
        Conversation $conversation,
        ?int $limit = null,
        ?string $role = null
    ): Collection {
        $query = $conversation->messages()->inSequence();

        if ($role) {
            $query->byRole($role);
        }

        if ($limit) {
            $query->limit($limit);
        }

        return $query->get();
    }

    public function getUserConversations(
        User $user,
        ?string $status = null,
        int $limit = 50
    ): Collection {
        $cacheKey = "user_conversations_{$user->id}_{$status}_{$limit}";

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($user, $status, $limit) {
            $query = Conversation::forUser($user->id)
                ->with(['mcpConnection:id,name,transport_type'])
                ->latest('last_activity_at');

            if ($status) {
                $query->where('status', $status);
            }

            return $query->limit($limit)->get();
        });
    }

    public function getConversationStats(User $user): array
    {
        $cacheKey = "conversation_stats_{$user->id}";

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($user) {
            $conversations = Conversation::forUser($user->id)->get();

            $stats = [
                'total_conversations' => $conversations->count(),
                'active_conversations' => $conversations->where('status', 'active')->count(),
                'archived_conversations' => $conversations->where('status', 'archived')->count(),
                'total_messages' => $conversations->sum('message_count'),
                'average_messages_per_conversation' => 0,
                'total_duration_minutes' => 0,
                'most_used_connections' => [],
                'conversation_activity_by_day' => [],
                'tool_usage_summary' => [],
            ];

            if ($stats['total_conversations'] > 0) {
                $stats['average_messages_per_conversation'] = round(
                    $stats['total_messages'] / $stats['total_conversations'],
                    1
                );
            }

            // Calculate total duration
            foreach ($conversations as $conversation) {
                $stats['total_duration_minutes'] += $conversation->getDurationInMinutes();
            }

            // Most used connections
            $connectionUsage = $conversations->groupBy('mcp_connection_id')->map->count()->sortDesc();
            foreach ($connectionUsage->take(5) as $connectionId => $count) {
                $connection = McpConnection::find($connectionId);
                if ($connection) {
                    $stats['most_used_connections'][] = [
                        'name' => $connection->name,
                        'count' => $count,
                    ];
                }
            }

            // Activity by day (last 30 days)
            $activityByDay = $conversations
                ->where('last_activity_at', '>=', now()->subDays(30))
                ->groupBy(fn ($conv) => $conv->last_activity_at?->format('Y-m-d'))
                ->map->count();

            for ($i = 29; $i >= 0; $i--) {
                $date = now()->subDays($i)->format('Y-m-d');
                $stats['conversation_activity_by_day'][$date] = $activityByDay[$date] ?? 0;
            }

            // Tool usage summary
            $toolUsage = [];
            foreach ($conversations as $conversation) {
                $conversationTools = $conversation->getToolUsageStats();
                foreach ($conversationTools as $toolName => $usage) {
                    if (! isset($toolUsage[$toolName])) {
                        $toolUsage[$toolName] = ['calls' => 0, 'successes' => 0, 'errors' => 0];
                    }
                    $toolUsage[$toolName]['calls'] += $usage['calls'];
                    $toolUsage[$toolName]['successes'] += $usage['successes'];
                    $toolUsage[$toolName]['errors'] += $usage['errors'];
                }
            }

            $stats['tool_usage_summary'] = collect($toolUsage)
                ->sortByDesc('calls')
                ->take(10)
                ->toArray();

            return $stats;
        });
    }

    public function archiveConversation(Conversation $conversation): void
    {
        $conversation->archive();

        Log::info('Conversation archived', [
            'conversation_id' => $conversation->id,
            'user_id' => $conversation->user_id,
        ]);

        $this->clearConversationCache($conversation->id);
        $this->clearUserConversationsCache($conversation->user_id);
    }

    public function endConversation(Conversation $conversation): void
    {
        $conversation->end();

        Log::info('Conversation ended', [
            'conversation_id' => $conversation->id,
            'user_id' => $conversation->user_id,
            'duration_minutes' => $conversation->getDurationInMinutes(),
        ]);

        $this->clearConversationCache($conversation->id);
        $this->clearUserConversationsCache($conversation->user_id);
    }

    public function resumeConversation(Conversation $conversation): void
    {
        $conversation->resume();

        Log::info('Conversation resumed', [
            'conversation_id' => $conversation->id,
            'user_id' => $conversation->user_id,
        ]);

        $this->clearConversationCache($conversation->id);
        $this->clearUserConversationsCache($conversation->user_id);
    }

    public function deleteConversation(Conversation $conversation): void
    {
        $conversationId = $conversation->id;
        $userId = $conversation->user_id;

        $conversation->delete();

        Log::info('Conversation deleted', [
            'conversation_id' => $conversationId,
            'user_id' => $userId,
        ]);

        $this->clearConversationCache($conversationId);
        $this->clearUserConversationsCache($userId);
    }

    public function exportConversation(Conversation $conversation): array
    {
        return $conversation->exportConversation();
    }

    public function searchConversations(
        User $user,
        string $query,
        array $filters = []
    ): Collection {
        $builder = Conversation::forUser($user->id)
            ->with(['mcpConnection:id,name,transport_type'])
            ->where('title', 'like', "%{$query}%");

        // Apply filters
        if (isset($filters['status'])) {
            $builder->where('status', $filters['status']);
        }

        if (isset($filters['connection_id'])) {
            $builder->where('mcp_connection_id', $filters['connection_id']);
        }

        if (isset($filters['date_from'])) {
            $builder->where('started_at', '>=', $filters['date_from']);
        }

        if (isset($filters['date_to'])) {
            $builder->where('started_at', '<=', $filters['date_to']);
        }

        // Also search in message content
        $builder->orWhereHas('messages', function ($messageQuery) use ($query) {
            $messageQuery->where('content', 'like', "%{$query}%");
        });

        return $builder->latest('last_activity_at')->limit(100)->get();
    }

    public function getConversationContext(Conversation $conversation): array
    {
        $cacheKey = "conversation_context_{$conversation->id}";

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($conversation) {
            $messages = $conversation->messages()->inSequence()->get();

            $context = [
                'conversation_summary' => $this->generateConversationSummary($messages),
                'active_tools' => $this->getActiveTools($messages),
                'key_topics' => $this->extractKeyTopics($messages),
                'context_variables' => $conversation->getContext(),
                'settings' => $conversation->getSetting(),
            ];

            return $context;
        });
    }

    private function generateTitle(McpConnection $connection): string
    {
        return "Chat with {$connection->name} - ".now()->format('M j, g:i A');
    }

    private function getDefaultSettings(): array
    {
        return [
            'auto_save' => true,
            'context_window' => 50, // Number of messages to keep in context
            'preserve_tools' => true,
            'smart_summarization' => true,
        ];
    }

    private function updateConversationContext(Conversation $conversation, ConversationMessage $message): void
    {
        // Extract and store relevant context from the message
        if ($message->isToolCall()) {
            $conversation->setContext('last_tool_used', $message->tool_name);
            $conversation->setContext('last_tool_arguments', $message->tool_arguments);
        }

        if ($message->isAssistantMessage()) {
            $conversation->setContext('last_assistant_response_at', $message->sent_at->toISOString());
        }

        // Update activity timestamp
        $conversation->updateActivity();
    }

    private function generateConversationSummary(Collection $messages): string
    {
        $messageCount = $messages->count();
        $userMessages = $messages->where('role', 'user')->count();
        $toolCalls = $messages->where('role', 'tool_call')->count();

        $summary = "Conversation with {$messageCount} messages";

        if ($userMessages > 0) {
            $summary .= ", {$userMessages} user inputs";
        }

        if ($toolCalls > 0) {
            $summary .= ", {$toolCalls} tool calls";
        }

        return $summary;
    }

    private function getActiveTools(Collection $messages): array
    {
        return $messages
            ->where('role', 'tool_call')
            ->pluck('tool_name')
            ->unique()
            ->values()
            ->toArray();
    }

    private function extractKeyTopics(Collection $messages): array
    {
        // Simple topic extraction based on frequently mentioned words
        $allContent = $messages
            ->where('role', 'user')
            ->pluck('content')
            ->implode(' ');

        $words = str_word_count(strtolower($allContent), 1);
        $commonWords = ['the', 'and', 'or', 'but', 'in', 'on', 'at', 'to', 'for', 'of', 'with', 'by', 'a', 'an', 'is', 'are', 'was', 'were', 'be', 'been', 'have', 'has', 'had', 'do', 'does', 'did', 'will', 'would', 'should', 'could', 'can', 'may', 'might', 'must', 'i', 'you', 'he', 'she', 'it', 'we', 'they', 'this', 'that', 'these', 'those'];

        $filteredWords = array_filter($words, function ($word) use ($commonWords) {
            return strlen($word) > 3 && ! in_array($word, $commonWords);
        });

        $wordCounts = array_count_values($filteredWords);
        arsort($wordCounts);

        return array_keys(array_slice($wordCounts, 0, 10));
    }

    private function clearConversationCache(int $conversationId): void
    {
        Cache::forget("conversation_context_{$conversationId}");
    }

    private function clearUserConversationsCache(int $userId): void
    {
        Cache::forget("conversation_stats_{$userId}");

        // Clear all user conversation caches (we don't know all possible combinations)
        $statuses = ['active', 'archived', 'ended', null];
        $limits = [10, 25, 50, 100];

        foreach ($statuses as $status) {
            foreach ($limits as $limit) {
                Cache::forget("user_conversations_{$userId}_{$status}_{$limit}");
            }
        }
    }
}
