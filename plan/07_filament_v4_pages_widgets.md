# Stage 07: Filament v4 Custom Pages & Widgets (CRITICAL PATTERNS)

## Overview
Create custom Filament v4 pages and widgets for MCP dashboard and conversation interface using proper widget patterns, sorting, and form handling. This stage establishes critical Filament v4 compliance for custom UI components.

## ⚠️ CRITICAL FILAMENT V4 WIDGET PATTERNS

**ESSENTIAL**: Use these exact patterns for all Filament v4 widgets and pages:

### Widget Patterns
```php
use Filament\Widgets\Widget as BaseWidget; // Correct base class
use Filament\Widgets\StatsOverviewWidget; // For stats
use Filament\Widgets\TableWidget; // For tables

class MyWidget extends BaseWidget
{
    protected static ?int $sort = 10; // Widget ordering
    protected int | string | array $columnSpan = 'full'; // Grid span
}
```

### Page Form Patterns
```php
use Filament\Forms\Form; // NOT Schema for pages
public function conversationForm(Form $form): Form
{
    return $form->schema([...])->columns(2);
}
```

## Step-by-Step Implementation

### 1. Create MCP Dashboard Page

**app/Filament/Pages/McpDashboard.php**:
```php
<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\McpStatsWidget;
use App\Filament\Widgets\McpConnectionsWidget;
use Filament\Pages\Page;

class McpDashboard extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-home';
    
    protected static string $view = 'filament.pages.mcp-dashboard';
    
    protected static ?string $title = 'Dashboard';
    
    protected static string $routePath = '/';
    
    protected static ?int $navigationSort = 1;

    public function getWidgets(): array
    {
        return [
            McpStatsWidget::class,
            McpConnectionsWidget::class,
        ];
    }

    public function getColumns(): int | string | array
    {
        return 2;
    }

    public function getWidgetsColumns(): int | string | array
    {
        return [
            'md' => 2,
            'xl' => 3,
        ];
    }
}
```

### 2. Create MCP Stats Widget

**app/Filament/Widgets/McpStatsWidget.php**:
```php
<?php

namespace App\Filament\Widgets;

use App\Models\Dataset;
use App\Models\Document;
use App\Models\ApiKey;
use App\Models\McpConnection;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class McpStatsWidget extends BaseWidget
{
    protected static ?int $sort = -1; // Show at top
    
    protected function getStats(): array
    {
        return [
            Stat::make('Total Connections', McpConnection::count())
                ->description(McpConnection::where('status', 'active')->count() . ' active')
                ->descriptionIcon('heroicon-m-arrow-trending-up')
                ->color('blue'),
                
            Stat::make('Active Datasets', Dataset::where('status', 'active')->count())
                ->description('Ready for MCP access')
                ->descriptionIcon('heroicon-m-folder')
                ->color('success'),
                
            Stat::make('Published Documents', Document::where('status', 'published')->count())
                ->description('Available via MCP')
                ->descriptionIcon('heroicon-m-document-text')
                ->color('info'),
                
            Stat::make('API Keys', ApiKey::where('is_active', true)->count())
                ->description('Active authentication keys')
                ->descriptionIcon('heroicon-m-key')
                ->color('warning'),
        ];
    }
}
```

### 3. Create MCP Connections Widget

