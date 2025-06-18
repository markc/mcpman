<?php

namespace App\Filament\Pages;

use App\Models\DevelopmentEnvironment;
use App\Models\ProxmoxCluster;
use App\Models\ProxmoxContainer;
use App\Models\ProxmoxVirtualMachine;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use UnitEnum;

class ProxmoxExecutiveDashboard extends Page
{
    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-chart-bar';

    protected string $view = 'filament.pages.proxmox-executive-dashboard';

    protected static ?string $navigationLabel = 'Executive Dashboard';

    protected static string|UnitEnum|null $navigationGroup = 'Analytics';

    protected static ?int $navigationSort = 1;

    protected static ?string $title = 'PHP/Laravel Development Platform Analytics';

    public function getWidgets(): array
    {
        return [
            ExecutiveKPIWidget::class,
            PlatformUsageWidget::class,
            CostAnalysisWidget::class,
            ResourceUtilizationWidget::class,
            EnvironmentTrendsWidget::class,
            ClusterHealthWidget::class,
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

class ExecutiveKPIWidget extends StatsOverviewWidget
{
    protected ?string $pollingInterval = '30s';

    protected function getStats(): array
    {
        $totalEnvironments = DevelopmentEnvironment::count();
        $activeEnvironments = DevelopmentEnvironment::where('status', 'running')->count();
        $totalMonthlyCost = DevelopmentEnvironment::where('status', 'running')
            ->get()
            ->sum(fn ($env) => $env->getEstimatedMonthlyCost());
        $totalVMs = ProxmoxVirtualMachine::count();
        $runningVMs = ProxmoxVirtualMachine::where('status', 'running')->count();
        $totalContainers = ProxmoxContainer::count();
        $runningContainers = ProxmoxContainer::where('status', 'running')->count();

        return [
            Stat::make('Active Development Environments', $activeEnvironments)
                ->description("{$totalEnvironments} total environments")
                ->descriptionIcon('heroicon-o-cube')
                ->color('success')
                ->chart([7, 12, 8, 15, 18, 22, $activeEnvironments]),

            Stat::make('Monthly Platform Cost', '$'.number_format($totalMonthlyCost, 2))
                ->description('Estimated operational cost')
                ->descriptionIcon('heroicon-o-currency-dollar')
                ->color('warning'),

            Stat::make('Running Virtual Machines', $runningVMs)
                ->description("{$totalVMs} total VMs")
                ->descriptionIcon('heroicon-o-server')
                ->color('info')
                ->chart([15, 18, 22, 25, 20, 24, $runningVMs]),

            Stat::make('Running Containers', $runningContainers)
                ->description("{$totalContainers} total containers")
                ->descriptionIcon('heroicon-o-cube-transparent')
                ->color('primary')
                ->chart([8, 12, 15, 18, 16, 20, $runningContainers]),
        ];
    }
}

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
                ->descriptionIcon('heroicon-o-heart')
                ->color($averageHealth >= 80 ? 'success' : ($averageHealth >= 60 ? 'warning' : 'danger')),

            Stat::make('Healthy Clusters', $healthyCount)
                ->description('Health score ≥ 80%')
                ->descriptionIcon('heroicon-o-check-circle')
                ->color('success'),

            Stat::make('Warning Clusters', $warningCount)
                ->description('Health score 60-79%')
                ->descriptionIcon('heroicon-o-exclamation-triangle')
                ->color('warning'),

            Stat::make('Critical Clusters', $criticalCount)
                ->description('Health score < 60%')
                ->descriptionIcon('heroicon-o-x-circle')
                ->color('danger'),
        ];
    }
}
