<?php

namespace App\Filament\Resources\DevelopmentEnvironmentResource\Pages;

use App\Filament\Resources\DevelopmentEnvironmentResource;
use Filament\Actions;
use Filament\Infolists\Components\BadgeEntry;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\KeyValueEntry;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Icons\Heroicon;

class ViewDevelopmentEnvironment extends ViewRecord
{
    protected static string $resource = DevelopmentEnvironmentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('start')
                ->icon(Heroicon::OutlinedPlay)
                ->color('success')
                ->visible(fn () => $this->record->isStopped())
                ->action(function () {
                    // TODO: Implement start action
                    \Filament\Notifications\Notification::make()
                        ->title('Environment starting')
                        ->body("Starting environment: {$this->record->name}")
                        ->info()
                        ->send();
                }),

            Actions\Action::make('stop')
                ->icon(Heroicon::OutlinedStop)
                ->color('warning')
                ->visible(fn () => $this->record->isRunning())
                ->requiresConfirmation()
                ->action(function () {
                    // TODO: Implement stop action
                    \Filament\Notifications\Notification::make()
                        ->title('Environment stopping')
                        ->body("Stopping environment: {$this->record->name}")
                        ->warning()
                        ->send();
                }),

            Actions\Action::make('access_urls')
                ->icon(Heroicon::OutlinedArrowTopRightOnSquare)
                ->color('info')
                ->visible(fn () => $this->record->isRunning())
                ->action(function () {
                    $urls = $this->record->getAccessUrls();
                    $urlList = collect($urls)->map(fn ($url, $service) => "- {$service}: {$url}")->implode("\n");

                    \Filament\Notifications\Notification::make()
                        ->title('Access URLs')
                        ->body($urlList ?: 'No access URLs available')
                        ->info()
                        ->persistent()
                        ->send();
                }),

            Actions\EditAction::make(),
        ];
    }

    public function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Environment Information')
                    ->schema([
                        TextEntry::make('name')
                            ->weight(FontWeight::Bold),

                        TextEntry::make('description')
                            ->placeholder('No description provided'),

                        BadgeEntry::make('status')
                            ->colors([
                                'warning' => 'provisioning',
                                'success' => 'running',
                                'secondary' => 'stopped',
                                'danger' => 'failed',
                                'gray' => 'destroying',
                            ]),

                        BadgeEntry::make('environment_type')
                            ->label('Type')
                            ->colors([
                                'primary' => 'development',
                                'info' => 'testing',
                                'success' => 'staging',
                                'warning' => 'training',
                            ]),

                        TextEntry::make('template_name')
                            ->label('Template'),

                        TextEntry::make('cluster.name')
                            ->label('Proxmox Cluster'),
                    ])
                    ->columns(3),

                Section::make('Resource Allocation')
                    ->schema([
                        TextEntry::make('total_cpu_cores')
                            ->label('CPU Cores')
                            ->badge()
                            ->color('primary'),

                        TextEntry::make('memory_allocation')
                            ->label('Memory')
                            ->getStateUsing(fn () => round($this->record->total_memory_bytes / (1024 ** 3), 1).' GB')
                            ->badge()
                            ->color('info'),

                        TextEntry::make('storage_allocation')
                            ->label('Storage')
                            ->getStateUsing(fn () => round($this->record->total_storage_bytes / (1024 ** 3), 1).' GB')
                            ->badge()
                            ->color('warning'),

                        TextEntry::make('estimated_cost_per_hour')
                            ->label('Cost/Hour')
                            ->money('USD')
                            ->badge()
                            ->color('success'),

                        TextEntry::make('estimated_monthly_cost')
                            ->label('Monthly Cost')
                            ->getStateUsing(fn () => '$'.number_format($this->record->getEstimatedMonthlyCost(), 2))
                            ->badge()
                            ->color('warning'),

                        TextEntry::make('total_runtime_hours')
                            ->label('Runtime Hours')
                            ->badge()
                            ->color('secondary'),
                    ])
                    ->columns(3),

                Section::make('Status & Health')
                    ->schema([
                        TextEntry::make('health_score')
                            ->label('Health Score')
                            ->getStateUsing(fn () => $this->record->getHealthScore().'%')
                            ->badge()
                            ->color(function (): string {
                                $score = $this->record->getHealthScore();

                                return match (true) {
                                    $score >= 80 => 'success',
                                    $score >= 60 => 'warning',
                                    default => 'danger',
                                };
                            }),

                        TextEntry::make('running_resources')
                            ->label('Running Resources')
                            ->getStateUsing(function () {
                                $resources = $this->record->getRunningResources();

                                return "{$resources['vms']} VMs, {$resources['containers']} Containers";
                            })
                            ->badge()
                            ->color('success'),

                        TextEntry::make('provisioned_at')
                            ->label('Created')
                            ->dateTime()
                            ->since(),

                        TextEntry::make('last_accessed_at')
                            ->label('Last Accessed')
                            ->dateTime()
                            ->since()
                            ->placeholder('Never'),

                        TextEntry::make('expires_at')
                            ->label('Expires')
                            ->dateTime()
                            ->placeholder('Never'),

                        TextEntry::make('time_until_destroy')
                            ->label('Time Until Destroy')
                            ->getStateUsing(fn () => $this->record->getTimeUntilDestroy())
                            ->placeholder('Not scheduled')
                            ->color(function (): string {
                                $time = $this->record->getTimeUntilDestroy();

                                return match (true) {
                                    $time === 'Overdue' => 'danger',
                                    str_contains($time ?? '', 'hour') && ! str_contains($time, 'day') => 'warning',
                                    default => 'secondary',
                                };
                            }),
                    ])
                    ->columns(3),

                Section::make('Network Configuration')
                    ->schema([
                        TextEntry::make('network_vlan')
                            ->label('VLAN ID')
                            ->placeholder('Default'),

                        TextEntry::make('subnet_cidr')
                            ->label('Subnet CIDR')
                            ->placeholder('Auto-assigned'),

                        IconEntry::make('public_access')
                            ->label('Public Access')
                            ->boolean(),

                        KeyValueEntry::make('exposed_ports')
                            ->label('Exposed Ports')
                            ->columnSpanFull(),
                    ])
                    ->columns(3),

                Section::make('Development Configuration')
                    ->schema([
                        TextEntry::make('ide_type')
                            ->label('IDE')
                            ->placeholder('Not configured'),

                        IconEntry::make('ci_cd_enabled')
                            ->label('CI/CD Enabled')
                            ->boolean(),

                        IconEntry::make('backup_enabled')
                            ->label('Backups Enabled')
                            ->boolean(),

                        KeyValueEntry::make('git_repositories')
                            ->label('Git Repositories')
                            ->columnSpanFull(),
                    ])
                    ->columns(3),

                Section::make('Project Information')
                    ->schema([
                        TextEntry::make('project_name')
                            ->label('Project')
                            ->placeholder('Not specified'),

                        TextEntry::make('tags')
                            ->badge()
                            ->separator(','),

                        TextEntry::make('notes')
                            ->label('Notes')
                            ->placeholder('No notes')
                            ->columnSpanFull(),
                    ])
                    ->columns(2),
            ]);
    }
}
