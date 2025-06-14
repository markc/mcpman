# Model Context Protocol (MCP) Specification Guide

## Protocol Overview

The Model Context Protocol (MCP) is an open standard that enables AI assistants to securely connect to and interact with external data sources and tools. Built on JSON-RPC 2.0, MCP provides a universal protocol for AI model integrations.

### Core Principles
- **Standardization**: Universal protocol replacing fragmented integrations
- **Security**: Controlled access with permission management
- **Flexibility**: Support for various transport mechanisms
- **Extensibility**: Plugin architecture for custom capabilities

### Architecture Components
- **Hosts**: AI applications (Claude Desktop, Claude Code)
- **Clients**: MCP client implementations within hosts
- **Servers**: External tools and data sources
- **Transport**: Communication mechanism (stdio, HTTP+SSE)

## JSON-RPC 2.0 Foundation

### Message Structure
All MCP messages must conform to JSON-RPC 2.0 specification:

```json
{
  "jsonrpc": "2.0",
  "id": "unique-request-id",
  "method": "method-name",
  "params": {
    "parameter": "value"
  }
}
```

### Response Structure
```json
{
  "jsonrpc": "2.0",
  "id": "matching-request-id",
  "result": {
    "data": "response-data"
  }
}
```

### Error Structure
```json
{
  "jsonrpc": "2.0",
  "id": "matching-request-id",
  "error": {
    "code": -32603,
    "message": "Internal error",
    "data": {
      "additional": "error-details"
    }
  }
}
```

## Protocol Handshake

### 1. Initialize Request (Client → Server)
```json
{
  "jsonrpc": "2.0",
  "id": 1,
  "method": "initialize",
  "params": {
    "protocolVersion": "2024-11-05",
    "capabilities": {
      "roots": {
        "listChanged": false
      }
    },
    "clientInfo": {
      "name": "Claude-Code",
      "version": "1.0.0"
    }
  }
}
```

### 2. Initialize Response (Server → Client)
```json
{
  "jsonrpc": "2.0",
  "id": 1,
  "result": {
    "protocolVersion": "2024-11-05",
    "capabilities": {
      "tools": {},
      "resources": {},
      "prompts": {}
    },
    "serverInfo": {
      "name": "my-mcp-server",
      "version": "1.0.0"
    }
  }
}
```

### 3. Initialized Notification (Client → Server)
```json
{
  "jsonrpc": "2.0",
  "method": "notifications/initialized"
}
```

**Note**: Claude Code currently skips the `notifications/initialized` step, which may cause some servers to reject subsequent requests.

## Core MCP Methods

### Tools Interface

#### tools/list
Lists available tools provided by the server.

**Request**:
```json
{
  "jsonrpc": "2.0",
  "id": 2,
  "method": "tools/list"
}
```

**Response**:
```json
{
  "jsonrpc": "2.0",
  "id": 2,
  "result": {
    "tools": [
      {
        "name": "file_reader",
        "description": "Read file contents",
        "inputSchema": {
          "type": "object",
          "properties": {
            "path": {
              "type": "string",
              "description": "File path to read"
            }
          },
          "required": ["path"]
        }
      }
    ]
  }
}
```

#### tools/call
Executes a specific tool with provided arguments.

**Request**:
```json
{
  "jsonrpc": "2.0",
  "id": 3,
  "method": "tools/call",
  "params": {
    "name": "file_reader",
    "arguments": {
      "path": "/path/to/file.txt"
    }
  }
}
```

**Response**:
```json
{
  "jsonrpc": "2.0",
  "id": 3,
  "result": {
    "content": [
      {
        "type": "text",
        "text": "File content here..."
      }
    ]
  }
}
```

### Resources Interface

#### resources/list
Lists available resources provided by the server.

**Request**:
```json
{
  "jsonrpc": "2.0",
  "id": 4,
  "method": "resources/list"
}
```

**Response**:
```json
{
  "jsonrpc": "2.0",
  "id": 4,
  "result": {
    "resources": [
      {
        "uri": "file:///project/README.md",
        "name": "Project README",
        "description": "Main project documentation",
        "mimeType": "text/markdown"
      }
    ]
  }
}
```

#### resources/read
Reads a specific resource by URI.

**Request**:
```json
{
  "jsonrpc": "2.0",
  "id": 5,
  "method": "resources/read",
  "params": {
    "uri": "file:///project/README.md"
  }
}
```

**Response**:
```json
{
  "jsonrpc": "2.0",
  "id": 5,
  "result": {
    "contents": [
      {
        "uri": "file:///project/README.md",
        "mimeType": "text/markdown",
        "text": "# Project Title\n\nProject description..."
      }
    ]
  }
}
```

### Prompts Interface

#### prompts/list
Lists available prompt templates.

**Request**:
```json
{
  "jsonrpc": "2.0",
  "id": 6,
  "method": "prompts/list"
}
```

**Response**:
```json
{
  "jsonrpc": "2.0",
  "id": 6,
  "result": {
    "prompts": [
      {
        "name": "code_review",
        "description": "Perform code review",
        "arguments": [
          {
            "name": "language",
            "description": "Programming language",
            "required": true
          }
        ]
      }
    ]
  }
}
```

#### prompts/get
Retrieves a specific prompt template.

**Request**:
```json
{
  "jsonrpc": "2.0",
  "id": 7,
  "method": "prompts/get",
  "params": {
    "name": "code_review",
    "arguments": {
      "language": "python"
    }
  }
}
```

