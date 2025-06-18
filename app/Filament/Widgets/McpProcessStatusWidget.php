<?php

namespace App\Filament\Widgets;

use App\Models\McpProcessStatus;
use App\Services\McpProcessOrchestrator;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Log;

class McpProcessStatusWidget extends BaseWidget
{
    protected ?string $pollingInterval = '2s';

    protected function getStats(): array
    {
        try {
            $orchestrator = app(McpProcessOrchestrator::class);

            // Get log monitoring status specifically
            $logMonitoringRunning = $orchestrator->isProcessRunning('log-monitoring');
            $logMonitoringStatus = $orchestrator->getProcessStatus('log-monitoring');

            // Get all process counts
            $totalProcesses = McpProcessStatus::count();
            $runningProcesses = McpProcessStatus::where('status', 'running')->count();
            $failedProcesses = McpProcessStatus::whereIn('status', ['failed', 'died'])->count();

            return [
                Stat::make('Log Monitoring', $logMonitoringRunning ? 'Running' : 'Stopped')
                    ->description($logMonitoringRunning
                        ? 'PID: '.($logMonitoringStatus['pid'] ?? 'N/A').' | Uptime: '.($logMonitoringStatus['uptime'] ?? 'N/A')
                        : 'Process not active'
                    )
                    ->descriptionIcon($logMonitoringRunning ? Heroicon::CheckCircle : Heroicon::XCircle)
                    ->color($logMonitoringRunning ? 'success' : 'danger')
                    ->chart($this->getProcessChart('log-monitoring')),

                Stat::make('Running Processes', $runningProcesses)
                    ->description('Active MCP processes')
                    ->descriptionIcon(Heroicon::PlayCircle)
                    ->color($runningProcesses > 0 ? 'success' : 'gray'),

                Stat::make('Failed Processes', $failedProcesses)
                    ->description('Processes that failed or died')
                    ->descriptionIcon(Heroicon::ExclamationTriangle)
                    ->color($failedProcesses > 0 ? 'danger' : 'success'),

                Stat::make('Total Processes', $totalProcesses)
                    ->description('All tracked processes')
                    ->descriptionIcon(Heroicon::CpuChip)
                    ->color('primary'),
            ];
        } catch (\Exception $e) {
            Log::error('McpProcessStatusWidget error', ['error' => $e->getMessage()]);

            return [
                Stat::make('Error', 'Failed to load')
                    ->description('Widget error: '.$e->getMessage())
                    ->descriptionIcon(Heroicon::ExclamationTriangle)
                    ->color('danger'),
            ];
        }
    }

    private function getProcessChart(string $processName): array
    {
        try {
            // Simple chart showing process activity over last 7 data points
            $status = McpProcessStatus::where('process_name', $processName)->first();
            if (! $status) {
                return [0, 0, 0, 0, 0, 0, 0];
            }

            // Generate mock chart data based on process status
            // In a real implementation, you'd track historical metrics
            $isRunning = $status->status === 'running' ? 1 : 0;

            return [$isRunning, $isRunning, $isRunning, $isRunning, $isRunning, $isRunning, $isRunning];
        } catch (\Exception $e) {
            return [0, 0, 0, 0, 0, 0, 0];
        }
    }
}
