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
use Filament\Support\Enums\Heroicon;
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
                ->icon(Heroicon::OUTLINE_ARROW_PATH)
                ->action('refreshData'),

            Action::make('exportData')
                ->label('Export Analytics')
                ->icon(Heroicon::OUTLINE_ARROW_DOWN_TRAY)
                ->action('exportAnalytics'),

            Action::make('clearCache')
                ->label('Clear Cache')
                ->icon(Heroicon::OUTLINE_TRASH)
                ->action('clearAnalyticsCache')
                ->color('danger')
                ->requiresConfirmation(),

            Action::make('cleanOldData')
                ->label('Clean Old Data')
                ->icon(Heroicon::OUTLINE_ARCHIVE_BOX_X_MARK)
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
                'icon' => Heroicon::OUTLINE_CHART_BAR,
                'color' => 'info',
            ],
            [
                'label' => 'Success Rate',
                'value' => number_format($summary['success_rate'] ?? 0, 1).'%',
                'description' => 'Successful operations',
                'icon' => Heroicon::OUTLINE_CHECK_CIRCLE,
                'color' => 'success',
            ],
            [
                'label' => 'Active Users',
                'value' => number_format($summary['unique_users'] ?? 0),
                'description' => 'Unique users',
                'icon' => Heroicon::OUTLINE_USERS,
                'color' => 'warning',
            ],
            [
                'label' => 'Avg Response Time',
                'value' => number_format($summary['average_response_time'] ?? 0).'ms',
                'description' => 'Average duration',
                'icon' => Heroicon::OUTLINE_CLOCK,
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
                'icon' => Heroicon::OUTLINE_BOLT,
                'color' => 'info',
            ],
            [
                'label' => 'Errors (Last Hour)',
                'value' => number_format($this->realTimeMetrics['errors_last_hour'] ?? 0),
                'description' => 'Recent errors',
                'icon' => Heroicon::OUTLINE_EXCLAMATION_TRIANGLE,
                'color' => 'danger',
            ],
            [
                'label' => 'Active Users',
                'value' => number_format($this->realTimeMetrics['active_users_last_hour'] ?? 0),
                'description' => 'Currently active',
                'icon' => Heroicon::OUTLINE_USER_GROUP,
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
