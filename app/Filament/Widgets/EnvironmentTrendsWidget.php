<?php

namespace App\Filament\Widgets;

use Filament\Widgets\ChartWidget;

class EnvironmentTrendsWidget extends ChartWidget
{
    protected ?string $heading = 'Environment Lifecycle Trends';

    protected static ?int $sort = 5;

    protected function getData(): array
    {
        // Generate sample data for environment creation/destruction trends
        $days = collect(range(1, 14))->map(function ($day) {
            $date = now()->subDays(14 - $day);

            return [
                'date' => $date->format('M j'),
                'created' => rand(2, 8),
                'destroyed' => rand(1, 5),
            ];
        });

        return [
            'datasets' => [
                [
                    'label' => 'Environments Created',
                    'data' => $days->pluck('created')->toArray(),
                    'borderColor' => '#10B981',
                    'backgroundColor' => 'rgba(16, 185, 129, 0.1)',
                    'fill' => true,
                ],
                [
                    'label' => 'Environments Destroyed',
                    'data' => $days->pluck('destroyed')->toArray(),
                    'borderColor' => '#EF4444',
                    'backgroundColor' => 'rgba(239, 68, 68, 0.1)',
                    'fill' => true,
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
