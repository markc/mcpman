<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class McpProcessError implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public string $processName;

    public string $error;

    public string $timestamp;

    public function __construct(string $processName, string $error)
    {
        $this->processName = $processName;
        $this->error = $error;
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
            'event_type' => 'process_error',
            'process_name' => $this->processName,
            'error' => $this->error,
            'timestamp' => $this->timestamp,
            'status' => 'error',
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'mcp.process.error';
    }
}
