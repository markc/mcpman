<?php

namespace App\Filament\Resources\Datasets;

use App\Filament\Resources\Datasets\Pages\CreateDataset;
use App\Filament\Resources\Datasets\Pages\EditDataset;
use App\Filament\Resources\Datasets\Pages\ListDatasets;
use App\Models\Dataset;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Set;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Str;

class DatasetResource extends Resource
{
    protected static ?string $model = Dataset::class;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required()
                    ->live(onBlur: true)
                    ->afterStateUpdated(fn (Set $set, ?string $state) => $set('slug', Str::slug($state))),

                TextInput::make('slug')
                    ->required()
                    ->unique(ignoreRecord: true),

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
                    ->default('json'),

                Select::make('status')
                    ->options([
                        'active' => 'Active',
                        'archived' => 'Archived',
                        'processing' => 'Processing',
                    ])
                    ->required()
                    ->default('active'),

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
            ])
            ->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('slug')
                    ->searchable()
                    ->toggleable(),

                TextColumn::make('type')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'json' => 'blue',
                        'csv' => 'gray',
                        'xml' => 'orange',
                        'yaml' => 'purple',
                        'text' => 'gray',
                        default => 'gray',
                    }),

                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'active' => 'success',
                        'processing' => 'warning',
                        'archived' => 'danger',
                        default => 'gray',
                    }),

                TextColumn::make('user.name')
                    ->label('Owner')
                    ->sortable(),

                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListDatasets::route('/'),
            'create' => CreateDataset::route('/create'),
            'edit' => EditDataset::route('/{record}/edit'),
        ];
    }
}
