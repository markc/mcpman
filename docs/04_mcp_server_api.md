# MCP Server API Documentation

This document provides complete API reference for the Model Context Protocol (MCP) server endpoints that allow Claude Code to interact with the Laravel Loop Filament application.

## Base Configuration

### Endpoint
```
POST http://localhost:8000/api/mcp
```

### Authentication
```http
Authorization: Bearer <api-key>
Content-Type: application/json
```

### Protocol
JSON-RPC 2.0 over HTTP

## Server Information

### Initialize Connection
Establishes connection and exchanges capabilities.

**Request**:
```json
{
  "jsonrpc": "2.0",
  "id": "init-1",
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

**Response**:
```json
{
  "jsonrpc": "2.0",
  "id": "init-1",
  "result": {
    "protocolVersion": "2024-11-05",
    "capabilities": {
      "tools": { "listChanged": false },
      "resources": { "subscribe": false, "listChanged": false },
      "prompts": { "listChanged": false }
    },
    "serverInfo": {
      "name": "Laravel-Filament-MCP",
      "version": "1.0.0"
    }
  }
}
```

## Tools API

### List Available Tools

**Request**:
```json
{
  "jsonrpc": "2.0",
  "id": "tools-list-1",
  "method": "tools/list"
}
```

**Response**:
```json
{
  "jsonrpc": "2.0",
  "id": "tools-list-1",
  "result": {
    "tools": [
      {
        "name": "create_dataset",
        "description": "Create a new dataset",
        "inputSchema": {
          "type": "object",
          "properties": {
            "name": { "type": "string" },
            "description": { "type": "string" },
            "type": { "type": "string", "enum": ["json", "csv", "xml", "yaml", "text"] },
            "schema": { "type": "object" },
            "metadata": { "type": "object" }
          },
          "required": ["name", "type"]
        }
      },
      {
        "name": "list_datasets",
        "description": "List all datasets",
        "inputSchema": {
          "type": "object",
          "properties": {
            "status": { "type": "string", "enum": ["active", "archived", "processing"] },
            "type": { "type": "string", "enum": ["json", "csv", "xml", "yaml", "text"] }
          }
        }
      },
      {
        "name": "create_document",
        "description": "Create a new document",
        "inputSchema": {
          "type": "object",
          "properties": {
            "title": { "type": "string" },
            "content": { "type": "string" },
            "type": { "type": "string", "enum": ["text", "json", "markdown", "html"] },
            "dataset_id": { "type": "integer" },
            "metadata": { "type": "object" }
          },
          "required": ["title", "content"]
        }
      },
      {
        "name": "search_documents",
        "description": "Search documents by content",
        "inputSchema": {
          "type": "object",
          "properties": {
            "query": { "type": "string" },
            "dataset_id": { "type": "integer" },
            "type": { "type": "string" }
          },
          "required": ["query"]
        }
      }
    ]
  }
}
```

### Call Tool

**Request Format**:
```json
{
  "jsonrpc": "2.0",
  "id": "tool-call-1",
  "method": "tools/call",
  "params": {
    "name": "tool_name",
    "arguments": {
      "param1": "value1",
      "param2": "value2"
    }
  }
}
```

#### Create Dataset Tool

**Request**:
```json
{
  "jsonrpc": "2.0",
  "id": "create-dataset-1",
  "method": "tools/call",
  "params": {
    "name": "create_dataset",
    "arguments": {
      "name": "Customer Data",
      "description": "Customer information dataset",
      "type": "json",
      "schema": {
        "name": "string",
        "email": "string",
        "created_at": "datetime"
      },
      "metadata": {
        "source": "api_import",
        "version": "1.0"
      }
    }
  }
}
```

**Response**:
```json
{
  "jsonrpc": "2.0",
  "id": "create-dataset-1",
  "result": {
    "content": [
      {
        "type": "text",
        "text": "Dataset 'Customer Data' created successfully with ID: 123"
      }
    ]
  }
}
```

#### List Datasets Tool

**Request**:
```json
{
  "jsonrpc": "2.0",
  "id": "list-datasets-1",
  "method": "tools/call",
  "params": {
    "name": "list_datasets",
    "arguments": {
      "status": "active",
      "type": "json"
    }
  }
}
```

**Response**:
```json
{
  "jsonrpc": "2.0",
  "id": "list-datasets-1",
  "result": {
    "content": [
      {
        "type": "text",
        "text": "Found 2 datasets:\n\n• #123 Customer Data (json) - active\n  Customer information dataset\n\n• #124 Product Catalog (json) - active\n  Product listing and details\n"
      }
    ]
  }
}
```

#### Create Document Tool

**Request**:
```json
{
  "jsonrpc": "2.0",
  "id": "create-doc-1",
  "method": "tools/call",
  "params": {
    "name": "create_document",
    "arguments": {
      "title": "API Documentation",
      "content": "# API Documentation\n\nThis document describes the API endpoints...",
      "type": "markdown",
      "dataset_id": 123,
      "metadata": {
        "version": "1.0",
        "author": "claude"
      }
    }
  }
}
```

**Response**:
```json
{
  "jsonrpc": "2.0",
  "id": "create-doc-1",
  "result": {
    "content": [
      {
        "type": "text",
        "text": "Document 'API Documentation' created successfully with ID: 456"
      }
    ]
  }
}
```

#### Search Documents Tool

**Request**:
```json
{
  "jsonrpc": "2.0",
  "id": "search-docs-1",
  "method": "tools/call",
  "params": {
    "name": "search_documents",
    "arguments": {
      "query": "API endpoint",
      "dataset_id": 123
    }
  }
}
```

**Response**:
```json
{
  "jsonrpc": "2.0",
  "id": "search-docs-1",
  "result": {
    "content": [
      {
        "type": "text",
        "text": "Found 3 documents:\n\n• #456 API Documentation (markdown)\n  Created: 2 hours ago\n\n• #457 Endpoint Reference (text)\n  Created: 1 day ago\n\n• #458 Integration Guide (html)\n  Created: 3 days ago\n"
      }
    ]
  }
}
```

## Resources API

### List Resources

**Request**:
```json
{
  "jsonrpc": "2.0",
  "id": "resources-list-1",
  "method": "resources/list"
}
```

**Response**:
```json
{
  "jsonrpc": "2.0",
  "id": "resources-list-1",
  "result": {
    "resources": [
      {
        "uri": "filament://datasets",
        "name": "Datasets",
        "description": "All available datasets",
        "mimeType": "application/json"
      },
      {
        "uri": "filament://documents",
        "name": "Documents",
        "description": "All available documents",
        "mimeType": "application/json"
      }
    ]
  }
}
```

### Read Resource

**Request**:
```json
{
  "jsonrpc": "2.0",
  "id": "resource-read-1",
  "method": "resources/read",
  "params": {
    "uri": "filament://datasets"
  }
}
```

**Response**:
```json
{
  "jsonrpc": "2.0",
  "id": "resource-read-1",
  "result": {
    "contents": [
      {
        "uri": "filament://datasets",
        "mimeType": "application/json",
        "text": "[\n  {\n    \"id\": 123,\n    \"name\": \"Customer Data\",\n    \"type\": \"json\",\n    \"status\": \"active\",\n    \"created_at\": \"2025-06-13T10:00:00Z\",\n    \"user\": {\n      \"id\": 1,\n      \"name\": \"Admin User\"\n    }\n  }\n]"
      }
    ]
  }
}
```

## Prompts API

### List Prompts

**Request**:
```json
{
  "jsonrpc": "2.0",
  "id": "prompts-list-1",
  "method": "prompts/list"
}
```

**Response**:
```json
{
  "jsonrpc": "2.0",
  "id": "prompts-list-1",
  "result": {
    "prompts": [
      {
        "name": "analyze_dataset",
        "description": "Analyze a dataset structure and content",
        "arguments": [
          {
            "name": "dataset_id",
            "description": "ID of the dataset to analyze",
            "required": true
          }
        ]
      },
      {
        "name": "summarize_documents",
        "description": "Create a summary of documents in a dataset",
        "arguments": [
          {
            "name": "dataset_id",
            "description": "ID of the dataset to summarize",
            "required": false
          }
        ]
      }
    ]
  }
}
```

### Get Prompt

**Request**:
```json
{
  "jsonrpc": "2.0",
  "id": "prompt-get-1",
  "method": "prompts/get",
  "params": {
    "name": "analyze_dataset",
    "arguments": {
      "dataset_id": 123
    }
  }
}
```

**Response**:
```json
{
  "jsonrpc": "2.0",
  "id": "prompt-get-1",
  "result": {
    "description": "Analysis prompt for dataset",
    "messages": [
      {
        "role": "user",
        "content": [
          {
            "type": "text",
            "text": "Please analyze this dataset:\n\nName: Customer Data\nType: json\nDescription: Customer information dataset\nDocuments: 15\n\nSchema:\n{\n  \"name\": \"string\",\n  \"email\": \"string\",\n  \"created_at\": \"datetime\"\n}\n\nSample documents:\n- Customer Registration Form\n- Email Validation Records\n- Account Creation Logs"
          }
        ]
      }
    ]
  }
}
```

## Error Responses

### Standard Error Format
```json
{
  "jsonrpc": "2.0",
  "id": "request-id",
  "error": {
    "code": -32602,
    "message": "Invalid params: name is required"
  }
}
```

### Error Codes
- `-32700`: Parse error (malformed JSON)
- `-32600`: Invalid request (missing required fields)
- `-32601`: Method not found (unknown method)
- `-32602`: Invalid params (validation failure)
- `-32603`: Internal error (server error)

### Authentication Errors
```json
{
  "jsonrpc": "2.0",
  "error": {
    "code": -32600,
    "message": "Invalid or missing API key"
  }
}
```

## Rate Limiting

### Default Limits
- **60 requests per minute** per API key
- **1000 requests per hour** per API key
- **10000 requests per day** per API key

### Rate Limit Headers
```http
X-RateLimit-Limit: 60
X-RateLimit-Remaining: 45
X-RateLimit-Reset: 1625097600
```

### Rate Limit Exceeded Response
```json
{
  "jsonrpc": "2.0",
  "error": {
    "code": -32603,
    "message": "Rate limit exceeded. Try again in 60 seconds."
  }
}
```

## Testing Examples

### Using cURL

#### Test Connection
```bash
curl -X POST http://localhost:8000/api/mcp \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer mcp_your_api_key_here" \
  -d '{
    "jsonrpc": "2.0",
    "id": "test-1",
    "method": "initialize",
    "params": {
      "protocolVersion": "2024-11-05",
      "clientInfo": {
        "name": "Test-Client",
        "version": "1.0.0"
      }
    }
  }'
