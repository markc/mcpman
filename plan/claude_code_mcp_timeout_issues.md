# Claude Code MCP Timeout Issues and Solutions

## Overview

Timeout issues are among the most common problems when implementing MCP (Model Context Protocol) integrations with Claude Code. This document provides comprehensive information about timeout causes, configuration options, and solutions.

## Types of Timeout Issues

### 1. MCP Server Startup Timeout
**Symptoms**: 
- `The process "'claude' 'mcp' 'serve' '--debug'" exceeded the timeout of X seconds`
- Server appears to hang during initialization

**Causes**:
- MCP server waiting for persistent connection
- Authentication issues requiring user interaction
- Server initialization complexity
- Missing required environment variables

### 2. Tool Call Timeout
**Symptoms**:
- `-32000: Connection closed` errors during long operations
- Tool calls that work in short tests but fail in production

**Causes**:
- Long-running operations (file searches, large data processing)
- Network latency for remote servers
- Server processing time exceeding timeout limits

### 3. Connection Handshake Timeout
**Symptoms**:
- Timeout during initial MCP handshake
- Failed protocol negotiation

**Causes**:
- Server not responding to JSON-RPC initialize request
- Protocol version mismatch
- Stdout contamination breaking JSON-RPC communication

## Current Default Timeouts

| Component | Default Timeout | Configurable |
|-----------|----------------|--------------|
| MCP Client Tool Calls | 60 seconds | Yes (MCP_TIMEOUT) |
| MCP Server Startup | Variable | Yes (MCP_TIMEOUT) |
| Direct Claude Execution | 30 seconds | No (Process timeout) |
| Connection Handshake | 10-15 seconds | Limited |

## Configuration Solutions

### Environment Variable Configuration

#### MCP_TIMEOUT
```bash
# Set longer timeout for MCP operations (milliseconds)
export MCP_TIMEOUT=30000  # 30 seconds
claude

# For extremely long operations
export MCP_TIMEOUT=120000  # 2 minutes
claude

# Inline usage
MCP_TIMEOUT=45000 claude mcp serve
```

#### Usage Examples
```bash
# Development with debug and long timeout
MCP_TIMEOUT=60000 claude --debug

# Production with moderate timeout
MCP_TIMEOUT=30000 claude

# Quick operations with short timeout
MCP_TIMEOUT=10000 claude -p "simple question"
```

### Application-Level Timeout Configuration

#### Laravel Process Timeout (Our Implementation)
```php
// Current implementation
$result = Process::timeout(15)  // 15 seconds
    ->input($jsonRequest)
    ->run($command);

// Recommended configuration
$result = Process::timeout(env('CLAUDE_MCP_TIMEOUT', 30))
    ->input($jsonRequest)
    ->run($command);
```

#### Environment Configuration
```bash
# In .env file
CLAUDE_MCP_TIMEOUT=30
CLAUDE_DIRECT_TIMEOUT=45
CLAUDE_MAX_RETRIES=3
```

## Known Issues from GitHub

### Issue #424: MCP Timeout Configuration
**Problem**: Default 60-second timeout insufficient for long operations
**Status**: Partially resolved with MCP_TIMEOUT environment variable
**Official Response**: Default timeout planned increase to 30 seconds

### Issue #723: Server Connection Timing
**Problem**: Claude doesn't wait for slow MCP servers to connect before processing prompts
**Impact**: Server capabilities not available for initial requests
**Workaround**: Use `--wait-for-mcp-servers` flag when available

### Issue #768: Protocol Version Validation
**Problem**: Protocol version validation errors with stdio servers
**Symptoms**: Handshake failures and timeout-like behavior
**Solution**: Ensure protocol version compatibility (2024-11-05)

## Diagnostic Approaches

### Debug Logging
```bash
# Enable comprehensive debug output
claude --debug mcp serve

# Monitor specific timeout events
claude --debug 2>&1 | grep -i timeout

# Log to file for analysis
claude --debug > claude_debug.log 2>&1
```

### Manual Testing
```bash
# Test direct Claude execution
time claude -p "what day is it?"

# Test MCP server startup
timeout 30s claude mcp serve --debug

# Test JSON-RPC manually
echo '{"jsonrpc":"2.0","id":1,"method":"initialize","params":{"protocolVersion":"2024-11-05"}}' | claude mcp serve
```

