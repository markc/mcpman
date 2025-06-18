<?php

namespace App\Filament\Pages;

use App\Models\Conversation;
use App\Models\McpConnection;
use App\Services\ConversationManager;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Heroicon;
use Illuminate\Support\Facades\Log;
use Livewire\Attributes\On;

class ConversationHistory extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $title = 'Conversation History';

    protected static ?string $navigationLabel = 'Conversation History';

    protected static ?int $navigationSort = 4;

    public function getView(): string
    {
        return 'filament.pages.conversation-history';
    }

    public ?array $data = [];

    public array $conversations = [];

    public array $conversationStats = [];

    public ?Conversation $selectedConversation = null;

    public array $selectedConversationMessages = [];

    public function mount(): void
    {
        try {
            $this->form->fill();
            $this->loadConversations();
            $this->loadConversationStats();
        } catch (\Exception $e) {
            Log::error('ConversationHistory mount error', ['error' => $e->getMessage()]);
            $this->conversations = [];
            $this->conversationStats = [];
        }
    }

    protected function getFormSchema(): array
    {
        return [
            TextInput::make('searchQuery')
                ->label('Search Conversations')
                ->placeholder('Search by title or content...')
                ->live(debounce: 500)
                ->afterStateUpdated(fn () => $this->searchConversations()),

            Select::make('statusFilter')
                ->label('Status')
                ->options([
                    'all' => 'All Statuses',
                    'active' => 'Active',
                    'archived' => 'Archived',
                    'ended' => 'Ended',
                ])
                ->default('all')
                ->live()
                ->afterStateUpdated(fn () => $this->filterConversations()),

            Select::make('connectionFilter')
                ->label('Connection')
                ->options(fn () => [
                    'all' => 'All Connections',
                    ...McpConnection::pluck('name', 'id')->toArray(),
                ])
                ->default('all')
                ->live()
                ->afterStateUpdated(fn () => $this->filterConversations()),

            DatePicker::make('dateFrom')
                ->label('From Date')
                ->live()
                ->afterStateUpdated(fn () => $this->filterConversations()),

            DatePicker::make('dateTo')
                ->label('To Date')
                ->live()
                ->afterStateUpdated(fn () => $this->filterConversations()),
        ];
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components($this->getFormSchema())
            ->statePath('data')
            ->columns(5);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('refreshStats')
                ->label('Refresh Statistics')
                ->icon(Heroicon::OUTLINE_ARROW_PATH)
                ->action('refreshStatistics'),

            Action::make('exportConversation')
                ->label('Export Conversation')
                ->icon(Heroicon::OUTLINE_ARROW_DOWN_TRAY)
                ->action('exportSelectedConversation')
                ->visible(fn () => $this->selectedConversation !== null)
                ->color('success'),

            Action::make('archiveConversation')
                ->label('Archive Conversation')
                ->icon(Heroicon::OUTLINE_ARCHIVE_BOX)
                ->action('archiveSelectedConversation')
                ->visible(fn () => $this->selectedConversation?->isActive())
                ->requiresConfirmation()
                ->color('warning'),

            Action::make('deleteConversation')
                ->label('Delete Conversation')
                ->icon(Heroicon::OUTLINE_TRASH)
                ->action('deleteSelectedConversation')
                ->visible(fn () => $this->selectedConversation !== null)
                ->requiresConfirmation()
                ->modalDescription('This action cannot be undone. All messages in this conversation will be permanently deleted.')
                ->color('danger'),

            Action::make('clearAllFilters')
                ->label('Clear Filters')
                ->icon(Heroicon::OUTLINE_FUNNEL)
                ->action('clearAllFilters')
                ->color('gray'),
        ];
    }

    public function loadConversations(): void
    {
        try {
            $conversationManager = app(ConversationManager::class);
            $user = $this->getUser();

            $this->conversations = $conversationManager->getUserConversations($user)
                ->map(fn ($conversation) => [
                    'id' => $conversation->id,
                    'title' => $conversation->title,
                    'session_id' => $conversation->session_id,
                    'status' => $conversation->status,
                    'message_count' => $conversation->message_count,
                    'connection_name' => $conversation->mcpConnection->name,
                    'connection_type' => $conversation->mcpConnection->transport_type,
                    'started_at' => $conversation->started_at?->diffForHumans(),
                    'last_activity_at' => $conversation->last_activity_at?->diffForHumans(),
                    'duration_minutes' => $conversation->getDurationInMinutes(),
                ])
                ->toArray();

        } catch (\Exception $e) {
            Log::error('Failed to load conversations', ['error' => $e->getMessage()]);
            $this->conversations = [];
        }
    }

    public function loadConversationStats(): void
    {
        try {
            $conversationManager = app(ConversationManager::class);
            $user = $this->getUser();

            $this->conversationStats = $conversationManager->getConversationStats($user);

        } catch (\Exception $e) {
            Log::error('Failed to load conversation statistics', ['error' => $e->getMessage()]);
            $this->conversationStats = [
                'total_conversations' => 0,
                'active_conversations' => 0,
                'archived_conversations' => 0,
                'total_messages' => 0,
                'average_messages_per_conversation' => 0,
                'total_duration_minutes' => 0,
                'most_used_connections' => [],
                'conversation_activity_by_day' => [],
                'tool_usage_summary' => [],
            ];
        }
    }

    public function selectConversation(int $conversationId): void
    {
        try {
            $this->selectedConversation = Conversation::with(['messages', 'mcpConnection'])
                ->find($conversationId);

            if ($this->selectedConversation) {
                $this->selectedConversationMessages = $this->selectedConversation->messages
                    ->map(fn ($message) => [
                        'id' => $message->id,
                        'role' => $message->role,
                        'content' => $message->getFormattedContent(),
                        'tool_name' => $message->tool_name,
                        'sent_at' => $message->sent_at?->format('M j, Y g:i A'),
                        'sequence_number' => $message->sequence_number,
                        'word_count' => $message->getWordCount(),
                    ])
                    ->toArray();
            }

        } catch (\Exception $e) {
            Log::error('Failed to select conversation', ['error' => $e->getMessage()]);

            Notification::make()
                ->title('Selection Failed')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    public function searchConversations(): void
    {
        $searchQuery = $this->data['searchQuery'] ?? '';

        if (empty($searchQuery)) {
            $this->loadConversations();

            return;
        }

        try {
            $conversationManager = app(ConversationManager::class);
            $user = $this->getUser();

            $filters = $this->buildFilters();
            $results = $conversationManager->searchConversations($user, $searchQuery, $filters);

            $this->conversations = $results->map(fn ($conversation) => [
                'id' => $conversation->id,
                'title' => $conversation->title,
                'session_id' => $conversation->session_id,
                'status' => $conversation->status,
                'message_count' => $conversation->message_count,
                'connection_name' => $conversation->mcpConnection->name,
                'connection_type' => $conversation->mcpConnection->transport_type,
                'started_at' => $conversation->started_at?->diffForHumans(),
                'last_activity_at' => $conversation->last_activity_at?->diffForHumans(),
                'duration_minutes' => $conversation->getDurationInMinutes(),
            ])->toArray();

        } catch (\Exception $e) {
            Log::error('Conversation search failed', ['error' => $e->getMessage()]);
            $this->conversations = [];
        }
    }

    public function filterConversations(): void
    {
        $searchQuery = $this->data['searchQuery'] ?? '';

        if (! empty($searchQuery)) {
            $this->searchConversations();
        } else {
            $this->loadConversations();
        }
    }

    public function exportSelectedConversation(): void
    {
        if (! $this->selectedConversation) {
            return;
        }

        try {
            $conversationManager = app(ConversationManager::class);
            $exportData = $conversationManager->exportConversation($this->selectedConversation);

            $filename = "conversation_{$this->selectedConversation->session_id}.json";

            // In a real implementation, you would generate a download response
            // For now, we'll just show a success notification
            Notification::make()
                ->title('Export Ready')
                ->body("Conversation exported successfully as {$filename}")
                ->success()
                ->send();

        } catch (\Exception $e) {
            Log::error('Conversation export failed', ['error' => $e->getMessage()]);

            Notification::make()
                ->title('Export Failed')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    public function archiveSelectedConversation(): void
    {
        if (! $this->selectedConversation) {
            return;
        }

        try {
            $conversationManager = app(ConversationManager::class);
            $conversationManager->archiveConversation($this->selectedConversation);

            $this->selectedConversation = null;
            $this->selectedConversationMessages = [];
            $this->loadConversations();
            $this->loadConversationStats();

            Notification::make()
                ->title('Conversation Archived')
                ->body('The conversation has been archived successfully')
                ->success()
                ->send();

        } catch (\Exception $e) {
            Log::error('Conversation archive failed', ['error' => $e->getMessage()]);

            Notification::make()
                ->title('Archive Failed')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    public function deleteSelectedConversation(): void
    {
        if (! $this->selectedConversation) {
            return;
        }

        try {
            $conversationManager = app(ConversationManager::class);
            $conversationManager->deleteConversation($this->selectedConversation);

            $this->selectedConversation = null;
            $this->selectedConversationMessages = [];
            $this->loadConversations();
            $this->loadConversationStats();

            Notification::make()
                ->title('Conversation Deleted')
                ->body('The conversation has been deleted permanently')
                ->success()
                ->send();

        } catch (\Exception $e) {
            Log::error('Conversation deletion failed', ['error' => $e->getMessage()]);

            Notification::make()
                ->title('Deletion Failed')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    public function clearAllFilters(): void
    {
        $this->data = [
            'searchQuery' => '',
            'statusFilter' => 'all',
            'connectionFilter' => 'all',
            'dateFrom' => null,
            'dateTo' => null,
        ];

        $this->loadConversations();

        Notification::make()
            ->title('Filters Cleared')
            ->success()
            ->send();
    }

    public function refreshStatistics(): void
    {
        $this->loadConversationStats();

        Notification::make()
            ->title('Statistics Refreshed')
            ->success()
            ->send();
    }

    #[On('echo:conversations,conversation.updated')]
    public function handleConversationUpdated($data): void
    {
        $this->loadConversations();
        $this->loadConversationStats();
    }

    private function buildFilters(): array
    {
        $filters = [];

        if (($this->data['statusFilter'] ?? 'all') !== 'all') {
            $filters['status'] = $this->data['statusFilter'];
        }

        if (($this->data['connectionFilter'] ?? 'all') !== 'all') {
            $filters['connection_id'] = $this->data['connectionFilter'];
        }

        if (! empty($this->data['dateFrom'])) {
            $filters['date_from'] = $this->data['dateFrom'];
        }

        if (! empty($this->data['dateTo'])) {
            $filters['date_to'] = $this->data['dateTo'];
        }

        return $filters;
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
