<?php

namespace App\Jobs;

use App\Models\McpConnection;
use App\Models\User;
use App\Services\ResourceManager;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class SyncResourcesJob implements ShouldQueue
{
    use Queueable;

    private McpConnection $connection;

    private User $user;

    /**
     * Create a new job instance.
     */
    public function __construct(McpConnection $connection, User $user)
    {
        $this->connection = $connection;
        $this->user = $user;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            Log::info("Starting resource sync for connection: {$this->connection->name}");

            $resourceManager = app(ResourceManager::class);
            $discoveredResources = $resourceManager->discoverResourcesFromConnection($this->connection, $this->user);

            Log::info('Resource sync completed', [
                'connection' => $this->connection->name,
                'discovered_count' => $discoveredResources->count(),
            ]);

        } catch (\Exception $e) {
            Log::error("Resource sync failed for connection: {$this->connection->name}", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Re-throw to mark job as failed
            throw $e;
        }
    }

    /**
     * Calculate the number of seconds to wait before retrying the job.
     */
    public function backoff(): array
    {
        return [30, 60, 120]; // Retry after 30s, 1m, 2m
    }

    /**
     * Determine the number of times the job may be attempted.
     */
    public function tries(): int
    {
        return 3;
    }
}
