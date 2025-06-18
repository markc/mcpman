<?php

namespace App\Services;

use App\Models\ProxmoxCluster;
use App\Models\ProxmoxContainer;
use App\Models\ProxmoxNode;
use App\Models\ProxmoxVirtualMachine;
use Exception;
use Illuminate\Support\Facades\Log;

class ProxmoxLifecycleService
{
    protected ProxmoxCluster $cluster;

    protected ProxmoxApiClient $apiClient;

    public function __construct(ProxmoxCluster $cluster)
    {
        $this->cluster = $cluster;
        $this->apiClient = $cluster->getApiClient();
    }

    /**
     * Create a new virtual machine
     */
    public function createVirtualMachine(array $config): ProxmoxVirtualMachine
    {
        try {
            // Find the best node for the VM
            $node = $this->findBestNode($config['cpu_cores'], $config['memory_bytes'], $config['disk_bytes']);

            if (! $node) {
                throw new Exception('No suitable node found for VM creation');
            }

            // Generate unique VMID
            $vmid = $this->generateUniqueVmid();

            // Prepare VM configuration for Proxmox API
            $proxmoxConfig = [
                'vmid' => $vmid,
                'name' => $config['name'],
                'cores' => $config['cpu_cores'],
                'memory' => $config['memory_bytes'] / (1024 * 1024), // Convert to MB
                'ostype' => $config['os_type'] ?? 'l26',
                'net0' => $config['network_config'] ?? 'virtio,bridge=vmbr0',
                'ide2' => 'none,media=cdrom',
                'boot' => 'order=scsi0',
                'agent' => 1,
            ];

            // Add disk configuration
            if (isset($config['template_id'])) {
                // Clone from template
                $result = $this->apiClient->cloneVM($config['template_id'], $vmid, [
                    'node' => $node->name,
                    'name' => $config['name'],
                    'description' => $config['description'] ?? '',
                ]);
            } else {
                // Create from scratch
                $proxmoxConfig['scsi0'] = "local-lvm:{$config['disk_bytes']}";
                $result = $this->apiClient->createVM($node->name, $proxmoxConfig);
            }

            // Create database record
            $vm = ProxmoxVirtualMachine::create([
                'proxmox_cluster_id' => $this->cluster->id,
                'proxmox_node_id' => $node->id,
                'development_environment_id' => $config['development_environment_id'] ?? null,
                'vmid' => $vmid,
                'name' => $config['name'],
                'description' => $config['description'] ?? '',
                'status' => 'stopped',
                'type' => 'vm',
                'os_type' => $config['os_type'] ?? 'linux',
                'cpu_cores' => $config['cpu_cores'],
                'memory_bytes' => $config['memory_bytes'],
                'total_disk_bytes' => $config['disk_bytes'],
                'network_interfaces' => $config['network_interfaces'] ?? [],
                'backup_enabled' => $config['backup_enabled'] ?? true,
                'template_id' => $config['template_id'] ?? null,
                'tags' => $config['tags'] ?? [],
            ]);

            Log::info("Created VM {$vm->name} (VMID: {$vmid}) on node {$node->name}");

            return $vm;

        } catch (Exception $e) {
            Log::error('Failed to create VM: '.$e->getMessage());
            throw $e;
        }
    }

