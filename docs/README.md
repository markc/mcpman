---
title: Laravel Loop Filament - MCP Integration Documentation
description: Bidirectional Model Context Protocol integration with Laravel 12 and Filament v4.0
date: 2025-06-13
---

# Laravel Loop Filament - MCP Integration Documentation

## Project Overview

Laravel Loop Filament is a Laravel 12 application that implements a **bidirectional Model Context Protocol (MCP) integration** with Filament v4.0. The project provides both an MCP server for Claude Code → Filament communication AND an MCP client for Filament → Claude Code communication, enabling full bidirectional control.

## Filament v4.x Compliance

⚠️ **CRITICAL**: This project uses Filament v4.0 - DO NOT downgrade to v3.x under any circumstances!

## Documentation Structure

- [01_installation_setup.md](01_installation_setup.md) - Installation and development setup
- [02_mcp_architecture.md](02_mcp_architecture.md) - MCP integration architecture and data models
- [03_filament_interface.md](03_filament_interface.md) - Admin interface usage and features
- [04_mcp_server_api.md](04_mcp_server_api.md) - MCP server endpoints and tools
- [05_mcp_client_usage.md](05_mcp_client_usage.md) - Outgoing MCP client configuration
- [06_deployment_guide.md](06_deployment_guide.md) - Production deployment instructions
- [07_troubleshooting.md](07_troubleshooting.md) - Common issues and solutions
- [08_testing_guide.md](08_testing_guide.md) - Testing MCP integration and endpoints

## Current Progress

### ✅ Completed Features

1. **Bidirectional MCP Integration**
   - Incoming MCP Server for Claude Code → Filament communication
   - Outgoing MCP Client for Filament → Claude Code communication
   - Full tool chaining and bidirectional communication support

2. **Filament Admin Interface**
   - Enhanced Resource management (Datasets, Documents, API Keys, MCP Connections)
   - Real-time MCP Dashboard with connection monitoring
   - Interactive Conversation Interface for Claude Code communication
   - Connection testing and health checks

3. **MCP Server Capabilities**
   - Tools: create_dataset, list_datasets, create_document, search_documents
   - Resources: filament://datasets, filament://documents
   - Prompts: analyze_dataset, summarize_documents
   - Authentication via API keys with fine-grained permissions

4. **Core Data Models**
   - Dataset: Data collections with schema support
   - Document: Content management with full-text search
   - ApiKey: Authentication and authorization management
   - McpConnection: Outgoing connection management

### 🔄 Bidirectional Flow

**Claude Code → Filament**: Claude Code can create datasets, documents, search content, and access all Filament resources through MCP server endpoints.

**Filament → Claude Code**: Filament admin interface can connect to Claude Code instances, execute tools, send messages, and receive responses through the MCP client.

## Key Technologies

- **Laravel 12** - PHP framework (requires PHP 8.2+)
- **Filament v4.0 Beta** - Admin panel framework
- **Model Context Protocol** - Bidirectional communication protocol
- **SQLite** - Default database with full-text search
- **Laravel Reverb** - Real-time communication
- **Prism PHP** - Data processing
- **Vite** - Frontend build tool with TailwindCSS v4.0
- **Pest PHP** - Testing framework

## Quick Start

1. **Development Server**
   ```bash
   composer run dev  # Starts all services
   ```

2. **Access Admin Panel**
   - Navigate to `http://localhost:8000/admin`
   - Use MCP Dashboard for connection monitoring
   - Use Chat with Claude for real-time interaction

3. **Test MCP Server**
   ```bash
   curl -X POST http://localhost:8000/api/mcp \
     -H "Content-Type: application/json" \
     -H "Authorization: Bearer your-api-key" \
     -d '{"jsonrpc":"2.0","id":1,"method":"tools/list"}'
   ```

## System Requirements

### Minimum Requirements
- PHP 8.2 or higher
- Composer 2.0+
- Node.js 18+ with npm
- 512MB RAM
- 1GB disk space

### Recommended Requirements
- PHP 8.3 with OPcache
- MySQL 8.0+ or PostgreSQL 13+
- Redis for caching and sessions
- 2GB RAM
- 10GB disk space
- SSL certificate for production

## Next Steps

1. **Configure MCP Connections**: Set up outgoing connections to Claude Code instances
2. **Create API Keys**: Generate authentication keys for MCP server access
3. **Import Data**: Create datasets and documents for Claude Code interaction
4. **Test Integration**: Use the conversation interface for bidirectional communication

## Version Information

- **Current Version**: 1.0.0
- **Laravel Version**: 12.x
- **Filament Version**: 4.0 (beta)
- **PHP Requirement**: 8.2+
- **Last Updated**: June 2025

⚠️ **Important**: This system uses Filament v4.0 (beta). Do not downgrade to Filament v3.x as it will cause compatibility issues.