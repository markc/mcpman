<?php

namespace App\Filament\Pages;

use App\Models\McpConnection;
use App\Models\Tool;
use App\Services\ToolRegistryManager;
use Filament\Actions\Action;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Log;
use Livewire\Attributes\On;

class ToolManagement extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $title = 'Tool Management';

    protected static ?string $navigationLabel = 'Tool Management';

    public function getView(): string
    {
        return 'filament.pages.tool-management';
    }

    public ?array $data = [];

    public array $toolStatistics = [];

    public array $discoveredTools = [];

    public array $availableConnections = [];

    public array $toolCompositions = [];

    public function mount(): void
    {
        try {
            $this->form->fill();
            $this->loadToolStatistics();
            $this->loadAvailableConnections();
            $this->loadToolCompositions();
        } catch (\Exception $e) {
            Log::error('ToolManagement mount error', ['error' => $e->getMessage()]);
            // Set safe defaults
            $this->toolStatistics = [];
            $this->discoveredTools = [];
            $this->availableConnections = [];
            $this->toolCompositions = [];
        }
    }

    protected function getFormSchema(): array
    {
        return [
            Select::make('selectedConnection')
                ->label('MCP Connection')
                ->options(McpConnection::where('status', 'active')->pluck('name', 'id'))
                ->placeholder('Select connection to discover tools')
                ->live()
                ->afterStateUpdated(fn () => $this->discoverTools()),

            TextInput::make('searchTerm')
                ->label('Search Tools')
                ->placeholder('Search by name, description, or category')
                ->live(debounce: 500)
                ->afterStateUpdated(fn () => $this->searchTools()),

            Select::make('categoryFilter')
                ->label('Category Filter')
                ->options([
                    'all' => 'All Categories',
                    'general' => 'General',
                    'filesystem' => 'File System',
                    'development' => 'Development',
                    'web' => 'Web & Network',
                    'search' => 'Search & Analysis',
                    'data' => 'Data & Database',
                    'ai' => 'AI & Processing',
                ])
                ->default('all')
                ->live()
                ->afterStateUpdated(fn () => $this->filterTools()),
        ];
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components($this->getFormSchema())
            ->statePath('data')
            ->columns(3);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('discoverAllTools')
                ->label('Discover All Tools')
                ->icon(Heroicon::OutlinedMagnifyingGlass)
                ->action('discoverAllTools')
                ->requiresConfirmation()
                ->modalDescription('This will scan all active MCP connections and discover available tools. This may take a few moments.'),

            Action::make('refreshStats')
                ->label('Refresh Statistics')
                ->icon(Heroicon::OutlinedArrowPath)
                ->action('refreshStatistics'),

            Action::make('clearCache')
                ->label('Clear Cache')
                ->icon(Heroicon::OutlinedTrash)
                ->action('clearToolCache')
                ->requiresConfirmation()
                ->color('danger'),

            Action::make('createComposition')
                ->label('Create Tool Composition')
                ->icon(Heroicon::OutlinedSquaresPlus)
                ->form([
                    TextInput::make('name')
                        ->label('Composition Name')
                        ->required()
                        ->maxLength(255),

                    Textarea::make('description')
                        ->label('Description')
                        ->rows(3),

                    CheckboxList::make('toolIds')
                        ->label('Select Tools')
                        ->options(Tool::active()->pluck('name', 'id'))
                        ->required()
                        ->columns(2),
                ])
                ->action('createToolComposition')
                ->modalWidth(Width::FourExtraLarge),
        ];
    }

    public function discoverAllTools(): void
    {
        try {
            $toolRegistry = app(ToolRegistryManager::class);
            $user = auth()->user() ?? \App\Models\User::first();

            if (! $user) {
                $user = \App\Models\User::create([
                    'name' => 'Default User',
                    'email' => 'default@example.com',
                    'password' => bcrypt('password'),
                ]);
            }

            $discoveredTools = $toolRegistry->discoverAllTools($user);

            $this->discoveredTools = $discoveredTools->toArray();
            $this->loadToolStatistics();

            Notification::make()
                ->title('Tool Discovery Complete')
                ->body("Discovered {$discoveredTools->count()} tools")
                ->success()
                ->send();

        } catch (\Exception $e) {
            Log::error('Tool discovery failed', ['error' => $e->getMessage()]);

            Notification::make()
                ->title('Tool Discovery Failed')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    public function discoverTools(): void
    {
        $connectionId = $this->data['selectedConnection'] ?? null;

        if (! $connectionId) {
            $this->discoveredTools = [];

            return;
        }

        try {
            $connection = McpConnection::find($connectionId);

            if (! $connection) {
                $this->discoveredTools = [];

                return;
            }

            $toolRegistry = app(ToolRegistryManager::class);
            $user = auth()->user() ?? \App\Models\User::first();

            if (! $user) {
                $user = \App\Models\User::create([
                    'name' => 'Default User',
                    'email' => 'default@example.com',
                    'password' => bcrypt('password'),
                ]);
            }

            $discoveredTools = $toolRegistry->discoverToolsFromConnection($connection, $user);

            $this->discoveredTools = $discoveredTools->toArray();

            Notification::make()
                ->title('Tools Discovered')
                ->body("Found {$discoveredTools->count()} tools")
                ->success()
                ->send();

        } catch (\Exception $e) {
            Log::error('Tool discovery failed', ['error' => $e->getMessage()]);
            $this->discoveredTools = [];

            Notification::make()
                ->title('Discovery Failed')
                ->body($e->getMessage())
                ->warning()
                ->send();
        }
    }

    public function searchTools(): void
    {
        $searchTerm = $this->data['searchTerm'] ?? '';

        if (empty($searchTerm)) {
            $this->discoveredTools = Tool::active()->get()->toArray();

            return;
        }

        try {
            $toolRegistry = app(ToolRegistryManager::class);
            $tools = $toolRegistry->searchTools(['search' => $searchTerm]);

            $this->discoveredTools = $tools->toArray();

        } catch (\Exception $e) {
            Log::error('Tool search failed', ['error' => $e->getMessage()]);
            $this->discoveredTools = [];
        }
    }

    public function filterTools(): void
    {
        $category = $this->data['categoryFilter'] ?? 'all';

        try {
            $toolRegistry = app(ToolRegistryManager::class);
            $criteria = [];

            if ($category !== 'all') {
                $criteria['category'] = $category;
            }

            $tools = $toolRegistry->searchTools($criteria);
            $this->discoveredTools = $tools->toArray();

        } catch (\Exception $e) {
            Log::error('Tool filtering failed', ['error' => $e->getMessage()]);
            $this->discoveredTools = [];
        }
    }

    public function refreshStatistics(): void
    {
        $this->loadToolStatistics();

        Notification::make()
            ->title('Statistics Refreshed')
            ->success()
            ->send();
    }

    public function clearToolCache(): void
    {
        try {
            $toolRegistry = app(ToolRegistryManager::class);
            $toolRegistry->clearCache();

            Notification::make()
                ->title('Cache Cleared')
                ->body('Tool cache has been cleared successfully')
                ->success()
                ->send();

        } catch (\Exception $e) {
            Log::error('Cache clearing failed', ['error' => $e->getMessage()]);

            Notification::make()
                ->title('Cache Clear Failed')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    public function createToolComposition(array $data): void
    {
        try {
            $toolRegistry = app(ToolRegistryManager::class);
            $composition = $toolRegistry->createToolComposition(
                $data['toolIds'],
                $data['name'],
                $data['description'] ?? null
            );

            $this->loadToolCompositions();

            Notification::make()
                ->title('Tool Composition Created')
                ->body("Created composition: {$data['name']}")
                ->success()
                ->send();

        } catch (\Exception $e) {
            Log::error('Tool composition creation failed', ['error' => $e->getMessage()]);

            Notification::make()
                ->title('Composition Creation Failed')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    public function toggleToolFavorite(int $toolId): void
    {
        try {
            $tool = Tool::find($toolId);

            if ($tool) {
                $tool->update(['is_favorite' => ! $tool->is_favorite]);

                $this->loadToolStatistics();

                Notification::make()
                    ->title($tool->is_favorite ? 'Added to Favorites' : 'Removed from Favorites')
                    ->success()
                    ->send();
            }

        } catch (\Exception $e) {
            Log::error('Toggle favorite failed', ['error' => $e->getMessage()]);

            Notification::make()
                ->title('Update Failed')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    public function toggleToolActive(int $toolId): void
    {
        try {
            $tool = Tool::find($toolId);

            if ($tool) {
                $tool->update(['is_active' => ! $tool->is_active]);

                $this->loadToolStatistics();

                Notification::make()
                    ->title($tool->is_active ? 'Tool Activated' : 'Tool Deactivated')
                    ->success()
                    ->send();
            }

        } catch (\Exception $e) {
            Log::error('Toggle active failed', ['error' => $e->getMessage()]);

            Notification::make()
                ->title('Update Failed')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    #[On('echo:tool-registry,tool.discovered')]
    public function handleToolDiscovered($data): void
    {
        $this->loadToolStatistics();
        $this->discoverTools();
    }

    #[On('echo:tool-registry,tool.updated')]
    public function handleToolUpdated($data): void
    {
        $this->loadToolStatistics();
        $this->discoverTools();
    }

    private function loadToolStatistics(): void
    {
        try {
            $toolRegistry = app(ToolRegistryManager::class);
            $this->toolStatistics = $toolRegistry->getToolStatistics();
        } catch (\Exception $e) {
            Log::error('Failed to load tool statistics', ['error' => $e->getMessage()]);
            $this->toolStatistics = [
                'total_tools' => 0,
                'active_tools' => 0,
                'favorite_tools' => 0,
                'categories' => [],
                'most_used' => [],
                'recently_discovered' => 0,
                'connections_with_tools' => 0,
                'average_usage' => 0,
                'average_success_rate' => 0,
            ];
        }
    }

    private function loadAvailableConnections(): void
    {
        $this->availableConnections = McpConnection::where('status', 'active')
            ->get(['id', 'name', 'transport_type'])
            ->toArray();
    }

    private function loadToolCompositions(): void
    {
        // Load cached tool compositions
        $this->toolCompositions = [];

        // In a real implementation, this would load from cache or database
        // For now, we'll keep it simple
    }
}
