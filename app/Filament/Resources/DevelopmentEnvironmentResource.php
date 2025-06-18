<?php

namespace App\Filament\Resources;

use App\Filament\Resources\DevelopmentEnvironmentResource\Pages;
use App\Models\DevelopmentEnvironment;
use App\Models\ProxmoxCluster;
use BackedEnum;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
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

class DevelopmentEnvironmentResource extends Resource
{
    protected static ?string $model = DevelopmentEnvironment::class;

    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-cube';

    protected static ?string $navigationLabel = 'Dev Environments';

    protected static string|UnitEnum|null $navigationGroup = 'Proxmox Management';

    protected static ?int $navigationSort = 2;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Placeholder::make('environment_header')
                    ->label('Environment Configuration')
                    ->content('Basic settings for your development environment.')
                    ->columnSpanFull(),

                TextInput::make('name')
                    ->required()
                    ->maxLength(255)
                    ->placeholder('My Development Environment'),

                Textarea::make('description')
                    ->maxLength(1000)
                    ->placeholder('Development environment for project X')
                    ->columnSpanFull(),

                Select::make('proxmox_cluster_id')
                    ->label('Proxmox Cluster')
                    ->options(ProxmoxCluster::where('status', 'active')->pluck('name', 'id'))
                    ->required()
                    ->searchable(),

                Select::make('environment_type')
                    ->options([
                        'development' => 'Development',
                        'testing' => 'Testing',
                        'staging' => 'Staging',
                        'training' => 'Training',
                    ])
                    ->default('development')
                    ->required(),

                Select::make('template_name')
                    ->options([
                        'lemp-laravel-stack' => 'LEMP + Laravel Development Stack',
                        'full-mail-server' => 'Complete Mail Server + Web Stack',
                        'filament-admin' => 'Filament v4 Admin Platform',
                        'multi-tenant-saas' => 'Multi-Tenant SaaS Platform',
                        'api-backend' => 'Laravel API Backend',
                        'legacy-migration' => 'PHP 8.4 Migration Environment',
                        'custom' => 'Custom Configuration',
                    ])
                    ->required()
                    ->helperText('Pre-configured PHP/Laravel template for quick deployment'),

                Placeholder::make('resources_header')
                    ->label('Resource Allocation')
                    ->content('Define the computing resources for your environment.')
                    ->columnSpanFull(),

                TextInput::make('total_cpu_cores')
                    ->label('CPU Cores')
                    ->numeric()
                    ->default(2)
                    ->required()
                    ->minValue(1)
                    ->maxValue(32),

                TextInput::make('total_memory_gb')
                    ->label('Memory (GB)')
                    ->numeric()
                    ->default(4)
                    ->required()
                    ->minValue(1)
                    ->maxValue(128)
                    ->afterStateUpdated(function ($state, callable $set) {
                        $set('total_memory_bytes', $state * 1024 * 1024 * 1024);
                    }),

                TextInput::make('total_storage_gb')
                    ->label('Storage (GB)')
                    ->numeric()
                    ->default(20)
                    ->required()
                    ->minValue(10)
                    ->maxValue(1000)
                    ->afterStateUpdated(function ($state, callable $set) {
                        $set('total_storage_bytes', $state * 1024 * 1024 * 1024);
                    }),

                TextInput::make('estimated_cost_per_hour')
                    ->label('Estimated Cost/Hour ($)')
                    ->numeric()
                    ->step('0.01')
                    ->default(0.05),

                Placeholder::make('network_header')
                    ->label('Network Configuration')
                    ->content('Network and security settings for your environment.')
                    ->columnSpanFull(),

                TextInput::make('network_vlan')
                    ->label('VLAN ID (Optional)')
                    ->numeric()
                    ->placeholder('100'),

                TextInput::make('subnet_cidr')
                    ->label('Subnet CIDR (Optional)')
                    ->placeholder('192.168.100.0/24'),

                Toggle::make('public_access')
                    ->label('Enable Public Access')
                    ->default(false)
                    ->helperText('Allow access from the internet'),

                KeyValue::make('exposed_ports')
                    ->label('Exposed Ports')
                    ->keyLabel('Service')
                    ->valueLabel('Port')
                    ->default([
                        'ssh' => 22,
                        'web' => 80,
                        'https' => 443,
                    ])
                    ->columnSpanFull(),

                Placeholder::make('lifecycle_header')
                    ->label('Lifecycle Management')
                    ->content('Configure automatic lifecycle policies for your environment.')
                    ->columnSpanFull(),

                DateTimePicker::make('expires_at')
                    ->label('Auto-Destroy Date (Optional)')
                    ->helperText('Environment will be automatically destroyed at this time'),

                TextInput::make('auto_destroy_hours')
                    ->label('Auto-Destroy After Hours (Optional)')
                    ->numeric()
                    ->placeholder('72')
                    ->helperText('Hours of inactivity before auto-destroy'),

                Toggle::make('auto_start')
                    ->label('Auto-Start on Creation')
                    ->default(true),

                Toggle::make('backup_enabled')
                    ->label('Enable Backups')
                    ->default(true),

                Placeholder::make('development_header')
                    ->label('Development Tools')
                    ->content('Configure development-specific features and tools.')
                    ->columnSpanFull(),

                Select::make('ide_type')
                    ->label('Preferred IDE')
                    ->options([
                        'vscode' => 'Visual Studio Code',
                        'vim' => 'Vim/Neovim',
                        'emacs' => 'Emacs',
                        'jetbrains' => 'JetBrains IDEs',
                        'none' => 'None/Custom',
                    ])
                    ->placeholder('Select IDE'),

