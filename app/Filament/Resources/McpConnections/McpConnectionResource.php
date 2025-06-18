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
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class McpConnectionResource extends Resource
{
    protected static ?string $model = McpConnection::class;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Placeholder::make('connection_info')
                    ->label('Connection Information')
                    ->content('Basic connection details and endpoint configuration')
                    ->columnSpanFull(),

                TextInput::make('name')
                    ->label('Connection Name')
                    ->required()
                    ->maxLength(255)
                    ->helperText('Descriptive name for this MCP connection'),

                TextInput::make('endpoint_url')
                    ->label('Endpoint URL / Command')
                    ->helperText('For HTTP/WebSocket: URL (e.g. http://localhost:3000). For stdio: command path (e.g. /usr/bin/claude mcp serve)')
                    ->default('/usr/bin/claude mcp serve')
                    ->required()
                    ->columnSpanFull(),

                        Select::make('transport_type')
                            ->label('Transport Type')
                            ->helperText('Communication protocol for MCP connection')
                            ->options([
                                'stdio' => 'Standard I/O (Local process)',
                                'http' => 'HTTP (Web service)',
                                'websocket' => 'WebSocket (Real-time)',
                            ])
                            ->required()
                            ->default('stdio')
                            ->live(),

                        Select::make('status')
                            ->label('Connection Status')
                            ->helperText('Current operational state')
                            ->options([
                                'active' => 'Active - Ready for use',
                                'inactive' => 'Inactive - Disabled',
                                'error' => 'Error - Needs attention',
                            ])
                            ->required()
                            ->default('inactive'),
                    ])
                    ->columns(2)
                    ->columnSpanFull(),

                Placeholder::make('configuration_header')
                    ->label('Configuration')
                    ->content('Authentication, capabilities, and advanced settings')
                    ->columnSpanFull(),

                KeyValue::make('auth_config')
                            ->label('Authentication Configuration')
                            ->helperText('Security credentials and authentication method')
                            ->keyLabel('Setting')
                            ->valueLabel('Value')
                            ->default([
                                'type' => 'bearer',
                                'token' => '',
                            ])
                            ->columnSpanFull(),

                        KeyValue::make('capabilities')
                            ->label('MCP Capabilities')
                            ->helperText('Features and functionality enabled for this connection')
                            ->keyLabel('Capability')
                            ->valueLabel('Enabled (true/false)')
                            ->default([
                                'tools' => 'true',
                                'prompts' => 'true',
                                'resources' => 'true',
                            ])
                            ->columnSpanFull(),

                        KeyValue::make('metadata')
                            ->label('Additional Metadata')
                            ->helperText('Custom properties and configuration options')
                            ->keyLabel('Property')
                            ->valueLabel('Value')
                            ->columnSpanFull(),
                ]),

                Placeholder::make('connection_status_header')
                    ->label('Connection Status')
                    ->content('Runtime information and diagnostics')
                    ->columnSpanFull()
                    ->hiddenOn('create'),
                        Placeholder::make('last_connected_at')
                            ->label('Last Connected')
                            ->content(fn ($record) => $record?->last_connected_at?->diffForHumans() ?? 'Never')
                            ->hiddenOn('create'),

                        Placeholder::make('last_error')
                            ->label('Last Error')
                            ->content(fn ($record) => $record?->last_error ?? 'None')
                            ->hiddenOn('create'),
                    ])->hiddenOn('create'),

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
                SelectFilter::make('transport_type')
                    ->options([
                        'stdio' => 'Standard I/O',
                        'http' => 'HTTP',
                        'websocket' => 'WebSocket',
                    ]),

                SelectFilter::make('status')
                    ->options([
                        'active' => 'Active',
                        'inactive' => 'Inactive',
                        'error' => 'Error',
                    ]),

                Filter::make('recently_connected')
                    ->label('Connected Recently')
                    ->query(fn (Builder $query): Builder => $query->where('last_connected_at', '>=', now()->subDay()))
                    ->toggle(),

                Filter::make('has_errors')
                    ->label('Has Errors')
                    ->query(fn (Builder $query): Builder => $query->whereNotNull('last_error')->where('last_error', '!=', ''))
                    ->toggle(),

                Filter::make('recent')
                    ->label('Created Recently')
                    ->query(fn (Builder $query): Builder => $query->where('created_at', '>=', now()->subWeek()))
                    ->toggle(),
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
