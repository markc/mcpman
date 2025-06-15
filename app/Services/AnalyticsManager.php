<?php

namespace App\Services;

use App\Models\Conversation;
use App\Models\McpAnalytics;
use App\Models\McpConnection;
use App\Models\Tool;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class AnalyticsManager
{
    /**
     * Record an analytics event
     */
    public function recordEvent(array $data): McpAnalytics
    {
        return McpAnalytics::recordEvent($data);
    }

    /**
     * Record conversation event
     */
    public function recordConversationEvent(
        string $eventType,
        User $user,
        McpConnection $connection,
        ?Conversation $conversation = null,
        array $eventData = [],
        ?int $durationMs = null,
        bool $success = true,
        ?string $errorMessage = null
    ): McpAnalytics {
        return $this->recordEvent([
            'event_type' => $eventType,
            'user_id' => $user->id,
            'connection_id' => $connection->id,
            'conversation_id' => $conversation?->id,
            'event_data' => $eventData,
            'duration_ms' => $durationMs,
            'success' => $success,
            'error_message' => $errorMessage,
        ]);
    }

    /**
     * Record tool execution event
     */
    public function recordToolExecution(
        Tool $tool,
        User $user,
        McpConnection $connection,
        array $arguments = [],
        ?int $durationMs = null,
        bool $success = true,
        ?string $errorMessage = null
    ): McpAnalytics {
        return $this->recordEvent([
            'event_type' => 'tool_execution',
            'user_id' => $user->id,
            'connection_id' => $connection->id,
            'tool_id' => $tool->id,
            'event_data' => [
                'tool_name' => $tool->name,
                'arguments' => $arguments,
            ],
            'duration_ms' => $durationMs,
            'success' => $success,
            'error_message' => $errorMessage,
        ]);
    }

    /**
     * Get analytics dashboard data
     */
    public function getDashboardData(int $days = 30): array
    {
        $cacheKey = "analytics_dashboard_{$days}d";

        return Cache::remember($cacheKey, 300, function () use ($days) {
            $startDate = now()->subDays($days);

            return [
                'summary' => $this->getSummaryMetrics($startDate),
                'trends' => $this->getTrendData($startDate),
                'top_connections' => $this->getTopConnections($startDate),
                'top_tools' => $this->getTopTools($startDate),
                'error_analysis' => $this->getErrorAnalysis($startDate),
                'performance_metrics' => $this->getPerformanceMetrics($startDate),
                'user_activity' => $this->getUserActivity($startDate),
            ];
        });
    }

    /**
     * Get summary metrics
     */
    protected function getSummaryMetrics(Carbon $startDate): array
    {
        return [
            'total_events' => McpAnalytics::where('created_at', '>=', $startDate)->count(),
            'successful_events' => McpAnalytics::successful()->where('created_at', '>=', $startDate)->count(),
            'failed_events' => McpAnalytics::failed()->where('created_at', '>=', $startDate)->count(),
            'unique_users' => McpAnalytics::where('created_at', '>=', $startDate)
                ->distinct('user_id')
                ->count('user_id'),
            'active_connections' => McpAnalytics::where('created_at', '>=', $startDate)
                ->distinct('connection_id')
                ->count('connection_id'),
            'tools_used' => McpAnalytics::where('created_at', '>=', $startDate)
                ->whereNotNull('tool_id')
                ->distinct('tool_id')
                ->count('tool_id'),
            'average_response_time' => McpAnalytics::where('created_at', '>=', $startDate)
                ->whereNotNull('duration_ms')
                ->avg('duration_ms'),
            'success_rate' => $this->calculateSuccessRate($startDate),
        ];
    }

    /**
     * Get trend data for charts
     */
    protected function getTrendData(Carbon $startDate): array
    {
        $dailyStats = McpAnalytics::selectRaw('
            DATE(created_at) as date,
            COUNT(*) as total_events,
            SUM(CASE WHEN success = 1 THEN 1 ELSE 0 END) as successful_events,
            SUM(CASE WHEN success = 0 THEN 1 ELSE 0 END) as failed_events,
            AVG(duration_ms) as avg_duration
        ')
            ->where('created_at', '>=', $startDate)
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        return [
            'daily_events' => $dailyStats->pluck('total_events', 'date'),
            'daily_success_rate' => $dailyStats->mapWithKeys(function ($item) {
                $rate = $item->total_events > 0 ?
                    ($item->successful_events / $item->total_events) * 100 : 0;

                return [$item->date => round($rate, 2)];
            }),
            'daily_avg_duration' => $dailyStats->pluck('avg_duration', 'date'),
        ];
    }

    /**
     * Get top connections by usage
     */
    protected function getTopConnections(Carbon $startDate, int $limit = 10): Collection
    {
        return McpAnalytics::selectRaw('
            connection_id,
            COUNT(*) as event_count,
            AVG(duration_ms) as avg_duration,
            SUM(CASE WHEN success = 1 THEN 1 ELSE 0 END) as successful_events,
            SUM(CASE WHEN success = 0 THEN 1 ELSE 0 END) as failed_events
        ')
            ->with('connection:id,name')
            ->where('created_at', '>=', $startDate)
            ->whereNotNull('connection_id')
            ->groupBy('connection_id')
            ->orderBy('event_count', 'desc')
            ->limit($limit)
            ->get()
            ->map(function ($item) {
                $item->success_rate = $item->event_count > 0 ?
                    ($item->successful_events / $item->event_count) * 100 : 0;

                return $item;
            });
    }

    /**
     * Get top tools by usage
     */
    protected function getTopTools(Carbon $startDate, int $limit = 10): Collection
    {
        return McpAnalytics::selectRaw('
            tool_id,
            COUNT(*) as usage_count,
            AVG(duration_ms) as avg_duration,
            SUM(CASE WHEN success = 1 THEN 1 ELSE 0 END) as successful_uses,
            SUM(CASE WHEN success = 0 THEN 1 ELSE 0 END) as failed_uses
        ')
            ->with('tool:id,name,category')
            ->where('created_at', '>=', $startDate)
            ->whereNotNull('tool_id')
            ->groupBy('tool_id')
            ->orderBy('usage_count', 'desc')
            ->limit($limit)
            ->get()
            ->map(function ($item) {
                $item->success_rate = $item->usage_count > 0 ?
                    ($item->successful_uses / $item->usage_count) * 100 : 0;

                return $item;
            });
    }

    /**
     * Get error analysis
     */
    protected function getErrorAnalysis(Carbon $startDate): array
    {
        $errorsByType = McpAnalytics::selectRaw('
            event_type,
            COUNT(*) as error_count,
            error_message
        ')
            ->where('created_at', '>=', $startDate)
            ->where('success', false)
            ->groupBy('event_type', 'error_message')
            ->orderBy('error_count', 'desc')
            ->limit(20)
            ->get();

        $errorsByConnection = McpAnalytics::selectRaw('
            connection_id,
            COUNT(*) as error_count
        ')
            ->with('connection:id,name')
            ->where('created_at', '>=', $startDate)
            ->where('success', false)
            ->whereNotNull('connection_id')
            ->groupBy('connection_id')
            ->orderBy('error_count', 'desc')
            ->limit(10)
            ->get();

        return [
            'errors_by_type' => $errorsByType,
            'errors_by_connection' => $errorsByConnection,
            'common_errors' => $errorsByType->take(5),
        ];
    }

    /**
     * Get performance metrics
     */
    protected function getPerformanceMetrics(Carbon $startDate): array
    {
        $slowRequests = McpAnalytics::slowRequests()
            ->where('created_at', '>=', $startDate)
            ->count();

        $totalRequests = McpAnalytics::whereNotNull('duration_ms')
            ->where('created_at', '>=', $startDate)
            ->count();

        $percentileData = McpAnalytics::selectRaw('
            MIN(duration_ms) as min_duration,
            MAX(duration_ms) as max_duration,
            AVG(duration_ms) as avg_duration
        ')
            ->where('created_at', '>=', $startDate)
            ->whereNotNull('duration_ms')
            ->first();

        return [
            'slow_requests' => $slowRequests,
            'slow_request_percentage' => $totalRequests > 0 ?
                ($slowRequests / $totalRequests) * 100 : 0,
            'min_duration' => $percentileData->min_duration ?? 0,
            'max_duration' => $percentileData->max_duration ?? 0,
            'avg_duration' => $percentileData->avg_duration ?? 0,
            'performance_by_event_type' => $this->getPerformanceByEventType($startDate),
        ];
    }

    /**
     * Get performance by event type
     */
    protected function getPerformanceByEventType(Carbon $startDate): Collection
    {
        return McpAnalytics::selectRaw('
            event_type,
            COUNT(*) as event_count,
            AVG(duration_ms) as avg_duration,
            MIN(duration_ms) as min_duration,
            MAX(duration_ms) as max_duration
        ')
            ->where('created_at', '>=', $startDate)
            ->whereNotNull('duration_ms')
            ->groupBy('event_type')
            ->orderBy('avg_duration', 'desc')
            ->get();
    }

    /**
     * Get user activity metrics
     */
    protected function getUserActivity(Carbon $startDate): array
    {
        $activeUsers = McpAnalytics::selectRaw('
            user_id,
            COUNT(*) as activity_count,
            COUNT(DISTINCT DATE(created_at)) as active_days,
            MAX(created_at) as last_activity
        ')
            ->with('user:id,name,email')
            ->where('created_at', '>=', $startDate)
            ->whereNotNull('user_id')
            ->groupBy('user_id')
            ->orderBy('activity_count', 'desc')
            ->limit(20)
            ->get();

        $usersByHour = McpAnalytics::selectRaw('
            HOUR(created_at) as hour,
            COUNT(DISTINCT user_id) as unique_users,
            COUNT(*) as total_events
        ')
            ->where('created_at', '>=', $startDate)
            ->whereNotNull('user_id')
            ->groupBy('hour')
            ->orderBy('hour')
            ->get();

        return [
            'most_active_users' => $activeUsers,
            'activity_by_hour' => $usersByHour,
            'peak_hour' => $usersByHour->sortByDesc('unique_users')->first()?->hour,
        ];
    }

    /**
     * Calculate success rate
     */
    protected function calculateSuccessRate(Carbon $startDate): float
    {
        $total = McpAnalytics::where('created_at', '>=', $startDate)->count();
        $successful = McpAnalytics::successful()->where('created_at', '>=', $startDate)->count();

        return $total > 0 ? ($successful / $total) * 100 : 0;
    }

    /**
     * Get real-time metrics
     */
    public function getRealTimeMetrics(): array
    {
        $cacheKey = 'realtime_metrics';

        return Cache::remember($cacheKey, 30, function () {
            $last24h = now()->subDay();
            $lastHour = now()->subHour();

            return [
                'events_last_hour' => McpAnalytics::where('created_at', '>=', $lastHour)->count(),
                'errors_last_hour' => McpAnalytics::failed()->where('created_at', '>=', $lastHour)->count(),
                'active_users_last_hour' => McpAnalytics::where('created_at', '>=', $lastHour)
                    ->distinct('user_id')
                    ->count('user_id'),
                'avg_response_time_last_hour' => McpAnalytics::where('created_at', '>=', $lastHour)
                    ->whereNotNull('duration_ms')
                    ->avg('duration_ms'),
                'current_success_rate' => $this->calculateSuccessRate($last24h),
            ];
        });
    }

    /**
     * Clean old analytics data
     */
    public function cleanOldData(int $daysToKeep = 90): int
    {
        $cutoffDate = now()->subDays($daysToKeep);

        return McpAnalytics::where('created_at', '<', $cutoffDate)->delete();
    }

    /**
     * Export analytics data
     */
    public function exportData(Carbon $startDate, Carbon $endDate, array $eventTypes = []): Collection
    {
        $query = McpAnalytics::with(['user', 'connection', 'tool', 'conversation'])
            ->whereBetween('created_at', [$startDate, $endDate]);

        if (! empty($eventTypes)) {
            $query->whereIn('event_type', $eventTypes);
        }

        return $query->orderBy('created_at')->get();
    }
}
