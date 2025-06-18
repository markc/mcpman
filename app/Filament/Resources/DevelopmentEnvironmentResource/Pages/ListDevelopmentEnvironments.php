<?php

namespace App\Filament\Resources\DevelopmentEnvironmentResource\Pages;

use App\Filament\Resources\DevelopmentEnvironmentResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Enums\Heroicon;

class ListDevelopmentEnvironments extends ListRecords
{
    protected static string $resource = DevelopmentEnvironmentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('Create Environment')
                ->icon(Heroicon::OUTLINE_PLUS),
        ];
    }
}
