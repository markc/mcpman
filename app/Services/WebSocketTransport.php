<?php

namespace App\Services;

use App\Models\McpConnection;
use Illuminate\Support\Facades\Log;
use Ratchet\Client\WebSocket;

class WebSocketTransport
{
    private McpConnection $connection;

    private ?WebSocket $websocket = null;

    private bool $connected = false;

    private array $pendingRequests = [];

    private int $requestTimeout = 30;

    public function __construct(McpConnection $connection)
    {
        $this->connection = $connection;
    }

    public function connect(): bool
    {
        try {
            if ($this->connected && $this->websocket) {
                return true;
            }

            $url = $this->connection->endpoint_url;

            // Validate WebSocket URL
            if (! str_starts_with($url, 'ws://') && ! str_starts_with($url, 'wss://')) {
                throw new \Exception('Invalid WebSocket URL. Must start with ws:// or wss://');
            }

            Log::info('Connecting to WebSocket', ['url' => $url]);

            // For now, we'll create a simple WebSocket client implementation
            // In a production environment, you might want to use ReactPHP/Socket or similar
            $this->websocket = $this->createWebSocketConnection($url);
            $this->connected = true;

            Log::info('WebSocket connected successfully', ['connection' => $this->connection->name]);

            return true;

        } catch (\Exception $e) {
            Log::error('WebSocket connection failed', [
                'connection' => $this->connection->name,
                'error' => $e->getMessage(),
            ]);

            $this->connected = false;

            return false;
        }
    }

    public function disconnect(): void
    {
        if ($this->websocket) {
            try {
                $this->websocket->close();
            } catch (\Exception $e) {
                Log::warning('Error closing WebSocket', ['error' => $e->getMessage()]);
            }
        }

        $this->websocket = null;
        $this->connected = false;
        $this->pendingRequests = [];

        Log::info('WebSocket disconnected', ['connection' => $this->connection->name]);
    }

