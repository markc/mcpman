# Stage 04: Filament v4 Resources (CRITICAL PATTERNS)

## Overview
Create Filament v4 admin resources using the correct Schema patterns for forms. This stage is **CRITICAL** for establishing proper Filament v4 compliance throughout the project.

## ⚠️ CRITICAL FILAMENT V4 RESOURCE PATTERNS

**ESSENTIAL**: Resources MUST use these exact patterns to avoid namespace errors:

### Resource Form Pattern
```php
use Filament\Schemas\Schema; // NOT Form
use Filament\Forms\Components\TextInput; // Correct namespace

public static function form(Schema $schema): Schema
{
    return $schema
        ->components([...]) // NOT ->schema([...])
        ->columns(2); // Grid layout
}
```

## Step-by-Step Implementation

### 1. Create Resource Directories and Files

```bash
# Create Filament resource directories
mkdir -p app/Filament/Resources/{Datasets,Documents,ApiKeys,McpConnections}
mkdir -p app/Filament/Resources/{Datasets,Documents,ApiKeys,McpConnections}/Pages

# Generate the resource files
php artisan make:filament-resource Dataset
php artisan make:filament-resource Document  
php artisan make:filament-resource ApiKey
php artisan make:filament-resource McpConnection
```

### 2. Dataset Resource

**app/Filament/Resources/Datasets/DatasetResource.php**:
```php
<?php

namespace App\Filament\Resources\Datasets;

use App\Models\Dataset;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\KeyValue;
use Filament\Schemas\Schema;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Actions\EditAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\BulkActionGroup;

class DatasetResource extends Resource
{
    protected static ?string $model = Dataset::class;
    
    protected static ?string $navigationIcon = 'heroicon-o-folder';
    
    protected static ?string $navigationGroup = 'MCP Management';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required()
                    ->live(onBlur: true)
                    ->afterStateUpdated(function (string $operation, $state, callable $set) {
                        if ($operation !== 'create') return;
                        $set('slug', \Illuminate\Support\Str::slug($state));
                    }),

                TextInput::make('slug')
                    ->required()
                    ->unique(Dataset::class, 'slug', ignoreRecord: true),

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
                        'inactive' => 'Inactive',
                        'processing' => 'Processing',
                    ])
                    ->required()
                    ->default('active'),

                Textarea::make('description')
                    ->rows(3)
                    ->columnSpanFull(),

                KeyValue::make('schema')
                    ->label('JSON Schema')
                    ->keyLabel('Property')
                    ->valueLabel('Type/Description')
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

                TextColumn::make('type')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'json' => 'blue',
                        'csv' => 'green',
                        'xml' => 'orange',
                        'yaml' => 'purple',
                        'text' => 'gray',
                        default => 'gray',
                    }),

                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'active' => 'success',
                        'inactive' => 'warning',
                        'processing' => 'info',
                        default => 'gray',
                    }),

                TextColumn::make('documents_count')
                    ->counts('documents')
                    ->label('Documents'),

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

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListDatasets::route('/'),
            'create' => Pages\CreateDataset::route('/create'),
            'edit' => Pages\EditDataset::route('/{record}/edit'),
        ];
    }
}
```

### 3. Document Resource

**app/Filament/Resources/Documents/DocumentResource.php**:
```php
<?php

namespace App\Filament\Resources\Documents;

use App\Models\Document;
use App\Models\Dataset;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\KeyValue;
use Filament\Schemas\Schema;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Actions\EditAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\BulkActionGroup;

class DocumentResource extends Resource
{
    protected static ?string $model = Document::class;
    
    protected static ?string $navigationIcon = 'heroicon-o-document-text';
    
    protected static ?string $navigationGroup = 'MCP Management';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('title')
                    ->required()
                    ->live(onBlur: true)
                    ->afterStateUpdated(function (string $operation, $state, callable $set) {
                        if ($operation !== 'create') return;
                        $set('slug', \Illuminate\Support\Str::slug($state));
                    }),

                TextInput::make('slug')
                    ->required()
                    ->unique(Document::class, 'slug', ignoreRecord: true),

                Select::make('type')
                    ->options([
                        'markdown' => 'Markdown',
                        'html' => 'HTML',
                        'text' => 'Text',
                        'json' => 'JSON',
                    ])
                    ->required()
                    ->default('markdown'),

                Select::make('status')
                    ->options([
                        'published' => 'Published',
                        'draft' => 'Draft',
                        'archived' => 'Archived',
                    ])
                    ->required()
                    ->default('draft'),

                Select::make('dataset_id')
                    ->label('Dataset')
                    ->options(Dataset::pluck('name', 'id'))
                    ->nullable()
                    ->searchable(),

                TagsInput::make('tags')
                    ->columnSpanFull(),

                Textarea::make('content')
                    ->required()
                    ->rows(10)
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
                TextColumn::make('title')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('type')
                    ->badge(),

                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'published' => 'success',
                        'draft' => 'warning',
                        'archived' => 'gray',
                        default => 'gray',
                    }),

                TextColumn::make('dataset.name')
                    ->label('Dataset')
                    ->sortable(),

                TextColumn::make('user.name')
                    ->label('Author')
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

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListDocuments::route('/'),
            'create' => Pages\CreateDocument::route('/create'),
            'edit' => Pages\EditDocument::route('/{record}/edit'),
        ];
    }
}
```

