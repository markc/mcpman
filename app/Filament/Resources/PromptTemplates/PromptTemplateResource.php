<?php

namespace App\Filament\Resources\PromptTemplates;

use App\Filament\Resources\PromptTemplates\Pages\CreatePromptTemplate;
use App\Filament\Resources\PromptTemplates\Pages\EditPromptTemplate;
use App\Filament\Resources\PromptTemplates\Pages\ListPromptTemplates;
use App\Models\PromptTemplate;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Set;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

class PromptTemplateResource extends Resource
{
    protected static ?string $model = PromptTemplate::class;

    protected static ?string $navigationLabel = 'Prompt Templates';

    protected static ?int $navigationSort = 4;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Template Information')
                    ->description('Basic template details and identification')
                    ->schema([
                        TextInput::make('name')
                            ->label('Template Name')
                            ->required()
                            ->maxLength(255)
                            ->helperText('Descriptive name for this prompt template')
                            ->live(onBlur: true)
                            ->afterStateUpdated(fn (Set $set, ?string $state) => $set('slug', Str::slug($state))),

                        TextInput::make('slug')
                            ->label('URL Slug')
                            ->required()
                            ->maxLength(255)
                            ->helperText('URL-friendly identifier for the template')
                            ->unique(ignoreRecord: true),

                        Textarea::make('description')
                            ->label('Description')
                            ->helperText('Detailed description of the template purpose and usage')
                            ->rows(3)
                            ->columnSpanFull(),

                        Select::make('category')
                            ->label('Category')
                            ->helperText('Template category for organization')
                            ->options(PromptTemplate::getCategories())
                            ->required()
                            ->default('general'),
                    ])
                    ->columns(2)
                    ->columnSpanFull(),

                Section::make('Template Content')
                    ->description('The main prompt template and variable configuration')
                    ->schema([
                        Textarea::make('template_content')
                            ->label('Template Content')
                            ->helperText('Use {{variable_name}} syntax for variables (e.g., "Analyze this {{data_type}} and provide {{output_format}} insights")')
                            ->required()
                            ->rows(8)
                            ->columnSpanFull(),

                        Textarea::make('instructions')
                            ->label('Usage Instructions')
                            ->helperText('Optional: Instructions for how to use this template effectively')
                            ->rows(4)
                            ->columnSpanFull(),

                        KeyValue::make('variables')
                            ->label('Template Variables')
                            ->helperText('Define variables found in the template (auto-detected from content)')
                            ->keyLabel('Variable Name')
                            ->valueLabel('Description/Type')
                            ->columnSpanFull(),
                    ])
                    ->columnSpanFull(),

                Section::make('Template Settings')
                    ->description('Visibility, tagging, and additional configuration')
                    ->schema([
                        Toggle::make('is_active')
                            ->label('Active')
                            ->helperText('Enable or disable this template')
                            ->default(true),

                        Toggle::make('is_public')
                            ->label('Public Template')
                            ->helperText('Make this template available to all users')
                            ->default(false),

                        TagsInput::make('tags')
                            ->label('Tags')
                            ->helperText('Add tags for easier searching and categorization')
                            ->placeholder('Add tags...')
                            ->columnSpanFull(),

                        KeyValue::make('metadata')
                            ->label('Additional Metadata')
                            ->helperText('Custom properties and configuration options')
                            ->keyLabel('Property')
                            ->valueLabel('Value')
                            ->columnSpanFull(),
                    ])
                    ->columns(2)
                    ->columnSpanFull(),

                Section::make('Usage Statistics')
                    ->description('Template performance and usage metrics')
                    ->schema([
                        Placeholder::make('usage_count')
                            ->label('Times Used')
                            ->content(fn ($record) => $record?->usage_count ?? 0),

                        Placeholder::make('average_rating')
                            ->label('Average Rating')
                            ->content(fn ($record) => $record?->average_rating ? number_format($record->average_rating, 2).'/5.0' : 'Not rated'),

                        Placeholder::make('created_at')
                            ->label('Created')
                            ->content(fn ($record) => $record?->created_at?->format('M j, Y g:i A') ?? 'New template'),

                        Placeholder::make('updated_at')
                            ->label('Last Updated')
                            ->content(fn ($record) => $record?->updated_at?->diffForHumans() ?? 'Never'),
                    ])
                    ->columns(2)
                    ->columnSpanFull()
                    ->hiddenOn('create'),

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

                TextColumn::make('category')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'development' => 'success',
                        'data_analysis' => 'info',
                        'creative' => 'warning',
                        'technical' => 'primary',
                        'debugging' => 'danger',
                        'automation' => 'secondary',
                        default => 'gray',
                    }),

                TextColumn::make('usage_count')
                    ->label('Usage')
                    ->numeric()
                    ->sortable(),

                TextColumn::make('average_rating')
                    ->label('Rating')
                    ->formatStateUsing(fn ($state) => $state ? number_format($state, 1).'/5' : 'N/A')
                    ->sortable(),

                IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean(),

                IconColumn::make('is_public')
                    ->label('Public')
                    ->boolean(),

                TextColumn::make('user.name')
                    ->label('Created By')
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('category')
                    ->options(PromptTemplate::getCategories()),

                Filter::make('is_active')
                    ->label('Active Templates Only')
                    ->query(fn (Builder $query): Builder => $query->where('is_active', true))
                    ->toggle()
                    ->default(),

                Filter::make('is_public')
                    ->label('Public Templates')
                    ->query(fn (Builder $query): Builder => $query->where('is_public', true))
                    ->toggle(),

                Filter::make('my_templates')
                    ->label('My Templates')
                    ->query(fn (Builder $query): Builder => $query->where('user_id', auth()->id()))
                    ->toggle(),

                Filter::make('popular')
                    ->label('Popular (10+ uses)')
                    ->query(fn (Builder $query): Builder => $query->where('usage_count', '>=', 10))
                    ->toggle(),

                Filter::make('highly_rated')
                    ->label('Highly Rated (4.0+)')
                    ->query(fn (Builder $query): Builder => $query->where('average_rating', '>=', 4.0))
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
            ])
            ->defaultSort('usage_count', 'desc');
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
            'index' => ListPromptTemplates::route('/'),
            'create' => CreatePromptTemplate::route('/create'),
            'edit' => EditPromptTemplate::route('/{record}/edit'),
        ];
    }
}