### Process Monitoring
```bash
# Monitor process behavior
ps aux | grep claude
top -p $(pgrep claude)

# Check for hanging processes
lsof -p $(pgrep claude)
```

## Solutions by Use Case

### Local Development Environment
```bash
# Optimal settings for development
export MCP_TIMEOUT=45000
export CLAUDE_DEBUG=true
claude --debug
```

### Production Environment
```bash
# Balanced settings for production
export MCP_TIMEOUT=30000
export CLAUDE_RETRY_COUNT=3
claude
```

### CI/CD Pipeline
```bash
# Fast settings for automated testing
export MCP_TIMEOUT=15000
claude -p "test command"
```

### Long-Running Operations
```bash
# Extended timeouts for complex tasks
export MCP_TIMEOUT=300000  # 5 minutes
claude
```

## Implementation Patterns

### Retry Logic with Exponential Backoff
```php
public function executeWithRetry(callable $operation, int $maxRetries = 3): array
{
    $attempt = 0;
    $baseDelay = 1000; // 1 second

    while ($attempt < $maxRetries) {
        try {
            return $operation();
        } catch (TimeoutException $e) {
            $attempt++;
            if ($attempt >= $maxRetries) {
                throw $e;
            }
            
            $delay = $baseDelay * pow(2, $attempt - 1);
            usleep($delay * 1000); // Convert to microseconds
        }
    }
}
```

### Graceful Degradation
```php
public function executeWithFallback(array $request): array
{
    // Try MCP server first
    try {
        $result = $this->tryMcpServer($request);
        if ($result !== null) {
            return $result;
        }
    } catch (TimeoutException $e) {
        Log::info('MCP server timeout, trying direct execution');
    }

    // Try direct Claude execution
    try {
        return $this->executeDirectClaude($request);
    } catch (TimeoutException $e) {
        Log::info('Direct execution timeout, using fallback');
    }

    // Use mock/cached response
    return $this->getFallbackResponse($request);
}
```

### Progressive Timeout Strategy
```php
public function executeWithProgressiveTimeout(array $request): array
{
    $timeouts = [10, 30, 60]; // Progressive timeouts in seconds
    
    foreach ($timeouts as $timeout) {
        try {
            return Process::timeout($timeout)
                ->input(json_encode($request))
                ->run(['claude', 'mcp', 'serve']);
        } catch (TimeoutException $e) {
            Log::debug("Timeout at {$timeout}s, trying longer timeout");
            continue;
        }
    }
    
    throw new TimeoutException('All timeout attempts failed');
}
```

## Best Practices

### Timeout Configuration
1. **Start Conservative**: Begin with shorter timeouts and increase as needed
2. **Environment-Specific**: Use different timeouts for dev/staging/production
3. **Operation-Aware**: Longer timeouts for complex operations
4. **User Experience**: Balance responsiveness with functionality

### Error Handling
1. **Graceful Degradation**: Always have fallback mechanisms
2. **User Communication**: Inform users about timeout and retry status
3. **Logging**: Comprehensive logging for timeout analysis
4. **Monitoring**: Track timeout patterns for optimization

### Performance Optimization
1. **Server Selection**: Choose fastest appropriate MCP servers
2. **Caching**: Cache results to avoid repeated timeout-prone operations
3. **Async Processing**: Use background jobs for long operations
4. **Connection Pooling**: Reuse connections where possible

## Troubleshooting Checklist

### When MCP Server Times Out:
- [ ] Check MCP_TIMEOUT environment variable
- [ ] Verify server executable exists and is accessible
- [ ] Test server independently outside Claude Code
- [ ] Check for authentication prompts or interactive elements
- [ ] Verify stdout cleanliness (no non-JSON output)
- [ ] Monitor system resources (CPU, memory)
- [ ] Check network connectivity for remote servers

### When Direct Execution Times Out:
- [ ] Test Claude authentication status
- [ ] Check for pending updates or maintenance
- [ ] Verify internet connectivity
- [ ] Test with simpler prompts first
- [ ] Check system resource availability
- [ ] Review Claude Code configuration

### When Handshake Times Out:
- [ ] Verify JSON-RPC 2.0 message format
- [ ] Check protocol version compatibility
- [ ] Ensure proper message termination (newlines)
- [ ] Verify no stdout contamination
- [ ] Test with minimal server implementation

This comprehensive guide provides the foundation for diagnosing and resolving timeout issues in Claude Code MCP implementations, ensuring robust and reliable integrations.