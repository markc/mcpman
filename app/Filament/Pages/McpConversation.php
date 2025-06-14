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

    protected static ?string $title = 'MCP Conversation';

    protected static ?string $navigationLabel = 'Chat with Claude';

    protected static string $view = 'filament.pages.mcp-conversation';

    public ?array $data = [];

    public array $conversation = [];

    public array $availableTools = [];

    public function mount(): void
    {
        $this->conversation = [];
        $this->loadAvailableConnections();
    }

    public function form(Form $form): Form
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
}
