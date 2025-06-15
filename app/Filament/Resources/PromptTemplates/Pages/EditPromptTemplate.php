<?php

namespace App\Filament\Resources\PromptTemplates\Pages;

use App\Filament\Resources\PromptTemplates\PromptTemplateResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditPromptTemplate extends EditRecord
{
    protected static string $resource = PromptTemplateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
