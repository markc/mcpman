# MCP Architecture & Data Models

This document explains the Model Context Protocol (MCP) integration architecture and the core data models used in Laravel Loop Filament.

## MCP Integration Overview

Laravel Loop Filament implements **bidirectional MCP communication**:

1. **Incoming MCP Server**: Claude Code → Filament
2. **Outgoing MCP Client**: Filament → Claude Code

This enables full two-way control and data exchange between Claude Code and the Filament admin interface.

## Architecture Components

### 1. Incoming MCP Server (Claude Code → Filament)

#### Service Layer
- **Location**: `app/Services/McpServer.php`
- **Purpose**: Handles incoming MCP requests from Claude Code
- **Protocol**: JSON-RPC 2.0 over HTTP
- **Authentication**: API key-based with fine-grained permissions

#### Controller Layer
- **Location**: `app/Http/Controllers/McpController.php`
- **Purpose**: HTTP endpoint handler for MCP requests
- **Endpoint**: `POST /api/mcp`
- **Features**: API key validation, request routing, error handling

#### API Routes
- **Location**: `routes/api.php`
- **Endpoints**:
  - `POST /api/mcp` - Main MCP handler
  - `POST /api/mcp/tools` - Tool-specific endpoint
  - `POST /api/mcp/resources` - Resource access endpoint
  - `POST /api/mcp/prompts` - Prompt management endpoint

### 2. Outgoing MCP Client (Filament → Claude Code)

#### Client Service
- **Location**: `app/Services/McpClient.php`
- **Purpose**: Manages connections to external Claude Code instances
- **Transport Types**: HTTP, WebSocket, stdio
- **Features**: Connection pooling, error handling, timeout management

#### Connection Management
- **Model**: `app/Models/McpConnection.php`
- **Purpose**: Stores and manages outgoing MCP connections
- **Features**: Status tracking, authentication config, capabilities detection

## Core Data Models

### 1. Dataset Model

**Location**: `app/Models/Dataset.php`

**Purpose**: Represents data collections that can be managed through MCP

**Fields**:
```php
- id: Primary key
- name: Human-readable dataset name
- slug: URL-friendly identifier (auto-generated)
- description: Optional dataset description
- type: Data format (json, csv, xml, yaml, text)
- status: Dataset status (active, archived, processing)
- schema: JSON schema definition (optional)
- metadata: Additional configuration data
- user_id: Owner reference
- created_at, updated_at: Timestamps
```

**Relationships**:
- `belongsTo(User::class)` - Dataset owner
- `hasMany(Document::class)` - Documents in this dataset

**Key Features**:
- Automatic slug generation from name
- JSON casting for schema and metadata
- Status scoping for active datasets
- Full-text search capabilities

### 2. Document Model

**Location**: `app/Models/Document.php`

**Purpose**: Individual content items within datasets

**Fields**:
```php
- id: Primary key
- title: Document title
- slug: URL-friendly identifier (auto-generated)
- content: Document content (full-text searchable)
- type: Content type (text, json, markdown, html)
- dataset_id: Parent dataset (optional)
- metadata: Additional document data
- user_id: Author reference
- created_at, updated_at: Timestamps
```

**Relationships**:
- `belongsTo(User::class)` - Document author
- `belongsTo(Dataset::class)` - Parent dataset (optional)

**Key Features**:
- Flexible content storage
- Optional dataset association
- Full-text search on title and content
- Metadata support for additional attributes

### 3. ApiKey Model

**Location**: `app/Models/ApiKey.php`

**Purpose**: Authentication and authorization for MCP server access

**Fields**:
```php
- id: Primary key
- name: Human-readable key name
- key: Unique API key (auto-generated with 'mcp_' prefix)
- permissions: JSON object defining allowed operations
- rate_limits: JSON object defining usage limits
- is_active: Boolean status flag
- last_used_at: Last usage timestamp
- expires_at: Optional expiration date
- user_id: Owner reference
- created_at, updated_at: Timestamps
```

**Relationships**:
- `belongsTo(User::class)` - API key owner

**Key Features**:
- Automatic key generation with prefix
- Granular permission system
- Rate limiting configuration
- Usage tracking
- Expiration support

### 4. McpConnection Model

**Location**: `app/Models/McpConnection.php`

**Purpose**: Manages outgoing connections to Claude Code instances

**Fields**:
```php
- id: Primary key
- name: Connection name
- endpoint_url: Target Claude Code endpoint
- transport_type: Communication method (stdio, http, websocket)
- auth_config: JSON authentication configuration
- capabilities: JSON object of supported features
- status: Connection status (active, inactive, error)
- last_connected_at: Last successful connection
- last_error: Most recent error message
- metadata: Additional connection data
- user_id: Owner reference
- created_at, updated_at: Timestamps
```

**Relationships**:
- `belongsTo(User::class)` - Connection owner

**Key Features**:
- Multiple transport type support
- Automatic status tracking
- Error logging and recovery
- Capability negotiation
- Connection health monitoring

## Database Schema

### Relationships Overview
```
User (1) -----> (*) Dataset -----> (*) Document
User (1) -----> (*) ApiKey
User (1) -----> (*) McpConnection
```

### Indexing Strategy
- **Datasets**: Indexed on `status` and `user_id` for filtering
- **Documents**: Indexed on `dataset_id` and full-text search on content
- **ApiKeys**: Indexed on `key` for authentication lookups
- **McpConnections**: Indexed on `status` and `user_id` for monitoring

## MCP Protocol Implementation

### JSON-RPC 2.0 Structure
```json
{
  "jsonrpc": "2.0",
  "id": "unique-request-id",
  "method": "method_name",
  "params": {
    "parameter": "value"
  }
}
```

### Server Capabilities
```json
{
  "tools": {
    "listChanged": false
  },
  "resources": {
    "subscribe": false,
    "listChanged": false
  },
  "prompts": {
    "listChanged": false
  }
}
```

### Client Capabilities
```json
{
  "roots": {
    "listChanged": false
  }
}
```

## Security Considerations

### Authentication Flow
1. Client sends API key in `Authorization: Bearer <key>` header
2. Server validates key exists and is active
3. Server checks key hasn't expired
4. Server verifies permissions for requested operation
5. Server enforces rate limits
6. Request is processed and response returned

### Permission System
```json
{
  "datasets": "read,write,delete",
  "documents": "read,write",
  "connections": "read"
}
```

### Rate Limiting
```json
{
  "requests_per_minute": "60",
  "requests_per_hour": "1000",
  "requests_per_day": "10000"
}
```

## Error Handling

### Server Error Codes
- `-32700`: Parse error
- `-32600`: Invalid request
- `-32601`: Method not found
- `-32602`: Invalid params
- `-32603`: Internal error

### Connection Error Handling
- Automatic retry with exponential backoff
- Connection health monitoring
- Graceful degradation when connections fail
- Error logging with context

## Performance Considerations

### Database Optimization
- Proper indexing on frequently queried fields
- JSON field optimization for metadata storage
- Connection pooling for high-throughput scenarios
- Query optimization for large datasets

### Memory Management
- Streaming for large document content
- Pagination for list operations
- Connection reuse and pooling
- Garbage collection for expired sessions

### Caching Strategy
- Redis for session and connection state
- Application-level caching for frequent queries
- HTTP caching headers for static resources
- Query result caching with invalidation

For implementation details, see the [MCP Server API Documentation](04_mcp_server_api.md) and [MCP Client Usage Guide](05_mcp_client_usage.md).