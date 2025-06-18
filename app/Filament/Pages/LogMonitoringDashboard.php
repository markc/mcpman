<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\LogFileMonitoringWidget;
use App\Filament\Widgets\SystemMonitoringWidget;
use App\Jobs\StartMcpProcess;
use App\Jobs\StopMcpProcess;
use App\Services\LogMonitoringService;
use App\Services\McpProcessOrchestrator;
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
        $isMonitoring = $this->isMonitoringActive();

        return [
            Action::make('toggleMonitoring')
                ->label($isMonitoring ? 'Stop Monitoring' : 'Start Monitoring')
                ->icon($isMonitoring ? 'heroicon-o-stop' : 'heroicon-o-play')
                ->color($isMonitoring ? 'danger' : 'success')
                ->action($isMonitoring ? 'stopLogMonitoring' : 'startLogMonitoring'),

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
        try {
            \Log::info('Starting log monitoring via new orchestrator');

            $processName = 'log-monitoring';
            $command = ['php', 'artisan', 'mcp:watch-logs', '--auto-fix'];
            $options = [
                'working_directory' => base_path(),
                'detached' => true,
                'restart_on_failure' => true,
                'use_systemd' => config('app.env') === 'production', // Use systemd in production
                'service_name' => 'log-monitoring',
            ];

            // Dispatch job to start process in background
            StartMcpProcess::dispatch($processName, $command, $options);

            Notification::make()
                ->title('Monitoring Starting')
                ->body('Log monitoring is being started in the background. Check the status widget for updates.')
                ->success()
                ->send();

        } catch (\Exception $e) {
            \Log::error('Failed to start monitoring', ['error' => $e->getMessage()]);

            Notification::make()
                ->title('Start Failed')
                ->body("Exception: {$e->getMessage()}")
                ->danger()
                ->send();
        }
    }

    public function stopLogMonitoring(): void
    {
        try {
            \Log::info('Stopping log monitoring via new orchestrator');

            $processName = 'log-monitoring';

            // Dispatch job to stop process in background
            StopMcpProcess::dispatch($processName);

            Notification::make()
                ->title('Monitoring Stopping')
                ->body('Log monitoring is being stopped in the background. Check the status widget for updates.')
                ->warning()
                ->send();

        } catch (\Exception $e) {
            \Log::error('Failed to stop monitoring', ['error' => $e->getMessage()]);

            Notification::make()
                ->title('Stop Failed')
                ->body("Error stopping monitoring: {$e->getMessage()}")
                ->danger()
                ->send();
        }
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
        \Log::info('Refresh Stats button clicked');

        $this->loadMonitoringStats();

        // Force check the monitoring status
        $status = $this->isMonitoringActive();
        \Log::info('Manual status check result', ['is_active' => $status]);

        Notification::make()
            ->title('Statistics Refreshed')
            ->body('Monitoring statistics have been updated. Status: '.($status ? 'Active' : 'Ready'))
            ->success()
            ->send();

        // Force a component refresh
        $this->dispatch('$refresh');
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

    protected function isMonitoringActive(): bool
    {
        try {
            $orchestrator = app(McpProcessOrchestrator::class);

            return $orchestrator->isProcessRunning('log-monitoring');
        } catch (\Exception $e) {
            \Log::error('Failed to check monitoring status', ['error' => $e->getMessage()]);

            return false;
        }
    }

    protected function getHeaderWidgets(): array
    {
        return [
            \App\Filament\Widgets\McpProcessStatusWidget::class,
            SystemMonitoringWidget::class,
            LogFileMonitoringWidget::class,
        ];
    }

    protected function getFooterWidgets(): array
    {
        return [
            \App\Filament\Widgets\McpHealthMonitorWidget::class,
        ];
    }
}
