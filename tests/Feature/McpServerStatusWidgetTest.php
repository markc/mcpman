<?php

namespace Tests\Feature;

use App\Filament\Widgets\McpServerStatusWidget;
use App\Models\McpConnection;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class McpServerStatusWidgetTest extends TestCase
{
    use RefreshDatabase;

    public function test_widget_loads_server_status_correctly(): void
    {
        echo "\n🔧 Testing McpServerStatusWidget...\n";

        // Create test data
        $user = User::create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => bcrypt('password'),
        ]);

        $connection = McpConnection::create([
            'name' => 'Test Connection',
            'endpoint_url' => 'claude mcp serve',
            'transport_type' => 'stdio',
            'status' => 'active',
            'auth_config' => ['type' => 'none'],
            'capabilities' => ['tools' => true, 'resources' => true],
            'metadata' => ['test' => true],
            'user_id' => $user->id,
        ]);

        // Test widget instantiation
        $widget = new McpServerStatusWidget;
        $this->assertInstanceOf(McpServerStatusWidget::class, $widget);
        echo "✓ Widget instantiated successfully\n";

        // Test mount method
        $widget->mount();
        $this->assertIsArray($widget->serverStatus);
        echo "✓ Widget mounted and server status loaded\n";

        // Test that server status contains expected keys
        $this->assertArrayHasKey('status', $widget->serverStatus);
        $this->assertArrayHasKey('last_updated', $widget->serverStatus);
        echo "✓ Server status has required keys\n";

        // Test render method
        $view = $widget->render();
        $this->assertInstanceOf(\Illuminate\Contracts\View\View::class, $view);
        echo "✓ Widget renders view correctly\n";

        // Test refresh status method
        $widget->refreshStatus();
        echo "✓ Refresh status method works\n";

        echo "✅ McpServerStatusWidget test completed!\n";
    }

    public function test_widget_handles_health_check_with_multiple_connections(): void
    {
        echo "\n💊 Testing health check with multiple connections...\n";

        $user = User::create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => bcrypt('password'),
        ]);

        // Create multiple connections
        $connections = [
            McpConnection::create([
                'name' => 'Connection 1',
                'endpoint_url' => 'claude mcp serve',
                'transport_type' => 'stdio',
                'status' => 'active',
                'auth_config' => ['type' => 'none'],
                'capabilities' => ['tools' => true],
                'metadata' => ['test' => true],
                'user_id' => $user->id,
            ]),
            McpConnection::create([
                'name' => 'Connection 2',
                'endpoint_url' => 'http://localhost:3000',
                'transport_type' => 'http',
                'status' => 'active',
                'auth_config' => ['type' => 'none'],
                'capabilities' => ['resources' => true],
                'metadata' => ['test' => true],
                'user_id' => $user->id,
            ]),
            McpConnection::create([
                'name' => 'Inactive Connection',
                'endpoint_url' => 'ws://localhost:4000',
                'transport_type' => 'websocket',
                'status' => 'inactive',
                'auth_config' => ['type' => 'none'],
                'capabilities' => ['tools' => false],
                'metadata' => ['test' => true],
                'user_id' => $user->id,
            ]),
        ];

        $widget = new McpServerStatusWidget;
        $widget->mount();

        // Check that widget loaded status for active connections only
        $this->assertArrayHasKey('active_connections', $widget->serverStatus);
        $this->assertEquals(2, $widget->serverStatus['active_connections']); // Only active ones

        // Check health check results
        $this->assertArrayHasKey('health_check', $widget->serverStatus);
        $this->assertIsArray($widget->serverStatus['health_check']);

        echo "✓ Health check handled multiple connections correctly\n";
        echo "✓ Active connections count: {$widget->serverStatus['active_connections']}\n";
        echo '✓ Health checks performed: '.count($widget->serverStatus['health_check'])."\n";

        echo "✅ Multiple connections health check test completed!\n";
    }

    public function test_widget_handles_error_states(): void
    {
        echo "\n❌ Testing error state handling...\n";

        $widget = new McpServerStatusWidget;

        // Test with no connections (should not error)
        $widget->mount();

        $this->assertArrayHasKey('status', $widget->serverStatus);
        $this->assertArrayHasKey('active_connections', $widget->serverStatus);
        $this->assertEquals(0, $widget->serverStatus['active_connections']);

        echo "✓ Widget handles empty connection state\n";
        echo "✓ No errors with zero connections\n";

        echo "✅ Error state handling test completed!\n";
    }
}
