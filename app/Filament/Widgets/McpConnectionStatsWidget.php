<?php

namespace App\Filament\Widgets;

use App\Models\McpConnection;
use App\Services\PersistentMcpManager;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Livewire\Attributes\On;

class McpConnectionStatsWidget extends BaseWidget
{
    protected static ?int $sort = 1;

    protected int|string|array $columnSpan = 'full';

    protected function getStats(): array
    {
        $connections = McpConnection::all();
        $manager = app(PersistentMcpManager::class);

        $activeInManager = 0;
        foreach ($connections->where('status', 'active') as $connection) {
            if ($manager->isConnectionActive((string) $connection->id)) {
                $activeInManager++;
            }
        }

        return [
            Stat::make('Total Connections', $connections->count())
                ->description('All MCP connections')
                ->descriptionIcon(Heroicon::ArrowTrendingUp)
                ->color('primary'),

            Stat::make('Active Connections', $connections->where('status', 'active')->count())
                ->description('Configured as active')
                ->descriptionIcon(Heroicon::CheckCircle)
                ->color('success'),

            Stat::make('Manager Active', $activeInManager)
                ->description('Actually running in manager')
                ->descriptionIcon(Heroicon::CpuChip)
                ->color('info'),

            Stat::make('Error Rate', $this->calculateErrorRate($connections))
                ->description('Connections with errors')
                ->descriptionIcon(Heroicon::ExclamationTriangle)
                ->color('danger'),

            Stat::make('Memory Usage', $this->getMemoryUsage())
                ->description('Current memory consumption')
                ->descriptionIcon(Heroicon::Server)
                ->color('warning'),
        ];
    }

    private function calculateErrorRate($connections): string
    {
        $total = $connections->count();
        if ($total === 0) {
            return '0%';
        }

        $errors = $connections->where('status', 'error')->count();

        return round(($errors / $total) * 100, 1).'%';
    }

    private function getMemoryUsage(): string
    {
        return round(memory_get_usage(true) / 1024 / 1024, 1).'MB';
    }

    #[On('echo:mcp-connections,connection.status.changed')]
    public function refreshStats(): void
    {
        // Refresh the widget when connection status changes
        $this->dispatch('$refresh');
    }

    public function getListeners(): array
    {
        return [
            'echo:mcp-connections,connection.status.changed' => 'refreshStats',
        ];
    }
}
