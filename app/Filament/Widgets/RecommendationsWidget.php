<?php

namespace App\Filament\Widgets;

use App\Services\McpHealthCheckService;
use Filament\Widgets\Widget;

class RecommendationsWidget extends Widget
{
    protected string $view = 'filament.widgets.recommendations-widget';

    protected static ?int $sort = 3;

    // Polling handled by parent page

    public function getViewData(): array
    {
        $healthService = app(McpHealthCheckService::class);
        $healthData = $healthService->getHealthDashboardData();

        return [
            'recommendations' => $healthData['recommendations'] ?? [],
        ];
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
}