    /**
     * Create a new container
     */
    public function createContainer(array $config): ProxmoxContainer
    {
        try {
            // Find the best node for the container
            $node = $this->findBestNode($config['cpu_cores'], $config['memory_bytes'], $config['disk_bytes']);

            if (! $node) {
                throw new Exception('No suitable node found for container creation');
            }

            // Generate unique CTID
            $ctid = $this->generateUniqueCtid();

            // Prepare container configuration for Proxmox API
            $proxmoxConfig = [
                'vmid' => $ctid,
                'hostname' => $config['name'],
                'cores' => $config['cpu_cores'],
                'memory' => $config['memory_bytes'] / (1024 * 1024), // Convert to MB
                'rootfs' => "local-lvm:{$config['disk_bytes']}",
                'ostemplate' => $config['os_template'],
                'net0' => $config['network_config'] ?? 'name=eth0,bridge=vmbr0,ip=dhcp',
                'unprivileged' => $config['privileged'] ? 0 : 1,
                'features' => $this->buildFeaturesString($config['features'] ?? []),
                'arch' => $config['arch'] ?? 'amd64',
            ];

            // Add swap if specified
            if (isset($config['swap_bytes']) && $config['swap_bytes'] > 0) {
                $proxmoxConfig['swap'] = $config['swap_bytes'] / (1024 * 1024);
            }

            // Create container via API
            $result = $this->apiClient->createContainer($node->name, $proxmoxConfig);

            // Create database record
            $container = ProxmoxContainer::create([
                'proxmox_cluster_id' => $this->cluster->id,
                'proxmox_node_id' => $node->id,
                'development_environment_id' => $config['development_environment_id'] ?? null,
                'ctid' => $ctid,
                'name' => $config['name'],
                'description' => $config['description'] ?? '',
                'status' => 'stopped',
                'type' => 'container',
                'os_template' => $config['os_template'],
                'os_type' => $config['os_type'] ?? 'linux',
                'cpu_cores' => $config['cpu_cores'],
                'memory_bytes' => $config['memory_bytes'],
                'swap_bytes' => $config['swap_bytes'] ?? 0,
                'disk_bytes' => $config['disk_bytes'],
                'privileged' => $config['privileged'] ?? false,
                'nesting' => $config['nesting'] ?? false,
                'features' => $config['features'] ?? [],
                'arch' => $config['arch'] ?? 'amd64',
                'storage_backend' => 'local-lvm',
                'network_interfaces' => $config['network_interfaces'] ?? [],
                'backup_enabled' => $config['backup_enabled'] ?? true,
                'tags' => $config['tags'] ?? [],
            ]);

            Log::info("Created container {$container->name} (CTID: {$ctid}) on node {$node->name}");

            return $container;

        } catch (Exception $e) {
            Log::error('Failed to create container: '.$e->getMessage());
            throw $e;
        }
    }

    /**
     * Start a virtual machine
     */
    public function startVirtualMachine(ProxmoxVirtualMachine $vm): bool
    {
        try {
            $result = $this->apiClient->startVM($vm->node->name, $vm->vmid);

            $vm->update([
                'status' => 'running',
                'started_at' => now(),
                'last_seen_at' => now(),
            ]);

            Log::info("Started VM {$vm->name} (VMID: {$vm->vmid})");

            return true;

        } catch (Exception $e) {
            Log::error("Failed to start VM {$vm->name}: ".$e->getMessage());

            $vm->update([
                'last_error' => $e->getMessage(),
                'last_seen_at' => now(),
            ]);

            return false;
        }
    }

    /**
     * Stop a virtual machine
     */
    public function stopVirtualMachine(ProxmoxVirtualMachine $vm, bool $force = false): bool
    {
        try {
            if ($force) {
                $result = $this->apiClient->stopVM($vm->node->name, $vm->vmid);
            } else {
                $result = $this->apiClient->shutdownVM($vm->node->name, $vm->vmid);
            }

            $vm->update([
                'status' => 'stopped',
                'stopped_at' => now(),
                'last_seen_at' => now(),
            ]);

            Log::info("Stopped VM {$vm->name} (VMID: {$vm->vmid})");

            return true;

        } catch (Exception $e) {
            Log::error("Failed to stop VM {$vm->name}: ".$e->getMessage());

            $vm->update([
                'last_error' => $e->getMessage(),
                'last_seen_at' => now(),
            ]);

            return false;
        }
    }

    /**
     * Start a container
     */
    public function startContainer(ProxmoxContainer $container): bool
    {
        try {
            $result = $this->apiClient->startContainer($container->node->name, $container->ctid);

            $container->update([
                'status' => 'running',
                'started_at' => now(),
                'last_seen_at' => now(),
            ]);

            Log::info("Started container {$container->name} (CTID: {$container->ctid})");

            return true;

        } catch (Exception $e) {
            Log::error("Failed to start container {$container->name}: ".$e->getMessage());

            $container->update([
                'last_error' => $e->getMessage(),
                'last_seen_at' => now(),
            ]);

            return false;
        }
    }

    /**
     * Stop a container
     */
    public function stopContainer(ProxmoxContainer $container, bool $force = false): bool
    {
        try {
            if ($force) {
                $result = $this->apiClient->stopContainer($container->node->name, $container->ctid);
            } else {
                $result = $this->apiClient->shutdownContainer($container->node->name, $container->ctid);
            }

            $container->update([
                'status' => 'stopped',
                'stopped_at' => now(),
                'last_seen_at' => now(),
            ]);

            Log::info("Stopped container {$container->name} (CTID: {$container->ctid})");

            return true;

        } catch (Exception $e) {
            Log::error("Failed to stop container {$container->name}: ".$e->getMessage());

            $container->update([
                'last_error' => $e->getMessage(),
                'last_seen_at' => now(),
            ]);

            return false;
        }
    }

