<?php

namespace App\Services;

use App\Events\McpConnectionStatusChanged;
use App\Models\McpConnection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Symfony\Component\Process\Process as SymfonyProcess;

class PersistentMcpManager
{
    private array $activeConnections = [];

    private array $connectionProcesses = [];

    private array $connectionStreams = [];

    private int $requestIdCounter = 1;

    public function __construct()
    {
        // Register shutdown handler to clean up processes
        register_shutdown_function([$this, 'cleanup']);
    }

    /**
     * Start a persistent MCP connection
     */
    public function startConnection(McpConnection $connection): bool
    {
        $connectionId = $connection->id;

        if (isset($this->activeConnections[$connectionId])) {
            return true; // Already connected
        }

        try {
            Log::info('Starting persistent MCP connection', [
                'connection_id' => $connectionId,
                'name' => $connection->name,
                'transport' => $connection->transport_type,
            ]);

            $success = match ($connection->transport_type) {
                'stdio' => $this->startStdioConnection($connection),
                'http' => $this->startHttpConnection($connection),
                'websocket' => $this->startWebSocketConnection($connection),
                default => throw new \Exception("Unsupported transport type: {$connection->transport_type}")
            };

            if ($success) {
                $this->activeConnections[$connectionId] = $connection;
                $connection->markAsConnected();

                // Broadcast status change
                McpConnectionStatusChanged::dispatch($connection, 'connected');

                return true;
            }

            return false;

        } catch (\Exception $e) {
            Log::error('Failed to start MCP connection', [
                'connection_id' => $connectionId,
                'error' => $e->getMessage(),
            ]);

            $connection->markAsError($e->getMessage());
            McpConnectionStatusChanged::dispatch($connection, 'error');

            return false;
        }
    }

    /**
     * Start stdio-based connection (for Claude Code)
     */
    private function startStdioConnection(McpConnection $connection): bool
    {
        $connectionId = $connection->id;

        // Prepare command based on endpoint_url (which contains the command for stdio)
        $commandParts = explode(' ', trim($connection->endpoint_url));
        $command = array_shift($commandParts);
        $args = array_merge($commandParts, ['--debug']);

        Log::info('Starting stdio MCP process', [
            'connection_id' => $connectionId,
            'command' => $command,
            'args' => $args,
        ]);

        // Create persistent process
        $process = new SymfonyProcess(
            array_merge([$command], $args),
            null,
            [
                'MCP_TIMEOUT' => config('mcp.timeout', 60000),
                'ANTHROPIC_API_KEY' => config('mcp.claude.api_key'),
            ],
            null,
            null // No timeout - persistent connection
        );

        $process->start();

        // Wait a moment for process to initialize
        usleep(500000); // 0.5 seconds

        if (! $process->isRunning()) {
            throw new \Exception('Process failed to start: '.$process->getErrorOutput());
        }

        $this->connectionProcesses[$connectionId] = $process;

        // Perform MCP handshake
        return $this->performHandshake($connectionId);
    }

