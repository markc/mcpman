<?php

return [
    /*
    |--------------------------------------------------------------------------
    | MCP Client Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for outgoing MCP connections to external Claude processes
    | and other MCP servers for bidirectional communication.
    |
    */

    'connections' => [
        'claude_debugger' => [
            'enabled' => env('MCP_CLAUDE_DEBUGGER_ENABLED', true),
            'type' => 'stdio', // stdio, http, websocket
            'command' => env('MCP_CLAUDE_DEBUGGER_COMMAND', 'claude'),
            'args' => [
                'mcp-server',
                '--name=mcpman-debugger',
                '--auto-connect',
                '--log-level=info',
            ],
            'timeout' => env('MCP_CLAUDE_DEBUGGER_TIMEOUT', 30),
            'retry_attempts' => env('MCP_CLAUDE_DEBUGGER_RETRIES', 3),
            'retry_delay' => env('MCP_CLAUDE_DEBUGGER_RETRY_DELAY', 2), // seconds
        ],

        'claude_assistant' => [
            'enabled' => env('MCP_CLAUDE_ASSISTANT_ENABLED', false),
            'type' => 'http',
            'endpoint' => env('MCP_CLAUDE_ASSISTANT_ENDPOINT', 'http://localhost:8001/mcp'),
            'auth' => [
                'type' => 'bearer',
                'token' => env('MCP_CLAUDE_ASSISTANT_TOKEN'),
            ],
            'timeout' => 60,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Auto-Notification Settings
    |--------------------------------------------------------------------------
    |
    | Configure automatic notification of external Claude processes when
    | errors are detected by the log monitoring system.
    |
    */

    'auto_notifications' => [
        'enabled' => env('MCP_AUTO_NOTIFICATIONS_ENABLED', true),
        'targets' => ['claude_debugger'], // Which connections to notify
        'error_types' => [
            'fatal' => true,
            'exception' => true,
            'syntax_error' => true,
            'class_not_found' => true,
            'method_not_found' => true,
            'duplicate_method' => true,
            'undefined_variable' => false, // Too noisy
        ],
        'debounce_ms' => env('MCP_NOTIFICATION_DEBOUNCE', 1000), // Prevent spam
        'include_context' => true,
        'max_context_lines' => 20,
    ],

    /*
    |--------------------------------------------------------------------------
    | Debugging & Logging
    |--------------------------------------------------------------------------
    */

    'debug' => [
        'log_requests' => env('MCP_CLIENT_LOG_REQUESTS', true),
        'log_responses' => env('MCP_CLIENT_LOG_RESPONSES', true),
        'log_errors' => env('MCP_CLIENT_LOG_ERRORS', true),
        'verbose_output' => env('MCP_CLIENT_VERBOSE', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | Default Capabilities
    |--------------------------------------------------------------------------
    |
    | Default capabilities to announce when connecting to MCP servers
    |
    */

    'capabilities' => [
        'notifications' => true,
        'tools' => true,
        'resources' => true,
        'prompts' => true,
        'error_handling' => true,
        'auto_fix' => true,
    ],
];