**app/Filament/Widgets/McpConnectionsWidget.php**:
```php
<?php

namespace App\Filament\Widgets;

use App\Models\McpConnection;
use App\Services\McpClient;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Support\Facades\Log;

class McpConnectionsWidget extends BaseWidget
{
    protected int | string | array $columnSpan = 'full';
    
    protected static ?string $heading = 'MCP Connection Status';
    
    protected static ?int $sort = 10; // Show after stats

    public function table(Table $table): Table
    {
        return $table
            ->query(
                McpConnection::query()
                    ->orderBy('status', 'asc')
                    ->orderBy('last_connected_at', 'desc')
            )
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
            ])
            ->actions([
                Action::make('test')
                    ->label('Test')
                    ->icon('heroicon-o-signal')
                    ->action(function (McpConnection $record) {
                        $this->testConnection($record);
                    }),
            ]);
    }

    public function testConnection(McpConnection $connection): void
    {
        try {
            $client = new McpClient($connection);
            $isConnected = $client->testConnection();

            if ($isConnected) {
                $connection->markAsConnected();
                $message = "Connection '{$connection->name}' is working correctly.";
                $type = 'success';
            } else {
                $connection->markAsError('Connection test failed');
                $message = "Connection '{$connection->name}' test failed.";
                $type = 'warning';
            }
        } catch (\Exception $e) {
            $connection->markAsError($e->getMessage());
            $message = "Connection '{$connection->name}' error: " . $e->getMessage();
            $type = 'danger';
            Log::error('Connection test failed', ['connection' => $connection->name, 'error' => $e->getMessage()]);
        }

        Notification::make()
            ->title('Connection Test Result')
            ->body($message)
            ->{$type}()
            ->send();
    }
}
```

### 4. Create MCP Conversation Page

