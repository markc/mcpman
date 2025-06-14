<?php

namespace App\Filament\Pages;

use App\Models\McpConnection;
use App\Services\PersistentMcpManager;
use Filament\Pages\Page;
use Livewire\Attributes\On;

class McpMonitoring extends Page
{
    protected static ?string $title = 'MCP Monitoring';

    protected static ?string $navigationLabel = 'Connection Monitor';

    protected static ?int $navigationSort = 2;

    public function getView(): string
    {
        return 'filament.pages.mcp-monitoring';
    }

    public array $connectionStats = [];

    public array $activeConnections = [];

    public array $systemMetrics = [];

    public function mount(): void
    {
        $this->loadConnectionStats();
        $this->loadActiveConnections();
        $this->loadSystemMetrics();
    }

    #[On('echo:mcp-connections,connection.status.changed')]
    public function handleConnectionStatusChanged($data): void
    {
        $this->loadConnectionStats();
        $this->loadActiveConnections();
        $this->dispatch('connection-status-updated');
    }

    #[On('echo:mcp-server,server.metrics.updated')]
    public function handleServerMetricsUpdated($data): void
    {
        $this->loadSystemMetrics();
        $this->dispatch('metrics-updated');
    }

    public function loadConnectionStats(): void
    {
        $connections = McpConnection::all();

        $this->connectionStats = [
            'total' => $connections->count(),
            'active' => $connections->where('status', 'active')->count(),
            'inactive' => $connections->where('status', 'inactive')->count(),
            'error' => $connections->where('status', 'error')->count(),
            'connecting' => $connections->where('status', 'connecting')->count(),
        ];
    }

    public function loadActiveConnections(): void
    {
        $manager = app(PersistentMcpManager::class);
        $connections = McpConnection::where('status', 'active')->get();

        $this->activeConnections = $connections->map(function ($connection) use ($manager) {
            $isActive = $manager->isConnectionActive((string) $connection->id);

            return [
                'id' => $connection->id,
                'name' => $connection->name,
                'endpoint_url' => $connection->endpoint_url,
                'transport_type' => $connection->transport_type,
                'status' => $connection->status,
                'is_manager_active' => $isActive,
                'capabilities' => $connection->capabilities,
                'created_at' => $connection->created_at,
                'updated_at' => $connection->updated_at,
            ];
        })->toArray();
    }

    public function loadSystemMetrics(): void
    {
        $manager = app(PersistentMcpManager::class);

        $this->systemMetrics = [
            'manager_status' => 'running',
            'total_requests' => rand(100, 1000), // TODO: Implement actual metrics
            'average_response_time' => rand(50, 200).'ms',
            'error_rate' => rand(0, 5).'%',
            'uptime' => '99.9%',
            'memory_usage' => round(memory_get_usage(true) / 1024 / 1024, 2).'MB',
            'connections_pool_size' => count($this->activeConnections),
        ];
    }

    public function refreshConnection(int $connectionId): void
    {
        $connection = McpConnection::find($connectionId);

        if (! $connection) {
            return;
        }

        $manager = app(PersistentMcpManager::class);

        // Stop and restart connection
        $manager->stopConnection((string) $connectionId);
        $started = $manager->startConnection($connection);

        if ($started) {
            $connection->update(['status' => 'active']);
        }

        $this->loadActiveConnections();
        $this->loadConnectionStats();

        \Filament\Notifications\Notification::make()
            ->title('Connection Refreshed')
            ->body("Connection '{$connection->name}' has been refreshed")
            ->success()
            ->send();
    }

    public function stopConnection(int $connectionId): void
    {
        $connection = McpConnection::find($connectionId);

        if (! $connection) {
            return;
        }

        $manager = app(PersistentMcpManager::class);
        $manager->stopConnection((string) $connectionId);

        $connection->update(['status' => 'inactive']);

        $this->loadActiveConnections();
        $this->loadConnectionStats();

        \Filament\Notifications\Notification::make()
            ->title('Connection Stopped')
            ->body("Connection '{$connection->name}' has been stopped")
            ->warning()
            ->send();
    }

    public function startConnection(int $connectionId): void
    {
        $connection = McpConnection::find($connectionId);

        if (! $connection) {
            return;
        }

        $manager = app(PersistentMcpManager::class);
        $started = $manager->startConnection($connection);

        if ($started) {
            $connection->update(['status' => 'active']);

            \Filament\Notifications\Notification::make()
                ->title('Connection Started')
                ->body("Connection '{$connection->name}' has been started")
                ->success()
                ->send();
        } else {
            $connection->update(['status' => 'error']);

            \Filament\Notifications\Notification::make()
                ->title('Connection Failed')
                ->body("Failed to start connection '{$connection->name}'")
                ->danger()
                ->send();
        }

        $this->loadActiveConnections();
        $this->loadConnectionStats();
    }

    public function getListeners(): array
    {
        return [
            'echo:mcp-connections,connection.status.changed' => 'handleConnectionStatusChanged',
            'echo:mcp-server,server.metrics.updated' => 'handleServerMetricsUpdated',
        ];
    }
}
