<?php

declare(strict_types=1);

namespace Blockstudio\PHPStan\Tests\Unit\Schema;

use Blockstudio\PHPStan\Schema\DbSchemaReader;
use PHPStan\Type\Constant\ConstantArrayType;
use PHPStan\Type\VerbosityLevel;
use PHPUnit\Framework\TestCase;

final class DbSchemaReaderTest extends TestCase
{
    private DbSchemaReader $reader;
    private string $dbDir;

    protected function setUp(): void
    {
        $this->reader = new DbSchemaReader();
        $this->dbDir = __DIR__ . '/../../data/db';
    }

    public function test_all_field_types_resolve_to_correct_php_types(): void
    {
        $type = $this->reader->getRecordType($this->dbDir . '/all-types.php');

        $this->assertNotNull($type);
        $this->assertInstanceOf(ConstantArrayType::class, $type);

        $description = $type->describe(VerbosityLevel::precise());
        $this->assertStringContainsString('id', $description);
        $this->assertStringContainsString('name', $description);
        $this->assertStringContainsString('count', $description);
        $this->assertStringContainsString('active', $description);
        $this->assertStringContainsString('metadata', $description);
        $this->assertStringContainsString('options', $description);
    }

    public function test_id_field_is_always_int(): void
    {
        $data = $this->reader->load($this->dbDir . '/all-types.php');

        $this->assertNotNull($data);
        $type = $data['type'];
        $this->assertNotNull($type);
        $description = $type->describe(VerbosityLevel::precise());
        $this->assertMatchesRegularExpression('/id\??\s*:\s*int/', $description);
    }

    public function test_required_string_field_is_non_nullable(): void
    {
        $type = $this->reader->getRecordType($this->dbDir . '/all-types.php');
        $this->assertNotNull($type);

        $description = $type->describe(VerbosityLevel::precise());
        // 'name' is required, should be just 'string' not 'string|null'
        $this->assertMatchesRegularExpression("/name:\s*string/", $description);
    }

    public function test_optional_field_is_nullable(): void
    {
        $type = $this->reader->getRecordType($this->dbDir . '/all-types.php');
        $this->assertNotNull($type);

        $description = $type->describe(VerbosityLevel::precise());
        // 'optional_str' is not required, should be 'string|null'
        $this->assertMatchesRegularExpression("/optional_str\??:\s*string\|null/", $description);
    }

    public function test_number_field_is_int_or_float(): void
    {
        $type = $this->reader->getRecordType($this->dbDir . '/all-types.php');
        $this->assertNotNull($type);

        $description = $type->describe(VerbosityLevel::precise());
        $this->assertMatchesRegularExpression("/count:\s*float\|int/", $description);
    }

    public function test_boolean_field_is_bool(): void
    {
        $type = $this->reader->getRecordType($this->dbDir . '/all-types.php');
        $this->assertNotNull($type);

        $description = $type->describe(VerbosityLevel::precise());
        $this->assertMatchesRegularExpression("/active:\s*bool/", $description);
    }

    public function test_no_fields_returns_type_with_only_id(): void
    {
        $type = $this->reader->getRecordType($this->dbDir . '/no-fields.php');

        $this->assertNotNull($type);
        $description = $type->describe(VerbosityLevel::precise());
        $this->assertStringContainsString('id', $description);
    }

    public function test_syntax_error_returns_null(): void
    {
        $type = $this->reader->getRecordType($this->dbDir . '/syntax-error.php');
        $this->assertNull($type);
    }

    public function test_missing_file_returns_null(): void
    {
        $type = $this->reader->getRecordType($this->dbDir . '/nonexistent.php');
        $this->assertNull($type);
    }

    public function test_load_returns_fields_array(): void
    {
        $data = $this->reader->load($this->dbDir . '/all-types.php');

        $this->assertIsArray($data);
        $this->assertArrayHasKey('fields', $data);
        $this->assertCount(6, $data['fields']);
    }

    public function test_caching_returns_same_data_on_repeat_calls(): void
    {
        $first = $this->reader->load($this->dbDir . '/all-types.php');
        $second = $this->reader->load($this->dbDir . '/all-types.php');

        $this->assertSame($first, $second);
    }

    public function test_builder_schema_resolves_to_typed_record(): void
    {
        $type = $this->reader->getRecordType($this->dbDir . '/builder.php');

        $this->assertNotNull($type);
        $description = $type->describe(VerbosityLevel::precise());
        $this->assertMatchesRegularExpression('/email:\s*string/', $description);
        $this->assertMatchesRegularExpression('/count\??:\s*int\|null/', $description);
        $this->assertMatchesRegularExpression('/active:\s*bool/', $description);
        $this->assertMatchesRegularExpression('/notes\??:\s*string\|null/', $description);
    }

    public function test_multi_builder_schema_can_select_named_schema(): void
    {
        $type = $this->reader->getRecordType($this->dbDir . '/multi-builder.php', 'subscribers');

        $this->assertNotNull($type);
        $description = $type->describe(VerbosityLevel::precise());
        $this->assertMatchesRegularExpression('/email:\s*string/', $description);
        $this->assertMatchesRegularExpression('/active:\s*bool/', $description);
    }
}
