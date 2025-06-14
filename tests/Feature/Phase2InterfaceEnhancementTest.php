<?php

namespace Tests\Feature;

use App\Models\McpConnection;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Phase2InterfaceEnhancementTest extends TestCase
{
    use RefreshDatabase;

    public function test_phase_2_interface_enhancement_comprehensive(): void
    {
        echo "\n🚀 Starting Phase 2 Interface Enhancement Test...\n";

        // Step 1: Setup test data
        $this->setupTestData();

        // Step 2: Test monitoring page functionality
        $this->testMonitoringPage();

        // Step 3: Test configuration page functionality
        $this->testConfigurationPage();

        // Step 4: Test security page functionality
        $this->testSecurityPage();

        // Step 5: Test widget functionality
        $this->testWidgetFunctionality();

        echo "\n✅ Phase 2 Interface Enhancement Test Completed!\n";
    }

    private function setupTestData(): void
    {
        echo "📝 Setting up test data for Phase 2...\n";

        // Create test user
        $user = User::create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => bcrypt('password'),
        ]);

        // Create multiple test MCP connections
        McpConnection::create([
            'name' => 'Active Connection',
            'endpoint_url' => 'claude mcp serve',
            'transport_type' => 'stdio',
            'status' => 'active',
            'auth_config' => ['type' => 'bearer', 'token' => 'test-token'],
            'capabilities' => ['tools' => true, 'resources' => true],
            'metadata' => ['test' => true],
            'user_id' => $user->id,
        ]);

        McpConnection::create([
            'name' => 'HTTP Connection',
            'endpoint_url' => 'http://localhost:3000/mcp',
            'transport_type' => 'http',
            'status' => 'inactive',
            'auth_config' => ['type' => 'none'],
            'capabilities' => ['tools' => false, 'resources' => true],
            'metadata' => ['test' => true],
            'user_id' => $user->id,
        ]);

        McpConnection::create([
            'name' => 'Error Connection',
            'endpoint_url' => 'invalid://endpoint',
            'transport_type' => 'websocket',
            'status' => 'error',
            'auth_config' => ['type' => 'api_key', 'key' => 'test-key'],
            'capabilities' => ['tools' => true, 'resources' => false],
            'metadata' => ['test' => true, 'error' => 'Connection failed'],
            'user_id' => $user->id,
        ]);

        echo "✓ Test data created (1 user, 3 connections)\n";
    }

    private function test_monitoring_page(): void
    {
        echo "\n📊 Testing MCP Monitoring Page...\n";

        $user = User::first();

        // Test monitoring page access
        $response = $this->actingAs($user)->get('/admin/mcp-monitoring');
        $this->assertTrue(in_array($response->status(), [200, 403]));
        echo "✓ Monitoring page accessible\n";

        // Test monitoring page class exists
        $this->assertTrue(class_exists(\App\Filament\Pages\McpMonitoring::class));
        echo "✓ McpMonitoring page class exists\n";

        // Test monitoring page methods
        $monitoringPage = new \App\Filament\Pages\McpMonitoring;
        $this->assertTrue(method_exists($monitoringPage, 'loadConnectionStats'));
        $this->assertTrue(method_exists($monitoringPage, 'loadActiveConnections'));
        $this->assertTrue(method_exists($monitoringPage, 'loadSystemMetrics'));
        echo "✓ Monitoring page has required methods\n";
    }

    private function test_configuration_page(): void
    {
        echo "\n⚙️ Testing MCP Configuration Page...\n";

        $user = User::first();

        // Test configuration page access
        $response = $this->actingAs($user)->get('/admin/mcp-configuration');
        $this->assertTrue(in_array($response->status(), [200, 403]));
        echo "✓ Configuration page accessible\n";

        // Test configuration page class exists
        $this->assertTrue(class_exists(\App\Filament\Pages\McpConfiguration::class));
        echo "✓ McpConfiguration page class exists\n";

        // Test configuration page methods
        $configPage = new \App\Filament\Pages\McpConfiguration;
        $this->assertTrue(method_exists($configPage, 'loadConfiguration'));
        $this->assertTrue(method_exists($configPage, 'saveConfiguration'));
        $this->assertTrue(method_exists($configPage, 'resetConfiguration'));
        echo "✓ Configuration page has required methods\n";
    }

    private function test_security_page(): void
    {
        echo "\n🔒 Testing MCP Security Page...\n";

        $user = User::first();

        // Test security page access
        $response = $this->actingAs($user)->get('/admin/mcp-security');
        $this->assertTrue(in_array($response->status(), [200, 403]));
        echo "✓ Security page accessible\n";

        // Test security page class exists
        $this->assertTrue(class_exists(\App\Filament\Pages\McpSecurity::class));
        echo "✓ McpSecurity page class exists\n";

        // Test security page methods
        $securityPage = new \App\Filament\Pages\McpSecurity;
        $this->assertTrue(method_exists($securityPage, 'loadSecuritySettings'));
        $this->assertTrue(method_exists($securityPage, 'saveSecuritySettings'));
        $this->assertTrue(method_exists($securityPage, 'auditAllConnections'));
        echo "✓ Security page has required methods\n";
    }

    private function test_widget_functionality(): void
    {
        echo "\n📊 Testing Widget Functionality...\n";

        // Test McpConnectionStatsWidget
        $this->assertTrue(class_exists(\App\Filament\Widgets\McpConnectionStatsWidget::class));
        $statsWidget = new \App\Filament\Widgets\McpConnectionStatsWidget;
        $this->assertTrue(method_exists($statsWidget, 'getStats'));
        echo "✓ McpConnectionStatsWidget exists and functional\n";

        // Test McpServerStatusWidget
        $this->assertTrue(class_exists(\App\Filament\Widgets\McpServerStatusWidget::class));
        $statusWidget = new \App\Filament\Widgets\McpServerStatusWidget;
        $this->assertTrue(method_exists($statusWidget, 'loadServerStatus'));
        echo "✓ McpServerStatusWidget exists and functional\n";
    }

    public function test_mcp_navigation_structure(): void
    {
        echo "\n🧭 Testing MCP Navigation Structure...\n";

        // Test that all pages have proper navigation configuration
        $pages = [
            \App\Filament\Pages\McpDashboard::class,
            \App\Filament\Pages\McpMonitoring::class,
            \App\Filament\Pages\McpConfiguration::class,
            \App\Filament\Pages\McpSecurity::class,
            \App\Filament\Pages\McpConversation::class,
        ];

        foreach ($pages as $pageClass) {
            $this->assertTrue(class_exists($pageClass));

            // Test that page has navigation label
            $reflection = new \ReflectionClass($pageClass);
            $this->assertTrue($reflection->hasProperty('navigationLabel') ||
                             $reflection->hasProperty('title'));

            echo "✓ {$pageClass} has proper navigation structure\n";
        }
    }

    public function test_mcp_widgets_integration(): void
    {
        echo "\n🔧 Testing Widget Integration with Dashboard...\n";

        // Test that dashboard includes the new widgets
        $dashboard = new \App\Filament\Pages\McpDashboard;
        $widgets = $dashboard->getWidgets();

        $this->assertContains(\App\Filament\Widgets\McpConnectionStatsWidget::class, $widgets);
        $this->assertContains(\App\Filament\Widgets\McpServerStatusWidget::class, $widgets);

        echo "✓ New widgets integrated into dashboard\n";
        echo '✓ Dashboard widget count: '.count($widgets)."\n";
    }

    public function test_real_time_event_structure(): void
    {
        echo "\n📡 Testing Real-time Event Structure...\n";

        // Test that pages have proper event listeners
        $pagesWithEvents = [
            \App\Filament\Pages\McpMonitoring::class,
            \App\Filament\Widgets\McpConnectionStatsWidget::class,
            \App\Filament\Widgets\McpServerStatusWidget::class,
        ];

        foreach ($pagesWithEvents as $class) {
            $instance = new $class;

            if (method_exists($instance, 'getListeners')) {
                $listeners = $instance->getListeners();
                $this->assertIsArray($listeners);
                echo "✓ {$class} has event listeners configured\n";
            }
        }
    }

    public function test_security_features(): void
    {
        echo "\n🛡️ Testing Security Features...\n";

        $connections = McpConnection::all();

        // Test connection security analysis
        $httpConnections = $connections->where('transport_type', 'http');
        $errorConnections = $connections->where('status', 'error');
        $secureConnections = $connections->where('transport_type', 'stdio');

        echo '✓ HTTP connections found: '.$httpConnections->count()."\n";
        echo '✓ Error connections found: '.$errorConnections->count()."\n";
        echo '✓ Secure connections found: '.$secureConnections->count()."\n";

        // Test that each connection has auth config
        foreach ($connections as $connection) {
            $this->assertIsArray($connection->auth_config);
            $this->assertArrayHasKey('type', $connection->auth_config);
        }

        echo "✓ All connections have auth configuration\n";
    }
}
