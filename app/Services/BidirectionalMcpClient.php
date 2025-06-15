<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;

class BidirectionalMcpClient
{
    private array $connections = [];

    private array $config;

    private int $requestId = 1;

    public function __construct()
    {
        $this->config = config('mcp-client', []);
    }

    /**
     * Initialize all configured MCP connections
     */
    public function initializeConnections(): array
    {
        $results = [];

        foreach ($this->config['connections'] ?? [] as $name => $config) {
            if ($config['enabled'] ?? false) {
                $results[$name] = $this->initializeConnection($name, $config);
            }
        }

        return $results;
    }

    /**
     * Initialize a single MCP connection
     */
    private function initializeConnection(string $name, array $config): array
    {
        try {
            Log::info("Initializing MCP connection: {$name}", ['config' => $config]);

            $connection = match ($config['type']) {
                'stdio' => $this->initializeStdioConnection($name, $config),
                'http' => $this->initializeHttpConnection($name, $config),
                'websocket' => $this->initializeWebSocketConnection($name, $config),
                default => throw new \InvalidArgumentException("Unsupported connection type: {$config['type']}"),
            };

            $this->connections[$name] = $connection;

            return [
                'status' => 'connected',
                'connection_id' => $connection['id'],
                'type' => $config['type'],
                'established_at' => now()->toISOString(),
            ];

        } catch (\Exception $e) {
            Log::error("Failed to initialize MCP connection: {$name}", [
                'error' => $e->getMessage(),
                'config' => $config,
            ]);

            return [
                'status' => 'failed',
                'error' => $e->getMessage(),
                'type' => $config['type'],
                'failed_at' => now()->toISOString(),
            ];
        }
    }

    /**
     * Initialize stdio connection (for Claude processes)
     */
    private function initializeStdioConnection(string $name, array $config): array
    {
        $command = array_merge([$config['command']], $config['args'] ?? []);

        Log::info("Preparing stdio connection for {$name}", ['command' => $command]);

        // For simplicity, we'll validate the command exists but use Process::run for actual communication
        $testResult = Process::timeout(5)->run([
            $config['command'], '--version',
        ]);

        if (! $testResult->successful()) {
            throw new \RuntimeException('Claude CLI not available: '.$testResult->errorOutput());
        }

        Log::info('MCP stdio connection prepared', [
            'name' => $name,
            'command' => $command,
            'claude_version' => trim($testResult->output()),
        ]);

        return [
            'id' => $name,
            'type' => 'stdio',
            'command' => $command,
            'initialized' => true,
            'capabilities' => ['notifications', 'tools', 'resources'],
        ];
    }

    /**
     * Initialize HTTP connection
     */
    private function initializeHttpConnection(string $name, array $config): array
    {
        // HTTP connections are stateless, just validate endpoint
        $endpoint = $config['endpoint'];

        // Test connection with a ping
        $result = Process::timeout(10)->run([
            'curl', '-s', '-o', '/dev/null', '-w', '%{http_code}', $endpoint,
        ]);

        if ($result->exitCode() !== 0 || ! in_array(trim($result->output()), ['200', '404', '405'])) {
            throw new \RuntimeException("HTTP endpoint not accessible: {$endpoint}");
        }

        return [
            'id' => $name,
            'type' => 'http',
            'endpoint' => $endpoint,
            'auth' => $config['auth'] ?? null,
            'initialized' => true,
        ];
    }

    /**
     * Initialize WebSocket connection
     */
    private function initializeWebSocketConnection(string $name, array $config): array
    {
        // WebSocket implementation would go here
        throw new \RuntimeException('WebSocket connections not yet implemented');
    }

