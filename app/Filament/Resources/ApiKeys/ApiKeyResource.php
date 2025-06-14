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
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Str;

class ApiKeyResource extends Resource
{
    protected static ?string $model = ApiKey::class;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required(),

                TextInput::make('key')
                    ->label('API Key')
                    ->default(fn () => 'mcp_'.Str::random(32))
                    ->required()
                    ->unique(ignoreRecord: true),

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
                    ->default(true),

                DateTimePicker::make('expires_at')
                    ->label('Expires At')
                    ->nullable(),

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
            'index' => ListApiKeys::route('/'),
            'create' => CreateApiKey::route('/create'),
            'edit' => EditApiKey::route('/{record}/edit'),
        ];
    }
}
