<?php

namespace App\Filament\Pages;

use App\Services\LogMonitoringService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

class LogMonitoringDashboard extends Page
{
    protected static ?string $title = 'Log Monitoring Dashboard';

    protected static ?string $navigationLabel = 'Log Monitoring';

    protected static ?int $navigationSort = 9;

    public function getView(): string
    {
        return 'filament.pages.log-monitoring-dashboard';
    }

    public array $monitoringStats = [];

    public function mount(): void
    {
        $this->loadMonitoringStats();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('startMonitoring')
                ->label('Start Monitoring')
                ->icon('heroicon-o-play')
                ->color('success')
                ->action('startLogMonitoring'),

            Action::make('testErrorDetection')
                ->label('Test Error Detection')
                ->icon('heroicon-o-bug-ant')
                ->color('warning')
                ->action('testErrorDetection'),

            Action::make('refreshStats')
                ->label('Refresh Stats')
                ->icon('heroicon-o-arrow-path')
                ->action('refreshMonitoringStats'),
        ];
    }

    public function startLogMonitoring(): void
    {
        Notification::make()
            ->title('Log Monitoring Instructions')
            ->body('To start log monitoring, run: composer run dev (includes monitoring) or php artisan mcp:watch-logs --auto-fix')
            ->info()
            ->persistent()
            ->send();
    }

    public function testErrorDetection(): void
    {
        // Create a test error in the log
        \Log::error('Test error for log monitoring: This is a simulated error for testing purposes', [
            'test' => true,
            'timestamp' => now()->toISOString(),
            'file' => 'test_file.php',
            'line' => 123,
        ]);

        Notification::make()
            ->title('Test Error Generated')
            ->body('A test error has been written to the log file for monitoring system testing.')
            ->success()
            ->send();
    }

    public function refreshMonitoringStats(): void
    {
        $this->loadMonitoringStats();

        Notification::make()
            ->title('Statistics Refreshed')
            ->body('Monitoring statistics have been updated.')
            ->success()
            ->send();
    }

    protected function loadMonitoringStats(): void
    {
        try {
            $logMonitor = app(LogMonitoringService::class);
            $this->monitoringStats = $logMonitor->getMonitoringStats();

            // Add additional stats
            $this->monitoringStats['log_monitoring_command'] = 'php artisan mcp:watch-logs --auto-fix';
            $this->monitoringStats['integration_status'] = 'Ready';
            $this->monitoringStats['last_updated'] = now()->format('Y-m-d H:i:s');

        } catch (\Exception $e) {
            $this->monitoringStats = [
                'error' => 'Failed to load monitoring stats: '.$e->getMessage(),
                'patterns_configured' => 0,
                'monitoring_supported' => false,
                'log_file_exists' => false,
                'log_file_size' => 0,
            ];
        }
    }

    public function getMonitoringStats(): array
    {
        return $this->monitoringStats;
    }

    public function getStatusColor(bool $status): string
    {
        return $status ? 'success' : 'danger';
    }

    public function getStatusIcon(bool $status): string
    {
        return $status ? 'heroicon-o-check-circle' : 'heroicon-o-x-circle';
    }

    public function formatFileSize(int $bytes): string
    {
        if ($bytes == 0) {
            return '0 B';
        }

        $units = ['B', 'KB', 'MB', 'GB'];
        $base = log($bytes, 1024);

        return round(pow(1024, $base - floor($base)), 2).' '.$units[floor($base)];
    }
}
