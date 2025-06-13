<?php

namespace App\Filament\Resources\ApiKeys\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\Hidden;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

class ApiKeyForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->schema([
                TextInput::make('name')
                    ->required()
                    ->columnSpan(1),
                    
                TextInput::make('key')
                    ->label('API Key')
                    ->default(fn () => 'mcp_' . Str::random(32))
                    ->required()
                    ->unique(ignoreRecord: true)
                    ->columnSpan(1),
                    
                KeyValue::make('permissions')
                    ->label('Permissions')
                    ->keyLabel('Resource')
                    ->valueLabel('Actions')
                    ->default([
                        'datasets' => 'read,write',
                        'documents' => 'read,write',
                        'connections' => 'read',
                    ])
                    ->columnSpanFull(),
                    
                KeyValue::make('rate_limits')
                    ->label('Rate Limits')
                    ->keyLabel('Endpoint')
                    ->valueLabel('Limit')
                    ->default([
                        'requests_per_minute' => '60',
                        'requests_per_hour' => '1000',
                    ])
                    ->columnSpanFull(),
                    
                Toggle::make('is_active')
                    ->label('Active')
                    ->default(true)
                    ->columnSpan(1),
                    
                DateTimePicker::make('expires_at')
                    ->label('Expires At')
                    ->nullable()
                    ->columnSpan(1),
                    
                Hidden::make('user_id')
                    ->default(fn () => auth()->id()),
            ]);
    }
}
