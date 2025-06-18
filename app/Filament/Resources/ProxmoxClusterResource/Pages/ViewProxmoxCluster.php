<?php

namespace App\Filament\Resources\ProxmoxClusterResource\Pages;

use App\Filament\Resources\ProxmoxClusterResource;
use App\Services\ProxmoxMonitoringService;
use Filament\Actions;
use Filament\Infolists\Components\BadgeEntry;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Enums\FontWeight;

class ViewProxmoxCluster extends ViewRecord
{
    protected static string $resource = ProxmoxClusterResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('health_check')
                ->icon('heroicon-o-heart')
                ->color('info')
                ->action(function () {
                    $monitoringService = new ProxmoxMonitoringService($this->record);
                    $healthData = $monitoringService->performHealthCheck();

                    \Filament\Notifications\Notification::make()
                        ->title('Health check completed')
                        ->body("Overall health score: {$healthData['overall_health']}%")
                        ->success()
                        ->send();
                }),

            Actions\EditAction::make(),
        ];
    }

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Section::make('Cluster Information')
                    ->schema([
                        TextEntry::make('name')
                            ->weight(FontWeight::Bold),

                        TextEntry::make('description')
                            ->placeholder('No description provided'),

                        BadgeEntry::make('status')
                            ->colors([
                                'success' => 'active',
                                'warning' => 'maintenance',
                                'danger' => 'error',
                                'secondary' => 'inactive',
                            ]),

                        TextEntry::make('health_score')
                            ->label('Health Score')
                            ->getStateUsing(fn () => $this->record->getHealthScore().'%')
                            ->color(function (): string {
                                $score = $this->record->getHealthScore();

                                return match (true) {
                                    $score >= 80 => 'success',
                                    $score >= 60 => 'warning',
                                    default => 'danger',
                                };
                            }),
                    ])
                    ->columns(2),

                Section::make('Connection Details')
                    ->schema([
                        TextEntry::make('api_endpoint')
                            ->label('API Endpoint'),

                        TextEntry::make('api_port')
                            ->label('API Port'),

                        TextEntry::make('username'),

                        IconEntry::make('verify_tls')
                            ->label('TLS Verification')
                            ->boolean(),

                        TextEntry::make('timeout')
                            ->label('Timeout')
                            ->suffix(' seconds'),

                        TextEntry::make('last_seen_at')
                            ->label('Last Seen')
                            ->dateTime()
                            ->since(),
                    ])
                    ->columns(3),

                Section::make('Resource Overview')
                    ->schema([
                        TextEntry::make('total_vms')
                            ->label('Virtual Machines')
                            ->badge()
                            ->color('primary'),

                        TextEntry::make('running_vms')
                            ->label('Running VMs')
                            ->badge()
                            ->color('success'),

                        TextEntry::make('total_containers')
                            ->label('Containers')
                            ->badge()
                            ->color('info'),

                        TextEntry::make('running_containers')
                            ->label('Running Containers')
                            ->badge()
                            ->color('success'),

                        TextEntry::make('online_nodes')
                            ->label('Online Nodes')
                            ->badge()
                            ->color('success'),

                        TextEntry::make('estimated_monthly_cost')
                            ->label('Monthly Cost')
                            ->getStateUsing(fn () => '$'.number_format($this->record->getEstimatedMonthlyCost(), 2))
                            ->badge()
                            ->color('warning'),
                    ])
                    ->columns(3),

                Section::make('Resource Utilization')
                    ->schema([
                        TextEntry::make('cpu_utilization')
                            ->label('CPU Usage')
                            ->getStateUsing(function () {
                                $utilization = $this->record->getResourceUtilization();

                                return $utilization['cpu'].'%';
                            })
                            ->badge()
                            ->color(function (): string {
                                $utilization = $this->record->getResourceUtilization();
                                $cpu = $utilization['cpu'];

                                return match (true) {
                                    $cpu >= 90 => 'danger',
                                    $cpu >= 75 => 'warning',
                                    default => 'success',
                                };
                            }),

                        TextEntry::make('memory_utilization')
                            ->label('Memory Usage')
                            ->getStateUsing(function () {
                                $utilization = $this->record->getResourceUtilization();

                                return $utilization['memory'].'%';
                            })
                            ->badge()
                            ->color(function (): string {
                                $utilization = $this->record->getResourceUtilization();
                                $memory = $utilization['memory'];

                                return match (true) {
                                    $memory >= 90 => 'danger',
                                    $memory >= 75 => 'warning',
                                    default => 'success',
                                };
                            }),

                        TextEntry::make('storage_utilization')
                            ->label('Storage Usage')
                            ->getStateUsing(function () {
                                $utilization = $this->record->getResourceUtilization();

                                return $utilization['storage'].'%';
                            })
                            ->badge()
                            ->color(function (): string {
                                $utilization = $this->record->getResourceUtilization();
                                $storage = $utilization['storage'];

                                return match (true) {
                                    $storage >= 95 => 'danger',
                                    $storage >= 85 => 'warning',
                                    default => 'success',
                                };
                            }),
                    ])
                    ->columns(3),
            ]);
    }
}
