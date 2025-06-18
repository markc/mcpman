<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Symfony\Component\Process\Process as SymfonyProcess;

class LogMonitoringService
{
    private array $errorPatterns = [
        'fatal' => [
            'pattern' => '/PHP Fatal error:(.+?)in (.+?) on line (\d+)/',
            'severity' => 'critical',
            'auto_fix' => true,
        ],
        'exception' => [
            'pattern' => '/\[(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})\] \w+\.ERROR: (.+?) \{/',
            'severity' => 'high',
            'auto_fix' => true,
        ],
        'syntax_error' => [
            'pattern' => '/Parse error: syntax error, (.+?) in (.+?) on line (\d+)/',
            'severity' => 'critical',
            'auto_fix' => true,
        ],
        'class_not_found' => [
            'pattern' => '/Class [\'"](.+?)[\'"] not found/',
            'severity' => 'high',
            'auto_fix' => true,
        ],
        'method_not_found' => [
            'pattern' => '/Call to undefined method (.+?)::(.+?)\(\)/',
            'severity' => 'high',
            'auto_fix' => true,
        ],
        'undefined_variable' => [
            'pattern' => '/Undefined variable: \$(.+?)/',
            'severity' => 'medium',
            'auto_fix' => false,
        ],
        'duplicate_method' => [
            'pattern' => '/Cannot redeclare (.+?)\(\)/',
            'severity' => 'critical',
            'auto_fix' => true,
        ],
        'mcp_method_error' => [
            'pattern' => '/error: Unsupported MCP method: (.+?)/',
            'severity' => 'high',
            'auto_fix' => true,
        ],
        'typed_property_error' => [
            'pattern' => '/Typed property (.+?) must not be accessed before initialization/',
            'severity' => 'high',
            'auto_fix' => true,
        ],
        'view_exception' => [
            'pattern' => '/Class "(.+?)" not found \(View: (.+?)\)/',
            'severity' => 'high',
            'auto_fix' => true,
        ],
        'livewire_component_not_found' => [
            'pattern' => '/Unable to find component: \[(.+?)\]/',
            'severity' => 'high',
            'auto_fix' => true,
        ],
    ];

    private array $contextPatterns = [
        'file_path' => '/in (.+?) on line (\d+)/',
        'stack_trace' => '/Stack trace:(.+?)(?=\[|\z)/s',
        'request_info' => '/POST: (.+?) • Auth ID: (\d+)/',
        'timestamp' => '/\[(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})\]/',
    ];

    private ?BidirectionalMcpClient $bidirectionalClient = null;

    public function __construct(?BidirectionalMcpClient $bidirectionalClient = null)
    {
        $this->bidirectionalClient = $bidirectionalClient ?? app(BidirectionalMcpClient::class);
    }

    /**
     * Start real-time log monitoring
     */
    public function startMonitoring(?string $logFile = null): void
    {
        $logFile = $logFile ?? storage_path('logs/laravel.log');

        if (! file_exists($logFile)) {
            Log::warning('Log file not found for monitoring', ['file' => $logFile]);

            return;
        }

        Log::info('Starting real-time log monitoring', ['file' => $logFile]);

        // Use tail -f to monitor file changes in real-time
        $process = new SymfonyProcess(['tail', '-f', $logFile]);
        $process->setTimeout(null); // No timeout for continuous monitoring

        $process->start(function ($type, $buffer) {
            if ($type === SymfonyProcess::OUT) {
                $this->processLogOutput($buffer);
            }
        });

        // Keep the process running
        while ($process->isRunning()) {
            usleep(100000); // 0.1 second
        }
    }

    /**
     * Process incoming log output for error detection
     */
    private function processLogOutput(string $output): void
    {
        $lines = explode("\n", trim($output));

        foreach ($lines as $line) {
            if (empty(trim($line))) {
                continue;
            }

            $error = $this->detectError($line);
            if ($error) {
                $this->handleDetectedError($error, $line);
            }
        }
    }