                Toggle::make('ci_cd_enabled')
                    ->label('Enable CI/CD Integration')
                    ->default(false),

                KeyValue::make('git_repositories')
                    ->label('Git Repositories to Clone')
                    ->keyLabel('Name')
                    ->valueLabel('Repository URL')
                    ->columnSpanFull(),

                Placeholder::make('metadata_header')
                    ->label('Project Information')
                    ->content('Optional metadata for organization and team collaboration.')
                    ->columnSpanFull(),

                TextInput::make('project_name')
                    ->label('Project Name')
                    ->placeholder('My Awesome Project'),

                TagsInput::make('tags')
                    ->placeholder('Add tags...')
                    ->columnSpanFull(),

                Textarea::make('notes')
                    ->placeholder('Additional notes about this environment')
                    ->columnSpanFull(),
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
                        'warning' => 'provisioning',
                        'success' => 'running',
                        'secondary' => 'stopped',
                        'danger' => 'failed',
                        'gray' => 'destroying',
                    ]),

                BadgeColumn::make('environment_type')
                    ->label('Type')
                    ->colors([
                        'primary' => 'development',
                        'info' => 'testing',
                        'success' => 'staging',
                        'warning' => 'training',
                    ]),

                TextColumn::make('template_name')
                    ->label('Template')
                    ->limit(20),

                TextColumn::make('cluster.name')
                    ->label('Cluster')
                    ->limit(15),

                TextColumn::make('total_resources')
                    ->label('Resources')
                    ->getStateUsing(function (DevelopmentEnvironment $record): string {
                        return "{$record->total_cpu_cores}C / ".
                               round($record->total_memory_bytes / (1024 ** 3), 1).'GB / '.
                               round($record->total_storage_bytes / (1024 ** 3), 1).'GB';
                    }),

                ProgressColumn::make('health_score')
                    ->label('Health')
                    ->getStateUsing(function (DevelopmentEnvironment $record): int {
                        return $record->getHealthScore();
                    })
                    ->color(function (int $state): string {
                        return match (true) {
                            $state >= 80 => 'success',
                            $state >= 60 => 'warning',
                            default => 'danger',
                        };
                    }),

                TextColumn::make('estimated_monthly_cost')
                    ->label('Monthly Cost')
                    ->getStateUsing(fn (DevelopmentEnvironment $record): string => '$'.number_format($record->getEstimatedMonthlyCost(), 2)
                    )
                    ->sortable(),

                TextColumn::make('provisioned_at')
                    ->label('Created')
                    ->dateTime()
                    ->sortable()
                    ->since(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'provisioning' => 'Provisioning',
                        'running' => 'Running',
                        'stopped' => 'Stopped',
                        'failed' => 'Failed',
                        'destroying' => 'Destroying',
                    ]),

                Tables\Filters\SelectFilter::make('environment_type')
                    ->options([
                        'development' => 'Development',
                        'testing' => 'Testing',
                        'staging' => 'Staging',
                        'training' => 'Training',
                    ]),

                Tables\Filters\SelectFilter::make('template_name')
                    ->options([
                        'lemp-laravel-stack' => 'LEMP + Laravel Stack',
                        'full-mail-server' => 'Mail Server + Web',
                        'filament-admin' => 'Filament v4 Admin',
                        'multi-tenant-saas' => 'Multi-Tenant SaaS',
                        'api-backend' => 'Laravel API Backend',
                        'legacy-migration' => 'PHP 8.4 Migration',
                        'custom' => 'Custom',
                    ]),
            ])
            ->actions([
                Action::make('start')
                    ->icon('heroicon-o-play')
                    ->color('success')
                    ->visible(fn (DevelopmentEnvironment $record): bool => $record->isStopped())
                    ->action(function (DevelopmentEnvironment $record) {
                        // TODO: Implement start action
                        \Filament\Notifications\Notification::make()
                            ->title('Environment starting')
                            ->body("Starting environment: {$record->name}")
                            ->info()
                            ->send();
                    }),

                Action::make('stop')
                    ->icon('heroicon-o-stop')
                    ->color('warning')
                    ->visible(fn (DevelopmentEnvironment $record): bool => $record->isRunning())
                    ->requiresConfirmation()
                    ->action(function (DevelopmentEnvironment $record) {
                        // TODO: Implement stop action
                        \Filament\Notifications\Notification::make()
                            ->title('Environment stopping')
                            ->body("Stopping environment: {$record->name}")
                            ->warning()
                            ->send();
                    }),

                Action::make('access')
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->color('info')
                    ->visible(fn (DevelopmentEnvironment $record): bool => $record->isRunning())
                    ->url(function (DevelopmentEnvironment $record): ?string {
                        $urls = $record->getAccessUrls();

                        return $urls['web'] ?? null;
                    })
                    ->openUrlInNewTab(),

                EditAction::make(),

                DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListDevelopmentEnvironments::route('/'),
            'create' => Pages\CreateDevelopmentEnvironment::route('/create'),
            'view' => Pages\ViewDevelopmentEnvironment::route('/{record}'),
            'edit' => Pages\EditDevelopmentEnvironment::route('/{record}/edit'),
        ];
    }

    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::where('status', 'running')->count();
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['name', 'description', 'project_name', 'template_name'];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with(['cluster', 'user'])
            ->where('user_id', auth()->id());
    }
}
