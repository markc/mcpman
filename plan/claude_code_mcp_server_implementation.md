# Claude Code MCP Server Implementation Guide

## Overview

Claude Code can function as a Model Context Protocol (MCP) server using the `claude mcp serve` command. This allows other applications to integrate with Claude's development tools through the standardized MCP protocol.

## Starting the MCP Server

### Basic Command
```bash
claude mcp serve
```

### With Debug Output
```bash
claude mcp serve --debug
```

## Technical Architecture

### Protocol Foundation
- **Protocol**: JSON-RPC 2.0
- **Transport**: stdio (standard input/output)
- **Message Format**: Newline-delimited JSON messages
- **Communication**: Bidirectional, persistent connection

### MCP Server Capabilities
Claude Code MCP server exposes these tools:
- **View**: Read and display file contents
- **Edit**: Intelligently modify files with context awareness
- **LS**: List directory contents and file information
- **GlobTool**: Pattern-based file searching across directories
- **GrepTool**: Text search within files with regex support
- **Replace**: Find and replace operations across multiple files
- **Bash**: Execute shell commands with output capture
- **dispatch_agent**: Task delegation for complex operations

## Initialization Handshake

### Required JSON-RPC Methods
Every MCP server must implement:

1. **initialize**: Establishes connection and declares capabilities
2. **tools/list**: Returns available tools and their schemas
3. **tools/call**: Executes specific tools with provided parameters

### Handshake Sequence

#### 1. Initialize Request (Client → Server)
```json
{
  "jsonrpc": "2.0",
  "id": 0,
  "method": "initialize",
  "params": {
    "capabilities": {},
    "clientInfo": {
      "name": "Laravel-Filament-MCP-Client",
      "version": "1.0.0"
    },
    "protocolVersion": "2024-11-05"
  }
}
```

#### 2. Initialize Response (Server → Client)
```json
{
  "jsonrpc": "2.0",
  "id": 0,
  "result": {
    "protocolVersion": "2024-11-05",
    "serverInfo": {
      "name": "claude-code",
      "version": "1.0.0"
    },
    "capabilities": {
      "tools": {}
    }
  }
}
```

#### 3. Notifications/Initialized (Client → Server)
```json
{
  "jsonrpc": "2.0",
  "method": "notifications/initialized"
}
```

**Note**: There's a known issue where Claude Code's MCP client doesn't send the `notifications/initialized` message, which can cause properly-implemented MCP servers to reject tool requests.

## Critical Implementation Requirements

### Stdout Cleanliness
- **Only valid JSON-RPC 2.0 messages** should be written to stdout
- **No startup banners, prompts, spinners, or logs** on stdout
- **No ANSI escape codes** or interactive elements
- Violation of this requirement breaks MCP protocol communication

### Message Format
- Messages must be **newline-delimited**
- Messages must **not contain embedded newlines**
- Each message must be valid JSON-RPC 2.0

### Timeout Handling
- Servers should establish timeouts for requests
- When timeout occurs, issue `$/cancelRequest` notification
- Maximum overall timeout should be enforced

## Configuration for Client Applications

### Claude Desktop Configuration
Add to `~/Library/Application Support/Claude/claude_desktop_config.json`:
```json
{
  "mcpServers": {
    "claude-code": {
      "command": "claude",
      "args": ["mcp", "serve"],
      "env": {}
    }
  }
}
```

### Docker Configuration
```json
{
  "mcpServers": {
    "claude-code": {
      "command": "docker",
      "args": ["run", "-v", "/path/to/workspace:/workspace", "claude-code-mcp"]
    }
  }
}
```

## Security Considerations

- Server runs with **user-level permissions** of executing user
- **No built-in authentication** beyond file system permissions
- Tool calls execute **without user confirmation** when accessed via MCP
- Only expose to **trusted applications** and secure networks
- Client applications should implement **confirmation mechanisms** for sensitive operations

## Known Limitations

### Connection Model
- Designed for **persistent connections**, not single-shot requests
- MCP server expects to stay running and handle multiple requests
- Single command execution with stdio input may timeout

### Context Restrictions
- Can only access files and commands within user's permission context
- Some users report token storage issues on macOS
- Nesting problems when Claude Code MCP server tries to access its own configured MCP servers

### Stability Issues
- Occasional startup failures in complex setups
- Configuration conflicts with multiple MCP servers
- Connection timeouts during initialization

## Best Practices

### For Server Implementation
1. Implement robust error handling for all tool calls
2. Provide meaningful error messages in JSON-RPC error responses
3. Log errors to stderr, never stdout
4. Validate all input parameters before execution
5. Handle cancellation requests properly

### For Client Implementation
1. Implement proper timeout handling
2. Handle server disconnections gracefully
3. Validate server responses before processing
4. Implement retry logic for transient failures
5. Use appropriate MCP_TIMEOUT environment variable

### Development and Testing
1. Test with `--debug` flag for troubleshooting
2. Monitor both stdout and stderr during development
3. Validate JSON-RPC message format
4. Test handshake sequence thoroughly
5. Verify timeout behavior under various conditions

## Troubleshooting Common Issues

### Server Not Responding
- Check if server process is running
- Verify stdout cleanliness
- Check for proper JSON-RPC format
- Monitor stderr for error messages

### Timeout Issues
- Increase `MCP_TIMEOUT` environment variable
- Check server initialization time
- Verify network connectivity for remote servers

### Protocol Errors
- Validate JSON-RPC 2.0 compliance
- Check protocol version compatibility
- Verify required method implementations

This implementation guide provides the foundation for successfully deploying and integrating Claude Code as an MCP server in various development environments.