    public function sendRequest(array $request): array
    {
        if (! $this->connected) {
            if (! $this->connect()) {
                throw new \Exception('Failed to establish WebSocket connection');
            }
        }

        $requestId = $request['id'] ?? uniqid();
        $request['id'] = $requestId;

        try {
            // Send the request
            $this->websocket->send(json_encode($request));

            // Wait for response
            return $this->waitForResponse($requestId);

        } catch (\Exception $e) {
            Log::error('WebSocket request failed', [
                'connection' => $this->connection->name,
                'method' => $request['method'] ?? 'unknown',
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    public function isConnected(): bool
    {
        return $this->connected && $this->websocket !== null;
    }

    public function ping(): bool
    {
        try {
            if (! $this->isConnected()) {
                return false;
            }

            $pingRequest = [
                'jsonrpc' => '2.0',
                'id' => uniqid(),
                'method' => 'ping',
                'params' => [],
            ];

            $response = $this->sendRequest($pingRequest);

            return ! isset($response['error']);

        } catch (\Exception $e) {
            Log::warning('WebSocket ping failed', [
                'connection' => $this->connection->name,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    private function createWebSocketConnection(string $url): WebSocket
    {
        // Simple WebSocket implementation using basic sockets
        // For production, consider using ReactPHP/Socket or Ratchet WebSocket client

        $context = stream_context_create();

        // Add SSL context for wss:// connections
        if (str_starts_with($url, 'wss://')) {
            stream_context_set_option($context, 'ssl', 'verify_peer', false);
            stream_context_set_option($context, 'ssl', 'verify_peer_name', false);
        }

        return new SimpleWebSocket($url, $context);
    }

    private function waitForResponse(string $requestId): array
    {
        $startTime = time();

        while (time() - $startTime < $this->requestTimeout) {
            try {
                if ($this->websocket && $this->websocket->hasMessage()) {
                    $message = $this->websocket->receive();
                    $response = json_decode($message, true);

                    if ($response && ($response['id'] ?? null) === $requestId) {
                        return $response;
                    }

                    // Store responses for other requests
                    if (isset($response['id'])) {
                        $this->pendingRequests[$response['id']] = $response;
                    }
                }

                // Check if response is already cached
                if (isset($this->pendingRequests[$requestId])) {
                    $response = $this->pendingRequests[$requestId];
                    unset($this->pendingRequests[$requestId]);

                    return $response;
                }

                usleep(10000); // Wait 10ms before checking again

            } catch (\Exception $e) {
                Log::error('Error waiting for WebSocket response', [
                    'request_id' => $requestId,
                    'error' => $e->getMessage(),
                ]);
                break;
            }
        }

        throw new \Exception('WebSocket request timeout');
    }
}

/**
 * Simple WebSocket implementation for basic MCP communication
 * In production, consider using a more robust WebSocket client library
 */
class SimpleWebSocket
{
    private $socket;

    private string $url;

    private bool $connected = false;

    public function __construct(string $url, $context = null)
    {
        $this->url = $url;
        $this->connect($context);
    }

    private function connect($context): void
    {
        $urlParts = parse_url($this->url);
        $host = $urlParts['host'];
        $port = $urlParts['port'] ?? (str_starts_with($this->url, 'wss://') ? 443 : 80);
        $path = $urlParts['path'] ?? '/';

        $isSecure = str_starts_with($this->url, 'wss://');
        $scheme = $isSecure ? 'ssl' : 'tcp';

        // Create socket connection
        $this->socket = stream_socket_client(
            "{$scheme}://{$host}:{$port}",
            $errno,
            $errstr,
            30,
            STREAM_CLIENT_CONNECT,
            $context
        );

        if (! $this->socket) {
            throw new \Exception("Failed to connect to WebSocket: {$errstr} ({$errno})");
        }

        // Perform WebSocket handshake
        $this->performHandshake($host, $path);
        $this->connected = true;
    }

    private function performHandshake(string $host, string $path): void
    {
        $key = base64_encode(random_bytes(16));

        $headers = [
            "GET {$path} HTTP/1.1",
            "Host: {$host}",
            'Upgrade: websocket',
            'Connection: Upgrade',
            "Sec-WebSocket-Key: {$key}",
            'Sec-WebSocket-Version: 13',
            "Origin: http://{$host}",
            '',
            '',
        ];

        fwrite($this->socket, implode("\r\n", $headers));

        // Read handshake response
        $response = fgets($this->socket, 1024);
        if (! str_contains($response, '101 Switching Protocols')) {
            throw new \Exception('WebSocket handshake failed: '.$response);
        }

        // Read remaining headers
        while (($line = fgets($this->socket, 1024)) !== false) {
            if (trim($line) === '') {
                break;
            }
        }
    }

    public function send(string $message): void
    {
        if (! $this->connected) {
            throw new \Exception('WebSocket not connected');
        }

        $frame = $this->createFrame($message);
        fwrite($this->socket, $frame);
    }

    public function receive(): string
    {
        if (! $this->connected) {
            throw new \Exception('WebSocket not connected');
        }

        return $this->readFrame();
    }

    public function hasMessage(): bool
    {
        if (! $this->connected) {
            return false;
        }

        // Use non-blocking read to check for available data
        $read = [$this->socket];
        $write = null;
        $except = null;

        return stream_select($read, $write, $except, 0, 100000) > 0;
    }

    public function close(): void
    {
        if ($this->socket && $this->connected) {
            // Send close frame
            $closeFrame = pack('C*', 0x88, 0x00);
            fwrite($this->socket, $closeFrame);
            fclose($this->socket);
        }

        $this->connected = false;
    }

    private function createFrame(string $message): string
    {
        $length = strlen($message);
        $mask = pack('N', rand());

        // Basic frame format for text messages
        if ($length < 126) {
            $header = pack('C*', 0x81, $length | 0x80).$mask;
        } elseif ($length < 65536) {
            $header = pack('C*', 0x81, 126 | 0x80).pack('n', $length).$mask;
        } else {
            $header = pack('C*', 0x81, 127 | 0x80).pack('J', $length).$mask;
        }

        // Apply mask to message
        $masked = '';
        for ($i = 0; $i < $length; $i++) {
            $masked .= $message[$i] ^ $mask[$i % 4];
        }

        return $header.$masked;
    }

    private function readFrame(): string
    {
        // Read frame header
        $header = fread($this->socket, 2);
        if (strlen($header) < 2) {
            throw new \Exception('Failed to read WebSocket frame header');
        }

        $firstByte = ord($header[0]);
        $secondByte = ord($header[1]);

        $opcode = $firstByte & 0x0F;
        $masked = ($secondByte & 0x80) === 0x80;
        $length = $secondByte & 0x7F;

        // Read extended length if needed
        if ($length === 126) {
            $extLength = fread($this->socket, 2);
            $length = unpack('n', $extLength)[1];
        } elseif ($length === 127) {
            $extLength = fread($this->socket, 8);
            $length = unpack('J', $extLength)[1];
        }

        // Read mask if present
        $mask = $masked ? fread($this->socket, 4) : '';

        // Read payload
        $payload = fread($this->socket, $length);

        // Unmask payload if needed
        if ($masked) {
            for ($i = 0; $i < $length; $i++) {
                $payload[$i] = $payload[$i] ^ $mask[$i % 4];
            }
        }

        return $payload;
    }
}
