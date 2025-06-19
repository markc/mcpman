<?php

namespace App\Console\Commands;

use App\Models\McpConnection;
use App\Services\McpClient;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class MonitorLogsCommand extends Command
{
    protected $signature = 'monitor:logs {connection=Log Monitor Claude}';

    protected $description = 'Monitor Laravel logs and send errors to Claude for analysis';

    private string $lastProcessedLine = '';

    private string $logFile;

    public function __construct()
    {
        parent::__construct();
        $this->logFile = storage_path('logs/laravel.log');
    }

    public function handle()
    {
        $connectionName = $this->argument('connection');
        $connection = McpConnection::where('name', $connectionName)->first();

        if (! $connection) {
            $this->error("MCP connection '{$connectionName}' not found.");

            return 1;
        }

        $client = new McpClient($connection);

        if (! $client->connect()) {
            $this->error('Failed to connect to Claude instance.');

            return 1;
        }

        $this->info('Starting log monitoring...');
        $this->info("Monitoring: {$this->logFile}");
        $this->info("Connected to: {$connectionName}");

        // Send initial setup message
        $client->executeConversation([
            [
                'role' => 'user',
                'content' => 'You are now monitoring Laravel logs for errors. When I send you log entries, analyze them and provide:
1. Error type and severity
2. Root cause analysis
3. Suggested fix with code examples
4. Prevention strategies

Respond concisely but thoroughly. Ready to monitor?',
            ],
        ]);

        $this->monitorLogFile($client);

        return 0;
    }

    private function monitorLogFile(McpClient $client)
    {
        $this->lastProcessedLine = $this->getLastLogLine();

        while (true) {
            $newErrors = $this->getNewLogErrors();

            if (! empty($newErrors)) {
                foreach ($newErrors as $error) {
                    $this->info('New error detected: '.substr($error, 0, 100).'...');
                    $this->sendErrorToClaude($client, $error);
                }
            }

            sleep(2); // Check every 2 seconds
        }
    }

    private function getNewLogErrors(): array
    {
        if (! file_exists($this->logFile)) {
            return [];
        }

        $content = file_get_contents($this->logFile);
        $lines = explode("\n", $content);

        $newErrors = [];
        $foundLastLine = empty($this->lastProcessedLine);

        foreach ($lines as $line) {
            if (! $foundLastLine) {
                if (trim($line) === trim($this->lastProcessedLine)) {
                    $foundLastLine = true;
                }

                continue;
            }

            // Look for Laravel error patterns
            if (preg_match('/\[(.*?)\] local\.ERROR:/', $line) ||
                preg_match('/\[(.*?)\] local\.CRITICAL:/', $line) ||
                preg_match('/\[(.*?)\] local\.EMERGENCY:/', $line)) {

                $errorBlock = $this->extractErrorBlock($lines, array_search($line, $lines));
                $newErrors[] = $errorBlock;
            }
        }

        if (! empty($lines)) {
            $this->lastProcessedLine = end($lines);
        }

        return $newErrors;
    }

    private function extractErrorBlock(array $lines, int $startIndex): string
    {
        $errorBlock = $lines[$startIndex] ?? '';
        $i = $startIndex + 1;

        // Collect stack trace and context
        while (isset($lines[$i]) &&
               (preg_match('/^#\d+/', $lines[$i]) ||
                preg_match('/^\s+/', $lines[$i]) ||
                preg_match('/Stack trace:/', $lines[$i]))) {
            $errorBlock .= "\n".$lines[$i];
            $i++;

            // Limit to reasonable size
            if ($i - $startIndex > 50) {
                break;
            }
        }

        return $errorBlock;
    }

    private function sendErrorToClaude(McpClient $client, string $error): void
    {
        try {
            $response = $client->executeConversation([
                [
                    'role' => 'user',
                    'content' => "NEW LARAVEL ERROR DETECTED:\n\n```\n{$error}\n```\n\nPlease analyze this error and provide your assessment.",
                ],
            ]);

            if (isset($response['content'])) {
                $this->line("\n".str_repeat('=', 80));
                $this->line('CLAUDE ANALYSIS:');
                $this->line(str_repeat('=', 80));
                $this->line($response['content']);
                $this->line(str_repeat('=', 80)."\n");

                // Log the analysis for later reference
                Log::info('Claude Error Analysis', [
                    'error' => substr($error, 0, 500),
                    'analysis' => $response['content'],
                ]);
            }
        } catch (\Exception $e) {
            $this->error('Failed to send error to Claude: '.$e->getMessage());
        }
    }

    private function getLastLogLine(): string
    {
        if (! file_exists($this->logFile)) {
            return '';
        }

        $lines = file($this->logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        return end($lines) ?: '';
    }
}
