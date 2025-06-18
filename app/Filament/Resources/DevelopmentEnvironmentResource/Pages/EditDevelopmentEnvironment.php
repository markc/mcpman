<?php

namespace App\Filament\Resources\DevelopmentEnvironmentResource\Pages;

use App\Filament\Resources\DevelopmentEnvironmentResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditDevelopmentEnvironment extends EditRecord
{
    protected static string $resource = DevelopmentEnvironmentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        // Convert bytes to GB for form display
        if (isset($data['total_memory_bytes'])) {
            $data['total_memory_gb'] = $data['total_memory_bytes'] / (1024 * 1024 * 1024);
        }

        if (isset($data['total_storage_bytes'])) {
            $data['total_storage_gb'] = $data['total_storage_bytes'] / (1024 * 1024 * 1024);
        }

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
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
}
