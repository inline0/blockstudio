<?php

declare(strict_types=1);

namespace Blockstudio\PHPStan\Tests\Unit\Schema;

use Blockstudio\PHPStan\Reflection\FieldTypeRegistry;
use Blockstudio\PHPStan\Schema\BlockJsonReader;
use PHPStan\Type\Constant\ConstantArrayType;
use PHPStan\Type\VerbosityLevel;
use PHPUnit\Framework\TestCase;

final class BlockJsonReaderTest extends TestCase
{
    private BlockJsonReader $reader;
    private string $blocksDir;

    protected function setUp(): void
    {
        $this->reader = new BlockJsonReader(new FieldTypeRegistry());
        $this->blocksDir = __DIR__ . '/../../data/blocks';
    }

    public function test_simple_block_returns_shape_with_text_and_number(): void
    {
        $type = $this->reader->getAttributeType($this->blocksDir . '/simple/block.json');

        $this->assertNotNull($type);
        $this->assertInstanceOf(ConstantArrayType::class, $type);
        $description = $type->describe(VerbosityLevel::precise());
        $this->assertStringContainsString('title', $description);
        $this->assertStringContainsString('count', $description);
        $this->assertStringContainsString('string', $description);
    }

    public function test_group_field_flattens_with_underscore_prefix(): void
    {
        $type = $this->reader->getAttributeType($this->blocksDir . '/group/block.json');

        $this->assertNotNull($type);
        $description = $type->describe(VerbosityLevel::precise());
        // Group "cta" with children "text" and "url" becomes "cta_text" and "cta_url"
        $this->assertStringContainsString('cta_text', $description);
        $this->assertStringContainsString('cta_url', $description);
        // The original group key "cta" should NOT be a top-level key
        $this->assertStringNotContainsString("'cta'", $description);
    }

    public function test_repeater_field_creates_list_of_inner_shape(): void
    {
        $type = $this->reader->getAttributeType($this->blocksDir . '/repeater/block.json');

        $this->assertNotNull($type);
        $description = $type->describe(VerbosityLevel::precise());
        $this->assertStringContainsString('items', $description);
        $this->assertStringContainsString('label', $description);
        $this->assertStringContainsString('url', $description);
    }

    public function test_tabs_field_flattens_into_top_level(): void
    {
        $type = $this->reader->getAttributeType($this->blocksDir . '/tabs/block.json');

        $this->assertNotNull($type);
        $description = $type->describe(VerbosityLevel::precise());
        // Tabs flatten to top level (not prefixed with the tabs field id)
        $this->assertStringContainsString('title', $description);
        $this->assertStringContainsString('color', $description);
    }

    public function test_block_without_blockstudio_returns_empty_shape(): void
    {
        $type = $this->reader->getAttributeType($this->blocksDir . '/no-blockstudio/block.json');

        $this->assertNotNull($type);
        $this->assertInstanceOf(ConstantArrayType::class, $type);
    }

    public function test_all_field_types_resolve_correctly(): void
    {
        $type = $this->reader->getAttributeType($this->blocksDir . '/all-types/block.json');

        $this->assertNotNull($type);
        $description = $type->describe(VerbosityLevel::precise());
        $this->assertStringContainsString('text', $description);
        $this->assertStringContainsString('textarea', $description);
        $this->assertStringContainsString('number', $description);
        $this->assertStringContainsString('toggle', $description);
        $this->assertStringContainsString('select', $description);
        $this->assertStringContainsString('color', $description);
        $this->assertStringContainsString('link', $description);
        $this->assertStringContainsString('files', $description);
        $this->assertStringContainsString('icon', $description);
        $this->assertStringContainsString('code', $description);
    }

    public function test_missing_file_returns_null(): void
    {
        $type = $this->reader->getAttributeType($this->blocksDir . '/nonexistent/block.json');
        $this->assertNull($type);
    }

    public function test_invalid_json_returns_null(): void
    {
        $type = $this->reader->getAttributeType($this->blocksDir . '/invalid-json/block.json');
        $this->assertNull($type);
    }

    public function test_load_returns_decoded_data(): void
    {
        $data = $this->reader->load($this->blocksDir . '/simple/block.json');

        $this->assertIsArray($data);
        $this->assertSame('test/simple', $data['name']);
        $this->assertCount(2, $data['blockstudio']['attributes']);
    }

    public function test_caching_returns_same_data_on_repeat_calls(): void
    {
        $first = $this->reader->load($this->blocksDir . '/simple/block.json');
        $second = $this->reader->load($this->blocksDir . '/simple/block.json');

        $this->assertSame($first, $second);
    }
}
