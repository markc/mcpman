<?php

namespace App\Services;

use App\Models\Dataset;
use App\Models\Document;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class McpServer
{
    private ?\App\Models\ApiKey $apiKey = null;

    public function handleRequest(array $request, ?\App\Models\ApiKey $apiKey = null): array
    {
        try {
            $this->apiKey = $apiKey;
            $method = $request['method'] ?? null;
            $params = $request['params'] ?? [];

            Log::info('MCP Server received request', ['method' => $method, 'params' => $params]);

            return match ($method) {
                'initialize' => $this->initialize($params),
                'tools/list' => $this->hasPermission('tools') ? $this->listTools() : $this->errorResponse('Insufficient permissions', -32603),
                'tools/call' => $this->hasPermission('tools') ? $this->callTool($params) : $this->errorResponse('Insufficient permissions', -32603),
                'resources/list' => $this->hasPermission('read') ? $this->listResources() : $this->errorResponse('Insufficient permissions', -32603),
                'resources/read' => $this->hasPermission('read') ? $this->readResource($params) : $this->errorResponse('Insufficient permissions', -32603),
                'prompts/list' => $this->listPrompts(),
                'prompts/get' => $this->getPrompt($params),
                default => $this->errorResponse('Unknown method', -32601)
            };
        } catch (\Exception $e) {
            Log::error('MCP Server error', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);

            return $this->errorResponse($e->getMessage(), -32603);
        }
    }

    private function initialize(array $params): array
    {
        return [
            'jsonrpc' => '2.0',
            'result' => [
                'protocolVersion' => '2024-11-05',
                'capabilities' => [
                    'tools' => [
                        'listChanged' => false,
                    ],
                    'resources' => [
                        'subscribe' => false,
                        'listChanged' => false,
                    ],
                    'prompts' => [
                        'listChanged' => false,
                    ],
                ],
                'serverInfo' => [
                    'name' => 'Laravel-Filament-MCP',
                    'version' => '1.0.0',
                ],
            ],
        ];
    }

    private function listTools(): array
    {
        return [
            'jsonrpc' => '2.0',
            'result' => [
                'tools' => [
                    [
                        'name' => 'create_dataset',
                        'description' => 'Create a new dataset',
                        'inputSchema' => [
                            'type' => 'object',
                            'properties' => [
                                'name' => ['type' => 'string'],
                                'description' => ['type' => 'string'],
                                'type' => ['type' => 'string', 'enum' => ['json', 'csv', 'xml', 'yaml', 'text']],
                                'schema' => ['type' => 'object'],
                                'metadata' => ['type' => 'object'],
                            ],
                            'required' => ['name', 'type'],
                        ],
                    ],
                    [
                        'name' => 'list_datasets',
                        'description' => 'List all datasets',
                        'inputSchema' => [
                            'type' => 'object',
                            'properties' => [
                                'status' => ['type' => 'string', 'enum' => ['active', 'archived', 'processing']],
                                'type' => ['type' => 'string', 'enum' => ['json', 'csv', 'xml', 'yaml', 'text']],
                            ],
                        ],
                    ],
                    [
                        'name' => 'create_document',
                        'description' => 'Create a new document',
                        'inputSchema' => [
                            'type' => 'object',
                            'properties' => [
                                'title' => ['type' => 'string'],
                                'content' => ['type' => 'string'],
                                'type' => ['type' => 'string', 'enum' => ['text', 'json', 'markdown', 'html']],
                                'dataset_id' => ['type' => 'integer'],
                                'metadata' => ['type' => 'object'],
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
                                'id' => ['type' => 'integer'],
                                'title' => ['type' => 'string'],
                                'content' => ['type' => 'string'],
                                'type' => ['type' => 'string', 'enum' => ['text', 'json', 'markdown', 'html']],
                                'tags' => ['type' => 'array', 'items' => ['type' => 'string']],
                                'status' => ['type' => 'string', 'enum' => ['published', 'draft', 'archived']],
                                'metadata' => ['type' => 'object'],
                            ],
                            'required' => ['id'],
                        ],
                    ],
                    [
                        'name' => 'search_documents',
                        'description' => 'Search documents by content',
                        'inputSchema' => [
                            'type' => 'object',
                            'properties' => [
                                'query' => ['type' => 'string'],
                                'dataset_id' => ['type' => 'integer'],
                                'type' => ['type' => 'string'],
                            ],
                            'required' => ['query'],
                        ],
                    ],
                ],
            ],
        ];
    }

    private function hasPermission(string $permission): bool
    {
        // Allow all permissions in local development mode
        if (app()->environment('local')) {
            return true;
        }

        // Check API key permissions
        if (! $this->apiKey) {
            return false;
        }

        return $this->apiKey->hasPermission($permission);
    }

    private function getUserId(): int
    {
        // In local development mode, use the default user
        if (app()->environment('local')) {
            $user = \App\Models\User::where('email', 'admin@example.com')->first();

            return $user ? $user->id : 1;
        }

        // Use the API key's user ID
        return $this->apiKey ? $this->apiKey->user_id : 1;
    }

    private function callTool(array $params): array
    {
        $name = $params['name'] ?? null;
        $arguments = $params['arguments'] ?? [];

        return match ($name) {
            'create_dataset' => $this->createDataset($arguments),
            'list_datasets' => $this->listDatasetsImpl($arguments),
            'create_document' => $this->createDocument($arguments),
            'update_document' => $this->updateDocument($arguments),
            'search_documents' => $this->searchDocuments($arguments),
            default => $this->errorResponse('Unknown tool', -32602)
        };
    }

    private function createDataset(array $args): array
    {
        $validator = Validator::make($args, [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'type' => 'required|in:json,csv,xml,yaml,text',
            'schema' => 'nullable|array',
            'metadata' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse('Validation failed: '.implode(', ', $validator->errors()->all()), -32602);
        }

        $dataset = Dataset::create([
            'name' => $args['name'],
            'slug' => str($args['name'])->slug(),
            'description' => $args['description'] ?? null,
            'type' => $args['type'],
            'schema' => $args['schema'] ?? null,
            'metadata' => $args['metadata'] ?? null,
            'status' => 'active',
            'user_id' => $this->getUserId(),
        ]);

        return [
            'jsonrpc' => '2.0',
            'result' => [
                'content' => [
                    [
                        'type' => 'text',
                        'text' => "Dataset '{$dataset->name}' created successfully with ID: {$dataset->id}",
                    ],
                ],
            ],
        ];
    }

    private function listDatasetsImpl(array $args): array
    {
        $query = Dataset::where('user_id', $this->getUserId());

        if (isset($args['status'])) {
            $query->where('status', $args['status']);
        }

        if (isset($args['type'])) {
            $query->where('type', $args['type']);
        }

        $datasets = $query->get(['id', 'name', 'description', 'type', 'status', 'created_at']);

        $text = "Found {$datasets->count()} datasets:\n\n";
        foreach ($datasets as $dataset) {
            $text .= "• #{$dataset->id} {$dataset->name} ({$dataset->type}) - {$dataset->status}\n";
            if ($dataset->description) {
                $text .= "  {$dataset->description}\n";
            }
            $text .= "\n";
        }

        return [
            'jsonrpc' => '2.0',
            'result' => [
                'content' => [
                    [
                        'type' => 'text',
                        'text' => $text,
                    ],
                ],
            ],
        ];
    }

    private function createDocument(array $args): array
    {
        $validator = Validator::make($args, [
            'title' => 'required|string|max:255',
            'content' => 'required|string',
            'type' => 'nullable|in:text,json,markdown,html',
            'dataset_id' => 'nullable|exists:datasets,id',
            'metadata' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse('Validation failed: '.implode(', ', $validator->errors()->all()), -32602);
        }

        $document = Document::create([
            'title' => $args['title'],
            'slug' => str($args['title'])->slug(),
            'content' => $args['content'],
            'type' => $args['type'] ?? 'text',
            'dataset_id' => $args['dataset_id'] ?? null,
            'metadata' => $args['metadata'] ?? null,
            'user_id' => $this->getUserId(),
        ]);

        return [
            'jsonrpc' => '2.0',
            'result' => [
                'content' => [
                    [
                        'type' => 'text',
                        'text' => "Document '{$document->title}' created successfully with ID: {$document->id}",
                    ],
                ],
            ],
        ];
    }

    private function updateDocument(array $args): array
    {
        $validator = Validator::make($args, [
            'id' => 'required|integer|exists:documents,id',
            'title' => 'sometimes|string|max:255',
            'content' => 'sometimes|string',
            'type' => 'sometimes|in:text,json,markdown,html',
            'tags' => 'sometimes|array',
            'status' => 'sometimes|in:published,draft,archived',
            'metadata' => 'sometimes|array',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse('Validation failed: '.implode(', ', $validator->errors()->all()), -32602);
        }

        $document = Document::where('id', $args['id'])
            ->where('user_id', $this->getUserId())
            ->first();

        if (! $document) {
            return $this->errorResponse('Document not found or access denied', -32602);
        }

        // Update only provided fields
        $updateData = array_filter([
            'title' => $args['title'] ?? null,
            'content' => $args['content'] ?? null,
            'type' => $args['type'] ?? null,
            'tags' => $args['tags'] ?? null,
            'status' => $args['status'] ?? null,
            'metadata' => $args['metadata'] ?? null,
        ], fn ($value) => $value !== null);

        $document->update($updateData);

        return [
            'jsonrpc' => '2.0',
            'result' => [
                'content' => [
                    [
                        'type' => 'text',
                        'text' => "Document '{$document->title}' updated successfully",
                    ],
                ],
            ],
        ];
    }

    private function searchDocuments(array $args): array
    {
        $query = Document::where('user_id', $this->getUserId());

        if (isset($args['query'])) {
            $query->where(function ($q) use ($args) {
                $q->where('title', 'like', "%{$args['query']}%")
                    ->orWhere('content', 'like', "%{$args['query']}%");
            });
        }

        if (isset($args['dataset_id'])) {
            $query->where('dataset_id', $args['dataset_id']);
        }

        if (isset($args['type'])) {
            $query->where('type', $args['type']);
        }

        $documents = $query->limit(10)->get(['id', 'title', 'type', 'created_at']);

        $text = "Found {$documents->count()} documents:\n\n";
        foreach ($documents as $document) {
            $text .= "• #{$document->id} {$document->title} ({$document->type})\n";
            $text .= "  Created: {$document->created_at->diffForHumans()}\n\n";
        }

        return [
            'jsonrpc' => '2.0',
            'result' => [
                'content' => [
                    [
                        'type' => 'text',
                        'text' => $text,
                    ],
                ],
            ],
        ];
    }

    private function listResources(): array
    {
        return [
            'jsonrpc' => '2.0',
            'result' => [
                'resources' => [
                    [
                        'uri' => 'filament://datasets',
                        'name' => 'Datasets',
                        'description' => 'All available datasets',
                        'mimeType' => 'application/json',
                    ],
                    [
                        'uri' => 'filament://documents',
                        'name' => 'Documents',
                        'description' => 'All available documents',
                        'mimeType' => 'application/json',
                    ],
                ],
            ],
        ];
    }

    private function readResource(array $params): array
    {
        $uri = $params['uri'] ?? null;

        return match ($uri) {
            'filament://datasets' => $this->getDatasets(),
            'filament://documents' => $this->getDocuments(),
            default => $this->errorResponse('Unknown resource', -32602)
        };
    }

    private function getDatasets(): array
    {
        $datasets = Dataset::with('user')->get();

        return [
            'jsonrpc' => '2.0',
            'result' => [
                'contents' => [
                    [
                        'uri' => 'filament://datasets',
                        'mimeType' => 'application/json',
                        'text' => json_encode($datasets->toArray(), JSON_PRETTY_PRINT),
                    ],
                ],
            ],
        ];
    }

    private function getDocuments(): array
    {
        $documents = Document::with(['dataset', 'user'])->get();

        return [
            'jsonrpc' => '2.0',
            'result' => [
                'contents' => [
                    [
                        'uri' => 'filament://documents',
                        'mimeType' => 'application/json',
                        'text' => json_encode($documents->toArray(), JSON_PRETTY_PRINT),
                    ],
                ],
            ],
        ];
    }

    private function listPrompts(): array
    {
        return [
            'jsonrpc' => '2.0',
            'result' => [
                'prompts' => [
                    [
                        'name' => 'analyze_dataset',
                        'description' => 'Analyze a dataset structure and content',
                        'arguments' => [
                            [
                                'name' => 'dataset_id',
                                'description' => 'ID of the dataset to analyze',
                                'required' => true,
                            ],
                        ],
                    ],
                    [
                        'name' => 'summarize_documents',
                        'description' => 'Create a summary of documents in a dataset',
                        'arguments' => [
                            [
                                'name' => 'dataset_id',
                                'description' => 'ID of the dataset to summarize',
                                'required' => false,
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }

    private function getPrompt(array $params): array
    {
        $name = $params['name'] ?? null;
        $arguments = $params['arguments'] ?? [];

        return match ($name) {
            'analyze_dataset' => $this->analyzeDatasetPrompt($arguments),
            'summarize_documents' => $this->summarizeDocumentsPrompt($arguments),
            default => $this->errorResponse('Unknown prompt', -32602)
        };
    }

    private function analyzeDatasetPrompt(array $args): array
    {
        $datasetId = $args['dataset_id'] ?? null;

        if (! $datasetId) {
            return $this->errorResponse('dataset_id is required', -32602);
        }

        $dataset = Dataset::with('documents')->find($datasetId);

        if (! $dataset) {
            return $this->errorResponse('Dataset not found', -32602);
        }

        $prompt = "Please analyze this dataset:\n\n";
        $prompt .= "Name: {$dataset->name}\n";
        $prompt .= "Type: {$dataset->type}\n";
        $prompt .= 'Description: '.($dataset->description ?: 'None')."\n";
        $prompt .= "Documents: {$dataset->documents->count()}\n\n";

        if ($dataset->schema) {
            $prompt .= "Schema:\n".json_encode($dataset->schema, JSON_PRETTY_PRINT)."\n\n";
        }

        $prompt .= "Sample documents:\n";
        foreach ($dataset->documents->take(3) as $doc) {
            $prompt .= "- {$doc->title}\n";
        }

        return [
            'jsonrpc' => '2.0',
            'result' => [
                'description' => 'Analysis prompt for dataset',
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
            ],
        ];
    }

    private function summarizeDocumentsPrompt(array $args): array
    {
        $datasetId = $args['dataset_id'] ?? null;

        $query = Document::query();
        if ($datasetId) {
            $query->where('dataset_id', $datasetId);
        }

        $documents = $query->get();

        $prompt = "Please create a summary of these documents:\n\n";
        foreach ($documents as $doc) {
            $prompt .= "Title: {$doc->title}\n";
            $prompt .= "Type: {$doc->type}\n";
            $prompt .= 'Content: '.substr($doc->content, 0, 200)."...\n\n";
        }

        return [
            'jsonrpc' => '2.0',
            'result' => [
                'description' => 'Document summarization prompt',
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
            ],
        ];
    }

    private function errorResponse(string $message, int $code): array
    {
        return [
            'jsonrpc' => '2.0',
            'error' => [
                'code' => $code,
                'message' => $message,
            ],
        ];
    }
}
