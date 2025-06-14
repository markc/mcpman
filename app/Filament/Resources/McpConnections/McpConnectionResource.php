<?php

namespace App\Filament\Resources\McpConnections;

use App\Filament\Resources\McpConnections\Pages\CreateMcpConnection;
use App\Filament\Resources\McpConnections\Pages\EditMcpConnection;
use App\Filament\Resources\McpConnections\Pages\ListMcpConnections;
use App\Models\McpConnection;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class McpConnectionResource extends Resource
{
    protected static ?string $model = McpConnection::class;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required(),

                TextInput::make('endpoint_url')
                    ->label('Endpoint URL')
                    ->helperText('For HTTP/WebSocket: enter URL (e.g. http://localhost:3000). For stdio: enter command path (e.g. /usr/bin/claude mcp serve)')
                    ->default('/usr/bin/claude mcp serve')
                    ->required(),

                Select::make('transport_type')
                    ->label('Transport Type')
                    ->options([
                        'stdio' => 'Standard I/O',
                        'http' => 'HTTP',
                        'websocket' => 'WebSocket',
                    ])
                    ->required()
                    ->default('stdio')
                    ->live(),

                Select::make('status')
                    ->options([
                        'active' => 'Active',
                        'inactive' => 'Inactive',
                        'error' => 'Error',
                    ])
                    ->required()
                    ->default('inactive'),

                KeyValue::make('auth_config')
                    ->label('Authentication Config')
                    ->keyLabel('Key')
                    ->valueLabel('Value')
                    ->default([
                        'type' => 'bearer',
                        'token' => '',
                    ])
                    ->columnSpanFull(),

                KeyValue::make('capabilities')
                    ->label('Capabilities')
                    ->keyLabel('Capability')
                    ->valueLabel('Enabled')
                    ->default([
                        'tools' => 'true',
                        'prompts' => 'true',
                        'resources' => 'true',
                    ])
                    ->columnSpanFull(),

                KeyValue::make('metadata')
                    ->label('Metadata')
                    ->keyLabel('Key')
                    ->valueLabel('Value')
                    ->columnSpanFull(),

                Placeholder::make('last_connected_at')
                    ->label('Last Connected')
                    ->content(fn ($record) => $record?->last_connected_at?->diffForHumans() ?? 'Never')
                    ->hiddenOn('create'),

                Placeholder::make('last_error')
                    ->label('Last Error')
                    ->content(fn ($record) => $record?->last_error ?? 'None')
                    ->hiddenOn('create'),

                Hidden::make('user_id')
                    ->default(fn () => auth()->id()),
            ])
            ->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
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

                TextColumn::make('user.name')
                    ->label('Owner')
                    ->sortable(),

                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListMcpConnections::route('/'),
            'create' => CreateMcpConnection::route('/create'),
            'edit' => EditMcpConnection::route('/{record}/edit'),
        ];
    }
}
