<?php

namespace App\Filament\Widgets;

use App\Services\LogMonitoringService;
use Filament\Support\Enums\Heroicon;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class SystemSupportWidget extends BaseWidget
{
    protected static ?int $sort = 1;

    // Disable Livewire caching to force fresh data
    public $listeners = [];

    protected ?string $pollingInterval = '10s';

    protected int|string|array $columnSpan = [
        'md' => 1,
        'xl' => 1,
    ];

    protected function getStats(): array
    {
        $logMonitor = app(LogMonitoringService::class);
        $stats = $logMonitor->getMonitoringStats();

        // Debug: Log what the widget actually receives
        \Log::info('SystemSupportWidget received stats', [
            'monitoring_supported' => $stats['monitoring_supported'],
            'patterns_configured' => $stats['patterns_configured'],
            'log_file_exists' => $stats['log_file_exists'],
            'log_file_size' => $stats['log_file_size'],
        ]);

        return [
            Stat::make('System Support', $stats['monitoring_supported'] ? 'Available' : 'Unavailable')
                ->description($stats['monitoring_supported'] ? 'Log monitoring is available' : 'Log monitoring unavailable')
                ->descriptionIcon($stats['monitoring_supported'] ? Heroicon::OUTLINE_CHECK_CIRCLE : Heroicon::OUTLINE_X_CIRCLE)
                ->color($stats['monitoring_supported'] ? 'success' : 'danger'),

            Stat::make('Error Patterns', $stats['patterns_configured'] ?? 11)
                ->description('Detection patterns configured')
                ->descriptionIcon(Heroicon::OUTLINE_PUZZLE_PIECE)
                ->color('info'),
        ];
    }
}
