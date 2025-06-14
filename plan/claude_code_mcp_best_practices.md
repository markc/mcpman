# Claude Code MCP Best Practices and Troubleshooting

## Best Practices for MCP Implementation

### Server Development

#### Stdout Hygiene (Critical)
```bash
# ✅ CORRECT: Only JSON-RPC messages to stdout
echo '{"jsonrpc":"2.0","id":1,"result":{"tools":[]}}'

# ❌ INCORRECT: Mixed output breaks protocol
echo "Starting server..."  # This breaks MCP communication
echo '{"jsonrpc":"2.0","id":1,"result":{"tools":[]}}'
```

#### Error Handling
```php
// ✅ CORRECT: Structured error responses
public function handleError(\Exception $e): array
{
    return [
        'jsonrpc' => '2.0',
        'id' => $this->requestId,
        'error' => [
            'code' => -32603,
            'message' => 'Internal error',
            'data' => [
                'type' => get_class($e),
                'message' => $e->getMessage()
            ]
        ]
    ];
}
```

#### Resource Management
```php
// ✅ CORRECT: Proper resource cleanup
public function __destruct()
{
    $this->closeConnections();
    $this->cleanupTempFiles();
}

// ✅ CORRECT: Timeout handling
public function executeWithTimeout(callable $operation, int $timeoutSeconds): mixed
{
    $start = time();
    while (time() - $start < $timeoutSeconds) {
        try {
            return $operation();
        } catch (WouldBlockException $e) {
            usleep(100000); // 100ms
            continue;
        }
    }
    throw new TimeoutException();
}
```

### Client Development

#### Connection Management
```php
class McpClient
{
    private array $connectionPool = [];
    
    public function getConnection(string $serverId): Process
    {
        if (!isset($this->connectionPool[$serverId])) {
            $this->connectionPool[$serverId] = $this->createConnection($serverId);
        }
        
        return $this->connectionPool[$serverId];
    }
    
    public function validateConnection(Process $connection): bool
    {
        try {
            $response = $this->sendRequest($connection, 'ping');
            return isset($response['result']);
        } catch (\Exception $e) {
            return false;
        }
    }
}
```

#### Request Retry Logic
```php
public function sendRequestWithRetry(array $request, int $maxRetries = 3): array
{
    $lastException = null;
    
    for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
        try {
            return $this->sendRequest($request);
        } catch (TimeoutException $e) {
            $lastException = $e;
            $delay = min(1000 * pow(2, $attempt - 1), 30000); // Max 30s
            usleep($delay * 1000);
            
            Log::warning("MCP request timeout, attempt {$attempt}/{$maxRetries}", [
                'method' => $request['method'] ?? 'unknown',
                'delay' => $delay
            ]);
        }
    }
    
    throw $lastException;
}
```

### Configuration Management

#### Environment-Specific Configurations
```php
// config/mcp.php
return [
    'timeout' => env('MCP_TIMEOUT', 30000),
    'max_retries' => env('MCP_MAX_RETRIES', 3),
    'debug' => env('MCP_DEBUG', false),
    
    'servers' => [
        'default' => [
            'command' => env('MCP_DEFAULT_COMMAND', 'claude'),
            'args' => ['mcp', 'serve'],
            'timeout' => env('MCP_SERVER_TIMEOUT', 30),
        ]
    ],
    
    'fallback' => [
        'enabled' => env('MCP_FALLBACK_ENABLED', true),
        'cache_responses' => env('MCP_CACHE_FALLBACK', true),
    ]
];
```

#### Development vs Production Settings
```bash
# Development (.env.local)
MCP_TIMEOUT=60000
MCP_DEBUG=true
MCP_MAX_RETRIES=5
MCP_FALLBACK_ENABLED=true

# Production (.env.production)
MCP_TIMEOUT=30000
MCP_DEBUG=false
MCP_MAX_RETRIES=3
MCP_FALLBACK_ENABLED=true
```

## Security Best Practices

