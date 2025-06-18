<?php

namespace App\Filament\Widgets;

use App\Models\ProxmoxCluster;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class ClusterHealthWidget extends StatsOverviewWidget
{
    protected ?string $pollingInterval = '60s';

    protected static ?int $sort = 6;

    protected function getStats(): array
    {
        $clusters = ProxmoxCluster::all();
        $healthyCount = 0;
        $warningCount = 0;
        $criticalCount = 0;
        $totalHealthScore = 0;

        foreach ($clusters as $cluster) {
            $healthScore = $cluster->getHealthScore();
            $totalHealthScore += $healthScore;

            if ($healthScore >= 80) {
                $healthyCount++;
            } elseif ($healthScore >= 60) {
                $warningCount++;
            } else {
                $criticalCount++;
            }
        }

        $averageHealth = $clusters->count() > 0 ? round($totalHealthScore / $clusters->count()) : 0;

        return [
            Stat::make('Average Cluster Health', $averageHealth.'%')
                ->description('Overall platform health')
                ->descriptionIcon(Heroicon::OutlinedHeart->value)
                ->color($averageHealth >= 80 ? 'success' : ($averageHealth >= 60 ? 'warning' : 'danger')),

            Stat::make('Healthy Clusters', $healthyCount)
                ->description('Health score ≥ 80%')
                ->descriptionIcon(Heroicon::OutlinedCheckCircle->value)
                ->color('success'),

            Stat::make('Warning Clusters', $warningCount)
                ->description('Health score 60-79%')
                ->descriptionIcon(Heroicon::OutlinedExclamationTriangle->value)
                ->color('warning'),

            Stat::make('Critical Clusters', $criticalCount)
                ->description('Health score < 60%')
                ->descriptionIcon(Heroicon::OutlinedXCircle->value)
                ->color('danger'),
        ];
    }
}
