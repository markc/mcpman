<?php

namespace App\Filament\Widgets;

use App\Services\McpHealthCheckService;
use Filament\Support\Enums\Heroicon;
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
                ->descriptionIcon(Heroicon::OUTLINE_COMMAND_LINE)
                ->color($this->getComponentColor($components['Claude CLI'] ?? 'Unknown')),

            Stat::make('Authentication', $components['Authentication'] ?? 'Unknown')
                ->description('API key validation')
                ->descriptionIcon(Heroicon::OUTLINE_KEY)
                ->color($this->getComponentColor($components['Authentication'] ?? 'Unknown')),

            Stat::make('MCP Server', $components['MCP Server'] ?? 'Unknown')
                ->description('Protocol server')
                ->descriptionIcon(Heroicon::OUTLINE_SERVER)
                ->color($this->getComponentColor($components['MCP Server'] ?? 'Unknown')),

            Stat::make('Connection Pool', $this->getActiveConnections())
                ->description('Active connections')
                ->descriptionIcon(Heroicon::OUTLINE_LINK)
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

    // Polling handled by parent page
}
