<?php

namespace App\Http\Controllers;

use App\Services\McpServer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class McpController extends Controller
{
    public function __construct(
        private McpServer $mcpServer
    ) {}

    public function handle(Request $request): JsonResponse
    {
        try {
            // Allow no authentication in local development mode
            $apiKeyModel = null;
            if (app()->environment('local')) {
                // In local environment, create a default user and API key if none exists
                $user = \App\Models\User::where('email', 'admin@example.com')->first();
                if ($user) {
                    $apiKeyModel = $user->apiKeys()->first();
                    if (! $apiKeyModel) {
                        $apiKeyModel = $user->apiKeys()->create([
                            'name' => 'Local Development Key',
                            'key' => 'mcp_local_dev_key',
                            'permissions' => ['read', 'write', 'tools'],
                            'is_active' => true,
                        ]);
                    }
                }
            } else {
                // Validate API key in production
                $apiKey = $request->header('Authorization');
                if (! $apiKey) {
                    return response()->json([
                        'jsonrpc' => '2.0',
                        'error' => [
                            'code' => -32600,
                            'message' => 'Missing API key',
                        ],
                    ], 401);
                }

                $apiKeyModel = $this->validateApiKey($apiKey);
                if (! $apiKeyModel) {
                    return response()->json([
                        'jsonrpc' => '2.0',
                        'error' => [
                            'code' => -32600,
                            'message' => 'Invalid API key',
                        ],
                    ], 401);
                }
            }

            // Record API key usage if we have one
            if ($apiKeyModel) {
                $apiKeyModel->recordUsage();
            }

            // Handle MCP request with user context
            $mcpRequest = $request->all();
            $response = $this->mcpServer->handleRequest($mcpRequest, $apiKeyModel);

            return response()->json($response);

        } catch (\Exception $e) {
            Log::error('MCP Controller error', ['error' => $e->getMessage()]);

            return response()->json([
                'jsonrpc' => '2.0',
                'error' => [
                    'code' => -32603,
                    'message' => 'Internal error: '.$e->getMessage(),
                ],
            ], 500);
        }
    }

    private function validateApiKey(string $apiKey): ?\App\Models\ApiKey
    {
        // Remove 'Bearer ' prefix if present
        $key = str_replace('Bearer ', '', $apiKey);

        // Check if API key exists and is active
        return \App\Models\ApiKey::where('key', $key)
            ->where('is_active', true)
            ->where(function ($query) {
                $query->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            })
            ->first();
    }
}
