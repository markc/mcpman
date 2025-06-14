# Stage 06: MCP Client Implementation

## Overview
Create the MCP client service that enables Filament to establish outgoing connections to Claude Code instances, supporting multiple transport types and providing real-time communication capabilities.

## MCP Client Architecture

The MCP client provides:
1. **Multi-transport support** - HTTP, WebSocket, and stdio connections
2. **Connection management** - Status tracking and error handling
3. **Tool execution** - Call tools on remote MCP servers
4. **Conversation handling** - Bidirectional communication with Claude Code

## Step-by-Step Implementation

### 1. Install Required Dependencies

```bash
# Add WebSocket and HTTP client support
composer require ratchet/pawl
composer require guzzlehttp/guzzle
```

### 2. Create MCP Client Service

**app/Services/McpClient.php**:
```php
<?php

namespace App\Services;

use App\Models\McpConnection;
use GuzzleHttp\Client as HttpClient;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Support\Facades\Log;
use Ratchet\Client\WebSocket;
use Ratchet\Client\Connector;

class McpClient
{
    protected McpConnection $connection;
    protected HttpClient $httpClient;
    protected ?WebSocket $websocketClient = null;
    protected array $pendingRequests = [];
    protected int $requestId = 1;

    public function __construct(McpConnection $connection)
    {
        $this->connection = $connection;
        $this->httpClient = new HttpClient([
            'timeout' => 30,
            'verify' => false, // For development - should be true in production
        ]);
    }

    public function testConnection(): bool
    {
        try {
            $response = $this->sendRequest('ping', []);
            return isset($response['result']);
        } catch (\Exception $e) {
            Log::error('MCP connection test failed', [
                'connection' => $this->connection->name,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    public function listTools(): array
    {
        try {
            $response = $this->sendRequest('tools/list', []);
            return $response['result']['tools'] ?? [];
        } catch (\Exception $e) {
            Log::error('Failed to list tools', [
                'connection' => $this->connection->name,
                'error' => $e->getMessage(),
            ]);
            return [];
        }
    }

    public function callTool(string $toolName, array $arguments = []): array
    {
        try {
            $response = $this->sendRequest('tools/call', [
                'name' => $toolName,
                'arguments' => $arguments,
            ]);

            if (isset($response['error'])) {
                return ['error' => $response['error']['message']];
            }

            return $response['result'] ?? [];
        } catch (\Exception $e) {
            Log::error('Tool call failed', [
                'connection' => $this->connection->name,
                'tool' => $toolName,
                'error' => $e->getMessage(),
            ]);
            return ['error' => $e->getMessage()];
        }
    }

    public function listResources(): array
    {
        try {
            $response = $this->sendRequest('resources/list', []);
            return $response['result']['resources'] ?? [];
        } catch (\Exception $e) {
            Log::error('Failed to list resources', [
                'connection' => $this->connection->name,
                'error' => $e->getMessage(),
            ]);
            return [];
        }
    }

    public function readResource(string $uri): array
    {
        try {
            $response = $this->sendRequest('resources/read', ['uri' => $uri]);
            return $response['result'] ?? [];
        } catch (\Exception $e) {
            Log::error('Failed to read resource', [
                'connection' => $this->connection->name,
                'uri' => $uri,
                'error' => $e->getMessage(),
            ]);
            return ['error' => $e->getMessage()];
        }
    }

    public function executeConversation(array $messages): array
    {
        try {
            // For conversation, we use the sampling/createMessage endpoint
            $response = $this->sendRequest('sampling/createMessage', [
                'messages' => $messages,
                'maxTokens' => 1000,
                'stopSequences' => [],
                'temperature' => 0.7,
            ]);

            if (isset($response['error'])) {
                return ['error' => $response['error']['message']];
            }

            return [
                'content' => $response['result']['content']['text'] ?? 'No response content',
                'stopReason' => $response['result']['stopReason'] ?? 'unknown',
                'usage' => $response['result']['usage'] ?? [],
            ];
        } catch (\Exception $e) {
            Log::error('Conversation execution failed', [
                'connection' => $this->connection->name,
                'error' => $e->getMessage(),
            ]);
            return ['error' => $e->getMessage()];
        }
    }

    protected function sendRequest(string $method, array $params = []): array
    {
        return match ($this->connection->transport_type) {
            'http' => $this->sendHttpRequest($method, $params),
            'websocket' => $this->sendWebSocketRequest($method, $params),
            'stdio' => $this->sendStdioRequest($method, $params),
            default => throw new \InvalidArgumentException('Unsupported transport type'),
        };
    }

    protected function sendHttpRequest(string $method, array $params): array
    {
        $requestId = $this->requestId++;
        
        $payload = [
            'jsonrpc' => '2.0',
            'id' => $requestId,
            'method' => $method,
            'params' => $params,
        ];

        $headers = [
            'Content-Type' => 'application/json',
            'User-Agent' => 'MCP-Manager/1.0',
        ];

        // Add authentication if configured
        if ($this->connection->auth_config) {
            $authConfig = $this->connection->auth_config;
            
            if ($authConfig['type'] === 'bearer' && !empty($authConfig['token'])) {
                $headers['Authorization'] = 'Bearer ' . $authConfig['token'];
            } elseif ($authConfig['type'] === 'basic' && !empty($authConfig['username'])) {
                $headers['Authorization'] = 'Basic ' . base64_encode(
                    $authConfig['username'] . ':' . ($authConfig['password'] ?? '')
                );
            }
        }

        try {
            $response = $this->httpClient->post($this->connection->endpoint_url, [
                'json' => $payload,
                'headers' => $headers,
            ]);

            $responseData = json_decode($response->getBody()->getContents(), true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new \RuntimeException('Invalid JSON response: ' . json_last_error_msg());
            }

            return $responseData;

        } catch (RequestException $e) {
            $statusCode = $e->getResponse()?->getStatusCode() ?? 0;
            $responseBody = $e->getResponse()?->getBody()?->getContents() ?? '';
            
            throw new \RuntimeException(
                "HTTP request failed: {$e->getMessage()} (Status: {$statusCode}, Body: {$responseBody})"
            );
        }
    }

    protected function sendWebSocketRequest(string $method, array $params): array
    {
        // WebSocket implementation for real-time communication
        $requestId = $this->requestId++;
        
        $payload = [
            'jsonrpc' => '2.0',
            'id' => $requestId,
            'method' => $method,
            'params' => $params,
        ];

        // This is a simplified WebSocket implementation
        // In production, you might want to use a more robust WebSocket client
        return $this->sendWebSocketMessage($payload);
    }

    protected function sendWebSocketMessage(array $payload): array
    {
        $connector = new Connector();
        $response = [];
        
        try {
            $connector($this->connection->endpoint_url)
                ->then(function (WebSocket $conn) use ($payload, &$response) {
                    $conn->send(json_encode($payload));
                    
                    $conn->on('message', function ($msg) use (&$response) {
                        $data = json_decode($msg->getPayload(), true);
                        $response = $data;
                    });
                    
                    // Set a timeout for the response
                    $conn->close();
                });
                
            // Wait for response (simplified - in production use proper async handling)
            return $response ?: ['error' => 'No response received'];
            
        } catch (\Exception $e) {
            throw new \RuntimeException('WebSocket connection failed: ' . $e->getMessage());
        }
    }

    protected function sendStdioRequest(string $method, array $params): array
    {
        // Standard I/O implementation for local processes
        $requestId = $this->requestId++;
        
        $payload = [
            'jsonrpc' => '2.0',
            'id' => $requestId,
            'method' => $method,
            'params' => $params,
        ];

        // Parse endpoint_url to get command and arguments
        $command = $this->parseStdioCommand($this->connection->endpoint_url);
        
        try {
            $process = proc_open(
                $command,
                [
                    0 => ['pipe', 'r'], // stdin
                    1 => ['pipe', 'w'], // stdout
                    2 => ['pipe', 'w'], // stderr
                ],
                $pipes
            );

            if (!is_resource($process)) {
                throw new \RuntimeException('Failed to start process');
            }

            // Send request
            fwrite($pipes[0], json_encode($payload) . "\n");
            fclose($pipes[0]);

            // Read response
            $response = stream_get_contents($pipes[1]);
            $error = stream_get_contents($pipes[2]);
            
            fclose($pipes[1]);
            fclose($pipes[2]);
            
            $exitCode = proc_close($process);

            if ($exitCode !== 0) {
                throw new \RuntimeException("Process exited with code {$exitCode}: {$error}");
            }

            $responseData = json_decode(trim($response), true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new \RuntimeException('Invalid JSON response: ' . json_last_error_msg());
            }

            return $responseData;

        } catch (\Exception $e) {
            throw new \RuntimeException('Stdio process failed: ' . $e->getMessage());
        }
    }

    protected function parseStdioCommand(string $endpointUrl): string
    {
        // Convert stdio:// URL to actual command
        // Format: stdio://path/to/executable?arg1=value1&arg2=value2
        
        $parsed = parse_url($endpointUrl);
        
        if ($parsed['scheme'] !== 'stdio') {
            throw new \InvalidArgumentException('Invalid stdio URL scheme');
        }

        $command = $parsed['path'] ?? '';
        
        if (isset($parsed['query'])) {
            parse_str($parsed['query'], $args);
            foreach ($args as $key => $value) {
                $command .= " --{$key}=" . escapeshellarg($value);
            }
        }

        return $command;
    }

    public function getConnectionStatus(): array
    {
        try {
            $isConnected = $this->testConnection();
            
            return [
                'connected' => $isConnected,
                'transport' => $this->connection->transport_type,
                'endpoint' => $this->connection->endpoint_url,
                'last_tested' => now()->toISOString(),
            ];
        } catch (\Exception $e) {
            return [
                'connected' => false,
                'error' => $e->getMessage(),
                'transport' => $this->connection->transport_type,
                'endpoint' => $this->connection->endpoint_url,
                'last_tested' => now()->toISOString(),
            ];
        }
    }

    public function getCapabilities(): array
    {
        try {
            $response = $this->sendRequest('initialize', [
                'protocolVersion' => '2024-11-05',
                'capabilities' => [
                    'experimental' => [],
                    'sampling' => [],
                ],
                'clientInfo' => [
                    'name' => 'MCP Manager',
                    'version' => '1.0.0',
                ],
            ]);

            return $response['result']['capabilities'] ?? [];
        } catch (\Exception $e) {
            Log::error('Failed to get capabilities', [
                'connection' => $this->connection->name,
                'error' => $e->getMessage(),
            ]);
            return [];
        }
    }

    public function close(): void
    {
        if ($this->websocketClient) {
            $this->websocketClient->close();
            $this->websocketClient = null;
        }
    }

    public function __destruct()
    {
        $this->close();
    }
}
```

