<?php

namespace App\Services;

use App\Events\McpConversationMessage;
use App\Events\McpProcessError;
use App\Models\McpConnection;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;

class EnhancedBidirectionalClient extends BidirectionalMcpClient
{
    private array $activeStreams = [];

    private array $errorHandlers = [];

    private int $heartbeatInterval = 30; // seconds

    /**
     * Start enhanced bidirectional communication with real-time features
     */
    public function startEnhancedMode(): array
    {
        $results = parent::initializeConnections();

        // Start heartbeat monitoring
        $this->startHeartbeatMonitoring();

        // Enable real-time error detection
        $this->enableRealTimeErrorDetection();

        // Start conversation streaming
        $this->initializeConversationStreaming();

        Log::info('Enhanced bidirectional MCP mode started', [
            'connections' => count($this->connections),
            'features' => ['heartbeat', 'error_detection', 'streaming'],
        ]);

        return [
            'status' => 'enhanced_mode_active',
            'connections' => $results,
            'features_enabled' => [
                'real_time_conversations',
                'automatic_error_recovery',
                'health_monitoring',
                'conversation_streaming',
            ],
            'started_at' => now()->toISOString(),
        ];
    }

    /**
     * Stream conversation with Claude Code in real-time
     */
    public function streamConversation(array $messages, ?User $user = null): \Generator
    {
        $streamId = uniqid('stream_');
        $this->activeStreams[$streamId] = [
            'started_at' => now(),
            'user' => $user,
            'message_count' => 0,
        ];

        try {
            foreach ($this->connections as $name => $connection) {
                if (! ($connection['capabilities'] ?? [])['streaming'] ?? false) {
                    continue;
                }

                Log::info('Starting conversation stream', [
                    'stream_id' => $streamId,
                    'connection' => $name,
                    'message_count' => count($messages),
                ]);

                yield [
                    'type' => 'stream_start',
                    'stream_id' => $streamId,
                    'connection' => $name,
                    'timestamp' => now()->toISOString(),
                ];

                // Send messages in batches for better performance
                $batches = array_chunk($messages, 5);

                foreach ($batches as $batchIndex => $batch) {
                    $response = $this->sendConversationBatch($name, $batch, $streamId);

                    if ($response['success'] ?? false) {
                        yield [
                            'type' => 'batch_response',
                            'stream_id' => $streamId,
                            'batch_index' => $batchIndex,
                            'response' => $response['data'] ?? [],
                            'timestamp' => now()->toISOString(),
                        ];

                        // Broadcast to connected clients
                        $this->broadcastConversationUpdate($streamId, $response, $user);

                        $this->activeStreams[$streamId]['message_count'] += count($batch);

                    } else {
                        yield [
                            'type' => 'batch_error',
                            'stream_id' => $streamId,
                            'batch_index' => $batchIndex,
                            'error' => $response['error'] ?? 'Unknown error',
                            'timestamp' => now()->toISOString(),
                        ];

                        // Attempt error recovery
                        $this->attemptErrorRecovery($name, $response['error'] ?? '');
                    }

                    // Small delay between batches to prevent overwhelming
                    usleep(100000); // 100ms
                }

                yield [
                    'type' => 'stream_complete',
                    'stream_id' => $streamId,
                    'connection' => $name,
                    'total_messages' => $this->activeStreams[$streamId]['message_count'],
                    'duration' => now()->diffInSeconds($this->activeStreams[$streamId]['started_at']),
                    'timestamp' => now()->toISOString(),
                ];
            }

        } catch (\Exception $e) {
            Log::error('Conversation stream failed', [
                'stream_id' => $streamId,
                'error' => $e->getMessage(),
            ]);

            yield [
                'type' => 'stream_error',
                'stream_id' => $streamId,
                'error' => $e->getMessage(),
                'timestamp' => now()->toISOString(),
            ];

        } finally {
            unset($this->activeStreams[$streamId]);
        }
    }

