<?php

namespace App\Jobs;

use App\Services\McpProcessOrchestrator;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class StartMcpProcess implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public string $processName;

    public array $command;

    public array $options;

    public function __construct(string $processName, array $command, array $options = [])
    {
        $this->processName = $processName;
        $this->command = $command;
        $this->options = $options;
    }

    public function handle(McpProcessOrchestrator $orchestrator): void
    {
        try {
            Log::info('Starting MCP process via queue', [
                'process_name' => $this->processName,
                'command' => $this->command,
                'options' => $this->options,
            ]);

            $result = $orchestrator->startProcess($this->processName, $this->command, $this->options);

            if (! $result['success']) {
                Log::error('Failed to start MCP process', [
                    'process_name' => $this->processName,
                    'error' => $result['message'],
                ]);

                $this->fail(new \Exception($result['message']));
            }
        } catch (\Exception $e) {
            Log::error('Exception starting MCP process', [
                'process_name' => $this->processName,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('StartMcpProcess job failed', [
            'process_name' => $this->processName,
            'error' => $exception->getMessage(),
        ]);
    }
}
