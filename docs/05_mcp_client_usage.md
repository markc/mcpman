# MCP Client Usage Guide

This guide covers how to configure and use the outgoing MCP client to connect from Filament to Claude Code instances.

## Overview

The MCP Client (`app/Services/McpClient.php`) enables Filament to initiate connections to external Claude Code instances, allowing the admin interface to:

- Execute tools on remote Claude Code servers
- Send conversation messages
- Retrieve and display responses
- Manage multiple concurrent connections

## Connection Configuration

### Creating MCP Connections

Navigate to **Admin Panel → MCP Connections** to manage outgoing connections.

#### Connection Types

**1. HTTP Transport**
```
Name: Claude Code HTTP
Endpoint URL: http://localhost:8001/mcp
Transport Type: HTTP
Auth Config:
  type: bearer
  token: your-claude-code-api-key
```

**2. WebSocket Transport**
```
Name: Claude Code WebSocket  
Endpoint URL: ws://localhost:8001/mcp
Transport Type: WebSocket
Auth Config:
  type: bearer
  token: your-claude-code-api-key
```

**3. stdio Transport**
```
Name: Claude Code Process
Endpoint URL: /path/to/claude-code-executable
Transport Type: stdio
Auth Config: (not required for stdio)
```

### Authentication Configuration

#### Bearer Token Authentication
```json
{
  "type": "bearer",
  "token": "your-api-key-here"
}
```

#### Custom Headers Authentication
```json
{
  "type": "headers",
  "headers": {
    "X-API-Key": "your-api-key",
    "X-Client-ID": "filament-client"
  }
}
```

#### Basic Authentication
```json
{
  "type": "basic",
  "username": "your-username",
  "password": "your-password"
}
```

### Capability Configuration

Define supported MCP features:

```json
{
  "tools": "true",
  "prompts": "true", 
  "resources": "true",
  "notifications": "false"
}
```

## Using the MCP Client

### Programmatic Usage

#### Basic Connection Example

```php
use App\Services\McpClient;
use App\Models\McpConnection;

// Get connection
$connection = McpConnection::where('name', 'Claude Code HTTP')->first();

// Create client
$client = new McpClient($connection);

// Test connection
if ($client->connect()) {
    echo "Connected successfully!";
} else {
    echo "Connection failed";
}
```

#### Tool Execution Example

```php
// List available tools
$tools = $client->listTools();

// Execute a tool
$result = $client->callTool('analyze_text', [
    'text' => 'This is some text to analyze',
    'language' => 'en'
]);

if (isset($result['error'])) {
    echo "Tool execution failed: " . $result['error'];
} else {
    echo "Tool result: " . json_encode($result);
}
```

#### Resource Access Example

```php
// List available resources
$resources = $client->listResources();

// Read a specific resource
$content = $client->readResource('file://project/README.md');

if (isset($content['error'])) {
    echo "Resource read failed: " . $content['error'];
} else {
    echo "Resource content: " . $content['content'];
}
```

#### Conversation Example

```php
// Send a conversation message
$response = $client->executeConversation([
    [
        'role' => 'user',
        'content' => 'Can you help me analyze this data?'
    ]
]);

if (isset($response['error'])) {
    echo "Conversation failed: " . $response['error'];
} else {
    echo "Claude response: " . $response['content'];
}
```

### Via Conversation Interface

The easiest way to use MCP connections is through the **Chat with Claude** interface in the admin panel.

#### Steps:
1. Navigate to **Admin Panel → Chat with Claude**
2. Select an active MCP connection from the dropdown
3. Wait for available tools to load
4. Type your message or select a tool to execute
5. View the response in the conversation area

#### Example Conversation Flow:

**User**: "List all available tools"
**Assistant**: Shows available tools from the connected Claude Code instance

**User**: "Analyze the current project structure"
**Assistant**: Executes analysis and provides detailed report

**Tool Execution**: Select "create_file" tool with arguments:
```
path: "/tmp/test.txt"
content: "Hello from Filament!"
```

## Transport Types

### HTTP Transport

**Best for**: REST API-style communication

**Configuration**:
```php
'transport_type' => 'http',
'endpoint_url' => 'http://claude-code-server:8001/mcp',
'auth_config' => [
    'type' => 'bearer',
    'token' => 'your-api-key'
]
```

**Features**:
- Simple request/response model
- Standard HTTP status codes
- Easy to debug with tools like cURL
- Works through firewalls and proxies

**Limitations**:
- No real-time bidirectional communication
- Higher latency for multiple requests
- No server-initiated messages

### WebSocket Transport

**Best for**: Real-time bidirectional communication

**Configuration**:
```php
'transport_type' => 'websocket',
'endpoint_url' => 'ws://claude-code-server:8001/mcp',
'auth_config' => [
    'type' => 'bearer',
    'token' => 'your-api-key'
]
```

**Features**:
- Real-time communication
- Server can initiate messages
- Lower latency for multiple exchanges
- Persistent connections

**Limitations**:
- More complex to implement
- Connection management complexity
- Firewall/proxy complications

### stdio Transport

**Best for**: Local Claude Code processes

**Configuration**:
```php
'transport_type' => 'stdio',
'endpoint_url' => '/usr/local/bin/claude-code',
'auth_config' => null // Not needed for local processes
```

**Features**:
- Direct process communication
- No network overhead
- Secure local execution
- Process lifecycle management

