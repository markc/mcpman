<?php

namespace App\Services;

use App\Models\DevelopmentEnvironment;
use App\Models\ProxmoxCluster;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class EnvironmentProvisioningService
{
    protected DevelopmentEnvironment $environment;

    protected ProxmoxCluster $cluster;

    protected ProxmoxLifecycleService $lifecycleService;

    public function __construct(DevelopmentEnvironment $environment)
    {
        $this->environment = $environment;
        $this->cluster = $environment->cluster;
        $this->lifecycleService = new ProxmoxLifecycleService($this->cluster);
    }

    /**
     * Provision complete development environment
     */
    public function provision(): bool
    {
        try {
            DB::beginTransaction();

            $this->updateEnvironmentStatus('provisioning', 'Starting environment provisioning');
            $this->logProvisioningStep('started', 'Environment provisioning started');

            // Step 1: Validate cluster and resources
            $this->validateClusterAndResources();
            $this->logProvisioningStep('validated', 'Cluster and resources validated');

            // Step 2: Generate environment template configuration
            $templateConfig = $this->generateTemplateConfiguration();
            $this->environment->update(['template_config' => $templateConfig]);
            $this->logProvisioningStep('template_generated', 'Template configuration generated');

            // Step 3: Provision VMs and containers based on template
            $resources = $this->provisionResources($templateConfig);
            $this->logProvisioningStep('resources_created', 'VMs and containers created', $resources);

            // Step 4: Configure networking
            if ($this->environment->network_vlan || $this->environment->subnet_cidr) {
                $this->configureNetworking();
                $this->logProvisioningStep('networking_configured', 'Network configuration applied');
            }

            // Step 5: Install and configure software
            $this->configureSoftware($resources);
            $this->logProvisioningStep('software_configured', 'Software configuration completed');

            // Step 6: Setup development tools
            $this->setupDevelopmentTools($resources);
            $this->logProvisioningStep('dev_tools_setup', 'Development tools configured');

            // Step 7: Configure backups and snapshots
            if ($this->environment->backup_enabled) {
                $this->configureBackups($resources);
                $this->logProvisioningStep('backups_configured', 'Backup configuration applied');
            }

            // Step 8: Start all resources if auto_start is enabled
            if ($this->environment->auto_start) {
                $this->startAllResources($resources);
                $this->logProvisioningStep('resources_started', 'All resources started');
            }

            // Step 9: Final validation and status update
            $this->performFinalValidation();
            $this->updateEnvironmentStatus('running', 'Environment successfully provisioned');
            $this->environment->update([
                'provisioned_at' => now(),
                'actual_cost_per_hour' => $this->calculateActualCost($resources),
            ]);

            DB::commit();

            Log::info("Successfully provisioned environment: {$this->environment->name}");

            return true;

        } catch (Exception $e) {
            DB::rollBack();

            $this->updateEnvironmentStatus('failed', "Provisioning failed: {$e->getMessage()}");
            $this->logProvisioningStep('failed', 'Provisioning failed', ['error' => $e->getMessage()]);

            Log::error("Failed to provision environment {$this->environment->name}: ".$e->getMessage());

            // Cleanup any partially created resources
            $this->cleanupFailedProvisioning();

            return false;
        }
    }

    /**
     * Validate cluster availability and resource requirements
     */
    private function validateClusterAndResources(): void
    {
        if (! $this->cluster->isOnline()) {
            throw new Exception("Cluster {$this->cluster->name} is not online");
        }

        $availableResources = $this->cluster->getResourceUtilization();
        $requiredCpu = $this->environment->total_cpu_cores;
        $requiredMemory = $this->environment->total_memory_bytes;
        $requiredStorage = $this->environment->total_storage_bytes;

        // Check if cluster has sufficient resources
        $suitableNodes = $this->cluster->nodes()
            ->canAccommodate($requiredCpu, $requiredMemory, $requiredStorage)
            ->count();

        if ($suitableNodes === 0) {
            throw new Exception('No suitable nodes found with sufficient resources');
        }
    }

    /**
     * Generate template-specific configuration
     */
    private function generateTemplateConfiguration(): array
    {
        $templateName = $this->environment->template_name;

        return match ($templateName) {
            'lemp-laravel-stack' => $this->generateLempLaravelStackConfig(),
            'full-mail-server' => $this->generateFullMailServerConfig(),
            'filament-admin' => $this->generateFilamentAdminConfig(),
            'multi-tenant-saas' => $this->generateMultiTenantSaasConfig(),
            'api-backend' => $this->generateApiBackendConfig(),
            'legacy-migration' => $this->generateLegacyMigrationConfig(),
            'custom' => $this->generateCustomConfig(),
            default => throw new Exception("Unknown template: {$templateName}"),
        };
    }

    /**
     * Provision VMs and containers based on template
     */
    private function provisionResources(array $templateConfig): array
    {
        $resources = ['vms' => [], 'containers' => []];

        // Create VMs
        foreach ($templateConfig['vms'] ?? [] as $vmConfig) {
            $vmConfig['development_environment_id'] = $this->environment->id;
            $vm = $this->lifecycleService->createVirtualMachine($vmConfig);
            $resources['vms'][] = $vm;
        }

        // Create containers
        foreach ($templateConfig['containers'] ?? [] as $containerConfig) {
            $containerConfig['development_environment_id'] = $this->environment->id;
            $container = $this->lifecycleService->createContainer($containerConfig);
            $resources['containers'][] = $container;
        }

        return $resources;
    }

    /**
     * Configure networking for the environment
     */
    private function configureNetworking(): void
    {
        // TODO: Implement VLAN and subnet configuration
        // This would involve configuring Proxmox network settings
        Log::info("Network configuration completed for environment {$this->environment->name}");
    }

    /**
     * Configure software and applications
     */
    private function configureSoftware(array $resources): void
    {
        // TODO: Implement software configuration
        // This would involve running scripts inside VMs/containers
        Log::info("Software configuration completed for environment {$this->environment->name}");
    }

    /**
     * Setup development tools and IDEs
     */
    private function setupDevelopmentTools(array $resources): void
    {
        if (! $this->environment->ide_type) {
            return;
        }

        // TODO: Implement development tools setup
        // Install and configure selected IDE, clone git repositories, etc.
        Log::info("Development tools setup completed for environment {$this->environment->name}");
    }

    /**
     * Configure backups for all resources
     */
    private function configureBackups(array $resources): void
    {
        $backupSchedule = [
            'frequency' => 'daily',
            'retention' => '7 days',
            'time' => '02:00',
        ];

        foreach ($resources['vms'] as $vm) {
            $vm->update([
                'backup_enabled' => true,
                'backup_schedule' => $backupSchedule,
            ]);
        }

        foreach ($resources['containers'] as $container) {
            $container->update([
                'backup_enabled' => true,
                'backup_schedule' => $backupSchedule,
            ]);
        }
    }

    /**
     * Start all resources in the environment
     */
    private function startAllResources(array $resources): void
    {
        // Start VMs first (they typically take longer)
        foreach ($resources['vms'] as $vm) {
            $this->lifecycleService->startVirtualMachine($vm);
            sleep(2); // Small delay between starts
        }

        // Then start containers
        foreach ($resources['containers'] as $container) {
            $this->lifecycleService->startContainer($container);
            sleep(1);
        }
    }

    /**
     * Perform final validation of the environment
     */
    private function performFinalValidation(): void
    {
        // Verify all resources are created and accessible
        $totalResources = $this->environment->getTotalResources();

        if ($totalResources['total'] === 0) {
            throw new Exception('No resources were created for the environment');
        }

        // TODO: Additional validation (network connectivity, service health, etc.)
    }

    /**
     * Calculate actual cost based on provisioned resources
     */
    private function calculateActualCost(array $resources): float
    {
        $totalCost = 0;

        foreach ($resources['vms'] as $vm) {
            $totalCost += $vm->getEstimatedMonthlyCost() / (24 * 30); // Convert to hourly
        }

        foreach ($resources['containers'] as $container) {
            $totalCost += $container->getEstimatedMonthlyCost() / (24 * 30); // Convert to hourly
        }

        return round($totalCost, 4);
    }

    /**
     * Cleanup resources from failed provisioning
     */
    private function cleanupFailedProvisioning(): void
    {
        try {
            // Delete any VMs that were created
            foreach ($this->environment->virtualMachines as $vm) {
                $this->lifecycleService->deleteVirtualMachine($vm, true);
            }

            // Delete any containers that were created
            foreach ($this->environment->containers as $container) {
                $this->lifecycleService->deleteContainer($container, true);
            }

        } catch (Exception $e) {
            Log::error("Failed to cleanup resources for environment {$this->environment->name}: ".$e->getMessage());
        }
    }

    /**
     * Update environment status and error message
     */
    private function updateEnvironmentStatus(string $status, ?string $error = null): void
    {
        $this->environment->update([
            'status' => $status,
            'last_error' => $error,
        ]);
    }

    /**
     * Log provisioning step for debugging and monitoring
     */
    private function logProvisioningStep(string $step, string $message, array $data = []): void
    {
        $log = $this->environment->provisioning_log ?? [];
        $log[] = [
            'step' => $step,
            'message' => $message,
            'data' => $data,
            'timestamp' => now()->toISOString(),
        ];

        $this->environment->update(['provisioning_log' => $log]);
    }

    /**
     * Template configurations - PHP/Laravel focused
     */
    private function generateLempLaravelStackConfig(): array
    {
        return [
            'vms' => [
                [
                    'name' => "{$this->environment->name}-lemp-laravel",
                    'description' => 'LEMP stack with Laravel 12 and Filament v4',
                    'cpu_cores' => $this->environment->total_cpu_cores,
                    'memory_bytes' => $this->environment->total_memory_bytes,
                    'disk_bytes' => $this->environment->total_storage_bytes,
                    'os_type' => 'linux',
                    'template_id' => null,
                    'tags' => ['lemp', 'laravel', 'nginx', 'php8.4', 'mariadb'],
                    'services' => ['nginx', 'php8.4-fpm', 'mariadb-server', 'redis-server', 'composer'],
                    'setup_script' => 'lemp-laravel-setup.sh',
                ],
            ],
        ];
    }

    private function generateFullMailServerConfig(): array
    {
        return [
            'vms' => [
                [
                    'name' => "{$this->environment->name}-mail-web",
                    'description' => 'Complete mail server with web interface',
                    'cpu_cores' => $this->environment->total_cpu_cores,
                    'memory_bytes' => $this->environment->total_memory_bytes,
                    'disk_bytes' => $this->environment->total_storage_bytes,
                    'os_type' => 'linux',
                    'tags' => ['mail-server', 'postfix', 'dovecot', 'nginx', 'php8.4'],
                    'services' => [
                        'nginx', 'php8.4-fpm', 'mariadb-server',
                        'postfix', 'dovecot-core', 'dovecot-imapd', 'dovecot-pop3d',
                        'dovecot-managesieved', 'spamprobe', 'redis-server',
                    ],
                    'setup_script' => 'mail-server-setup.sh',
                ],
            ],
        ];
    }

    private function generateFilamentAdminConfig(): array
    {
        return [
            'vms' => [
                [
                    'name' => "{$this->environment->name}-filament-admin",
                    'description' => 'Laravel 12 + Filament v4 admin panel',
                    'cpu_cores' => $this->environment->total_cpu_cores,
                    'memory_bytes' => $this->environment->total_memory_bytes,
                    'disk_bytes' => $this->environment->total_storage_bytes,
                    'os_type' => 'linux',
                    'tags' => ['filament', 'laravel', 'admin-panel', 'nginx', 'php8.4'],
                    'services' => ['nginx', 'php8.4-fpm', 'mariadb-server', 'redis-server'],
                    'setup_script' => 'filament-admin-setup.sh',
                ],
            ],
        ];
    }

    private function generateMultiTenantSaasConfig(): array
    {
        return [
            'vms' => [
                [
                    'name' => "{$this->environment->name}-saas-web",
                    'description' => 'Multi-tenant SaaS application server',
                    'cpu_cores' => max(2, floor($this->environment->total_cpu_cores * 0.7)),
                    'memory_bytes' => floor($this->environment->total_memory_bytes * 0.7),
                    'disk_bytes' => floor($this->environment->total_storage_bytes * 0.6),
                    'os_type' => 'linux',
                    'tags' => ['saas', 'multi-tenant', 'laravel', 'nginx'],
                    'services' => ['nginx', 'php8.4-fpm', 'redis-server', 'supervisor'],
                    'setup_script' => 'multi-tenant-web-setup.sh',
                ],
                [
                    'name' => "{$this->environment->name}-saas-db",
                    'description' => 'Dedicated database server for SaaS platform',
                    'cpu_cores' => max(1, floor($this->environment->total_cpu_cores * 0.3)),
                    'memory_bytes' => floor($this->environment->total_memory_bytes * 0.3),
                    'disk_bytes' => floor($this->environment->total_storage_bytes * 0.4),
                    'os_type' => 'linux',
                    'tags' => ['database', 'mariadb', 'saas'],
                    'services' => ['mariadb-server'],
                    'setup_script' => 'mariadb-dedicated-setup.sh',
                ],
            ],
        ];
    }

    private function generateApiBackendConfig(): array
    {
        return [
            'containers' => [
                [
                    'name' => "{$this->environment->name}-api-backend",
                    'description' => 'High-performance Laravel API backend',
                    'cpu_cores' => $this->environment->total_cpu_cores,
                    'memory_bytes' => $this->environment->total_memory_bytes,
                    'disk_bytes' => $this->environment->total_storage_bytes,
                    'os_template' => 'local:vztmpl/debian-13-standard_13.0-1_amd64.tar.zst',
                    'privileged' => false,
                    'tags' => ['api', 'laravel', 'nginx', 'php8.4'],
                    'services' => ['nginx', 'php8.4-fpm', 'mariadb-server', 'redis-server'],
                    'setup_script' => 'laravel-api-setup.sh',
                ],
            ],
        ];
    }

    private function generateLegacyMigrationConfig(): array
    {
        return [
            'vms' => [
                [
                    'name' => "{$this->environment->name}-php84-migration",
                    'description' => 'Modern PHP 8.4 environment for application migration and testing',
                    'cpu_cores' => $this->environment->total_cpu_cores,
                    'memory_bytes' => $this->environment->total_memory_bytes,
                    'disk_bytes' => $this->environment->total_storage_bytes,
                    'os_type' => 'linux',
                    'tags' => ['migration', 'php8.4', 'nginx'],
                    'services' => [
                        'nginx',
                        'php8.4-fpm',
                        'mariadb-server', 'sqlite3',
                    ],
                    'setup_script' => 'php84-migration-setup.sh',
                ],
            ],
        ];
    }

    private function generateCustomConfig(): array
    {
        // For custom configurations, create a basic LEMP stack
        return [
            'vms' => [
                [
                    'name' => "{$this->environment->name}-custom",
                    'description' => 'Custom PHP/Laravel development environment',
                    'cpu_cores' => $this->environment->total_cpu_cores,
                    'memory_bytes' => $this->environment->total_memory_bytes,
                    'disk_bytes' => $this->environment->total_storage_bytes,
                    'os_type' => 'linux',
                    'tags' => ['custom', 'lemp'],
                    'services' => ['nginx', 'php8.4-fpm', 'mariadb-server'],
                    'setup_script' => 'basic-lemp-setup.sh',
                ],
            ],
        ];
    }
}