    /**
     * Delete a virtual machine
     */
    public function deleteVirtualMachine(ProxmoxVirtualMachine $vm, bool $purge = false): bool
    {
        try {
            // Stop VM first if running
            if ($vm->isRunning()) {
                $this->stopVirtualMachine($vm, true);
                sleep(5); // Wait for VM to stop
            }

            $result = $this->apiClient->deleteVM($vm->node->name, $vm->vmid, $purge);

            // Remove from database
            $vm->delete();

            Log::info("Deleted VM {$vm->name} (VMID: {$vm->vmid})");

            return true;

        } catch (Exception $e) {
            Log::error("Failed to delete VM {$vm->name}: ".$e->getMessage());

            $vm->update([
                'last_error' => $e->getMessage(),
                'last_seen_at' => now(),
            ]);

            return false;
        }
    }

    /**
     * Delete a container
     */
    public function deleteContainer(ProxmoxContainer $container, bool $purge = false): bool
    {
        try {
            // Stop container first if running
            if ($container->isRunning()) {
                $this->stopContainer($container, true);
                sleep(3); // Wait for container to stop
            }

            $result = $this->apiClient->deleteContainer($container->node->name, $container->ctid, $purge);

            // Remove from database
            $container->delete();

            Log::info("Deleted container {$container->name} (CTID: {$container->ctid})");

            return true;

        } catch (Exception $e) {
            Log::error("Failed to delete container {$container->name}: ".$e->getMessage());

            $container->update([
                'last_error' => $e->getMessage(),
                'last_seen_at' => now(),
            ]);

            return false;
        }
    }

    /**
     * Clone a virtual machine
     */
    public function cloneVirtualMachine(ProxmoxVirtualMachine $sourceVm, array $config): ProxmoxVirtualMachine
    {
        try {
            $targetNode = $this->findBestNode(
                $sourceVm->cpu_cores,
                $sourceVm->memory_bytes,
                $sourceVm->total_disk_bytes
            );

            if (! $targetNode) {
                throw new Exception('No suitable node found for VM cloning');
            }

            $newVmid = $this->generateUniqueVmid();

            $cloneConfig = [
                'newid' => $newVmid,
                'name' => $config['name'],
                'description' => $config['description'] ?? "Clone of {$sourceVm->name}",
                'target' => $targetNode->name,
                'full' => $config['full_clone'] ?? true,
            ];

            $result = $this->apiClient->cloneVM($sourceVm->vmid, $newVmid, $cloneConfig);

            // Create database record for cloned VM
            $clonedVm = ProxmoxVirtualMachine::create([
                'proxmox_cluster_id' => $this->cluster->id,
                'proxmox_node_id' => $targetNode->id,
                'development_environment_id' => $config['development_environment_id'] ?? null,
                'vmid' => $newVmid,
                'name' => $config['name'],
                'description' => $config['description'] ?? "Clone of {$sourceVm->name}",
                'status' => 'stopped',
                'type' => 'vm',
                'os_type' => $sourceVm->os_type,
                'cpu_cores' => $sourceVm->cpu_cores,
                'memory_bytes' => $sourceVm->memory_bytes,
                'total_disk_bytes' => $sourceVm->total_disk_bytes,
                'backup_enabled' => $config['backup_enabled'] ?? true,
                'template_id' => $sourceVm->vmid,
                'clone_config' => $cloneConfig,
                'tags' => $config['tags'] ?? [],
            ]);

            Log::info("Cloned VM {$sourceVm->name} to {$clonedVm->name} (VMID: {$newVmid})");

            return $clonedVm;

        } catch (Exception $e) {
            Log::error("Failed to clone VM {$sourceVm->name}: ".$e->getMessage());
            throw $e;
        }
    }

