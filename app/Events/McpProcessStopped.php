<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class McpProcessStopped implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public string $processName;

    public ?int $pid;

    public string $timestamp;

    public function __construct(string $processName, ?int $pid)
    {
        $this->processName = $processName;
        $this->pid = $pid;
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
            'event_type' => 'process_stopped',
            'process_name' => $this->processName,
            'pid' => $this->pid,
            'timestamp' => $this->timestamp,
            'status' => 'stopped',
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'mcp.process.stopped';
    }
}
