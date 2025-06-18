<?php

namespace App\Filament\Pages;

use App\Models\DevelopmentEnvironment;
use App\Models\ProxmoxCluster;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

class ProxmoxExecutiveDashboard extends Page
{
    protected static BackedEnum|string|null $navigationIcon = Heroicon::OutlinedChartBar;

    protected string $view = 'filament.pages.proxmox-executive-dashboard';

    protected static ?string $navigationLabel = 'Executive Dashboard';

    protected static string|UnitEnum|null $navigationGroup = 'Analytics';

    protected static ?int $navigationSort = 1;

    protected static ?string $title = 'PHP/Laravel Development Platform Analytics';

    public function getWidgets(): array
    {
        return [
            \App\Filament\Widgets\ExecutiveKPIWidget::class,
            \App\Filament\Widgets\PlatformUsageWidget::class,
            \App\Filament\Widgets\CostAnalysisWidget::class,
            \App\Filament\Widgets\ResourceUtilizationWidget::class,
            \App\Filament\Widgets\EnvironmentTrendsWidget::class,
            \App\Filament\Widgets\ClusterHealthWidget::class,
        ];
    }

    protected function getViewData(): array
    {
        return [
            'totalClusters' => ProxmoxCluster::count(),
            'activeClusters' => ProxmoxCluster::where('status', 'active')->count(),
            'totalEnvironments' => DevelopmentEnvironment::count(),
            'activeEnvironments' => DevelopmentEnvironment::where('status', 'running')->count(),
            'totalMonthlyCost' => $this->getTotalMonthlyCost(),
            'averageUtilization' => $this->getAverageUtilization(),
        ];
    }

    private function getTotalMonthlyCost(): float
    {
        return DevelopmentEnvironment::where('status', 'running')
            ->get()
            ->sum(fn ($env) => $env->getEstimatedMonthlyCost());
    }

    private function getAverageUtilization(): array
    {
        $clusters = ProxmoxCluster::where('status', 'active')->get();
        $totalUtilization = ['cpu' => 0, 'memory' => 0, 'storage' => 0];
        $count = $clusters->count();

        if ($count === 0) {
            return $totalUtilization;
        }

        foreach ($clusters as $cluster) {
            $utilization = $cluster->getResourceUtilization();
            $totalUtilization['cpu'] += $utilization['cpu'];
            $totalUtilization['memory'] += $utilization['memory'];
            $totalUtilization['storage'] += $utilization['storage'];
        }

        return [
            'cpu' => round($totalUtilization['cpu'] / $count, 1),
            'memory' => round($totalUtilization['memory'] / $count, 1),
            'storage' => round($totalUtilization['storage'] / $count, 1),
        ];
    }
}
