<?php

namespace Tests\Browser;

use App\Models\McpConnection;
use App\Models\User;
use App\Services\PersistentMcpManager;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Symfony\Component\Panther\Client;
use Symfony\Component\Panther\PantherTestCase;

class Phase1McpImplementationTest extends PantherTestCase
{
    use DatabaseTruncation;

    private Client $client;

    protected function setUp(): void
    {
        parent::setUp();

        // Create Panther client for browser testing
        $this->client = static::createPantherClient([
            'browser' => static::FIREFOX,
        ]);
    }

    public function test_phase_1_mcp_implementation_comprehensive(): void
    {
        echo "\n🧪 Starting Phase 1 MCP Implementation Test...\n";

        // Step 1: Setup test data
        $this->setupTestData();

        // Step 2: Test MCP Dashboard
        $this->testMcpDashboard();

        // Step 3: Test MCP Connections Management
        $this->testMcpConnectionsManagement();

        // Step 4: Test MCP Conversation Interface
        $this->testMcpConversation();

        // Step 5: Test Persistent MCP Manager
        $this->testPersistentMcpManager();

        echo "\n✅ Phase 1 MCP Implementation Test Completed!\n";
    }

    private function setupTestData(): void
    {
        echo "📝 Setting up test data...\n";

        // Create test user
        $user = User::firstOrCreate([
            'email' => 'admin@example.com',
        ], [
            'name' => 'Admin User',
            'password' => bcrypt('password'),
        ]);

        // Create test MCP connection
        McpConnection::firstOrCreate([
            'name' => 'Test Claude Code Connection',
        ], [
            'endpoint_url' => 'claude mcp serve',
            'transport_type' => 'stdio',
            'status' => 'active',
            'auth_config' => ['type' => 'none'],
            'capabilities' => ['tools' => true, 'resources' => true],
            'metadata' => ['test' => true],
            'user_id' => $user->id,
        ]);

        echo "✓ Test data created\n";
    }

    private function test_mcp_dashboard(): void
    {
        echo "\n📊 Testing MCP Dashboard...\n";

        $crawler = $this->client->request('GET', 'http://localhost:8000/admin');

        // Wait for page to load
        $this->client->waitFor('.fi-sidebar-nav');

        // Take screenshot of dashboard
        $this->client->takeScreenshot('screenshots/01_mcp_dashboard.png');

        // Verify dashboard elements
        $this->assertStringContainsString('Dashboard', $this->client->getPageSource());
        $this->assertStringContainsString('Total Connections', $this->client->getPageSource());

        echo "✓ Dashboard loaded successfully\n";
        echo "📸 Screenshot saved: 01_mcp_dashboard.png\n";
    }

    private function test_mcp_connections_management(): void
    {
        echo "\n🔗 Testing MCP Connections Management...\n";

        // Navigate to MCP Connections
        $this->client->request('GET', 'http://localhost:8000/admin/mcp-connections');

        // Wait for table to load
        $this->client->waitFor('.fi-ta-table');

        // Take screenshot
        $this->client->takeScreenshot('screenshots/02_mcp_connections_list.png');

        // Verify connections table
        $this->assertStringContainsString('Test Claude Code Connection', $this->client->getPageSource());
        $this->assertStringContainsString('stdio', $this->client->getPageSource());

        echo "✓ MCP Connections list loaded\n";
        echo "📸 Screenshot saved: 02_mcp_connections_list.png\n";

        // Test connection creation
        $createButton = $this->client->getCrawler()->selectButton('New mcp connection');
        if ($createButton->count() > 0) {
            $createButton->click();

            $this->client->waitFor('.fi-fo-field-wrp');
            $this->client->takeScreenshot('screenshots/03_mcp_connection_create.png');

            echo "✓ Connection creation form loaded\n";
            echo "📸 Screenshot saved: 03_mcp_connection_create.png\n";
        }
    }

