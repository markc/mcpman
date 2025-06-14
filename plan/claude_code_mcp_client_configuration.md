# Claude Code MCP Client Configuration Guide

## Overview

Claude Code functions as a powerful MCP (Model Context Protocol) client, capable of connecting to multiple MCP servers to extend its capabilities. This guide covers configuration, management, and best practices for using Claude Code as an MCP client.

## Core MCP Commands

### Adding MCP Servers

#### Stdio Transport (Local Servers)
```bash
# Basic syntax
claude mcp add <name> <command> [args...]

# Example with environment variables
claude mcp add my-server -e API_KEY=123 -- /path/to/server arg1 arg2

# Example: Postgres database server
claude mcp add postgres-server /path/to/postgres-mcp-server --connection-string "postgresql://user:pass@localhost:5432/mydb"
```

#### SSE Transport (Remote Servers)
```bash
# Basic syntax
claude mcp add --transport sse <name> <url>

# Example: Remote SSE server
claude mcp add --transport sse sse-server https://example.com/sse-endpoint
```

### Server Management Commands
```bash
# List all configured servers
claude mcp list

# Get details for a specific server
claude mcp get my-server

# Remove a server
claude mcp remove my-server

# Import servers from Claude Desktop
claude mcp add-from-claude-desktop

# Reset project-scoped server choices
claude mcp reset-project-choices
```

### Advanced Configuration
```bash
# Add with JSON configuration
claude mcp add-json my-server '{"command": "node", "args": ["server.js"], "env": {"API_KEY": "value"}}'

# Add with specific scope
claude mcp add my-server -s local /path/to/server    # Local scope (default)
claude mcp add my-server -s project /path/to/server  # Project scope
claude mcp add my-server -s user /path/to/server     # User scope
```

## MCP Server Scopes

### Local Scope (Default)
- **Storage**: Project-specific user settings
- **Availability**: Only available to you in current project
- **Use Case**: Personal development tools

### Project Scope
- **Storage**: `.mcp.json` file at project root
- **Availability**: Shared with team via version control
- **Use Case**: Team collaboration tools

### User Scope
- **Storage**: User-wide configuration
- **Availability**: Available across all projects
- **Use Case**: Global development tools

## Configuration Files

### Project-Scoped Configuration (.mcp.json)
```json
{
  "mcpServers": {
    "postgres-server": {
      "command": "/usr/local/bin/postgres-mcp-server",
      "args": ["--connection-string", "postgresql://localhost:5432/mydb"],
      "env": {
        "API_KEY": "your-api-key"
      }
    },
    "github-server": {
      "command": "npx",
      "args": ["@modelcontextprotocol/server-github"],
      "env": {
        "GITHUB_PERSONAL_ACCESS_TOKEN": "ghp_your_token"
      }
    }
  }
}
```

### Local Configuration (.claude.json)
```json
{
  "mcpServers": {
    "my-custom-tool": {
      "type": "stdio",
      "command": "node",
      "args": ["/path/to/build/index.js"]
    }
  }
}
```

## Environment Variables and Timeout Configuration

### MCP Timeout Configuration
```bash
# Set custom timeout (in milliseconds)
export MCP_TIMEOUT=30000  # 30 seconds
claude

# Or inline
MCP_TIMEOUT=30000 claude
```

### Environment Variable Passing
```bash
# Pass environment variables to MCP server
claude mcp add server-name -e API_KEY=value -e DEBUG=true -- /path/to/server

# Multiple environment variables
claude mcp add weather-server \
  -e WEATHER_API_KEY=your_key \
  -e LOCATION=default \
  -- /usr/local/bin/weather-server
```

## Transport Types

### Stdio Transport
- **Best for**: Local servers, subprocess communication
- **Protocol**: JSON-RPC 2.0 over stdin/stdout
- **Advantages**: Simple, direct communication
- **Limitations**: Local only, subprocess management

```bash
claude mcp add local-server /path/to/server
```

