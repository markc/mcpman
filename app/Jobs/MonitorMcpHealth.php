<?php

namespace App\Jobs;

use App\Events\McpProcessError;
use App\Models\McpProcessStatus;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class MonitorMcpHealth implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public string $processName;

    public function __construct(string $processName)
    {
        $this->processName = $processName;
    }

    public function handle(\App\Services\McpHealthMonitorService $healthMonitor): void
    {
        try {
            $processStatus = McpProcessStatus::where('process_name', $this->processName)
                ->where('status', 'running')
                ->first();

            if (! $processStatus) {
                Log::debug('Process not found or not running, skipping health check', [
                    'process_name' => $this->processName,
                ]);

                return;
            }

            // Skip if health check was done recently
            if (! $processStatus->needsHealthCheck()) {
                return;
            }

            Log::debug('Performing comprehensive health check', [
                'process_name' => $this->processName,
                'pid' => $processStatus->pid,
            ]);

            // Perform multi-layer health check
            $healthResult = $healthMonitor->performSingleHealthCheck($processStatus);

            Log::info('Health check completed', [
                'process_name' => $this->processName,
                'health_score' => $healthResult['score'] ?? 0,
                'overall_healthy' => $healthResult['overall_healthy'] ?? false,
            ]);

        } catch (\Exception $e) {
            Log::error('Health check failed', [
                'process_name' => $this->processName,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            broadcast(new McpProcessError(
                $this->processName,
                'Health check failed: '.$e->getMessage()
            ));
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('MonitorMcpHealth job failed', [
            'process_name' => $this->processName,
            'error' => $exception->getMessage(),
        ]);
    }
}
