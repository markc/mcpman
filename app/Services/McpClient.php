<?php

namespace App\Services;

use App\Models\McpConnection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;

class McpClient
{
    private McpConnection $connection;
    
    public function __construct(McpConnection $connection)
    {
        $this->connection = $connection;
    }
    
    public function connect(): bool
    {
        try {
            $response = $this->sendRequest('initialize', [
                'protocolVersion' => '2024-11-05',
                'capabilities' => [
                    'roots' => [
                        'listChanged' => false
                    ]
                ],
                'clientInfo' => [
                    'name' => 'Laravel-Filament-MCP-Client',
                    'version' => '1.0.0'
                ]
            ]);
            
            if (isset($response['result'])) {
                $this->connection->update([
                    'capabilities' => $response['result']['capabilities'] ?? [],
                    'status' => 'active',
                    'last_connected_at' => now(),
                    'last_error' => null
                ]);
                
                Log::info('MCP connection established', ['connection' => $this->connection->name]);
                return true;
            }
            
            return false;
            
        } catch (\Exception $e) {
            $this->connection->markAsError($e->getMessage());
            Log::error('MCP connection failed', ['connection' => $this->connection->name, 'error' => $e->getMessage()]);
            return false;
        }
    }
    
    public function listTools(): array
    {
        try {
            $response = $this->sendRequest('tools/list');
            return $response['result']['tools'] ?? [];
        } catch (\Exception $e) {
            Log::error('Failed to list tools', ['error' => $e->getMessage()]);
            return [];
        }
    }
    
    public function callTool(string $name, array $arguments = []): array
    {
        try {
            $response = $this->sendRequest('tools/call', [
                'name' => $name,
                'arguments' => $arguments
            ]);
            
            return $response['result'] ?? [];
        } catch (\Exception $e) {
            Log::error('Tool call failed', ['tool' => $name, 'error' => $e->getMessage()]);
            return ['error' => $e->getMessage()];
        }
    }
    
    public function listResources(): array
    {
        try {
            $response = $this->sendRequest('resources/list');
            return $response['result']['resources'] ?? [];
        } catch (\Exception $e) {
            Log::error('Failed to list resources', ['error' => $e->getMessage()]);
            return [];
        }
    }
    
    public function readResource(string $uri): array
    {
        try {
            $response = $this->sendRequest('resources/read', ['uri' => $uri]);
            return $response['result'] ?? [];
        } catch (\Exception $e) {
            Log::error('Resource read failed', ['uri' => $uri, 'error' => $e->getMessage()]);
            return ['error' => $e->getMessage()];
        }
    }
    
    public function listPrompts(): array
    {
        try {
            $response = $this->sendRequest('prompts/list');
            return $response['result']['prompts'] ?? [];
        } catch (\Exception $e) {
            Log::error('Failed to list prompts', ['error' => $e->getMessage()]);
            return [];
        }
    }
    
    public function getPrompt(string $name, array $arguments = []): array
    {
        try {
            $response = $this->sendRequest('prompts/get', [
                'name' => $name,
                'arguments' => $arguments
            ]);
            
            return $response['result'] ?? [];
        } catch (\Exception $e) {
            Log::error('Prompt get failed', ['prompt' => $name, 'error' => $e->getMessage()]);
            return ['error' => $e->getMessage()];
        }
    }
    
    public function executeConversation(array $messages): array
    {
        try {
            // This would be a custom method for having Claude Code process a conversation
            $response = $this->sendRequest('conversation/execute', [
                'messages' => $messages,
                'model' => 'claude-3-5-sonnet-20241022'
            ]);
            
            return $response['result'] ?? [];
        } catch (\Exception $e) {
            Log::error('Conversation execution failed', ['error' => $e->getMessage()]);
            return ['error' => $e->getMessage()];
        }
    }
    
    private function sendRequest(string $method, array $params = []): array
    {
        $request = [
            'jsonrpc' => '2.0',
            'id' => uniqid(),
            'method' => $method,
            'params' => $params
        ];
        
        Log::debug('Sending MCP request', ['method' => $method, 'connection' => $this->connection->name]);
        
        return match($this->connection->transport_type) {
            'http' => $this->sendHttpRequest($request),
            'websocket' => $this->sendWebSocketRequest($request),
            'stdio' => $this->sendStdioRequest($request),
            default => throw new \Exception('Unsupported transport type: ' . $this->connection->transport_type)
        };
    }
    
    private function sendHttpRequest(array $request): array
    {
        $headers = ['Content-Type' => 'application/json'];
        
        // Add authentication if configured
        if ($this->connection->auth_config) {
            $authConfig = $this->connection->auth_config;
            
            if ($authConfig['type'] === 'bearer' && !empty($authConfig['token'])) {
                $headers['Authorization'] = 'Bearer ' . $authConfig['token'];
            }
        }
        
        $response = Http::withHeaders($headers)
            ->timeout(30)
            ->post($this->connection->endpoint_url, $request);
        
        if (!$response->successful()) {
            throw new \Exception('HTTP request failed: ' . $response->status() . ' ' . $response->body());
        }
        
        return $response->json();
    }
    
    private function sendWebSocketRequest(array $request): array
    {
        // WebSocket implementation would go here
        // For now, we'll throw an exception as it requires additional libraries
        throw new \Exception('WebSocket transport not yet implemented');
    }
    
    private function sendStdioRequest(array $request): array
    {
        // For stdio, we'd need to launch a process and communicate via stdin/stdout
        // This is complex and would require careful process management
        
        $command = $this->connection->endpoint_url; // This would be the command to run
        
        $process = Process::start($command);
        
        // Send request via stdin
        $process->input(json_encode($request) . "\n");
        
        // Read response from stdout
        $output = $process->output();
        
        if (!$process->successful()) {
            throw new \Exception('Process failed: ' . $process->errorOutput());
        }
        
        $lines = explode("\n", trim($output));
        $lastLine = end($lines);
        
        $response = json_decode($lastLine, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \Exception('Invalid JSON response: ' . $lastLine);
        }
        
        return $response;
    }
    
    public function disconnect(): void
    {
        try {
            $this->connection->disconnect();
            Log::info('MCP connection closed', ['connection' => $this->connection->name]);
        } catch (\Exception $e) {
            Log::error('Error disconnecting MCP', ['connection' => $this->connection->name, 'error' => $e->getMessage()]);
        }
    }
    
    public function testConnection(): bool
    {
        try {
            $response = $this->sendRequest('ping');
            return isset($response['result']) || isset($response['id']);
        } catch (\Exception $e) {
            $this->connection->markAsError($e->getMessage());
            return false;
        }
    }
}