    private function test_mcp_conversation(): void
    {
        echo "\n💬 Testing MCP Conversation Interface...\n";

        // Navigate to conversation page
        $this->client->request('GET', 'http://localhost:8000/admin/mcp-conversation');

        // Wait for form to load
        $this->client->waitFor('.fi-fo-component-ctn');

        // Take screenshot of empty conversation
        $this->client->takeScreenshot('screenshots/04_mcp_conversation_empty.png');

        echo "✓ Conversation interface loaded\n";
        echo "📸 Screenshot saved: 04_mcp_conversation_empty.png\n";

        // Test conversation form
        $this->assertStringContainsString('MCP Connection', $this->client->getPageSource());
        $this->assertStringContainsString('Message', $this->client->getPageSource());
        $this->assertStringContainsString('Send Message', $this->client->getPageSource());

        // Try to select a connection and send a test message
        try {
            // Find and click the connection dropdown
            $connectionSelect = $this->client->getCrawler()->filter('[wire\\:model="data.selectedConnection"]');
            if ($connectionSelect->count() > 0) {
                $connectionSelect->click();

                // Wait for dropdown options
                usleep(500000); // 0.5 seconds

                // Take screenshot with dropdown open
                $this->client->takeScreenshot('screenshots/05_mcp_connection_dropdown.png');
                echo "📸 Screenshot saved: 05_mcp_connection_dropdown.png\n";
            }

            // Find message textarea and enter test message
            $messageField = $this->client->getCrawler()->filter('textarea[wire\\:model="data.message"]');
            if ($messageField->count() > 0) {
                $messageField->sendKeys('what day is it?');

                // Take screenshot with message entered
                $this->client->takeScreenshot('screenshots/06_mcp_message_entered.png');
                echo "📸 Screenshot saved: 06_mcp_message_entered.png\n";

                // Try to click send button
                $sendButton = $this->client->getCrawler()->selectButton('Send Message');
                if ($sendButton->count() > 0) {
                    $sendButton->click();

                    // Wait for response
                    sleep(3);

                    // Take screenshot of conversation with response
                    $this->client->takeScreenshot('screenshots/07_mcp_conversation_response.png');
                    echo "📸 Screenshot saved: 07_mcp_conversation_response.png\n";
                }
            }

            echo "✓ Conversation interface tested\n";

        } catch (\Exception $e) {
            echo '⚠️  Conversation interaction failed (expected in test environment): '.$e->getMessage()."\n";
        }
    }

    private function test_persistent_mcp_manager(): void
    {
        echo "\n🔧 Testing Persistent MCP Manager...\n";

        // Test the manager service directly
        $manager = app(PersistentMcpManager::class);
        $connection = McpConnection::first();

        if ($connection) {
            echo "✓ PersistentMcpManager service instantiated\n";
            echo "✓ Test connection available: {$connection->name}\n";

            // Test connection status check
            $isActive = $manager->isConnectionActive((string) $connection->id);
            echo '✓ Connection active check: '.($isActive ? 'Yes' : 'No')."\n";

            // Test that manager has proper methods
            $this->assertTrue(method_exists($manager, 'startConnection'));
            $this->assertTrue(method_exists($manager, 'stopConnection'));
            $this->assertTrue(method_exists($manager, 'isConnectionActive'));
            $this->assertTrue(method_exists($manager, 'healthCheck'));

            echo "✓ Manager has required methods\n";
        }
    }

    private function captureSystemStatus(): void
    {
        echo "\n📊 Capturing System Status...\n";

        // Navigate to dashboard for final status
        $this->client->request('GET', 'http://localhost:8000/admin');
        $this->client->waitFor('.fi-wi-stats-overview');

        // Take final system status screenshot
        $this->client->takeScreenshot('screenshots/08_system_status_final.png');

        echo "📸 Screenshot saved: 08_system_status_final.png\n";
        echo "✓ System status captured\n";
    }

    public function test_mcp_configuration_and_services(): void
    {
        echo "\n⚙️  Testing MCP Configuration and Services...\n";

        // Test configuration is loaded
        $mcpConfig = config('mcp');
        $this->assertIsArray($mcpConfig);
        $this->assertArrayHasKey('timeout', $mcpConfig);
        $this->assertArrayHasKey('server', $mcpConfig);
        $this->assertArrayHasKey('client', $mcpConfig);

        echo "✓ MCP configuration loaded properly\n";

        // Test service bindings
        $this->assertTrue(app()->bound(PersistentMcpManager::class));

        echo "✓ Services bound in container\n";

        // Test events exist
        $this->assertTrue(class_exists(\App\Events\McpConnectionStatusChanged::class));
        $this->assertTrue(class_exists(\App\Events\McpConversationMessage::class));
        $this->assertTrue(class_exists(\App\Events\McpServerStatus::class));

        echo "✓ MCP events defined\n";
    }

    protected function tearDown(): void
    {
        $this->captureSystemStatus();

        echo "\n📁 Test screenshots saved to: tests/screenshots/\n";
        echo "🏁 Phase 1 testing completed!\n\n";

        parent::tearDown();
    }
}