### 4. ApiKey Resource

**app/Filament/Resources/ApiKeys/ApiKeyResource.php**:
```php
<?php

namespace App\Filament\Resources\ApiKeys;

use App\Models\ApiKey;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Placeholder;
use Filament\Schemas\Schema;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;
use Filament\Actions\EditAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\BulkActionGroup;

class ApiKeyResource extends Resource
{
    protected static ?string $model = ApiKey::class;
    
    protected static ?string $navigationIcon = 'heroicon-o-key';
    
    protected static ?string $navigationGroup = 'MCP Management';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required(),

                TextInput::make('key')
                    ->label('API Key')
                    ->default(fn () => 'mcp_' . \Illuminate\Support\Str::random(40))
                    ->required(),

                Toggle::make('is_active')
                    ->label('Active')
                    ->default(true),

                DateTimePicker::make('expires_at')
                    ->label('Expires At')
                    ->nullable(),

                TagsInput::make('permissions')
                    ->label('Permissions')
                    ->placeholder('Add permission...')
                    ->default(['read', 'write'])
                    ->columnSpanFull(),

                KeyValue::make('rate_limits')
                    ->label('Rate Limits')
                    ->keyLabel('Limit Type')
                    ->valueLabel('Value')
                    ->default([
                        'per_minute' => '60',
                        'per_hour' => '1000',
                    ])
                    ->columnSpanFull(),

                Placeholder::make('usage_count')
                    ->label('Usage Count')
                    ->content(fn ($record) => $record?->usage_count ?? 0)
                    ->hiddenOn('create'),

                Placeholder::make('last_used_at')
                    ->label('Last Used')
                    ->content(fn ($record) => $record?->last_used_at?->diffForHumans() ?? 'Never')
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

                TextColumn::make('key')
                    ->label('API Key')
                    ->limit(20)
                    ->tooltip(fn ($record) => $record->key),

                ToggleColumn::make('is_active')
                    ->label('Active'),

                TextColumn::make('usage_count')
                    ->sortable(),

                TextColumn::make('last_used_at')
                    ->label('Last Used')
                    ->dateTime()
                    ->sortable()
                    ->placeholder('Never'),

                TextColumn::make('expires_at')
                    ->label('Expires')
                    ->dateTime()
                    ->sortable()
                    ->placeholder('Never'),

                TextColumn::make('user.name')
                    ->label('Owner')
                    ->sortable(),
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

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListApiKeys::route('/'),
            'create' => Pages\CreateApiKey::route('/create'),
            'edit' => Pages\EditApiKey::route('/{record}/edit'),
        ];
    }
}
```

### 5. McpConnection Resource

