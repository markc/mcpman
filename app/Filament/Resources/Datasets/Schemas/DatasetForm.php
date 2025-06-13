<?php

namespace App\Filament\Resources\Datasets\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Set;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

class DatasetForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->schema([
                TextInput::make('name')
                    ->required()
                    ->live(onBlur: true)
                    ->afterStateUpdated(fn (Set $set, ?string $state) => $set('slug', Str::slug($state)))
                    ->columnSpan(1),
                    
                TextInput::make('slug')
                    ->required()
                    ->unique(ignoreRecord: true)
                    ->columnSpan(1),
                    
                Textarea::make('description')
                    ->rows(3)
                    ->columnSpanFull(),
                    
                Select::make('type')
                    ->options([
                        'json' => 'JSON',
                        'csv' => 'CSV',
                        'xml' => 'XML',
                        'yaml' => 'YAML',
                        'text' => 'Text',
                    ])
                    ->required()
                    ->default('json')
                    ->columnSpan(1),
                    
                Select::make('status')
                    ->options([
                        'active' => 'Active',
                        'archived' => 'Archived',
                        'processing' => 'Processing',
                    ])
                    ->required()
                    ->default('active')
                    ->columnSpan(1),
                    
                KeyValue::make('schema')
                    ->label('Schema Definition')
                    ->keyLabel('Field')
                    ->valueLabel('Type')
                    ->columnSpanFull(),
                    
                KeyValue::make('metadata')
                    ->label('Metadata')
                    ->keyLabel('Key')
                    ->valueLabel('Value')
                    ->columnSpanFull(),
                    
                Hidden::make('user_id')
                    ->default(fn () => auth()->id()),
            ]);
    }
}
