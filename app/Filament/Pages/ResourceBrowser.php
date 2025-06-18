<?php

namespace App\Filament\Pages;

use App\Models\McpConnection;
use App\Models\Resource;
use App\Services\ResourceManager;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Heroicon;
use Filament\Support\Enums\Width;
use Illuminate\Support\Facades\Log;
use Livewire\Attributes\On;

class ResourceBrowser extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $title = 'Resource Browser';

    protected static ?string $navigationLabel = 'Resource Browser';

    public function getView(): string
    {
        return 'filament.pages.resource-browser';
    }

    public ?array $data = [];

    public array $resourceStatistics = [];

    public array $discoveredResources = [];

    public array $availableConnections = [];

    public ?Resource $selectedResource = null;

    public ?array $resourceContent = null;

    public string $currentPath = '';

    public function mount(): void
    {
        try {
            $this->form->fill();
            // Initialize with empty arrays to avoid service calls during debugging
            $this->resourceStatistics = [];
            $this->discoveredResources = [];
            $this->availableConnections = [];
            $this->currentPath = '/';
        } catch (\Exception $e) {
            Log::error('ResourceBrowser mount error', ['error' => $e->getMessage()]);
            $this->resourceStatistics = [];
            $this->discoveredResources = [];
            $this->availableConnections = [];
        }
    }

    protected function getFormSchema(): array
    {
        return [
            Select::make('selectedConnection')
                ->label('MCP Connection')
                ->options(fn () => McpConnection::where('status', 'active')->pluck('name', 'id')->toArray())
                ->placeholder('Select connection to browse resources')
                ->live()
                ->afterStateUpdated(fn () => $this->browseConnection()),

            TextInput::make('searchTerm')
                ->label('Search Resources')
                ->placeholder('Search by name, path, or description')
                ->live(debounce: 500)
                ->afterStateUpdated(fn () => $this->searchResources()),

            Select::make('typeFilter')
                ->label('Resource Type')
                ->options([
                    'all' => 'All Types',
                    'file' => 'Files',
                    'directory' => 'Directories',
                    'image' => 'Images',
                    'video' => 'Videos',
                    'audio' => 'Audio',
                    'api_endpoint' => 'API Endpoints',
                    'database' => 'Databases',
                ])
                ->default('all')
                ->live()
                ->afterStateUpdated(fn () => $this->filterResources()),
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
            Action::make('discoverAllResources')
                ->label('Discover All Resources')
                ->icon(Heroicon::OUTLINE_MAGNIFYING_GLASS)
                ->action('discoverAllResources')
                ->requiresConfirmation()
                ->modalDescription('This will scan all active MCP connections and discover available resources. This may take a few moments.'),

            Action::make('refreshStats')
                ->label('Refresh Statistics')
                ->icon(Heroicon::OUTLINE_ARROW_PATH)
                ->action('refreshStatistics'),

            Action::make('clearCache')
                ->label('Clear Cache')
                ->icon(Heroicon::OUTLINE_TRASH)
                ->action('clearResourceCache')
                ->requiresConfirmation()
                ->color('danger'),

            Action::make('syncResource')
                ->label('Sync Resource')
                ->icon(Heroicon::OUTLINE_ARROW_PATH_ROUNDED_SQUARE)
                ->form([
                    Select::make('targetConnection')
                        ->label('Target Connection')
                        ->options(fn () => McpConnection::where('status', 'active')->pluck('name', 'id')->toArray())
                        ->required(),
                ])
                ->action('syncSelectedResource')
                ->visible(fn () => $this->selectedResource !== null)
                ->modalWidth(Width::Medium),
        ];
    }

    public function discoverAllResources(): void
    {
        try {
            $resourceManager = app(ResourceManager::class);
            $user = $this->getUser();

            $discoveredResources = $resourceManager->discoverAllResources($user);

            $this->discoveredResources = $discoveredResources->toArray();
            $this->loadResourceStatistics();

            Notification::make()
                ->title('Resource Discovery Complete')
                ->body("Discovered {$discoveredResources->count()} resources")
                ->success()
                ->send();

        } catch (\Exception $e) {
            Log::error('Resource discovery failed', ['error' => $e->getMessage()]);

            Notification::make()
                ->title('Resource Discovery Failed')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    public function browseConnection(): void
    {
        $connectionId = $this->data['selectedConnection'] ?? null;

        if (! $connectionId) {
            $this->discoveredResources = [];

            return;
        }

        try {
            $connection = McpConnection::find($connectionId);

            if (! $connection) {
                $this->discoveredResources = [];

                return;
            }

            $resourceManager = app(ResourceManager::class);
            $user = $this->getUser();

            $discoveredResources = $resourceManager->discoverResourcesFromConnection($connection, $user);

            $this->discoveredResources = $discoveredResources->toArray();

            Notification::make()
                ->title('Resources Loaded')
                ->body("Found {$discoveredResources->count()} resources")
                ->success()
                ->send();

        } catch (\Exception $e) {
            Log::error('Resource browsing failed', ['error' => $e->getMessage()]);
            $this->discoveredResources = [];

            Notification::make()
                ->title('Browsing Failed')
                ->body($e->getMessage())
                ->warning()
                ->send();
        }
    }

    public function searchResources(): void
    {
        $searchTerm = $this->data['searchTerm'] ?? '';

        if (empty($searchTerm)) {
            $this->discoveredResources = Resource::rootResources()->get()->toArray();

            return;
        }

        try {
            $resourceManager = app(ResourceManager::class);
            $resources = $resourceManager->searchResources(['search' => $searchTerm]);

            $this->discoveredResources = $resources->toArray();

        } catch (\Exception $e) {
            Log::error('Resource search failed', ['error' => $e->getMessage()]);
            $this->discoveredResources = [];
        }
    }

    public function filterResources(): void
    {
        $type = $this->data['typeFilter'] ?? 'all';

        try {
            $resourceManager = app(ResourceManager::class);
            $criteria = [];

            if ($type !== 'all') {
                $criteria['type'] = $type;
            }

            $resources = $resourceManager->searchResources($criteria);
            $this->discoveredResources = $resources->toArray();

        } catch (\Exception $e) {
            Log::error('Resource filtering failed', ['error' => $e->getMessage()]);
            $this->discoveredResources = [];
        }
    }

    public function selectResource(int $resourceId): void
    {
        try {
            $this->selectedResource = Resource::find($resourceId);

            if ($this->selectedResource) {
                $this->loadResourceContent();
            }

        } catch (\Exception $e) {
            Log::error('Resource selection failed', ['error' => $e->getMessage()]);

            Notification::make()
                ->title('Selection Failed')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    public function loadResourceContent(): void
    {
        if (! $this->selectedResource) {
            return;
        }

        try {
            $resourceManager = app(ResourceManager::class);
            $this->resourceContent = $resourceManager->accessResource($this->selectedResource);

            Notification::make()
                ->title('Resource Loaded')
                ->body($this->resourceContent['from_cache'] ? 'Loaded from cache' : 'Fetched from source')
                ->success()
                ->send();

        } catch (\Exception $e) {
            Log::error('Resource content loading failed', ['error' => $e->getMessage()]);

            Notification::make()
                ->title('Loading Failed')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    public function syncSelectedResource(array $data): void
    {
        if (! $this->selectedResource) {
            return;
        }

        try {
            $targetConnection = McpConnection::find($data['targetConnection']);

            if (! $targetConnection) {
                throw new \Exception('Target connection not found');
            }

            $resourceManager = app(ResourceManager::class);
            $user = $this->getUser();

            $syncedResource = $resourceManager->synchronizeResources(
                $this->selectedResource,
                $targetConnection,
                $user
            );

            Notification::make()
                ->title('Resource Synced')
                ->body("Resource synced to {$targetConnection->name}")
                ->success()
                ->send();

        } catch (\Exception $e) {
            Log::error('Resource sync failed', ['error' => $e->getMessage()]);

            Notification::make()
                ->title('Sync Failed')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    public function refreshStatistics(): void
    {
        $this->loadResourceStatistics();

        Notification::make()
            ->title('Statistics Refreshed')
            ->success()
            ->send();
    }

    public function clearResourceCache(): void
    {
        try {
            $resourceManager = app(ResourceManager::class);
            $resourceManager->clearAllCache();

            Notification::make()
                ->title('Cache Cleared')
                ->body('All resource cache has been cleared successfully')
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

    #[On('echo:resource-manager,resource.discovered')]
    public function handleResourceDiscovered($data): void
    {
        $this->loadResourceStatistics();
        $this->browseConnection();
    }

    #[On('echo:resource-manager,resource.accessed')]
    public function handleResourceAccessed($data): void
    {
        $this->loadResourceStatistics();
    }

    private function loadResourceStatistics(): void
    {
        try {
            $resourceManager = app(ResourceManager::class);
            $this->resourceStatistics = $resourceManager->getResourceStatistics();
        } catch (\Exception $e) {
            Log::error('Failed to load resource statistics', ['error' => $e->getMessage()]);
            $this->resourceStatistics = [
                'total_resources' => 0,
                'cached_resources' => 0,
                'public_resources' => 0,
                'types' => [],
                'connections_with_resources' => 0,
                'recently_accessed' => 0,
                'total_cached_size' => 0,
                'expired_cache_count' => 0,
            ];
        }
    }

    private function loadAvailableConnections(): void
    {
        $this->availableConnections = McpConnection::where('status', 'active')
            ->get(['id', 'name', 'transport_type'])
            ->toArray();
    }

    private function getUser()
    {
        $user = auth()->user() ?? \App\Models\User::first();

        if (! $user) {
            $user = \App\Models\User::create([
                'name' => 'Default User',
                'email' => 'default@example.com',
                'password' => bcrypt('password'),
            ]);
        }

        return $user;
    }
}
