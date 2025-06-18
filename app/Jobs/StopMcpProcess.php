<?php

namespace App\Jobs;

use App\Services\McpProcessOrchestrator;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class StopMcpProcess implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public string $processName;

    public bool $forceKill;

    public function __construct(string $processName, bool $forceKill = false)
    {
        $this->processName = $processName;
        $this->forceKill = $forceKill;
    }

    public function handle(McpProcessOrchestrator $orchestrator): void
    {
        try {
            Log::info('Stopping MCP process via queue', [
                'process_name' => $this->processName,
                'force_kill' => $this->forceKill,
            ]);

            $result = $orchestrator->stopProcess($this->processName);

            if (! $result['success']) {
                Log::error('Failed to stop MCP process', [
                    'process_name' => $this->processName,
                    'error' => $result['message'],
                ]);

                $this->fail(new \Exception($result['message']));
            }
        } catch (\Exception $e) {
            Log::error('Exception stopping MCP process', [
                'process_name' => $this->processName,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('StopMcpProcess job failed', [
            'process_name' => $this->processName,
            'error' => $exception->getMessage(),
        ]);
    }
}