    /**
     * Send error notification to external Claude processes
     */
    public function notifyError(array $errorDetails): array
    {
        $results = [];
        $notificationConfig = $this->config['auto_notifications'] ?? [];

        if (! ($notificationConfig['enabled'] ?? true)) {
            return ['status' => 'disabled'];
        }

        $errorType = $errorDetails['error_details']['type'] ?? 'unknown';
        $errorTypeConfig = $notificationConfig['error_types'] ?? [];

        // Check if this error type should be notified
        if (! ($errorTypeConfig[$errorType] ?? false)) {
            Log::debug("Error type {$errorType} notifications disabled");

            return ['status' => 'filtered', 'reason' => 'error_type_disabled'];
        }

        // Send to all configured targets
        foreach ($notificationConfig['targets'] ?? [] as $targetName) {
            if (isset($this->connections[$targetName])) {
                $results[$targetName] = $this->sendErrorNotification($targetName, $errorDetails);
            } else {
                Log::warning("Target connection not found: {$targetName}");
                $results[$targetName] = ['status' => 'connection_not_found'];
            }
        }

        return $results;
    }

    /**
     * Send error notification to specific connection
     */
    private function sendErrorNotification(string $connectionName, array $errorDetails): array
    {
        try {
            $connection = $this->connections[$connectionName];

            $notification = [
                'jsonrpc' => '2.0',
                'method' => 'notification/error_detected',
                'params' => [
                    'error_id' => $this->generateErrorId($errorDetails),
                    'timestamp' => now()->toISOString(),
                    'source' => 'mcpman_log_monitor',
                    'error_details' => $errorDetails['error_details'] ?? [],
                    'context' => $this->prepareErrorContext($errorDetails),
                    'suggested_actions' => $errorDetails['suggested_actions'] ?? [],
                    'auto_fix_available' => $errorDetails['auto_fix_recommended'] ?? false,
                ],
            ];

            $result = match ($connection['type']) {
                'stdio' => $this->sendStdioNotification($connection, $notification),
                'http' => $this->sendHttpNotification($connection, $notification),
                'websocket' => $this->sendWebSocketNotification($connection, $notification),
                default => ['status' => 'unsupported_type'],
            };

            Log::info("Error notification sent to {$connectionName}", [
                'error_id' => $notification['params']['error_id'],
                'result' => $result,
            ]);

            return $result;

        } catch (\Exception $e) {
            Log::error("Failed to send error notification to {$connectionName}", [
                'error' => $e->getMessage(),
                'error_details' => $errorDetails,
            ]);

            return [
                'status' => 'failed',
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Send notification via stdio connection
     */
    private function sendStdioNotification(array $connection, array $notification): array
    {
        $command = $connection['command'];

        // Create a formatted message for Claude
        $message = $this->formatErrorMessageForClaude($notification);

        // Send via claude CLI with a prompt
        $result = Process::timeout(30)->run([
            $command[0], // claude
            '-p',
            $message,
        ]);

        return [
            'status' => $result->successful() ? 'sent' : 'failed',
            'method' => 'stdio',
            'exit_code' => $result->exitCode(),
            'response' => $result->successful() ? substr($result->output(), 0, 200).'...' : $result->errorOutput(),
        ];
    }

    /**
     * Send notification via HTTP
     */
    private function sendHttpNotification(array $connection, array $notification): array
    {
        $endpoint = $connection['endpoint'];
        $headers = ['Content-Type: application/json'];

        if (isset($connection['auth'])) {
            $auth = $connection['auth'];
            if ($auth['type'] === 'bearer' && isset($auth['token'])) {
                $headers[] = "Authorization: Bearer {$auth['token']}";
            }
        }

        $result = Process::timeout(30)->run([
            'curl',
            '-X', 'POST',
            '-H', implode(' -H ', array_map(fn ($h) => "'{$h}'", $headers)),
            '-d', json_encode($notification),
            $endpoint,
        ]);

        return [
            'status' => $result->successful() ? 'sent' : 'failed',
            'method' => 'http',
            'http_code' => $result->exitCode(),
            'response' => $result->output(),
        ];
    }

    /**
     * Send notification via WebSocket
     */
    private function sendWebSocketNotification(array $connection, array $notification): array
    {
        // WebSocket implementation would go here
        return ['status' => 'not_implemented'];
    }

    /**
     * Prepare error context for external Claude
     */
    private function prepareErrorContext(array $errorDetails): array
    {
        $context = $errorDetails['context'] ?? [];
        $maxLines = $this->config['auto_notifications']['max_context_lines'] ?? 20;

        // Limit file content size
        if (isset($context['file_content']) && is_array($context['file_content'])) {
            $fileContent = $context['file_content'];
            if (count($fileContent) > $maxLines) {
                $errorLine = (int) ($context['line_number'] ?? 0);
                $start = max(0, $errorLine - ($maxLines / 2));
                $context['file_content'] = array_slice($fileContent, $start, $maxLines, true);
                $context['content_truncated'] = true;
            }
        }

        // Add application info
        $context['application'] = [
            'name' => 'MCPman Laravel App',
            'version' => '1.0.0',
            'environment' => app()->environment(),
            'php_version' => PHP_VERSION,
            'laravel_version' => app()->version(),
        ];

        return $context;
    }

    /**
     * Generate unique error ID
     */
    private function generateErrorId(array $errorDetails): string
    {
        $components = [
            $errorDetails['error_details']['type'] ?? 'unknown',
            $errorDetails['error_details']['file_path'] ?? 'unknown',
            $errorDetails['error_details']['line_number'] ?? 0,
            $errorDetails['timestamp'] ?? now()->toISOString(),
        ];

        return 'mcpman_'.md5(implode('|', $components));
    }

    /**
     * Get status of all connections
     */
    public function getConnectionsStatus(): array
    {
        // Reinitialize connections if needed to get current status
        if (empty($this->connections)) {
            $this->initializeConnections();
        }

        $status = [];

        foreach ($this->connections as $name => $connection) {
            $status[$name] = [
                'type' => $connection['type'],
                'initialized' => $connection['initialized'] ?? false,
                'running' => $this->isConnectionActive($connection),
                'capabilities' => $connection['capabilities'] ?? [],
                'command' => $connection['command'] ?? null,
            ];
        }

        return $status;
    }

    /**
     * Check if connection is active
     */
    private function isConnectionActive(array $connection): bool
    {
        return match ($connection['type']) {
            'stdio' => $connection['initialized'] ?? false,
            'http' => true, // HTTP is stateless
            'websocket' => false, // Not implemented yet
            default => false,
        };
    }

    /**
     * Format error message for Claude CLI
     */
    private function formatErrorMessageForClaude(array $notification): string
    {
        $errorDetails = $notification['params']['error_details'] ?? [];
        $context = $notification['params']['context'] ?? [];

        $errorType = $errorDetails['type'] ?? 'unknown';
        $filePath = $errorDetails['file_path'] ?? 'unknown file';
        $lineNumber = $errorDetails['line_number'] ?? 'unknown line';
        $rawError = $errorDetails['raw_error'] ?? $errorDetails['message'] ?? 'No error message';

        $message = "🚨 AUTOMATED ERROR DETECTION from MCPman Laravel App\n\n";
        $message .= "**Error Type**: {$errorType}\n";
        $message .= "**File**: {$filePath}:{$lineNumber}\n";
        $message .= "**Raw Error**: {$rawError}\n\n";

        // Add file context if available
        if (! empty($context['file_content'])) {
            $message .= "**File Context**:\n```php\n";
            foreach ($context['file_content'] as $lineNum => $lineContent) {
                $marker = $lineNum == $lineNumber ? '>>> ' : '    ';
                $message .= "{$marker}{$lineNum}: {$lineContent}\n";
            }
            $message .= "```\n\n";
        }

        // Add suggested actions from notification params (not context)
        if (! empty($notification['params']['suggested_actions'] ?? [])) {
            $message .= "**Suggested Actions**:\n";
            foreach ($notification['params']['suggested_actions'] as $action) {
                $message .= "- {$action}\n";
            }
            $message .= "\n";
        }

        $message .= '**Request**: Please analyze this error and provide a specific fix for the Laravel application. ';
        $message .= 'Include the exact code changes needed and explain the cause of the error.';

        return $message;
    }

    /**
     * Close all connections
     */
    public function closeConnections(): void
    {
        foreach ($this->connections as $name => $connection) {
            try {
                // Stdio connections don't maintain persistent processes in our implementation
                Log::info("Closed MCP connection: {$name}");
            } catch (\Exception $e) {
                Log::error("Error closing MCP connection: {$name}", ['error' => $e->getMessage()]);
            }
        }

        $this->connections = [];
    }
}
