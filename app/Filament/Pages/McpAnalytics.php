<?php

namespace App\Filament\Pages;

use App\Services\AnalyticsManager;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Cache;

class McpAnalytics extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $title = 'MCP Analytics';

    protected static ?string $navigationLabel = 'Analytics & Metrics';

    protected static ?int $navigationSort = 5;

    public function getView(): string
    {
        return 'filament.pages.mcp-analytics';
    }

    public ?array $data = [];

    public array $dashboardData = [];

    public array $realTimeMetrics = [];

    public int $selectedPeriod = 30; // Days

    public function mount(): void
    {
        $this->form->fill([
            'period' => 30,
            'start_date' => now()->subDays(30)->format('Y-m-d'),
            'end_date' => now()->format('Y-m-d'),
        ]);

        $this->loadDashboardData();
        $this->loadRealTimeMetrics();
    }

    protected function getFormSchema(): array
    {
        return [
            Select::make('period')
                ->label('Time Period')
                ->options([
                    7 => 'Last 7 days',
                    30 => 'Last 30 days',
                    90 => 'Last 90 days',
                    365 => 'Last year',
                    'custom' => 'Custom range',
                ])
                ->default(30)
                ->live()
                ->afterStateUpdated(fn () => $this->updatePeriod()),

            DatePicker::make('start_date')
                ->label('Start Date')
                ->visible(fn (callable $get) => $get('period') === 'custom')
                ->required(fn (callable $get) => $get('period') === 'custom'),

            DatePicker::make('end_date')
                ->label('End Date')
                ->visible(fn (callable $get) => $get('period') === 'custom')
                ->required(fn (callable $get) => $get('period') === 'custom'),
        ];
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components($this->getFormSchema())
            ->statePath('data')
            ->columns(3);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('refreshData')
                ->label('Refresh Data')
                ->icon('heroicon-o-arrow-path')
                ->action('refreshData'),

            Action::make('exportData')
                ->label('Export Analytics')
                ->icon('heroicon-o-arrow-down-tray')
                ->action('exportAnalytics'),

            Action::make('clearCache')
                ->label('Clear Cache')
                ->icon('heroicon-o-trash')
                ->action('clearAnalyticsCache')
                ->color('danger')
                ->requiresConfirmation(),

            Action::make('cleanOldData')
                ->label('Clean Old Data')
                ->icon('heroicon-o-archive-box-x-mark')
                ->action('cleanOldData')
                ->color('warning')
                ->requiresConfirmation()
                ->modalDescription('This will remove analytics data older than 90 days. This action cannot be undone.'),
        ];
    }

    public function updatePeriod(): void
    {
        $period = $this->data['period'] ?? 30;

        if (is_numeric($period)) {
            $this->selectedPeriod = (int) $period;
            $this->loadDashboardData();
        }
    }

    public function refreshData(): void
    {
        $this->loadDashboardData();
        $this->loadRealTimeMetrics();

        Notification::make()
            ->title('Data Refreshed')
            ->body('Analytics data has been refreshed successfully.')
            ->success()
            ->send();
    }

    public function exportAnalytics(): void
    {
        try {
            $analyticsManager = app(AnalyticsManager::class);

            $startDate = $this->data['period'] === 'custom'
                ? Carbon::parse($this->data['start_date'])
                : now()->subDays($this->selectedPeriod);

            $endDate = $this->data['period'] === 'custom'
                ? Carbon::parse($this->data['end_date'])
                : now();

            $data = $analyticsManager->exportData($startDate, $endDate);

            // In a real implementation, this would generate and download a file
            Notification::make()
                ->title('Export Complete')
                ->body("Exported {$data->count()} analytics records for the selected period.")
                ->success()
                ->send();

        } catch (\Exception $e) {
            Notification::make()
                ->title('Export Failed')
                ->body('Failed to export analytics data: '.$e->getMessage())
                ->danger()
                ->send();
        }
    }

    public function clearAnalyticsCache(): void
    {
        Cache::flush();

        Notification::make()
            ->title('Cache Cleared')
            ->body('Analytics cache has been cleared successfully.')
            ->success()
            ->send();

        $this->loadDashboardData();
        $this->loadRealTimeMetrics();
    }

    public function cleanOldData(): void
    {
        try {
            $analyticsManager = app(AnalyticsManager::class);
            $deletedCount = $analyticsManager->cleanOldData(90);

            Notification::make()
                ->title('Old Data Cleaned')
                ->body("Removed {$deletedCount} old analytics records.")
                ->success()
                ->send();

            $this->loadDashboardData();

        } catch (\Exception $e) {
            Notification::make()
                ->title('Cleanup Failed')
                ->body('Failed to clean old data: '.$e->getMessage())
                ->danger()
                ->send();
        }
    }

    public function loadDashboardData(): void
    {
        try {
            $analyticsManager = app(AnalyticsManager::class);
            $this->dashboardData = $analyticsManager->getDashboardData($this->selectedPeriod);
        } catch (\Exception $e) {
            $this->dashboardData = [];

            Notification::make()
                ->title('Data Load Failed')
                ->body('Failed to load analytics data: '.$e->getMessage())
                ->danger()
                ->send();
        }
    }

    public function loadRealTimeMetrics(): void
    {
        try {
            $analyticsManager = app(AnalyticsManager::class);
            $this->realTimeMetrics = $analyticsManager->getRealTimeMetrics();
        } catch (\Exception $e) {
            $this->realTimeMetrics = [];
        }
    }

    public function getSummaryCards(): array
    {
        $summary = $this->dashboardData['summary'] ?? [];

        return [
            [
                'label' => 'Total Events',
                'value' => number_format($summary['total_events'] ?? 0),
                'description' => 'Events in selected period',
                'icon' => 'heroicon-o-chart-bar',
                'color' => 'info',
            ],
            [
                'label' => 'Success Rate',
                'value' => number_format($summary['success_rate'] ?? 0, 1).'%',
                'description' => 'Successful operations',
                'icon' => 'heroicon-o-check-circle',
                'color' => 'success',
            ],
            [
                'label' => 'Active Users',
                'value' => number_format($summary['unique_users'] ?? 0),
                'description' => 'Unique users',
                'icon' => 'heroicon-o-users',
                'color' => 'warning',
            ],
            [
                'label' => 'Avg Response Time',
                'value' => number_format($summary['average_response_time'] ?? 0).'ms',
                'description' => 'Average duration',
                'icon' => 'heroicon-o-clock',
                'color' => 'primary',
            ],
        ];
    }

    public function getRealTimeCards(): array
    {
        return [
            [
                'label' => 'Events (Last Hour)',
                'value' => number_format($this->realTimeMetrics['events_last_hour'] ?? 0),
                'description' => 'Recent activity',
                'icon' => 'heroicon-o-bolt',
                'color' => 'info',
            ],
            [
                'label' => 'Errors (Last Hour)',
                'value' => number_format($this->realTimeMetrics['errors_last_hour'] ?? 0),
                'description' => 'Recent errors',
                'icon' => 'heroicon-o-exclamation-triangle',
                'color' => 'danger',
            ],
            [
                'label' => 'Active Users',
                'value' => number_format($this->realTimeMetrics['active_users_last_hour'] ?? 0),
                'description' => 'Currently active',
                'icon' => 'heroicon-o-user-group',
                'color' => 'success',
            ],
        ];
    }

    public function getChartData(): array
    {
        $trends = $this->dashboardData['trends'] ?? [];

        return [
            'daily_events' => $trends['daily_events'] ?? [],
            'success_rate' => $trends['daily_success_rate'] ?? [],
            'response_times' => $trends['daily_avg_duration'] ?? [],
        ];
    }

    // Auto-refresh every 30 seconds for real-time metrics
    protected int $pollingInterval = 30;

    public function poll(): void
    {
        $this->loadRealTimeMetrics();
    }
}
