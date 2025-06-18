<?php

namespace App\Console\Commands;

use App\Models\DevelopmentEnvironment;
use App\Services\ProxmoxLifecycleService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ProxmoxCleanupExpiredCommand extends Command
{
    protected $signature = 'proxmox:cleanup-expired {--dry-run : Show what would be cleaned up without actually doing it}';

    protected $description = 'Cleanup expired development environments';

    public function handle()
    {
        $isDryRun = $this->option('dry-run');

        if ($isDryRun) {
            $this->info('Running in dry-run mode - no actual cleanup will be performed.');
        }

        // Find environments that should be auto-destroyed
        $expiredEnvironments = DevelopmentEnvironment::shouldAutoDestroy()->get();

        if ($expiredEnvironments->isEmpty()) {
            $this->info('No expired environments found.');

            return self::SUCCESS;
        }

        $this->info("Found {$expiredEnvironments->count()} expired environment(s) for cleanup:");

        $cleanupResults = [];

        foreach ($expiredEnvironments as $environment) {
            $expiryInfo = $environment->expires_at
                ? "expired at {$environment->expires_at->format('Y-m-d H:i:s')}"
                : "auto-destroy after {$environment->auto_destroy_hours} hours";

            $this->line("  - {$environment->name} ({$expiryInfo})");

            if (! $isDryRun) {
                try {
                    $this->info("    Destroying environment: {$environment->name}");

                    // Update status to destroying
                    $environment->update(['status' => 'destroying']);

                    $lifecycleService = new ProxmoxLifecycleService($environment->cluster);

                    // Stop and delete all VMs
                    foreach ($environment->virtualMachines as $vm) {
                        $this->line("    Deleting VM: {$vm->name}");
                        $lifecycleService->deleteVirtualMachine($vm, true);
                    }

                    // Stop and delete all containers
                    foreach ($environment->containers as $container) {
                        $this->line("    Deleting container: {$container->name}");
                        $lifecycleService->deleteContainer($container, true);
                    }

                    // Delete the environment record
                    $environment->delete();

                    $this->info("    ✓ Successfully destroyed environment: {$environment->name}");

                    $cleanupResults[] = [
                        'environment' => $environment->name,
                        'status' => 'SUCCESS',
                        'details' => 'Environment successfully destroyed',
                    ];

                } catch (\Exception $e) {
                    $this->error("    ✗ Failed to destroy environment {$environment->name}: {$e->getMessage()}");
                    Log::error("Failed to cleanup expired environment {$environment->name}: ".$e->getMessage());

                    // Reset status on failure
                    $environment->update([
                        'status' => 'failed',
                        'last_error' => "Cleanup failed: {$e->getMessage()}",
                    ]);

                    $cleanupResults[] = [
                        'environment' => $environment->name,
                        'status' => 'FAILED',
                        'details' => $e->getMessage(),
                    ];
                }
            } else {
                $cleanupResults[] = [
                    'environment' => $environment->name,
                    'status' => 'DRY_RUN',
                    'details' => 'Would be destroyed',
                ];
            }
        }

        // Display cleanup summary
        if (! empty($cleanupResults)) {
            $this->newLine();
            $this->info('Cleanup Summary:');

            $tableData = collect($cleanupResults)->map(function ($result) {
                return [
                    'Environment' => $result['environment'],
                    'Status' => $result['status'],
                    'Details' => $result['details'],
                ];
            })->toArray();

            $this->table(['Environment', 'Status', 'Details'], $tableData);

            if (! $isDryRun) {
                $successful = collect($cleanupResults)->where('status', 'SUCCESS')->count();
                $failed = collect($cleanupResults)->where('status', 'FAILED')->count();

                $this->newLine();
                $this->info("Cleanup completed: {$successful} successful, {$failed} failed");

                if ($failed > 0) {
                    $this->warn('Some environments failed to be destroyed. Check logs for details.');

                    return self::FAILURE;
                }
            }
        }

        return self::SUCCESS;
    }
}