    /**
     * Perform JSON-RPC 2.0 MCP handshake
     */
    private function performHandshake(string $connectionId): bool
    {
        try {
            $initRequest = [
                'jsonrpc' => '2.0',
                'id' => $this->getNextRequestId(),
                'method' => 'initialize',
                'params' => [
                    'protocolVersion' => config('mcp.server.protocol_version', '2024-11-05'),
                    'capabilities' => [
                        'tools' => [],
                        'resources' => [],
                        'prompts' => [],
                    ],
                    'clientInfo' => [
                        'name' => 'MCPman-Laravel-Client',
                        'version' => '1.0.0',
                    ],
                ],
            ];

            $response = $this->sendMessage($connectionId, $initRequest);

            if (isset($response['result'])) {
                Log::info('MCP handshake successful', [
                    'connection_id' => $connectionId,
                    'server_info' => $response['result']['serverInfo'] ?? 'unknown',
                    'capabilities' => $response['result']['capabilities'] ?? [],
                ]);

                // Send initialized notification
                $this->sendNotification($connectionId, 'notifications/initialized');

                return true;
            }

            Log::error('MCP handshake failed', [
                'connection_id' => $connectionId,
                'response' => $response,
            ]);

            return false;

        } catch (\Exception $e) {
            Log::error('MCP handshake exception', [
                'connection_id' => $connectionId,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Send a JSON-RPC request and wait for response
     */
    public function sendMessage(string $connectionId, array $message): array
    {
        if (! isset($this->connectionProcesses[$connectionId])) {
            throw new \Exception("No active connection for ID: $connectionId");
        }

        $process = $this->connectionProcesses[$connectionId];

        if (! $process->isRunning()) {
            throw new \Exception("Connection process is not running for ID: $connectionId");
        }

        $jsonMessage = json_encode($message)."\n";

        Log::debug('Sending MCP message', [
            'connection_id' => $connectionId,
            'method' => $message['method'] ?? 'notification',
            'id' => $message['id'] ?? null,
        ]);

        // Send message to process stdin
        $process->getInput()->write($jsonMessage);

        // Read response (only for requests with ID)
        if (isset($message['id'])) {
            return $this->readResponse($connectionId, $message['id'], 15); // 15 second timeout
        }

        return []; // No response expected for notifications
    }

    /**
     * Send a notification (no response expected)
     */
    public function sendNotification(string $connectionId, string $method, array $params = []): void
    {
        $notification = [
            'jsonrpc' => '2.0',
            'method' => $method,
            'params' => $params,
        ];

        $this->sendMessage($connectionId, $notification);
    }

    /**
     * Read response from process stdout
     */
    private function readResponse(string $connectionId, int $requestId, int $timeoutSeconds): array
    {
        $process = $this->connectionProcesses[$connectionId];
        $startTime = time();

        while (time() - $startTime < $timeoutSeconds) {
            if (! $process->isRunning()) {
                throw new \Exception('Process died while waiting for response');
            }

            // Try to read from stdout
            $output = $process->getIncrementalOutput();

            if (! empty($output)) {
                $lines = explode("\n", trim($output));

                foreach ($lines as $line) {
                    $line = trim($line);
                    if (empty($line)) {
                        continue;
                    }

                    $response = json_decode($line, true);

                    if (json_last_error() === JSON_ERROR_NONE) {
                        // Check if this is the response we're waiting for
                        if (isset($response['id']) && $response['id'] == $requestId) {
                            return $response;
                        }

                        // Handle notifications or other messages
                        $this->handleIncomingMessage($connectionId, $response);
                    }
                }
            }

            usleep(100000); // 100ms
        }

        throw new \Exception("Timeout waiting for response to request ID: $requestId");
    }

    /**
     * Handle incoming messages (notifications, etc.)
     */
    private function handleIncomingMessage(string $connectionId, array $message): void
    {
        if (isset($message['method'])) {
            Log::debug('Received MCP notification', [
                'connection_id' => $connectionId,
                'method' => $message['method'],
                'params' => $message['params'] ?? [],
            ]);

            // Handle specific notifications if needed
            // For now, just log them
        }
    }

    /**
     * Call a tool on a connection
     */
    public function callTool(string $connectionId, string $toolName, array $arguments = []): array
    {
        $request = [
            'jsonrpc' => '2.0',
            'id' => $this->getNextRequestId(),
            'method' => 'tools/call',
            'params' => [
                'name' => $toolName,
                'arguments' => $arguments,
            ],
        ];

        return $this->sendMessage($connectionId, $request);
    }

    /**
     * List available tools on a connection
     */
    public function listTools(string $connectionId): array
    {
        $request = [
            'jsonrpc' => '2.0',
            'id' => $this->getNextRequestId(),
            'method' => 'tools/list',
        ];

        $response = $this->sendMessage($connectionId, $request);

        return $response['result']['tools'] ?? [];
    }

    /**
     * Execute a conversation
     */
    public function executeConversation(string $connectionId, array $messages): array
    {
        // For Claude Code, we'll use the chat tool
        $request = [
            'jsonrpc' => '2.0',
            'id' => $this->getNextRequestId(),
            'method' => 'tools/call',
            'params' => [
                'name' => 'chat',
                'arguments' => [
                    'message' => end($messages)['content'] ?? '',
                ],
            ],
        ];

        return $this->sendMessage($connectionId, $request);
    }

    /**
     * Check if connection is active
     */
    public function isConnectionActive(string $connectionId): bool
    {
        if (! isset($this->connectionProcesses[$connectionId])) {
            return false;
        }

        return $this->connectionProcesses[$connectionId]->isRunning();
    }

    /**
     * Stop a connection
     */
    public function stopConnection(string $connectionId): void
    {
        if (isset($this->connectionProcesses[$connectionId])) {
            $process = $this->connectionProcesses[$connectionId];

            if ($process->isRunning()) {
                $process->stop();
            }

            unset($this->connectionProcesses[$connectionId]);
        }

        if (isset($this->activeConnections[$connectionId])) {
            $connection = $this->activeConnections[$connectionId];
            $connection->update(['status' => 'inactive']);

            McpConnectionStatusChanged::dispatch($connection, 'disconnected');

            unset($this->activeConnections[$connectionId]);
        }
    }

    /**
     * Get next request ID
     */
    private function getNextRequestId(): int
    {
        return $this->requestIdCounter++;
    }

    /**
     * Start HTTP connection (placeholder)
     */
    private function startHttpConnection(McpConnection $connection): bool
    {
        // TODO: Implement HTTP+SSE transport
        throw new \Exception('HTTP transport not yet implemented');
    }

    /**
     * Start WebSocket connection (placeholder)
     */
    private function startWebSocketConnection(McpConnection $connection): bool
    {
        // TODO: Implement WebSocket transport
        throw new \Exception('WebSocket transport not yet implemented');
    }

    /**
     * Get all active connections
     */
    public function getActiveConnections(): array
    {
        return $this->activeConnections;
    }

    /**
     * Cleanup all connections on shutdown
     */
    public function cleanup(): void
    {
        foreach (array_keys($this->connectionProcesses) as $connectionId) {
            $this->stopConnection($connectionId);
        }
    }

    /**
     * Health check for connection
     */
    public function healthCheck(string $connectionId): bool
    {
        try {
            if (! $this->isConnectionActive($connectionId)) {
                return false;
            }

            // Send a ping request
            $request = [
                'jsonrpc' => '2.0',
                'id' => $this->getNextRequestId(),
                'method' => 'ping',
            ];

            $response = $this->sendMessage($connectionId, $request);

            return isset($response['result']) || isset($response['error']);

        } catch (\Exception $e) {
            Log::warning('MCP health check failed', [
                'connection_id' => $connectionId,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }
}
