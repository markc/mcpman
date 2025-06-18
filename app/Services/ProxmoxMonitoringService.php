<?php

namespace App\Services;

use App\Models\ProxmoxCluster;
use App\Models\ProxmoxContainer;
use App\Models\ProxmoxNode;
use App\Models\ProxmoxVirtualMachine;
use Exception;
use Illuminate\Support\Facades\Log;

class ProxmoxMonitoringService
{
    protected ProxmoxCluster $cluster;

    protected ProxmoxApiClient $apiClient;

    public function __construct(ProxmoxCluster $cluster)
    {
        $this->cluster = $cluster;
        $this->apiClient = $cluster->getApiClient();
    }

    /**
     * Perform comprehensive cluster health check
     */
    public function performHealthCheck(): array
    {
        try {
            $healthData = [
                'cluster_status' => $this->checkClusterStatus(),
                'nodes_status' => $this->checkNodesStatus(),
                'resources_status' => $this->checkResourcesStatus(),
                'vms_status' => $this->checkVirtualMachinesStatus(),
                'containers_status' => $this->checkContainersStatus(),
                'overall_health' => 0,
                'checked_at' => now(),
            ];

            // Calculate overall health score
            $healthData['overall_health'] = $this->calculateOverallHealth($healthData);

            // Update cluster last seen timestamp
            $this->cluster->update([
                'last_seen_at' => now(),
                'status' => $healthData['overall_health'] > 50 ? 'active' : 'degraded',
            ]);

            Log::info("Health check completed for cluster {$this->cluster->name}");

            return $healthData;

        } catch (Exception $e) {
            Log::error("Health check failed for cluster {$this->cluster->name}: ".$e->getMessage());

            $this->cluster->update([
                'status' => 'error',
                'last_error' => $e->getMessage(),
                'last_seen_at' => now(),
            ]);

            throw $e;
        }
    }

    /**
     * Check cluster status
     */
    private function checkClusterStatus(): array
    {
        try {
            $clusterInfo = $this->apiClient->getClusterStatus();

            $this->cluster->update([
                'cluster_info' => $clusterInfo,
            ]);

            return [
                'status' => 'healthy',
                'info' => $clusterInfo,
                'nodes_count' => count($clusterInfo['nodes'] ?? []),
                'quorum' => $clusterInfo['quorum'] ?? false,
            ];

        } catch (Exception $e) {
            Log::error('Failed to check cluster status: '.$e->getMessage());

            return [
                'status' => 'error',
                'error' => $e->getMessage(),
                'nodes_count' => 0,
                'quorum' => false,
            ];
        }
    }

    /**
     * Check and update all nodes status
     */
    private function checkNodesStatus(): array
    {
        try {
            $nodesData = $this->apiClient->getNodes();
            $nodeStatuses = [];

            foreach ($nodesData as $nodeData) {
                $node = $this->updateNodeFromApi($nodeData);
                $nodeStatuses[$node->name] = [
                    'status' => $node->status,
                    'health_score' => $node->getHealthScore(),
                    'cpu_usage' => $node->cpu_usage_percent,
                    'memory_usage' => $node->getMemoryUtilization(),
                    'storage_usage' => $node->getStorageUtilization(),
                    'uptime' => $node->uptime_seconds,
                ];
            }

            return [
                'status' => 'healthy',
                'nodes' => $nodeStatuses,
                'total_nodes' => count($nodeStatuses),
                'online_nodes' => count(array_filter($nodeStatuses, fn ($n) => $n['status'] === 'online')),
            ];

        } catch (Exception $e) {
            Log::error('Failed to check nodes status: '.$e->getMessage());

            return [
                'status' => 'error',
                'error' => $e->getMessage(),
                'nodes' => [],
                'total_nodes' => 0,
                'online_nodes' => 0,
            ];
        }
    }