**app/Filament/Resources/McpConnections/McpConnectionResource.php**:
```php
<?php

namespace App\Filament\Resources\McpConnections;

use App\Models\McpConnection;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Actions\EditAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\BulkActionGroup;

class McpConnectionResource extends Resource
{
    protected static ?string $model = McpConnection::class;
    
    protected static ?string $navigationIcon = 'heroicon-o-signal';
    
    protected static ?string $navigationGroup = 'MCP Management';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required(),

                TextInput::make('endpoint_url')
                    ->label('Endpoint URL')
                    ->url()
                    ->required(),

                Select::make('transport_type')
                    ->label('Transport Type')
                    ->options([
                        'stdio' => 'Standard I/O',
                        'http' => 'HTTP',
                        'websocket' => 'WebSocket',
                    ])
                    ->required()
                    ->default('stdio'),

                Select::make('status')
                    ->options([
                        'active' => 'Active',
                        'inactive' => 'Inactive',
                        'error' => 'Error',
                    ])
                    ->required()
                    ->default('inactive'),

                KeyValue::make('auth_config')
                    ->label('Authentication Config')
                    ->keyLabel('Key')
                    ->valueLabel('Value')
                    ->default([
                        'type' => 'bearer',
                        'token' => '',
                    ])
                    ->columnSpanFull(),

                KeyValue::make('capabilities')
                    ->label('Capabilities')
                    ->keyLabel('Capability')
                    ->valueLabel('Enabled')
                    ->default([
                        'tools' => 'true',
                        'prompts' => 'true',
                        'resources' => 'true',
                    ])
                    ->columnSpanFull(),

                Placeholder::make('last_connected_at')
                    ->label('Last Connected')
                    ->content(fn ($record) => $record?->last_connected_at?->diffForHumans() ?? 'Never')
                    ->hiddenOn('create'),

                Placeholder::make('last_error')
                    ->label('Last Error')
                    ->content(fn ($record) => $record?->last_error ?? 'None')
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

                TextColumn::make('endpoint_url')
                    ->label('Endpoint')
                    ->limit(50)
                    ->tooltip(fn ($record) => $record->endpoint_url),

                TextColumn::make('transport_type')
                    ->label('Transport')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'stdio' => 'gray',
                        'http' => 'blue',
                        'websocket' => 'green',
                        default => 'gray',
                    }),

                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'active' => 'success',
                        'inactive' => 'warning',
                        'error' => 'danger',
                        default => 'gray',
                    }),

                TextColumn::make('last_connected_at')
                    ->label('Last Connected')
                    ->dateTime()
                    ->sortable()
                    ->placeholder('Never'),

                TextColumn::make('user.name')
                    ->label('Owner')
                    ->sortable(),
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

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListMcpConnections::route('/'),
            'create' => Pages\CreateMcpConnection::route('/create'),
            'edit' => Pages\EditMcpConnection::route('/{record}/edit'),
        ];
    }
}
```

### 6. Create Page Classes

For each resource, create the standard page classes (they'll be auto-generated):

```bash
# The pages should be auto-generated when you created the resources
# But if needed, create them manually:
php artisan make:filament-page Datasets/CreateDataset --resource=DatasetResource --type=create
# etc...
```

### 7. Test the Resources

```bash
# Build assets and test
npm run build
php artisan serve

# Visit http://localhost:8000/admin
# Navigate to each resource and test CRUD operations
```

## Expected Outcomes

After completing Stage 04:

✅ All four MCP resources accessible in Filament admin panel  
✅ Proper Filament v4 Schema patterns used throughout  
✅ Form components correctly namespaced (`Filament\Forms\Components\*`)  
✅ Grid layouts using `->columns(2)` instead of Grid wrappers  
✅ Full CRUD operations working for all MCP entities  
✅ Navigation grouped under "MCP Management"  

## Critical Pattern Validation

**Verify these patterns in ALL resources:**

1. ✅ `use Filament\Schemas\Schema;` (NOT Form)
2. ✅ `public static function form(Schema $schema): Schema`
3. ✅ `->components([...])` (NOT ->schema([...]))
4. ✅ `->columns(2)` for grid layout
5. ✅ `->columnSpanFull()` for wide components
6. ✅ No Grid or Section component imports

## Next Stage
Proceed to **Stage 05: MCP Server Implementation** to create the bidirectional MCP protocol server for Claude Code communication.

## Files Created/Modified
- `app/Filament/Resources/Datasets/DatasetResource.php`
- `app/Filament/Resources/Documents/DocumentResource.php`
- `app/Filament/Resources/ApiKeys/ApiKeyResource.php`
- `app/Filament/Resources/McpConnections/McpConnectionResource.php`
- Plus corresponding Page classes for each resource

## Git Checkpoint
```bash
git add .
git commit -m "Stage 04: Filament v4 resources with correct Schema patterns

- Create Dataset, Document, ApiKey, McpConnection resources
- Use proper Filament v4 Schema patterns for all forms
- Implement correct component namespaces and grid layouts
- Add navigation grouping and proper CRUD operations
- Establish critical Filament v4 compliance throughout"
```