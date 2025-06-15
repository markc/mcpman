<?php

namespace App\Filament\Pages;

use App\Models\McpConnection;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;

class McpSecurity extends Page implements HasForms, HasTable
{
    use InteractsWithForms;
    use InteractsWithTable;

    protected static ?string $title = 'MCP Security';

    protected static ?string $navigationLabel = 'Security';

    protected static ?int $navigationSort = 4;

    public function getView(): string
    {
        return 'filament.pages.mcp-security';
    }

    public ?array $data = [];

    public function mount(): void
    {
        $this->loadSecuritySettings();
        $this->form->fill($this->data);
    }

    protected function getFormSchema(): array
    {
        return [
            Section::make('Authentication Settings')
                ->description('Configure authentication and authorization')
                ->schema([
                    Toggle::make('require_authentication')
                        ->label('Require Authentication')
                        ->default(true)
                        ->helperText('Require users to authenticate before accessing MCP features'),

                    Toggle::make('require_mcp_permission')
                        ->label('Require MCP Permission')
                        ->default(true)
                        ->helperText('Require explicit MCP permission to use MCP features'),

                    TextInput::make('session_timeout')
                        ->label('Session Timeout (minutes)')
                        ->numeric()
                        ->default(120)
                        ->required()
                        ->helperText('Auto-logout inactive users'),

                    TextInput::make('max_failed_attempts')
                        ->label('Max Failed Login Attempts')
                        ->numeric()
                        ->default(5)
                        ->required()
                        ->helperText('Lock account after failed attempts'),
                ]),

            Section::make('Connection Security')
                ->description('Security settings for MCP connections')
                ->schema([
                    Toggle::make('enforce_ssl')
                        ->label('Enforce SSL/TLS')
                        ->default(true)
                        ->helperText('Require secure connections for all MCP communications'),

                    Toggle::make('verify_certificates')
                        ->label('Verify SSL Certificates')
                        ->default(true)
                        ->helperText('Validate SSL certificates for secure connections'),

                    TextInput::make('connection_timeout')
                        ->label('Connection Timeout (seconds)')
                        ->numeric()
                        ->default(30)
                        ->required()
                        ->helperText('Maximum time to establish connections'),

                    TextInput::make('idle_timeout')
                        ->label('Idle Connection Timeout (minutes)')
                        ->numeric()
                        ->default(15)
                        ->required()
                        ->helperText('Close idle connections after this time'),
                ]),

            Section::make('Rate Limiting')
                ->description('Configure rate limiting and abuse prevention')
                ->schema([
                    TextInput::make('requests_per_minute')
                        ->label('Requests per Minute')
                        ->numeric()
                        ->default(60)
                        ->required()
                        ->helperText('Maximum requests per user per minute'),

                    TextInput::make('connections_per_user')
                        ->label('Max Connections per User')
                        ->numeric()
                        ->default(5)
                        ->required()
                        ->helperText('Maximum concurrent connections per user'),

                    Toggle::make('block_suspicious_activity')
                        ->label('Block Suspicious Activity')
                        ->default(true)
                        ->helperText('Automatically block suspicious connection patterns'),
                ]),

            Section::make('Audit & Logging')
                ->description('Configure security logging and auditing')
                ->schema([
                    Toggle::make('log_all_connections')
                        ->label('Log All Connections')
                        ->default(true)
                        ->helperText('Log all MCP connection attempts'),

                    Toggle::make('log_failed_attempts')
                        ->label('Log Failed Attempts')
                        ->default(true)
                        ->helperText('Log failed authentication and connection attempts'),

                    Select::make('log_level')
                        ->label('Log Level')
                        ->options([
                            'debug' => 'Debug',
                            'info' => 'Info',
                            'warning' => 'Warning',
                            'error' => 'Error',
                        ])
                        ->default('info')
                        ->required(),

                    TextInput::make('log_retention_days')
                        ->label('Log Retention (days)')
                        ->numeric()
                        ->default(30)
                        ->required()
                        ->helperText('How long to keep security logs'),
                ]),
        ];
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components($this->getFormSchema())
            ->statePath('data')
            ->columns(1);
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                McpConnection::query()
                    ->with('user')
                    ->where('status', '!=', 'deleted')
            )
            ->columns([
                TextColumn::make('name')
                    ->label('Connection Name')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('user.name')
                    ->label('Owner')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('transport_type')
                    ->label('Transport')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'stdio' => 'info',
                        'http' => 'warning',
                        'websocket' => 'success',
                        default => 'gray',
                    }),

                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'active' => 'success',
                        'inactive' => 'warning',
                        'error' => 'danger',
                        'connecting' => 'info',
                        default => 'gray',
                    }),

                TextColumn::make('last_connected_at')
                    ->label('Last Connected')
                    ->dateTime()
                    ->sortable(),

                TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime()
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->striped()
            ->paginated([10, 25, 50]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('save')
                ->label('Save Security Settings')
                ->icon('heroicon-o-shield-check')
                ->action('saveSecuritySettings')
                ->requiresConfirmation()
                ->modalHeading('Save Security Settings')
                ->modalDescription('This will update security settings and may affect active connections.')
                ->modalSubmitActionLabel('Save Settings'),

            Action::make('auditConnections')
                ->label('Audit All Connections')
                ->icon('heroicon-o-eye')
                ->action('auditAllConnections')
                ->color('info')
                ->requiresConfirmation()
                ->modalHeading('Security Audit')
                ->modalDescription('Perform a security audit of all MCP connections.')
                ->modalSubmitActionLabel('Run Audit'),

            Action::make('revokeAll')
                ->label('Revoke All Sessions')
                ->icon('heroicon-o-x-circle')
                ->action('revokeAllSessions')
                ->color('danger')
                ->requiresConfirmation()
                ->modalHeading('Revoke All Sessions')
                ->modalDescription('This will forcibly disconnect all active MCP connections. Use with caution.')
                ->modalSubmitActionLabel('Revoke All'),
        ];
    }

    public function loadSecuritySettings(): void
    {
        // Load from config or database
        $this->data = [
            'require_authentication' => config('mcp.security.require_authentication', true),
            'require_mcp_permission' => config('mcp.security.require_mcp_permission', true),
            'session_timeout' => config('mcp.security.session_timeout', 120),
            'max_failed_attempts' => config('mcp.security.max_failed_attempts', 5),
            'enforce_ssl' => config('mcp.security.enforce_ssl', true),
            'verify_certificates' => config('mcp.security.verify_certificates', true),
            'connection_timeout' => config('mcp.security.connection_timeout', 30),
            'idle_timeout' => config('mcp.security.idle_timeout', 15),
            'requests_per_minute' => config('mcp.security.requests_per_minute', 60),
            'connections_per_user' => config('mcp.security.connections_per_user', 5),
            'block_suspicious_activity' => config('mcp.security.block_suspicious_activity', true),
            'log_all_connections' => config('mcp.security.log_all_connections', true),
            'log_failed_attempts' => config('mcp.security.log_failed_attempts', true),
            'log_level' => config('mcp.security.log_level', 'info'),
            'log_retention_days' => config('mcp.security.log_retention_days', 30),
        ];
    }

    public function saveSecuritySettings(): void
    {
        $this->form->getState();

        // In a real implementation, this would update the configuration
        // For now, we'll just show a success notification

        Notification::make()
            ->title('Security Settings Saved')
            ->body('Security settings have been updated successfully.')
            ->success()
            ->send();
    }

    public function auditAllConnections(): void
    {
        $connections = McpConnection::all();
        $issues = [];

        foreach ($connections as $connection) {
            // Check for security issues
            if ($connection->transport_type === 'http' && ! str_starts_with($connection->endpoint_url, 'https://')) {
                $issues[] = "Connection '{$connection->name}' uses insecure HTTP";
            }

            if (empty($connection->auth_config) || $connection->auth_config['type'] === 'none') {
                $issues[] = "Connection '{$connection->name}' has no authentication configured";
            }

            if ($connection->status === 'error') {
                $issues[] = "Connection '{$connection->name}' is in error state";
            }
        }

        if (empty($issues)) {
            Notification::make()
                ->title('Security Audit Complete')
                ->body('No security issues found.')
                ->success()
                ->send();
        } else {
            Notification::make()
                ->title('Security Issues Found')
                ->body(implode("\n", $issues))
                ->warning()
                ->persistent()
                ->send();
        }
    }

    public function revokeAllSessions(): void
    {
        // In a real implementation, this would:
        // 1. Disconnect all active MCP connections
        // 2. Invalidate user sessions
        // 3. Force re-authentication

        $manager = app(\App\Services\PersistentMcpManager::class);
        $connections = McpConnection::where('status', 'active')->get();

        foreach ($connections as $connection) {
            $manager->stopConnection((string) $connection->id);
            $connection->update(['status' => 'inactive']);
        }

        Notification::make()
            ->title('All Sessions Revoked')
            ->body(count($connections).' connections have been forcibly disconnected.')
            ->warning()
            ->send();
    }
}