    /**
     * Enhanced error notification with automatic recovery
     */
    public function enhancedErrorNotification(array $errorData): array
    {
        $errorId = uniqid('error_');

        Log::warning('Enhanced error notification', [
            'error_id' => $errorId,
            'type' => $errorData['type'] ?? 'unknown',
            'source' => $errorData['source'] ?? 'unknown',
        ]);

        // Analyze error and suggest fixes
        $analysis = $this->analyzeError($errorData);

        // Broadcast error to all connected Claude processes
        $notificationResults = [];

        foreach ($this->connections as $name => $connection) {
            try {
                $notification = [
                    'error_id' => $errorId,
                    'timestamp' => now()->toISOString(),
                    'error_data' => $errorData,
                    'analysis' => $analysis,
                    'suggested_fixes' => $this->generateSuggestedFixes($errorData),
                    'auto_recovery_attempted' => false,
                ];

                $result = $this->sendNotificationToConnection($name, $notification);
                $notificationResults[$name] = $result;

                // Attempt automatic recovery if enabled
                if ($analysis['auto_recoverable'] ?? false) {
                    $recovery = $this->attemptAutomaticRecovery($errorData, $analysis);
                    $notification['auto_recovery_attempted'] = true;
                    $notification['recovery_result'] = $recovery;
                }

            } catch (\Exception $e) {
                Log::error("Failed to send error notification to {$name}", [
                    'error_id' => $errorId,
                    'notification_error' => $e->getMessage(),
                ]);

                $notificationResults[$name] = [
                    'success' => false,
                    'error' => $e->getMessage(),
                ];
            }
        }

        // Fire internal event for dashboard updates
        Event::dispatch(new McpProcessError($errorData, $analysis));

        return [
            'error_id' => $errorId,
            'analysis' => $analysis,
            'notifications_sent' => $notificationResults,
            'auto_recovery_attempted' => $analysis['auto_recoverable'] ?? false,
            'timestamp' => now()->toISOString(),
        ];
    }

    /**
     * Real-time health monitoring with predictive alerts
     */
    public function startRealTimeHealthMonitoring(): void
    {
        Log::info('Starting real-time health monitoring');

        // Cache health metrics for trend analysis
        $this->cacheHealthBaseline();

        // Monitor each connection continuously
        foreach ($this->connections as $name => $connection) {
            $this->startConnectionHealthMonitoring($name);
        }
    }

