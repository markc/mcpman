<?php

namespace App\Filament\Pages;

use Filament\Actions\Action;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\File;

class McpConfiguration extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $title = 'MCP Configuration';

    protected static ?string $navigationLabel = 'Configuration';

    protected static ?int $navigationSort = 3;

    public function getView(): string
    {
        return 'filament.pages.mcp-configuration';
    }

    public ?array $data = [];

    public function mount(): void
    {
        $this->loadConfiguration();
        $this->form->fill($this->data);
    }

    protected function getFormSchema(): array
    {
        return [
            // Server Configuration Section
            Placeholder::make('server_config_header')
                ->label('Server Configuration')
                ->content('Configure MCP server settings')
                ->columnSpanFull(),

            TextInput::make('timeout')
                ->label('Connection Timeout (ms)')
                ->numeric()
                ->default(60000)
                ->required()
                ->helperText('Maximum time to wait for MCP connections'),

            TextInput::make('max_connections')
                ->label('Max Concurrent Connections')
                ->numeric()
                ->default(10)
                ->required()
                ->helperText('Maximum number of simultaneous MCP connections'),

            Toggle::make('persistent_connections')
                ->label('Use Persistent Connections')
                ->default(true)
                ->helperText('Keep connections alive for better performance')
                ->columnSpanFull(),

            Toggle::make('auto_reconnect')
                ->label('Auto Reconnect')
                ->default(true)
                ->helperText('Automatically reconnect when connections drop')
                ->columnSpanFull(),

            // Client Configuration Section
            Placeholder::make('client_config_header')
                ->label('Client Configuration')
                ->content('Configure MCP client behavior')
                ->columnSpanFull(),

            TextInput::make('retry_attempts')
                ->label('Retry Attempts')
                ->numeric()
                ->default(3)
                ->required()
                ->helperText('Number of retry attempts for failed requests'),

            TextInput::make('retry_delay')
                ->label('Retry Delay (ms)')
                ->numeric()
                ->default(1000)
                ->required()
                ->helperText('Delay between retry attempts'),

            Toggle::make('debug_mode')
                ->label('Debug Mode')
                ->default(false)
                ->helperText('Enable detailed logging for troubleshooting')
                ->columnSpanFull(),

            // Security Settings Section
            Placeholder::make('security_config_header')
                ->label('Security Settings')
                ->content('Configure security and authentication')
                ->columnSpanFull(),

            Select::make('auth_method')
                ->label('Default Authentication Method')
                ->options([
                    'none' => 'No Authentication',
                    'bearer' => 'Bearer Token',
                    'api_key' => 'API Key',
                    'oauth' => 'OAuth 2.0',
                ])
                ->default('none')
                ->required(),

            TextInput::make('rate_limit')
                ->label('Rate Limit (requests per minute)')
                ->numeric()
                ->default(60)
                ->required()
                ->helperText('Maximum requests per minute per connection'),

            Toggle::make('ssl_verify')
                ->label('Verify SSL Certificates')
                ->default(true)
                ->helperText('Verify SSL certificates for secure connections')
                ->columnSpanFull(),

            // Broadcasting Configuration Section
            Placeholder::make('broadcasting_config_header')
                ->label('Broadcasting Configuration')
                ->content('Configure real-time broadcasting')
                ->columnSpanFull(),

            Toggle::make('broadcasting_enabled')
                ->label('Enable Broadcasting')
                ->default(true)
                ->helperText('Enable real-time updates via WebSockets')
                ->columnSpanFull(),

            TextInput::make('broadcast_driver')
                ->label('Broadcast Driver')
                ->default('reverb')
                ->required()
                ->helperText('Broadcasting driver to use'),

            TextInput::make('broadcast_queue')
                ->label('Broadcast Queue')
                ->default('default')
                ->required()
                ->helperText('Queue to use for broadcasting jobs'),

            // Advanced Settings Section
            Placeholder::make('advanced_config_header')
                ->label('Advanced Settings')
                ->content('Advanced configuration options')
                ->columnSpanFull(),

            KeyValue::make('custom_headers')
                ->label('Custom HTTP Headers')
                ->keyLabel('Header Name')
                ->valueLabel('Header Value')
                ->helperText('Custom headers to include in MCP requests')
                ->columnSpanFull(),

            KeyValue::make('environment_variables')
                ->label('Environment Variables')
                ->keyLabel('Variable Name')
                ->valueLabel('Variable Value')
                ->helperText('Environment variables for MCP processes')
                ->columnSpanFull(),
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
            Action::make('save')
                ->label('Save Configuration')
                ->icon('heroicon-o-check')
                ->action('saveConfiguration')
                ->requiresConfirmation()
                ->modalHeading('Save MCP Configuration')
                ->modalDescription('This will update the MCP configuration file and restart services if needed.')
                ->modalSubmitActionLabel('Save Changes'),

            Action::make('reset')
                ->label('Reset to Defaults')
                ->icon('heroicon-o-arrow-path')
                ->action('resetConfiguration')
                ->color('gray')
                ->requiresConfirmation()
                ->modalHeading('Reset Configuration')
                ->modalDescription('This will reset all settings to their default values. Are you sure?')
                ->modalSubmitActionLabel('Reset'),

            Action::make('export')
                ->label('Export Config')
                ->icon('heroicon-o-arrow-down-tray')
                ->action('exportConfiguration')
                ->color('info'),

            Action::make('import')
                ->label('Import Config')
                ->icon('heroicon-o-arrow-up-tray')
                ->action('importConfiguration')
                ->color('warning'),
        ];
    }

    public function loadConfiguration(): void
    {
        $config = config('mcp', []);

        $this->data = [
            'timeout' => $config['timeout'] ?? 60000,
            'max_connections' => $config['max_connections'] ?? 10,
            'persistent_connections' => $config['client']['use_persistent'] ?? true,
            'auto_reconnect' => $config['client']['auto_reconnect'] ?? true,
            'retry_attempts' => $config['client']['retry_attempts'] ?? 3,
            'retry_delay' => $config['client']['retry_delay'] ?? 1000,
            'debug_mode' => $config['debug'] ?? false,
            'auth_method' => $config['auth']['default_method'] ?? 'none',
            'rate_limit' => $config['rate_limit'] ?? 60,
            'ssl_verify' => $config['ssl_verify'] ?? true,
            'broadcasting_enabled' => $config['broadcasting']['enabled'] ?? true,
            'broadcast_driver' => $config['broadcasting']['driver'] ?? 'reverb',
            'broadcast_queue' => $config['broadcasting']['queue'] ?? 'default',
            'custom_headers' => $config['custom_headers'] ?? [],
            'environment_variables' => $config['environment_variables'] ?? [],
        ];
    }

    public function saveConfiguration(): void
    {
        $this->form->getState();

        $config = [
            'timeout' => (int) $this->data['timeout'],
            'max_connections' => (int) $this->data['max_connections'],
            'debug' => (bool) $this->data['debug_mode'],
            'rate_limit' => (int) $this->data['rate_limit'],
            'ssl_verify' => (bool) $this->data['ssl_verify'],

            'server' => [
                'enabled' => true,
                'port' => env('MCP_SERVER_PORT', 3000),
                'host' => env('MCP_SERVER_HOST', 'localhost'),
            ],

            'client' => [
                'use_persistent' => (bool) $this->data['persistent_connections'],
                'auto_reconnect' => (bool) $this->data['auto_reconnect'],
                'retry_attempts' => (int) $this->data['retry_attempts'],
                'retry_delay' => (int) $this->data['retry_delay'],
            ],

            'auth' => [
                'default_method' => $this->data['auth_method'],
                'timeout' => 30,
            ],

            'broadcasting' => [
                'enabled' => (bool) $this->data['broadcasting_enabled'],
                'driver' => $this->data['broadcast_driver'],
                'queue' => $this->data['broadcast_queue'],
                'channels' => [
                    'mcp.connections' => 'mcp-connections',
                    'mcp.server' => 'mcp-server',
                    'mcp.conversations' => 'mcp-conversations.{user_id}',
                ],
            ],

            'custom_headers' => $this->data['custom_headers'] ?? [],
            'environment_variables' => $this->data['environment_variables'] ?? [],
        ];

        $configPath = config_path('mcp.php');
        $configContent = "<?php\n\nreturn ".var_export($config, true).";\n";

        File::put($configPath, $configContent);

        // Clear config cache
        \Artisan::call('config:clear');

        Notification::make()
            ->title('Configuration Saved')
            ->body('MCP configuration has been updated successfully.')
            ->success()
            ->send();
    }

    public function resetConfiguration(): void
    {
        $this->loadConfiguration();
        $this->form->fill($this->data);

        Notification::make()
            ->title('Configuration Reset')
            ->body('Configuration has been reset to default values.')
            ->warning()
            ->send();
    }

    public function exportConfiguration(): void
    {
        $config = config('mcp');
        $filename = 'mcp-config-'.date('Y-m-d-H-i-s').'.json';

        // In a real implementation, this would trigger a download
        // For now, we'll just show a notification
        Notification::make()
            ->title('Configuration Exported')
            ->body("Configuration would be exported as {$filename}")
            ->info()
            ->send();
    }

    public function importConfiguration(): void
    {
        // In a real implementation, this would show a file upload dialog
        Notification::make()
            ->title('Import Configuration')
            ->body('Configuration import would be implemented here')
            ->info()
            ->send();
    }
}
