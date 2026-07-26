<?php

declare(strict_types=1);

namespace Blockstudio\PHPStan\Tests\Unit\Theme;

use Blockstudio\PHPStan\Theme\ProjectScanner;
use PHPUnit\Framework\TestCase;

final class ProjectScannerTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/blockstudio-theme-scanner-' . uniqid();
        mkdir($this->root . '/blocks/card', 0777, true);
        mkdir($this->root . '/ignored', 0777, true);
        mkdir($this->root . '/vendor/package', 0777, true);
        file_put_contents($this->root . '/style.css', '/* theme */');
        file_put_contents($this->root . '/blocks/card/block.json', '{"name":"example/card"}');
        file_put_contents($this->root . '/blocks/card/index.php', '<?php');
        file_put_contents($this->root . '/ignored/index.php', '<?php');
        file_put_contents($this->root . '/vendor/package/index.php', '<?php');
        $this->root = realpath($this->root) ?: $this->root;
    }

    protected function tearDown(): void
    {
        $this->remove($this->root);
    }

    public function test_scans_ordinary_roots_with_stable_exclusions(): void
    {
        $scanner = new ProjectScanner(
            $this->root,
            [$this->root, $this->root],
            ['ignored/**']
        );

        $this->assertSame(
            [
                $this->root . '/blocks/card/block.json',
                $this->root . '/blocks/card/index.php',
                $this->root . '/style.css',
            ],
            $scanner->files()
        );
        $this->assertSame(
            [$this->root . '/blocks/card/block.json'],
            $scanner->filesNamed('block.json')
        );
        $this->assertSame(
            'blocks/card/index.php',
            $scanner->relativePath($this->root . '/blocks/card/index.php')
        );
    }

    public function test_generated_output_is_excluded_at_any_depth(): void
    {
        mkdir($this->root . '/blocks/card/_dist/modules/vendored', 0777, true);
        mkdir($this->root . '/blocks/card/dist', 0777, true);
        mkdir($this->root . '/blocks/card/distinct', 0777, true);
        file_put_contents($this->root . '/blocks/card/_dist/bundle.js', '');
        file_put_contents($this->root . '/blocks/card/_dist/modules/vendored/lib.js', '');
        file_put_contents($this->root . '/blocks/card/dist/bundle.js', '');
        file_put_contents($this->root . '/blocks/card/distinct/keep.php', '<?php');

        $scanner = new ProjectScanner($this->root, [$this->root]);

        $this->assertSame(
            [
                $this->root . '/blocks/card/block.json',
                $this->root . '/blocks/card/distinct/keep.php',
                $this->root . '/blocks/card/index.php',
                $this->root . '/ignored/index.php',
                $this->root . '/style.css',
            ],
            $scanner->files()
        );
    }

    public function test_a_multi_segment_pattern_stays_anchored(): void
    {
        mkdir($this->root . '/assets/sites/divine/docs', 0777, true);
        mkdir($this->root . '/blocks/card/assets/sites/divine/docs', 0777, true);
        file_put_contents($this->root . '/assets/sites/divine/docs/dropped.md', '');
        file_put_contents($this->root . '/blocks/card/assets/sites/divine/docs/keep.md', '');

        $scanner = new ProjectScanner(
            $this->root,
            [$this->root],
            ['assets/sites/*/docs/**']
        );

        $this->assertSame(
            [
                $this->root . '/blocks/card/assets/sites/divine/docs/keep.md',
                $this->root . '/blocks/card/block.json',
                $this->root . '/blocks/card/index.php',
                $this->root . '/ignored/index.php',
                $this->root . '/style.css',
            ],
            $scanner->files()
        );
    }

    public function test_a_single_segment_pattern_excludes_at_any_depth(): void
    {
        mkdir($this->root . '/blocks/card/ignored', 0777, true);
        file_put_contents($this->root . '/blocks/card/ignored/dropped.php', '<?php');

        $scanner = new ProjectScanner($this->root, [$this->root], ['ignored/**']);

        $this->assertSame(
            [
                $this->root . '/blocks/card/block.json',
                $this->root . '/blocks/card/index.php',
                $this->root . '/style.css',
            ],
            $scanner->files()
        );
    }

    public function test_reports_a_bounded_scan_without_writing_state(): void
    {
        $scanner = new ProjectScanner($this->root, [], [], 2);

        $this->assertCount(2, $scanner->files());
        $this->assertTrue($scanner->limitReached());
        $this->assertSame(2, $scanner->scanLimit());
        $this->assertFileDoesNotExist($this->root . '/.phpstan');
        $this->assertFileDoesNotExist($this->root . '/.cache');
    }

    public function test_missing_and_external_roots_are_safe(): void
    {
        $missing = $this->root . '/missing';
        $scanner = new ProjectScanner($this->root, [$missing]);

        $this->assertSame([$missing], $scanner->roots());
        $this->assertSame([], $scanner->files());
        $this->assertNull($scanner->rootForFile($this->root . '/style.css'));
    }

    private function remove(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }

        foreach (scandir($path) ?: [] as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $child = $path . '/' . $item;
            if (is_dir($child)) {
                $this->remove($child);
            } else {
                unlink($child);
            }
        }

        rmdir($path);
    }
}
