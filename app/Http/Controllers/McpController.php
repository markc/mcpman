<?php

namespace App\Http\Controllers;

use App\Services\McpServer;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class McpController extends Controller
{
    public function __construct(
        private McpServer $mcpServer
    ) {}
    
    public function handle(Request $request): JsonResponse
    {
        try {
            // Validate API key
            $apiKey = $request->header('Authorization');
            if (!$apiKey || !$this->validateApiKey($apiKey)) {
                return response()->json([
                    'jsonrpc' => '2.0',
                    'error' => [
                        'code' => -32600,
                        'message' => 'Invalid or missing API key'
                    ]
                ], 401);
            }
            
            // Handle MCP request
            $mcpRequest = $request->all();
            $response = $this->mcpServer->handleRequest($mcpRequest);
            
            return response()->json($response);
            
        } catch (\Exception $e) {
            Log::error('MCP Controller error', ['error' => $e->getMessage()]);
            
            return response()->json([
                'jsonrpc' => '2.0',
                'error' => [
                    'code' => -32603,
                    'message' => 'Internal error: ' . $e->getMessage()
                ]
            ], 500);
        }
    }
    
    private function validateApiKey(string $apiKey): bool
    {
        // Remove 'Bearer ' prefix if present
        $key = str_replace('Bearer ', '', $apiKey);
        
        // Check if API key exists and is active
        return \App\Models\ApiKey::where('key', $key)
            ->where('is_active', true)
            ->where(function($query) {
                $query->whereNull('expires_at')
                      ->orWhere('expires_at', '>', now());
            })
            ->exists();
    }
}