    /**
     * Check cluster resource utilization
     */
    private function checkResourcesStatus(): array
    {
        try {
            $nodes = $this->cluster->nodes()->where('status', 'online')->get();

            $totalResources = [
                'cpu_cores' => $nodes->sum('cpu_cores'),
                'memory_bytes' => $nodes->sum('memory_bytes'),
                'storage_bytes' => $nodes->sum('storage_bytes'),
            ];

            $usedResources = [
                'cpu_cores' => $nodes->sum(function ($node) {
                    return ($node->cpu_usage_percent / 100) * $node->cpu_cores;
                }),
                'memory_bytes' => $nodes->sum('memory_used_bytes'),
                'storage_bytes' => $nodes->sum('storage_used_bytes'),
            ];

            // Update cluster resource information
            $this->cluster->update([
                'total_resources' => $totalResources,
                'used_resources' => $usedResources,
            ]);

            $utilization = [
                'cpu' => $totalResources['cpu_cores'] > 0
                    ? round(($usedResources['cpu_cores'] / $totalResources['cpu_cores']) * 100, 2)
                    : 0,
                'memory' => $totalResources['memory_bytes'] > 0
                    ? round(($usedResources['memory_bytes'] / $totalResources['memory_bytes']) * 100, 2)
                    : 0,
                'storage' => $totalResources['storage_bytes'] > 0
                    ? round(($usedResources['storage_bytes'] / $totalResources['storage_bytes']) * 100, 2)
                    : 0,
            ];

            return [
                'status' => 'healthy',
                'total_resources' => $totalResources,
                'used_resources' => $usedResources,
                'utilization' => $utilization,
                'available_resources' => [
                    'cpu_cores' => $totalResources['cpu_cores'] - $usedResources['cpu_cores'],
                    'memory_bytes' => $totalResources['memory_bytes'] - $usedResources['memory_bytes'],
                    'storage_bytes' => $totalResources['storage_bytes'] - $usedResources['storage_bytes'],
                ],
            ];

        } catch (Exception $e) {
            Log::error('Failed to check resources status: '.$e->getMessage());

            return [
                'status' => 'error',
                'error' => $e->getMessage(),
                'utilization' => ['cpu' => 0, 'memory' => 0, 'storage' => 0],
            ];
        }
    }

    /**
     * Check and sync virtual machines status
     */
    private function checkVirtualMachinesStatus(): array
    {
        try {
            $vmStatuses = [];
            $totalVms = 0;
            $runningVms = 0;

            foreach ($this->cluster->nodes()->where('status', 'online')->get() as $node) {
                $vmsData = $this->apiClient->getVMs($node->name);

                foreach ($vmsData as $vmData) {
                    $vm = $this->updateVmFromApi($node, $vmData);
                    if ($vm) {
                        $vmStatuses[$vm->vmid] = [
                            'name' => $vm->name,
                            'status' => $vm->status,
                            'node' => $node->name,
                            'cpu_usage' => $vm->cpu_usage_percent,
                            'memory_usage' => $vm->getMemoryUtilization(),
                            'uptime' => $vm->uptime_seconds,
                        ];

                        $totalVms++;
                        if ($vm->status === 'running') {
                            $runningVms++;
                        }
                    }
                }
            }

            return [
                'status' => 'healthy',
                'vms' => $vmStatuses,
                'total_vms' => $totalVms,
                'running_vms' => $runningVms,
                'stopped_vms' => $totalVms - $runningVms,
            ];

        } catch (Exception $e) {
            Log::error('Failed to check VMs status: '.$e->getMessage());

            return [
                'status' => 'error',
                'error' => $e->getMessage(),
                'vms' => [],
                'total_vms' => 0,
                'running_vms' => 0,
            ];
        }
    }

    /**
     * Check and sync containers status
     */
    private function checkContainersStatus(): array
    {
        try {
            $containerStatuses = [];
            $totalContainers = 0;
            $runningContainers = 0;

            foreach ($this->cluster->nodes()->where('status', 'online')->get() as $node) {
                $containersData = $this->apiClient->getContainers($node->name);

                foreach ($containersData as $containerData) {
                    $container = $this->updateContainerFromApi($node, $containerData);
                    if ($container) {
                        $containerStatuses[$container->ctid] = [
                            'name' => $container->name,
                            'status' => $container->status,
                            'node' => $node->name,
                            'cpu_usage' => $container->cpu_usage_percent,
                            'memory_usage' => $container->getMemoryUtilization(),
                            'uptime' => $container->uptime_seconds,
                        ];

                        $totalContainers++;
                        if ($container->status === 'running') {
                            $runningContainers++;
                        }
                    }
                }
            }

            return [
                'status' => 'healthy',
                'containers' => $containerStatuses,
                'total_containers' => $totalContainers,
                'running_containers' => $runningContainers,
                'stopped_containers' => $totalContainers - $runningContainers,
            ];

        } catch (Exception $e) {
            Log::error('Failed to check containers status: '.$e->getMessage());

            return [
                'status' => 'error',
                'error' => $e->getMessage(),
                'containers' => [],
                'total_containers' => 0,
                'running_containers' => 0,
            ];
        }
    }

