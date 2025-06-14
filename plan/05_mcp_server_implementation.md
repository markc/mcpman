# Stage 05: MCP Server Implementation

## Overview
Create the bidirectional Model Context Protocol server that enables Claude Code to communicate with the Filament application, providing access to datasets, documents, and tool execution.

## MCP Server Architecture

The MCP server provides these capabilities:
1. **Resources** - Access to datasets and documents
2. **Tools** - Execute operations on data
3. **Prompts** - Predefined interaction templates
4. **Authentication** - API key-based security

## Step-by-Step Implementation

### 1. Create MCP Server Service

**app/Services/McpServer.php**:
```php
<?php

namespace App\Services;

use App\Models\ApiKey;
use App\Models\Dataset;
use App\Models\Document;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class McpServer
{
    protected ?ApiKey $authenticatedKey = null;

    public function handleRequest(Request $request): array
    {
        try {
            // Authenticate request
            if (!$this->authenticate($request)) {
                return $this->errorResponse('Authentication failed', 401);
            }

            // Parse MCP request
            $mcpRequest = $request->json()->all();
            
            if (!$this->validateMcpRequest($mcpRequest)) {
                return $this->errorResponse('Invalid MCP request format', 400);
            }

            // Route to appropriate handler
            return match ($mcpRequest['method']) {
                'resources/list' => $this->listResources($mcpRequest),
                'resources/read' => $this->readResource($mcpRequest),
                'tools/list' => $this->listTools($mcpRequest),
                'tools/call' => $this->callTool($mcpRequest),
                'prompts/list' => $this->listPrompts($mcpRequest),
                'prompts/get' => $this->getPrompt($mcpRequest),
                default => $this->errorResponse('Unknown method: ' . $mcpRequest['method'], 404),
            };

        } catch (\Exception $e) {
            Log::error('MCP Server error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return $this->errorResponse('Internal server error', 500);
        }
    }

    protected function authenticate(Request $request): bool
    {
        $apiKey = $request->header('Authorization');
        
        if (!$apiKey || !str_starts_with($apiKey, 'Bearer ')) {
            return false;
        }

        $keyValue = substr($apiKey, 7);
        $this->authenticatedKey = ApiKey::where('key', $keyValue)
            ->where('is_active', true)
            ->first();

        if (!$this->authenticatedKey || !$this->authenticatedKey->isValid()) {
            return false;
        }

        // Record usage
        $this->authenticatedKey->recordUsage();

        return true;
    }

    protected function validateMcpRequest(array $request): bool
    {
        $validator = Validator::make($request, [
            'jsonrpc' => 'required|in:2.0',
            'id' => 'required',
            'method' => 'required|string',
            'params' => 'sometimes|array',
        ]);

        return !$validator->fails();
    }

    protected function listResources(array $request): array
    {
        if (!$this->hasPermission('read')) {
            return $this->errorResponse('Insufficient permissions', 403);
        }

        $resources = [];

        // Add datasets as resources
        $datasets = Dataset::where('status', 'active')
            ->where('user_id', $this->authenticatedKey->user_id)
            ->get();

        foreach ($datasets as $dataset) {
            $resources[] = [
                'uri' => "dataset://{$dataset->slug}",
                'name' => $dataset->name,
                'description' => $dataset->description,
                'mimeType' => $this->getDatasetMimeType($dataset->type),
            ];
        }

        // Add documents as resources
        $documents = Document::where('status', 'published')
            ->where('user_id', $this->authenticatedKey->user_id)
            ->get();

        foreach ($documents as $document) {
            $resources[] = [
                'uri' => "document://{$document->slug}",
                'name' => $document->title,
                'description' => "Document: {$document->title}",
                'mimeType' => $this->getDocumentMimeType($document->type),
            ];
        }

        return $this->successResponse(['resources' => $resources]);
    }

    protected function readResource(array $request): array
    {
        if (!$this->hasPermission('read')) {
            return $this->errorResponse('Insufficient permissions', 403);
        }

        $uri = $request['params']['uri'] ?? null;
        
        if (!$uri) {
            return $this->errorResponse('URI parameter required', 400);
        }

        if (str_starts_with($uri, 'dataset://')) {
            return $this->readDataset(substr($uri, 10));
        }

        if (str_starts_with($uri, 'document://')) {
            return $this->readDocument(substr($uri, 11));
        }

        return $this->errorResponse('Unknown resource URI scheme', 404);
    }

    protected function readDataset(string $slug): array
    {
        $dataset = Dataset::where('slug', $slug)
            ->where('status', 'active')
            ->where('user_id', $this->authenticatedKey->user_id)
            ->first();

        if (!$dataset) {
            return $this->errorResponse('Dataset not found', 404);
        }

        $contents = [
            'metadata' => [
                'name' => $dataset->name,
                'type' => $dataset->type,
                'description' => $dataset->description,
                'schema' => $dataset->schema,
                'document_count' => $dataset->documents()->count(),
            ],
            'documents' => [],
        ];

        // Include documents in dataset
        $documents = $dataset->documents()
            ->where('status', 'published')
            ->get(['title', 'slug', 'content', 'type', 'tags']);

        foreach ($documents as $document) {
            $contents['documents'][] = [
                'title' => $document->title,
                'slug' => $document->slug,
                'type' => $document->type,
                'content' => $document->content,
                'tags' => $document->tags,
            ];
        }

        return $this->successResponse([
            'contents' => [
                [
                    'uri' => "dataset://{$slug}",
                    'mimeType' => $this->getDatasetMimeType($dataset->type),
                    'text' => json_encode($contents, JSON_PRETTY_PRINT),
                ],
            ],
        ]);
    }

    protected function readDocument(string $slug): array
    {
        $document = Document::where('slug', $slug)
            ->where('status', 'published')
            ->where('user_id', $this->authenticatedKey->user_id)
            ->first();

        if (!$document) {
            return $this->errorResponse('Document not found', 404);
        }

        return $this->successResponse([
            'contents' => [
                [
                    'uri' => "document://{$slug}",
                    'mimeType' => $this->getDocumentMimeType($document->type),
                    'text' => $document->content,
                ],
            ],
        ]);
    }

    protected function listTools(array $request): array
    {
        if (!$this->hasPermission('tools')) {
            return $this->errorResponse('Insufficient permissions', 403);
        }

        $tools = [
            [
                'name' => 'create_document',
                'description' => 'Create a new document',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'title' => ['type' => 'string', 'description' => 'Document title'],
                        'content' => ['type' => 'string', 'description' => 'Document content'],
                        'type' => ['type' => 'string', 'enum' => ['markdown', 'html', 'text', 'json']],
                        'dataset_slug' => ['type' => 'string', 'description' => 'Dataset to add document to (optional)'],
                        'tags' => ['type' => 'array', 'items' => ['type' => 'string']],
                    ],
                    'required' => ['title', 'content'],
                ],
            ],
            [
                'name' => 'update_document',
                'description' => 'Update an existing document',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'slug' => ['type' => 'string', 'description' => 'Document slug'],
                        'title' => ['type' => 'string', 'description' => 'New title'],
                        'content' => ['type' => 'string', 'description' => 'New content'],
                        'tags' => ['type' => 'array', 'items' => ['type' => 'string']],
                    ],
                    'required' => ['slug'],
                ],
            ],
            [
                'name' => 'search_documents',
                'description' => 'Search documents by content or title',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'query' => ['type' => 'string', 'description' => 'Search query'],
                        'type' => ['type' => 'string', 'description' => 'Filter by document type'],
                        'dataset_slug' => ['type' => 'string', 'description' => 'Filter by dataset'],
                    ],
                    'required' => ['query'],
                ],
            ],
            [
                'name' => 'create_dataset',
                'description' => 'Create a new dataset',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'name' => ['type' => 'string', 'description' => 'Dataset name'],
                        'description' => ['type' => 'string', 'description' => 'Dataset description'],
                        'type' => ['type' => 'string', 'enum' => ['json', 'csv', 'xml', 'yaml', 'text']],
                        'schema' => ['type' => 'object', 'description' => 'JSON schema definition'],
                    ],
                    'required' => ['name', 'type'],
                ],
            ],
        ];

        return $this->successResponse(['tools' => $tools]);
    }

    protected function callTool(array $request): array
    {
        if (!$this->hasPermission('tools')) {
            return $this->errorResponse('Insufficient permissions', 403);
        }

        $toolName = $request['params']['name'] ?? null;
        $arguments = $request['params']['arguments'] ?? [];

        return match ($toolName) {
            'create_document' => $this->createDocument($arguments),
            'update_document' => $this->updateDocument($arguments),
            'search_documents' => $this->searchDocuments($arguments),
            'create_dataset' => $this->createDataset($arguments),
            default => $this->errorResponse('Unknown tool: ' . $toolName, 404),
        };
    }

    protected function createDocument(array $args): array
    {
        $validator = Validator::make($args, [
            'title' => 'required|string|max:255',
            'content' => 'required|string',
            'type' => 'sometimes|in:markdown,html,text,json',
            'dataset_slug' => 'sometimes|string',
            'tags' => 'sometimes|array',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse('Validation failed: ' . implode(', ', $validator->errors()->all()), 400);
        }

        $datasetId = null;
        if (!empty($args['dataset_slug'])) {
            $dataset = Dataset::where('slug', $args['dataset_slug'])
                ->where('user_id', $this->authenticatedKey->user_id)
                ->first();
            $datasetId = $dataset?->id;
        }

        $document = Document::create([
            'title' => $args['title'],
            'content' => $args['content'],
            'type' => $args['type'] ?? 'markdown',
            'status' => 'published',
            'tags' => $args['tags'] ?? [],
            'dataset_id' => $datasetId,
            'user_id' => $this->authenticatedKey->user_id,
        ]);

        return $this->successResponse([
            'content' => [
                [
                    'type' => 'text',
                    'text' => "Document '{$document->title}' created successfully with slug: {$document->slug}",
                ],
            ],
        ]);
    }

    protected function updateDocument(array $args): array
    {
        $validator = Validator::make($args, [
            'slug' => 'required|string',
            'title' => 'sometimes|string|max:255',
            'content' => 'sometimes|string',
            'tags' => 'sometimes|array',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse('Validation failed: ' . implode(', ', $validator->errors()->all()), 400);
        }

        $document = Document::where('slug', $args['slug'])
            ->where('user_id', $this->authenticatedKey->user_id)
            ->first();

        if (!$document) {
            return $this->errorResponse('Document not found', 404);
        }

        $updateData = array_filter([
            'title' => $args['title'] ?? null,
            'content' => $args['content'] ?? null,
            'tags' => $args['tags'] ?? null,
        ], fn($value) => $value !== null);

        $document->update($updateData);

        return $this->successResponse([
            'content' => [
                [
                    'type' => 'text',
                    'text' => "Document '{$document->title}' updated successfully",
                ],
            ],
        ]);
    }

    protected function searchDocuments(array $args): array
    {
        $query = $args['query'] ?? '';
        
        $documents = Document::where('user_id', $this->authenticatedKey->user_id)
            ->where('status', 'published')
            ->where(function ($q) use ($query) {
                $q->where('title', 'like', "%{$query}%")
                  ->orWhere('content', 'like', "%{$query}%");
            });

        if (!empty($args['type'])) {
            $documents->where('type', $args['type']);
        }

        if (!empty($args['dataset_slug'])) {
            $dataset = Dataset::where('slug', $args['dataset_slug'])
                ->where('user_id', $this->authenticatedKey->user_id)
                ->first();
            if ($dataset) {
                $documents->where('dataset_id', $dataset->id);
            }
        }

        $results = $documents->limit(10)->get(['title', 'slug', 'content', 'type']);

        $content = "Found " . count($results) . " documents matching '{$query}':\n\n";
        
        foreach ($results as $doc) {
            $content .= "**{$doc->title}** (slug: {$doc->slug})\n";
            $content .= "Type: {$doc->type}\n";
            $content .= "Content preview: " . \Illuminate\Support\Str::limit($doc->content, 200) . "\n\n";
        }

        return $this->successResponse([
            'content' => [
                [
                    'type' => 'text',
                    'text' => $content,
                ],
            ],
        ]);
    }

    protected function createDataset(array $args): array
    {
        $validator = Validator::make($args, [
            'name' => 'required|string|max:255',
            'description' => 'sometimes|string',
            'type' => 'required|in:json,csv,xml,yaml,text',
            'schema' => 'sometimes|array',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse('Validation failed: ' . implode(', ', $validator->errors()->all()), 400);
        }

        $dataset = Dataset::create([
            'name' => $args['name'],
            'description' => $args['description'] ?? null,
            'type' => $args['type'],
            'schema' => $args['schema'] ?? null,
            'user_id' => $this->authenticatedKey->user_id,
        ]);

        return $this->successResponse([
            'content' => [
                [
                    'type' => 'text',
                    'text' => "Dataset '{$dataset->name}' created successfully with slug: {$dataset->slug}",
                ],
            ],
        ]);
    }

    protected function listPrompts(array $request): array
    {
        $prompts = [
            [
                'name' => 'analyze_dataset',
                'description' => 'Analyze a dataset and provide insights',
                'arguments' => [
                    [
                        'name' => 'dataset_slug',
                        'description' => 'The slug of the dataset to analyze',
                        'required' => true,
                    ],
                ],
            ],
            [
                'name' => 'summarize_documents',
                'description' => 'Create a summary of documents in a dataset',
                'arguments' => [
                    [
                        'name' => 'dataset_slug',
                        'description' => 'The slug of the dataset containing documents',
                        'required' => true,
                    ],
                ],
            ],
        ];

        return $this->successResponse(['prompts' => $prompts]);
    }

    protected function getPrompt(array $request): array
    {
        $promptName = $request['params']['name'] ?? null;
        $arguments = $request['params']['arguments'] ?? [];

        return match ($promptName) {
            'analyze_dataset' => $this->getAnalyzeDatasetPrompt($arguments),
            'summarize_documents' => $this->getSummarizeDocumentsPrompt($arguments),
            default => $this->errorResponse('Unknown prompt: ' . $promptName, 404),
        };
    }

    protected function getAnalyzeDatasetPrompt(array $args): array
    {
        $datasetSlug = $args['dataset_slug'] ?? null;
        
        if (!$datasetSlug) {
            return $this->errorResponse('dataset_slug parameter required', 400);
        }

        $dataset = Dataset::where('slug', $datasetSlug)
            ->where('user_id', $this->authenticatedKey->user_id)
            ->first();

        if (!$dataset) {
            return $this->errorResponse('Dataset not found', 404);
        }

        $prompt = "Please analyze the dataset '{$dataset->name}' and provide insights on:\n\n";
        $prompt .= "1. Data structure and schema\n";
        $prompt .= "2. Content patterns and trends\n";
        $prompt .= "3. Data quality assessment\n";
        $prompt .= "4. Recommendations for improvement\n\n";
        $prompt .= "Dataset information:\n";
        $prompt .= "- Type: {$dataset->type}\n";
        $prompt .= "- Description: {$dataset->description}\n";
        $prompt .= "- Document count: " . $dataset->documents()->count() . "\n";

        return $this->successResponse([
            'description' => 'Analyze dataset and provide insights',
            'messages' => [
                [
                    'role' => 'user',
                    'content' => [
                        [
                            'type' => 'text',
                            'text' => $prompt,
                        ],
                    ],
                ],
            ],
        ]);
    }

    protected function getSummarizeDocumentsPrompt(array $args): array
    {
        $datasetSlug = $args['dataset_slug'] ?? null;
        
        if (!$datasetSlug) {
            return $this->errorResponse('dataset_slug parameter required', 400);
        }

        $dataset = Dataset::where('slug', $datasetSlug)
            ->where('user_id', $this->authenticatedKey->user_id)
            ->first();

        if (!$dataset) {
            return $this->errorResponse('Dataset not found', 404);
        }

        $prompt = "Please create a comprehensive summary of all documents in the '{$dataset->name}' dataset.\n\n";
        $prompt .= "Include:\n";
        $prompt .= "1. Overview of document types and topics\n";
        $prompt .= "2. Key themes and patterns\n";
        $prompt .= "3. Document relationships and connections\n";
        $prompt .= "4. Summary statistics\n\n";

        return $this->successResponse([
            'description' => 'Summarize all documents in dataset',
            'messages' => [
                [
                    'role' => 'user',
                    'content' => [
                        [
                            'type' => 'text',
                            'text' => $prompt,
                        ],
                    ],
                ],
            ],
        ]);
    }

    protected function hasPermission(string $permission): bool
    {
        return in_array($permission, $this->authenticatedKey->permissions ?? []);
    }

    protected function getDatasetMimeType(string $type): string
    {
        return match ($type) {
            'json' => 'application/json',
            'csv' => 'text/csv',
            'xml' => 'application/xml',
            'yaml' => 'application/yaml',
            'text' => 'text/plain',
            default => 'application/octet-stream',
        };
    }

    protected function getDocumentMimeType(string $type): string
    {
        return match ($type) {
            'markdown' => 'text/markdown',
            'html' => 'text/html',
            'json' => 'application/json',
            'text' => 'text/plain',
            default => 'text/plain',
        };
    }

    protected function successResponse(array $result): array
    {
        return [
            'jsonrpc' => '2.0',
            'id' => request()->json('id'),
            'result' => $result,
        ];
    }

    protected function errorResponse(string $message, int $code): array
    {
        return [
            'jsonrpc' => '2.0',
            'id' => request()->json('id'),
            'error' => [
                'code' => $code,
                'message' => $message,
            ],
        ];
    }
}
```

