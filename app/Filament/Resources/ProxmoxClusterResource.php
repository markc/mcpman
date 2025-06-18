<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ProxmoxClusterResource\Pages;
use App\Models\ProxmoxCluster;
use BackedEnum;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Heroicon;
use Filament\Tables;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\ProgressColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class ProxmoxClusterResource extends Resource
{
    protected static ?string $model = ProxmoxCluster::class;

    protected static BackedEnum|string|null $navigationIcon = Heroicon::OUTLINE_SERVER_STACK;

    protected static ?string $navigationLabel = 'Proxmox Clusters';

    protected static string|UnitEnum|null $navigationGroup = 'Proxmox Management';

    protected static ?int $navigationSort = 1;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Placeholder::make('cluster_info_header')
                    ->label('Cluster Information')
                    ->content('Basic configuration for the Proxmox cluster connection.')
                    ->columnSpanFull(),

                TextInput::make('name')
                    ->required()
                    ->maxLength(255)
                    ->placeholder('Production Cluster'),

                Textarea::make('description')
                    ->maxLength(1000)
                    ->placeholder('Main production Proxmox cluster')
                    ->columnSpanFull(),

                Placeholder::make('connection_header')
                    ->label('Connection Settings')
                    ->content('Network and authentication configuration for accessing the Proxmox API.')
                    ->columnSpanFull(),

                TextInput::make('api_endpoint')
                    ->label('API Endpoint')
                    ->required()
                    ->url()
                    ->placeholder('https://proxmox.example.com'),

                TextInput::make('api_port')
                    ->label('API Port')
                    ->numeric()
                    ->default(8006)
                    ->required(),

                TextInput::make('username')
                    ->required()
                    ->placeholder('root@pam')
                    ->helperText('Format: username@realm (e.g., root@pam or user@pve)'),

                TextInput::make('password')
                    ->password()
                    ->dehydrateStateUsing(fn ($state) => filled($state) ? $state : null)
                    ->dehydrated(fn ($state) => filled($state))
                    ->placeholder('Leave empty to keep existing password'),

                TextInput::make('api_token')
                    ->label('API Token (Optional)')
                    ->dehydrateStateUsing(fn ($state) => filled($state) ? $state : null)
                    ->dehydrated(fn ($state) => filled($state))
                    ->placeholder('user@realm!tokenid=token-secret')
                    ->helperText('Preferred over password authentication')
                    ->columnSpanFull(),

                Placeholder::make('advanced_header')
                    ->label('Advanced Settings')
                    ->content('Security and performance configuration options.')
                    ->columnSpanFull(),

                Toggle::make('verify_tls')
                    ->label('Verify TLS Certificate')
                    ->default(true),

                TextInput::make('timeout')
                    ->label('Connection Timeout (seconds)')
                    ->numeric()
                    ->default(30)
                    ->required(),

                Select::make('status')
                    ->options([
                        'active' => 'Active',
                        'inactive' => 'Inactive',
                        'maintenance' => 'Maintenance',
                        'error' => 'Error',
                    ])
                    ->default('active')
                    ->required(),
            ])
            ->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                BadgeColumn::make('status')
                    ->colors([
                        'success' => 'active',
                        'warning' => 'maintenance',
                        'danger' => 'error',
                        'secondary' => 'inactive',
                    ]),

                TextColumn::make('api_endpoint')
                    ->label('Endpoint')
                    ->limit(30)
                    ->searchable(),

                TextColumn::make('total_vms')
                    ->label('VMs')
                    ->badge()
                    ->color('primary'),

                TextColumn::make('total_containers')
                    ->label('Containers')
                    ->badge()
                    ->color('info'),

                TextColumn::make('online_nodes')
                    ->label('Online Nodes')
                    ->badge()
                    ->color('success'),

                ProgressColumn::make('health_score')
                    ->label('Health')
                    ->getStateUsing(function (ProxmoxCluster $record): int {
                        return $record->getHealthScore();
                    })
                    ->color(function (int $state): string {
                        return match (true) {
                            $state >= 80 => 'success',
                            $state >= 60 => 'warning',
                            default => 'danger',
                        };
                    }),

                TextColumn::make('last_seen_at')
                    ->label('Last Seen')
                    ->dateTime()
                    ->sortable()
                    ->since(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'active' => 'Active',
                        'inactive' => 'Inactive',
                        'maintenance' => 'Maintenance',
                        'error' => 'Error',
                    ]),
            ])
            ->actions([
                Action::make('health_check')
                    ->icon(Heroicon::OUTLINE_HEART)
                    ->color('info')
                    ->action(function (ProxmoxCluster $record) {
                        $monitoringService = new \App\Services\ProxmoxMonitoringService($record);
                        $monitoringService->performHealthCheck();

                        \Filament\Notifications\Notification::make()
                            ->title('Health check completed')
                            ->success()
                            ->send();
                    }),

                EditAction::make(),

                Action::make('view_details')
                    ->icon(Heroicon::OUTLINE_EYE)
                    ->url(fn (ProxmoxCluster $record): string => route('filament.admin.resources.proxmox-clusters.view', $record)
                    ),

                DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('name');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListProxmoxClusters::route('/'),
            'create' => Pages\CreateProxmoxCluster::route('/create'),
            'view' => Pages\ViewProxmoxCluster::route('/{record}'),
            'edit' => Pages\EditProxmoxCluster::route('/{record}/edit'),
        ];
    }

    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::where('status', 'active')->count();
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['name', 'description', 'api_endpoint'];
    }

    public static function getGlobalSearchResultDetails($record): array
    {
        return [
            'Status' => $record->status,
            'Endpoint' => $record->api_endpoint,
            'Health Score' => $record->getHealthScore().'%',
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withCount(['virtualMachines', 'containers', 'nodes'])
            ->with(['nodes' => function ($query) {
                $query->where('status', 'online');
            }]);
    }
}
