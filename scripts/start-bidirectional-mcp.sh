#!/bin/bash

# MCPman Bidirectional MCP Setup Script
# This script sets up the dual-Claude process architecture

set -e

echo "🚀 Setting up Bidirectional MCP Architecture"
echo "============================================="
echo

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Function to print colored output
print_status() {
    echo -e "${BLUE}[INFO]${NC} $1"
}

print_success() {
    echo -e "${GREEN}[SUCCESS]${NC} $1"
}

print_warning() {
    echo -e "${YELLOW}[WARNING]${NC} $1"
}

print_error() {
    echo -e "${RED}[ERROR]${NC} $1"
}

# Check if Claude CLI is available
print_status "Checking Claude CLI availability..."
if ! command -v claude &> /dev/null; then
    print_error "Claude CLI not found. Please install Claude Code CLI first."
    print_status "Visit: https://claude.ai/code"
    exit 1
fi

# Check Claude authentication
print_status "Checking Claude CLI authentication..."
if ! claude -p "hello" &> /dev/null; then
    print_error "Claude CLI not authenticated. Please run 'claude auth' first."
    exit 1
fi

print_success "Claude CLI is available and authenticated"

# Check if Laravel app is ready
print_status "Checking Laravel application..."
if [ ! -f "composer.json" ] || [ ! -d "app" ]; then
    print_error "This script must be run from the Laravel project root directory"
    exit 1
fi

print_success "Laravel application found"

# Create .env entries if they don't exist
print_status "Configuring environment variables..."

ENV_FILE=".env"
if [ ! -f "$ENV_FILE" ]; then
    print_warning ".env file not found, creating from .env.example"
    cp .env.example .env
fi

# Add MCP configuration to .env if not present
MCP_CONFIG_ADDED=false

if ! grep -q "MCP_CLAUDE_DEBUGGER_ENABLED" "$ENV_FILE"; then
    echo "" >> "$ENV_FILE"
    echo "# MCP Bidirectional Configuration" >> "$ENV_FILE"
    echo "MCP_CLAUDE_DEBUGGER_ENABLED=true" >> "$ENV_FILE"
    echo "MCP_AUTO_NOTIFICATIONS_ENABLED=true" >> "$ENV_FILE"
    echo "MCP_CLIENT_LOG_REQUESTS=true" >> "$ENV_FILE"
    echo "MCP_CLIENT_VERBOSE=false" >> "$ENV_FILE"
    MCP_CONFIG_ADDED=true
fi

if [ "$MCP_CONFIG_ADDED" = true ]; then
    print_success "MCP configuration added to .env"
else
    print_status "MCP configuration already present in .env"
fi

# Clear Laravel caches
print_status "Clearing Laravel caches..."
php artisan optimize:clear > /dev/null 2>&1

# Test MCP connections
print_status "Testing MCP connection setup..."
php artisan mcp:connections status

echo
print_success "Bidirectional MCP setup complete!"
echo
echo "Next steps:"
echo "1. Terminal 1 (Laravel + Monitor): composer run dev"
echo "2. Terminal 2 (Claude Debugger): claude mcp-server --name=mcpman-debugger --auto-connect"
echo "3. Errors will now be automatically sent to the Claude debugger process!"
echo
echo "Commands:"
echo "  php artisan mcp:connections start   # Start external connections"
echo "  php artisan mcp:connections status  # Check connection status"
echo "  php artisan mcp:connections test    # Test error notifications"
echo

exit 0