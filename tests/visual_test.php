<?php

require_once __DIR__.'/../vendor/autoload.php';

use Symfony\Component\Panther\PantherTestCase;

// Simple visual test to capture screenshots
echo "🎨 Starting Visual Test for Phase 1 MCP Implementation...\n";

// Create a test client
$client = PantherTestCase::createPantherClient(['browser' => PantherTestCase::FIREFOX]);

try {
    // Ensure directory exists
    @mkdir(__DIR__.'/screenshots', 0755, true);

    echo "📸 Capturing home page...\n";
    $client->request('GET', 'http://localhost:8000');
    $client->takeScreenshot(__DIR__.'/screenshots/01_home_page.png');

    echo "📸 Capturing admin login...\n";
    $client->request('GET', 'http://localhost:8000/admin');
    $client->takeScreenshot(__DIR__.'/screenshots/02_admin_login.png');

    echo "📸 Capturing admin dashboard (after auto-login)...\n";
    // Try to access the dashboard directly (might show login form)
    $client->request('GET', 'http://localhost:8000/admin/login');
    $client->takeScreenshot(__DIR__.'/screenshots/03_admin_login_form.png');

    echo "✅ Screenshots captured successfully!\n";
    echo '📁 Screenshots saved to: '.__DIR__."/screenshots/\n";

} catch (Exception $e) {
    echo '❌ Error capturing screenshots: '.$e->getMessage()."\n";
} finally {
    $client->quit();
}

echo "🏁 Visual test completed!\n";
