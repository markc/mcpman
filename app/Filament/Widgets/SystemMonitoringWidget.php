<?php

namespace App\Filament\Widgets;

use App\Services\LogMonitoringService;
use App\Services\ProcessManager;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class SystemMonitoringWidget extends BaseWidget
{
    protected static ?int $sort = 1;

    protected ?string $pollingInterval = '2s';

    protected int|string|array $columnSpan = [
        'md' => 1,
        'xl' => 1,
    ];

    protected function getStats(): array
    {
        $logMonitor = app(LogMonitoringService::class);
        $processManager = app(ProcessManager::class);
        $stats = $logMonitor->getMonitoringStats();

        // Check if monitoring is actually running using ProcessManager
        $isProcessRunning = $processManager->isProcessRunning('mcp-log-monitoring');

        $systemStatus = $stats['monitoring_supported']
            ? ($isProcessRunning ? 'Active' : 'Ready')
            : 'Unavailable';

        $systemDescription = match ($systemStatus) {
            'Active' => 'MCP log monitoring is running',
            'Ready' => 'MCP system ready, not monitoring',
            default => 'MCP log monitoring unavailable'
        };

        $systemColor = match ($systemStatus) {
            'Active' => 'success',
            'Ready' => 'warning',
            default => 'danger'
        };

        return [
            Stat::make('MCP System', $systemStatus)
                ->description($systemDescription)
                ->descriptionIcon($systemStatus === 'Active' ? 'heroicon-o-check-circle' : 'heroicon-o-exclamation-triangle')
                ->color($systemColor),

            Stat::make('Error Patterns', $stats['patterns_configured'] ?? 11)
                ->description('Detection patterns configured')
                ->descriptionIcon('heroicon-o-puzzle-piece')
                ->color('info'),
        ];
    }
}
