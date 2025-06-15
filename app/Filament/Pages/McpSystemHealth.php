<?php

namespace App\Filament\Pages;

use App\Services\McpHealthCheckService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

class McpSystemHealth extends Page
{
    protected static ?string $title = 'MCP System Health';

    protected static ?string $navigationLabel = 'System Health';

    protected static ?int $navigationSort = 8;

    public function getView(): string
    {
        return 'filament.pages.mcp-system-health';
    }

    public array $healthData = [];

    public function mount(): void
    {
        $this->loadHealthData();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('refreshHealth')
                ->label('Refresh Health Check')
                ->icon('heroicon-o-arrow-path')
                ->action('refreshHealthCheck'),

            Action::make('clearCache')
                ->label('Clear Cache')
                ->icon('heroicon-o-trash')
                ->action('clearHealthCache')
                ->color('warning')
                ->requiresConfirmation(),

            Action::make('runDiagnostics')
                ->label('Run Full Diagnostics')
                ->icon('heroicon-o-wrench-screwdriver')
                ->action('runFullDiagnostics')
                ->color('info'),
        ];
    }

    public function refreshHealthCheck(): void
    {
        $healthService = app(McpHealthCheckService::class);
        $healthService->clearHealthCache();
        $this->loadHealthData();

        $status = $this->healthData['status'] ?? 'unknown';
        $score = $this->healthData['score'] ?? 0;

        Notification::make()
            ->title('Health Check Refreshed')
            ->body("System health: {$status} (Score: {$score}/100)")
            ->success()
            ->send();
    }

    public function clearHealthCache(): void
    {
        $healthService = app(McpHealthCheckService::class);
        $healthService->clearHealthCache();

        Notification::make()
            ->title('Cache Cleared')
            ->body('Health check cache has been cleared successfully.')
            ->success()
            ->send();

        $this->loadHealthData();
    }

    public function runFullDiagnostics(): void
    {
        $healthService = app(McpHealthCheckService::class);
        $healthService->clearHealthCache();
        $this->healthData = $healthService->getHealthDashboardData();

        $errors = count($this->healthData['errors'] ?? []);
        $status = $this->healthData['status'] ?? 'unknown';

        Notification::make()
            ->title('Full Diagnostics Complete')
            ->body("Status: {$status} | Found {$errors} issues")
            ->color($errors > 0 ? 'warning' : 'success')
            ->persistent()
            ->send();
    }

    protected function loadHealthData(): void
    {
        try {
            $healthService = app(McpHealthCheckService::class);
            $this->healthData = $healthService->getHealthDashboardData();
        } catch (\Exception $e) {
            $this->healthData = [
                'status' => 'error',
                'score' => 0,
                'components' => [],
                'performance' => [],
                'errors' => [
                    [
                        'message' => 'Failed to load health data',
                        'context' => ['exception' => $e->getMessage()],
                        'timestamp' => now()->toISOString(),
                    ],
                ],
                'recommendations' => [
                    [
                        'type' => 'error',
                        'message' => 'Health Service Error',
                        'action' => 'Check application logs for details',
                    ],
                ],
            ];

            Notification::make()
                ->title('Health Check Failed')
                ->body('Failed to load health data: '.$e->getMessage())
                ->danger()
                ->send();
        }
    }

    public function getStatusColor(): string
    {
        return match ($this->healthData['status'] ?? 'unknown') {
            'excellent' => 'success',
            'good' => 'primary',
            'fair' => 'warning',
            'poor' => 'danger',
            'critical', 'error' => 'danger',
            default => 'gray',
        };
    }

    public function getStatusIcon(): string
    {
        return match ($this->healthData['status'] ?? 'unknown') {
            'excellent' => 'heroicon-o-check-circle',
            'good' => 'heroicon-o-check-badge',
            'fair' => 'heroicon-o-exclamation-triangle',
            'poor' => 'heroicon-o-x-circle',
            'critical', 'error' => 'heroicon-o-x-circle',
            default => 'heroicon-o-question-mark-circle',
        };
    }

    public function getComponentStatusColor(string $status): string
    {
        return match (strtolower($status)) {
            'available', 'valid', 'responsive' => 'success',
            'unavailable', 'invalid', 'unresponsive' => 'danger',
            default => 'warning',
        };
    }

    public function getRecommendationColor(string $type): string
    {
        return match ($type) {
            'success' => 'success',
            'info' => 'info',
            'warning' => 'warning',
            'error' => 'danger',
            default => 'gray',
        };
    }

    public function getRecommendationIcon(string $type): string
    {
        return match ($type) {
            'success' => 'heroicon-o-check-circle',
            'info' => 'heroicon-o-information-circle',
            'warning' => 'heroicon-o-exclamation-triangle',
            'error' => 'heroicon-o-x-circle',
            default => 'heroicon-o-light-bulb',
        };
    }

    public function getHealthCards(): array
    {
        $score = $this->healthData['score'] ?? 0;
        $status = $this->healthData['status'] ?? 'unknown';
        $lastCheck = $this->healthData['last_check'] ?? null;
        $responseTime = $this->healthData['performance']['Response Time'] ?? 'Unknown';

        return [
            [
                'label' => 'Overall Health',
                'value' => $score.'/100',
                'description' => ucfirst($status),
                'icon' => $this->getStatusIcon(),
                'color' => $this->getStatusColor(),
            ],
            [
                'label' => 'Response Time',
                'value' => $responseTime,
                'description' => $this->healthData['performance']['Assessment'] ?? 'Unknown',
                'icon' => 'heroicon-o-clock',
                'color' => ($this->healthData['performance']['Assessment'] ?? '') === 'fast' ? 'success' : 'warning',
            ],
            [
                'label' => 'System Issues',
                'value' => count($this->healthData['errors'] ?? []),
                'description' => 'Active problems',
                'icon' => 'heroicon-o-exclamation-triangle',
                'color' => count($this->healthData['errors'] ?? []) === 0 ? 'success' : 'danger',
            ],
            [
                'label' => 'Last Check',
                'value' => $lastCheck ? \Carbon\Carbon::parse($lastCheck)->diffForHumans() : 'Never',
                'description' => 'Health verification',
                'icon' => 'heroicon-o-clock',
                'color' => 'info',
            ],
        ];
    }

    // Auto-refresh every 60 seconds
    protected int $pollingInterval = 60;

    public function poll(): void
    {
        // Only refresh if health check is not cached (to avoid excessive checks)
        $healthService = app(McpHealthCheckService::class);
        $cached = $healthService->getCachedHealthStatus();

        if (! $cached) {
            $this->loadHealthData();
        }
    }
}
