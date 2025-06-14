<?php

namespace App\Events;

use App\Models\McpConnection;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class McpConnectionStatusChanged implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public McpConnection $connection;

    public string $status;

    public array $data;

    /**
     * Create a new event instance.
     */
    public function __construct(McpConnection $connection, string $status, array $data = [])
    {
        $this->connection = $connection;
        $this->status = $status;
        $this->data = $data;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new Channel('mcp-connections'),
            new PrivateChannel('mcp-connections.'.$this->connection->user_id),
        ];
    }

    /**
     * Get the data to broadcast.
     */
    public function broadcastWith(): array
    {
        return [
            'connection_id' => $this->connection->id,
            'connection_name' => $this->connection->name,
            'status' => $this->status,
            'transport_type' => $this->connection->transport_type,
            'last_connected_at' => $this->connection->last_connected_at?->toISOString(),
            'last_error' => $this->connection->last_error,
            'data' => $this->data,
            'timestamp' => now()->toISOString(),
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'connection.status.changed';
    }
}
