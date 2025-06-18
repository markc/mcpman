<?php

namespace App\Filament\Widgets;

use App\Models\DevelopmentEnvironment;
use App\Models\ProxmoxContainer;
use App\Models\ProxmoxVirtualMachine;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

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
                ->descriptionIcon(Heroicon::OutlinedCube)
                ->color('success')
                ->chart([7, 12, 8, 15, 18, 22, $activeEnvironments]),

            Stat::make('Monthly Platform Cost', '$'.number_format($totalMonthlyCost, 2))
                ->description('Estimated operational cost')
                ->descriptionIcon(Heroicon::OutlinedCurrencyDollar)
                ->color('warning'),

            Stat::make('Running Virtual Machines', $runningVMs)
                ->description("{$totalVMs} total VMs")
                ->descriptionIcon(Heroicon::OutlinedServer)
                ->color('info')
                ->chart([15, 18, 22, 25, 20, 24, $runningVMs]),

            Stat::make('Running Containers', $runningContainers)
                ->description("{$totalContainers} total containers")
                ->descriptionIcon(Heroicon::OutlinedCubeTransparent)
                ->color('primary')
                ->chart([8, 12, 15, 18, 16, 20, $runningContainers]),
        ];
    }
}
