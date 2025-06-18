<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class McpProcessHealthCheck implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public string $processName;

    public bool $healthy;

    public array $metrics;

    public string $timestamp;

    public function __construct(string $processName, bool $healthy, array $metrics = [])
    {
        $this->processName = $processName;
        $this->healthy = $healthy;
        $this->metrics = $metrics;
        $this->timestamp = now()->toISOString();
    }

    /**
     * Get the channels the event should broadcast on.
     */
    public function broadcastOn(): array
    {
        return [
            new Channel('mcp-processes'),
            new PrivateChannel('mcp-processes.'.auth()->id()),
        ];
    }

    /**
     * Get the data to broadcast.
     */
    public function broadcastWith(): array
    {
        return [
            'event_type' => 'health_check',
            'process_name' => $this->processName,
            'healthy' => $this->healthy,
            'metrics' => $this->metrics,
            'timestamp' => $this->timestamp,
            'status' => $this->healthy ? 'healthy' : 'unhealthy',
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'mcp.process.health';
    }
}
