<?php

namespace App\Filament\Pages;

use App\Models\McpConnection;
use App\Models\Dataset;
use App\Models\Document;
use App\Models\ApiKey;
use App\Services\McpClient;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Pages\Page;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class McpDashboard extends Page
{
    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-presentation-chart-line';
    protected string $view = 'filament.pages.mcp-dashboard';
    protected static ?string $title = 'MCP Dashboard';
    protected static ?string $navigationLabel = 'MCP Status';
    protected static ?int $navigationSort = 1;
    
    public array $connectionStats = [];
    public array $systemStats = [];
    public array $recentActivity = [];
    
    public function mount(): void
    {
        $this->loadStats();
    }
    
    protected function getHeaderActions(): array
    {
        return [
            Action::make('refreshStats')
                ->label('Refresh')
                ->icon('heroicon-o-arrow-path')
                ->action('loadStats'),
                
            Action::make('testAllConnections')
                ->label('Test All Connections')
                ->icon('heroicon-o-signal')
                ->action('testAllConnections')
                ->requiresConfirmation()
                ->modalHeading('Test All MCP Connections')
                ->modalDescription('This will test the connectivity of all active MCP connections. This may take a few moments.')
                ->modalSubmitActionLabel('Test Connections'),
        ];
    }
    
    public function loadStats(): void
    {
        // Connection Statistics
        $this->connectionStats = [
            'total' => McpConnection::count(),
            'active' => McpConnection::where('status', 'active')->count(),
            'inactive' => McpConnection::where('status', 'inactive')->count(),
            'error' => McpConnection::where('status', 'error')->count(),
            'last_24h_connections' => McpConnection::where('last_connected_at', '>=', now()->subDay())->count(),
        ];
        
        // System Statistics
        $this->systemStats = [
            'datasets' => Dataset::count(),
            'documents' => Document::count(),
            'api_keys' => ApiKey::where('is_active', true)->count(),
            'recent_datasets' => Dataset::where('created_at', '>=', now()->subWeek())->count(),
            'recent_documents' => Document::where('created_at', '>=', now()->subWeek())->count(),
        ];
        
        // Recent Activity
        $this->recentActivity = $this->getRecentActivity();
    }
    
    public function testAllConnections(): void
    {
        $connections = McpConnection::active()->get();
        $results = [];
        
        foreach ($connections as $connection) {
            try {
                $client = new McpClient($connection);
                $isConnected = $client->testConnection();
                
                if ($isConnected) {
                    $connection->markAsConnected();
                    $results[] = "✅ {$connection->name}: Connected";
                } else {
                    $connection->markAsError('Connection test failed');
                    $results[] = "❌ {$connection->name}: Failed";
                }
            } catch (\Exception $e) {
                $connection->markAsError($e->getMessage());
                $results[] = "❌ {$connection->name}: Error - " . $e->getMessage();
                Log::error('Connection test failed', ['connection' => $connection->name, 'error' => $e->getMessage()]);
            }
        }
        
        $this->loadStats();
        
        Notification::make()
            ->title('Connection Tests Completed')
            ->body(implode("\n", $results))
            ->success()
            ->persistent()
            ->send();
    }
    
    public function testConnection(int $connectionId): void
    {
        $connection = McpConnection::find($connectionId);
        
        if (!$connection) {
            Notification::make()
                ->title('Connection not found')
                ->danger()
                ->send();
            return;
        }
        
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
        
        $this->loadStats();
        
        Notification::make()
            ->title('Connection Test Result')
            ->body($message)
            ->{$type}()
            ->send();
    }
    
    private function getRecentActivity(): array
    {
        $activity = [];
        
        // Recent connection activities
        $recentConnections = McpConnection::where('last_connected_at', '>=', now()->subDay())
            ->orderBy('last_connected_at', 'desc')
            ->limit(5)
            ->get();
            
        foreach ($recentConnections as $connection) {
            $activity[] = [
                'type' => 'connection',
                'message' => "Connection '{$connection->name}' last active",
                'timestamp' => $connection->last_connected_at,
                'status' => $connection->status,
            ];
        }
        
        // Recent datasets
        $recentDatasets = Dataset::where('created_at', '>=', now()->subDay())
            ->orderBy('created_at', 'desc')
            ->limit(3)
            ->get();
            
        foreach ($recentDatasets as $dataset) {
            $activity[] = [
                'type' => 'dataset',
                'message' => "Dataset '{$dataset->name}' created",
                'timestamp' => $dataset->created_at,
                'status' => $dataset->status,
            ];
        }
        
        // Recent documents
        $recentDocuments = Document::where('created_at', '>=', now()->subDay())
            ->orderBy('created_at', 'desc')
            ->limit(3)
            ->get();
            
        foreach ($recentDocuments as $document) {
            $activity[] = [
                'type' => 'document',
                'message' => "Document '{$document->title}' created",
                'timestamp' => $document->created_at,
                'status' => 'active',
            ];
        }
        
        // Sort by timestamp descending
        usort($activity, fn($a, $b) => $b['timestamp'] <=> $a['timestamp']);
        
        return array_slice($activity, 0, 10);
    }
    
    public function getConnectionsProperty()
    {
        return McpConnection::orderBy('status', 'asc')
            ->orderBy('last_connected_at', 'desc')
            ->get();
    }
}