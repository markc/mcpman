<?php

namespace App\Filament\Resources\McpConnections\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Filament\Schemas\Schema;

class McpConnectionForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->schema([
                TextInput::make('name')
                    ->required()
                    ->columnSpan(1),
                    
                TextInput::make('endpoint_url')
                    ->label('Endpoint URL')
                    ->url()
                    ->required()
                    ->columnSpan(1),
                    
                Select::make('transport_type')
                    ->label('Transport Type')
                    ->options([
                        'stdio' => 'Standard I/O',
                        'http' => 'HTTP',
                        'websocket' => 'WebSocket',
                    ])
                    ->required()
                    ->default('stdio')
                    ->columnSpan(1),
                    
                Select::make('status')
                    ->options([
                        'active' => 'Active',
                        'inactive' => 'Inactive',
                        'error' => 'Error',
                    ])
                    ->required()
                    ->default('inactive')
                    ->columnSpan(1),
                    
                KeyValue::make('auth_config')
                    ->label('Authentication Config')
                    ->keyLabel('Key')
                    ->valueLabel('Value')
                    ->default([
                        'type' => 'bearer',
                        'token' => '',
                    ])
                    ->columnSpanFull(),
                    
                KeyValue::make('capabilities')
                    ->label('Capabilities')
                    ->keyLabel('Capability')
                    ->valueLabel('Enabled')
                    ->default([
                        'tools' => 'true',
                        'prompts' => 'true',
                        'resources' => 'true',
                    ])
                    ->columnSpanFull(),
                    
                KeyValue::make('metadata')
                    ->label('Metadata')
                    ->keyLabel('Key')
                    ->valueLabel('Value')
                    ->columnSpanFull(),
                    
                Placeholder::make('last_connected_at')
                    ->label('Last Connected')
                    ->content(fn ($record) => $record?->last_connected_at?->diffForHumans() ?? 'Never')
                    ->columnSpan(1),
                    
                Placeholder::make('last_error')
                    ->label('Last Error')
                    ->content(fn ($record) => $record?->last_error ?? 'None')
                    ->columnSpan(1),
                    
                Hidden::make('user_id')
                    ->default(fn () => auth()->id()),
            ]);
    }
}