### 3. Add Client Testing Features

**tests/Feature/McpClientTest.php**:
```php
<?php

use App\Models\User;
use App\Models\McpConnection;
use App\Services\McpClient;
use Illuminate\Support\Facades\Http;

test('mcp client can test connection', function () {
    $user = User::factory()->create();
    $connection = McpConnection::factory()->create([
        'user_id' => $user->id,
        'transport_type' => 'http',
        'endpoint_url' => 'https://example.com/mcp',
        'status' => 'inactive',
    ]);

    // Mock HTTP response
    Http::fake([
        'example.com/mcp' => Http::response([
            'jsonrpc' => '2.0',
            'id' => 1,
            'result' => 'pong',
        ], 200),
    ]);

    $client = new McpClient($connection);
    $isConnected = $client->testConnection();

    expect($isConnected)->toBeTrue();
});

test('mcp client handles connection failures', function () {
    $user = User::factory()->create();
    $connection = McpConnection::factory()->create([
        'user_id' => $user->id,
        'transport_type' => 'http',
        'endpoint_url' => 'https://invalid.example.com/mcp',
        'status' => 'inactive',
    ]);

    // Mock HTTP failure
    Http::fake([
        'invalid.example.com/mcp' => Http::response([], 500),
    ]);

    $client = new McpClient($connection);
    $isConnected = $client->testConnection();

    expect($isConnected)->toBeFalse();
});

test('mcp client can list tools', function () {
    $user = User::factory()->create();
    $connection = McpConnection::factory()->create([
        'user_id' => $user->id,
        'transport_type' => 'http',
        'endpoint_url' => 'https://example.com/mcp',
    ]);

    Http::fake([
        'example.com/mcp' => Http::response([
            'jsonrpc' => '2.0',
            'id' => 1,
            'result' => [
                'tools' => [
                    [
                        'name' => 'test_tool',
                        'description' => 'A test tool',
                        'inputSchema' => ['type' => 'object'],
                    ],
                ],
            ],
        ], 200),
    ]);

    $client = new McpClient($connection);
    $tools = $client->listTools();

    expect($tools)->toHaveCount(1);
    expect($tools[0]['name'])->toBe('test_tool');
});
```

