# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

⚠️ **IMPORTANT: This project uses Filament v4.0 - DO NOT downgrade to v3.x under any circumstances!**

This is a Laravel 12 application that implements a **bidirectional Model Context Protocol (MCP) integration** with Filament v4.0. The project provides both an MCP server for Claude Code → Filament communication AND an MCP client for Filament → Claude Code communication, enabling full bidirectional control. The project uses:
- **Backend**: Laravel 12 with PHP 8.2+
- **Admin Panel**: Filament v4.0 (beta) with dark mode enabled and amber primary color
- **MCP Integration**: Bidirectional MCP server/client providing full communication between Claude Code and Filament
- **Frontend**: Vite with TailwindCSS v4.0
- **Testing**: Pest PHP testing framework
- **Database**: SQLite (configured in database/database.sqlite)

## Essential Commands

### Development
```bash
# Start development server with all services (server, queue, logs, vite)
composer run dev

# Individual services
php artisan serve                    # Development server
php artisan queue:listen --tries=1  # Queue worker
php artisan pail --timeout=0       # Real-time logs
npm run dev                         # Vite development server
```

### Testing
```bash
# Run all tests
composer run test

# Run tests with Pest directly
php artisan test

# Run specific test files
php artisan test tests/Feature/ExampleTest.php
php artisan test tests/Unit/ExampleTest.php
```

### Asset Management
```bash
npm run build    # Build assets for production
npm run dev      # Start Vite development server
```

### Code Quality
```bash
# Laravel Pint (code formatting)
./vendor/bin/pint

# Clear configuration cache (useful before testing)
php artisan config:clear
```

### Git Workflow
**⚠️ CRITICAL: ALL merges to remote main branch MUST use the git workflow scripts:**

```bash
# Required workflow for ALL code changes:
git start [feature-name]         # Start new feature branch
# ... make your changes ...
git finish ["commit message"]    # Auto-format with Pint, commit, PR, merge, cleanup
```

**NEVER use:**
- `git push origin main` (direct pushes blocked by pre-push hook)
- `git merge` directly on main branch
- Manual PR creation/merging

**The `git finish` script automatically:**
- Runs Laravel Pint code formatting
- Creates proper commit messages
- Creates and merges pull requests
- Cleans up feature branches
- Ensures code quality standards

**Setup git workflow (run once):**
```bash
./scripts/setup-git-aliases.sh     # Enable `git start` and `git finish` aliases
./scripts/install-git-hooks.sh     # Install pre-push protection hooks
```

## Architecture

### Filament Integration
- Admin panel accessible at `/admin` route
- Filament provider: `app/Providers/Filament/AdminPanelProvider.php`
- Auto-discovers resources in `app/Filament/Resources/`
- Auto-discovers pages in `app/Filament/Pages/`
- Auto-discovers widgets in `app/Filament/Widgets/`

### Testing Setup
- Uses Pest PHP testing framework
- Feature tests extend `Tests\TestCase` with Laravel testing capabilities
- Test configuration includes in-memory SQLite database
- Tests located in `tests/Feature/` and `tests/Unit/`

### Frontend Assets
- Vite configuration in `vite.config.js`
- Entry points: `resources/css/app.css` and `resources/js/app.js`
- TailwindCSS v4.0 integrated via Vite plugin
- Filament assets auto-compiled to `public/css/filament/` and `public/js/filament/`

## Data Models & Relationships

### Core MCP Entities
This application provides a Model Context Protocol (MCP) server for data management with the following structure:

#### Dataset Model (`app/Models/Dataset.php`)
- Main entity representing data collections
- Fields: name, slug, description, type, status, schema, metadata
- Supports JSON, CSV, XML, YAML, and text formats
- Relationships: user (owner), documents

#### Document Model (`app/Models/Document.php`)
- Individual documents within datasets
- Fields: title, slug, content, type, status, tags, metadata
- Supports markdown, HTML, text, and JSON formats
- Relationships: user (author), dataset (optional parent)

#### ApiKey Model (`app/Models/ApiKey.php`)
- API keys for MCP authentication and authorization
- Fields: name, key, permissions, rate_limits, is_active, expires_at
- Tracks usage and provides fine-grained access control
- Relationships: user (owner)

#### McpConnection Model (`app/Models/McpConnection.php`)
- Manages outgoing MCP connections from Filament to Claude Code
- Fields: name, endpoint_url, transport_type, auth_config, capabilities, status
- Supports HTTP, WebSocket, and stdio transport types
- Connection status tracking and error logging
- Relationships: user (owner)

## Documentation

For detailed information about the MCP integration and usage, see the comprehensive documentation in the `docs/` folder:

- **[Installation & Setup](docs/01_installation_setup.md)** - Development environment setup
- **[MCP Architecture](docs/02_mcp_architecture.md)** - Technical architecture and data models  
- **[Filament Interface](docs/03_filament_interface.md)** - Admin panel usage guide
- **[MCP Server API](docs/04_mcp_server_api.md)** - Complete API reference
- **[MCP Client Usage](docs/05_mcp_client_usage.md)** - Outgoing connection management
- **[Deployment Guide](docs/06_deployment_guide.md)** - Production deployment
- **[Troubleshooting](docs/07_troubleshooting.md)** - Common issues and solutions
- **[Testing Guide](docs/08_testing_guide.md)** - Testing strategies and examples

## Quick Reference

### Essential Commands
```bash
# Start development server with all services
composer run dev

# Run tests
composer run test

# Access admin panel
http://localhost:8000/admin
```

### Core Locations
- **MCP Server**: `app/Services/McpServer.php`
- **MCP Client**: `app/Services/McpClient.php`
- **API Endpoint**: `POST /api/mcp`
- **Admin Dashboard**: `/admin` → MCP Status
- **Chat Interface**: `/admin` → Chat with Claude