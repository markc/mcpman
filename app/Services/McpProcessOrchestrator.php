<?php

namespace App\Services;

use App\Events\McpProcessError;
use App\Events\McpProcessStarted;
use App\Events\McpProcessStopped;
use App\Models\McpProcessStatus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Redis;

class McpProcessOrchestrator
{
    private array $managedProcesses = [];

    private array $healthCheckIntervals = [];

    private bool $initialized = false;

    public function __construct()
    {
        // Defer initialization until first use to avoid database queries during testing setup
    }

    /**
     * Lazy initialization to defer database queries until needed
     */
    private function ensureInitialized(): void
    {
        if ($this->initialized) {
            return;
        }

        try {
            $this->loadPersistedProcesses();
            $this->initializeRedisTracking();
            $this->initialized = true;
        } catch (\Exception $e) {
            Log::warning('Failed to initialize McpProcessOrchestrator', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Initialize Redis-based process tracking for fast lookups
     */
    private function initializeRedisTracking(): void
    {
        try {
            // Set up Redis keys for process tracking
            $runningProcesses = McpProcessStatus::where('status', 'running')->get();

            foreach ($runningProcesses as $process) {
                $this->cacheProcessStatus($process->process_name, [
                    'status' => $process->status,
                    'pid' => $process->pid,
                    'started_at' => $process->started_at->toISOString(),
                    'last_health_check' => $process->last_health_check?->toISOString(),
                ]);
            }
        } catch (\Exception $e) {
            Log::warning('Redis initialization failed, continuing without cache', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Cache process status in Redis for fast access (graceful fallback)
     */
    private function cacheProcessStatus(string $processName, array $data): void
    {
        try {
            if ($this->isRedisAvailable()) {
                Cache::put("mcp_process:{$processName}", $data, 300); // 5-minute TTL
                Redis::sadd('mcp_processes:active', $processName);
            }
        } catch (\Exception $e) {
            Log::debug('Redis caching failed, continuing without cache', [
                'process' => $processName,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Remove process from Redis cache (graceful fallback)
     */
    private function uncacheProcessStatus(string $processName): void
    {
        try {
            if ($this->isRedisAvailable()) {
                Cache::forget("mcp_process:{$processName}");
                Redis::srem('mcp_processes:active', $processName);
            }
        } catch (\Exception $e) {
            Log::debug('Redis uncaching failed, continuing without cache', [
                'process' => $processName,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Check if Redis is available and working
     */
    private function isRedisAvailable(): bool
    {
        try {
            if (! extension_loaded('redis')) {
                return false;
            }

            // Quick Redis connectivity test
            Redis::ping();

            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Start an MCP process with full lifecycle management
     */
    public function startProcess(string $processName, array $command, array $options = []): array
    {
        $this->ensureInitialized();

        try {
            Log::info('Starting MCP process', ['name' => $processName, 'command' => $command]);

            // Check if process is already running
            if ($this->isProcessRunning($processName)) {
                return [
                    'success' => false,
                    'message' => "Process '{$processName}' is already running",
                    'process_name' => $processName,
                ];
            }

            // Create process status record
            $status = McpProcessStatus::create([
                'process_name' => $processName,
                'command' => json_encode($command),
                'status' => 'starting',
                'started_at' => now(),
                'options' => json_encode($options),
                'restart_count' => 0,
            ]);

            // Start the process using a reliable method
            $result = $this->executeProcessStart($command, $options);

            if ($result['success']) {
                // Update status with PID
                $status->update([
                    'pid' => $result['pid'],
                    'status' => 'running',
                    'last_health_check' => now(),
                ]);

                // Track in memory
                $this->managedProcesses[$processName] = [
                    'status' => $status,
                    'pid' => $result['pid'],
                    'started_at' => now(),
                    'command' => $command,
                ];

                // Cache in Redis for fast access
                $this->cacheProcessStatus($processName, [
                    'status' => 'running',
                    'pid' => $result['pid'],
                    'started_at' => now()->toISOString(),
                    'last_health_check' => now()->toISOString(),
                ]);

                // Start health monitoring
                $this->startHealthMonitoring($processName);

                // Fire event
                Event::dispatch(new McpProcessStarted($processName, $result['pid'], $command));

                Log::info('MCP process started successfully', [
                    'name' => $processName,
                    'pid' => $result['pid'],
                ]);

                return [
                    'success' => true,
                    'message' => "Process '{$processName}' started successfully",
                    'process_name' => $processName,
                    'pid' => $result['pid'],
                ];
            } else {
                // Update status to failed
                $status->update([
                    'status' => 'failed',
                    'error_log' => $result['error'],
                ]);

                Event::dispatch(new McpProcessError($processName, $result['error']));

                return [
                    'success' => false,
                    'message' => $result['error'],
                    'process_name' => $processName,
                ];
            }

        } catch (\Exception $e) {
            Log::error('Failed to start MCP process', [
                'name' => $processName,
                'error' => $e->getMessage(),
            ]);

            Event::dispatch(new McpProcessError($processName, $e->getMessage()));

            return [
                'success' => false,
                'message' => "Exception: {$e->getMessage()}",
                'process_name' => $processName,
            ];
        }
    }

    /**
     * Stop an MCP process gracefully
     */
    public function stopProcess(string $processName): array
    {
        $this->ensureInitialized();

        try {
            Log::info('Stopping MCP process', ['name' => $processName]);

            $status = McpProcessStatus::where('process_name', $processName)
                ->where('status', 'running')
                ->first();

            if (! $status) {
                return [
                    'success' => false,
                    'message' => "Process '{$processName}' is not running",
                    'process_name' => $processName,
                ];
            }

            // Try graceful shutdown first
            $stopped = $this->gracefulShutdown($status->pid);

            if (! $stopped) {
                // Force kill if graceful shutdown failed
                $stopped = $this->forceKill($status->pid);
            }

            if ($stopped) {
                // Update status
                $status->update([
                    'status' => 'stopped',
                    'stopped_at' => now(),
                    'pid' => null,
                ]);

                // Remove from memory tracking
                unset($this->managedProcesses[$processName]);
                unset($this->healthCheckIntervals[$processName]);

                // Remove from Redis cache
                $this->uncacheProcessStatus($processName);

                // Fire event
                Event::dispatch(new McpProcessStopped($processName, $status->pid));

                Log::info('MCP process stopped successfully', ['name' => $processName]);

                return [
                    'success' => true,
                    'message' => "Process '{$processName}' stopped successfully",
                    'process_name' => $processName,
                ];
            } else {
                return [
                    'success' => false,
                    'message' => "Failed to stop process '{$processName}'",
                    'process_name' => $processName,
                ];
            }

        } catch (\Exception $e) {
            Log::error('Failed to stop MCP process', [
                'name' => $processName,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => "Exception: {$e->getMessage()}",
                'process_name' => $processName,
            ];
        }
    }

    /**
     * Check if a process is running (Redis-cached for performance)
     */
    public function isProcessRunning(string $processName): bool
    {
        $this->ensureInitialized();

        // Try Redis cache first for fast lookup (if available)
        if ($this->isRedisAvailable()) {
            try {
                $cached = Cache::get("mcp_process:{$processName}");
                if ($cached && $cached['status'] === 'running') {
                    // Quick PID verification
                    if ($this->verifyProcessExists($cached['pid'])) {
                        return true;
                    } else {
                        // Process died, update cache and database
                        $this->uncacheProcessStatus($processName);
                        McpProcessStatus::where('process_name', $processName)
                            ->where('status', 'running')
                            ->update(['status' => 'died', 'stopped_at' => now()]);

                        return false;
                    }
                }
            } catch (\Exception $e) {
                Log::debug('Redis lookup failed, falling back to database', ['process' => $processName]);
            }
        }

        // Fallback to database lookup
        $status = McpProcessStatus::where('process_name', $processName)
            ->where('status', 'running')
            ->first();

        if (! $status || ! $status->pid) {
            return false;
        }

        // Verify the process is actually running
        $isRunning = $this->verifyProcessExists($status->pid);

        if ($isRunning) {
            // Update cache with current status
            $this->cacheProcessStatus($processName, [
                'status' => 'running',
                'pid' => $status->pid,
                'started_at' => $status->started_at->toISOString(),
                'last_health_check' => $status->last_health_check?->toISOString(),
            ]);
        }

        return $isRunning;
    }

    /**
     * Get detailed process status
     */
    public function getProcessStatus(string $processName): array
    {
        $this->ensureInitialized();

        $status = McpProcessStatus::where('process_name', $processName)
            ->latest()
            ->first();

        if (! $status) {
            return [
                'name' => $processName,
                'status' => 'not_found',
                'running' => false,
            ];
        }

        $isRunning = $status->status === 'running' && $this->verifyProcessExists($status->pid);

        return [
            'name' => $processName,
            'status' => $status->status,
            'running' => $isRunning,
            'pid' => $status->pid,
            'started_at' => $status->started_at,
            'last_health_check' => $status->last_health_check,
            'restart_count' => $status->restart_count,
            'command' => json_decode($status->command, true),
            'uptime' => $isRunning ? $status->started_at->diffForHumans() : null,
        ];
    }

    /**
     * Get all managed processes status
     */
    public function getAllProcessesStatus(): array
    {
        $this->ensureInitialized();

        $statuses = McpProcessStatus::where('status', '!=', 'stopped')
            ->get()
            ->keyBy('process_name');

        $result = [];
        foreach ($statuses as $name => $status) {
            $result[$name] = $this->getProcessStatus($name);
        }

        return $result;
    }

    /**
     * Restart a process
     */
    public function restartProcess(string $processName): array
    {
        $this->ensureInitialized();

        $stopResult = $this->stopProcess($processName);

        if (! $stopResult['success']) {
            return $stopResult;
        }

        // Wait a moment for clean shutdown
        sleep(1);

        // Get the original command and restart
        $status = McpProcessStatus::where('process_name', $processName)
            ->latest()
            ->first();

        if ($status) {
            $command = json_decode($status->command, true);
            $options = json_decode($status->options, true) ?? [];

            // Increment restart count
            $status->increment('restart_count');

            return $this->startProcess($processName, $command, $options);
        }

        return [
            'success' => false,
            'message' => "Cannot restart '{$processName}' - no previous configuration found",
            'process_name' => $processName,
        ];
    }

    /**
     * Execute process start using the most reliable method with systemd support
     */
    private function executeProcessStart(array $command, array $options): array
    {
        $useSystemd = $options['use_systemd'] ?? false;

        if ($useSystemd) {
            return $this->executeWithSystemd($command, $options);
        }

        try {
            // Create a temporary script for reliable process spawning
            $scriptFile = storage_path('app/process-scripts/start-'.uniqid().'.sh');
            $scriptDir = dirname($scriptFile);

            if (! is_dir($scriptDir)) {
                mkdir($scriptDir, 0755, true);
            }

            // Build command
            $cmdString = implode(' ', array_map('escapeshellarg', $command));
            $workDir = $options['working_directory'] ?? base_path();

            $scriptContent = <<<SCRIPT
#!/bin/bash
cd "{$workDir}"
exec {$cmdString} > /dev/null 2>&1 &
echo \$!
SCRIPT;

            file_put_contents($scriptFile, $scriptContent);
            chmod($scriptFile, 0755);

            // Execute script and capture PID
            $pid = trim(shell_exec("bash {$scriptFile}"));

            // Clean up script
            unlink($scriptFile);

            if (! empty($pid) && is_numeric($pid)) {
                // Verify process started
                sleep(1);
                if ($this->verifyProcessExists((int) $pid)) {
                    return [
                        'success' => true,
                        'pid' => (int) $pid,
                    ];
                } else {
                    return [
                        'success' => false,
                        'error' => 'Process failed to start or died immediately',
                    ];
                }
            } else {
                return [
                    'success' => false,
                    'error' => 'Failed to capture process PID',
                ];
            }

        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Execute process using systemd user service
     */
    private function executeWithSystemd(array $command, array $options): array
    {
        try {
            $serviceName = 'mcp-'.($options['service_name'] ?? uniqid());
            $workingDir = $options['working_directory'] ?? base_path();
            $user = get_current_user();

            // Create systemd service file content
            $serviceContent = "[Unit]\n";
            $serviceContent .= "Description=MCP Process Service\n";
            $serviceContent .= "After=network.target\n\n";
            $serviceContent .= "[Service]\n";
            $serviceContent .= "Type=simple\n";
            $serviceContent .= "User={$user}\n";
            $serviceContent .= "WorkingDirectory={$workingDir}\n";
            $serviceContent .= 'ExecStart='.implode(' ', $command)."\n";
            $serviceContent .= "Restart=on-failure\n";
            $serviceContent .= "RestartSec=5\n";
            $serviceContent .= "StandardOutput=journal\n";
            $serviceContent .= "StandardError=journal\n\n";
            $serviceContent .= "[Install]\n";
            $serviceContent .= "WantedBy=default.target\n";

            // Write service file to user systemd directory
            $userSystemdDir = storage_path('app/systemd');
            if (! is_dir($userSystemdDir)) {
                mkdir($userSystemdDir, 0755, true);
            }

            $serviceFile = $userSystemdDir."/{$serviceName}.service";
            file_put_contents($serviceFile, $serviceContent);

            // Start the service
            $output = [];
            $exitCode = 0;

            exec('systemctl --user daemon-reload 2>&1', $output, $exitCode);
            exec('systemctl --user start '.escapeshellarg($serviceName).' 2>&1', $output, $exitCode);

            if ($exitCode !== 0) {
                return [
                    'success' => false,
                    'error' => 'Systemd service start failed: '.implode(', ', $output),
                ];
            }

            // Get the main PID from systemd
            $pidOutput = [];
            exec('systemctl --user show --property=MainPID '.escapeshellarg($serviceName), $pidOutput);

            $pid = null;
            foreach ($pidOutput as $line) {
                if (strpos($line, 'MainPID=') === 0) {
                    $pid = (int) substr($line, 8);
                    break;
                }
            }

            return [
                'success' => true,
                'pid' => $pid,
                'service_name' => $serviceName,
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => 'Systemd execution failed: '.$e->getMessage(),
            ];
        }
    }

    /**
     * Verify if a process exists by PID
     */
    private function verifyProcessExists(?int $pid): bool
    {
        if (! $pid) {
            return false;
        }

        return posix_getpgid($pid) !== false;
    }

    /**
     * Graceful shutdown attempt
     */
    private function gracefulShutdown(int $pid): bool
    {
        if (! $this->verifyProcessExists($pid)) {
            return true; // Already stopped
        }

        // Send SIGTERM
        posix_kill($pid, SIGTERM);

        // Wait up to 5 seconds for graceful shutdown
        for ($i = 0; $i < 10; $i++) {
            usleep(500000); // 0.5 seconds
            if (! $this->verifyProcessExists($pid)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Force kill process
     */
    private function forceKill(int $pid): bool
    {
        if (! $this->verifyProcessExists($pid)) {
            return true; // Already stopped
        }

        // Send SIGKILL
        posix_kill($pid, SIGKILL);

        // Wait a moment and verify
        sleep(1);

        return ! $this->verifyProcessExists($pid);
    }

    /**
     * Start health monitoring for a process
     */
    private function startHealthMonitoring(string $processName): void
    {
        // Health monitoring will be implemented via queue jobs
        // This method sets up the monitoring schedule
        Log::info('Health monitoring started for process', ['name' => $processName]);
    }

    /**
     * Load persisted processes from database
     */
    private function loadPersistedProcesses(): void
    {
        $runningProcesses = McpProcessStatus::where('status', 'running')->get();

        foreach ($runningProcesses as $status) {
            // Verify process is actually running
            if ($this->verifyProcessExists($status->pid)) {
                $this->managedProcesses[$status->process_name] = [
                    'status' => $status,
                    'pid' => $status->pid,
                    'started_at' => $status->started_at,
                    'command' => json_decode($status->command, true),
                ];
            } else {
                // Process died, update status
                $status->update(['status' => 'died', 'stopped_at' => now()]);
            }
        }
    }
}
