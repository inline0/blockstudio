<?php

declare(strict_types=1);

namespace Blockstudio\PHPStan\Tests\Unit\Schema;

use Blockstudio\PHPStan\Schema\BlockstudioJsonReader;
use PHPUnit\Framework\TestCase;

final class BlockstudioJsonReaderTest extends TestCase
{
    private BlockstudioJsonReader $reader;
    private string $tempDir;

    protected function setUp(): void
    {
        $this->reader = new BlockstudioJsonReader();
        $this->tempDir = sys_get_temp_dir() . '/bs-phpstan-test-' . uniqid();
        mkdir($this->tempDir, 0777, true);
    }

    protected function tearDown(): void
    {
        if (is_dir($this->tempDir)) {
            $files = glob($this->tempDir . '/*');
            if ($files !== false) {
                array_map('unlink', $files);
            }
            rmdir($this->tempDir);
        }
    }

    public function test_loads_valid_blockstudio_json(): void
    {
        $path = $this->tempDir . '/blockstudio.json';
        file_put_contents($path, json_encode([
            'tailwind' => ['enabled' => true, 'config' => ''],
        ]));

        $data = $this->reader->load($path);

        $this->assertIsArray($data);
        $this->assertArrayHasKey('tailwind', $data);
        $this->assertTrue($data['tailwind']['enabled']);
    }

    public function test_returns_null_for_missing_file(): void
    {
        $data = $this->reader->load($this->tempDir . '/nonexistent.json');
        $this->assertNull($data);
    }

    public function test_returns_null_for_invalid_json(): void
    {
        $path = $this->tempDir . '/blockstudio.json';
        file_put_contents($path, '{ broken json');

        $data = $this->reader->load($path);
        $this->assertNull($data);
    }

    public function test_caches_data_across_calls(): void
    {
        $path = $this->tempDir . '/blockstudio.json';
        file_put_contents($path, json_encode(['key' => 'value']));

        $first = $this->reader->load($path);
        $second = $this->reader->load($path);

        $this->assertSame($first, $second);
    }
}