### 4. Create Connection Management Commands

**app/Console/Commands/TestMcpConnections.php**:
```php
<?php

namespace App\Console\Commands;

use App\Models\McpConnection;
use App\Services\McpClient;
use Illuminate\Console\Command;

class TestMcpConnections extends Command
{
    protected $signature = 'mcp:test-connections {--connection= : Test specific connection by name}';
    protected $description = 'Test all MCP connections or a specific connection';

    public function handle(): int
    {
        $connectionName = $this->option('connection');
        
        $query = McpConnection::query();
        
        if ($connectionName) {
            $query->where('name', $connectionName);
        }
        
        $connections = $query->get();
        
        if ($connections->isEmpty()) {
            $this->error('No connections found.');
            return 1;
        }

        $this->info('Testing MCP connections...');
        
        foreach ($connections as $connection) {
            $this->line("\nTesting: {$connection->name}");
            $this->line("Endpoint: {$connection->endpoint_url}");
            $this->line("Transport: {$connection->transport_type}");
            
            try {
                $client = new McpClient($connection);
                $isConnected = $client->testConnection();
                
                if ($isConnected) {
                    $this->info("✓ Connection successful");
                    $connection->markAsConnected();
                    
                    // Get additional info
                    $tools = $client->listTools();
                    $this->line("Available tools: " . count($tools));
                    
                    $resources = $client->listResources();
                    $this->line("Available resources: " . count($resources));
                    
                } else {
                    $this->error("✗ Connection failed");
                    $connection->markAsError('Connection test failed');
                }
                
            } catch (\Exception $e) {
                $this->error("✗ Error: " . $e->getMessage());
                $connection->markAsError($e->getMessage());
            }
        }

        return 0;
    }
}
```

