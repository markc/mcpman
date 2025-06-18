<?php

namespace App\Filament\Pages;

use App\Events\McpConversationMessage;
use App\Models\McpConnection;
use App\Models\Tool;
use App\Services\McpClient;
use App\Services\ToolRegistryManager;
use Filament\Actions\Action;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Heroicon;
use Illuminate\Support\Facades\Log;

class McpConversation extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $title = 'MCP Conversation';

    protected static ?string $navigationLabel = 'Chat with Claude';

    public function getView(): string
    {
        return 'filament.pages.mcp-conversation';
    }

    public ?array $data = [];

    public array $conversation = [];

    public array $availableTools = [];

    public function getFormSchema(): array
    {
        return [
            Select::make('selectedConnection')
                ->label('MCP Connection')
                ->options(McpConnection::where('status', 'active')->pluck('name', 'id'))
                ->required()
                ->live(debounce: 500)
                ->afterStateUpdated(fn () => $this->loadTools()),

            Select::make('selectedTool')
                ->label('Use Tool (Optional)')
                ->options(fn () => $this->getToolOptions())
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

    public function mount(): void
    {
        $this->conversation = [];
        $this->form->fill();
        $this->loadAvailableConnections();
    }

    public function handleIncomingMessage($data): void
    {
        // Add message to conversation if it's for the current connection
        if (isset($data['connection_id']) &&
            $data['connection_id'] == ($this->data['selectedConnection'] ?? null)) {

            $this->conversation[] = $data['message'];

            // Dispatch browser event to scroll to bottom
            $this->dispatch('conversation-updated');
        }
    }

    public function getListeners(): array
    {
        $userId = auth()->id() ?? 1;

        return [
            "echo:mcp-conversations.{$userId},conversation.message" => 'handleIncomingMessage',
        ];
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components($this->getFormSchema())
            ->statePath('data')
            ->columns(2);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('sendMessage')
                ->label('Send Message')
                ->icon(Heroicon::OUTLINE_PAPER_AIRPLANE)
                ->action('sendMessage')
                ->disabled(fn () => empty($this->data['selectedConnection'] ?? null)),

            Action::make('callTool')
                ->label('Call Tool')
                ->icon(Heroicon::OUTLINE_WRENCH)
                ->action('callTool')
                ->disabled(fn () => empty($this->data['selectedConnection'] ?? null) || empty($this->data['selectedTool'] ?? null))
                ->visible(fn () => ! empty($this->data['selectedTool'] ?? null)),

            Action::make('clearConversation')
                ->label('Clear')
                ->icon(Heroicon::OUTLINE_TRASH)
                ->action('clearConversation')
                ->color('danger')
                ->requiresConfirmation(),

            Action::make('refreshTools')
                ->label('Refresh Tools')
                ->icon(Heroicon::OUTLINE_ARROW_PATH)
                ->action('loadTools'),
        ];
    }

    public function sendMessage(): void
    {
        Log::info('sendMessage method called', ['data' => $this->data]);

        // Validate form first
        $this->form->getState();

        // Use the data property which is where form data is stored
        $formData = $this->data;

        if (empty($formData['message']) || empty($formData['selectedConnection'])) {
            Notification::make()
                ->title('Please fill in all required fields')
                ->warning()
                ->send();

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
            $userMessage = [
                'role' => 'user',
                'content' => $formData['message'],
                'timestamp' => now()->toISOString(),
            ];
            $this->conversation[] = $userMessage;

            // Broadcast user message
            McpConversationMessage::dispatch(
                auth()->user() ?? \App\Models\User::first(),
                $connection,
                $userMessage
            );

            // Send conversation to Claude Code
            $response = $client->executeConversation([
                [
                    'role' => 'user',
                    'content' => $formData['message'],
                ],
            ]);

            Log::info('MCP Response received', ['response' => $response]);

            if (isset($response['error'])) {
                $errorMessage = [
                    'role' => 'error',
                    'content' => 'Error: '.$response['error'],
                    'timestamp' => now()->toISOString(),
                ];
                $this->conversation[] = $errorMessage;

                // Broadcast error message
                McpConversationMessage::dispatch(
                    auth()->user() ?? \App\Models\User::first(),
                    $connection,
                    $errorMessage
                );
            } else {
                // Handle both direct content and result.content formats
                $content = $response['content'] ?? $response['result']['content'] ?? 'No response content';

                $assistantMessage = [
                    'role' => 'assistant',
                    'content' => $content,
                    'timestamp' => now()->toISOString(),
                ];
                $this->conversation[] = $assistantMessage;

                // Broadcasting is handled by McpClient for assistant messages
            }

            $this->data['message'] = '';

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
        $this->form->getState();
        $formData = $this->data;

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

            // Find the tool in the registry
            $tool = Tool::where('mcp_connection_id', $connection->id)
                ->where('name', $formData['selectedTool'])
                ->first();

            // Add tool call to conversation
            $this->conversation[] = [
                'role' => 'tool_call',
                'content' => "Calling tool: {$formData['selectedTool']}",
                'tool' => $formData['selectedTool'],
                'arguments' => $formData['toolArguments'] ?? [],
                'timestamp' => now()->toISOString(),
            ];

            // Execute tool using the ToolRegistryManager for tracking
            if ($tool) {
                $toolRegistry = app(ToolRegistryManager::class);
                $response = $toolRegistry->executeTool($tool, $formData['toolArguments'] ?? []);
            } else {
                // Fallback to direct client call if tool not in registry
                $client = new McpClient($connection);
                $response = $client->callTool($formData['selectedTool'], $formData['toolArguments'] ?? []);
            }

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

            $this->data['selectedTool'] = null;
            $this->data['toolArguments'] = [];

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
        // Use the data property instead of form state to avoid validation errors
        $selectedConnection = $this->data['selectedConnection'] ?? null;

        if (empty($selectedConnection)) {
            $this->availableTools = [];

            return;
        }

        try {
            $connection = McpConnection::find($selectedConnection);

            if (! $connection) {
                $this->availableTools = [];

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

    private function getToolOptions(): array
    {
        $selectedConnection = $this->data['selectedConnection'] ?? null;

        if (! $selectedConnection) {
            return [];
        }

        // First try to get tools from registry
        $registryTools = Tool::where('mcp_connection_id', $selectedConnection)
            ->where('is_active', true)
            ->pluck('description', 'name')
            ->toArray();

        // Fallback to available tools from client
        $clientTools = collect($this->availableTools)->pluck('description', 'name')->toArray();

        // Merge both sources, registry takes precedence
        return array_merge($clientTools, $registryTools);
    }
}
