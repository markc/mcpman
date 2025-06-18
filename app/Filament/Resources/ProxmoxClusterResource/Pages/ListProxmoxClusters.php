<?php

namespace App\Filament\Resources\ProxmoxClusterResource\Pages;

use App\Filament\Resources\ProxmoxClusterResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListProxmoxClusters extends ListRecords
{
    protected static string $resource = ProxmoxClusterResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
