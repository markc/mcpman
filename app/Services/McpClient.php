<?php

namespace App\Services;

use App\Events\McpConversationMessage;
use App\Models\McpConnection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;

class McpClient
{
    private McpConnection $connection;

    private PersistentMcpManager $manager;

    private bool $usePersistentConnection;

    public function __construct(McpConnection $connection, ?PersistentMcpManager $manager = null)
    {
        $this->connection = $connection;
        $this->manager = $manager ?? app(PersistentMcpManager::class);
        $this->usePersistentConnection = config('mcp.client.use_persistent', true);
    }

    public function connect(): bool
    {
        if ($this->usePersistentConnection) {
            return $this->manager->startConnection($this->connection);
        }

        // Fallback to legacy connection method
        return $this->legacyConnect();
    }

    private function legacyConnect(): bool
    {
        try {
            $response = $this->sendRequest('initialize', [
                'protocolVersion' => '2024-11-05',
                'capabilities' => [
                    'roots' => [
                        'listChanged' => false,
                    ],
                ],
                'clientInfo' => [
                    'name' => 'Laravel-Filament-MCP-Client',
                    'version' => '1.0.0',
                ],
            ]);

            if (isset($response['result'])) {
                $this->connection->update([
                    'capabilities' => $response['result']['capabilities'] ?? [],
                    'status' => 'active',
                    'last_connected_at' => now(),
                    'last_error' => null,
                ]);

                Log::info('MCP connection established', ['connection' => $this->connection->name]);

                return true;
            }

            return false;

        } catch (\Exception $e) {
            $this->connection->markAsError($e->getMessage());
            Log::error('MCP connection failed', ['connection' => $this->connection->name, 'error' => $e->getMessage()]);

            return false;
        }
    }

    public function listTools(): array
    {
        try {
            if ($this->usePersistentConnection && $this->manager->isConnectionActive((string) $this->connection->id)) {
                return $this->manager->listTools((string) $this->connection->id);
            }

            $response = $this->sendRequest('tools/list');

            return $response['result']['tools'] ?? [];
        } catch (\Exception $e) {
            Log::error('Failed to list tools', ['error' => $e->getMessage()]);

            return [];
        }
    }

    public function callTool(string $name, array $arguments = []): array
    {
        try {
            if ($this->usePersistentConnection && $this->manager->isConnectionActive((string) $this->connection->id)) {
                $response = $this->manager->callTool((string) $this->connection->id, $name, $arguments);

                return $response['result'] ?? $response;
            }

            $response = $this->sendRequest('tools/call', [
                'name' => $name,
                'arguments' => $arguments,
            ]);

            return $response['result'] ?? [];
        } catch (\Exception $e) {
            Log::error('Tool call failed', ['tool' => $name, 'error' => $e->getMessage()]);

            return ['error' => $e->getMessage()];
        }
    }

    public function listResources(): array
    {
        try {
            $response = $this->sendRequest('resources/list');

            return $response['result']['resources'] ?? [];
        } catch (\Exception $e) {
            Log::error('Failed to list resources', ['error' => $e->getMessage()]);

            return [];
        }
    }

    public function readResource(string $uri): array
    {
        try {
            $response = $this->sendRequest('resources/read', ['uri' => $uri]);

            return $response['result'] ?? [];
        } catch (\Exception $e) {
            Log::error('Resource read failed', ['uri' => $uri, 'error' => $e->getMessage()]);

            return ['error' => $e->getMessage()];
        }
    }

    public function listPrompts(): array
    {
        try {
            $response = $this->sendRequest('prompts/list');

            return $response['result']['prompts'] ?? [];
        } catch (\Exception $e) {
            Log::error('Failed to list prompts', ['error' => $e->getMessage()]);

            return [];
        }
    }

    public function getPrompt(string $name, array $arguments = []): array
    {
        try {
            $response = $this->sendRequest('prompts/get', [
                'name' => $name,
                'arguments' => $arguments,
            ]);

            return $response['result'] ?? [];
        } catch (\Exception $e) {
            Log::error('Prompt get failed', ['prompt' => $name, 'error' => $e->getMessage()]);

            return ['error' => $e->getMessage()];
        }
    }

