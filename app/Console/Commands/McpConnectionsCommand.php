<?php

namespace App\Console\Commands;

use App\Models\McpConnection;
use App\Services\McpClient;
use App\Services\PersistentMcpManager;
use Illuminate\Console\Command;

class McpConnectionsCommand extends Command
{
    protected $signature = 'mcp:connections {action : Action to perform (start|stop|status|test)} {connection? : Connection name (optional)}';

    protected $description = 'Manage MCP connections (start, stop, status, test)';

    private PersistentMcpManager $manager;

    public function __construct(PersistentMcpManager $manager)
    {
        parent::__construct();
        $this->manager = $manager;
    }

    public function handle(): int
    {
        $action = $this->argument('action');
        $connectionName = $this->argument('connection');

        return match ($action) {
            'start' => $this->startConnections($connectionName),
            'stop' => $this->stopConnections($connectionName),
            'status' => $this->showStatus($connectionName),
            'test' => $this->testConnections($connectionName),
            default => $this->handleInvalidAction($action)
        };
    }

    private function startConnections(?string $connectionName): int
    {
        $connections = $this->getConnections($connectionName);

        if ($connections->isEmpty()) {
            $this->error('No connections found.');

            return 1;
        }

        $started = 0;
        $failed = 0;

        foreach ($connections as $connection) {
            $this->info("Starting connection: {$connection->name}");

            try {
                if ($this->manager->startConnection($connection)) {
                    $this->info("✅ {$connection->name} started successfully");
                    $started++;
                } else {
                    $this->error("❌ Failed to start {$connection->name}");
                    $failed++;
                }
            } catch (\Exception $e) {
                $this->error("❌ Error starting {$connection->name}: {$e->getMessage()}");
                $failed++;
            }
        }

        $this->info("\nSummary: {$started} started, {$failed} failed");

        return $failed > 0 ? 1 : 0;
    }

    private function stopConnections(?string $connectionName): int
    {
        $connections = $this->getConnections($connectionName);

        if ($connections->isEmpty()) {
            $this->error('No connections found.');

            return 1;
        }

        $stopped = 0;
        $failed = 0;

        foreach ($connections as $connection) {
            $this->info("Stopping connection: {$connection->name}");

            try {
                if ($this->manager->stopConnection($connection)) {
                    $this->info("✅ {$connection->name} stopped successfully");
                    $stopped++;
                } else {
                    $this->error("❌ Failed to stop {$connection->name}");
                    $failed++;
                }
            } catch (\Exception $e) {
                $this->error("❌ Error stopping {$connection->name}: {$e->getMessage()}");
                $failed++;
            }
        }

        $this->info("\nSummary: {$stopped} stopped, {$failed} failed");

        return $failed > 0 ? 1 : 0;
    }

    private function showStatus(?string $connectionName): int
    {
        $connections = $this->getConnections($connectionName);

        if ($connections->isEmpty()) {
            $this->error('No connections found.');

            return 1;
        }

        $this->table(
            ['Name', 'Type', 'Status', 'Health', 'Last Seen', 'Error Count'],
            $connections->map(function ($connection) {
                $health = $this->manager->checkConnectionHealth($connection);

                return [
                    $connection->name,
                    ucfirst($connection->transport_type),
                    $this->formatStatus($connection->status),
                    $this->formatHealth($health['status'] ?? 'unknown'),
                    $connection->last_seen_at?->diffForHumans() ?? 'Never',
                    $connection->error_count ?? 0,
                ];
            })->toArray()
        );

        return 0;
    }

    private function testConnections(?string $connectionName): int
    {
        $connections = $this->getConnections($connectionName);

        if ($connections->isEmpty()) {
            $this->error('No connections found.');

            return 1;
        }

        $passed = 0;
        $failed = 0;

        foreach ($connections as $connection) {
            $this->info("Testing connection: {$connection->name}");

            try {
                $client = new McpClient($connection, $this->manager);

                // Test basic connectivity
                if ($client->connect()) {
                    // Test a simple ping request
                    $response = $client->sendRequest('ping', []);

                    if ($response['success'] ?? false) {
                        $this->info("✅ {$connection->name} test passed");
                        $passed++;
                    } else {
                        $this->error("❌ {$connection->name} test failed: ".($response['error'] ?? 'Unknown error'));
                        $failed++;
                    }
                } else {
                    $this->error("❌ {$connection->name} connection failed");
                    $failed++;
                }
            } catch (\Exception $e) {
                $this->error("❌ Error testing {$connection->name}: {$e->getMessage()}");
                $failed++;
            }
        }

        $this->info("\nTest Summary: {$passed} passed, {$failed} failed");

        return $failed > 0 ? 1 : 0;
    }

    private function getConnections(?string $connectionName)
    {
        if ($connectionName) {
            return McpConnection::where('name', $connectionName)->get();
        }

        return McpConnection::where('is_active', true)->get();
    }

    private function formatStatus(string $status): string
    {
        return match ($status) {
            'active' => '<fg=green>●</> Active',
            'inactive' => '<fg=red>●</> Inactive',
            'error' => '<fg=red>●</> Error',
            'connecting' => '<fg=yellow>●</> Connecting',
            default => "<fg=gray>●</> {$status}"
        };
    }

    private function formatHealth(string $health): string
    {
        return match ($health) {
            'healthy' => '<fg=green>●</> Healthy',
            'unhealthy' => '<fg=red>●</> Unhealthy',
            'degraded' => '<fg=yellow>●</> Degraded',
            default => "<fg=gray>●</> {$health}"
        };
    }

    private function handleInvalidAction(string $action): int
    {
        $this->error("Invalid action: {$action}");
        $this->info('Available actions: start, stop, status, test');

        return 1;
    }
}
