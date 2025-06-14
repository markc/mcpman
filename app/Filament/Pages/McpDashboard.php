<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\McpConnectionsWidget;
use App\Filament\Widgets\McpStatsWidget;
use App\Models\McpConnection;
use App\Services\McpClient;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Log;

class McpDashboard extends Page
{
    protected static ?string $title = 'Dashboard';

    protected static ?string $navigationLabel = 'Dashboard';

    protected static ?int $navigationSort = 1;

    protected static string $routePath = '/';

    public function getWidgets(): array
    {
        return [
            McpStatsWidget::class,
            McpConnectionsWidget::class,
        ];
    }

    public function hasWidgets(): bool
    {
        return count($this->getWidgets()) > 0;
    }

    public function getWidgetsColumns(): int|string|array
    {
        return 2;
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('testAllConnections')
                ->label('Test All Connections')
                ->icon('heroicon-o-signal')
                ->action('testAllConnections')
                ->requiresConfirmation()
                ->modalHeading('Test All MCP Connections')
                ->modalDescription('This will test the connectivity of all active MCP connections. This may take a few moments.')
                ->modalSubmitActionLabel('Test Connections'),
        ];
    }

    public function testAllConnections(): void
    {
        $connections = McpConnection::where('status', 'active')->get();
        $results = [];

        foreach ($connections as $connection) {
            try {
                $client = new McpClient($connection);
                $isConnected = $client->testConnection();

                if ($isConnected) {
                    $connection->update(['status' => 'active', 'last_connected_at' => now()]);
                    $results[] = "✅ {$connection->name}: Connected";
                } else {
                    $connection->update(['status' => 'error', 'last_error' => 'Connection test failed']);
                    $results[] = "❌ {$connection->name}: Failed";
                }
            } catch (\Exception $e) {
                $connection->update(['status' => 'error', 'last_error' => $e->getMessage()]);
                $results[] = "❌ {$connection->name}: Error - ".$e->getMessage();
                Log::error('Connection test failed', ['connection' => $connection->name, 'error' => $e->getMessage()]);
            }
        }

        Notification::make()
            ->title('Connection Tests Completed')
            ->body(implode("\n", $results))
            ->success()
            ->persistent()
            ->send();
    }
}
