<?php

namespace App\Filament\Pages;

use App\Models\McpConnection;
use App\Services\McpClient;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Log;

class McpConversation extends Page
{
    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-chat-bubble-left-right';
    protected string $view = 'filament.pages.mcp-conversation';
    protected static ?string $title = 'MCP Conversation';
    protected static ?string $navigationLabel = 'Chat with Claude';
    
    public ?int $selectedConnection = null;
    public string $message = '';
    public array $conversation = [];
    public array $availableTools = [];
    public ?string $selectedTool = null;
    public array $toolArguments = [];
    
    public function mount(): void
    {
        $this->conversation = [];
        $this->loadAvailableConnections();
    }
    
    protected function getForms(): array
    {
        return [
            'conversationForm' => $this->form(Form::make()->schema([
                Select::make('selectedConnection')
                    ->label('MCP Connection')
                    ->options(McpConnection::active()->pluck('name', 'id'))
                    ->required()
                    ->live()
                    ->afterStateUpdated(fn () => $this->loadTools()),
                    
                Textarea::make('message')
                    ->label('Message')
                    ->rows(3)
                    ->placeholder('Type your message to Claude Code...')
                    ->required(),
                    
                Select::make('selectedTool')
                    ->label('Use Tool (Optional)')
                    ->options(fn () => collect($this->availableTools)->pluck('description', 'name'))
                    ->nullable()
                    ->live(),
                    
                KeyValue::make('toolArguments')
                    ->label('Tool Arguments')
                    ->visible(fn () => !empty($this->selectedTool))
                    ->keyLabel('Parameter')
                    ->valueLabel('Value'),
            ])),
        ];
    }
    
    protected function getHeaderActions(): array
    {
        return [
            Action::make('sendMessage')
                ->label('Send Message')
                ->icon('heroicon-o-paper-airplane')
                ->action('sendMessage')
                ->disabled(fn () => empty($this->selectedConnection)),
                
            Action::make('callTool')
                ->label('Call Tool')
                ->icon('heroicon-o-wrench')
                ->action('callTool')
                ->disabled(fn () => empty($this->selectedConnection) || empty($this->selectedTool))
                ->visible(fn () => !empty($this->selectedTool)),
                
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
        if (empty($this->message) || empty($this->selectedConnection)) {
            return;
        }
        
        try {
            $connection = McpConnection::find($this->selectedConnection);
            
            if (!$connection) {
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
                'content' => $this->message,
                'timestamp' => now()->toISOString(),
            ];
            
            // Send conversation to Claude Code
            $response = $client->executeConversation([
                [
                    'role' => 'user',
                    'content' => $this->message
                ]
            ]);
            
            if (isset($response['error'])) {
                $this->conversation[] = [
                    'role' => 'error',
                    'content' => 'Error: ' . $response['error'],
                    'timestamp' => now()->toISOString(),
                ];
            } else {
                $this->conversation[] = [
                    'role' => 'assistant',
                    'content' => $response['content'] ?? 'No response content',
                    'timestamp' => now()->toISOString(),
                ];
            }
            
            $this->message = '';
            
            Notification::make()
                ->title('Message sent successfully')
                ->success()
                ->send();
                
        } catch (\Exception $e) {
            Log::error('Conversation error', ['error' => $e->getMessage()]);
            
            $this->conversation[] = [
                'role' => 'error',
                'content' => 'Error: ' . $e->getMessage(),
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
        if (empty($this->selectedTool) || empty($this->selectedConnection)) {
            return;
        }
        
        try {
            $connection = McpConnection::find($this->selectedConnection);
            
            if (!$connection) {
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
                'content' => "Calling tool: {$this->selectedTool}",
                'tool' => $this->selectedTool,
                'arguments' => $this->toolArguments,
                'timestamp' => now()->toISOString(),
            ];
            
            $response = $client->callTool($this->selectedTool, $this->toolArguments);
            
            if (isset($response['error'])) {
                $this->conversation[] = [
                    'role' => 'error',
                    'content' => 'Tool Error: ' . $response['error'],
                    'timestamp' => now()->toISOString(),
                ];
            } else {
                $this->conversation[] = [
                    'role' => 'tool_result',
                    'content' => $response['content'] ?? json_encode($response, JSON_PRETTY_PRINT),
                    'timestamp' => now()->toISOString(),
                ];
            }
            
            $this->selectedTool = null;
            $this->toolArguments = [];
            
            Notification::make()
                ->title('Tool executed successfully')
                ->success()
                ->send();
                
        } catch (\Exception $e) {
            Log::error('Tool call error', ['error' => $e->getMessage()]);
            
            $this->conversation[] = [
                'role' => 'error',
                'content' => 'Tool Error: ' . $e->getMessage(),
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
        if (empty($this->selectedConnection)) {
            $this->availableTools = [];
            return;
        }
        
        try {
            $connection = McpConnection::find($this->selectedConnection);
            
            if (!$connection) {
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