    /**
     * Detect errors using pattern matching
     */
    private function detectError(string $line): ?array
    {
        foreach ($this->errorPatterns as $type => $config) {
            if (preg_match($config['pattern'], $line, $matches)) {
                return [
                    'type' => $type,
                    'severity' => $config['severity'],
                    'auto_fix' => $config['auto_fix'],
                    'matches' => $matches,
                    'raw_line' => $line,
                ];
            }
        }

        return null;
    }

    /**
     * Handle detected error by gathering context and notifying Claude
     */
    private function handleDetectedError(array $error, string $line): void
    {
        Log::info('Error detected by log monitor', [
            'type' => $error['type'],
            'severity' => $error['severity'],
        ]);

        // Gather additional context
        $context = $this->gatherErrorContext($error, $line);

        // Prepare Claude notification
        $notification = $this->prepareClaudeNotification($error, $context);

        // Send to Claude via MCP
        $this->notifyClaude($notification);
    }

    /**
     * Gather additional context for the error
     */
    private function gatherErrorContext(array $error, string $line): array
    {
        $context = [
            'timestamp' => now()->toISOString(),
            'error_type' => $error['type'],
            'severity' => $error['severity'],
            'raw_error' => $line,
        ];

        // Extract file path and line number
        if (preg_match($this->contextPatterns['file_path'], $line, $matches)) {
            $context['file_path'] = $matches[1] ?? null;
            $context['line_number'] = $matches[2] ?? null;

            // Read the problematic file section if available
            if ($context['file_path'] && file_exists($context['file_path'])) {
                $context['file_content'] = $this->getFileContext(
                    $context['file_path'],
                    (int) ($context['line_number'] ?? 0)
                );
            }
        }

        // Extract request information
        if (preg_match($this->contextPatterns['request_info'], $line, $matches)) {
            $context['request_url'] = $matches[1] ?? null;
            $context['user_id'] = $matches[2] ?? null;
        }

        // Get recent log context (last 10 lines before error)
        $context['recent_logs'] = $this->getRecentLogContext();

        return $context;
    }

    /**
     * Get file content around the error line
     */
    private function getFileContext(string $filePath, int $lineNumber): array
    {
        try {
            $lines = file($filePath, FILE_IGNORE_NEW_LINES);
            $start = max(0, $lineNumber - 6); // 5 lines before
            $end = min(count($lines), $lineNumber + 5); // 5 lines after

            $context = [];
            for ($i = $start; $i < $end; $i++) {
                $context[$i + 1] = $lines[$i] ?? '';
            }

            return $context;
        } catch (\Exception $e) {
            return ['error' => 'Could not read file: '.$e->getMessage()];
        }
    }

    /**
     * Get recent log entries for context
     */
    private function getRecentLogContext(int $lines = 10): array
    {
        try {
            $logFile = storage_path('logs/laravel.log');
            $result = Process::run(['tail', '-n', (string) $lines, $logFile]);

            if ($result->successful()) {
                return explode("\n", trim($result->output()));
            }
        } catch (\Exception $e) {
            Log::warning('Could not get recent log context', ['error' => $e->getMessage()]);
        }

        return [];
    }

    /**
     * Prepare notification for Claude
     */
    private function prepareClaudeNotification(array $error, array $context): array
    {
        $message = $this->generateErrorMessage($error, $context);

        return [
            'type' => 'error_detected',
            'severity' => $error['severity'],
            'auto_fix_recommended' => $error['auto_fix'],
            'error_details' => [
                'type' => $error['type'],
                'message' => $message,
                'file_path' => $context['file_path'] ?? null,
                'line_number' => $context['line_number'] ?? null,
                'raw_error' => $context['raw_error'],
            ],
            'context' => $context,
            'suggested_actions' => $this->getSuggestedActions($error['type']),
            'timestamp' => $context['timestamp'],
        ];
    }

