<?php

namespace App\Filament\Widgets;

use App\Models\McpConnection;
use App\Services\McpClient;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Support\Facades\Log;

class McpConnectionsWidget extends BaseWidget
{
    protected int|string|array $columnSpan = 'full';

    protected static ?string $heading = 'MCP Connection Status';

    protected static ?int $sort = 10;

    public function table(Table $table): Table
    {
        return $table
            ->query(
                McpConnection::query()
                    ->orderBy('status', 'asc')
                    ->orderBy('last_connected_at', 'desc')
            )
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('endpoint_url')
                    ->label('Endpoint')
                    ->limit(50)
                    ->tooltip(fn ($record) => $record->endpoint_url),

                TextColumn::make('transport_type')
                    ->label('Transport')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'stdio' => 'gray',
                        'http' => 'blue',
                        'websocket' => 'green',
                        default => 'gray',
                    }),

                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'active' => 'success',
                        'inactive' => 'warning',
                        'error' => 'danger',
                        default => 'gray',
                    }),

                TextColumn::make('last_connected_at')
                    ->label('Last Connected')
                    ->dateTime()
                    ->sortable()
                    ->placeholder('Never'),
            ])
            ->actions([
                Action::make('test')
                    ->label('Test')
                    ->icon('heroicon-o-signal')
                    ->action(function (McpConnection $record) {
                        $this->testConnection($record);
                    }),
            ]);
    }

    public function testConnection(McpConnection $connection): void
    {
        try {
            $client = new McpClient($connection);
            $isConnected = $client->testConnection();

            if ($isConnected) {
                $connection->markAsConnected();
                $message = "Connection '{$connection->name}' is working correctly.";
                $type = 'success';
            } else {
                $connection->markAsError('Connection test failed');
                $message = "Connection '{$connection->name}' test failed.";
                $type = 'warning';
            }
        } catch (\Exception $e) {
            $connection->markAsError($e->getMessage());
            $message = "Connection '{$connection->name}' error: ".$e->getMessage();
            $type = 'danger';
            Log::error('Connection test failed', ['connection' => $connection->name, 'error' => $e->getMessage()]);
        }

        Notification::make()
            ->title('Connection Test Result')
            ->body($message)
            ->{$type}()
            ->send();
    }
}
