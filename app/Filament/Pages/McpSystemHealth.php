<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\HealthOverviewWidget;
use App\Filament\Widgets\RecommendationsWidget;
use App\Filament\Widgets\SystemComponentsWidget;
use App\Filament\Widgets\SystemErrorsWidget;
use App\Services\McpHealthCheckService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Enums\Heroicon;

class McpSystemHealth extends Page
{
    protected static ?string $title = 'MCP System Health';

    protected static ?string $navigationLabel = 'System Health';

    protected static ?int $navigationSort = 8;

    public function getView(): string
    {
        return 'filament.pages.mcp-system-health';
    }

    public function getHeaderWidgets(): array
    {
        return [
            HealthOverviewWidget::class,
            SystemComponentsWidget::class,
            RecommendationsWidget::class,
            SystemErrorsWidget::class,
        ];
    }

    public function getHeaderWidgetsColumns(): int|array
    {
        return [
            'md' => 2,
            'xl' => 2,
        ];
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
                ->icon(Heroicon::OUTLINE_ARROW_PATH)
                ->action('refreshHealthCheck'),

            Action::make('clearCache')
                ->label('Clear Cache')
                ->icon(Heroicon::OUTLINE_TRASH)
                ->action('clearHealthCache')
                ->color('warning')
                ->requiresConfirmation(),

            Action::make('runDiagnostics')
                ->label('Run Full Diagnostics')
                ->icon(Heroicon::OUTLINE_WRENCH_SCREWDRIVER)
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
            'excellent' => Heroicon::OUTLINE_CHECK_CIRCLE,
            'good' => Heroicon::OUTLINE_CHECK_BADGE,
            'fair' => Heroicon::OUTLINE_EXCLAMATION_TRIANGLE,
            'poor' => Heroicon::OUTLINE_X_CIRCLE,
            'critical', 'error' => Heroicon::OUTLINE_X_CIRCLE,
            default => Heroicon::OUTLINE_QUESTION_MARK_CIRCLE,
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
            'success' => Heroicon::OUTLINE_CHECK_CIRCLE,
            'info' => Heroicon::OUTLINE_INFORMATION_CIRCLE,
            'warning' => Heroicon::OUTLINE_EXCLAMATION_TRIANGLE,
            'error' => Heroicon::OUTLINE_X_CIRCLE,
            default => Heroicon::OUTLINE_LIGHT_BULB,
        };
    }

    /**
     * Get health data for widgets and page content
     */
    public function getHealthData(): array
    {
        return $this->healthData;
    }

    // Auto-refresh every 60 seconds
    protected ?string $pollingInterval = '60s';

    public function poll(): void
    {
        // Force refresh health data on each poll
        $healthService = app(McpHealthCheckService::class);
        $healthService->clearHealthCache();
        $this->loadHealthData();
    }
}
