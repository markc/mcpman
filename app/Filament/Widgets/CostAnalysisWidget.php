<?php

namespace App\Filament\Widgets;

use App\Models\DevelopmentEnvironment;
use Filament\Widgets\ChartWidget;

class CostAnalysisWidget extends ChartWidget
{
    protected ?string $heading = 'Cost Analysis by Environment Type';

    protected static ?int $sort = 3;

    protected function getData(): array
    {
        $costByType = DevelopmentEnvironment::where('status', 'running')
            ->get()
            ->groupBy('environment_type')
            ->map(function ($environments) {
                return $environments->sum(fn ($env) => $env->getEstimatedMonthlyCost());
            });

        $colors = [
            'development' => '#10B981',
            'testing' => '#3B82F6',
            'staging' => '#F59E0B',
            'training' => '#8B5CF6',
        ];

        return [
            'datasets' => [
                [
                    'data' => $costByType->values()->toArray(),
                    'backgroundColor' => $costByType->keys()->map(fn ($type) => $colors[$type] ?? '#6B7280')->toArray(),
                ],
            ],
            'labels' => $costByType->keys()->map(fn ($type) => ucfirst($type))->toArray(),
        ];
    }

    protected function getType(): string
    {
        return 'doughnut';
    }
}
