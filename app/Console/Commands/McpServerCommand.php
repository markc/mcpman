<?php

namespace App\Console\Commands;

use App\Models\ApiKey;
use App\Services\McpProcessOrchestrator;
use Illuminate\Console\Command;

class McpServerCommand extends Command
{
    protected $signature = 'mcp:server {action : Action (start|stop|restart|status)} {--port=8080 : Port for HTTP server} {--daemon : Run as daemon}';

    protected $description = 'Manage the MCP server process';

    private McpProcessOrchestrator $orchestrator;

    public function __construct(McpProcessOrchestrator $orchestrator)
    {
        parent::__construct();
        $this->orchestrator = $orchestrator;
    }

    public function handle(): int
    {
        $action = $this->argument('action');
        $port = $this->option('port');
        $daemon = $this->option('daemon');

        return match ($action) {
            'start' => $this->startServer($port, $daemon),
            'stop' => $this->stopServer(),
            'restart' => $this->restartServer($port, $daemon),
            'status' => $this->showServerStatus(),
            default => $this->handleInvalidAction($action)
        };
    }

    private function startServer(int $port, bool $daemon): int
    {
        try {
            $this->info("Starting MCP server on port {$port}...");

            if ($daemon) {
                $this->info('Running in daemon mode');
            }

            // Check if server is already running
            if ($this->orchestrator->isRunning()) {
                $this->warn('MCP server is already running');

                return 0;
            }

            // Start the orchestrator
            $result = $this->orchestrator->startProcesses();

            if ($result['success']) {
                $this->info('✅ MCP server started successfully');
                $this->displayServerInfo($port);

                if (! $daemon) {
                    $this->info('Press Ctrl+C to stop the server');
                    $this->monitorServer();
                }

                return 0;
            } else {
                $this->error('❌ Failed to start MCP server');
                $this->error($result['message'] ?? 'Unknown error');

                return 1;
            }

        } catch (\Exception $e) {
            $this->error("Error starting server: {$e->getMessage()}");

            return 1;
        }
    }

    private function stopServer(): int
    {
        try {
            $this->info('Stopping MCP server...');

            $result = $this->orchestrator->stopProcesses();

            if ($result['success']) {
                $this->info('✅ MCP server stopped successfully');

                return 0;
            } else {
                $this->error('❌ Failed to stop MCP server');
                $this->error($result['message'] ?? 'Unknown error');

                return 1;
            }

        } catch (\Exception $e) {
            $this->error("Error stopping server: {$e->getMessage()}");

            return 1;
        }
    }

    private function restartServer(int $port, bool $daemon): int
    {
        $this->info('Restarting MCP server...');

        $stopResult = $this->stopServer();
        if ($stopResult !== 0) {
            return $stopResult;
        }

        sleep(2); // Wait a moment before restarting

        return $this->startServer($port, $daemon);
    }

    private function showServerStatus(): int
    {
        $status = $this->orchestrator->getStatus();

        $this->info('MCP Server Status');
        $this->info('================');

        $this->table(
            ['Component', 'Status', 'PID', 'Uptime', 'Memory'],
            [
                [
                    'Main Process',
                    $this->formatStatus($status['main_process'] ?? 'unknown'),
                    $status['main_pid'] ?? 'N/A',
                    $status['uptime'] ?? 'N/A',
                    $status['memory_usage'] ?? 'N/A',
                ],
                [
                    'HTTP Server',
                    $this->formatStatus($status['http_server'] ?? 'unknown'),
                    $status['http_pid'] ?? 'N/A',
                    $status['http_uptime'] ?? 'N/A',
                    $status['http_memory'] ?? 'N/A',
                ],
                [
                    'Queue Worker',
                    $this->formatStatus($status['queue_worker'] ?? 'unknown'),
                    $status['queue_pid'] ?? 'N/A',
                    $status['queue_uptime'] ?? 'N/A',
                    $status['queue_memory'] ?? 'N/A',
                ],
            ]
        );

        // Show connection stats
        $connections = $status['connections'] ?? [];
        if (! empty($connections)) {
            $this->newLine();
            $this->info('Active Connections');
            $this->info('==================');

            $this->table(
                ['ID', 'Type', 'Status', 'Last Activity'],
                collect($connections)->map(function ($conn) {
                    return [
                        $conn['id'] ?? 'N/A',
                        $conn['type'] ?? 'unknown',
                        $this->formatStatus($conn['status'] ?? 'unknown'),
                        $conn['last_activity'] ?? 'N/A',
                    ];
                })->toArray()
            );
        }

        return 0;
    }

    private function displayServerInfo(int $port): void
    {
        $this->newLine();
        $this->info('Server Information');
        $this->info('==================');
        $this->info("HTTP Endpoint: http://localhost:{$port}/api/mcp");
        $this->info('Protocol: JSON-RPC 2.0');
        $this->info('Authentication: API Key required');

        // Show example API keys
        $apiKeys = ApiKey::where('is_active', true)->limit(3)->get();
        if ($apiKeys->isNotEmpty()) {
            $this->newLine();
            $this->info('Available API Keys:');
            foreach ($apiKeys as $key) {
                $this->info("  {$key->name}: {$key->key}");
            }
        }

        $this->newLine();
    }

    private function monitorServer(): void
    {
        while ($this->orchestrator->isRunning()) {
            sleep(5);

            // Check server health
            $health = $this->orchestrator->checkHealth();
            if (! $health['healthy']) {
                $this->warn('Server health check failed: '.$health['message']);
            }
        }

        $this->info('Server stopped');
    }

    private function formatStatus(string $status): string
    {
        return match ($status) {
            'running', 'active' => '<fg=green>●</> Running',
            'stopped', 'inactive' => '<fg=red>●</> Stopped',
            'error' => '<fg=red>●</> Error',
            'starting' => '<fg=yellow>●</> Starting',
            'stopping' => '<fg=yellow>●</> Stopping',
            default => "<fg=gray>●</> {$status}"
        };
    }

    private function handleInvalidAction(string $action): int
    {
        $this->error("Invalid action: {$action}");
        $this->info('Available actions: start, stop, restart, status');

        return 1;
    }
}