### Input Validation
```php
public function validateMcpRequest(array $request): void
{
    // Validate JSON-RPC structure
    if (!isset($request['jsonrpc']) || $request['jsonrpc'] !== '2.0') {
        throw new InvalidRequestException('Invalid JSON-RPC version');
    }
    
    // Validate required fields
    if (!isset($request['method']) || !is_string($request['method'])) {
        throw new InvalidRequestException('Missing or invalid method');
    }
    
    // Sanitize parameters
    if (isset($request['params'])) {
        $request['params'] = $this->sanitizeParams($request['params']);
    }
}

private function sanitizeParams(array $params): array
{
    // Remove potentially dangerous parameters
    $dangerous = ['__proto__', 'constructor', 'prototype'];
    
    return array_filter($params, function($key) use ($dangerous) {
        return !in_array($key, $dangerous);
    }, ARRAY_FILTER_USE_KEY);
}
```

### Permission Controls
```php
class SecureMcpClient extends McpClient
{
    private array $allowedMethods = [
        'initialize',
        'tools/list',
        'tools/call',
        'resources/list',
        'resources/read'
    ];
    
    public function sendRequest(string $method, array $params = []): array
    {
        if (!in_array($method, $this->allowedMethods)) {
            throw new SecurityException("Method {$method} not allowed");
        }
        
        return parent::sendRequest($method, $params);
    }
}
```

### Environment Security
```bash
# ✅ CORRECT: Secure environment variable handling
export MCP_API_TOKEN="$(cat /secure/path/token.txt)"
claude mcp add secure-server -e API_TOKEN="$MCP_API_TOKEN" -- /path/to/server

# ❌ INCORRECT: Token visible in process list
claude mcp add insecure-server -e API_TOKEN=secret123 -- /path/to/server
```

## Performance Optimization

### Connection Pooling
```php
class PooledMcpClient
{
    private SplObjectStorage $connectionPool;
    private array $connectionTimestamps;
    private int $maxIdleTime = 300; // 5 minutes
    
    public function __construct()
    {
        $this->connectionPool = new SplObjectStorage();
        $this->connectionTimestamps = [];
    }
    
    public function getConnection(string $serverId): McpConnection
    {
        $this->cleanupIdleConnections();
        
        foreach ($this->connectionPool as $connection) {
            if ($connection->getServerId() === $serverId && $connection->isHealthy()) {
                $this->connectionTimestamps[$connection->getId()] = time();
                return $connection;
            }
        }
        
        return $this->createNewConnection($serverId);
    }
    
    private function cleanupIdleConnections(): void
    {
        $now = time();
        foreach ($this->connectionPool as $connection) {
            $lastUsed = $this->connectionTimestamps[$connection->getId()] ?? 0;
            if (($now - $lastUsed) > $this->maxIdleTime) {
                $this->connectionPool->detach($connection);
                unset($this->connectionTimestamps[$connection->getId()]);
                $connection->close();
            }
        }
    }
}
```

### Response Caching
```php
class CachedMcpClient extends McpClient
{
    private array $cache = [];
    private array $cacheTtl = [
        'tools/list' => 300,     // 5 minutes
        'resources/list' => 60,  // 1 minute
        'ping' => 30            // 30 seconds
    ];
    
    public function sendRequest(string $method, array $params = []): array
    {
        $cacheKey = $this->getCacheKey($method, $params);
        
        if (isset($this->cache[$cacheKey])) {
            $cached = $this->cache[$cacheKey];
            if (time() < $cached['expires']) {
                return $cached['data'];
            }
            unset($this->cache[$cacheKey]);
        }
        
        $response = parent::sendRequest($method, $params);
        
        if (isset($this->cacheTtl[$method])) {
            $this->cache[$cacheKey] = [
                'data' => $response,
                'expires' => time() + $this->cacheTtl[$method]
            ];
        }
        
        return $response;
    }
}
```

## Comprehensive Troubleshooting Guide

### Diagnostic Commands

