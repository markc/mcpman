<?php

namespace App\Services;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;

class ProcessManager
{
    private string $pidDirectory;

    public function __construct()
    {
        $this->pidDirectory = storage_path('app/pids');

        // Ensure PID directory exists
        if (! File::exists($this->pidDirectory)) {
            File::makeDirectory($this->pidDirectory, 0755, true);
        }
    }

    /**
     * Start a background process and track its PID
     */
    public function startProcess(string $name, array $command, ?string $workingDirectory = null): array
    {
        $pidFile = $this->getPidFile($name);

        // Check if process is already running
        if ($this->isProcessRunning($name)) {
            return [
                'success' => false,
                'message' => "Process '{$name}' is already running",
                'pid' => $this->getStoredPid($name),
            ];
        }

        try {
            // Build the command with nohup and background execution
            $commandString = 'nohup '.implode(' ', array_map('escapeshellarg', $command)).' > /dev/null 2>&1 & echo $!';

            // Execute from the correct working directory
            $workingDir = $workingDirectory ?? base_path();
            $pid = trim(shell_exec("cd {$workingDir} && {$commandString}"));

            if (empty($pid) || ! is_numeric($pid)) {
                throw new \Exception('Failed to capture process PID');
            }

            // Store the PID
            File::put($pidFile, $pid);

            // Give the process a moment to start
            sleep(1);

            // Verify the process is actually running
            if (! $this->isProcessRunningByPid((int) $pid)) {
                File::delete($pidFile);
                throw new \Exception('Process failed to start or died immediately');
            }

            Log::info("Process '{$name}' started successfully", [
                'pid' => $pid,
                'command' => $command,
                'working_directory' => $workingDir,
            ]);

            return [
                'success' => true,
                'message' => "Process '{$name}' started successfully",
                'pid' => (int) $pid,
            ];

        } catch (\Exception $e) {
            Log::error("Failed to start process '{$name}'", [
                'error' => $e->getMessage(),
                'command' => $command,
            ]);

            return [
                'success' => false,
                'message' => "Failed to start process '{$name}': ".$e->getMessage(),
                'pid' => null,
            ];
        }
    }

    /**
     * Stop a tracked process by name
     */
    public function stopProcess(string $name): array
    {
        $pidFile = $this->getPidFile($name);

        if (! File::exists($pidFile)) {
            return [
                'success' => false,
                'message' => "Process '{$name}' is not tracked (no PID file found)",
                'pid' => null,
            ];
        }

        $pid = $this->getStoredPid($name);

        if (! $pid) {
            File::delete($pidFile);

            return [
                'success' => false,
                'message' => "Invalid PID file for process '{$name}'",
                'pid' => null,
            ];
        }

        try {
            // Check if process is actually running
            if (! $this->isProcessRunningByPid($pid)) {
                File::delete($pidFile);

                return [
                    'success' => true,
                    'message' => "Process '{$name}' was not running (cleaned up stale PID file)",
                    'pid' => $pid,
                ];
            }

            // Kill the specific process
            $result = posix_kill($pid, SIGTERM);

            if (! $result) {
                // Try with SIGKILL if SIGTERM fails
                $result = posix_kill($pid, SIGKILL);
            }

            if ($result) {
                // Give it time to die
                sleep(1);

                // Verify it's stopped
                if (! $this->isProcessRunningByPid($pid)) {
                    File::delete($pidFile);

                    Log::info("Process '{$name}' stopped successfully", ['pid' => $pid]);

                    return [
                        'success' => true,
                        'message' => "Process '{$name}' stopped successfully",
                        'pid' => $pid,
                    ];
                } else {
                    throw new \Exception('Process did not terminate after kill signal');
                }
            } else {
                throw new \Exception('Failed to send kill signal to process');
            }

        } catch (\Exception $e) {
            Log::error("Failed to stop process '{$name}'", [
                'error' => $e->getMessage(),
                'pid' => $pid,
            ]);

            return [
                'success' => false,
                'message' => "Failed to stop process '{$name}': ".$e->getMessage(),
                'pid' => $pid,
            ];
        }
    }

    /**
     * Check if a named process is running
     */
    public function isProcessRunning(string $name): bool
    {
        $pid = $this->getStoredPid($name);

        return $pid && $this->isProcessRunningByPid($pid);
    }

    /**
     * Get process status information
     */
    public function getProcessStatus(string $name): array
    {
        $pidFile = $this->getPidFile($name);
        $pid = $this->getStoredPid($name);

        return [
            'name' => $name,
            'pid_file_exists' => File::exists($pidFile),
            'pid' => $pid,
            'is_running' => $pid ? $this->isProcessRunningByPid($pid) : false,
            'uptime' => $pid ? $this->getProcessUptime($pid) : null,
        ];
    }

    /**
     * List all tracked processes
     */
    public function listProcesses(): array
    {
        $processes = [];
        $pidFiles = File::glob($this->pidDirectory.'/*.pid');

        foreach ($pidFiles as $pidFile) {
            $name = basename($pidFile, '.pid');
            $processes[] = $this->getProcessStatus($name);
        }

        return $processes;
    }

    /**
     * Clean up stale PID files
     */
    public function cleanupStaleProcesses(): int
    {
        $cleaned = 0;
        $pidFiles = File::glob($this->pidDirectory.'/*.pid');

        foreach ($pidFiles as $pidFile) {
            $name = basename($pidFile, '.pid');
            $pid = $this->getStoredPid($name);

            if (! $pid || ! $this->isProcessRunningByPid($pid)) {
                File::delete($pidFile);
                $cleaned++;
                Log::info("Cleaned up stale PID file for process '{$name}'", ['pid' => $pid]);
            }
        }

        return $cleaned;
    }

    /**
     * Get the PID file path for a process name
     */
    private function getPidFile(string $name): string
    {
        return $this->pidDirectory.'/'.$name.'.pid';
    }

    /**
     * Get stored PID for a process name
     */
    private function getStoredPid(string $name): ?int
    {
        $pidFile = $this->getPidFile($name);

        if (! File::exists($pidFile)) {
            return null;
        }

        $pid = trim(File::get($pidFile));

        return is_numeric($pid) ? (int) $pid : null;
    }

    /**
     * Check if a process is running by PID
     */
    private function isProcessRunningByPid(int $pid): bool
    {
        return posix_getpgid($pid) !== false;
    }

    /**
     * Get process uptime in seconds
     */
    private function getProcessUptime(int $pid): ?int
    {
        try {
            $stat = file_get_contents("/proc/{$pid}/stat");
            if ($stat) {
                $parts = explode(' ', $stat);
                $startTime = (int) $parts[21]; // Process start time in clock ticks
                $clockTicks = (int) shell_exec('getconf CLK_TCK');
                $bootTime = (int) trim(shell_exec("grep btime /proc/stat | awk '{print $2}'"));

                $processStartTime = $bootTime + ($startTime / $clockTicks);

                return time() - (int) $processStartTime;
            }
        } catch (\Exception $e) {
            // Fall back to simple existence check
        }

        return null;
    }
}