### SSE Transport (Server-Sent Events)
- **Best for**: Remote servers, web-based services
- **Protocol**: HTTP with Server-Sent Events
- **Advantages**: Network-based, persistent connections
- **Limitations**: Requires network connectivity

```bash
claude mcp add --transport sse remote-server https://api.example.com/mcp
```

## Practical Examples

### Database Integration
```bash
# PostgreSQL server
claude mcp add postgres /usr/local/bin/postgres-mcp-server \
  --connection-string "postgresql://user:pass@localhost:5432/db"

# MongoDB server
claude mcp add mongodb -e MONGO_URI=mongodb://localhost:27017/mydb \
  -- /usr/local/bin/mongodb-mcp-server
```

### Development Tools
```bash
# Git integration
claude mcp add git-server /usr/local/bin/git-mcp-server

# Docker management
claude mcp add docker -e DOCKER_HOST=unix:///var/run/docker.sock \
  -- /usr/local/bin/docker-mcp-server

# File system tools
claude mcp add filesystem /usr/local/bin/fs-mcp-server \
  --root-directory /project/workspace
```

### API Integrations
```bash
# GitHub integration
claude mcp add github -e GITHUB_TOKEN=ghp_your_token \
  -- npx @modelcontextprotocol/server-github

# Slack integration
claude mcp add slack -e SLACK_BOT_TOKEN=xoxb-your-token \
  -- /usr/local/bin/slack-mcp-server
```

## Connection Status and Debugging

### Checking Server Status
```bash
# Within Claude Code session
/mcp

# Get detailed server information
claude mcp get server-name
```

### Debug Mode
```bash
# Enable debug logging
claude --debug

# Or with MCP-specific debug
claude --mcp-debug  # (Deprecated, use --debug)
```

### Common Status Messages
- **Connected**: Server is active and responding
- **Connecting**: Initial connection in progress
- **Error**: Connection failed or server unavailable
- **Timeout**: Server took too long to respond

## Known Issues and Solutions

### Timeout Problems
**Issue**: MCP servers timeout during startup or tool calls
**Solution**: Configure longer timeout
```bash
MCP_TIMEOUT=30000 claude
```

### Server Startup Delays
**Issue**: Claude doesn't wait for slow MCP servers to connect
**Workaround**: Use servers that start quickly, or implement retry logic

### Protocol Version Mismatch
**Issue**: Server and client use different MCP protocol versions
**Solution**: Ensure both use compatible versions (2024-11-05 is current)

### Missing Initialized Notification
**Issue**: Claude Code doesn't send `notifications/initialized`
**Impact**: Some properly-implemented MCP servers may reject requests
**Workaround**: Implement servers that don't strictly require this notification

## Security Best Practices

### Server Trust
- Only add MCP servers from trusted sources
- Review server code before adding to project scope
- Be cautious with servers that access internet resources

### Permission Management
- Use minimal required permissions for MCP servers
- Regularly audit configured servers
- Remove unused servers promptly

### Environment Variables
- Store sensitive tokens in environment variables, not command line args
- Use project-scoped configuration for team secrets
- Rotate API keys and tokens regularly

### Network Security
- Use HTTPS for remote MCP servers
- Verify SSL certificates
- Consider VPN or private networks for sensitive integrations

## Troubleshooting Common Issues

### Server Not Loading
1. Check server command path and arguments
2. Verify environment variables are set correctly
3. Test server independently outside Claude Code
4. Check logs with `--debug` flag

### Permission Errors
1. Verify file permissions on server executable
2. Check environment variable access
3. Ensure Claude Code has necessary system permissions

### Network Issues (SSE Transport)
1. Verify URL accessibility
2. Check firewall settings
3. Test with curl or similar tools
4. Verify SSL/TLS configuration

### Performance Issues
1. Monitor server response times
2. Adjust MCP_TIMEOUT if needed
3. Consider local vs remote server placement
4. Profile server performance independently

This configuration guide provides comprehensive coverage of Claude Code's MCP client capabilities, enabling effective integration with various external tools and services.