**app/Filament/Pages/McpConversation.php**:
```php
<?php

namespace App\Filament\Pages;

use App\Models\McpConnection;
use App\Services\McpClient;
use Filament\Actions\Action;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Log;

class McpConversation extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-chat-bubble-left-ellipsis';
    
    protected static string $view = 'filament.pages.mcp-conversation';
    
    protected static ?string $title = 'MCP Conversation';
    
    protected static ?string $navigationLabel = 'Chat with Claude';
    
    protected static ?int $navigationSort = 2;

    public ?array $data = [];
    
    public array $conversation = [];
    
    public array $availableTools = [];

    public function mount(): void
    {
        $this->conversation = [];
        $this->loadAvailableConnections();
    }

    public function conversationForm(Form $form): Form
    {
        return $form
            ->schema($this->getFormSchema())
            ->statePath('data')
            ->columns(2);
    }

    protected function getFormSchema(): array
    {
        return [
            Select::make('selectedConnection')
                ->label('MCP Connection')
                ->options(McpConnection::where('status', 'active')->pluck('name', 'id'))
                ->required()
                ->live()
                ->afterStateUpdated(fn () => $this->loadTools()),

            Select::make('selectedTool')
                ->label('Use Tool (Optional)')
                ->options(fn () => collect($this->availableTools)->pluck('description', 'name'))
                ->nullable()
                ->live(),

            Textarea::make('message')
                ->label('Message')
                ->rows(3)
                ->placeholder('Type your message to Claude Code...')
                ->required()
                ->columnSpanFull(),

            KeyValue::make('toolArguments')
                ->label('Tool Arguments')
                ->visible(fn (callable $get) => ! empty($get('selectedTool')))
                ->keyLabel('Parameter')
                ->valueLabel('Value')
                ->columnSpanFull(),
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('sendMessage')
                ->label('Send Message')
                ->icon('heroicon-o-paper-airplane')
                ->action('sendMessage')
                ->disabled(fn () => empty($this->data['selectedConnection'] ?? null)),

            Action::make('callTool')
                ->label('Call Tool')
                ->icon('heroicon-o-wrench')
                ->action('callTool')
                ->disabled(fn () => empty($this->data['selectedConnection'] ?? null) || empty($this->data['selectedTool'] ?? null))
                ->visible(fn () => ! empty($this->data['selectedTool'] ?? null)),

            Action::make('clearConversation')
                ->label('Clear')
                ->icon('heroicon-o-trash')
                ->action('clearConversation')
                ->color('danger')
                ->requiresConfirmation(),

            Action::make('refreshTools')
                ->label('Refresh Tools')
                ->icon('heroicon-o-arrow-path')
                ->action('loadTools'),
        ];
    }

    public function sendMessage(): void
    {
        $formData = $this->form->getState();
        
        if (empty($formData['message']) || empty($formData['selectedConnection'])) {
            return;
        }

        try {
            $connection = McpConnection::find($formData['selectedConnection']);

            if (! $connection) {
                Notification::make()
                    ->title('Connection not found')
                    ->danger()
                    ->send();

                return;
            }

            $client = new McpClient($connection);

            // Add user message to conversation
            $this->conversation[] = [
                'role' => 'user',
                'content' => $formData['message'],
                'timestamp' => now()->toISOString(),
            ];

            // Send conversation to Claude Code
            $response = $client->executeConversation([
                [
                    'role' => 'user',
                    'content' => $formData['message'],
                ],
            ]);

            if (isset($response['error'])) {
                $this->conversation[] = [
                    'role' => 'error',
                    'content' => 'Error: '.$response['error'],
                    'timestamp' => now()->toISOString(),
                ];
            } else {
                $this->conversation[] = [
                    'role' => 'assistant',
                    'content' => $response['content'] ?? 'No response content',
                    'timestamp' => now()->toISOString(),
                ];
            }

            $this->form->fill(['message' => '']);

            Notification::make()
                ->title('Message sent successfully')
                ->success()
                ->send();

        } catch (\Exception $e) {
            Log::error('Conversation error', ['error' => $e->getMessage()]);

            $this->conversation[] = [
                'role' => 'error',
                'content' => 'Error: '.$e->getMessage(),
                'timestamp' => now()->toISOString(),
            ];

            Notification::make()
                ->title('Error sending message')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    public function callTool(): void
    {
        $formData = $this->form->getState();
        
        if (empty($formData['selectedTool']) || empty($formData['selectedConnection'])) {
            return;
        }

        try {
            $connection = McpConnection::find($formData['selectedConnection']);

            if (! $connection) {
                Notification::make()
                    ->title('Connection not found')
                    ->danger()
                    ->send();

                return;
            }

            $client = new McpClient($connection);

            // Add tool call to conversation
            $this->conversation[] = [
                'role' => 'tool_call',
                'content' => "Calling tool: {$formData['selectedTool']}",
                'tool' => $formData['selectedTool'],
                'arguments' => $formData['toolArguments'] ?? [],
                'timestamp' => now()->toISOString(),
            ];

            $response = $client->callTool($formData['selectedTool'], $formData['toolArguments'] ?? []);

            if (isset($response['error'])) {
                $this->conversation[] = [
                    'role' => 'error',
                    'content' => 'Tool Error: '.$response['error'],
                    'timestamp' => now()->toISOString(),
                ];
            } else {
                $this->conversation[] = [
                    'role' => 'tool_result',
                    'content' => $response['content'] ?? json_encode($response, JSON_PRETTY_PRINT),
                    'timestamp' => now()->toISOString(),
                ];
            }

            $this->form->fill(['selectedTool' => null, 'toolArguments' => []]);

            Notification::make()
                ->title('Tool executed successfully')
                ->success()
                ->send();

        } catch (\Exception $e) {
            Log::error('Tool call error', ['error' => $e->getMessage()]);

            $this->conversation[] = [
                'role' => 'error',
                'content' => 'Tool Error: '.$e->getMessage(),
                'timestamp' => now()->toISOString(),
            ];

            Notification::make()
                ->title('Error calling tool')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    public function clearConversation(): void
    {
        $this->conversation = [];

        Notification::make()
            ->title('Conversation cleared')
            ->success()
            ->send();
    }

    public function loadTools(): void
    {
        $formData = $this->form->getState();
        
        if (empty($formData['selectedConnection'])) {
            $this->availableTools = [];

            return;
        }

        try {
            $connection = McpConnection::find($formData['selectedConnection']);

            if (! $connection) {
                return;
            }

            $client = new McpClient($connection);
            $this->availableTools = $client->listTools();

        } catch (\Exception $e) {
            Log::error('Failed to load tools', ['error' => $e->getMessage()]);
            $this->availableTools = [];
        }
    }

    private function loadAvailableConnections(): void
    {
        // This method could be expanded to test connections and show their status
    }
}
```

