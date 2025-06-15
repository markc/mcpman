<?php

namespace App\Filament\Resources\Documents;

use App\Filament\Resources\Documents\Pages\CreateDocument;
use App\Filament\Resources\Documents\Pages\EditDocument;
use App\Filament\Resources\Documents\Pages\ListDocuments;
use App\Models\Document;
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
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

class DocumentResource extends Resource
{
    protected static ?string $model = Document::class;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Document Information')
                    ->description('Basic document details and identification')
                    ->schema([
                        TextInput::make('title')
                            ->required()
                            ->maxLength(255)
                            ->helperText('The document title will auto-generate the slug')
                            ->live(onBlur: true)
                            ->afterStateUpdated(fn (Set $set, ?string $state) => $set('slug', Str::slug($state))),

                        TextInput::make('slug')
                            ->required()
                            ->maxLength(255)
                            ->helperText('URL-friendly identifier for the document')
                            ->unique(ignoreRecord: true),

                        Select::make('dataset_id')
                            ->label('Dataset')
                            ->helperText('Optional: Associate with a dataset for organization')
                            ->relationship('dataset', 'name')
                            ->searchable()
                            ->preload()
                            ->nullable(),
                    ])
                    ->columns(2)
                    ->columnSpanFull(),

                Section::make('Document Configuration')
                    ->description('Content type and publication settings')
                    ->schema([
                        Select::make('type')
                            ->label('Content Type')
                            ->helperText('Select the format of your document content')
                            ->options([
                                'text' => 'Plain Text',
                                'json' => 'JSON Data',
                                'markdown' => 'Markdown',
                                'html' => 'HTML',
                            ])
                            ->required()
                            ->default('text'),

                        Select::make('status')
                            ->label('Publication Status')
                            ->helperText('Control document visibility and availability')
                            ->options([
                                'draft' => 'Draft (Hidden)',
                                'published' => 'Published (Visible)',
                                'archived' => 'Archived (Read-only)',
                            ])
                            ->required()
                            ->default('draft'),
                    ])
                    ->columns(2)
                    ->columnSpanFull(),

                Section::make('Document Content')
                    ->description('Main document content and metadata')
                    ->schema([
                        Textarea::make('content')
                            ->label('Content')
                            ->helperText('The main content of your document')
                            ->rows(10)
                            ->columnSpanFull(),

                        KeyValue::make('metadata')
                            ->label('Custom Metadata')
                            ->helperText('Additional key-value pairs for document metadata')
                            ->keyLabel('Property')
                            ->valueLabel('Value')
                            ->columnSpanFull(),
                    ])
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
                TextColumn::make('title')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('slug')
                    ->searchable()
                    ->toggleable(),

                TextColumn::make('dataset.name')
                    ->label('Dataset')
                    ->sortable()
                    ->placeholder('None'),

                TextColumn::make('type')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'text' => 'gray',
                        'json' => 'blue',
                        'markdown' => 'green',
                        'html' => 'orange',
                        default => 'gray',
                    }),

                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'draft' => 'warning',
                        'published' => 'success',
                        'archived' => 'danger',
                        default => 'gray',
                    }),

                TextColumn::make('user.name')
                    ->label('Author')
                    ->sortable(),

                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('type')
                    ->options([
                        'text' => 'Plain Text',
                        'json' => 'JSON Data',
                        'markdown' => 'Markdown',
                        'html' => 'HTML',
                    ]),

                SelectFilter::make('status')
                    ->options([
                        'draft' => 'Draft',
                        'published' => 'Published',
                        'archived' => 'Archived',
                    ]),

                SelectFilter::make('dataset')
                    ->relationship('dataset', 'name')
                    ->searchable()
                    ->preload(),

                Filter::make('has_content')
                    ->label('Has Content')
                    ->query(fn (Builder $query): Builder => $query->whereNotNull('content')->where('content', '!=', ''))
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
            'index' => ListDocuments::route('/'),
            'create' => CreateDocument::route('/create'),
            'edit' => EditDocument::route('/{record}/edit'),
        ];
    }
}