#### System Health Check
```bash
#!/bin/bash
# mcp_health_check.sh

echo "=== MCP System Health Check ==="

# Check Claude Code installation
echo "1. Claude Code Version:"
claude --version || echo "❌ Claude Code not installed"

# Check MCP server availability
echo -e "\n2. MCP Server Test:"
timeout 10s claude mcp serve --debug 2>&1 | head -5 || echo "❌ MCP server timeout"

# Check environment variables
echo -e "\n3. Environment Variables:"
echo "MCP_TIMEOUT: ${MCP_TIMEOUT:-not set}"
echo "ANTHROPIC_API_KEY: ${ANTHROPIC_API_KEY:+set}"

# Check available MCP servers
echo -e "\n4. Configured MCP Servers:"
claude mcp list || echo "❌ No MCP servers configured"

# Test direct execution
echo -e "\n5. Direct Execution Test:"
timeout 10s claude -p "test" || echo "❌ Direct execution failed"
```

#### Network Diagnostics
```bash
#!/bin/bash
# network_diagnostics.sh

echo "=== Network Diagnostics ==="

# Test API connectivity
echo "1. Anthropic API Connectivity:"
curl -s --max-time 10 https://api.anthropic.com/v1/messages \
    -H "x-api-key: $ANTHROPIC_API_KEY" \
    -H "content-type: application/json" \
    -H "anthropic-version: 2023-06-01" \
    -d '{"model":"claude-3-haiku-20240307","max_tokens":10,"messages":[{"role":"user","content":"test"}]}' \
    | jq '.error // "✅ API accessible"'

# Test DNS resolution
echo -e "\n2. DNS Resolution:"
nslookup api.anthropic.com || echo "❌ DNS resolution failed"

# Test port accessibility
echo -e "\n3. HTTPS Connectivity:"
nc -zv api.anthropic.com 443 2>&1 | grep -q "succeeded" && echo "✅ Port 443 accessible" || echo "❌ Port 443 blocked"
```

### Common Issue Solutions

#### Issue: "Connection closed" errors
```bash
# Solution 1: Increase timeout
export MCP_TIMEOUT=60000
claude

# Solution 2: Check server health
claude mcp list
claude mcp get problematic-server

# Solution 3: Restart with debug
claude --debug mcp serve > debug.log 2>&1 &
```

#### Issue: Server not responding
```bash
# Solution 1: Check process status
ps aux | grep "claude mcp serve"
kill -9 $(pgrep -f "claude mcp serve")  # Force kill if hanging

# Solution 2: Test server independently
/path/to/mcp/server --test-mode

# Solution 3: Verify permissions
ls -la /path/to/mcp/server
chmod +x /path/to/mcp/server
```

#### Issue: JSON-RPC protocol errors
```bash
# Solution 1: Validate message format
echo '{"jsonrpc":"2.0","id":1,"method":"initialize","params":{}}' | \
    claude mcp serve 2>&1 | head -1 | jq .

# Solution 2: Check stdout cleanliness
claude mcp serve 2>/dev/null | head -5
# Should only see JSON-RPC messages

# Solution 3: Test with minimal server
python3 -c "
import json, sys
msg = {'jsonrpc': '2.0', 'id': 1, 'result': {'capabilities': {}}}
print(json.dumps(msg))
" | claude mcp serve
```

### Monitoring and Alerting

#### Performance Monitoring
```php
class McpPerformanceMonitor
{
    private array $metrics = [];
    
    public function recordRequest(string $method, float $duration, bool $success): void
    {
        $this->metrics[] = [
            'method' => $method,
            'duration' => $duration,
            'success' => $success,
            'timestamp' => microtime(true)
        ];
        
        // Alert on slow requests
        if ($duration > 30.0) { // 30 seconds
            $this->alertSlowRequest($method, $duration);
        }
        
        // Alert on failure rate
        $recentFailures = $this->getRecentFailureRate($method);
        if ($recentFailures > 0.5) { // 50% failure rate
            $this->alertHighFailureRate($method, $recentFailures);
        }
    }
    
    private function getRecentFailureRate(string $method): float
    {
        $cutoff = microtime(true) - 300; // Last 5 minutes
        $recent = array_filter($this->metrics, fn($m) => 
            $m['method'] === $method && $m['timestamp'] > $cutoff
        );
        
        if (empty($recent)) return 0.0;
        
        $failures = array_filter($recent, fn($m) => !$m['success']);
        return count($failures) / count($recent);
    }
}
```

This comprehensive guide provides practical approaches to implementing robust, secure, and performant MCP integrations with Claude Code.