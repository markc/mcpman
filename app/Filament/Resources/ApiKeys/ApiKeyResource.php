<?php

namespace App\Filament\Resources\ApiKeys;

use App\Filament\Resources\ApiKeys\Pages\CreateApiKey;
use App\Filament\Resources\ApiKeys\Pages\EditApiKey;
use App\Filament\Resources\ApiKeys\Pages\ListApiKeys;
use App\Models\ApiKey;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

class ApiKeyResource extends Resource
{
    protected static ?string $model = ApiKey::class;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('API Key Information')
                    ->description('Basic API key identification and authentication')
                    ->schema([
                        TextInput::make('name')
                            ->label('Key Name')
                            ->required()
                            ->maxLength(255)
                            ->helperText('Descriptive name for this API key'),

                        TextInput::make('key')
                            ->label('API Key Value')
                            ->helperText('Unique key for API authentication (auto-generated)')
                            ->default(fn () => 'mcp_'.Str::random(32))
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->columnSpanFull(),
                    ])
                    ->columns(2)
                    ->columnSpanFull(),

                Section::make('Access Control')
                    ->description('Permissions and usage limitations')
                    ->schema([
                        KeyValue::make('permissions')
                            ->label('Resource Permissions')
                            ->helperText('Define what resources this key can access and what actions are allowed')
                            ->keyLabel('Resource Type')
                            ->valueLabel('Actions (comma-separated)')
                            ->default([
                                'datasets' => 'read,write',
                                'documents' => 'read,write',
                                'connections' => 'read',
                            ])
                            ->columnSpanFull(),

                        KeyValue::make('rate_limits')
                            ->label('Rate Limiting')
                            ->helperText('Control API usage frequency to prevent abuse')
                            ->keyLabel('Time Window')
                            ->valueLabel('Request Limit')
                            ->default([
                                'requests_per_minute' => '60',
                                'requests_per_hour' => '1000',
                            ])
                            ->columnSpanFull(),
                    ])
                    ->columnSpanFull(),

                Section::make('Key Settings')
                    ->description('Activation status and expiration settings')
                    ->schema([
                        Toggle::make('is_active')
                            ->label('Active')
                            ->helperText('Enable or disable this API key')
                            ->default(true),

                        DateTimePicker::make('expires_at')
                            ->label('Expiration Date')
                            ->helperText('Optional: Set when this key should expire (leave empty for no expiration)')
                            ->nullable(),
                    ])
                    ->columns(2)
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

                TextColumn::make('key')
                    ->label('API Key')
                    ->limit(20)
                    ->tooltip(fn ($record) => $record->key),

                IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean(),

                TextColumn::make('expires_at')
                    ->label('Expires')
                    ->dateTime()
                    ->sortable()
                    ->placeholder('Never'),

                TextColumn::make('user.name')
                    ->label('Owner')
                    ->sortable(),

                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(),
            ])
            ->filters([
                Filter::make('is_active')
                    ->label('Active Keys Only')
                    ->query(fn (Builder $query): Builder => $query->where('is_active', true))
                    ->toggle()
                    ->default(),

                Filter::make('expires_soon')
                    ->label('Expires Within 30 Days')
                    ->query(fn (Builder $query): Builder => $query->whereNotNull('expires_at')->where('expires_at', '<=', now()->addDays(30)))
                    ->toggle(),

                Filter::make('never_expires')
                    ->label('Never Expires')
                    ->query(fn (Builder $query): Builder => $query->whereNull('expires_at'))
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
            'index' => ListApiKeys::route('/'),
            'create' => CreateApiKey::route('/create'),
            'edit' => EditApiKey::route('/{record}/edit'),
        ];
    }
}