### 2. Create MCP Controller

**app/Http/Controllers/McpController.php**:
```php
<?php

namespace App\Http\Controllers;

use App\Services\McpServer;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class McpController extends Controller
{
    protected McpServer $mcpServer;

    public function __construct(McpServer $mcpServer)
    {
        $this->mcpServer = $mcpServer;
    }

    public function handle(Request $request): JsonResponse
    {
        $response = $this->mcpServer->handleRequest($request);
        
        $statusCode = 200;
        if (isset($response['error'])) {
            $statusCode = $response['error']['code'] ?? 500;
        }

        return response()->json($response, $statusCode);
    }
}
```

### 3. Add API Routes

**routes/api.php**:
```php
<?php

use App\Http\Controllers\McpController;
use Illuminate\Support\Facades\Route;

Route::post('/mcp', [McpController::class, 'handle'])
    ->name('mcp.handle');

// Health check endpoint
Route::get('/mcp/health', function () {
    return response()->json([
        'status' => 'ok',
        'service' => 'MCP Server',
        'version' => '1.0.0',
        'timestamp' => now()->toISOString(),
    ]);
})->name('mcp.health');
```

### 4. Test the MCP Server

Create a test script to verify the server works:

**tests/Feature/McpServerTest.php**:
```php
<?php

use App\Models\User;
use App\Models\ApiKey;
use App\Models\Dataset;

test('mcp server health check', function () {
    $response = $this->get('/api/mcp/health');
    
    $response->assertStatus(200)
             ->assertJson(['status' => 'ok']);
});

test('mcp server requires authentication', function () {
    $response = $this->postJson('/api/mcp', [
        'jsonrpc' => '2.0',
        'id' => 1,
        'method' => 'resources/list',
    ]);
    
    $response->assertStatus(401);
});

test('mcp server lists resources with valid api key', function () {
    $user = User::factory()->create();
    $apiKey = ApiKey::factory()->create([
        'user_id' => $user->id,
        'permissions' => ['read'],
    ]);
    
    $dataset = Dataset::factory()->create([
        'user_id' => $user->id,
        'status' => 'active',
    ]);
    
    $response = $this->postJson('/api/mcp', [
        'jsonrpc' => '2.0',
        'id' => 1,
        'method' => 'resources/list',
    ], [
        'Authorization' => 'Bearer ' . $apiKey->key,
    ]);
    
    $response->assertStatus(200)
             ->assertJsonStructure([
                 'jsonrpc',
                 'id',
                 'result' => [
                     'resources' => [
                         '*' => ['uri', 'name', 'description', 'mimeType']
                     ]
                 ]
             ]);
});
```