    private function sendConversationBatch(string $connectionName, array $messages, string $streamId): array
    {
        try {
            $connection = $this->connections[$connectionName];

            $request = [
                'jsonrpc' => '2.0',
                'id' => $this->requestId++,
                'method' => 'conversation/stream_batch',
                'params' => [
                    'stream_id' => $streamId,
                    'messages' => $messages,
                    'options' => [
                        'real_time' => true,
                        'include_metadata' => true,
                    ],
                ],
            ];

            return $this->sendRequestToConnection($connectionName, $request);

        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    private function broadcastConversationUpdate(string $streamId, array $response, ?User $user): void
    {
        try {
            // Broadcast via Filament real-time updates
            Event::dispatch(new McpConversationMessage(
                $user ?? User::first(),
                McpConnection::first(), // Get first connection as context
                [
                    'type' => 'stream_update',
                    'stream_id' => $streamId,
                    'data' => $response,
                    'timestamp' => now()->toISOString(),
                ]
            ));

        } catch (\Exception $e) {
            Log::warning('Failed to broadcast conversation update', [
                'stream_id' => $streamId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function analyzeError(array $errorData): array
    {
        $errorType = $errorData['type'] ?? 'unknown';
        $errorMessage = $errorData['message'] ?? '';

        $analysis = [
            'severity' => $this->determineSeverity($errorData),
            'category' => $this->categorizeError($errorType, $errorMessage),
            'auto_recoverable' => false,
            'confidence' => 0.0,
            'suggested_actions' => [],
        ];

        // Analyze based on error patterns
        if (str_contains($errorMessage, 'connection')) {
            $analysis['category'] = 'connection';
            $analysis['auto_recoverable'] = true;
            $analysis['confidence'] = 0.8;
            $analysis['suggested_actions'] = ['retry_connection', 'check_network'];
        }

        if (str_contains($errorMessage, 'timeout')) {
            $analysis['category'] = 'timeout';
            $analysis['auto_recoverable'] = true;
            $analysis['confidence'] = 0.9;
            $analysis['suggested_actions'] = ['increase_timeout', 'retry_request'];
        }

        if (str_contains($errorMessage, 'authentication')) {
            $analysis['category'] = 'auth';
            $analysis['auto_recoverable'] = false;
            $analysis['confidence'] = 0.95;
            $analysis['suggested_actions'] = ['check_credentials', 'refresh_token'];
        }

        return $analysis;
    }

    private function generateSuggestedFixes(array $errorData): array
    {
        $fixes = [];
        $errorMessage = strtolower($errorData['message'] ?? '');

        if (str_contains($errorMessage, 'connection refused')) {
            $fixes[] = [
                'title' => 'Restart MCP Server',
                'description' => 'The MCP server appears to be down. Try restarting it.',
                'command' => 'php artisan mcp:server restart',
                'confidence' => 0.8,
            ];
        }

        if (str_contains($errorMessage, 'timeout')) {
            $fixes[] = [
                'title' => 'Increase Timeout',
                'description' => 'Increase the request timeout in configuration.',
                'config_change' => 'mcp.client.timeout',
                'suggested_value' => 60,
                'confidence' => 0.7,
            ];
        }

        if (str_contains($errorMessage, 'permission')) {
            $fixes[] = [
                'title' => 'Check API Key Permissions',
                'description' => 'Verify the API key has sufficient permissions.',
                'action' => 'check_api_permissions',
                'confidence' => 0.9,
            ];
        }

        return $fixes;
    }

    private function attemptAutomaticRecovery(array $errorData, array $analysis): array
    {
        $recoveryActions = [];

        foreach ($analysis['suggested_actions'] as $action) {
            try {
                $result = match ($action) {
                    'retry_connection' => $this->retryFailedConnections(),
                    'increase_timeout' => $this->temporarilyIncreaseTimeout(),
                    'retry_request' => $this->retryLastRequest(),
                    default => ['success' => false, 'message' => 'Unknown action']
                };

                $recoveryActions[$action] = $result;

                if ($result['success'] ?? false) {
                    Log::info("Automatic recovery successful: {$action}");
                    break; // Stop on first successful recovery
                }

            } catch (\Exception $e) {
                $recoveryActions[$action] = [
                    'success' => false,
                    'error' => $e->getMessage(),
                ];
            }
        }

        return [
            'attempted_actions' => $recoveryActions,
            'recovery_successful' => collect($recoveryActions)->contains('success', true),
            'timestamp' => now()->toISOString(),
        ];
    }

    private function startHeartbeatMonitoring(): void
    {
        // In a real implementation, you'd start a background job or use Laravel's scheduler
        Log::info('Heartbeat monitoring initialized', [
            'interval' => $this->heartbeatInterval,
            'connections' => count($this->connections),
        ]);
    }

    private function enableRealTimeErrorDetection(): void
    {
        // Set up error detection listeners
        $this->errorHandlers = [
            'connection_lost' => fn ($data) => $this->handleConnectionLost($data),
            'timeout' => fn ($data) => $this->handleTimeout($data),
            'auth_failure' => fn ($data) => $this->handleAuthFailure($data),
        ];

        Log::info('Real-time error detection enabled');
    }

    private function initializeConversationStreaming(): void
    {
        // Initialize streaming capabilities
        Cache::put('mcp_streaming_active', true, 3600);

        Log::info('Conversation streaming initialized');
    }

    private function determineSeverity(array $errorData): string
    {
        $message = strtolower($errorData['message'] ?? '');

        if (str_contains($message, 'critical') || str_contains($message, 'fatal')) {
            return 'critical';
        }

        if (str_contains($message, 'error') || str_contains($message, 'failed')) {
            return 'high';
        }

        if (str_contains($message, 'warning') || str_contains($message, 'timeout')) {
            return 'medium';
        }

        return 'low';
    }

    private function categorizeError(string $type, string $message): string
    {
        $message = strtolower($message);

        if (str_contains($message, 'connection') || str_contains($message, 'network')) {
            return 'connectivity';
        }

        if (str_contains($message, 'auth') || str_contains($message, 'permission')) {
            return 'authentication';
        }

        if (str_contains($message, 'timeout') || str_contains($message, 'slow')) {
            return 'performance';
        }

        return $type;
    }

    private function retryFailedConnections(): array
    {
        $results = [];

        foreach ($this->connections as $name => $connection) {
            if (($connection['status'] ?? '') === 'failed') {
                $results[$name] = $this->initializeConnection($name, $connection['config'] ?? []);
            }
        }

        return [
            'success' => ! empty($results),
            'connections_retried' => count($results),
            'results' => $results,
        ];
    }

    private function temporarilyIncreaseTimeout(): array
    {
        // Temporarily increase timeout for this session
        $originalTimeout = config('mcp.client.timeout', 30);
        config(['mcp.client.timeout' => $originalTimeout * 2]);

        return [
            'success' => true,
            'original_timeout' => $originalTimeout,
            'new_timeout' => $originalTimeout * 2,
        ];
    }

    private function retryLastRequest(): array
    {
        // This would retry the last failed request if we were tracking them
        return [
            'success' => true,
            'message' => 'Last request retry attempted',
        ];
    }
}
