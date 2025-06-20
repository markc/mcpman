<?php

namespace App\Filament\Widgets;

use App\Services\McpHealthCheckService;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\Widget;

class RecommendationsWidget extends Widget
{
    protected static ?int $sort = 3;

    protected string $view = 'filament.widgets.recommendations-widget';

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
            'success' => Heroicon::OutlinedCheckCircle,
            'info' => Heroicon::OutlinedInformationCircle,
            'warning' => Heroicon::OutlinedExclamationTriangle,
            'error' => Heroicon::OutlinedXCircle,
            default => Heroicon::OutlinedLightBulb,
        };
    }
}
