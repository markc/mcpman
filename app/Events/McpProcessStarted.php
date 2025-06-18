<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class McpProcessStarted implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public string $processName;

    public int $pid;

    public array $command;

    public string $timestamp;

    public function __construct(string $processName, int $pid, array $command)
    {
        $this->processName = $processName;
        $this->pid = $pid;
        $this->command = $command;
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
            'event_type' => 'process_started',
            'process_name' => $this->processName,
            'pid' => $this->pid,
            'command' => $this->command,
            'timestamp' => $this->timestamp,
            'status' => 'running',
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'mcp.process.started';
    }
}
