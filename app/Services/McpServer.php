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

            Log::info('MCP Server received request', [
                'method' => $method,
                'params_count' => count($params),
                'has_params' => ! empty($params),
            ]);

            return match ($method) {
                'initialize' => $this->initialize($params),
                'tools/list' => $this->hasPermission('tools') ? $this->listTools() : $this->errorResponse('Insufficient permissions', -32603),
                'tools/call' => $this->hasPermission('tools') ? $this->callTool($params) : $this->errorResponse('Insufficient permissions', -32603),
                'resources/list' => $this->hasPermission('read') ? $this->listResources() : $this->errorResponse('Insufficient permissions', -32603),
                'resources/read' => $this->hasPermission('read') ? $this->readResource($params) : $this->errorResponse('Insufficient permissions', -32603),
                'prompts/list' => $this->listPrompts(),
                'prompts/get' => $this->getPrompt($params),
                'error_notification' => $this->handleErrorNotification($params),
                'get_file_content' => $this->hasPermission('read') ? $this->getFileContent($params) : $this->errorResponse('Insufficient permissions', -32603),
                'apply_fix' => $this->hasPermission('write') ? $this->applyFix($params) : $this->errorResponse('Insufficient permissions', -32603),
                default => $this->errorResponse('Unknown method', -32601)
            };
        } catch (\Exception $e) {
            Log::error('MCP Server error', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

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

    /**
     * Handle error notification from log monitoring
     */
    private function handleErrorNotification(array $params): array
    {
        Log::info('Received error notification from log monitor', [
            'error_type' => $params['error_details']['type'] ?? 'unknown',
            'file' => $params['error_details']['file_path'] ?? 'unknown',
            'severity' => $params['severity'] ?? 'unknown',
        ]);

        // Store error for tracking and analysis
        $this->storeErrorNotification($params);

        // Generate response with fix suggestions
        return [
            'jsonrpc' => '2.0',
            'result' => [
                'status' => 'received',
                'error_id' => $this->generateErrorId($params),
                'analysis' => $this->analyzeError($params),
                'suggestions' => $params['suggested_actions'] ?? [],
                'auto_fix_available' => $params['auto_fix_recommended'] ?? false,
                'timestamp' => now()->toISOString(),
            ],
        ];
    }

    /**
     * Get file content for error analysis
     */
    private function getFileContent(array $params): array
    {
        $filePath = $params['file_path'] ?? null;
        $startLine = $params['start_line'] ?? 1;
        $endLine = $params['end_line'] ?? null;

        if (! $filePath || ! file_exists($filePath)) {
            return $this->errorResponse('File not found', -32602);
        }

        try {
            $lines = file($filePath, FILE_IGNORE_NEW_LINES);
            $totalLines = count($lines);

            $start = max(0, $startLine - 1);
            $end = $endLine ? min($totalLines, $endLine) : $totalLines;

            $content = array_slice($lines, $start, $end - $start);

            return [
                'jsonrpc' => '2.0',
                'result' => [
                    'file_path' => $filePath,
                    'content' => $content,
                    'line_range' => [
                        'start' => $startLine,
                        'end' => $start + count($content),
                    ],
                    'total_lines' => $totalLines,
                ],
            ];
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to read file: '.$e->getMessage(), -32603);
        }
    }

    /**
     * Apply automated fix
     */
    private function applyFix(array $params): array
    {
        $filePath = $params['file_path'] ?? null;
        $fixType = $params['fix_type'] ?? null;
        $fixData = $params['fix_data'] ?? [];

        if (! $filePath || ! file_exists($filePath)) {
            return $this->errorResponse('File not found', -32602);
        }

        try {
            $result = match ($fixType) {
                'remove_duplicate_method' => $this->removeDuplicateMethod($filePath, $fixData),
                'add_missing_import' => $this->addMissingImport($filePath, $fixData),
                'fix_syntax_error' => $this->fixSyntaxError($filePath, $fixData),
                default => throw new \InvalidArgumentException("Unknown fix type: {$fixType}"),
            };

            return [
                'jsonrpc' => '2.0',
                'result' => [
                    'status' => 'applied',
                    'fix_type' => $fixType,
                    'file_path' => $filePath,
                    'changes_made' => $result,
                    'timestamp' => now()->toISOString(),
                ],
            ];
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to apply fix: '.$e->getMessage(), -32603);
        }
    }

    /**
     * Store error notification for tracking
     */
    private function storeErrorNotification(array $params): void
    {
        // Could store in database for analytics and tracking
        Log::info('Error stored for tracking', [
            'error_id' => $this->generateErrorId($params),
            'type' => $params['error_details']['type'] ?? 'unknown',
            'file' => $params['error_details']['file_path'] ?? 'unknown',
        ]);
    }

    /**
     * Generate unique error ID
     */
    private function generateErrorId(array $params): string
    {
        $errorType = $params['error_details']['type'] ?? 'unknown';
        $filePath = $params['error_details']['file_path'] ?? 'unknown';
        $timestamp = $params['timestamp'] ?? now()->toISOString();

        return 'error_'.md5($errorType.$filePath.$timestamp);
    }

    /**
     * Analyze error and provide context
     */
    private function analyzeError(array $params): array
    {
        $errorType = $params['error_details']['type'] ?? 'unknown';
        $filePath = $params['error_details']['file_path'] ?? null;
        $lineNumber = $params['error_details']['line_number'] ?? null;

        return [
            'error_type' => $errorType,
            'severity' => $params['severity'] ?? 'unknown',
            'file_analysis' => $filePath ? $this->analyzeFile($filePath) : null,
            'line_context' => $lineNumber ? $this->getLineContext($filePath, $lineNumber) : null,
            'potential_causes' => $this->getPotentialCauses($errorType),
            'fix_complexity' => $this->getFixComplexity($errorType),
        ];
    }

    /**
     * Analyze file for context
     */
    private function analyzeFile(string $filePath): array
    {
        return [
            'file_type' => pathinfo($filePath, PATHINFO_EXTENSION),
            'file_size' => filesize($filePath),
            'line_count' => count(file($filePath)),
            'namespace' => $this->extractNamespace($filePath),
            'class_name' => $this->extractClassName($filePath),
        ];
    }

    /**
     * Get context around specific line
     */
    private function getLineContext(string $filePath, int $lineNumber): array
    {
        if (! file_exists($filePath)) {
            return [];
        }

        $lines = file($filePath, FILE_IGNORE_NEW_LINES);
        $start = max(0, $lineNumber - 6);
        $end = min(count($lines), $lineNumber + 5);

        $context = [];
        for ($i = $start; $i < $end; $i++) {
            $context[$i + 1] = [
                'line_number' => $i + 1,
                'content' => $lines[$i] ?? '',
                'is_error_line' => ($i + 1) === $lineNumber,
            ];
        }

        return $context;
    }

    /**
     * Get potential causes for error types
     */
    private function getPotentialCauses(string $errorType): array
    {
        return match ($errorType) {
            'duplicate_method' => [
                'Copy/paste error',
                'Merge conflict resolution',
                'Multiple trait inclusions',
                'IDE auto-completion mistake',
            ],
            'class_not_found' => [
                'Missing use statement',
                'Incorrect namespace',
                'Autoloader not updated',
                'Typo in class name',
            ],
            'syntax_error' => [
                'Missing semicolon',
                'Unmatched brackets',
                'Invalid PHP syntax',
                'Encoding issues',
            ],
            default => [
                'Recent code changes',
                'Configuration issues',
                'Dependency problems',
            ],
        };
    }

    /**
     * Get fix complexity assessment
     */
    private function getFixComplexity(string $errorType): string
    {
        return match ($errorType) {
            'duplicate_method', 'syntax_error' => 'simple',
            'class_not_found', 'method_not_found' => 'medium',
            'fatal', 'exception' => 'complex',
            default => 'unknown',
        };
    }

    /**
     * Extract namespace from PHP file
     */
    private function extractNamespace(string $filePath): ?string
    {
        $content = file_get_contents($filePath);
        if (preg_match('/namespace\s+([^;]+);/', $content, $matches)) {
            return trim($matches[1]);
        }

        return null;
    }

    /**
     * Extract class name from PHP file
     */
    private function extractClassName(string $filePath): ?string
    {
        $content = file_get_contents($filePath);
        if (preg_match('/class\s+(\w+)/', $content, $matches)) {
            return $matches[1];
        }

        return null;
    }

    /**
     * Remove duplicate method from file
     */
    private function removeDuplicateMethod(string $filePath, array $fixData): array
    {
        // Implementation would analyze and remove duplicate methods
        return ['action' => 'remove_duplicate_method', 'status' => 'simulated'];
    }

    /**
     * Add missing import to file
     */
    private function addMissingImport(string $filePath, array $fixData): array
    {
        // Implementation would add missing use statements
        return ['action' => 'add_missing_import', 'status' => 'simulated'];
    }

    /**
     * Fix syntax error in file
     */
    private function fixSyntaxError(string $filePath, array $fixData): array
    {
        // Implementation would fix common syntax errors
        return ['action' => 'fix_syntax_error', 'status' => 'simulated'];
    }
}