## Expected Outcomes

After completing Stage 05:

✅ MCP server responding at `/api/mcp` endpoint  
✅ API key authentication working  
✅ Resource listing and reading functional  
✅ Tool execution for CRUD operations  
✅ Prompt templates for common tasks  
✅ Comprehensive error handling and logging  

## Testing the Server

```bash
# Test health endpoint
curl http://localhost:8000/api/mcp/health

# Test with API key (replace YOUR_API_KEY)
curl -X POST http://localhost:8000/api/mcp \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer YOUR_API_KEY" \
  -d '{
    "jsonrpc": "2.0",
    "id": 1,
    "method": "resources/list"
  }'
```

## Next Stage
Proceed to **Stage 06: MCP Client Implementation** to create outgoing connections to Claude Code instances.

## Files Created/Modified
- `app/Services/McpServer.php` - Complete MCP protocol server
- `app/Http/Controllers/McpController.php` - HTTP controller for MCP requests
- `routes/api.php` - API routes for MCP endpoints
- `tests/Feature/McpServerTest.php` - Test coverage for MCP functionality

## Git Checkpoint
```bash
git add .
git commit -m "Stage 05: MCP server implementation

- Create complete MCP protocol server with resources, tools, and prompts
- Add API key authentication and permission system
- Implement CRUD operations for datasets and documents
- Add comprehensive error handling and logging
- Create test coverage for MCP functionality"
```