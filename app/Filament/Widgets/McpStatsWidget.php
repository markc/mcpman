<?php

namespace App\Filament\Widgets;

use App\Models\ApiKey;
use App\Models\Dataset;
use App\Models\Document;
use App\Models\McpConnection;
use Filament\Support\Enums\Heroicon;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Cache;

class McpStatsWidget extends BaseWidget
{
    protected static ?int $sort = -1;

    // Cache stats for 5 minutes to improve performance
    protected int $cacheInterval = 300;

    protected ?string $pollingInterval = '5m';

    protected function getStats(): array
    {
        return Cache::remember('mcp_stats_widget', $this->cacheInterval, function () {
            // Get base counts
            $totalConnections = McpConnection::count();
            $activeConnections = McpConnection::where('status', 'active')->count();
            $totalDatasets = Dataset::count();
            $totalDocuments = Document::count();
            $activeApiKeys = ApiKey::where('is_active', true)->count();

            // Get weekly counts for trends
            $weeklyDatasets = Dataset::where('created_at', '>=', now()->subWeek())->count();
            $weeklyDocuments = Document::where('created_at', '>=', now()->subWeek())->count();
            $weeklyConnections = McpConnection::where('created_at', '>=', now()->subWeek())->count();

            // Calculate growth percentages
            $connectionGrowth = $totalConnections > 0 ? round(($weeklyConnections / $totalConnections) * 100, 1) : 0;
            $datasetGrowth = $totalDatasets > 0 ? round(($weeklyDatasets / $totalDatasets) * 100, 1) : 0;
            $documentGrowth = $totalDocuments > 0 ? round(($weeklyDocuments / $totalDocuments) * 100, 1) : 0;

            return [
                Stat::make('MCP Connections', $totalConnections)
                    ->description($activeConnections.' active'.($weeklyConnections > 0 ? " • +{$weeklyConnections} this week" : ''))
                    ->descriptionIcon($connectionGrowth > 0 ? Heroicon::MINI_ARROW_TRENDING_UP : Heroicon::MINI_SIGNAL)
                    ->color($activeConnections > 0 ? 'success' : 'warning')
                    ->chart($this->getConnectionTrend()),

                Stat::make('Datasets', $totalDatasets)
                    ->description($weeklyDatasets.' this week'.($datasetGrowth > 0 ? " • +{$datasetGrowth}% growth" : ''))
                    ->descriptionIcon($datasetGrowth > 0 ? Heroicon::MINI_ARROW_TRENDING_UP : Heroicon::MINI_TABLE_CELLS)
                    ->color('info')
                    ->chart($this->getDatasetTrend()),

                Stat::make('Documents', $totalDocuments)
                    ->description($weeklyDocuments.' this week'.($documentGrowth > 0 ? " • +{$documentGrowth}% growth" : ''))
                    ->descriptionIcon($documentGrowth > 0 ? Heroicon::MINI_ARROW_TRENDING_UP : Heroicon::MINI_DOCUMENT_TEXT)
                    ->color('primary')
                    ->chart($this->getDocumentTrend()),

                Stat::make('Active API Keys', $activeApiKeys)
                    ->description($this->getApiKeyDescription($activeApiKeys))
                    ->descriptionIcon(Heroicon::MINI_KEY)
                    ->color($activeApiKeys > 0 ? 'warning' : 'gray'),
            ];
        });
    }

    private function getConnectionTrend(): array
    {
        return Cache::remember('connection_trend', 3600, function () {
            $data = [];
            for ($i = 6; $i >= 0; $i--) {
                $date = now()->subDays($i)->startOfDay();
                $count = McpConnection::where('created_at', '>=', $date)
                    ->where('created_at', '<', $date->copy()->addDay())
                    ->count();
                $data[] = $count;
            }

            return $data;
        });
    }

    private function getDatasetTrend(): array
    {
        return Cache::remember('dataset_trend', 3600, function () {
            $data = [];
            for ($i = 6; $i >= 0; $i--) {
                $date = now()->subDays($i)->startOfDay();
                $count = Dataset::where('created_at', '>=', $date)
                    ->where('created_at', '<', $date->copy()->addDay())
                    ->count();
                $data[] = $count;
            }

            return $data;
        });
    }

    private function getDocumentTrend(): array
    {
        return Cache::remember('document_trend', 3600, function () {
            $data = [];
            for ($i = 6; $i >= 0; $i--) {
                $date = now()->subDays($i)->startOfDay();
                $count = Document::where('created_at', '>=', $date)
                    ->where('created_at', '<', $date->copy()->addDay())
                    ->count();
                $data[] = $count;
            }

            return $data;
        });
    }

    private function getApiKeyDescription(int $activeKeys): string
    {
        $expiringKeys = ApiKey::where('is_active', true)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now()->addDays(30))
            ->count();

        if ($expiringKeys > 0) {
            return "Authentication tokens • {$expiringKeys} expiring soon";
        }

        return 'Authentication tokens';
    }
}
