<?php

return [
    /*
    |--------------------------------------------------------------------------
    | MCP Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for Model Context Protocol client and server functionality
    |
    */

    'timeout' => env('MCP_TIMEOUT', 60000),
    'direct_timeout' => env('CLAUDE_DIRECT_TIMEOUT', 45),
    'max_retries' => env('CLAUDE_MAX_RETRIES', 3),
    'fallback_enabled' => env('CLAUDE_FALLBACK_ENABLED', true),
    'cache_responses' => env('CLAUDE_CACHE_RESPONSES', true),
    'debug_logging' => env('CLAUDE_DEBUG_LOGGING', false),

    'claude' => [
        'api_key' => env('CLAUDE_API_KEY'),
        'command' => env('CLAUDE_COMMAND', 'claude'),
        'config_check' => env('CLAUDE_CONFIG_CHECK', true),
    ],

    'server' => [
        'enabled' => env('MCP_SERVER_ENABLED', true),
        'host' => env('MCP_SERVER_HOST', 'localhost'),
        'port' => env('MCP_SERVER_PORT', 8081),
        'protocol_version' => '2024-11-05',
        'capabilities' => [
            'tools' => true,
            'resources' => true,
            'prompts' => true,
        ],
        'server_info' => [
            'name' => 'MCPman-Laravel-Server',
            'version' => '1.0.0',
        ],
    ],

    'client' => [
        'use_persistent' => env('MCP_USE_PERSISTENT_CONNECTIONS', true),
        'default_transport' => 'stdio',
        'transports' => [
            'stdio' => [
                'timeout' => 30,
                'retry_attempts' => 3,
            ],
            'http' => [
                'timeout' => 30,
                'verify_ssl' => true,
                'retry_attempts' => 3,
            ],
            'websocket' => [
                'timeout' => 30,
                'retry_attempts' => 3,
            ],
        ],
    ],

    'broadcasting' => [
        'channels' => [
            'mcp.connections' => 'mcp-connections',
            'mcp.conversations' => 'mcp-conversations',
            'mcp.server.status' => 'mcp-server-status',
            'mcp.diagnostics' => 'mcp-diagnostics',
        ],
    ],
];