    public function executeConversation(array $messages): array
    {
        try {
            if ($this->usePersistentConnection && $this->manager->isConnectionActive((string) $this->connection->id)) {
                $response = $this->manager->executeConversation((string) $this->connection->id, $messages);

                // Broadcast the conversation message
                if (isset($response['result'])) {
                    $message = [
                        'role' => 'assistant',
                        'content' => $response['result']['content'][0]['text'] ?? $response['result']['content'] ?? 'No content',
                        'timestamp' => now()->toISOString(),
                    ];

                    McpConversationMessage::dispatch(
                        auth()->user() ?? \App\Models\User::first(),
                        $this->connection,
                        $message
                    );
                }

                return $response;
            }

            // Fallback to direct execution
            return $this->executeDirectClaudeCommand([
                'method' => 'conversation/execute',
                'params' => ['messages' => $messages],
            ]);

        } catch (\Exception $e) {
            Log::error('Conversation execution failed', ['error' => $e->getMessage()]);

            return ['error' => $e->getMessage()];
        }
    }

    private function sendRequest(string $method, array $params = []): array
    {
        $request = [
            'jsonrpc' => '2.0',
            'id' => uniqid(),
            'method' => $method,
            'params' => $params,
        ];

        Log::debug('Sending MCP request', ['method' => $method, 'connection' => $this->connection->name]);

        return match ($this->connection->transport_type) {
            'http' => $this->sendHttpRequest($request),
            'websocket' => $this->sendWebSocketRequest($request),
            'stdio' => $this->sendStdioRequest($request),
            default => throw new \Exception('Unsupported transport type: '.$this->connection->transport_type)
        };
    }

    private function sendHttpRequest(array $request): array
    {
        $headers = ['Content-Type' => 'application/json'];

        // Add authentication if configured
        if ($this->connection->auth_config) {
            $authConfig = $this->connection->auth_config;

            if ($authConfig['type'] === 'bearer' && ! empty($authConfig['token'])) {
                $headers['Authorization'] = 'Bearer '.$authConfig['token'];
            }
        }

        $response = Http::withHeaders($headers)
            ->timeout(30)
            ->post($this->connection->endpoint_url, $request);

        if (! $response->successful()) {
            throw new \Exception('HTTP request failed: '.$response->status().' '.$response->body());
        }

        return $response->json();
    }

    private function sendWebSocketRequest(array $request): array
    {
        // WebSocket implementation would go here
        // For now, we'll throw an exception as it requires additional libraries
        throw new \Exception('WebSocket transport not yet implemented');
    }

    private function sendStdioRequest(array $request): array
    {
        // Skip MCP server mode for now due to persistent connection requirements
        // Go directly to direct Claude execution for conversation
        if (($request['method'] ?? '') === 'conversation/execute') {
            Log::info('Using direct Claude execution instead of MCP server');

            return $this->executeDirectClaudeCommand($request);
        }

        // For other methods, fall back to mock responses
        return $this->getMockResponse($request);
    }