### 5. Register Console Command

**app/Console/Kernel.php** (or add to service provider):
```php
protected $commands = [
    Commands\TestMcpConnections::class,
];
```

### 6. Add Client Factory for Testing

**database/factories/McpConnectionFactory.php**:
```php
<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class McpConnectionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => $this->faker->words(2, true),
            'endpoint_url' => $this->faker->url(),
            'transport_type' => $this->faker->randomElement(['stdio', 'http', 'websocket']),
            'auth_config' => [
                'type' => 'bearer',
                'token' => $this->faker->sha256(),
            ],
            'capabilities' => [
                'tools' => 'true',
                'prompts' => 'true',
                'resources' => 'true',
            ],
            'status' => 'inactive',
            'user_id' => User::factory(),
        ];
    }
}
```

## Expected Outcomes

After completing Stage 06:

✅ MCP client supporting HTTP, WebSocket, and stdio transports  
✅ Connection testing and status tracking  
✅ Tool execution and resource access on remote servers  
✅ Conversation handling for real-time communication  
✅ Authentication support for multiple auth types  
✅ Console command for testing connections  
✅ Comprehensive error handling and logging  

## Testing the Client

```bash
# Test all connections
php artisan mcp:test-connections

# Test specific connection
php artisan mcp:test-connections --connection="Claude Local"

# Run unit tests
php artisan test --filter McpClientTest
```

## Usage Examples

```php
// Create client for a connection
$connection = McpConnection::find(1);
$client = new McpClient($connection);

// Test connection
$isConnected = $client->testConnection();

// List available tools
$tools = $client->listTools();

// Call a tool
$result = $client->callTool('search_files', ['query' => 'laravel']);

// Execute conversation
$response = $client->executeConversation([
    ['role' => 'user', 'content' => 'Hello, Claude!']
]);
```

## Next Stage
Proceed to **Stage 07: Filament v4 Custom Pages & Widgets** to create the dashboard and conversation interfaces using proper Filament v4 widget patterns.

## Files Created/Modified
- `app/Services/McpClient.php` - Complete MCP client implementation
- `tests/Feature/McpClientTest.php` - Client testing
- `app/Console/Commands/TestMcpConnections.php` - Connection testing command
- `database/factories/McpConnectionFactory.php` - Test factory
- `composer.json` - Added WebSocket and HTTP client dependencies

## Git Checkpoint
```bash
git add .
git commit -m "Stage 06: MCP client implementation

- Create MCP client supporting HTTP, WebSocket, and stdio transports
- Add connection testing and status tracking functionality
- Implement tool execution and resource access for remote servers
- Add conversation handling for real-time communication
- Create console command for testing connections
- Add comprehensive error handling and test coverage"
```