<?php

namespace App\Filament\Widgets;

use App\Models\McpProcessStatus;
use Filament\Widgets\ChartWidget;

class McpHealthMonitorWidget extends ChartWidget
{
    protected ?string $heading = 'MCP Process Health';

    protected ?string $pollingInterval = '5s';

    protected int|string|array $columnSpan = 'full';

    protected function getData(): array
    {
        try {
            $runningProcesses = McpProcessStatus::where('status', 'running')->get();

            $processNames = [];
            $cpuData = [];
            $memoryData = [];
            $uptimeData = [];

            foreach ($runningProcesses as $process) {
                $processNames[] = $process->process_name;

                $resourceUsage = $process->getResourceUsage();
                $cpuData[] = $resourceUsage['cpu_time'] ?? 0;
                $memoryData[] = $resourceUsage['memory_mb'] ?? 0;

                // Convert uptime to hours for charting
                $uptime = $process->started_at ?
                    $process->started_at->diffInHours(now()) : 0;
                $uptimeData[] = $uptime;
            }

            if (empty($processNames)) {
                $processNames = ['No Running Processes'];
                $cpuData = [0];
                $memoryData = [0];
                $uptimeData = [0];
            }

            return [
                'datasets' => [
                    [
                        'label' => 'Memory Usage (MB)',
                        'data' => $memoryData,
                        'backgroundColor' => 'rgb(59, 130, 246)',
                        'borderColor' => 'rgb(59, 130, 246)',
                        'tension' => 0.1,
                    ],
                    [
                        'label' => 'CPU Time',
                        'data' => $cpuData,
                        'backgroundColor' => 'rgb(34, 197, 94)',
                        'borderColor' => 'rgb(34, 197, 94)',
                        'tension' => 0.1,
                    ],
                    [
                        'label' => 'Uptime (Hours)',
                        'data' => $uptimeData,
                        'backgroundColor' => 'rgb(168, 85, 247)',
                        'borderColor' => 'rgb(168, 85, 247)',
                        'tension' => 0.1,
                    ],
                ],
                'labels' => $processNames,
            ];
        } catch (\Exception $e) {
            return [
                'datasets' => [
                    [
                        'label' => 'Error',
                        'data' => [0],
                        'backgroundColor' => 'rgb(239, 68, 68)',
                    ],
                ],
                'labels' => ['Error Loading Data'],
            ];
        }
    }

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getOptions(): array
    {
        return [
            'plugins' => [
                'legend' => [
                    'display' => true,
                ],
            ],
            'scales' => [
                'y' => [
                    'beginAtZero' => true,
                ],
            ],
        ];
    }
}