### 5. Create Dashboard View

**resources/views/filament/pages/mcp-dashboard.blade.php**:
```php
<x-filament-panels::page>
    @if($this->hasWidgets())
        <x-filament-widgets::widgets
            :widgets="$this->getWidgets()"
            :columns="$this->getWidgetsColumns()"
        />
    @endif
</x-filament-panels::page>
```

### 6. Create Conversation View

**resources/views/filament/pages/mcp-conversation.blade.php**:
```php
<x-filament-panels::page>
    <div class="space-y-6">
        <!-- Conversation Display -->
        <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <div class="fi-section-header flex flex-col gap-3 px-6 py-4">
                <div class="flex items-center gap-3">
                    <h3 class="fi-section-header-heading text-base font-semibold leading-6 text-gray-950 dark:text-white">
                        Conversation
                    </h3>
                </div>
            </div>
            
            <div class="fi-section-content px-6 py-4">
                <div class="max-h-96 overflow-y-auto space-y-4">
                    @if(empty($this->conversation))
                        <div class="text-center text-gray-500 dark:text-gray-400 py-8">
                            <x-filament::icon
                                icon="heroicon-o-chat-bubble-left-ellipsis"
                                class="mx-auto h-12 w-12 text-gray-400"
                            />
                            <p class="mt-2">No messages yet. Start a conversation with Claude Code!</p>
                        </div>
                    @else
                        @foreach($this->conversation as $message)
                            <div class="flex {{ $message['role'] === 'user' ? 'justify-end' : 'justify-start' }}">
                                <div class="max-w-xs lg:max-w-md px-4 py-2 rounded-lg
                                    @if($message['role'] === 'user')
                                        bg-primary-500 text-white
                                    @elseif($message['role'] === 'assistant')
                                        bg-gray-100 dark:bg-gray-700 text-gray-900 dark:text-gray-100
                                    @elseif($message['role'] === 'tool_call')
                                        bg-warning-100 dark:bg-warning-900 text-warning-900 dark:text-warning-100 border border-warning-300
                                    @elseif($message['role'] === 'tool_result')
                                        bg-success-100 dark:bg-success-900 text-success-900 dark:text-success-100 border border-success-300
                                    @else
                                        bg-danger-100 dark:bg-danger-900 text-danger-900 dark:text-danger-100 border border-danger-300
                                    @endif
                                ">
                                    <div class="text-sm">
                                        @if($message['role'] === 'tool_call')
                                            <div class="font-medium">🔧 Tool Call: {{ $message['tool'] ?? 'Unknown' }}</div>
                                            @if(!empty($message['arguments']))
                                                <div class="mt-1 text-xs opacity-75">
                                                    {{ json_encode($message['arguments']) }}
                                                </div>
                                            @endif
                                        @elseif($message['role'] === 'tool_result')
                                            <div class="font-medium">✅ Tool Result</div>
                                        @elseif($message['role'] === 'error')
                                            <div class="font-medium">❌ Error</div>
                                        @endif
                                        
                                        <div class="{{ $message['role'] === 'tool_call' ? 'mt-1' : '' }}">
                                            @if(is_array($message['content']))
                                                <pre class="whitespace-pre-wrap text-xs">{{ json_encode($message['content'], JSON_PRETTY_PRINT) }}</pre>
                                            @else
                                                {{ $message['content'] }}
                                            @endif
                                        </div>
                                    </div>
                                    
                                    <div class="text-xs opacity-75 mt-1">
                                        {{ \Carbon\Carbon::parse($message['timestamp'])->format('H:i:s') }}
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    @endif
                </div>
            </div>
        </div>
        
        <!-- Conversation Form -->
        <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <div class="fi-section-content px-6 py-4">
                {{ $this->form }}
            </div>
        </div>
        
        <!-- Available Tools -->
        @if(!empty($this->availableTools))
            <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                <div class="fi-section-header flex flex-col gap-3 px-6 py-4">
                    <div class="flex items-center gap-3">
                        <h3 class="fi-section-header-heading text-base font-semibold leading-6 text-gray-950 dark:text-white">
                            Available Tools
                        </h3>
                    </div>
                </div>
                
                <div class="fi-section-content px-6 py-4">
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                        @foreach($this->availableTools as $tool)
                            <div class="rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 p-4">
                                <h4 class="font-medium text-gray-900 dark:text-gray-100">
                                    {{ $tool['name'] }}
                                </h4>
                                <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">
                                    {{ $tool['description'] ?? 'No description' }}
                                </p>
                                @if(!empty($tool['inputSchema']['properties']))
                                    <div class="mt-2">
                                        <p class="text-xs text-gray-500 dark:text-gray-400">Parameters:</p>
                                        <ul class="text-xs text-gray-600 dark:text-gray-400 ml-2">
                                            @foreach($tool['inputSchema']['properties'] as $param => $schema)
                                                <li>• {{ $param }} ({{ $schema['type'] ?? 'unknown' }})</li>
                                            @endforeach
                                        </ul>
                                    </div>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        @endif
    </div>
</x-filament-panels::page>
```