```

#### List Tools
```bash
curl -X POST http://localhost:8000/api/mcp \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer mcp_your_api_key_here" \
  -d '{
    "jsonrpc": "2.0",
    "id": "test-2",
    "method": "tools/list"
  }'
```

#### Create Dataset
```bash
curl -X POST http://localhost:8000/api/mcp \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer mcp_your_api_key_here" \
  -d '{
    "jsonrpc": "2.0",
    "id": "test-3",
    "method": "tools/call",
    "params": {
      "name": "create_dataset",
      "arguments": {
        "name": "Test Dataset",
        "description": "Created via API",
        "type": "json"
      }
    }
  }'
```

### Creating Test API Key

Use Laravel Tinker to create a test API key:

```bash
php artisan tinker
```

```php
App\Models\ApiKey::create([
    'name' => 'Test Key',
    'key' => 'mcp_test_' . Str::random(32),
    'permissions' => [
        'datasets' => 'read,write',
        'documents' => 'read,write',
        'connections' => 'read'
    ],
    'rate_limits' => [
        'requests_per_minute' => '60',
        'requests_per_hour' => '1000'
    ],
    'is_active' => true,
    'user_id' => 1
]);
```

## Integration Examples

### Claude Code Configuration
Add this server configuration to your Claude Code MCP settings:

```json
{
  "mcpServers": {
    "laravel-loop-filament": {
      "command": "stdio",
      "args": [],
      "env": {
        "API_KEY": "your-api-key-here",
        "BASE_URL": "http://localhost:8000/api/mcp"
      }
    }
  }
}
```

For more implementation examples and troubleshooting, see the [Testing Guide](08_testing_guide.md).