    /**
     * Update node information from API data
     */
    private function updateNodeFromApi(array $nodeData): ProxmoxNode
    {
        $node = ProxmoxNode::updateOrCreate(
            [
                'proxmox_cluster_id' => $this->cluster->id,
                'name' => $nodeData['node'],
            ],
            [
                'ip_address' => $nodeData['ip'] ?? '127.0.0.1',
                'status' => $nodeData['status'] === 'online' ? 'online' : 'offline',
                'cpu_cores' => $nodeData['maxcpu'] ?? 0,
                'memory_bytes' => ($nodeData['maxmem'] ?? 0),
                'storage_bytes' => ($nodeData['maxdisk'] ?? 0),
                'cpu_model' => $nodeData['cpu'] ?? null,
                'cpu_usage_percent' => $nodeData['cpu'] ? ($nodeData['cpu'] * 100) : 0,
                'memory_used_bytes' => $nodeData['mem'] ?? 0,
                'storage_used_bytes' => $nodeData['disk'] ?? 0,
                'load_average' => $nodeData['load'] ?? 0,
                'uptime_seconds' => $nodeData['uptime'] ?? 0,
                'pve_version' => $nodeData['pveversion'] ?? null,
                'kernel_version' => $nodeData['kversion'] ?? null,
                'last_health_check' => now(),
            ]
        );

        return $node;
    }

    /**
     * Update VM information from API data
     */
    private function updateVmFromApi(ProxmoxNode $node, array $vmData): ?ProxmoxVirtualMachine
    {
        $vm = ProxmoxVirtualMachine::where('proxmox_cluster_id', $this->cluster->id)
            ->where('vmid', $vmData['vmid'])
            ->first();

        if (! $vm) {
            // VM exists in Proxmox but not in our database - create it
            $vm = ProxmoxVirtualMachine::create([
                'proxmox_cluster_id' => $this->cluster->id,
                'proxmox_node_id' => $node->id,
                'vmid' => $vmData['vmid'],
                'name' => $vmData['name'] ?? "vm-{$vmData['vmid']}",
                'status' => $vmData['status'] ?? 'unknown',
                'type' => $vmData['template'] ? 'template' : 'vm',
                'cpu_cores' => $vmData['cpus'] ?? 1,
                'memory_bytes' => ($vmData['maxmem'] ?? 0),
                'total_disk_bytes' => ($vmData['maxdisk'] ?? 0),
                'is_template' => $vmData['template'] ?? false,
                'last_seen_at' => now(),
            ]);
        } else {
            // Update existing VM
            $vm->update([
                'proxmox_node_id' => $node->id,
                'status' => $vmData['status'] ?? 'unknown',
                'cpu_usage_percent' => $vmData['cpu'] ? ($vmData['cpu'] * 100) : null,
                'memory_used_bytes' => $vmData['mem'] ?? null,
                'disk_used_bytes' => $vmData['disk'] ?? null,
                'network_in_bytes' => $vmData['netin'] ?? null,
                'network_out_bytes' => $vmData['netout'] ?? null,
                'uptime_seconds' => $vmData['uptime'] ?? null,
                'last_seen_at' => now(),
            ]);
        }

        return $vm;
    }

