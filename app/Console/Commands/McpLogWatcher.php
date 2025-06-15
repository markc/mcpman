<?php

namespace App\Console\Commands;

use App\Services\LogMonitoringService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class McpLogWatcher extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'mcp:watch-logs 
                            {--file= : Specific log file to watch (default: laravel.log)}
                            {--auto-fix : Enable automatic fix suggestions}';

    /**
     * The console command description.
     */
    protected $description = 'Monitor Laravel logs in real-time and automatically notify Claude of errors via MCP';

    public function __construct(
        private LogMonitoringService $logMonitor
    ) {
        parent::__construct();
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('🔍 Starting MCP Log Monitoring Service');
        $this->newLine();

        // Check if monitoring is supported
        if (! $this->logMonitor->isMonitoringSupported()) {
            $this->error('❌ Log monitoring is not supported on this system (tail command not found)');

            return Command::FAILURE;
        }

        // Show monitoring stats
        $stats = $this->logMonitor->getMonitoringStats();
        $this->displayMonitoringInfo($stats);

        // Get log file path
        $logFile = $this->option('file')
            ? storage_path('logs/'.$this->option('file'))
            : storage_path('logs/laravel.log');

        if (! file_exists($logFile)) {
            $this->error("❌ Log file not found: {$logFile}");

            return Command::FAILURE;
        }

        $this->info("📁 Monitoring log file: {$logFile}");
        $this->info('🤖 Auto-fix suggestions: '.($this->option('auto-fix') ? 'ENABLED' : 'DISABLED'));
        $this->newLine();

        $this->info('🚀 Log monitoring is now active. Press Ctrl+C to stop.');
        $this->info('💡 Detected errors will be automatically sent to Claude for analysis and fixes.');
        $this->newLine();

        // Handle graceful shutdown
        pcntl_signal(SIGINT, function () {
            $this->info("\n🛑 Shutting down log monitoring...");
            exit(0);
        });

        try {
            // Start monitoring (this will run indefinitely)
            $this->logMonitor->startMonitoring($logFile);
        } catch (\Exception $e) {
            $this->error('❌ Monitoring failed: '.$e->getMessage());
            Log::error('Log monitoring failed', ['error' => $e->getMessage()]);

            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }

    /**
     * Display monitoring information
     */
    private function displayMonitoringInfo(array $stats): void
    {
        $this->table(
            ['Metric', 'Value', 'Status'],
            [
                ['Error Patterns', $stats['patterns_configured'], '✅ Configured'],
                ['System Support', 'tail command', $stats['monitoring_supported'] ? '✅ Available' : '❌ Missing'],
                ['Log File', 'laravel.log', $stats['log_file_exists'] ? '✅ Found' : '❌ Missing'],
                ['Log Size', $this->formatBytes($stats['log_file_size']), $stats['log_file_size'] > 0 ? '📊 Data' : '📭 Empty'],
            ]
        );
        $this->newLine();
    }

    /**
     * Format bytes to human readable format
     */
    private function formatBytes(int $bytes): string
    {
        if ($bytes == 0) {
            return '0 B';
        }

        $units = ['B', 'KB', 'MB', 'GB'];
        $base = log($bytes, 1024);

        return round(pow(1024, $base - floor($base)), 2).' '.$units[floor($base)];
    }
}
