<?php

namespace App\Services;

use App\Models\McpConnection;
use App\Models\Resource;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ResourceManager
{
    private const CACHE_TTL_MINUTES = 60; // 1 hour default

    private const MAX_CACHE_SIZE_MB = 100; // Maximum cache size per resource

    /**
     * Discover resources from all active MCP connections
     */
    public function discoverAllResources(User $user): Collection
    {
        $discoveredResources = collect();
        $connections = McpConnection::where('status', 'active')->get();

        foreach ($connections as $connection) {
            try {
                $connectionResources = $this->discoverResourcesFromConnection($connection, $user);
                $discoveredResources = $discoveredResources->merge($connectionResources);
            } catch (\Exception $e) {
                Log::error("Failed to discover resources from connection {$connection->id}", [
                    'error' => $e->getMessage(),
                    'connection' => $connection->name,
                ]);
            }
        }

        return $discoveredResources;
    }

    /**
     * Discover resources from a specific MCP connection
     */
    public function discoverResourcesFromConnection(McpConnection $connection, User $user): Collection
    {
        $cacheKey = "mcp_resources_{$connection->id}";

        // Check cache first (10 minute TTL)
        $cachedResources = Cache::get($cacheKey);
        if ($cachedResources !== null) {
            return collect($cachedResources);
        }

        $discoveredResources = collect();

        try {
            // Use MCP client to discover resources
            $client = new McpClient($connection);
            $resourcesData = $this->getResourcesFromClient($client);

            foreach ($resourcesData as $resourceData) {
                $resource = $this->createOrUpdateResource($connection, $resourceData, $user);
                if ($resource) {
                    $discoveredResources->push($resource);
                }
            }

            // Cache the results
            Cache::put($cacheKey, $discoveredResources->toArray(), 600); // 10 minutes

        } catch (\Exception $e) {
            Log::error("Resource discovery failed for connection {$connection->id}", [
                'error' => $e->getMessage(),
                'connection' => $connection->name,
            ]);
        }

        return $discoveredResources;
    }

    /**
     * Get resources from MCP client
     */
    private function getResourcesFromClient(McpClient $client): array
    {
        // First try to list resources using MCP protocol
        try {
            $response = $client->listResources();
            if (isset($response['resources'])) {
                return $response['resources'];
            }
        } catch (\Exception $e) {
            Log::warning('Failed to list resources via MCP protocol', ['error' => $e->getMessage()]);
        }

        // Fallback: try to use file system tools to discover resources
        try {
            $tools = $client->listTools();
            $resources = [];

            foreach ($tools as $tool) {
                if ($this->isFileSystemTool($tool)) {
                    $foundResources = $this->discoverResourcesWithTool($client, $tool);
                    $resources = array_merge($resources, $foundResources);
                }
            }

            return $resources;
        } catch (\Exception $e) {
            Log::warning('Failed to discover resources via tools', ['error' => $e->getMessage()]);

            return [];
        }
    }

    /**
     * Check if a tool can be used for resource discovery
     */
    private function isFileSystemTool(array $tool): bool
    {
        $name = strtolower($tool['name'] ?? '');
        $description = strtolower($tool['description'] ?? '');

        $fileSystemKeywords = ['file', 'read', 'list', 'glob', 'ls', 'dir', 'find'];

        foreach ($fileSystemKeywords as $keyword) {
            if (str_contains($name, $keyword) || str_contains($description, $keyword)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Discover resources using a specific tool
     */
    private function discoverResourcesWithTool(McpClient $client, array $tool): array
    {
        $resources = [];

        try {
            // Try common discovery patterns
            $discoveryCommands = [
                ['path' => '.'],
                ['directory' => '.'],
                ['pattern' => '*'],
                ['glob' => '*'],
            ];

            foreach ($discoveryCommands as $args) {
                try {
                    $result = $client->callTool($tool['name'], $args);
                    if (isset($result['content'])) {
                        $foundResources = $this->parseResourcesFromToolOutput($result['content'], $tool['name']);
                        $resources = array_merge($resources, $foundResources);
                    }
                } catch (\Exception $e) {
                    // Continue with next command
                    continue;
                }
            }
        } catch (\Exception $e) {
            Log::debug("Tool {$tool['name']} failed for resource discovery", ['error' => $e->getMessage()]);
        }

        return $resources;
    }

    /**
     * Parse resources from tool output
     */
    private function parseResourcesFromToolOutput(mixed $content, string $toolName): array
    {
        $resources = [];

        if (is_string($content)) {
            // Try to parse as file list
            $lines = explode("\n", $content);
            foreach ($lines as $line) {
                $line = trim($line);
                if (empty($line)) {
                    continue;
                }

                $resources[] = [
                    'name' => basename($line),
                    'path_or_uri' => $line,
                    'type' => $this->guessResourceType($line),
                    'discovered_via' => $toolName,
                ];
            }
        } elseif (is_array($content)) {
            // Handle structured output
            foreach ($content as $item) {
                if (is_string($item)) {
                    $resources[] = [
                        'name' => basename($item),
                        'path_or_uri' => $item,
                        'type' => $this->guessResourceType($item),
                        'discovered_via' => $toolName,
                    ];
                } elseif (is_array($item) && isset($item['name'])) {
                    $resources[] = array_merge([
                        'type' => $this->guessResourceType($item['name']),
                        'discovered_via' => $toolName,
                    ], $item);
                }
            }
        }

        return $resources;
    }

    /**
     * Guess resource type from path/name
     */
    private function guessResourceType(string $pathOrName): string
    {
        $extension = strtolower(pathinfo($pathOrName, PATHINFO_EXTENSION));

        // Image files
        if (in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'svg', 'webp'])) {
            return 'image';
        }

        // Video files
        if (in_array($extension, ['mp4', 'avi', 'mov', 'wmv', 'flv', 'webm'])) {
            return 'video';
        }

        // Audio files
        if (in_array($extension, ['mp3', 'wav', 'flac', 'aac', 'ogg'])) {
            return 'audio';
        }

        // If no extension or ends with /, it's likely a directory
        if (empty($extension) || str_ends_with($pathOrName, '/')) {
            return 'directory';
        }

        return 'file';
    }

    /**
     * Create or update a resource in the registry
     */
    private function createOrUpdateResource(McpConnection $connection, array $resourceData, User $user): ?Resource
    {
        try {
            $slug = Str::slug($resourceData['name']);

            $resource = Resource::updateOrCreate(
                [
                    'mcp_connection_id' => $connection->id,
                    'path_or_uri' => $resourceData['path_or_uri'],
                ],
                [
                    'name' => $resourceData['name'],
                    'slug' => $slug,
                    'type' => $resourceData['type'],
                    'description' => $resourceData['description'] ?? null,
                    'mime_type' => $resourceData['mime_type'] ?? $this->guessMimeType($resourceData['name']),
                    'size_bytes' => $resourceData['size_bytes'] ?? null,
                    'metadata' => array_merge([
                        'source_connection' => $connection->name,
                        'discovered_at' => now()->toISOString(),
                        'discovered_via' => $resourceData['discovered_via'] ?? 'mcp_protocol',
                    ], $resourceData['metadata'] ?? []),
                    'discovered_by_user_id' => $user->id,
                    'is_indexable' => true,
                ]
            );

            return $resource;

        } catch (\Exception $e) {
            Log::error('Failed to create/update resource', [
                'resource_data' => $resourceData,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Guess MIME type from filename
     */
    private function guessMimeType(string $filename): ?string
    {
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

        return match ($extension) {
            'txt' => 'text/plain',
            'html', 'htm' => 'text/html',
            'css' => 'text/css',
            'js' => 'application/javascript',
            'json' => 'application/json',
            'xml' => 'application/xml',
            'pdf' => 'application/pdf',
            'jpg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'svg' => 'image/svg+xml',
            'mp4' => 'video/mp4',
            'mp3' => 'audio/mpeg',
            'wav' => 'audio/wav',
            default => null,
        };
    }

    /**
     * Access a resource and return its content
     */
    public function accessResource(Resource $resource, bool $useCache = true): array
    {
        $resource->updateLastAccessed();

        // Check cache first if enabled
        if ($useCache && $resource->isCacheValid()) {
            $cachedContent = $this->getCachedContent($resource);
            if ($cachedContent !== null) {
                return [
                    'content' => $cachedContent,
                    'from_cache' => true,
                    'cached_at' => $resource->cached_at,
                ];
            }
        }

        try {
            $client = new McpClient($resource->mcpConnection);
            $content = $this->fetchResourceContent($client, $resource);

            // Cache the content if it's not too large
            if ($this->shouldCacheContent($content, $resource)) {
                $this->cacheContent($resource, $content);
            }

            return [
                'content' => $content,
                'from_cache' => false,
                'fetched_at' => now(),
            ];

        } catch (\Exception $e) {
            Log::error("Failed to access resource {$resource->id}", [
                'error' => $e->getMessage(),
                'resource' => $resource->name,
            ]);

            throw $e;
        }
    }

    /**
     * Fetch resource content via MCP client
     */
    private function fetchResourceContent(McpClient $client, Resource $resource): mixed
    {
        // Try to read the resource using available tools
        $tools = $client->listTools();

        foreach ($tools as $tool) {
            if ($this->canToolReadResource($tool, $resource)) {
                try {
                    $result = $client->callTool($tool['name'], [
                        'file_path' => $resource->path_or_uri,
                        'path' => $resource->path_or_uri,
                        'filename' => $resource->path_or_uri,
                    ]);

                    if (isset($result['content'])) {
                        return $result['content'];
                    }
                } catch (\Exception $e) {
                    // Try next tool
                    continue;
                }
            }
        }

        throw new \Exception("No suitable tool found to read resource: {$resource->name}");
    }

    /**
     * Check if a tool can read a specific resource
     */
    private function canToolReadResource(array $tool, Resource $resource): bool
    {
        $name = strtolower($tool['name'] ?? '');
        $description = strtolower($tool['description'] ?? '');

        $readKeywords = ['read', 'get', 'fetch', 'load', 'content'];

        foreach ($readKeywords as $keyword) {
            if (str_contains($name, $keyword) || str_contains($description, $keyword)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if content should be cached
     */
    private function shouldCacheContent(mixed $content, Resource $resource): bool
    {
        $contentSize = strlen(is_string($content) ? $content : json_encode($content));

        return $contentSize <= (self::MAX_CACHE_SIZE_MB * 1024 * 1024);
    }

    /**
     * Cache resource content
     */
    private function cacheContent(Resource $resource, mixed $content): void
    {
        $cacheKey = "resource_content_{$resource->id}";
        Cache::put($cacheKey, $content, self::CACHE_TTL_MINUTES * 60);
        $resource->markAsCached(self::CACHE_TTL_MINUTES);
    }

    /**
     * Get cached content
     */
    private function getCachedContent(Resource $resource): mixed
    {
        $cacheKey = "resource_content_{$resource->id}";

        return Cache::get($cacheKey);
    }

    /**
     * Synchronize resources between connections
     */
    public function synchronizeResources(Resource $sourceResource, McpConnection $targetConnection, User $user): Resource
    {
        $content = $this->accessResource($sourceResource);

        // Create new resource in target connection
        $syncedResource = Resource::create([
            'name' => $sourceResource->name.'_synced',
            'type' => $sourceResource->type,
            'path_or_uri' => $this->generateSyncPath($sourceResource, $targetConnection),
            'description' => "Synced from {$sourceResource->name}",
            'mime_type' => $sourceResource->mime_type,
            'size_bytes' => $sourceResource->size_bytes,
            'metadata' => array_merge($sourceResource->metadata ?? [], [
                'synced_from' => $sourceResource->id,
                'synced_at' => now()->toISOString(),
            ]),
            'mcp_connection_id' => $targetConnection->id,
            'discovered_by_user_id' => $user->id,
            'is_public' => $sourceResource->is_public,
            'is_indexable' => $sourceResource->is_indexable,
        ]);

        // Cache the content for the new resource
        $this->cacheContent($syncedResource, $content['content']);

        return $syncedResource;
    }

    /**
     * Generate sync path for target connection
     */
    private function generateSyncPath(Resource $sourceResource, McpConnection $targetConnection): string
    {
        return "synced/{$sourceResource->mcpConnection->name}/{$sourceResource->path_or_uri}";
    }

    /**
     * Search resources by various criteria
     */
    public function searchResources(array $criteria): Collection
    {
        $query = Resource::query();

        if (! empty($criteria['search'])) {
            $search = $criteria['search'];
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%")
                    ->orWhere('path_or_uri', 'like', "%{$search}%")
                    ->orWhereJsonContains('tags', $search);
            });
        }

        if (! empty($criteria['type'])) {
            $query->ofType($criteria['type']);
        }

        if (! empty($criteria['connection_id'])) {
            $query->byConnection($criteria['connection_id']);
        }

        if (isset($criteria['is_public'])) {
            $query->where('is_public', $criteria['is_public']);
        }

        if (isset($criteria['is_cached'])) {
            $query->where('is_cached', $criteria['is_cached']);
        }

        if (! empty($criteria['mime_type'])) {
            $query->where('mime_type', $criteria['mime_type']);
        }

        if (! empty($criteria['parent_id'])) {
            $query->where('parent_resource_id', $criteria['parent_id']);
        } elseif (isset($criteria['root_only']) && $criteria['root_only']) {
            $query->rootResources();
        }

        $sortBy = $criteria['sort_by'] ?? 'name';
        $sortDirection = $criteria['sort_direction'] ?? 'asc';

        return $query->orderBy($sortBy, $sortDirection)->get();
    }

    /**
     * Get resource statistics
     */
    public function getResourceStatistics(): array
    {
        return Cache::remember('resource_statistics', 600, function () {
            return [
                'total_resources' => Resource::count(),
                'cached_resources' => Resource::cached()->count(),
                'public_resources' => Resource::public()->count(),
                'types' => Resource::selectRaw('type, COUNT(*) as count')
                    ->groupBy('type')
                    ->pluck('count', 'type')
                    ->toArray(),
                'connections_with_resources' => Resource::distinct('mcp_connection_id')->count(),
                'recently_accessed' => Resource::recentlyAccessed(7)->count(),
                'total_cached_size' => Resource::cached()->sum('size_bytes'),
                'expired_cache_count' => Resource::expired()->count(),
            ];
        });
    }

    /**
     * Clear expired cache entries
     */
    public function clearExpiredCache(): int
    {
        $expiredResources = Resource::expired()->get();
        $clearedCount = 0;

        foreach ($expiredResources as $resource) {
            try {
                $cacheKey = "resource_content_{$resource->id}";
                Cache::forget($cacheKey);
                $resource->clearCache();
                $clearedCount++;
            } catch (\Exception $e) {
                Log::error("Failed to clear cache for resource {$resource->id}", [
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $clearedCount;
    }

    /**
     * Clear all resource cache
     */
    public function clearAllCache(): void
    {
        $connections = McpConnection::all();
        foreach ($connections as $connection) {
            Cache::forget("mcp_resources_{$connection->id}");
        }

        $resources = Resource::cached()->get();
        foreach ($resources as $resource) {
            $cacheKey = "resource_content_{$resource->id}";
            Cache::forget($cacheKey);
            $resource->clearCache();
        }

        Cache::forget('resource_statistics');

        Log::info('All resource cache cleared');
    }
}
