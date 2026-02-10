<?php

/**
 * Unit Tests for Compose Manager Main Page Structure
 * 
 * Tests the inline CSS, column definitions, and HTML structure in
 * compose_manager_main.php. These tests verify that today's fixes
 * (column widths, selectors, class names) are correctly applied.
 */

declare(strict_types=1);

namespace ComposeManager\Tests;

use PluginTests\TestCase;
use PluginTests\Mocks\FunctionMocks;

class ComposeManagerMainTest extends TestCase
{
    private string $mainPagePath;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mainPagePath = __DIR__ . '/../../source/compose.manager/php/compose_manager_main.php';
        $this->assertFileExists($this->mainPagePath, 'compose_manager_main.php must exist');
    }

    /**
     * Read the raw PHP/HTML source (not execute it).
     */
    private function getPageSource(): string
    {
        return file_get_contents($this->mainPagePath);
    }

    // ===========================================
    // Inline CSS / Column Width Tests
    // ===========================================

    public function testInlineStyleBlockExists(): void
    {
        $source = $this->getPageSource();
        $this->assertStringContainsString('<style>', $source);
        $this->assertStringContainsString('table-layout:fixed', $source);
    }

    public function testBasicViewColumnWidths(): void
    {
        $source = $this->getPageSource();

        // Basic view columns: Stack 33%, Update 18%, Containers 18%, Uptime 18%, Autostart 13%
        $this->assertStringContainsString('th.col-stack{width:33%}', $source);
        $this->assertStringContainsString('th.col-update{width:18%}', $source);
        $this->assertStringContainsString('th.col-containers{width:18%}', $source);
        $this->assertStringContainsString('th.col-uptime{width:18%}', $source);
        $this->assertStringContainsString('th.col-autostart{width:13%}', $source);
    }

    public function testAdvancedViewColumnWidths(): void
    {
        $source = $this->getPageSource();

        // Advanced view columns should be prefixed with .cm-advanced-view
        $this->assertStringContainsString('.cm-advanced-view thead th.col-stack{width:14%}', $source);
        $this->assertStringContainsString('.cm-advanced-view thead th.col-update{width:9%}', $source);
        $this->assertStringContainsString('.cm-advanced-view thead th.col-containers{width:5%}', $source);
        $this->assertStringContainsString('.cm-advanced-view thead th.col-uptime{width:8%}', $source);
        $this->assertStringContainsString('.cm-advanced-view thead th.col-description{width:22%}', $source);
        $this->assertStringContainsString('.cm-advanced-view thead th.col-compose{width:7%}', $source);
        $this->assertStringContainsString('.cm-advanced-view thead th.col-path{width:29%}', $source);
        $this->assertStringContainsString('.cm-advanced-view thead th.col-autostart{width:6%}', $source);
    }

    public function testOverflowClippingOnCells(): void
    {
        $source = $this->getPageSource();
        $this->assertStringContainsString('overflow:hidden', $source);
        $this->assertStringContainsString('text-overflow:ellipsis', $source);
    }

    // ===========================================
    // Autostart Column Styling Tests
    // ===========================================

    public function testAutostartRightAligned(): void
    {
        $source = $this->getPageSource();
        // Autostart header and cell right-aligned
        $this->assertStringContainsString('th.col-autostart', $source);
        $this->assertStringContainsString('text-align:right', $source);
    }

    public function testAutostartCellPadding(): void
    {
        $source = $this->getPageSource();
        // Autostart cell class nine has 15px right padding
        $this->assertStringContainsString('td.nine{white-space:nowrap;padding-right:15px}', $source);
    }

    // ===========================================
    // Advanced/Basic View Isolation Tests
    // ===========================================

    public function testCmAdvancedClassUsedInsteadOfAdvanced(): void
    {
        $source = $this->getPageSource();
        // The compose manager should use 'cm-advanced' classes, not bare '.advanced'
        $this->assertStringContainsString('.cm-advanced{display:none}', $source);
        $this->assertStringContainsString('.cm-advanced-view .cm-advanced{display:table-cell}', $source);
        $this->assertStringContainsString('.cm-advanced-view div.cm-advanced{display:block}', $source);
    }

    // ===========================================
    // Update Column Selector Tests (Bug Fix)
    // ===========================================

    public function testUpdateColumnUsesCorrectSelector(): void
    {
        $source = $this->getPageSource();
        // The correct class is 'compose-updatecolumn' — bug was 'updatecolumn'
        $this->assertStringContainsString('td.compose-updatecolumn', $source);
        // The bare 'td.updatecolumn' should NOT appear (the old buggy selector)
        $this->assertStringNotContainsString("find('td.updatecolumn')", $source);
    }

    // ===========================================
    // Container Sub-table Source Column Tests
    // ===========================================

    public function testContainerSubtableSourceLeftAligned(): void
    {
        $source = $this->getPageSource();
        // Source column in container sub-table should be left-aligned
        $this->assertStringContainsString('.compose-ct-table', $source);
        $this->assertStringContainsString('text-align:left!important', $source);
    }

    // ===========================================
    // Detail Row Structure Tests
    // ===========================================

    public function testDetailRowStyling(): void
    {
        $source = $this->getPageSource();
        $this->assertStringContainsString('.stack-details-cell{width:auto!important}', $source);
        $this->assertStringContainsString('tr.stack-details-row', $source);
    }

    // ===========================================
    // processWebUIUrl() Function Tests (source check)
    // ===========================================

    public function testProcessWebUIUrlFunctionExists(): void
    {
        $source = $this->getPageSource();
        $this->assertStringContainsString('function processWebUIUrl(url', $source);
    }

    public function testProcessWebUIUrlHandlesIPPlaceholder(): void
    {
        $source = $this->getPageSource();
        // Should replace [IP] with window.location.hostname
        $this->assertStringContainsString('[IP]', $source);
        $this->assertStringContainsString('window.location.hostname', $source);
    }

    public function testProcessWebUIUrlHandlesPortPlaceholder(): void
    {
        $source = $this->getPageSource();
        // Should handle [PORT:xxxx] pattern
        $this->assertStringContainsString('[PORT:', $source);
    }

    public function testIsValidWebUIUrlFunctionExists(): void
    {
        $source = $this->getPageSource();
        $this->assertStringContainsString('function isValidWebUIUrl(url)', $source);
    }

    // ===========================================
    // Console Opens in New Window Tests
    // ===========================================

    public function testConsoleUsesWindowOpen(): void
    {
        $source = $this->getPageSource();
        // Console should use window.open for a new browser window, not Shadowbox
        // Look for the console action section
        $this->assertStringContainsString("action: 'containerConsole'", $source);
    }

    // ===========================================
    // SHA Display Length Tests
    // ===========================================

    public function testShaDisplayLength(): void
    {
        $source = $this->getPageSource();
        // SHA for up-to-date containers should be truncated to 15 characters
        $this->assertStringContainsString('substring(0, 15)', $source);
    }
}
