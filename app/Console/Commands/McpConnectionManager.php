<?php

namespace App\Console\Commands;

use App\Services\BidirectionalMcpClient;
use Illuminate\Console\Command;

class McpConnectionManager extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'mcp:connections 
                            {action : Action to perform (start|stop|status|test)}
                            {--connection= : Specific connection to manage}
                            {--auto-restart : Automatically restart failed connections}';

    /**
     * The console command description.
     */
    protected $description = 'Manage bidirectional MCP connections to external Claude processes';

    public function __construct(
        private BidirectionalMcpClient $mcpClient
    ) {
        parent::__construct();
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $action = $this->argument('action');

        return match ($action) {
            'start' => $this->startConnections(),
            'stop' => $this->stopConnections(),
            'status' => $this->showStatus(),
            'test' => $this->testConnections(),
            default => $this->error("Unknown action: {$action}") ?: Command::FAILURE,
        };
    }

    /**
     * Start MCP connections
     */
    private function startConnections(): int
    {
        $this->info('🚀 Starting bidirectional MCP connections...');
        $this->newLine();

        try {
            $results = $this->mcpClient->initializeConnections();

            $this->displayConnectionResults($results);

            $successCount = collect($results)->where('status', 'connected')->count();
            $totalCount = count($results);

            if ($successCount === $totalCount) {
                $this->info("✅ All {$totalCount} connections established successfully!");

                return Command::SUCCESS;
            } else {
                $this->warn("⚠️  {$successCount}/{$totalCount} connections established.");

                return Command::FAILURE;
            }

        } catch (\Exception $e) {
            $this->error('❌ Failed to start connections: '.$e->getMessage());

            return Command::FAILURE;
        }
    }

    /**
     * Stop MCP connections
     */
    private function stopConnections(): int
    {
        $this->info('🛑 Stopping bidirectional MCP connections...');

        try {
            $this->mcpClient->closeConnections();
            $this->info('✅ All connections closed successfully.');

            return Command::SUCCESS;

        } catch (\Exception $e) {
            $this->error('❌ Failed to stop connections: '.$e->getMessage());

            return Command::FAILURE;
        }
    }

    /**
     * Show connection status
     */
    private function showStatus(): int
    {
        $this->info('📊 MCP Connection Status');
        $this->newLine();

        try {
            $status = $this->mcpClient->getConnectionsStatus();

            if (empty($status)) {
                $this->warn('No connections configured or active.');

                return Command::SUCCESS;
            }

            $this->displayConnectionStatus($status);

            return Command::SUCCESS;

        } catch (\Exception $e) {
            $this->error('❌ Failed to get status: '.$e->getMessage());

            return Command::FAILURE;
        }
    }

    /**
     * Test connections
     */
    private function testConnections(): int
    {
        $this->info('🧪 Testing MCP connections...');
        $this->newLine();

        try {
            // First get status
            $status = $this->mcpClient->getConnectionsStatus();

            if (empty($status)) {
                $this->warn('No connections to test. Run "mcp:connections start" first.');

                return Command::FAILURE;
            }

            // Test each connection
            foreach ($status as $name => $info) {
                $this->testConnection($name, $info);
            }

            return Command::SUCCESS;

        } catch (\Exception $e) {
            $this->error('❌ Test failed: '.$e->getMessage());

            return Command::FAILURE;
        }
    }

    /**
     * Display connection results
     */
    private function displayConnectionResults(array $results): void
    {
        $rows = [];

        foreach ($results as $name => $result) {
            $status = $result['status'];
            $statusIcon = $status === 'connected' ? '✅' : '❌';
            $type = $result['type'] ?? 'unknown';
            $time = $result['established_at'] ?? $result['failed_at'] ?? 'unknown';
            $error = $result['error'] ?? '';

            $rows[] = [
                $statusIcon.' '.$name,
                ucfirst($status),
                $type,
                $time,
                $error ? substr($error, 0, 50).'...' : '',
            ];
        }

        $this->table(
            ['Connection', 'Status', 'Type', 'Time', 'Error'],
            $rows
        );
    }

    /**
     * Display connection status
     */
    private function displayConnectionStatus(array $status): void
    {
        $rows = [];

        foreach ($status as $name => $info) {
            $runningIcon = ($info['running'] ?? false) ? '🟢' : '🔴';
            $type = $info['type'] ?? 'unknown';
            $initialized = ($info['initialized'] ?? false) ? 'Yes' : 'No';
            $capabilities = count($info['capabilities'] ?? []);
            $processInfo = '';

            if ($type === 'stdio' && isset($info['process_id'])) {
                $processRunning = $info['process_running'] ?? false;
                $processInfo = "PID: {$info['process_id']} ".($processRunning ? '(running)' : '(stopped)');
            }

            $rows[] = [
                $runningIcon.' '.$name,
                ucfirst($type),
                $initialized,
                $capabilities,
                $processInfo,
            ];
        }

        $this->table(
            ['Connection', 'Type', 'Initialized', 'Capabilities', 'Process Info'],
            $rows
        );
    }

    /**
     * Test a specific connection
     */
    private function testConnection(string $name, array $info): void
    {
        $this->line("Testing connection: {$name}");

        if (! ($info['running'] ?? false)) {
            $this->error('  ❌ Connection not running');

            return;
        }

        if (! ($info['initialized'] ?? false)) {
            $this->error('  ❌ Connection not initialized');

            return;
        }

        // Test by sending a simple notification
        try {
            $testError = [
                'error_details' => [
                    'type' => 'test',
                    'message' => 'Test error notification from MCPman',
                    'file_path' => __FILE__,
                    'line_number' => __LINE__,
                ],
                'context' => [
                    'test' => true,
                    'timestamp' => now()->toISOString(),
                ],
                'suggested_actions' => ['This is a test notification'],
                'auto_fix_recommended' => false,
            ];

            $result = $this->mcpClient->notifyError($testError);

            if (isset($result[$name]) && $result[$name]['status'] === 'sent') {
                $this->info('  ✅ Test notification sent successfully');
            } else {
                $this->warn('  ⚠️  Test notification may have failed');
                $this->line('  Result: '.json_encode($result[$name] ?? []));
            }

        } catch (\Exception $e) {
            $this->error('  ❌ Test failed: '.$e->getMessage());
        }
    }
}
