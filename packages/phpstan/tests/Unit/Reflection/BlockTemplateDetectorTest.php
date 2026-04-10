<?php

declare(strict_types=1);

namespace Blockstudio\PHPStan\Tests\Unit\Reflection;

use Blockstudio\PHPStan\Reflection\BlockTemplateDetector;
use Blockstudio\PHPStan\Schema\ProjectScanner;
use PHPUnit\Framework\TestCase;

final class BlockTemplateDetectorTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/bs-detector-test-' . uniqid();
        mkdir($this->tempDir . '/blockstudio/hero', 0777, true);
        file_put_contents(
            $this->tempDir . '/blockstudio/hero/block.json',
            json_encode(['name' => 'mytheme/hero'])
        );
        file_put_contents(
            $this->tempDir . '/blockstudio/hero/index.php',
            '<?php echo $a["title"];'
        );
    }

    protected function tearDown(): void
    {
        @unlink($this->tempDir . '/blockstudio/hero/block.json');
        @unlink($this->tempDir . '/blockstudio/hero/index.php');
        @rmdir($this->tempDir . '/blockstudio/hero');
        @rmdir($this->tempDir . '/blockstudio');
        @rmdir($this->tempDir);
    }

    public function test_detects_template_file_next_to_block_json(): void
    {
        $detector = new BlockTemplateDetector(new ProjectScanner($this->tempDir));
        $blockJson = $detector->getBlockJsonForTemplate(
            $this->tempDir . '/blockstudio/hero/index.php'
        );

        $this->assertNotNull($blockJson);
        $this->assertStringEndsWith('blockstudio/hero/block.json', $blockJson);
    }

    public function test_returns_null_for_non_template_file(): void
    {
        $detector = new BlockTemplateDetector(new ProjectScanner($this->tempDir));
        $blockJson = $detector->getBlockJsonForTemplate('/some/random/file.php');

        $this->assertNull($blockJson);
    }

    public function test_returns_null_for_non_php_file(): void
    {
        $detector = new BlockTemplateDetector(new ProjectScanner($this->tempDir));
        $blockJson = $detector->getBlockJsonForTemplate(
            $this->tempDir . '/blockstudio/hero/block.json'
        );

        $this->assertNull($blockJson);
    }

    public function test_caches_results_across_calls(): void
    {
        $detector = new BlockTemplateDetector(new ProjectScanner($this->tempDir));
        $path = $this->tempDir . '/blockstudio/hero/index.php';

        $first = $detector->getBlockJsonForTemplate($path);
        $second = $detector->getBlockJsonForTemplate($path);

        $this->assertSame($first, $second);
    }
}
