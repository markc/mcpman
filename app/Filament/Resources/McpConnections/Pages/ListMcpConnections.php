<?php

namespace App\Filament\Resources\McpConnections\Pages;

use App\Filament\Resources\McpConnections\McpConnectionResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListMcpConnections extends ListRecords
{
    protected static string $resource = McpConnectionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
