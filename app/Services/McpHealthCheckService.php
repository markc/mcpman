<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;

class McpHealthCheckService
{
    private array $healthStatus = [];

    private ?array $cachedDashboardData = null;

    public function __construct()
    {
        $this->healthStatus = [
            'claude_available' => false,
            'claude_authenticated' => false,
            'mcp_server_responsive' => false,
            'last_check' => null,
            'errors' => [],
            'performance_metrics' => [],
        ];
    }

    /**
     * Perform comprehensive health check
     */
    public function performHealthCheck(bool $useCache = true): array
    {
        $cacheKey = config('mcp.health_check.cache_key', 'mcp_health_status');
        $cacheTtl = config('mcp.health_check.cache_ttl', 300);

        if ($useCache) {
            $cached = Cache::get($cacheKey);
            if ($cached) {
                return $cached;
            }
        }

        Log::info('Performing MCP health check');

        $startTime = microtime(true);

        // Check Claude CLI availability
        $this->checkClaudeAvailability();

        // Small delay to avoid process conflicts
        if ($this->healthStatus['claude_available']) {
            usleep(500000); // 0.5 second delay
        }

        // Check Claude authentication
        $this->checkClaudeAuthentication();

        // Small delay to avoid process conflicts
        if ($this->healthStatus['claude_authenticated']) {
            usleep(500000); // 0.5 second delay
        }

        // Check MCP server responsiveness
        $this->checkMcpServerResponsiveness();

        // Calculate overall health score
        $this->calculateHealthScore();

        $this->healthStatus['last_check'] = now()->toISOString();
        $this->healthStatus['check_duration_ms'] = round((microtime(true) - $startTime) * 1000, 2);

        // Cache the results
        Cache::put($cacheKey, $this->healthStatus, $cacheTtl);

        Log::info('MCP health check completed', [
            'overall_health' => $this->healthStatus['overall_health'] ?? 'unknown',
            'duration_ms' => $this->healthStatus['check_duration_ms'],
        ]);

        return $this->healthStatus;
    }

    /**
     * Check if Claude CLI is available
     */
    private function checkClaudeAvailability(): void
    {
        try {
            $timeout = config('mcp.health_check.timeout', 15);
            $result = Process::timeout($timeout)->run(['claude', '--version']);

            if ($result->successful()) {
                $this->healthStatus['claude_available'] = true;
                $this->healthStatus['claude_version'] = trim($result->output());
                Log::debug('Claude CLI available', ['version' => $this->healthStatus['claude_version']]);
            } else {
                $this->healthStatus['claude_available'] = false;
                $this->addError('Claude CLI not available', [
                    'exit_code' => $result->exitCode(),
                    'error' => $result->errorOutput(),
                ]);
            }
        } catch (\Exception $e) {
            $this->healthStatus['claude_available'] = false;
            $this->addError('Claude CLI check failed', ['exception' => $e->getMessage()]);
        }
    }

    /**
     * Check Claude authentication status
     */
    private function checkClaudeAuthentication(): void
    {
        if (! $this->healthStatus['claude_available']) {
            $this->healthStatus['claude_authenticated'] = false;

            return;
        }

        try {
            $timeout = config('mcp.claude.auth_timeout', 30); // Increased timeout
            // Use a minimal prompt to test Claude authentication quickly
            $result = Process::timeout($timeout)->run(['claude', '-p', 'hi']);

            if ($result->successful() && ! empty(trim($result->output()))) {
                $this->healthStatus['claude_authenticated'] = true;
                Log::debug('Claude authentication verified');
            } else {
                $this->healthStatus['claude_authenticated'] = false;
                $this->addError('Claude authentication failed', [
                    'exit_code' => $result->exitCode(),
                    'output' => $result->output(),
                    'error' => $result->errorOutput(),
                    'suggestion' => 'Run `claude auth` to authenticate with Claude',
                ]);
            }
        } catch (\Exception $e) {
            $this->healthStatus['claude_authenticated'] = false;
            $this->addError('Claude auth check failed', ['exception' => $e->getMessage()]);
        }
    }

    /**
     * Check MCP server responsiveness with a simple test
     */
    private function checkMcpServerResponsiveness(): void
    {
        if (! $this->healthStatus['claude_authenticated']) {
            $this->healthStatus['mcp_server_responsive'] = false;

            return;
        }

        try {
            $timeout = config('mcp.health_check.timeout', 30); // Increased timeout
            $testCommand = ['claude', '-p', 'ok'];

            $startTime = microtime(true);
            $result = Process::timeout($timeout)->run($testCommand);
            $responseTime = round((microtime(true) - $startTime) * 1000, 2);

            $this->healthStatus['performance_metrics']['response_time_ms'] = $responseTime;

            if ($result->successful() && ! empty(trim($result->output()))) {
                $this->healthStatus['mcp_server_responsive'] = true;
                $this->healthStatus['performance_metrics']['last_successful_response'] = now()->toISOString();
                Log::debug('MCP server responsive', ['response_time_ms' => $responseTime]);
            } else {
                $this->healthStatus['mcp_server_responsive'] = false;
                $this->addError('MCP server not responsive', [
                    'exit_code' => $result->exitCode(),
                    'response_time_ms' => $responseTime,
                    'output_empty' => empty(trim($result->output())),
                ]);
            }
        } catch (\Exception $e) {
            $this->healthStatus['mcp_server_responsive'] = false;
            $this->addError('MCP server test failed', ['exception' => $e->getMessage()]);
        }
    }

