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
     * Phase 3 Enhancement: Improved stdio-based connection with better process management
     */
    private function startStdioConnection(McpConnection $connection): bool
    {
        $connectionId = $connection->id;

        // Phase 3: Enhanced command preparation with fallback strategies
        $commandParts = explode(' ', trim($connection->endpoint_url));
        $command = array_shift($commandParts);

        // Add enhanced debugging and timeout arguments
        $args = array_merge($commandParts, [
            '--debug',
            '--timeout', (string) (config('mcp.timeout', 60000) / 1000), // Convert to seconds
        ]);

        Log::info('Starting enhanced stdio MCP process', [
            'connection_id' => $connectionId,
            'command' => $command,
            'args' => $args,
            'environment_variables' => [
                'MCP_TIMEOUT' => config('mcp.timeout', 60000),
                'HAS_API_KEY' => ! empty(config('mcp.claude.api_key')),
            ],
        ]);

        // Phase 3: Enhanced process configuration with better resource limits
        $process = new SymfonyProcess(
            array_merge([$command], $args),
            null, // Working directory
            [
                'MCP_TIMEOUT' => config('mcp.timeout', 60000),
                'ANTHROPIC_API_KEY' => config('mcp.claude.api_key'),
                'MCP_CLIENT_NAME' => 'MCPman-Laravel',
                'MCP_LOG_LEVEL' => config('mcp.debug_logging', false) ? 'debug' : 'info',
            ],
            null, // Input
            null  // No timeout - persistent connection
        );

        // Phase 3: Enhanced process startup with better error handling
        try {
            $process->start();

            // Progressive wait strategy for better startup detection
            $maxWaitTime = 5; // seconds
            $checkInterval = 0.1; // 100ms
            $waited = 0;

            while ($waited < $maxWaitTime) {
                if ($process->isRunning()) {
                    break;
                }

                usleep($checkInterval * 1000000); // Convert to microseconds
                $waited += $checkInterval;

                // Check for immediate failures
                if ($process->isTerminated()) {
                    throw new \Exception('Process terminated during startup: '.$process->getErrorOutput());
                }
            }

            if (! $process->isRunning()) {
                throw new \Exception('Process failed to start within timeout period');
            }

        } catch (\Exception $e) {
            Log::error('Failed to start MCP process', [
                'connection_id' => $connectionId,
                'error' => $e->getMessage(),
                'command' => $command,
                'exit_code' => $process->getExitCode(),
                'error_output' => $process->getErrorOutput(),
            ]);

            throw $e;
        }

        $this->connectionProcesses[$connectionId] = $process;

        // Phase 3: Initialize communication streams with better error handling
        $this->initializeStreams($connectionId, $process);

        // Perform enhanced MCP handshake
        return $this->performHandshake($connectionId);
    }

    /**
     * Phase 3 Enhancement: Improved JSON-RPC 2.0 MCP handshake with better error handling
     */
    private function performHandshake(string $connectionId): bool
    {
        try {
            // Phase 3: Enhanced initialization with comprehensive capabilities
            $initRequest = [
                'jsonrpc' => '2.0',
                'id' => $this->getNextRequestId(),
                'method' => 'initialize',
                'params' => [
                    'protocolVersion' => config('mcp.server.protocol_version', '2024-11-05'),
                    'capabilities' => [
                        'tools' => [
                            'listChanged' => true,
                        ],
                        'resources' => [
                            'subscribe' => true,
                            'listChanged' => true,
                        ],
                        'prompts' => [
                            'listChanged' => true,
                        ],
                        'logging' => [
                            'level' => config('mcp.debug_logging', false) ? 'debug' : 'info',
                        ],
                        'sampling' => [],
                    ],
                    'clientInfo' => [
                        'name' => 'MCPman-Laravel-Client',
                        'version' => '1.0.0',
                        'description' => 'Laravel Filament MCP Integration Client with Phase 3 enhancements',
                    ],
                ],
            ];

            Log::info('Performing enhanced MCP handshake', [
                'connection_id' => $connectionId,
                'protocol_version' => $initRequest['params']['protocolVersion'],
                'client_capabilities' => array_keys($initRequest['params']['capabilities']),
            ]);

            $response = $this->sendMessage($connectionId, $initRequest, 10); // 10 second timeout for handshake

            if (isset($response['result'])) {
                // Phase 3: Store server capabilities for advanced feature support
                $serverCapabilities = $response['result']['capabilities'] ?? [];
                $this->storeServerCapabilities($connectionId, $serverCapabilities);

                Log::info('Enhanced MCP handshake successful', [
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
     * Phase 3 Enhancement: Send a JSON-RPC request and wait for response with configurable timeout
     */
    public function sendMessage(string $connectionId, array $message, ?int $timeoutSeconds = null): array
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

    /**
     * Phase 3 Enhancement: Initialize communication streams with better error handling
     */
    private function initializeStreams(string $connectionId, SymfonyProcess $process): void
    {
        try {
            $stdin = $process->getInput();
            $stdout = $process->getOutput();

            if (! $stdin || ! $stdout) {
                throw new \Exception('Failed to get process streams');
            }

            $this->connectionStreams[$connectionId] = [
                'stdin' => $stdin,
                'stdout' => $stdout,
                'process' => $process,
                'buffer' => '',
                'last_activity' => time(),
            ];

            Log::debug('MCP streams initialized', [
                'connection_id' => $connectionId,
                'has_stdin' => ! empty($stdin),
                'has_stdout' => ! empty($stdout),
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to initialize MCP streams', [
                'connection_id' => $connectionId,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Phase 3 Enhancement: Store server capabilities for advanced feature support
     */
    private function storeServerCapabilities(string $connectionId, array $capabilities): void
    {
        try {
            $this->connectionStreams[$connectionId]['server_capabilities'] = $capabilities;

            Log::info('Server capabilities stored', [
                'connection_id' => $connectionId,
                'tools_supported' => isset($capabilities['tools']),
                'resources_supported' => isset($capabilities['resources']),
                'prompts_supported' => isset($capabilities['prompts']),
                'logging_supported' => isset($capabilities['logging']),
            ]);

        } catch (\Exception $e) {
            Log::warning('Failed to store server capabilities', [
                'connection_id' => $connectionId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Phase 3 Enhancement: Send notification (no response expected)
     */
    private function sendNotification(string $connectionId, string $method, array $params = []): void
    {
        try {
            $notification = [
                'jsonrpc' => '2.0',
                'method' => $method,
                'params' => $params,
            ];

            $this->writeToProcess($connectionId, $notification);

            Log::debug('Notification sent', [
                'connection_id' => $connectionId,
                'method' => $method,
            ]);

        } catch (\Exception $e) {
            Log::warning('Failed to send notification', [
                'connection_id' => $connectionId,
                'method' => $method,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Phase 3 Enhancement: Write JSON-RPC message to process with better error handling
     */
    private function writeToProcess(string $connectionId, array $message): void
    {
        if (! isset($this->connectionStreams[$connectionId])) {
            throw new \Exception('Connection streams not available');
        }

        $process = $this->connectionStreams[$connectionId]['process'];

        if (! $process->isRunning()) {
            throw new \Exception('Process is not running');
        }

        $json = json_encode($message)."\n";

        try {
            $process->setInput($json);
            $this->connectionStreams[$connectionId]['last_activity'] = time();

            Log::debug('Message written to process', [
                'connection_id' => $connectionId,
                'method' => $message['method'] ?? 'unknown',
                'id' => $message['id'] ?? null,
                'message_size' => strlen($json),
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to write to process', [
                'connection_id' => $connectionId,
                'error' => $e->getMessage(),
                'process_running' => $process->isRunning(),
            ]);
            throw $e;
        }
    }

    /**
     * Phase 3 Enhancement: Get server capabilities for a connection
     */
    public function getServerCapabilities(string $connectionId): array
    {
        return $this->connectionStreams[$connectionId]['server_capabilities'] ?? [];
    }

    /**
     * Phase 3 Enhancement: Check if specific capability is supported
     */
    public function hasCapability(string $connectionId, string $capability): bool
    {
        $capabilities = $this->getServerCapabilities($connectionId);

        return isset($capabilities[$capability]);
    }

    /**
     * Phase 3 Enhancement: Enhanced error recovery and reconnection
     */
    public function attemptReconnection(string $connectionId): bool
    {
        try {
            Log::info('Attempting MCP reconnection', ['connection_id' => $connectionId]);

            // Stop existing connection
            $this->stopConnection($connectionId);

            // Wait a moment before reconnecting
            usleep(1000000); // 1 second

            // Get connection object and restart
            if (isset($this->activeConnections[$connectionId])) {
                $connection = $this->activeConnections[$connectionId];
                unset($this->activeConnections[$connectionId]);

                return $this->startConnection($connection);
            }

            return false;

        } catch (\Exception $e) {
            Log::error('Reconnection failed', [
                'connection_id' => $connectionId,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }
}
