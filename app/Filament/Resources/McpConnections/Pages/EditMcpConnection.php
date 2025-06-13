<?php

namespace App\Filament\Resources\McpConnections\Pages;

use App\Filament\Resources\McpConnections\McpConnectionResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditMcpConnection extends EditRecord
{
    protected static string $resource = McpConnectionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