**Response**:
```json
{
  "jsonrpc": "2.0",
  "id": 7,
  "result": {
    "description": "Code review for Python",
    "messages": [
      {
        "role": "user",
        "content": {
          "type": "text",
          "text": "Please review this Python code for best practices..."
        }
      }
    ]
  }
}
```

## Transport Mechanisms

### Stdio Transport

#### Characteristics
- **Use Case**: Local servers, subprocess communication
- **Protocol**: JSON-RPC 2.0 over stdin/stdout
- **Message Format**: Newline-delimited JSON
- **Connection**: Persistent subprocess

#### Implementation Requirements
```bash
# Server must read from stdin, write to stdout
while read -r line; do
    # Process JSON-RPC message
    process_jsonrpc "$line"
done

# Critical: Only JSON-RPC messages to stdout
echo '{"jsonrpc":"2.0","id":1,"result":{}}'
```

#### Stdout Purity Rules
- ✅ Only valid JSON-RPC 2.0 messages
- ❌ No startup banners or logs
- ❌ No interactive prompts
- ❌ No ANSI escape codes
- ❌ No debugging output

### HTTP+SSE Transport

#### Characteristics
- **Use Case**: Remote servers, web services
- **Protocol**: HTTP for requests, Server-Sent Events for server-initiated messages
- **Connection**: Persistent HTTP connection with SSE

#### HTTP Endpoint Structure
```http
POST /mcp HTTP/1.1
Host: server.example.com
Content-Type: application/json
Accept: text/event-stream

{"jsonrpc":"2.0","id":1,"method":"initialize","params":{}}
```

#### SSE Response Format
```
event: message
data: {"jsonrpc":"2.0","id":1,"result":{}}

event: notification
data: {"jsonrpc":"2.0","method":"notification","params":{}}
```

## Error Codes

### Standard JSON-RPC Errors
| Code | Message | Description |
|------|---------|-------------|
| -32700 | Parse error | Invalid JSON received |
| -32600 | Invalid Request | Invalid JSON-RPC format |
| -32601 | Method not found | Method does not exist |
| -32602 | Invalid params | Invalid method parameters |
| -32603 | Internal error | Internal server error |

### MCP-Specific Errors
| Code | Message | Description |
|------|---------|-------------|
| -32000 | Connection closed | Transport connection lost |
| -32001 | Request timeout | Request exceeded timeout |
| -32002 | Method not supported | Method not implemented |
| -32003 | Resource not found | Requested resource unavailable |

## Protocol Versioning

### Current Version: 2024-11-05

#### Version Negotiation
1. Client sends supported version in `initialize` request
2. Server responds with compatible version
3. If incompatible, client should disconnect

#### Backward Compatibility
- Servers should support previous protocol versions when possible
- Clients should handle version mismatches gracefully
- New features should be additive, not breaking

## Security Considerations

### Authentication
- MCP protocol itself does not define authentication
- Implementations should use transport-layer security
- Consider OAuth, API keys, or certificate-based auth

### Authorization
- Servers should implement capability-based permissions
- Tools should validate user permissions before execution
- Resources should enforce access controls

### Input Validation
```json
{
  "jsonrpc": "2.0",
  "id": 1,
  "method": "tools/call",
  "params": {
    "name": "validated_tool",
    "arguments": {
      "sanitized_input": "safe_value"
    }
  }
}
```

### Output Sanitization
- Escape HTML/XML content in responses
- Validate file paths and URIs
- Prevent path traversal attacks
- Sanitize shell command outputs

## Implementation Guidelines

### Server Implementation Checklist
- [ ] Implement required methods: `initialize`, `tools/list`, `tools/call`
- [ ] Follow JSON-RPC 2.0 specification exactly
- [ ] Maintain stdout purity for stdio transport
- [ ] Handle protocol version negotiation
- [ ] Implement proper error responses
- [ ] Support cancellation via `$/cancelRequest`
- [ ] Validate all inputs thoroughly
- [ ] Log errors to stderr, not stdout

### Client Implementation Checklist
- [ ] Send proper `initialize` request with capabilities
- [ ] Handle protocol version negotiation
- [ ] Implement timeout and retry logic
- [ ] Support request cancellation
- [ ] Parse JSON-RPC responses correctly
- [ ] Handle transport disconnections gracefully
- [ ] Validate server responses
- [ ] Implement security measures

### Testing Considerations
- Test handshake sequence thoroughly
- Verify JSON-RPC compliance
- Test timeout and error conditions
- Validate security measures
- Test with multiple concurrent requests
- Verify resource cleanup on disconnection

## Advanced Features

### Request Cancellation
```json
{
  "jsonrpc": "2.0",
  "method": "$/cancelRequest",
  "params": {
    "id": "request-to-cancel"
  }
}
```

### Progress Notifications
```json
{
  "jsonrpc": "2.0",
  "method": "$/progress",
  "params": {
    "token": "operation-token",
    "value": {
      "kind": "report",
      "percentage": 50,
      "message": "Processing..."
    }
  }
}
```

### Batched Requests
```json
[
  {"jsonrpc": "2.0", "id": 1, "method": "tools/list"},
  {"jsonrpc": "2.0", "id": 2, "method": "resources/list"}
]
```

This specification provides the complete technical foundation for implementing MCP-compliant servers and clients that interoperate with Claude Code and other MCP hosts.