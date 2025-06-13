<?php

namespace App\Filament\Resources\Documents\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Set;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

class DocumentForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->schema([
                TextInput::make('title')
                    ->required()
                    ->live(onBlur: true)
                    ->afterStateUpdated(fn (Set $set, ?string $state) => $set('slug', Str::slug($state)))
                    ->columnSpan(1),
                    
                TextInput::make('slug')
                    ->required()
                    ->unique(ignoreRecord: true)
                    ->columnSpan(1),
                    
                Select::make('dataset_id')
                    ->label('Dataset')
                    ->relationship('dataset', 'name')
                    ->searchable()
                    ->preload()
                    ->columnSpan(1),
                    
                Select::make('type')
                    ->options([
                        'text' => 'Text',
                        'json' => 'JSON',
                        'markdown' => 'Markdown',
                        'html' => 'HTML',
                    ])
                    ->required()
                    ->default('text')
                    ->columnSpan(1),
                    
                Textarea::make('content')
                    ->rows(10)
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
