<?php

namespace App\Filament\Widgets;

use Filament\Widgets\ChartWidget;

class PlatformUsageWidget extends ChartWidget
{
    protected ?string $heading = 'Platform Usage Trends (30 Days)';

    protected static ?int $sort = 2;

    protected function getData(): array
    {
        // Generate sample data for the last 30 days
        $days = collect(range(1, 30))->map(function ($day) {
            $date = now()->subDays(30 - $day);

            return [
                'date' => $date->format('M j'),
                'environments' => rand(15, 35),
                'vms' => rand(25, 55),
                'containers' => rand(30, 70),
            ];
        });

        return [
            'datasets' => [
                [
                    'label' => 'Development Environments',
                    'data' => $days->pluck('environments')->toArray(),
                    'borderColor' => '#10B981',
                    'backgroundColor' => '#10B981',
                ],
                [
                    'label' => 'Virtual Machines',
                    'data' => $days->pluck('vms')->toArray(),
                    'borderColor' => '#3B82F6',
                    'backgroundColor' => '#3B82F6',
                ],
                [
                    'label' => 'Containers',
                    'data' => $days->pluck('containers')->toArray(),
                    'borderColor' => '#8B5CF6',
                    'backgroundColor' => '#8B5CF6',
                ],
            ],
            'labels' => $days->pluck('date')->toArray(),
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }
}
