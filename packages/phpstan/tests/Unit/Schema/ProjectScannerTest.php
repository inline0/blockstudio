<?php

declare(strict_types=1);

namespace Blockstudio\PHPStan\Tests\Unit\Schema;

use Blockstudio\PHPStan\Schema\ProjectScanner;
use PHPUnit\Framework\TestCase;

final class ProjectScannerTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/bs-scanner-test-' . uniqid();
        mkdir($this->tempDir . '/blockstudio/hero', 0777, true);
        mkdir($this->tempDir . '/blockstudio/card', 0777, true);
        mkdir($this->tempDir . '/blockstudio/nested/deep/widget', 0777, true);
        mkdir($this->tempDir . '/node_modules/some-package', 0777, true);
        mkdir($this->tempDir . '/vendor/some-vendor', 0777, true);

        file_put_contents(
            $this->tempDir . '/blockstudio/hero/block.json',
            json_encode(['name' => 'mytheme/hero'])
        );
        file_put_contents(
            $this->tempDir . '/blockstudio/card/block.json',
            json_encode(['name' => 'mytheme/card'])
        );
        file_put_contents(
            $this->tempDir . '/blockstudio/nested/deep/widget/block.json',
            json_encode(['name' => 'mytheme/widget'])
        );
        file_put_contents(
            $this->tempDir . '/node_modules/some-package/block.json',
            json_encode(['name' => 'should-be-skipped'])
        );
        file_put_contents(
            $this->tempDir . '/vendor/some-vendor/block.json',
            json_encode(['name' => 'should-be-skipped'])
        );
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->tempDir);
    }

    private function removeDir(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }
        $items = scandir($path);
        if ($items === false) {
            return;
        }
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $full = $path . '/' . $item;
            if (is_dir($full)) {
                $this->removeDir($full);
            } else {
                unlink($full);
            }
        }
        rmdir($path);
    }

    public function test_finds_all_block_json_files(): void
    {
        $scanner = new ProjectScanner($this->tempDir);
        $paths = $scanner->getBlockJsonPaths();

        $this->assertCount(3, $paths);
        $this->assertContains($this->tempDir . '/blockstudio/hero/block.json', $paths);
        $this->assertContains($this->tempDir . '/blockstudio/card/block.json', $paths);
    }

    public function test_skips_node_modules(): void
    {
        $scanner = new ProjectScanner($this->tempDir);
        $paths = $scanner->getBlockJsonPaths();

        foreach ($paths as $path) {
            $this->assertStringNotContainsString('node_modules', $path);
        }
    }

    public function test_skips_vendor(): void
    {
        $scanner = new ProjectScanner($this->tempDir);
        $paths = $scanner->getBlockJsonPaths();

        foreach ($paths as $path) {
            $this->assertStringNotContainsString('vendor', $path);
        }
    }

    public function test_walks_into_nested_directories(): void
    {
        $scanner = new ProjectScanner($this->tempDir);
        $paths = $scanner->getBlockJsonPaths();

        $this->assertContains(
            $this->tempDir . '/blockstudio/nested/deep/widget/block.json',
            $paths
        );
    }

    public function test_finds_block_by_name(): void
    {
        $scanner = new ProjectScanner($this->tempDir);
        $path = $scanner->findBlockJsonByName('mytheme/hero');

        $this->assertNotNull($path);
        $this->assertStringEndsWith('blockstudio/hero/block.json', $path);
    }

    public function test_returns_null_for_unknown_block_name(): void
    {
        $scanner = new ProjectScanner($this->tempDir);
        $this->assertNull($scanner->findBlockJsonByName('mytheme/nonexistent'));
    }

    public function test_finds_sibling_block_json_for_template_file(): void
    {
        $scanner = new ProjectScanner($this->tempDir);
        $templatePath = $this->tempDir . '/blockstudio/hero/index.php';
        file_put_contents($templatePath, '<?php');

        $blockJson = $scanner->findSiblingBlockJson($templatePath);

        $this->assertNotNull($blockJson);
        $this->assertStringEndsWith('blockstudio/hero/block.json', $blockJson);
    }

    public function test_returns_null_when_no_sibling_block_json(): void
    {
        $scanner = new ProjectScanner($this->tempDir);
        $standalonePath = $this->tempDir . '/standalone.php';
        file_put_contents($standalonePath, '<?php');

        $this->assertNull($scanner->findSiblingBlockJson($standalonePath));
    }

    public function test_finds_db_php_for_block(): void
    {
        $dbPath = $this->tempDir . '/blockstudio/hero/db.php';
        file_put_contents($dbPath, '<?php return [];');

        $scanner = new ProjectScanner($this->tempDir);
        $found = $scanner->findDbPhpByBlockName('mytheme/hero');

        $this->assertNotNull($found);
        $this->assertSame($dbPath, $found);
    }

    public function test_returns_null_when_block_has_no_db_php(): void
    {
        $scanner = new ProjectScanner($this->tempDir);
        $this->assertNull($scanner->findDbPhpByBlockName('mytheme/card'));
    }
}
