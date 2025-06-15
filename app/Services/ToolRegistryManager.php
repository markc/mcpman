<?php

namespace App\Services;

use App\Models\McpConnection;
use App\Models\Tool;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ToolRegistryManager
{
    private PersistentMcpManager $mcpManager;

    public function __construct(PersistentMcpManager $mcpManager)
    {
        $this->mcpManager = $mcpManager;
    }

    /**
     * Discover tools from all active MCP connections
     */
    public function discoverAllTools(User $user): Collection
    {
        $discoveredTools = collect();
        $connections = McpConnection::where('status', 'active')->get();

        foreach ($connections as $connection) {
            try {
                $connectionTools = $this->discoverToolsFromConnection($connection, $user);
                $discoveredTools = $discoveredTools->merge($connectionTools);
            } catch (\Exception $e) {
                Log::error("Failed to discover tools from connection {$connection->id}", [
                    'error' => $e->getMessage(),
                    'connection' => $connection->name,
                ]);
            }
        }

        return $discoveredTools;
    }

    /**
     * Discover tools from a specific MCP connection
     */
    public function discoverToolsFromConnection(McpConnection $connection, User $user): Collection
    {
        $cacheKey = "mcp_tools_{$connection->id}";

        // Check cache first (5 minute TTL)
        $cachedTools = Cache::get($cacheKey);
        if ($cachedTools !== null) {
            return collect($cachedTools);
        }

        $discoveredTools = collect();

        try {
            // Get tools using MCP client
            $client = new McpClient($connection);
            $toolsData = $client->listTools();

            foreach ($toolsData as $toolData) {
                $tool = $this->createOrUpdateTool($connection, $toolData, $user);
                if ($tool) {
                    $discoveredTools->push($tool);
                }
            }

            // Cache the results
            Cache::put($cacheKey, $discoveredTools->toArray(), 300); // 5 minutes

        } catch (\Exception $e) {
            Log::error("Tool discovery failed for connection {$connection->id}", [
                'error' => $e->getMessage(),
                'connection' => $connection->name,
            ]);
        }

        return $discoveredTools;
    }

    /**
     * Create or update a tool in the registry
     */
    private function createOrUpdateTool(McpConnection $connection, array $toolData, User $user): ?Tool
    {
        try {
            $slug = Str::slug($toolData['name']);

            $tool = Tool::updateOrCreate(
                [
                    'mcp_connection_id' => $connection->id,
                    'slug' => $slug,
                ],
                [
                    'name' => $toolData['name'],
                    'description' => $toolData['description'] ?? null,
                    'input_schema' => $toolData['inputSchema'] ?? null,
                    'category' => $this->categorizeToolUsingHeuristics($toolData),
                    'metadata' => [
                        'source_connection' => $connection->name,
                        'discovered_at' => now()->toISOString(),
                        'raw_schema' => $toolData,
                    ],
                    'discovered_by_user_id' => $user->id,
                    'is_active' => true,
                ]
            );

            return $tool;

        } catch (\Exception $e) {
            Log::error('Failed to create/update tool', [
                'tool_data' => $toolData,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Categorize tool based on name and description heuristics
     */
    private function categorizeToolUsingHeuristics(array $toolData): string
    {
        $name = strtolower($toolData['name'] ?? '');
        $description = strtolower($toolData['description'] ?? '');
        $text = $name.' '.$description;

        // File system operations
        if (preg_match('/\b(file|read|write|glob|ls|dir|path|filesystem)\b/', $text)) {
            return 'filesystem';
        }

        // Code and development
        if (preg_match('/\b(code|edit|git|bash|terminal|shell|debug|test)\b/', $text)) {
            return 'development';
        }

        // Web and network
        if (preg_match('/\b(web|http|url|fetch|api|network|request)\b/', $text)) {
            return 'web';
        }

        // Search and analysis
        if (preg_match('/\b(search|grep|find|analyze|query)\b/', $text)) {
            return 'search';
        }

        // Data and database
        if (preg_match('/\b(data|database|sql|json|csv|export|import)\b/', $text)) {
            return 'data';
        }

        // AI and processing
        if (preg_match('/\b(ai|llm|model|process|generate|transform)\b/', $text)) {
            return 'ai';
        }

        return 'general';
    }

    /**
     * Get tool statistics
     */
    public function getToolStatistics(): array
    {
        $stats = Cache::remember('tool_statistics', 600, function () {
            return [
                'total_tools' => Tool::count(),
                'active_tools' => Tool::active()->count(),
                'favorite_tools' => Tool::favorites()->count(),
                'categories' => Tool::selectRaw('category, COUNT(*) as count')
                    ->groupBy('category')
                    ->pluck('count', 'category')
                    ->toArray(),
                'most_used' => Tool::orderBy('usage_count', 'desc')
                    ->take(10)
                    ->get(['name', 'usage_count', 'category'])
                    ->toArray(),
                'recently_discovered' => Tool::where('created_at', '>=', now()->subDays(7))
                    ->count(),
                'connections_with_tools' => Tool::distinct('mcp_connection_id')->count(),
                'average_usage' => Tool::avg('usage_count'),
                'average_success_rate' => Tool::avg('success_rate'),
            ];
        });

        return $stats;
    }

    /**
     * Search tools by various criteria
     */
    public function searchTools(array $criteria): Collection
    {
        $query = Tool::query();

        if (! empty($criteria['search'])) {
            $search = $criteria['search'];
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%")
                    ->orWhereJsonContains('tags', $search);
            });
        }

        if (! empty($criteria['category'])) {
            $query->byCategory($criteria['category']);
        }

        if (! empty($criteria['connection_id'])) {
            $query->where('mcp_connection_id', $criteria['connection_id']);
        }

        if (isset($criteria['is_active'])) {
            $query->where('is_active', $criteria['is_active']);
        }

        if (isset($criteria['is_favorite'])) {
            $query->where('is_favorite', $criteria['is_favorite']);
        }

        if (! empty($criteria['min_usage'])) {
            $query->where('usage_count', '>=', $criteria['min_usage']);
        }

        if (! empty($criteria['recently_used_days'])) {
            $query->recentlyUsed($criteria['recently_used_days']);
        }

        $sortBy = $criteria['sort_by'] ?? 'name';
        $sortDirection = $criteria['sort_direction'] ?? 'asc';

        return $query->orderBy($sortBy, $sortDirection)->get();
    }

    /**
     * Execute a tool with tracking
     */
    public function executeTool(Tool $tool, array $arguments = []): array
    {
        $startTime = microtime(true);
        $successful = false;

        try {
            $client = new McpClient($tool->mcpConnection);
            $result = $client->callTool($tool->name, $arguments);

            $successful = ! isset($result['error']);
            $executionTime = microtime(true) - $startTime;

            // Track usage
            $tool->incrementUsage($executionTime, $successful);

            return $result;

        } catch (\Exception $e) {
            $executionTime = microtime(true) - $startTime;
            $tool->incrementUsage($executionTime, false);

            throw $e;
        }
    }

    /**
     * Create a tool composition (chain of tools)
     */
    public function createToolComposition(array $toolIds, string $name, ?string $description = null): array
    {
        $tools = Tool::whereIn('id', $toolIds)->get();

        if ($tools->count() !== count($toolIds)) {
            throw new \InvalidArgumentException('Some tools were not found');
        }

        $composition = [
            'id' => Str::uuid()->toString(),
            'name' => $name,
            'description' => $description,
            'tools' => $tools->map(function (Tool $tool) {
                return [
                    'id' => $tool->id,
                    'name' => $tool->name,
                    'slug' => $tool->slug,
                    'parameters' => $tool->parameters,
                    'required_parameters' => $tool->required_parameters,
                ];
            })->toArray(),
            'created_at' => now()->toISOString(),
        ];

        // Store composition in cache for later use
        Cache::put("tool_composition_{$composition['id']}", $composition, 3600); // 1 hour

        return $composition;
    }

    /**
     * Execute a tool composition
     */
    public function executeToolComposition(string $compositionId, array $inputs = []): array
    {
        $composition = Cache::get("tool_composition_{$compositionId}");

        if (! $composition) {
            throw new \InvalidArgumentException('Tool composition not found');
        }

        $results = [];
        $previousOutput = null;

        foreach ($composition['tools'] as $toolData) {
            $tool = Tool::find($toolData['id']);

            if (! $tool) {
                throw new \RuntimeException("Tool {$toolData['name']} not found");
            }

            // Merge previous output with current inputs
            $arguments = array_merge($inputs, ['previous_output' => $previousOutput]);

            try {
                $result = $this->executeTool($tool, $arguments);
                $results[] = [
                    'tool' => $toolData['name'],
                    'result' => $result,
                    'successful' => ! isset($result['error']),
                ];

                $previousOutput = $result;

            } catch (\Exception $e) {
                $results[] = [
                    'tool' => $toolData['name'],
                    'error' => $e->getMessage(),
                    'successful' => false,
                ];

                // Stop composition on error
                break;
            }
        }

        return [
            'composition_id' => $compositionId,
            'composition_name' => $composition['name'],
            'results' => $results,
            'overall_success' => collect($results)->every('successful'),
        ];
    }

    /**
     * Clear tool cache
     */
    public function clearCache(): void
    {
        $connections = McpConnection::all();
        foreach ($connections as $connection) {
            Cache::forget("mcp_tools_{$connection->id}");
        }

        Cache::forget('tool_statistics');

        Log::info('Tool registry cache cleared');
    }
}
