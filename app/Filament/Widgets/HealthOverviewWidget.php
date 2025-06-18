<?php

namespace App\Filament\Widgets;

use App\Services\McpHealthCheckService;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class HealthOverviewWidget extends BaseWidget
{
    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        $healthService = app(McpHealthCheckService::class);
        // Force fresh data for widgets
        $healthData = $healthService->getHealthDashboardData();

        return [
            Stat::make('Overall Health', $healthData['score'].'/100')
                ->description(ucfirst($healthData['status'] ?? 'unknown'))
                ->descriptionIcon($this->getStatusIcon($healthData['status'] ?? 'unknown'))
                ->color($this->getStatusColor($healthData['status'] ?? 'unknown')),

            Stat::make('Response Time', $healthData['performance']['Response Time'] ?? 'Unknown')
                ->description($healthData['performance']['Assessment'] ?? 'Unknown')
                ->descriptionIcon(Heroicon::OutlinedClock)
                ->color(($healthData['performance']['Assessment'] ?? '') === 'fast' ? 'success' : 'warning'),

            Stat::make('System Issues', count($healthData['errors'] ?? []))
                ->description('Active problems')
                ->descriptionIcon(Heroicon::OutlinedExclamationTriangle)
                ->color(count($healthData['errors'] ?? []) === 0 ? 'success' : 'danger'),

            Stat::make('Last Check', $this->formatLastCheck($healthData['last_check'] ?? null))
                ->description('Health verification')
                ->descriptionIcon(Heroicon::OutlinedClock)
                ->color('info'),
        ];
    }

    private function getStatusIcon(string $status): string
    {
        return match ($status) {
            'excellent' => Heroicon::OutlinedCheckCircle,
            'good' => Heroicon::OutlinedCheckBadge,
            'fair' => Heroicon::OutlinedExclamationTriangle,
            'poor' => Heroicon::OutlinedXCircle,
            'critical', 'error' => Heroicon::OutlinedXCircle,
            default => Heroicon::OutlinedQuestionMarkCircle,
        };
    }

    private function getStatusColor(string $status): string
    {
        return match ($status) {
            'excellent' => 'success',
            'good' => 'primary',
            'fair' => 'warning',
            'poor' => 'danger',
            'critical', 'error' => 'danger',
            default => 'gray',
        };
    }

    private function formatLastCheck(?string $lastCheck): string
    {
        if (! $lastCheck) {
            return 'Never';
        }

        try {
            return \Carbon\Carbon::parse($lastCheck)->diffForHumans();
        } catch (\Exception $e) {
            return 'Unknown';
        }
    }

    // Polling handled by parent page
}
