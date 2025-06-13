<?php

namespace App\Filament\Resources\McpConnections;

use App\Filament\Resources\McpConnections\Pages\CreateMcpConnection;
use App\Filament\Resources\McpConnections\Pages\EditMcpConnection;
use App\Filament\Resources\McpConnections\Pages\ListMcpConnections;
use App\Filament\Resources\McpConnections\Schemas\McpConnectionForm;
use App\Filament\Resources\McpConnections\Tables\McpConnectionsTable;
use App\Models\McpConnection;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class McpConnectionResource extends Resource
{
    protected static ?string $model = McpConnection::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    public static function form(Schema $schema): Schema
    {
        return McpConnectionForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return McpConnectionsTable::configure($table);
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
            'index' => ListMcpConnections::route('/'),
            'create' => CreateMcpConnection::route('/create'),
            'edit' => EditMcpConnection::route('/{record}/edit'),
        ];
    }
}