    /**
     * Migrate a virtual machine to another node
     */
    public function migrateVirtualMachine(ProxmoxVirtualMachine $vm, ProxmoxNode $targetNode, bool $online = false): bool
    {
        try {
            $migrateConfig = [
                'target' => $targetNode->name,
                'online' => $online ? 1 : 0,
            ];

            $result = $this->apiClient->migrateVM($vm->node->name, $vm->vmid, $migrateConfig);

            // Update VM's node assignment
            $vm->update([
                'proxmox_node_id' => $targetNode->id,
                'last_seen_at' => now(),
            ]);

            Log::info("Migrated VM {$vm->name} to node {$targetNode->name}");

            return true;

        } catch (Exception $e) {
            Log::error("Failed to migrate VM {$vm->name}: ".$e->getMessage());

            $vm->update([
                'last_error' => $e->getMessage(),
                'last_seen_at' => now(),
            ]);

            return false;
        }
    }

    /**
     * Create snapshot for VM or container
     */
    public function createSnapshot(string $type, int $id, string $snapname, string $description = ''): bool
    {
        try {
            if ($type === 'vm') {
                $vm = ProxmoxVirtualMachine::where('vmid', $id)->firstOrFail();
                $result = $this->apiClient->createSnapshot($vm->node->name, $id, $snapname, $description);

                $snapshots = $vm->snapshots ?? [];
                $snapshots[] = [
                    'name' => $snapname,
                    'description' => $description,
                    'created_at' => now()->toISOString(),
                ];
                $vm->update(['snapshots' => $snapshots]);

            } else {
                $container = ProxmoxContainer::where('ctid', $id)->firstOrFail();
                $result = $this->apiClient->createContainerSnapshot($container->node->name, $id, $snapname, $description);

                $snapshots = $container->snapshots ?? [];
                $snapshots[] = [
                    'name' => $snapname,
                    'description' => $description,
                    'created_at' => now()->toISOString(),
                ];
                $container->update(['snapshots' => $snapshots]);
            }

            Log::info("Created snapshot {$snapname} for {$type} {$id}");

            return true;

        } catch (Exception $e) {
            Log::error("Failed to create snapshot for {$type} {$id}: ".$e->getMessage());

            return false;
        }
    }

    /**
     * Find the best node for resource allocation
     */
    private function findBestNode(int $cpuCores, int $memoryBytes, int $storageBytes): ?ProxmoxNode
    {
        $suitableNodes = $this->cluster->nodes()
            ->canAccommodate($cpuCores, $memoryBytes, $storageBytes)
            ->get();

        if ($suitableNodes->isEmpty()) {
            return null;
        }

        // Score nodes based on resource availability and health
        $bestNode = null;
        $bestScore = 0;

        foreach ($suitableNodes as $node) {
            $healthScore = $node->getHealthScore();
            $resourceScore = $this->calculateResourceScore($node, $cpuCores, $memoryBytes, $storageBytes);
            $totalScore = ($healthScore + $resourceScore) / 2;

            if ($totalScore > $bestScore) {
                $bestScore = $totalScore;
                $bestNode = $node;
            }
        }

        return $bestNode;
    }

    /**
     * Calculate resource score for node selection
     */
    private function calculateResourceScore(ProxmoxNode $node, int $cpuCores, int $memoryBytes, int $storageBytes): float
    {
        $availableCpu = $node->getAvailableCpuCores();
        $availableMemory = $node->getAvailableMemory();
        $availableStorage = $node->getAvailableStorage();

        // Calculate efficiency scores (more available resources = higher score)
        $cpuScore = min(100, ($availableCpu / $cpuCores) * 25);
        $memoryScore = min(100, ($availableMemory / $memoryBytes) * 25);
        $storageScore = min(100, ($availableStorage / $storageBytes) * 25);

        return ($cpuScore + $memoryScore + $storageScore) / 3;
    }

    /**
     * Generate unique VMID
     */
    private function generateUniqueVmid(): int
    {
        $maxVmid = ProxmoxVirtualMachine::where('proxmox_cluster_id', $this->cluster->id)
            ->max('vmid') ?? 100;

        return $maxVmid + 1;
    }

    /**
     * Generate unique CTID
     */
    private function generateUniqueCtid(): int
    {
        $maxCtid = ProxmoxContainer::where('proxmox_cluster_id', $this->cluster->id)
            ->max('ctid') ?? 100;

        return $maxCtid + 1;
    }

    /**
     * Build features string for container
     */
    private function buildFeaturesString(array $features): string
    {
        $enabledFeatures = array_keys(array_filter($features));

        return implode(',', $enabledFeatures);
    }
}
