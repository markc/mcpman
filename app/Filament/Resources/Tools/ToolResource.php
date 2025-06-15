<?php

namespace App\Filament\Resources\Tools;

use App\Filament\Resources\Tools\Pages\CreateTool;
use App\Filament\Resources\Tools\Pages\EditTool;
use App\Filament\Resources\Tools\Pages\ListTools;
use App\Models\McpConnection;
use App\Models\Tool;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
// Table row actions (ViewAction, EditAction, DeleteAction) are not available in this Filament v4 version
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ToolResource extends Resource
{
    protected static ?string $model = Tool::class;

    protected static ?string $navigationLabel = 'Tools';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required()
                    ->maxLength(255),

                TextInput::make('slug')
                    ->maxLength(255)
                    ->unique(ignoreRecord: true),

                Select::make('mcp_connection_id')
                    ->label('MCP Connection')
                    ->options(fn () => McpConnection::pluck('name', 'id')->toArray())
                    ->required()
                    ->searchable(),

                Textarea::make('description')
                    ->columnSpanFull(),

                TextInput::make('version')
                    ->default('1.0.0')
                    ->maxLength(255),

                Select::make('category')
                    ->options([
                        'general' => 'General',
                        'filesystem' => 'File System',
                        'development' => 'Development',
                        'web' => 'Web & Network',
                        'search' => 'Search & Analysis',
                        'data' => 'Data & Database',
                        'ai' => 'AI & Processing',
                    ])
                    ->default('general')
                    ->required(),

                Toggle::make('is_active')
                    ->default(true),

                Toggle::make('is_favorite')
                    ->default(false),

                TagsInput::make('tags')
                    ->placeholder('Add tags...')
                    ->columnSpanFull(),

                KeyValue::make('input_schema')
                    ->label('Input Schema')
                    ->keyLabel('Property')
                    ->valueLabel('Definition')
                    ->columnSpanFull(),

                KeyValue::make('output_schema')
                    ->label('Output Schema')
                    ->keyLabel('Property')
                    ->valueLabel('Definition')
                    ->columnSpanFull(),

                KeyValue::make('metadata')
                    ->label('Metadata')
                    ->keyLabel('Key')
                    ->valueLabel('Value')
                    ->columnSpanFull(),
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
                        'filesystem' => 'info',
                        'development' => 'success',
                        'web' => 'warning',
                        'search' => 'primary',
                        'data' => 'secondary',
                        'ai' => 'danger',
                        default => 'gray',
                    }),

                TextColumn::make('mcpConnection.name')
                    ->label('Connection')
                    ->sortable()
                    ->default('N/A'),

                TextColumn::make('usage_count')
                    ->label('Usage')
                    ->numeric()
                    ->sortable(),

                TextColumn::make('success_rate')
                    ->label('Success Rate')
                    ->suffix('%')
                    ->numeric()
                    ->sortable(),

                TextColumn::make('average_execution_time')
                    ->label('Avg Time')
                    ->suffix('s')
                    ->numeric()
                    ->sortable(),

                IconColumn::make('is_active')
                    ->boolean(),

                IconColumn::make('is_favorite')
                    ->boolean(),

                TextColumn::make('last_used_at')
                    ->dateTime()
                    ->sortable(),

                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('category')
                    ->options([
                        'general' => 'General',
                        'filesystem' => 'File System',
                        'development' => 'Development',
                        'web' => 'Web & Network',
                        'search' => 'Search & Analysis',
                        'data' => 'Data & Database',
                        'ai' => 'AI & Processing',
                    ]),

                SelectFilter::make('mcp_connection_id')
                    ->label('Connection')
                    ->options(fn () => McpConnection::pluck('name', 'id')->toArray()),

                Filter::make('is_active')
                    ->toggle(),

                Filter::make('is_favorite')
                    ->toggle(),

                Filter::make('recently_used')
                    ->query(fn (Builder $query): Builder => $query->recentlyUsed(7))
                    ->toggle(),

                Filter::make('popular')
                    ->query(fn (Builder $query): Builder => $query->popular(10))
                    ->toggle(),
            ])
            // ->actions([
            //     ViewAction::make(),
            //     EditAction::make(),
            //     DeleteAction::make(),
            // ])
            ->bulkActions([
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
            'index' => ListTools::route('/'),
            'create' => CreateTool::route('/create'),
            'edit' => EditTool::route('/{record}/edit'),
        ];
    }
}
