<?php

namespace App\Filament\Widgets;

use App\Services\McpHealthCheckService;
use Filament\Support\Enums\Heroicon;
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
            'success' => Heroicon::OUTLINE_CHECK_CIRCLE,
            'info' => Heroicon::OUTLINE_INFORMATION_CIRCLE,
            'warning' => Heroicon::OUTLINE_EXCLAMATION_TRIANGLE,
            'error' => Heroicon::OUTLINE_X_CIRCLE,
            default => Heroicon::OUTLINE_LIGHT_BULB,
        };
    }
}
