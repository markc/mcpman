<?php

namespace App\Filament\Resources\ProxmoxClusterResource\Pages;

use App\Filament\Resources\ProxmoxClusterResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditProxmoxCluster extends EditRecord
{
    protected static string $resource = ProxmoxClusterResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }
}
