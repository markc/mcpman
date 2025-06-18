<?php

namespace App\Filament\Widgets;

use App\Services\LogMonitoringService;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class LogFileStatusWidget extends BaseWidget
{
    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = [
        'md' => 1,
        'xl' => 1,
    ];

    protected function getStats(): array
    {
        $logMonitor = app(LogMonitoringService::class);
        $stats = $logMonitor->getMonitoringStats();

        return [
            Stat::make('Log File', $stats['log_file_exists'] ? 'Found' : 'Missing')
                ->description($stats['log_file_exists'] ? 'Log file is accessible' : 'Log file not found')
                ->descriptionIcon($stats['log_file_exists'] ? Heroicon::OutlinedCheckCircle : Heroicon::OutlinedXCircle)
                ->color($stats['log_file_exists'] ? 'success' : 'danger'),

            Stat::make('Log Size', $this->formatFileSize($stats['log_file_size'] ?? 0))
                ->description('Current log file size')
                ->descriptionIcon(Heroicon::OutlinedChartBar)
                ->color('primary'),
        ];
    }

    private function formatFileSize(int $bytes): string
    {
        if ($bytes == 0) {
            return '0 B';
        }

        $units = ['B', 'KB', 'MB', 'GB'];
        $base = log($bytes, 1024);

        return round(pow(1024, $base - floor($base)), 2).' '.$units[floor($base)];
    }
}
