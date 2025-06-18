<?php

namespace App\Console\Commands;

use App\Models\ProxmoxCluster;
use App\Services\ProxmoxMonitoringService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ProxmoxHealthCheckCommand extends Command
{
    protected $signature = 'proxmox:health-check {--cluster=* : Specific cluster IDs to check}';

    protected $description = 'Perform health checks on Proxmox clusters';

    public function handle()
    {
        $clusterIds = $this->option('cluster');

        if (empty($clusterIds)) {
            $clusters = ProxmoxCluster::where('status', 'active')->get();
        } else {
            $clusters = ProxmoxCluster::whereIn('id', $clusterIds)->get();
        }

        if ($clusters->isEmpty()) {
            $this->error('No clusters found to check.');

            return self::FAILURE;
        }

        $this->info("Performing health checks on {$clusters->count()} cluster(s)...");

        $healthResults = [];

        foreach ($clusters as $cluster) {
            $this->line("Checking cluster: {$cluster->name}");

            try {
                $monitoringService = new ProxmoxMonitoringService($cluster);
                $healthData = $monitoringService->performHealthCheck();

                $healthScore = $healthData['overall_health'];
                $status = $healthScore >= 80 ? 'HEALTHY' : ($healthScore >= 60 ? 'WARNING' : 'CRITICAL');

                $this->line("  Status: <fg={$this->getStatusColor($status)}>{$status}</> (Health: {$healthScore}%)");

                $healthResults[] = [
                    'cluster' => $cluster->name,
                    'status' => $status,
                    'health_score' => $healthScore,
                    'details' => $healthData,
                ];

            } catch (\Exception $e) {
                $this->error("  ERROR: {$e->getMessage()}");
                Log::error("Health check failed for cluster {$cluster->name}: ".$e->getMessage());

                $healthResults[] = [
                    'cluster' => $cluster->name,
                    'status' => 'ERROR',
                    'error' => $e->getMessage(),
                ];
            }
        }

        // Display summary table
        $this->newLine();
        $this->info('Health Check Summary:');

        $tableData = collect($healthResults)->map(function ($result) {
            return [
                'Cluster' => $result['cluster'],
                'Status' => $result['status'],
                'Health Score' => isset($result['health_score']) ? $result['health_score'].'%' : 'N/A',
                'Nodes Online' => isset($result['details']['nodes_status']['online_nodes'])
                    ? $result['details']['nodes_status']['online_nodes'].'/'.$result['details']['nodes_status']['total_nodes']
                    : 'N/A',
                'Running VMs' => isset($result['details']['vms_status']['running_vms'])
                    ? $result['details']['vms_status']['running_vms']
                    : 'N/A',
                'Running Containers' => isset($result['details']['containers_status']['running_containers'])
                    ? $result['details']['containers_status']['running_containers']
                    : 'N/A',
            ];
        })->toArray();

        $this->table([
            'Cluster', 'Status', 'Health Score', 'Nodes Online', 'Running VMs', 'Running Containers',
        ], $tableData);

        // Count results
        $healthy = collect($healthResults)->where('status', 'HEALTHY')->count();
        $warning = collect($healthResults)->where('status', 'WARNING')->count();
        $critical = collect($healthResults)->where('status', 'CRITICAL')->count();
        $errors = collect($healthResults)->where('status', 'ERROR')->count();

        $this->newLine();
        $this->info("Summary: {$healthy} healthy, {$warning} warning, {$critical} critical, {$errors} errors");

        if ($critical > 0 || $errors > 0) {
            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    private function getStatusColor(string $status): string
    {
        return match ($status) {
            'HEALTHY' => 'green',
            'WARNING' => 'yellow',
            'CRITICAL' => 'red',
            'ERROR' => 'red',
            default => 'gray',
        };
    }
}
