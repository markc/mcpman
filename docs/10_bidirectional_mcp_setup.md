# Bidirectional MCP Setup Guide

This guide explains how to set up the complete bidirectional MCP architecture with two Claude processes for automated debugging.

## 🏗️ Architecture Overview

```
┌─────────────────────┐    ┌─────────────────────┐    ┌─────────────────────┐
│   Claude Process 1  │    │   Laravel MCPman   │    │   Claude Process 2  │
│   (Your Session)    │    │                     │    │   (Auto-Debugger)  │
│                     │    │  ┌───────────────┐  │    │                     │
│  - Interactive      │◄──►│  │  MCP Server   │  │◄──►│  - Receives errors  │
│  - Manual commands  │    │  │  (Port 8000)  │  │    │    automatically    │
│  - Code changes     │    │  └───────────────┘  │    │  - Analyzes context │
│                     │    │                     │    │  - Suggests fixes   │
│                     │    │  ┌───────────────┐  │    │  - Sends solutions  │
│                     │    │  │ Log Monitor   │  │    │    back via MCP     │
│                     │    │  │ Service       │──┼────┤                     │
│                     │    │  └───────────────┘  │    │                     │
└─────────────────────┘    └─────────────────────┘    └─────────────────────┘
```

## 🚀 Quick Setup

### Step 1: Run the Setup Script
```bash
./scripts/start-bidirectional-mcp.sh
```

### Step 2: Start Laravel with MCP Integration
```bash
composer run dev-bidirectional
```

### Step 3: Start External Claude Debugger (New Terminal)
```bash
claude mcp-server --name=mcpman-debugger --auto-connect
```

## 📋 Manual Setup Steps

### 1. Environment Configuration

Add to your `.env` file:
```env
# MCP Bidirectional Configuration
MCP_CLAUDE_DEBUGGER_ENABLED=true
MCP_AUTO_NOTIFICATIONS_ENABLED=true
MCP_CLIENT_LOG_REQUESTS=true
MCP_CLIENT_VERBOSE=false
```

### 2. Verify Claude CLI Setup
```bash
# Check Claude is installed
claude --version

# Check authentication
claude -p "hello"
```

### 3. Test MCP Connections
```bash
# Check connection status
php artisan mcp:connections status

# Start connections
php artisan mcp:connections start

# Test error notifications
php artisan mcp:connections test
```

## 🎛️ Available Commands

### MCP Connection Management
```bash
# Start all configured MCP connections
php artisan mcp:connections start

# Check status of all connections
php artisan mcp:connections status

# Test connections with sample error
php artisan mcp:connections test

# Stop all connections
php artisan mcp:connections stop
```

### Log Monitoring
```bash
# Start log monitoring with auto-fix
php artisan mcp:watch-logs --auto-fix

# Monitor specific log file
php artisan mcp:watch-logs --file=custom.log
```

### Development Server Options
```bash
# Standard development (6 services)
composer run dev

# Bidirectional MCP development (includes external connections)
composer run dev-bidirectional
```

## 🔧 Configuration Details

### MCP Client Configuration (`config/mcp-client.php`)

```php
'connections' => [
    'claude_debugger' => [
        'enabled' => true,
        'type' => 'stdio',
        'command' => 'claude',
        'args' => [
            'mcp-server',
            '--name=mcpman-debugger',
            '--auto-connect',
        ],
    ],
],

'auto_notifications' => [
    'enabled' => true,
    'targets' => ['claude_debugger'],
    'error_types' => [
        'fatal' => true,
        'exception' => true,
        'syntax_error' => true,
        'class_not_found' => true,
        'method_not_found' => true,
        'duplicate_method' => true,
    ],
],
```

## 🔍 How It Works

### 1. Error Detection
- Log monitor watches `storage/logs/laravel.log` in real-time
- Detects 7 types of errors using regex patterns
- Extracts context: file paths, line numbers, stack traces

### 2. Bidirectional Notification
- Sends error to **local MCP server** (your current Claude session)
- Sends error to **external Claude debugger** process via stdio
- Both processes receive complete context simultaneously

### 3. Automated Response
- External Claude debugger analyzes errors automatically
- Provides fix suggestions back through MCP
- Your session receives both the error and the suggested fix

## 🎯 Benefits

### ⚡ **Instant Detection**
- No manual error discovery
- Real-time monitoring of all Laravel logs

### 🤖 **Dual Analysis**
- Your interactive Claude session (manual)
- Dedicated Claude debugger (automatic)

### 📋 **Complete Context**
- Full file content around error lines
- Stack traces and error metadata
- Application environment information

### 🔧 **Automated Workflow**
1. Error occurs in Laravel
2. Log monitor detects it instantly
3. Both Claude processes notified
4. External Claude provides analysis
5. Fixes sent back to Laravel
6. You get both error and solution immediately

## 🛠️ Troubleshooting

### Connection Issues
```bash
# Check if Claude CLI is working
claude -p "test connection"

# Verify MCP server capabilities
php artisan mcp:connections status

# View detailed logs
tail -f storage/logs/laravel.log | grep MCP
```

### Process Management
```bash
# Find Claude processes
ps aux | grep claude

# Check if external debugger is running
ps aux | grep "mcp-server"

# Kill stuck processes
pkill -f "claude mcp-server"
```

### Debug Mode
Set in `.env`:
```env
MCP_CLIENT_VERBOSE=true
MCP_CLIENT_LOG_REQUESTS=true
MCP_CLIENT_LOG_RESPONSES=true
```

## 📊 Monitoring

### Real-time Status
Visit `/admin/log-monitoring` in your Filament panel to see:
- Connection status
- Error pattern statistics
- Recent notifications
- System health

### Console Output
When running `composer run dev-bidirectional`, you'll see 6 services:
- **server**: Laravel HTTP server
- **queue**: Background job processing
- **logs**: Real-time log tailing
- **vite**: Frontend asset compilation
- **monitor**: Log error monitoring
- **mcp**: Bidirectional MCP connections

## 🎉 Success Indicators

You know the system is working when:

1. **✅ Both Claude processes running**: `ps aux | grep claude` shows 2 processes
2. **✅ MCP connections active**: `php artisan mcp:connections status` shows connected
3. **✅ Auto-notifications working**: Errors automatically appear in both sessions
4. **✅ No manual copying**: Error context sent automatically with file paths and line numbers

## 🚀 Next Steps

Once setup is complete:
1. Trigger an error in your Laravel app
2. Watch both Claude sessions receive the notification
3. See the external debugger analyze and suggest fixes
4. Enjoy automated debugging without manual copy/paste!

The bidirectional MCP architecture eliminates the need for manual error reporting and creates a true automated debugging pipeline.