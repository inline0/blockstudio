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
        mkdir($this->tempDir . '/blockstudio/fields/hero', 0777, true);
        mkdir($this->tempDir . '/blockstudio/fields/layout', 0777, true);
        mkdir($this->tempDir . '/config/not-a-field', 0777, true);
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
            $this->tempDir . '/blockstudio/fields/hero/field.json',
            json_encode(['name' => 'mytheme/hero', 'attributes' => [['id' => 'title', 'type' => 'text']]])
        );
        file_put_contents(
            $this->tempDir . '/blockstudio/fields/layout/field.json',
            json_encode(['name' => 'mytheme/layout', 'attributes' => [['id' => 'gap', 'type' => 'number']]])
        );
        file_put_contents(
            $this->tempDir . '/config/not-a-field/field.json',
            json_encode(['name' => 'ignored', 'attributes' => [['id' => 'value', 'type' => 'text']]])
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

    private function writeBlockJson(string $relativePath, string $blockName): void
    {
        $path = $this->tempDir . '/' . $relativePath;
        mkdir(dirname($path), 0777, true);
        file_put_contents($path, json_encode(['name' => $blockName]));
    }

    private function relative(string $path): string
    {
        $root = str_replace('\\', '/', $this->tempDir) . '/';
        $normalized = str_replace('\\', '/', $path);
        return str_starts_with($normalized, $root)
            ? substr($normalized, strlen($root))
            : $normalized;
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

    public function test_analysed_paths_bound_discovery(): void
    {
        $this->writeBlockJson('theme/blocks/hero/block.json', 'demo/hero');
        $this->writeBlockJson('excluded/blockstudio/example/block.json', 'demo/excluded');

        $scanner = new ProjectScanner(
            $this->tempDir,
            [],
            [],
            [$this->tempDir . '/theme']
        );

        $names = array_map(
            fn(string $path): string => $this->relative($path),
            $scanner->getBlockJsonPaths()
        );

        $this->assertSame(['theme/blocks/hero/block.json'], $names);
        $this->assertNull($scanner->findBlockJsonByName('demo/excluded'));
    }

    public function test_relative_analysed_paths_resolve_against_the_working_directory(): void
    {
        $this->writeBlockJson('theme/blocks/hero/block.json', 'demo/hero');
        $this->writeBlockJson('other/block.json', 'demo/other');

        $scanner = new ProjectScanner($this->tempDir, [], [], ['theme']);

        $names = array_map(
            fn(string $path): string => $this->relative($path),
            $scanner->getBlockJsonPaths()
        );

        $this->assertSame(['theme/blocks/hero/block.json'], $names);
    }

    public function test_phpstan_exclude_paths_keep_schemas_out_of_discovery(): void
    {
        $this->writeBlockJson('theme/blocks/hero/block.json', 'demo/hero');
        $this->writeBlockJson('theme/fixtures/broken/block.json', 'demo/broken');

        $scanner = new ProjectScanner(
            $this->tempDir,
            [],
            [],
            [],
            ['analyse' => [$this->tempDir . '/theme/fixtures'], 'analyseAndScan' => []]
        );

        $names = array_map(
            fn(string $path): string => $this->relative($path),
            $scanner->getBlockJsonPaths()
        );

        $this->assertContains('theme/blocks/hero/block.json', $names);
        $this->assertNotContains('theme/fixtures/broken/block.json', $names);
    }

    public function test_plain_list_exclude_paths_are_honoured(): void
    {
        $this->writeBlockJson('theme/blocks/hero/block.json', 'demo/hero');
        $this->writeBlockJson('generated/block.json', 'demo/generated');

        $scanner = new ProjectScanner($this->tempDir, [], [], [], ['generated']);

        $names = array_map(
            fn(string $path): string => $this->relative($path),
            $scanner->getBlockJsonPaths()
        );

        $this->assertContains('theme/blocks/hero/block.json', $names);
        $this->assertNotContains('generated/block.json', $names);
    }

    public function test_finds_all_block_json_files(): void
    {
        $scanner = new ProjectScanner($this->tempDir);
        $paths = $scanner->getBlockJsonPaths();

        $this->assertCount(3, $paths);
        $this->assertContains($this->tempDir . '/blockstudio/hero/block.json', $paths);
        $this->assertContains($this->tempDir . '/blockstudio/card/block.json', $paths);
    }

    public function test_configured_exclusions_keep_out_documents_that_only_share_a_reserved_name(): void
    {
        mkdir($this->tempDir . '/assets/sites/example/schemas', 0777, true);
        file_put_contents(
            $this->tempDir . '/assets/sites/example/schemas/block.json',
            json_encode(['$schema' => 'https://json-schema.org/draft-07/schema#'])
        );

        $unfiltered = new ProjectScanner($this->tempDir);
        $this->assertContains(
            $this->tempDir . '/assets/sites/example/schemas/block.json',
            $unfiltered->getBlockJsonPaths()
        );

        $scanner = new ProjectScanner(
            $this->tempDir,
            [],
            ['assets/sites/*/schemas/**']
        );

        $this->assertNotContains(
            $this->tempDir . '/assets/sites/example/schemas/block.json',
            $scanner->getBlockJsonPaths()
        );
        $this->assertContains(
            $this->tempDir . '/blockstudio/hero/block.json',
            $scanner->getBlockJsonPaths()
        );
    }

    public function test_a_configured_exclusion_applies_at_any_depth(): void
    {
        mkdir($this->tempDir . '/blockstudio/vendored/schemas/deep', 0777, true);
        file_put_contents(
            $this->tempDir . '/blockstudio/vendored/schemas/deep/block.json',
            json_encode(['$schema' => 'https://json-schema.org/draft-07/schema#'])
        );

        $scanner = new ProjectScanner($this->tempDir, [], ['schemas/**']);

        $this->assertNotContains(
            $this->tempDir . '/blockstudio/vendored/schemas/deep/block.json',
            $scanner->getBlockJsonPaths()
        );
        $this->assertContains(
            $this->tempDir . '/blockstudio/card/block.json',
            $scanner->getBlockJsonPaths()
        );
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

    public function test_finds_and_deduplicates_field_json_files(): void
    {
        $scanner = new ProjectScanner($this->tempDir);

        $this->assertSame([
            $this->tempDir . '/blockstudio/fields/hero/field.json',
            $this->tempDir . '/blockstudio/fields/layout/field.json',
        ], $scanner->getFieldJsonPaths());
        $this->assertSame(
            [$this->tempDir . '/blockstudio/fields/hero/field.json'],
            $scanner->findFieldJsonPathsByName('mytheme/hero')
        );
        $this->assertSame([], $scanner->findFieldJsonPathsByName('ignored'));
    }

    public function test_returns_all_ambiguous_field_definitions_in_stable_order(): void
    {
        mkdir($this->tempDir . '/blockstudio/fields/duplicate', 0777, true);
        file_put_contents(
            $this->tempDir . '/blockstudio/fields/duplicate/field.json',
            json_encode(['name' => 'mytheme/hero', 'attributes' => [['id' => 'copy', 'type' => 'text']]])
        );

        $scanner = new ProjectScanner($this->tempDir);

        $this->assertSame([
            $this->tempDir . '/blockstudio/fields/duplicate/field.json',
            $this->tempDir . '/blockstudio/fields/hero/field.json',
        ], $scanner->findFieldJsonPathsByName('mytheme/hero'));
    }

    public function test_additional_scan_root_indexes_field_json_directly(): void
    {
        $library = $this->tempDir . '/external-library/reusable';
        mkdir($library, 0777, true);
        $path = $library . '/field.json';
        file_put_contents(
            $path,
            json_encode(['name' => 'vendor/card', 'attributes' => [['id' => 'title', 'type' => 'text']]])
        );

        $scanner = new ProjectScanner($this->tempDir, [$library]);

        $this->assertSame([$path], $scanner->findFieldJsonPathsByName('vendor/card'));
        $this->assertContains($path, $scanner->getFieldJsonPaths());
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
