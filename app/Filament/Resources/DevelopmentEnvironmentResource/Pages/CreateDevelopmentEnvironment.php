<?php

namespace App\Filament\Resources\DevelopmentEnvironmentResource\Pages;

use App\Filament\Resources\DevelopmentEnvironmentResource;
use Filament\Resources\Pages\CreateRecord;

class CreateDevelopmentEnvironment extends CreateRecord
{
    protected static string $resource = DevelopmentEnvironmentResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['user_id'] = auth()->id();

        // Convert GB to bytes for database storage
        if (isset($data['total_memory_gb'])) {
            $data['total_memory_bytes'] = $data['total_memory_gb'] * 1024 * 1024 * 1024;
            unset($data['total_memory_gb']);
        }

        if (isset($data['total_storage_gb'])) {
            $data['total_storage_bytes'] = $data['total_storage_gb'] * 1024 * 1024 * 1024;
            unset($data['total_storage_gb']);
        }

        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->getRecord()]);
    }
}
