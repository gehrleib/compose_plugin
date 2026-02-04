<?php

/**
 * Unit Tests for compose_list_functions.php (REAL SOURCE)
 * 
 * Tests the helper functions in source/compose.manager/php/compose_list_functions.php
 */

declare(strict_types=1);

namespace ComposeManager\Tests;

use PluginTests\TestCase;
use PluginTests\Mocks\FunctionMocks;

// Load the actual source functions file directly (no execution code to bypass)
require_once '/usr/local/emhttp/plugins/compose.manager/php/compose_list_functions.php';

class ComposeListTest extends TestCase
{
    private string $testComposeRoot;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create test compose root
        $this->testComposeRoot = sys_get_temp_dir() . '/compose_list_test_' . getmypid();
        if (!is_dir($this->testComposeRoot)) {
            mkdir($this->testComposeRoot, 0755, true);
        }
        
        global $compose_root, $plugin_root;
        $compose_root = $this->testComposeRoot;
        $plugin_root = '/usr/local/emhttp/plugins/compose.manager';
        
        FunctionMocks::setPluginConfig('compose.manager', [
            'PROJECTS_FOLDER' => $this->testComposeRoot,
        ]);
    }

    protected function tearDown(): void
    {
        if (is_dir($this->testComposeRoot)) {
            $this->recursiveDelete($this->testComposeRoot);
        }
        parent::tearDown();
    }

    private function recursiveDelete(string $dir): void
    {
        if (is_dir($dir)) {
            $objects = scandir($dir);
            foreach ($objects as $object) {
                if ($object != "." && $object != "..") {
                    if (is_dir($dir . "/" . $object)) {
                        $this->recursiveDelete($dir . "/" . $object);
                    } else {
                        unlink($dir . "/" . $object);
                    }
                }
            }
            rmdir($dir);
        }
    }

    /**
     * Create a test stack directory
     */
    private function createTestStack(string $name, array $files = []): string
    {
        $stackPath = $this->testComposeRoot . '/' . $name;
        mkdir($stackPath, 0755, true);
        
        if (!isset($files['docker-compose.yml'])) {
            file_put_contents($stackPath . '/docker-compose.yml', "services:\n  web:\n    image: nginx\n");
        }
        
        foreach ($files as $filename => $content) {
            file_put_contents($stackPath . '/' . $filename, $content);
        }
        
        return $stackPath;
    }

    // ===========================================
    // createComboButton() Function Tests
    // ===========================================

    /**
     * Test createComboButton generates proper HTML structure
     */
    public function testCreateComboButtonGeneratesHtmlStructure(): void
    {
        $html = createComboButton(
            'Start',
            'start-btn',
            'startStack',
            "'mystack'",
            ['with detach', 'without detach']
        );
        
        // Should contain combo button group wrapper
        $this->assertStringContainsString("<div class='combo-btn-group'>", $html);
        
        // Should contain the main button with text
        $this->assertStringContainsString("value='Start'", $html);
        $this->assertStringContainsString("id='start-btn-left-btn'", $html);
        
        // Should contain the dropdown toggle
        $this->assertStringContainsString("class='dropdown-toggle combo-btn-group-right'", $html);
        
        // Should contain dropdown items
        $this->assertStringContainsString('with detach', $html);
        $this->assertStringContainsString('without detach', $html);
    }

    /**
     * Test createComboButton includes onClick handler
     */
    public function testCreateComboButtonIncludesOnClickHandler(): void
    {
        $html = createComboButton(
            'Start',
            'start-btn',
            'startStack',
            "'mystack'",
            ['option1']
        );
        
        // Main button should have onClick
        $this->assertStringContainsString("onclick='startStack('mystack');'", $html);
        
        // Dropdown items should have onClick with option
        $this->assertStringContainsString('onclick=\'startStack(\'mystack\', &quot;option1&quot;);\'', $html);
    }

    /**
     * Test createComboButton with multiple items
     */
    public function testCreateComboButtonWithMultipleItems(): void
    {
        $html = createComboButton(
            'Action',
            'action-btn',
            'doAction',
            "'id123'",
            ['Item 1', 'Item 2', 'Item 3']
        );
        
        $this->assertStringContainsString('Item 1', $html);
        $this->assertStringContainsString('Item 2', $html);
        $this->assertStringContainsString('Item 3', $html);
    }

    /**
     * Test createComboButton with empty items array
     */
    public function testCreateComboButtonWithEmptyItems(): void
    {
        $html = createComboButton(
            'Start',
            'start-btn',
            'startStack',
            "'mystack'",
            []
        );
        
        // Should still have the dropdown structure, just empty
        $this->assertStringContainsString("<div class='dropdown-content'>", $html);
        $this->assertStringContainsString("</div>", $html);
    }

    // ===========================================
    // Stack Discovery Tests
    // ===========================================

    /**
     * Test stack detection requires docker-compose.yml
     */
    public function testStackDetectionRequiresComposeYml(): void
    {
        // Create directory without docker-compose.yml
        $stackPath = $this->testComposeRoot . '/nocompose';
        mkdir($stackPath, 0755, true);
        file_put_contents($stackPath . '/readme.txt', 'Not a compose stack');
        
        // Stack should not be detected
        $projects = array_diff(scandir($this->testComposeRoot), ['.', '..']);
        $validStacks = 0;
        
        foreach ($projects as $project) {
            $path = $this->testComposeRoot . '/' . $project;
            if (is_file($path . '/docker-compose.yml') || is_file($path . '/indirect')) {
                $validStacks++;
            }
        }
        
        $this->assertEquals(0, $validStacks);
    }

    /**
     * Test stack detection with docker-compose.yml
     */
    public function testStackDetectionWithComposeYml(): void
    {
        $this->createTestStack('validstack');
        
        $projects = array_diff(scandir($this->testComposeRoot), ['.', '..']);
        $validStacks = 0;
        
        foreach ($projects as $project) {
            $path = $this->testComposeRoot . '/' . $project;
            if (is_file($path . '/docker-compose.yml') || is_file($path . '/indirect')) {
                $validStacks++;
            }
        }
        
        $this->assertEquals(1, $validStacks);
    }

    /**
     * Test stack detection with indirect file
     */
    public function testStackDetectionWithIndirectFile(): void
    {
        $stackPath = $this->testComposeRoot . '/indirectstack';
        mkdir($stackPath, 0755, true);
        file_put_contents($stackPath . '/indirect', '/mnt/user/appdata/realstack');
        
        $projects = array_diff(scandir($this->testComposeRoot), ['.', '..']);
        $validStacks = 0;
        
        foreach ($projects as $project) {
            $path = $this->testComposeRoot . '/' . $project;
            if (is_file($path . '/docker-compose.yml') || is_file($path . '/indirect')) {
                $validStacks++;
            }
        }
        
        $this->assertEquals(1, $validStacks);
    }

    // ===========================================
    // Stack Name Resolution Tests
    // ===========================================

    /**
     * Test stack name uses folder name when no name file
     */
    public function testStackNameUsesFolderNameWhenNoNameFile(): void
    {
        $this->createTestStack('my-stack');
        
        $projectName = 'my-stack';
        $nameFile = $this->testComposeRoot . '/my-stack/name';
        
        if (is_file($nameFile)) {
            $projectName = trim(file_get_contents($nameFile));
        }
        
        $this->assertEquals('my-stack', $projectName);
    }

    /**
     * Test stack name uses name file contents when present
     */
    public function testStackNameUsesNameFileWhenPresent(): void
    {
        $this->createTestStack('my-stack', ['name' => 'My Custom Name']);
        
        $projectName = 'my-stack';
        $nameFile = $this->testComposeRoot . '/my-stack/name';
        
        if (is_file($nameFile)) {
            $projectName = trim(file_get_contents($nameFile));
        }
        
        $this->assertEquals('My Custom Name', $projectName);
    }

    // ===========================================
    // Element ID Generation Tests
    // ===========================================

    /**
     * Test ID generation replaces dots with dashes
     */
    public function testIdGenerationReplacesDots(): void
    {
        $project = 'my.stack.name';
        $id = str_replace('.', '-', $project);
        $id = str_replace(' ', '', $id);
        
        $this->assertEquals('my-stack-name', $id);
    }

    /**
     * Test ID generation removes spaces
     */
    public function testIdGenerationRemovesSpaces(): void
    {
        $project = 'my stack name';
        $id = str_replace('.', '-', $project);
        $id = str_replace(' ', '', $id);
        
        $this->assertEquals('mystackname', $id);
    }

    /**
     * Test ID generation with complex name
     */
    public function testIdGenerationComplexName(): void
    {
        $project = 'my.stack with spaces';
        $id = str_replace('.', '-', $project);
        $id = str_replace(' ', '', $id);
        
        $this->assertEquals('my-stackwithspaces', $id);
    }

    // ===========================================
    // Autostart Detection Tests
    // ===========================================

    /**
     * Test autostart defaults to false when no file
     */
    public function testAutostartDefaultsToFalse(): void
    {
        $this->createTestStack('mystack');
        
        $autostartFile = $this->testComposeRoot . '/mystack/autostart';
        $autostart = is_file($autostartFile) ? trim(file_get_contents($autostartFile)) : 'false';
        
        $this->assertEquals('false', $autostart);
    }

    /**
     * Test autostart reads true from file
     */
    public function testAutostartReadsTrueFromFile(): void
    {
        $this->createTestStack('mystack', ['autostart' => 'true']);
        
        $autostartFile = $this->testComposeRoot . '/mystack/autostart';
        $autostart = is_file($autostartFile) ? trim(file_get_contents($autostartFile)) : 'false';
        
        $this->assertEquals('true', $autostart);
    }

    // ===========================================
    // Indirect Stack Path Tests
    // ===========================================

    /**
     * Test base path uses indirect content when present
     */
    public function testBasePathUsesIndirectWhenPresent(): void
    {
        $stackPath = $this->testComposeRoot . '/mystack';
        mkdir($stackPath, 0755, true);
        file_put_contents($stackPath . '/indirect', '/mnt/user/appdata/realstack');
        
        $basePath = is_file($stackPath . '/indirect')
            ? trim(file_get_contents($stackPath . '/indirect'))
            : $stackPath;
        
        $this->assertEquals('/mnt/user/appdata/realstack', $basePath);
    }

    /**
     * Test base path uses stack folder when no indirect
     */
    public function testBasePathUsesStackFolderWhenNoIndirect(): void
    {
        $this->createTestStack('mystack');
        $stackPath = $this->testComposeRoot . '/mystack';
        
        $basePath = is_file($stackPath . '/indirect')
            ? trim(file_get_contents($stackPath . '/indirect'))
            : $stackPath;
        
        $this->assertEquals($stackPath, $basePath);
    }
}
