<?php

namespace App\Filament\Widgets;

use App\Models\ApiKey;
use App\Models\Dataset;
use App\Models\Document;
use App\Models\McpConnection;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class McpStatsWidget extends BaseWidget
{
    protected static ?int $sort = -1;

    protected function getStats(): array
    {
        return [
            Stat::make('Total Connections', McpConnection::count())
                ->description(McpConnection::where('status', 'active')->count().' active')
                ->descriptionIcon('heroicon-m-arrow-trending-up')
                ->color('blue'),

            Stat::make('Datasets', Dataset::count())
                ->description(Dataset::where('created_at', '>=', now()->subWeek())->count().' this week')
                ->descriptionIcon('heroicon-m-table-cells')
                ->color('green'),

            Stat::make('Documents', Document::count())
                ->description(Document::where('created_at', '>=', now()->subWeek())->count().' this week')
                ->descriptionIcon('heroicon-m-document-text')
                ->color('purple'),

            Stat::make('Active API Keys', ApiKey::where('is_active', true)->count())
                ->description('Authentication tokens')
                ->descriptionIcon('heroicon-m-key')
                ->color('yellow'),
        ];
    }
}
