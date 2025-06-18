<?php

namespace App\Filament\Widgets;

use App\Models\ProxmoxCluster;
use Filament\Widgets\ChartWidget;

class ResourceUtilizationWidget extends ChartWidget
{
    protected ?string $heading = 'Average Resource Utilization Across Clusters';

    protected static ?int $sort = 4;

    protected function getData(): array
    {
        $clusters = ProxmoxCluster::where('status', 'active')->get();
        $utilizationData = [];

        foreach ($clusters as $cluster) {
            $utilization = $cluster->getResourceUtilization();
            $utilizationData[] = [
                'cluster' => $cluster->name,
                'cpu' => $utilization['cpu'],
                'memory' => $utilization['memory'],
                'storage' => $utilization['storage'],
            ];
        }

        return [
            'datasets' => [
                [
                    'label' => 'CPU Utilization (%)',
                    'data' => collect($utilizationData)->pluck('cpu')->toArray(),
                    'backgroundColor' => '#EF4444',
                ],
                [
                    'label' => 'Memory Utilization (%)',
                    'data' => collect($utilizationData)->pluck('memory')->toArray(),
                    'backgroundColor' => '#F59E0B',
                ],
                [
                    'label' => 'Storage Utilization (%)',
                    'data' => collect($utilizationData)->pluck('storage')->toArray(),
                    'backgroundColor' => '#10B981',
                ],
            ],
            'labels' => collect($utilizationData)->pluck('cluster')->toArray(),
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }
}