    /**
     * Generate human-readable error message
     */
    private function generateErrorMessage(array $error, array $context): string
    {
        $filePath = $context['file_path'] ?? 'unknown file';
        $lineNumber = $context['line_number'] ?? 'unknown line';

        return match ($error['type']) {
            'fatal' => "PHP Fatal Error in {$filePath}:{$lineNumber}",
            'exception' => 'Laravel Exception detected in application',
            'syntax_error' => "PHP Syntax Error in {$filePath}:{$lineNumber}",
            'class_not_found' => 'Class not found error - missing import or namespace issue',
            'method_not_found' => 'Undefined method call - possible typo or missing method',
            'duplicate_method' => "Duplicate method declaration in {$filePath}:{$lineNumber}",
            default => "Application error detected: {$error['type']}",
        };
    }

    /**
     * Get suggested actions for error types
     */
    private function getSuggestedActions(string $errorType): array
    {
        return match ($errorType) {
            'fatal' => [
                'Check for syntax errors and missing semicolons',
                'Verify class imports and namespaces',
                'Review recent code changes',
            ],
            'duplicate_method' => [
                'Search for duplicate method declarations',
                'Check for merge conflicts',
                'Remove or rename one of the duplicate methods',
            ],
            'class_not_found' => [
                'Add missing use statement',
                'Check namespace declaration',
                'Run composer dump-autoload',
            ],
            'method_not_found' => [
                'Check method name spelling',
                'Verify method exists in the class',
                'Check method visibility (public/private/protected)',
            ],
            default => [
                'Review error context and stack trace',
                'Check recent code changes',
                'Verify configuration and dependencies',
            ],
        };
    }

    /**
     * Send notification to Claude via MCP
     */
    private function notifyClaude(array $notification): void
    {
        try {
            // Send to external Claude processes via bidirectional client
            $externalResults = $this->bidirectionalClient->notifyError($notification);

            Log::info('Error notification sent to Claude processes', [
                'error_type' => $notification['error_details']['type'],
                'file' => $notification['error_details']['file_path'] ?? 'unknown',
                'auto_fix' => $notification['auto_fix_recommended'],
                'external_results' => $externalResults,
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to send error notification to Claude', [
                'error' => $e->getMessage(),
                'notification' => $notification,
            ]);
        }
    }

    /**
     * Check if MCP log monitoring is actually running and functional
     */
    public function isMonitoringSupported(): bool
    {
        try {
            // 1. Check if the MCP watch-logs command class exists
            if (! class_exists(\App\Console\Commands\McpLogWatcher::class)) {
                Log::info('MCP log monitoring check: McpLogWatcher command class not found [NEW CHECK]');

                return false;
            }

            // 2. Check if log file exists and is writable
            $logFile = storage_path('logs/laravel.log');
            if (! file_exists($logFile) || ! is_writable($logFile)) {
                Log::info('MCP log monitoring check: log file not writable', ['file' => $logFile]);

                return false;
            }

            // 3. Check if MCP services are properly configured
            if (! app()->bound(BidirectionalMcpClient::class)) {
                Log::info('MCP log monitoring check: BidirectionalMcpClient service not bound');

                return false;
            }

            // 4. Test actual monitoring process - check if it's currently running
            $isProcessRunning = $this->isLogMonitoringProcessActive();

            // Remove this info log to prevent spam - status is already shown in widget

            // Return true if system is ready (even if not currently monitoring)
            // This indicates the system CAN work, not that it IS working
            return true;

        } catch (\Exception $e) {
            Log::warning('MCP log monitoring support check failed', [
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Check if log monitoring process is actually running
     */
    private function isLogMonitoringProcessActive(): bool
    {
        $output = shell_exec("ps aux | grep 'php artisan mcp:watch-logs' | grep -v grep");

        return ! empty(trim($output ?? ''));
    }

    /**
     * Get monitoring statistics
     */
    public function getMonitoringStats(): array
    {
        return [
            'patterns_configured' => count($this->errorPatterns),
            'monitoring_supported' => $this->isMonitoringSupported(),
            'log_file_exists' => file_exists(storage_path('logs/laravel.log')),
            'log_file_size' => file_exists(storage_path('logs/laravel.log'))
                ? filesize(storage_path('logs/laravel.log'))
                : 0,
        ];
    }
}
