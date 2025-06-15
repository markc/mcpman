<?php

namespace App\Filament\Widgets;

use App\Services\McpHealthCheckService;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class SystemComponentsWidget extends BaseWidget
{
    protected static ?int $sort = 2;

    protected function getStats(): array
    {
        $healthService = app(McpHealthCheckService::class);
        $healthData = $healthService->getHealthDashboardData();
        $components = $healthData['components'] ?? [];

        return [
            Stat::make('Claude CLI', $components['Claude CLI'] ?? 'Unknown')
                ->description('Command line interface')
                ->descriptionIcon('heroicon-o-command-line')
                ->color($this->getComponentColor($components['Claude CLI'] ?? 'Unknown')),

            Stat::make('Authentication', $components['Authentication'] ?? 'Unknown')
                ->description('API key validation')
                ->descriptionIcon('heroicon-o-key')
                ->color($this->getComponentColor($components['Authentication'] ?? 'Unknown')),

            Stat::make('MCP Server', $components['MCP Server'] ?? 'Unknown')
                ->description('Protocol server')
                ->descriptionIcon('heroicon-o-server')
                ->color($this->getComponentColor($components['MCP Server'] ?? 'Unknown')),

            Stat::make('Connection Pool', $this->getActiveConnections())
                ->description('Active connections')
                ->descriptionIcon('heroicon-o-link')
                ->color('info'),
        ];
    }

    private function getComponentColor(string $status): string
    {
        return match (strtolower($status)) {
            'available', 'valid', 'responsive' => 'success',
            'unavailable', 'invalid', 'unresponsive' => 'danger',
            default => 'warning',
        };
    }

    private function getActiveConnections(): string
    {
        try {
            $connections = \App\Models\McpConnection::where('status', 'active')->count();

            return (string) $connections;
        } catch (\Exception $e) {
            return '0';
        }
    }

    protected ?string $pollingInterval = '60s'; // Refresh every 60 seconds
}
