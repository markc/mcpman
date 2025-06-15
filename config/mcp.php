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
        'auth_timeout' => env('CLAUDE_AUTH_TIMEOUT', 10), // seconds
        'retry_delay' => env('CLAUDE_RETRY_DELAY', 1000), // milliseconds
        'exponential_backoff' => env('CLAUDE_EXPONENTIAL_BACKOFF', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Health Check Configuration
    |--------------------------------------------------------------------------
    |
    | System health monitoring and diagnostics configuration.
    |
    */
    'health_check' => [
        'enabled' => env('MCP_HEALTH_CHECK_ENABLED', true),
        'interval' => env('MCP_HEALTH_CHECK_INTERVAL', 60), // seconds
        'timeout' => env('MCP_HEALTH_CHECK_TIMEOUT', 15), // seconds
        'cache_key' => 'mcp_health_status',
        'cache_ttl' => env('CLAUDE_CACHE_TTL', 300), // 5 minutes
    ],

    /*
    |--------------------------------------------------------------------------
    | Performance Monitoring
    |--------------------------------------------------------------------------
    |
    | Performance metrics and monitoring thresholds.
    |
    */
    'monitoring' => [
        'enabled' => env('MCP_MONITORING_ENABLED', true),
        'slow_query_threshold' => env('MCP_SLOW_QUERY_THRESHOLD', 10000), // milliseconds
        'error_rate_threshold' => env('MCP_ERROR_RATE_THRESHOLD', 0.1), // 10%
        'metrics_retention_days' => env('MCP_METRICS_RETENTION_DAYS', 30),
        'log_requests' => env('MCP_LOG_REQUESTS', true),
        'log_responses' => env('MCP_LOG_RESPONSES', false), // May contain sensitive data
        'log_performance' => env('MCP_LOG_PERFORMANCE', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Security Configuration
    |--------------------------------------------------------------------------
    |
    | Security settings for MCP connections and data handling.
    |
    */
    'security' => [
        'sanitize_inputs' => env('MCP_SANITIZE_INPUTS', true),
        'max_request_size' => env('MCP_MAX_REQUEST_SIZE', 1024 * 1024), // 1MB
        'allowed_tools' => env('MCP_ALLOWED_TOOLS', null), // Comma-separated list or null for all
        'rate_limit_enabled' => env('MCP_RATE_LIMIT_ENABLED', true),
        'rate_limit_per_minute' => env('MCP_RATE_LIMIT_PER_MINUTE', 60),
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