### 7. Update Admin Panel Provider

**app/Providers/Filament/AdminPanelProvider.php** - Remove default widgets:
```php
->widgets([
    // Remove default widgets to use custom MCP dashboard
])
```

## Expected Outcomes

After completing Stage 07:

✅ Custom MCP dashboard with stats and connection widgets  
✅ Real-time conversation interface for Claude Code communication  
✅ Proper Filament v4 widget patterns with sorting (`$sort` property)  
✅ Form handling using correct Filament v4 patterns  
✅ Icon components sized correctly (no oversized SVGs)  
✅ Dashboard accessible at `/admin` with widgets ordered properly  
✅ Conversation page with tool parameter handling  

## Critical Pattern Validation

**Verify these patterns in all widgets and pages:**

1. ✅ Widget `$sort` property for ordering (-1 for top, 10+ for bottom)
2. ✅ `protected int | string | array $columnSpan = 'full';` for full-width widgets
3. ✅ `use Filament\Forms\Form;` for page forms (NOT Schema)
4. ✅ `->columns(2)` for grid layouts (NO Grid component)
5. ✅ `->columnSpanFull()` for wide form components
6. ✅ `<x-filament::icon>` with proper sizing classes

## Testing the Interface

```bash
# Build assets and test
npm run build
php artisan serve

# Visit http://localhost:8000/admin
# Test dashboard widgets and conversation interface
# Verify widget ordering and responsive behavior
```

## Next Stage
Proceed to **Stage 08: Advanced Features & UI Polish** to add real-time updates, advanced filtering, and enhanced user experience features.

## Files Created/Modified
- `app/Filament/Pages/McpDashboard.php` - Main dashboard page
- `app/Filament/Pages/McpConversation.php` - Conversation interface
- `app/Filament/Widgets/McpStatsWidget.php` - Stats overview widget
- `app/Filament/Widgets/McpConnectionsWidget.php` - Connection status widget
- `resources/views/filament/pages/mcp-dashboard.blade.php` - Dashboard view
- `resources/views/filament/pages/mcp-conversation.blade.php` - Conversation view

## Git Checkpoint
```bash
git add .
git commit -m "Stage 07: Filament v4 custom pages and widgets

- Create MCP dashboard with stats and connection widgets
- Add real-time conversation interface for Claude Code
- Implement proper Filament v4 widget patterns with sorting
- Use correct form handling patterns for pages
- Add comprehensive UI for MCP management and communication
- Ensure proper icon sizing and responsive layouts"
```