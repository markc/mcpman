<?php

namespace Tests\Browser;

use Symfony\Component\Panther\Client;
use Symfony\Component\Panther\PantherTestCase;

class SvgAnalysisTest extends PantherTestCase
{
    private Client $client;

    protected function setUp(): void
    {
        parent::setUp();
        $this->client = static::createPantherClient([
            'browser' => static::FIREFOX,
            'external_base_uri' => 'http://127.0.0.1:8000',
        ]);
    }

    public function test_analyze_svg_images_on_mcp_conversation(): void
    {
        // Navigate directly to the MCP conversation page (auto-login will handle authentication)
        $crawler = $this->client->request('GET', '/admin/mcp-conversation');

        // Wait for page to load
        $this->client->waitFor('body');

        // Find all SVG elements
        $svgElements = $crawler->filter('svg');

        echo "\n=== SVG Analysis Report ===\n";
        echo 'Found '.count($svgElements)." SVG elements on the page\n\n";

        foreach ($svgElements as $index => $svgElement) {
            echo 'SVG Element #'.($index + 1).":\n";

            // Get SVG attributes
            $width = $svgElement->getAttribute('width') ?: 'not set';
            $height = $svgElement->getAttribute('height') ?: 'not set';
            $viewBox = $svgElement->getAttribute('viewBox') ?: 'not set';
            $class = $svgElement->getAttribute('class') ?: 'not set';

            echo "  - Width: $width\n";
            echo "  - Height: $height\n";
            echo "  - ViewBox: $viewBox\n";
            echo "  - Class: $class\n";

            // Get computed styles using JavaScript
            $computedStyles = $this->client->executeScript("
                const element = document.querySelectorAll('svg')[".$index.'];
                const styles = window.getComputedStyle(element);
                return {
                    width: styles.width,
                    height: styles.height,
                    display: styles.display,
                    position: styles.position,
                    transform: styles.transform,
                    maxWidth: styles.maxWidth,
                    maxHeight: styles.maxHeight
                };
            ');

            echo '  - Computed Width: '.$computedStyles['width']."\n";
            echo '  - Computed Height: '.$computedStyles['height']."\n";
            echo '  - Display: '.$computedStyles['display']."\n";
            echo '  - Position: '.$computedStyles['position']."\n";
            echo '  - Transform: '.$computedStyles['transform']."\n";
            echo '  - Max Width: '.$computedStyles['maxWidth']."\n";
            echo '  - Max Height: '.$computedStyles['maxHeight']."\n";

            // Get parent container info
            $parentInfo = $this->client->executeScript("
                const element = document.querySelectorAll('svg')[".$index.'];
                const parent = element.parentElement;
                const parentStyles = window.getComputedStyle(parent);
                return {
                    tagName: parent.tagName,
                    className: parent.className,
                    width: parentStyles.width,
                    height: parentStyles.height,
                    overflow: parentStyles.overflow
                };
            ');

            echo '  - Parent: '.$parentInfo['tagName'].' (class: '.$parentInfo['className'].")\n";
            echo '  - Parent Width: '.$parentInfo['width']."\n";
            echo '  - Parent Height: '.$parentInfo['height']."\n";
            echo '  - Parent Overflow: '.$parentInfo['overflow']."\n";

            echo "\n";
        }

        // Check for any CSS rules that might affect SVG sizing
        $cssRules = $this->client->executeScript("
            const sheets = Array.from(document.styleSheets);
            const svgRules = [];
            
            sheets.forEach(sheet => {
                try {
                    const rules = Array.from(sheet.cssRules || sheet.rules || []);
                    rules.forEach(rule => {
                        if (rule.selectorText && rule.selectorText.includes('svg')) {
                            svgRules.push({
                                selector: rule.selectorText,
                                cssText: rule.cssText
                            });
                        }
                    });
                } catch (e) {
                    // Cross-origin stylesheets may throw errors
                }
            });
            
            return svgRules;
        ");

        echo "=== Relevant CSS Rules ===\n";
        foreach ($cssRules as $rule) {
            echo 'Selector: '.$rule['selector']."\n";
            echo 'CSS: '.$rule['cssText']."\n\n";
        }

        // Take a screenshot for visual analysis
        $this->client->takeScreenshot('/tmp/mcp-dashboard-screenshot.png');
        echo "Screenshot saved to: /tmp/mcp-dashboard-screenshot.png\n";

        // This test always passes - it's for analysis purposes
        $this->assertTrue(true);
    }

    protected function tearDown(): void
    {
        $this->client->quit();
        parent::tearDown();
    }
}