    /**
     * Update container information from API data
     */
    private function updateContainerFromApi(ProxmoxNode $node, array $containerData): ?ProxmoxContainer
    {
        $container = ProxmoxContainer::where('proxmox_cluster_id', $this->cluster->id)
            ->where('ctid', $containerData['vmid'])
            ->first();

        if (! $container) {
            // Container exists in Proxmox but not in our database - create it
            $container = ProxmoxContainer::create([
                'proxmox_cluster_id' => $this->cluster->id,
                'proxmox_node_id' => $node->id,
                'ctid' => $containerData['vmid'],
                'name' => $containerData['name'] ?? "ct-{$containerData['vmid']}",
                'status' => $containerData['status'] ?? 'unknown',
                'type' => $containerData['template'] ? 'template' : 'container',
                'os_template' => $containerData['ostemplate'] ?? 'unknown',
                'cpu_cores' => $containerData['cpus'] ?? 1,
                'memory_bytes' => ($containerData['maxmem'] ?? 0),
                'disk_bytes' => ($containerData['maxdisk'] ?? 0),
                'is_template' => $containerData['template'] ?? false,
                'last_seen_at' => now(),
            ]);
        } else {
            // Update existing container
            $container->update([
                'proxmox_node_id' => $node->id,
                'status' => $containerData['status'] ?? 'unknown',
                'cpu_usage_percent' => $containerData['cpu'] ? ($containerData['cpu'] * 100) : null,
                'memory_used_bytes' => $containerData['mem'] ?? null,
                'disk_used_bytes' => $containerData['disk'] ?? null,
                'network_in_bytes' => $containerData['netin'] ?? null,
                'network_out_bytes' => $containerData['netout'] ?? null,
                'uptime_seconds' => $containerData['uptime'] ?? null,
                'last_seen_at' => now(),
            ]);
        }

        return $container;
    }

    /**
     * Calculate overall cluster health score
     */
    private function calculateOverallHealth(array $healthData): int
    {
        $score = 100;

        // Cluster status weight: 30%
        if ($healthData['cluster_status']['status'] !== 'healthy') {
            $score -= 30;
        }

        // Nodes status weight: 25%
        $nodesStatus = $healthData['nodes_status'];
        if ($nodesStatus['total_nodes'] > 0) {
            $nodeHealthRatio = $nodesStatus['online_nodes'] / $nodesStatus['total_nodes'];
            $score = $score - 25 + ($nodeHealthRatio * 25);
        }

        // Resource utilization weight: 25%
        $resourcesStatus = $healthData['resources_status'];
        if ($resourcesStatus['status'] === 'healthy') {
            $utilization = $resourcesStatus['utilization'];
            $avgUtilization = ($utilization['cpu'] + $utilization['memory'] + $utilization['storage']) / 3;

            if ($avgUtilization > 90) {
                $score -= 20;
            } elseif ($avgUtilization > 80) {
                $score -= 10;
            } elseif ($avgUtilization > 70) {
                $score -= 5;
            }
        } else {
            $score -= 25;
        }

        // VMs and containers status weight: 20%
        $vmsStatus = $healthData['vms_status'];
        $containersStatus = $healthData['containers_status'];

        if ($vmsStatus['status'] !== 'healthy' || $containersStatus['status'] !== 'healthy') {
            $score -= 20;
        }

        return max(0, min(100, (int) $score));
    }

    /**
     * Get cluster resource trends
     */
    public function getResourceTrends(int $hours = 24): array
    {
        // This would typically fetch historical data from a time-series database
        // For now, return current metrics as a single data point
        $healthData = $this->performHealthCheck();

        return [
            'cpu_trend' => [$healthData['resources_status']['utilization']['cpu']],
            'memory_trend' => [$healthData['resources_status']['utilization']['memory']],
            'storage_trend' => [$healthData['resources_status']['utilization']['storage']],
            'timestamps' => [now()->timestamp],
        ];
    }

    /**
     * Get cluster cost analysis
     */
    public function getCostAnalysis(): array
    {
        $environments = $this->cluster->developmentEnvironments()->where('status', 'running')->get();

        $totalHourlyCost = $environments->sum('actual_cost_per_hour');
        $totalMonthlyCost = $totalHourlyCost * 24 * 30;

        $costByType = $environments->groupBy('environment_type')->map(function ($envs) {
            return $envs->sum('actual_cost_per_hour') * 24 * 30;
        });

        return [
            'total_hourly_cost' => $totalHourlyCost,
            'total_monthly_cost' => $totalMonthlyCost,
            'cost_by_environment_type' => $costByType,
            'active_environments' => $environments->count(),
        ];
    }
}
