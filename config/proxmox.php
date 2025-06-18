<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Proxmox Platform Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration settings for the Proxmox platform integration including
    | default connection settings, resource limits, and monitoring options.
    |
    */

    'defaults' => [
        /*
        |--------------------------------------------------------------------------
        | Default Connection Settings
        |--------------------------------------------------------------------------
        |
        | These values will be used as defaults when creating new Proxmox clusters
        | if not explicitly specified.
        |
        */
        'api_port' => env('PROXMOX_DEFAULT_API_PORT', 8006),
        'verify_tls' => env('PROXMOX_DEFAULT_VERIFY_TLS', true),
        'timeout' => env('PROXMOX_DEFAULT_TIMEOUT', 30),
        'realm' => env('PROXMOX_DEFAULT_REALM', 'pam'),
    ],

    'monitoring' => [
        /*
        |--------------------------------------------------------------------------
        | Health Check Settings
        |--------------------------------------------------------------------------
        |
        | Configuration for automated health checks and monitoring.
        |
        */
        'enabled' => env('PROXMOX_MONITORING_ENABLED', true),
        'health_check_interval' => env('PROXMOX_HEALTH_CHECK_INTERVAL', 300), // seconds
        'node_offline_threshold' => env('PROXMOX_NODE_OFFLINE_THRESHOLD', 15), // minutes
        'cluster_offline_threshold' => env('PROXMOX_CLUSTER_OFFLINE_THRESHOLD', 10), // minutes

        /*
        |--------------------------------------------------------------------------
        | Resource Utilization Thresholds
        |--------------------------------------------------------------------------
        |
        | Warning and critical thresholds for resource utilization monitoring.
        |
        */
        'thresholds' => [
            'cpu' => [
                'warning' => env('PROXMOX_CPU_WARNING_THRESHOLD', 80),
                'critical' => env('PROXMOX_CPU_CRITICAL_THRESHOLD', 95),
            ],
            'memory' => [
                'warning' => env('PROXMOX_MEMORY_WARNING_THRESHOLD', 85),
                'critical' => env('PROXMOX_MEMORY_CRITICAL_THRESHOLD', 95),
            ],
            'storage' => [
                'warning' => env('PROXMOX_STORAGE_WARNING_THRESHOLD', 85),
                'critical' => env('PROXMOX_STORAGE_CRITICAL_THRESHOLD', 95),
            ],
        ],
    ],

    'provisioning' => [
        /*
        |--------------------------------------------------------------------------
        | Environment Provisioning Settings
        |--------------------------------------------------------------------------
        |
        | Configuration for automated environment provisioning and management.
        |
        */
        'max_concurrent_provisions' => env('PROXMOX_MAX_CONCURRENT_PROVISIONS', 3),
        'provision_timeout' => env('PROXMOX_PROVISION_TIMEOUT', 1800), // seconds (30 minutes)
        'cleanup_failed_provisions' => env('PROXMOX_CLEANUP_FAILED_PROVISIONS', true),
        'auto_start_on_provision' => env('PROXMOX_AUTO_START_ON_PROVISION', true),

        /*
        |--------------------------------------------------------------------------
        | Resource Limits
        |--------------------------------------------------------------------------
        |
        | Maximum resource limits for development environments.
        |
        */
        'limits' => [
            'max_cpu_cores_per_environment' => env('PROXMOX_MAX_CPU_CORES_PER_ENV', 16),
            'max_memory_gb_per_environment' => env('PROXMOX_MAX_MEMORY_GB_PER_ENV', 64),
            'max_storage_gb_per_environment' => env('PROXMOX_MAX_STORAGE_GB_PER_ENV', 500),
            'max_environments_per_user' => env('PROXMOX_MAX_ENVIRONMENTS_PER_USER', 10),
        ],
    ],

    'templates' => [
        /*
        |--------------------------------------------------------------------------
        | Environment Templates
        |--------------------------------------------------------------------------
        |
        | Pre-configured templates for PHP/Laravel development environments.
        | Focus on modern LEMP stack with mail server capabilities.
        |
        */
        'lemp-laravel-stack' => [
            'name' => 'LEMP + Laravel Development Stack',
            'description' => 'Nginx, PHP 8.4-FPM, MariaDB, Laravel 12, Filament v4',
            'default_resources' => [
                'cpu_cores' => 2,
                'memory_gb' => 4,
                'storage_gb' => 40,
            ],
            'estimated_cost_per_hour' => 0.08,
            'services' => ['nginx', 'php8.4-fpm', 'mariadb', 'redis', 'composer'],
        ],
        'full-mail-server' => [
            'name' => 'Complete Mail Server + Web Stack',
            'description' => 'Nginx, PHP 8.4-FPM, MariaDB, Postfix, Dovecot, Sieve, SpamProbe',
            'default_resources' => [
                'cpu_cores' => 3,
                'memory_gb' => 6,
                'storage_gb' => 60,
            ],
            'estimated_cost_per_hour' => 0.12,
            'services' => ['nginx', 'php8.4-fpm', 'mariadb', 'postfix', 'dovecot', 'sieve', 'spamprobe'],
        ],
        'filament-admin' => [
            'name' => 'Filament v4 Admin Platform',
            'description' => 'Pre-configured Laravel 12 + Filament v4 admin panel with LEMP stack',
            'default_resources' => [
                'cpu_cores' => 2,
                'memory_gb' => 4,
                'storage_gb' => 35,
            ],
            'estimated_cost_per_hour' => 0.07,
            'services' => ['nginx', 'php8.4-fpm', 'mariadb', 'redis'],
        ],
        'multi-tenant-saas' => [
            'name' => 'Multi-Tenant SaaS Platform',
            'description' => 'Laravel 12 with multi-tenancy, Filament v4, mail server, background jobs',
            'default_resources' => [
                'cpu_cores' => 4,
                'memory_gb' => 8,
                'storage_gb' => 80,
            ],
            'estimated_cost_per_hour' => 0.15,
            'services' => ['nginx', 'php8.4-fpm', 'mariadb', 'redis', 'postfix', 'dovecot', 'supervisor'],
        ],
        'api-backend' => [
            'name' => 'Laravel API Backend',
            'description' => 'High-performance Laravel 12 API with Nginx, PHP 8.4-FPM, MariaDB, Redis',
            'default_resources' => [
                'cpu_cores' => 2,
                'memory_gb' => 4,
                'storage_gb' => 30,
            ],
            'estimated_cost_per_hour' => 0.06,
            'services' => ['nginx', 'php8.4-fpm', 'mariadb', 'redis'],
        ],
        'legacy-migration' => [
            'name' => 'PHP 8.4 Migration Environment',
            'description' => 'Modern PHP 8.4 environment for application migration and testing',
            'default_resources' => [
                'cpu_cores' => 2,
                'memory_gb' => 4,
                'storage_gb' => 40,
            ],
            'estimated_cost_per_hour' => 0.06,
            'services' => ['nginx', 'php8.4-fpm', 'mariadb', 'sqlite'],
        ],
    ],

    'cost_calculation' => [
        /*
        |--------------------------------------------------------------------------
        | Cost Calculation Settings
        |--------------------------------------------------------------------------
        |
        | Configuration for cost calculation and billing estimates.
        |
        */
        'rates' => [
            'cpu_per_core_per_hour' => env('PROXMOX_CPU_RATE', 0.03),
            'memory_per_gb_per_hour' => env('PROXMOX_MEMORY_RATE', 0.01),
            'storage_per_gb_per_hour' => env('PROXMOX_STORAGE_RATE', 0.0001),
            'container_discount_factor' => env('PROXMOX_CONTAINER_DISCOUNT', 0.7), // 30% cheaper than VMs
        ],
        'currency' => env('PROXMOX_CURRENCY', 'USD'),
        'update_interval' => env('PROXMOX_COST_UPDATE_INTERVAL', 3600), // seconds (1 hour)
    ],

    'backup' => [
        /*
        |--------------------------------------------------------------------------
        | Backup Configuration
        |--------------------------------------------------------------------------
        |
        | Default backup settings for VMs and containers.
        |
        */
        'default_enabled' => env('PROXMOX_BACKUP_DEFAULT_ENABLED', true),
        'default_schedule' => [
            'frequency' => env('PROXMOX_BACKUP_FREQUENCY', 'daily'),
            'time' => env('PROXMOX_BACKUP_TIME', '02:00'),
            'retention_days' => env('PROXMOX_BACKUP_RETENTION_DAYS', 7),
        ],
        'compression' => env('PROXMOX_BACKUP_COMPRESSION', 'lzo'),
        'mode' => env('PROXMOX_BACKUP_MODE', 'snapshot'), // snapshot, suspend, stop
    ],

    'security' => [
        /*
        |--------------------------------------------------------------------------
        | Security Settings
        |--------------------------------------------------------------------------
        |
        | Security-related configuration options.
        |
        */
        'encrypt_credentials' => env('PROXMOX_ENCRYPT_CREDENTIALS', true),
        'require_tls' => env('PROXMOX_REQUIRE_TLS', true),
        'allowed_networks' => env('PROXMOX_ALLOWED_NETWORKS', ''), // Comma-separated CIDRs
        'api_token_expiry_days' => env('PROXMOX_API_TOKEN_EXPIRY_DAYS', 365),

        /*
        |--------------------------------------------------------------------------
        | Container Security
        |--------------------------------------------------------------------------
        |
        | Security settings specific to LXC containers.
        |
        */
        'container_defaults' => [
            'privileged' => env('PROXMOX_CONTAINER_DEFAULT_PRIVILEGED', false),
            'nesting' => env('PROXMOX_CONTAINER_DEFAULT_NESTING', false),
            'keyctl' => env('PROXMOX_CONTAINER_DEFAULT_KEYCTL', false),
        ],
    ],

    'networking' => [
        /*
        |--------------------------------------------------------------------------
        | Networking Configuration
        |--------------------------------------------------------------------------
        |
        | Default networking settings for VMs and containers.
        |
        */
        'default_bridge' => env('PROXMOX_DEFAULT_BRIDGE', 'vmbr0'),
        'vlan_range' => [
            'start' => env('PROXMOX_VLAN_RANGE_START', 100),
            'end' => env('PROXMOX_VLAN_RANGE_END', 999),
        ],
        'ip_ranges' => [
            'development' => env('PROXMOX_DEV_IP_RANGE', '192.168.100.0/24'),
            'testing' => env('PROXMOX_TEST_IP_RANGE', '192.168.101.0/24'),
            'staging' => env('PROXMOX_STAGE_IP_RANGE', '192.168.102.0/24'),
        ],
    ],

    'queue' => [
        /*
        |--------------------------------------------------------------------------
        | Queue Configuration
        |--------------------------------------------------------------------------
        |
        | Queue settings for background job processing.
        |
        */
        'connection' => env('PROXMOX_QUEUE_CONNECTION', 'database'),
        'health_check_queue' => env('PROXMOX_HEALTH_CHECK_QUEUE', 'proxmox-monitoring'),
        'provisioning_queue' => env('PROXMOX_PROVISIONING_QUEUE', 'proxmox-provisioning'),
        'default_queue' => env('PROXMOX_DEFAULT_QUEUE', 'proxmox'),

        /*
        |--------------------------------------------------------------------------
        | Job Retry Configuration
        |--------------------------------------------------------------------------
        */
        'max_retries' => env('PROXMOX_JOB_MAX_RETRIES', 3),
        'retry_delay' => env('PROXMOX_JOB_RETRY_DELAY', 60), // seconds
    ],
];
