<?php

namespace App\Services;

use App\Events\McpProcessError;
use App\Events\McpProcessHealthCheck;
use App\Jobs\MonitorMcpHealth;
use App\Jobs\StartMcpProcess;
use App\Models\McpProcessStatus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class McpHealthMonitorService
{
    private McpProcessOrchestrator $orchestrator;

    public function __construct(McpProcessOrchestrator $orchestrator)
    {
        $this->orchestrator = $orchestrator;
    }

    /**
     * Perform comprehensive health check on all running processes
     */
    public function performHealthChecks(): array
    {
        $results = [];
        $runningProcesses = McpProcessStatus::where('status', 'running')->get();

        foreach ($runningProcesses as $process) {
            $results[$process->process_name] = $this->performSingleHealthCheck($process);
        }

        return $results;
    }

    /**
     * Perform detailed health check on a single process
     */
    public function performSingleHealthCheck(McpProcessStatus $process): array
    {
        try {
            Log::debug('Performing health check', ['process' => $process->process_name]);

            $healthData = [
                'process_name' => $process->process_name,
                'timestamp' => now()->toISOString(),
                'checks' => [],
            ];

            // Layer 1: PID existence check
            $pidExists = $this->checkPidExists($process->pid);
            $healthData['checks']['pid_exists'] = $pidExists;

            // Layer 2: Process responsiveness check
            $isResponsive = $this->checkProcessResponsiveness($process);
            $healthData['checks']['responsive'] = $isResponsive;

            // Layer 3: Resource usage check
            $resourceUsage = $this->checkResourceUsage($process);
            $healthData['checks']['resource_usage'] = $resourceUsage;

            // Layer 4: Application-specific health check
            $appHealth = $this->checkApplicationHealth($process);
            $healthData['checks']['application_health'] = $appHealth;

            // Determine overall health
            $overallHealth = $pidExists && $isResponsive &&
                           $resourceUsage['healthy'] && $appHealth['healthy'];

            $healthData['overall_healthy'] = $overallHealth;
            $healthData['score'] = $this->calculateHealthScore($healthData['checks']);

            // Update process health metrics
            $this->updateProcessHealth($process, $healthData);

            // Handle unhealthy processes
            if (! $overallHealth) {
                $this->handleUnhealthyProcess($process, $healthData);
            }

            // Broadcast health check event
            broadcast(new McpProcessHealthCheck(
                $process->process_name,
                $overallHealth,
                $healthData
            ));

            return $healthData;

        } catch (\Exception $e) {
            Log::error('Health check failed', [
                'process' => $process->process_name,
                'error' => $e->getMessage(),
            ]);

            broadcast(new McpProcessError(
                $process->process_name,
                'Health check failed: '.$e->getMessage()
            ));

            return [
                'process_name' => $process->process_name,
                'timestamp' => now()->toISOString(),
                'overall_healthy' => false,
                'error' => $e->getMessage(),
                'score' => 0,
            ];
        }
    }

    /**
     * Layer 1: Check if process PID exists
     */
    private function checkPidExists(?int $pid): bool
    {
        if (! $pid) {
            return false;
        }

        return posix_getpgid($pid) !== false;
    }

    /**
     * Layer 2: Check process responsiveness
     */
    private function checkProcessResponsiveness(McpProcessStatus $process): bool
    {
        try {
            // Check if process is consuming CPU (indicating activity)
            $resourceUsage = $process->getResourceUsage();

            // If we have previous CPU time, check if it's increasing
            $previousCpu = Cache::get("process_cpu:{$process->process_name}", 0);
            $currentCpu = $resourceUsage['cpu_time'] ?? 0;

            // Store current CPU time for next check
            Cache::put("process_cpu:{$process->process_name}", $currentCpu, 300);

            // Process is responsive if CPU time has increased or if it's a new check
            return $currentCpu > $previousCpu || $previousCpu === 0;

        } catch (\Exception $e) {
            Log::debug('Responsiveness check failed', [
                'process' => $process->process_name,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Layer 3: Check resource usage levels
     */
    private function checkResourceUsage(McpProcessStatus $process): array
    {
        try {
            $usage = $process->getResourceUsage();

            $memoryMb = $usage['memory_mb'] ?? 0;
            $threads = $usage['threads'] ?? 0;

            // Define resource thresholds
            $memoryThreshold = 512; // MB
            $threadThreshold = 50;

            $memoryHealthy = $memoryMb < $memoryThreshold;
            $threadsHealthy = $threads < $threadThreshold;

            return [
                'healthy' => $memoryHealthy && $threadsHealthy,
                'memory_mb' => $memoryMb,
                'memory_healthy' => $memoryHealthy,
                'threads' => $threads,
                'threads_healthy' => $threadsHealthy,
                'thresholds' => [
                    'memory_mb' => $memoryThreshold,
                    'threads' => $threadThreshold,
                ],
            ];

        } catch (\Exception $e) {
            return [
                'healthy' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Layer 4: Application-specific health checks
     */
    private function checkApplicationHealth(McpProcessStatus $process): array
    {
        try {
            // For log monitoring processes, check if log files are being processed
            if ($process->process_name === 'log-monitoring') {
                return $this->checkLogMonitoringHealth($process);
            }

            // For other processes, basic health check
            return [
                'healthy' => true,
                'type' => 'basic',
                'message' => 'No specific health checks defined',
            ];

        } catch (\Exception $e) {
            return [
                'healthy' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Specific health check for log monitoring processes
     */
    private function checkLogMonitoringHealth(McpProcessStatus $process): array
    {
        try {
            $logFile = storage_path('logs/laravel.log');

            if (! file_exists($logFile)) {
                return [
                    'healthy' => false,
                    'type' => 'log_monitoring',
                    'message' => 'Log file not found',
                ];
            }

            $lastModified = filemtime($logFile);
            $minutesSinceModified = (time() - $lastModified) / 60;

            // Log file should be updated within last 60 minutes for active monitoring
            $healthy = $minutesSinceModified < 60;

            return [
                'healthy' => $healthy,
                'type' => 'log_monitoring',
                'log_file_size' => filesize($logFile),
                'last_modified_minutes_ago' => round($minutesSinceModified, 1),
                'message' => $healthy ? 'Log monitoring active' : 'Log file not recently updated',
            ];

        } catch (\Exception $e) {
            return [
                'healthy' => false,
                'type' => 'log_monitoring',
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Calculate overall health score (0-100)
     */
    private function calculateHealthScore(array $checks): int
    {
        $totalChecks = 0;
        $passedChecks = 0;

        foreach ($checks as $check) {
            if (is_bool($check)) {
                $totalChecks++;
                if ($check) {
                    $passedChecks++;
                }
            } elseif (is_array($check) && isset($check['healthy'])) {
                $totalChecks++;
                if ($check['healthy']) {
                    $passedChecks++;
                }
            }
        }

        return $totalChecks > 0 ? round(($passedChecks / $totalChecks) * 100) : 0;
    }

    /**
     * Update process health metrics in database
     */
    private function updateProcessHealth(McpProcessStatus $process, array $healthData): void
    {
        $process->update([
            'last_health_check' => now(),
            'metrics' => array_merge($process->metrics ?? [], [
                'last_health_score' => $healthData['score'],
                'last_health_timestamp' => $healthData['timestamp'],
                'health_checks' => $healthData['checks'],
            ]),
        ]);
    }

    /**
     * Handle unhealthy processes with smart recovery
     */
    private function handleUnhealthyProcess(McpProcessStatus $process, array $healthData): void
    {
        Log::warning('Unhealthy process detected', [
            'process' => $process->process_name,
            'health_score' => $healthData['score'],
            'checks' => $healthData['checks'],
        ]);

        $restartThreshold = 30; // Restart if health score below 30%
        $maxRestarts = 3; // Maximum restarts per hour

        if ($healthData['score'] < $restartThreshold) {
            $recentRestarts = $this->countRecentRestarts($process->process_name);

            if ($recentRestarts < $maxRestarts) {
                Log::info('Attempting automatic restart', [
                    'process' => $process->process_name,
                    'recent_restarts' => $recentRestarts,
                ]);

                $this->attemptAutoRestart($process);
            } else {
                Log::error('Max restart limit reached', [
                    'process' => $process->process_name,
                    'recent_restarts' => $recentRestarts,
                ]);

                broadcast(new McpProcessError(
                    $process->process_name,
                    'Process unhealthy and max restart limit reached'
                ));
            }
        }
    }

    /**
     * Count recent restarts for a process
     */
    private function countRecentRestarts(string $processName): int
    {
        $cacheKey = "restart_count:{$processName}";
        $restarts = Cache::get($cacheKey, []);

        // Filter restarts from last hour
        $oneHourAgo = now()->subHour();
        $recentRestarts = collect($restarts)->filter(function ($timestamp) use ($oneHourAgo) {
            return \Carbon\Carbon::parse($timestamp)->isAfter($oneHourAgo);
        });

        return $recentRestarts->count();
    }

    /**
     * Attempt automatic restart of unhealthy process
     */
    private function attemptAutoRestart(McpProcessStatus $process): void
    {
        try {
            // Record restart attempt
            $cacheKey = "restart_count:{$process->process_name}";
            $restarts = Cache::get($cacheKey, []);
            $restarts[] = now()->toISOString();
            Cache::put($cacheKey, $restarts, 3600); // Keep for 1 hour

            // Get original command and options for restart
            $command = $process->command;
            $options = $process->options ?? [];

            // Stop the current process
            $this->orchestrator->stopProcess($process->process_name);

            // Wait a moment for clean shutdown
            sleep(2);

            // Start the process again via background job
            StartMcpProcess::dispatch($process->process_name, $command, $options);

            Log::info('Auto-restart initiated', ['process' => $process->process_name]);

        } catch (\Exception $e) {
            Log::error('Auto-restart failed', [
                'process' => $process->process_name,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Schedule health monitoring for all running processes
     */
    public function scheduleHealthMonitoring(): void
    {
        $runningProcesses = McpProcessStatus::where('status', 'running')->get();

        foreach ($runningProcesses as $process) {
            if ($process->needsHealthCheck()) {
                MonitorMcpHealth::dispatch($process->process_name);
            }
        }
    }
}