    /**
     * Calculate overall health score
     */
    private function calculateHealthScore(): void
    {
        $components = [
            'claude_available' => 30,
            'claude_authenticated' => 35,
            'mcp_server_responsive' => 35,
        ];

        $score = 0;
        foreach ($components as $component => $weight) {
            if ($this->healthStatus[$component] ?? false) {
                $score += $weight;
            }
        }

        $this->healthStatus['health_score'] = $score;
        $this->healthStatus['overall_health'] = match (true) {
            $score >= 90 => 'excellent',
            $score >= 70 => 'good',
            $score >= 50 => 'fair',
            $score >= 30 => 'poor',
            default => 'critical',
        };

        // Add performance assessment
        $responseTime = $this->healthStatus['performance_metrics']['response_time_ms'] ?? null;
        if ($responseTime !== null) {
            $slowThreshold = config('mcp.monitoring.slow_query_threshold', 10000);
            $this->healthStatus['performance_assessment'] = $responseTime > $slowThreshold ? 'slow' : 'fast';
        }
    }

    /**
     * Add error to health status
     */
    private function addError(string $message, array $context = []): void
    {
        $this->healthStatus['errors'][] = [
            'message' => $message,
            'context' => $context,
            'timestamp' => now()->toISOString(),
        ];

        Log::warning("MCP Health Check: {$message}", $context);
    }

    /**
     * Get cached health status
     */
    public function getCachedHealthStatus(): ?array
    {
        $cacheKey = config('mcp.health_check.cache_key', 'mcp_health_status');

        return Cache::get($cacheKey);
    }

    /**
     * Clear health check cache
     */
    public function clearHealthCache(): void
    {
        $cacheKey = config('mcp.health_check.cache_key', 'mcp_health_status');
        Cache::forget($cacheKey);
        $this->cachedDashboardData = null; // Clear dashboard cache too
    }

    /**
     * Get health status dashboard data
     */
    public function getHealthDashboardData(): array
    {
        // Use singleton pattern to avoid multiple health checks in single page load
        if ($this->cachedDashboardData !== null) {
            return $this->cachedDashboardData;
        }

        $health = $this->performHealthCheck(true); // Use cache for dashboard to avoid conflicts

        $this->cachedDashboardData = [
            'status' => $health['overall_health'] ?? 'unknown',
            'score' => $health['health_score'] ?? 0,
            'components' => [
                'Claude CLI' => $health['claude_available'] ? 'Available' : 'Unavailable',
                'Authentication' => $health['claude_authenticated'] ? 'Valid' : 'Invalid',
                'MCP Server' => $health['mcp_server_responsive'] ? 'Responsive' : 'Unresponsive',
            ],
            'performance' => [
                'Response Time' => isset($health['performance_metrics']['response_time_ms'])
                    ? $health['performance_metrics']['response_time_ms'].'ms'
                    : 'Unknown',
                'Assessment' => $health['performance_assessment'] ?? 'Unknown',
            ],
            'last_check' => $health['last_check'] ?? null,
            'errors' => $health['errors'] ?? [],
            'recommendations' => $this->getHealthRecommendations($health),
        ];

        return $this->cachedDashboardData;
    }

    /**
     * Get health recommendations based on current status
     */
    private function getHealthRecommendations(array $health): array
    {
        $recommendations = [];

        if (! ($health['claude_available'] ?? false)) {
            $recommendations[] = [
                'type' => 'error',
                'message' => 'Install Claude CLI',
                'action' => 'Visit https://claude.ai/code to install Claude Code CLI',
            ];
        }

        if (! ($health['claude_authenticated'] ?? false) && ($health['claude_available'] ?? false)) {
            $recommendations[] = [
                'type' => 'warning',
                'message' => 'Authenticate Claude CLI',
                'action' => 'Run `claude auth` in your terminal to authenticate',
            ];
        }

        if (! ($health['mcp_server_responsive'] ?? false) && ($health['claude_authenticated'] ?? false)) {
            $recommendations[] = [
                'type' => 'info',
                'message' => 'MCP Server Issues',
                'action' => 'Check network connectivity and API rate limits',
            ];
        }

        $responseTime = $health['performance_metrics']['response_time_ms'] ?? null;
        $slowThreshold = config('mcp.monitoring.slow_query_threshold', 10000);
        if ($responseTime && $responseTime > $slowThreshold) {
            $recommendations[] = [
                'type' => 'warning',
                'message' => 'Slow Response Times',
                'action' => 'Consider enabling response caching or checking network connectivity',
            ];
        }

        if (($health['health_score'] ?? 0) === 100 && empty($health['errors'])) {
            $recommendations[] = [
                'type' => 'success',
                'message' => 'All Systems Operational',
                'action' => 'MCP integration is working perfectly!',
            ];
        }

        return $recommendations;
    }
}
