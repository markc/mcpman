<?php

namespace App\Filament\Widgets;

use App\Services\PersistentMcpManager;
use Filament\Widgets\Widget;
use Livewire\Attributes\On;

class McpServerStatusWidget extends Widget
{
    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = [
        'md' => 2,
        'xl' => 1,
    ];

    public array $serverStatus = [];

    public function render(): \Illuminate\Contracts\View\View
    {
        return view('filament.widgets.mcp-server-status');
    }

    public function mount(): void
    {
        $this->loadServerStatus();
    }

    #[On('echo:mcp-server,server.status.changed')]
    public function handleServerStatusChanged($data): void
    {
        $this->loadServerStatus();
        $this->dispatch('server-status-updated');
    }

    public function loadServerStatus(): void
    {
        $manager = app(PersistentMcpManager::class);

        try {
            // Get all active connections
            $activeConnections = \App\Models\McpConnection::where('status', 'active')->get();
            $healthChecks = [];

            // Safely check health for each connection
            foreach ($activeConnections as $connection) {
                try {
                    $healthChecks[$connection->name] = $manager->healthCheck((string) $connection->id);
                } catch (\Exception $e) {
                    // If health check fails, mark as unhealthy but don't break the widget
                    $healthChecks[$connection->name] = false;
                }
            }

            $this->serverStatus = [
                'status' => 'running',
                'uptime' => $this->calculateUptime(),
                'health_check' => $healthChecks,
                'active_connections' => $activeConnections->count(),
                'memory_usage' => memory_get_usage(true),
                'memory_peak' => memory_get_peak_usage(true),
                'php_version' => PHP_VERSION,
                'laravel_version' => app()->version(),
                'last_updated' => now()->toISOString(),
            ];
        } catch (\Exception $e) {
            $this->serverStatus = [
                'status' => 'error',
                'error' => $e->getMessage(),
                'last_updated' => now()->toISOString(),
            ];
        }
    }

    private function calculateUptime(): string
    {
        // Simple uptime calculation based on when the application started
        // In a real implementation, this would track actual service uptime
        $startTime = filemtime(base_path('.env'));
        $uptime = time() - $startTime;

        $days = floor($uptime / 86400);
        $hours = floor(($uptime % 86400) / 3600);
        $minutes = floor(($uptime % 3600) / 60);

        if ($days > 0) {
            return "{$days}d {$hours}h {$minutes}m";
        } elseif ($hours > 0) {
            return "{$hours}h {$minutes}m";
        } else {
            return "{$minutes}m";
        }
    }

    public function refreshStatus(): void
    {
        $this->loadServerStatus();

        \Filament\Notifications\Notification::make()
            ->title('Server status refreshed')
            ->success()
            ->send();
    }

    public function getListeners(): array
    {
        return [
            'echo:mcp-server,server.status.changed' => 'handleServerStatusChanged',
        ];
    }
}