**Limitations**:
- Only works with local processes
- Process management complexity
- Platform-dependent paths

## Error Handling

### Connection Errors

The client automatically handles various error conditions:

#### Network Errors
```php
try {
    $result = $client->callTool('some_tool', $args);
} catch (\Exception $e) {
    if (str_contains($e->getMessage(), 'Connection refused')) {
        // Handle connection refused
        $connection->markAsError('Service unavailable');
    } else if (str_contains($e->getMessage(), 'timeout')) {
        // Handle timeout
        $connection->markAsError('Request timeout');
    }
}
```

#### Authentication Errors
```php
if (isset($result['error']) && $result['error']['code'] === -32600) {
    // Invalid authentication
    $connection->markAsError('Authentication failed');
    // Maybe refresh token or prompt for new credentials
}
```

#### Protocol Errors
```php
if (isset($result['error'])) {
    switch ($result['error']['code']) {
        case -32601:
            // Method not found
            break;
        case -32602:
            // Invalid parameters
            break;
        case -32603:
            // Internal error
            break;
    }
}
```

### Automatic Recovery

The client includes automatic recovery mechanisms:

#### Connection Retry
```php
// Automatic retry with exponential backoff
$maxRetries = 3;
$baseDelay = 1; // seconds

for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
    try {
        $result = $client->sendRequest($method, $params);
        break; // Success
    } catch (\Exception $e) {
        if ($attempt === $maxRetries) {
            throw $e; // Final attempt failed
        }
        
        $delay = $baseDelay * pow(2, $attempt - 1);
        sleep($delay);
    }
}
```

#### Connection Health Monitoring
```php
// Periodic health checks
$client->testConnection(); // Returns boolean

// Automatic reconnection on failure
if (!$client->testConnection()) {
    $client->disconnect();
    $client->connect();
}
```

## Performance Optimization

### Connection Pooling

For high-frequency operations, implement connection pooling:

```php
class McpConnectionPool
{
    private array $connections = [];
    
    public function getConnection(string $connectionName): McpClient
    {
        if (!isset($this->connections[$connectionName])) {
            $mcpConnection = McpConnection::where('name', $connectionName)->first();
            $this->connections[$connectionName] = new McpClient($mcpConnection);
            $this->connections[$connectionName]->connect();
        }
        
        return $this->connections[$connectionName];
    }
    
    public function closeAll(): void
    {
        foreach ($this->connections as $client) {
            $client->disconnect();
        }
        $this->connections = [];
    }
}
```

### Request Batching

Batch multiple operations to reduce overhead:

```php
// Instead of multiple individual calls
$results = [];
foreach ($items as $item) {
    $results[] = $client->callTool('process_item', ['item' => $item]);
}

// Batch them if the target supports it
$batchResult = $client->callTool('process_items', ['items' => $items]);
```

### Caching

Cache frequently accessed data:

```php
use Illuminate\Support\Facades\Cache;

// Cache tool list for 5 minutes
$tools = Cache::remember(
    "mcp_tools_{$connection->id}",
    300,
    fn() => $client->listTools()
);
```

## Testing Connections

### Manual Testing

Use the admin interface to test connections:

1. Navigate to **MCP Dashboard**
2. Find the connection to test
3. Click the "Test" button
4. Review the result notification

### Automated Testing

Create automated tests for connection health:

```php
// In a scheduled job or health check
$connections = McpConnection::active()->get();

foreach ($connections as $connection) {
    $client = new McpClient($connection);
    
    if ($client->testConnection()) {
        $connection->markAsConnected();
    } else {
        $connection->markAsError('Health check failed');
    }
}
```

### Load Testing

Test connection performance under load:

```php
// Simulate concurrent requests
$processes = [];
for ($i = 0; $i < 10; $i++) {
    $processes[] = new Process([
        'php', 'artisan', 'test:mcp-connection', $connection->id
    ]);
}

foreach ($processes as $process) {
    $process->start();
}

// Wait for all to complete
foreach ($processes as $process) {
    $process->wait();
}
```

## Debugging

### Enable Debug Logging

Add detailed logging for troubleshooting:

```php
// In McpClient.php
Log::debug('Sending MCP request', [
    'connection' => $this->connection->name,
    'method' => $method,
    'params' => $params
]);

$response = $this->sendRequest($method, $params);

Log::debug('Received MCP response', [
    'connection' => $this->connection->name,
    'response' => $response
]);
```

### Monitor Network Traffic

For HTTP connections, monitor traffic:

```bash
# Using tcpdump
tcpdump -i any -w mcp-traffic.pcap port 8001

# Using Wireshark for analysis
wireshark mcp-traffic.pcap
```

### Process Monitoring

For stdio connections, monitor process behavior:

```bash
# Monitor process creation
ps aux | grep claude-code

# Monitor file descriptors
lsof -p <process-id>

# Monitor system calls
strace -p <process-id>
```

## Security Considerations

### API Key Management
- Store API keys securely in the database
- Rotate keys regularly
- Use different keys for different environments
- Monitor key usage for anomalies

### Network Security
- Use HTTPS for production HTTP connections
- Implement proper TLS certificate validation
- Use VPN or private networks when possible
- Monitor connection logs for suspicious activity

### Process Security
- Run stdio processes with minimal privileges
- Validate all executable paths
- Monitor process resource usage
- Implement process sandboxing when possible

For additional troubleshooting and advanced configuration, see the [Troubleshooting Guide](07_troubleshooting.md).