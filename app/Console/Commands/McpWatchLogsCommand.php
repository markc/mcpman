<?php

namespace App\Console\Commands;

use App\Services\BidirectionalMcpClient;
use App\Services\LogMonitoringService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class McpWatchLogsCommand extends Command
{
    protected $signature = 'mcp:watch-logs {--filter= : Filter logs by pattern} {--level= : Filter by log level} {--notify : Send notifications for errors}';

    protected $description = 'Watch application logs and optionally send MCP notifications';

    private LogMonitoringService $logService;

    private BidirectionalMcpClient $mcpClient;

    public function __construct(LogMonitoringService $logService, BidirectionalMcpClient $mcpClient)
    {
        parent::__construct();
        $this->logService = $logService;
        $this->mcpClient = $mcpClient;
    }

    public function handle(): int
    {
        $filter = $this->option('filter');
        $level = $this->option('level');
        $notify = $this->option('notify');

        $this->info('Starting MCP log monitoring...');

        if ($filter) {
            $this->info("Filter: {$filter}");
        }

        if ($level) {
            $this->info("Level: {$level}");
        }

        if ($notify) {
            $this->info('Notifications: Enabled');
        }

        $this->info('Press Ctrl+C to stop monitoring');
        $this->newLine();

        // Start monitoring
        return $this->startMonitoring($filter, $level, $notify);
    }

    private function startMonitoring(?string $filter, ?string $level, bool $notify): int
    {
        $lastPosition = $this->getLastLogPosition();

        while (true) {
            try {
                $newEntries = $this->logService->getNewLogEntries($lastPosition, $filter, $level);

                foreach ($newEntries as $entry) {
                    $this->displayLogEntry($entry);

                    // Send notifications for errors if enabled
                    if ($notify && $this->shouldNotify($entry)) {
                        $this->sendNotification($entry);
                    }

                    $lastPosition = $entry['position'] ?? $lastPosition;
                }

                // Check if we should stop
                if ($this->shouldStop()) {
                    break;
                }

                // Wait before next check
                usleep(100000); // 100ms

            } catch (\Exception $e) {
                $this->error("Error monitoring logs: {$e->getMessage()}");
                Log::error('MCP log monitoring error', [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);

                // Wait a bit longer on error
                sleep(1);
            }
        }

        $this->info('Log monitoring stopped');

        return 0;
    }

    private function getLastLogPosition(): int
    {
        $logFile = storage_path('logs/laravel.log');

        if (! file_exists($logFile)) {
            return 0;
        }

        return filesize($logFile);
    }

    private function displayLogEntry(array $entry): void
    {
        $timestamp = $entry['timestamp'] ?? now()->format('Y-m-d H:i:s');
        $level = strtoupper($entry['level'] ?? 'INFO');
        $message = $entry['message'] ?? '';
        $context = $entry['context'] ?? [];

        // Color coding for levels
        $levelColor = match ($level) {
            'ERROR', 'CRITICAL', 'ALERT', 'EMERGENCY' => 'red',
            'WARNING' => 'yellow',
            'INFO' => 'green',
            'DEBUG' => 'blue',
            default => 'white'
        };

        $this->line(sprintf(
            '[%s] <fg=%s>%s</> %s',
            $timestamp,
            $levelColor,
            $level,
            $message
        ));

        // Display context if present and not too large
        if (! empty($context) && count($context) < 10) {
            foreach ($context as $key => $value) {
                if (is_scalar($value) || is_null($value)) {
                    $this->line("  <fg=gray>{$key}:</> ".json_encode($value));
                }
            }
        }
    }

    private function shouldNotify(array $entry): bool
    {
        $level = strtolower($entry['level'] ?? '');

        // Notify on errors, criticals, and specific patterns
        if (in_array($level, ['error', 'critical', 'alert', 'emergency'])) {
            return true;
        }

        // Check for MCP-related issues
        $message = strtolower($entry['message'] ?? '');
        $mcpKeywords = ['mcp', 'connection', 'timeout', 'failed', 'exception'];

        foreach ($mcpKeywords as $keyword) {
            if (strpos($message, $keyword) !== false) {
                return true;
            }
        }

        return false;
    }

    private function sendNotification(array $entry): void
    {
        try {
            $this->mcpClient->notifyError([
                'type' => 'log_error',
                'timestamp' => $entry['timestamp'] ?? now()->toISOString(),
                'level' => $entry['level'] ?? 'unknown',
                'message' => $entry['message'] ?? '',
                'context' => $entry['context'] ?? [],
                'source' => 'log_monitoring',
            ]);

            $this->line('  <fg=blue>→ Notification sent</>');

        } catch (\Exception $e) {
            $this->line("  <fg=red>→ Failed to send notification: {$e->getMessage()}</>");
        }
    }

    private function shouldStop(): bool
    {
        // Check for Ctrl+C or termination signal
        if (function_exists('pcntl_signal_dispatch')) {
            pcntl_signal_dispatch();
        }

        return false; // For now, run until manually stopped
    }
}
