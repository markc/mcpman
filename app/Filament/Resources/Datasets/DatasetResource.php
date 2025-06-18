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
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Set;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

class DatasetResource extends Resource
{
    protected static ?string $model = Dataset::class;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Placeholder::make('dataset_info_header')
                    ->label('Dataset Information')
                    ->content('Basic dataset details and identification')
                    ->columnSpanFull(),
                TextInput::make('name')
                    ->label('Dataset Name')
                    ->required()
                    ->maxLength(255)
                    ->helperText('The dataset name will auto-generate the slug')
                    ->live(onBlur: true)
                    ->afterStateUpdated(fn (Set $set, ?string $state) => $set('slug', Str::slug($state))),

                TextInput::make('slug')
                    ->label('URL Slug')
                    ->required()
                    ->maxLength(255)
                    ->helperText('URL-friendly identifier for the dataset')
                    ->unique(ignoreRecord: true),

                Textarea::make('description')
                    ->label('Description')
                    ->helperText('Detailed description of the dataset purpose and contents')
                    ->rows(3)
                    ->columnSpanFull(),

                Placeholder::make('dataset_config_header')
                    ->label('Dataset Configuration')
                    ->content('Data format and processing settings')
                    ->columnSpanFull(),
                Select::make('type')
                    ->label('Data Format')
                    ->helperText('Select the primary data format for this dataset')
                    ->options([
                        'json' => 'JSON - Structured data',
                        'csv' => 'CSV - Tabular data',
                        'xml' => 'XML - Markup data',
                        'yaml' => 'YAML - Configuration data',
                        'text' => 'Text - Plain text data',
                    ])
                    ->required()
                    ->default('json'),

                Select::make('status')
                    ->label('Processing Status')
                    ->helperText('Current state of the dataset')
                    ->options([
                        'active' => 'Active - Ready for use',
                        'processing' => 'Processing - Being updated',
                        'archived' => 'Archived - Read-only',
                    ])
                    ->required()
                    ->default('active'),

                Placeholder::make('schema_metadata_header')
                    ->label('Schema & Metadata')
                    ->content('Data structure and custom properties')
                    ->columnSpanFull(),
                KeyValue::make('schema')
                    ->label('Schema Definition')
                    ->helperText('Define the structure and data types for your dataset')
                    ->keyLabel('Field Name')
                    ->valueLabel('Data Type')
                    ->columnSpanFull(),

                KeyValue::make('metadata')
                    ->label('Custom Metadata')
                    ->helperText('Additional properties and configuration options')
                    ->keyLabel('Property')
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
                SelectFilter::make('type')
                    ->options([
                        'json' => 'JSON - Structured data',
                        'csv' => 'CSV - Tabular data',
                        'xml' => 'XML - Markup data',
                        'yaml' => 'YAML - Configuration data',
                        'text' => 'Text - Plain text data',
                    ]),

                SelectFilter::make('status')
                    ->options([
                        'active' => 'Active',
                        'processing' => 'Processing',
                        'archived' => 'Archived',
                    ]),

                Filter::make('has_schema')
                    ->label('Has Schema Definition')
                    ->query(fn (Builder $query): Builder => $query->whereNotNull('schema')->whereJsonLength('schema', '>', 0))
                    ->toggle(),

                Filter::make('recent')
                    ->label('Created Recently')
                    ->query(fn (Builder $query): Builder => $query->where('created_at', '>=', now()->subWeek()))
                    ->toggle(),
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