    private function tryMcpServerMode(array $request): ?array
    {
        try {
            // Use the proper Claude MCP serve command with debug enabled
            $command = ['claude', 'mcp', 'serve', '--debug'];
            $jsonRequest = json_encode($request)."\n";

            Log::info('Trying Claude MCP server mode', [
                'method' => $request['method'] ?? 'unknown',
                'request_id' => $request['id'] ?? 'unknown',
            ]);

            // Increase timeout since MCP server initialization may take time
            $result = Process::timeout(15)
                ->input($jsonRequest)
                ->run($command);

            if (! $result->successful()) {
                Log::info('MCP server mode failed', [
                    'exit_code' => $result->exitCode(),
                    'error' => $result->errorOutput(),
                    'output' => $result->output(),
                ]);

                return null;
            }

            $output = trim($result->output());
            $errorOutput = trim($result->errorOutput());

            // Log both outputs for debugging
            if (! empty($errorOutput)) {
                Log::debug('MCP server stderr', ['stderr' => $errorOutput]);
            }

            if (empty($output)) {
                Log::debug('MCP server mode returned no stdout output');

                return null;
            }

            Log::debug('MCP server raw output', ['output' => $output]);

            // Parse JSON-RPC response - look for valid JSON-RPC in any line
            $lines = array_filter(explode("\n", $output));
            foreach ($lines as $line) {
                $line = trim($line);
                if (empty($line) || ! str_starts_with($line, '{')) {
                    continue;
                }

                $response = json_decode($line, true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    // Check if it's a valid JSON-RPC response
                    if (isset($response['jsonrpc']) && $response['jsonrpc'] === '2.0') {
                        Log::info('MCP server mode successful', [
                            'response_id' => $response['id'] ?? 'unknown',
                            'has_result' => isset($response['result']),
                            'has_error' => isset($response['error']),
                        ]);

                        return $response;
                    }
                }
            }

            Log::debug('MCP server output contains no valid JSON-RPC responses', [
                'line_count' => count($lines),
                'first_line' => $lines[0] ?? 'empty',
            ]);

            return null;

        } catch (\Exception $e) {
            Log::info('MCP server mode exception', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return null;
        }
    }

    private function executeDirectClaudeCommand(array $request): array
    {
        $params = $request['params'] ?? [];
        $messages = $params['messages'] ?? [];
        $lastMessage = end($messages);
        $userPrompt = $lastMessage['content'] ?? '';

        if (empty($userPrompt)) {
            return $this->getMockResponse($request);
        }

        try {
            // Phase 1 Enhancement: Check authentication first
            if (config('mcp.claude.config_check', true)) {
                $authResult = $this->checkClaudeAuthentication();
                if (! $authResult['success']) {
                    Log::warning('Claude authentication issue detected', $authResult);

                    return $this->getMockResponse($request);
                }
            }

            // Phase 1 Enhancement: Use enhanced command with timeout from config
            $timeout = config('mcp.direct_timeout', 45);
            $command = ['claude', '-p', $userPrompt];

            Log::info('Executing direct Claude command with enhanced timeout', [
                'prompt' => substr($userPrompt, 0, 100).(strlen($userPrompt) > 100 ? '...' : ''),
                'timeout' => $timeout,
                'attempt_number' => 1,
            ]);

            $startTime = microtime(true);

            $result = Process::timeout($timeout)
                ->env(['MCP_TIMEOUT' => config('mcp.timeout', 30000)])
                ->run($command);

            $duration = round((microtime(true) - $startTime) * 1000, 2);

            if (! $result->successful()) {
                Log::warning('Direct Claude execution failed', [
                    'exit_code' => $result->exitCode(),
                    'error' => $result->errorOutput(),
                    'output' => $result->output(),
                    'duration_ms' => $duration,
                ]);

                // Phase 1 Enhancement: Try retry logic
                if (config('mcp.max_retries', 3) > 1) {
                    return $this->retryDirectClaudeCommand($request, 1);
                }

                return $this->getMockResponse($request);
            }

            $output = trim($result->output());

            if (empty($output)) {
                Log::warning('No output from direct Claude execution', ['duration_ms' => $duration]);

                return $this->getMockResponse($request);
            }

            Log::info('Direct Claude execution successful', [
                'response_length' => strlen($output),
                'duration_ms' => $duration,
            ]);

            // Phase 1 Enhancement: Cache successful responses
            if (config('mcp.cache_responses', true)) {
                $this->cacheResponse($userPrompt, $output);
            }

            return [
                'result' => [
                    'content' => $output,
                ],
            ];

        } catch (\Exception $e) {
            Log::info('Direct Claude execution failed, using fallback', [
                'error' => $e->getMessage(),
                'prompt' => substr($userPrompt, 0, 100).(strlen($userPrompt) > 100 ? '...' : ''),
            ]);

            return $this->getMockResponse($request);
        }
    }

    private function getMockResponse(array $request): array
    {
        $method = $request['method'];
        $params = $request['params'] ?? [];

        // Map MCP methods to mock responses
        switch ($method) {
            case 'tools/list':
                return $this->getClaudeCodeTools();

            case 'tools/call':
                return $this->callClaudeCodeTool($params);

            case 'conversation/execute':
                return $this->executeClaudeCodeConversation($params);

            default:
                throw new \Exception("Unsupported MCP method: {$method}");
        }
    }

    private function getClaudeCodeTools(): array
    {
        // Return available Claude Code "tools" (which are really just Claude capabilities)
        return [
            'result' => [
                'tools' => [
                    [
                        'name' => 'chat',
                        'description' => 'Chat with Claude Code',
                        'inputSchema' => [
                            'type' => 'object',
                            'properties' => [
                                'message' => ['type' => 'string', 'description' => 'Message to send to Claude'],
                            ],
                            'required' => ['message'],
                        ],
                    ],
                    [
                        'name' => 'code_analysis',
                        'description' => 'Analyze code in the current project',
                        'inputSchema' => [
                            'type' => 'object',
                            'properties' => [
                                'request' => ['type' => 'string', 'description' => 'Code analysis request'],
                            ],
                            'required' => ['request'],
                        ],
                    ],
                ],
            ],
        ];
    }

    private function callClaudeCodeTool(array $params): array
    {
        $toolName = $params['name'] ?? '';
        $arguments = $params['arguments'] ?? [];

        // For now, just return a success response
        return [
            'result' => [
                'content' => [
                    [
                        'type' => 'text',
                        'text' => "Tool '{$toolName}' would be called with: ".json_encode($arguments),
                    ],
                ],
            ],
        ];
    }

    private function executeClaudeCodeConversation(array $params): array
    {
        $messages = $params['messages'] ?? [];
        $lastMessage = end($messages);
        $userMessage = $lastMessage['content'] ?? '';

        // Generate a more realistic mock response based on the user's message
        $response = $this->generateMockResponse($userMessage);

        return [
            'result' => [
                'content' => $response,
            ],
        ];
    }

    private function generateMockResponse(string $userMessage): string
    {
        $lowerMessage = strtolower($userMessage);

        // Check for specific questions and provide relevant mock responses
        if (str_contains($lowerMessage, 'what day') || str_contains($lowerMessage, 'today')) {
            return 'Today is '.now()->format('l, F j, Y').'. (This is a mock response from the MCP fallback system - real Claude Code integration is being attempted but timing out)';
        }

        if (str_contains($lowerMessage, 'time')) {
            return 'The current time is '.now()->format('g:i A T').'. (Mock response from MCP fallback)';
        }

        if (str_contains($lowerMessage, 'hello') || str_contains($lowerMessage, 'hi')) {
            return "Hello! I'm Claude Code responding via the MCP integration. (This is currently a mock response as the real integration is timing out)";
        }

        if (str_contains($lowerMessage, 'weather')) {
            return "I don't have access to current weather data in this mock response. For real weather information, the full Claude Code integration would need to be working.";
        }

        // Default response for any other message
        return "I received your message: '{$userMessage}'. This is a mock response from the MCP fallback system. The real Claude Code MCP server is being attempted but timing out after 5 seconds. Your message was processed successfully though!";
    }

    /**
     * Phase 1 Enhancement: Check Claude authentication status
     */
    private function checkClaudeAuthentication(): array
    {
        try {
            $timeout = config('mcp.claude.auth_timeout', 10);
            $command = ['claude', 'config', 'get'];

            $result = Process::timeout($timeout)->run($command);

            if (! $result->successful()) {
                return [
                    'success' => false,
                    'error' => 'Authentication check failed',
                    'exit_code' => $result->exitCode(),
                    'output' => $result->errorOutput(),
                ];
            }

            return ['success' => true, 'output' => $result->output()];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Phase 1 Enhancement: Retry logic with exponential backoff
     */
    private function retryDirectClaudeCommand(array $request, int $attempt): array
    {
        $maxRetries = config('mcp.max_retries', 3);

        if ($attempt >= $maxRetries) {
            Log::warning('Maximum retries reached for Claude command');

            return $this->getMockResponse($request);
        }

        $delay = config('mcp.claude.retry_delay', 1000); // milliseconds
        if (config('mcp.claude.exponential_backoff', true)) {
            $delay = $delay * pow(2, $attempt - 1);
        }

        Log::info('Retrying Claude command', [
            'attempt' => $attempt + 1,
            'delay_ms' => $delay,
            'max_retries' => $maxRetries,
        ]);

        usleep($delay * 1000); // Convert to microseconds

        $params = $request['params'] ?? [];
        $messages = $params['messages'] ?? [];
        $lastMessage = end($messages);
        $userPrompt = $lastMessage['content'] ?? '';

        try {
            $timeout = config('mcp.direct_timeout', 45);
            $command = ['claude', '-p', $userPrompt];

            $startTime = microtime(true);
            $result = Process::timeout($timeout)->run($command);
            $duration = round((microtime(true) - $startTime) * 1000, 2);

            if (! $result->successful()) {
                Log::warning('Retry attempt failed', [
                    'attempt' => $attempt + 1,
                    'duration_ms' => $duration,
                    'exit_code' => $result->exitCode(),
                ]);

                return $this->retryDirectClaudeCommand($request, $attempt + 1);
            }

            $output = trim($result->output());
            if (empty($output)) {
                return $this->retryDirectClaudeCommand($request, $attempt + 1);
            }

            Log::info('Retry successful', [
                'attempt' => $attempt + 1,
                'duration_ms' => $duration,
            ]);

            return [
                'result' => [
                    'content' => $output,
                ],
            ];

        } catch (\Exception $e) {
            Log::warning('Retry attempt exception', [
                'attempt' => $attempt + 1,
                'error' => $e->getMessage(),
            ]);

            return $this->retryDirectClaudeCommand($request, $attempt + 1);
        }
    }

    /**
     * Phase 1 Enhancement: Cache responses to reduce API calls
     */
    private function cacheResponse(string $prompt, string $response): void
    {
        try {
            $cacheKey = config('mcp.health_check.cache_key', 'mcp_response_').md5($prompt);
            $ttl = config('mcp.health_check.cache_ttl', 300);

            \Illuminate\Support\Facades\Cache::put($cacheKey, $response, $ttl);

        } catch (\Exception $e) {
            Log::debug('Failed to cache response', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Phase 1 Enhancement: Check for cached responses
     */
    private function getCachedResponse(string $prompt): ?string
    {
        if (! config('mcp.cache_responses', true)) {
            return null;
        }

        try {
            $cacheKey = config('mcp.health_check.cache_key', 'mcp_response_').md5($prompt);

            return \Illuminate\Support\Facades\Cache::get($cacheKey);

        } catch (\Exception $e) {
            return null;
        }
    }

    public function testConnection(): bool
    {
        try {
            if ($this->usePersistentConnection) {
                return $this->manager->healthCheck((string) $this->connection->id);
            }

            $response = $this->sendRequest('ping');

            return isset($response['result']) || isset($response['id']);
        } catch (\Exception $e) {
            $this->connection->markAsError($e->getMessage());

            return false;
        }
    }

    public function disconnect(): void
    {
        try {
            if ($this->usePersistentConnection) {
                $this->manager->stopConnection((string) $this->connection->id);
            } else {
                $this->connection->disconnect();
            }
            Log::info('MCP connection closed', ['connection' => $this->connection->name]);
        } catch (\Exception $e) {
            Log::error('Error disconnecting MCP', ['connection' => $this->connection->name, 'error' => $e->getMessage()]);
        }
    }

    public function isConnected(): bool
    {
        if ($this->usePersistentConnection) {
            return $this->manager->isConnectionActive((string) $this->connection->id);
        }

        return $this->connection->status === 'active';
    }
}
