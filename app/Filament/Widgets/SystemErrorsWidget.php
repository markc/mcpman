<?php

namespace App\Filament\Widgets;

use App\Services\McpHealthCheckService;
use Filament\Widgets\Widget;

class SystemErrorsWidget extends Widget
{
    protected static ?int $sort = 4;

    protected string $view = 'filament.widgets.system-errors-widget';

    // Polling handled by parent page

    public function getViewData(): array
    {
        $healthService = app(McpHealthCheckService::class);
        $healthData = $healthService->getHealthDashboardData();

        return [
            'errors' => $healthData['errors'] ?? [],
        ];
    }
}
