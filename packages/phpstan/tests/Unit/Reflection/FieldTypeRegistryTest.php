<?php

declare(strict_types=1);

namespace Blockstudio\PHPStan\Tests\Unit\Reflection;

use Blockstudio\PHPStan\Reflection\FieldTypeRegistry;
use PHPStan\Type\ArrayType;
use PHPStan\Type\BooleanType;
use PHPStan\Type\Constant\ConstantArrayType;
use PHPStan\Type\FloatType;
use PHPStan\Type\IntegerType;
use PHPStan\Type\MixedType;
use PHPStan\Type\StringType;
use PHPStan\Type\UnionType;
use PHPStan\Type\VerbosityLevel;
use PHPUnit\Framework\TestCase;

final class FieldTypeRegistryTest extends TestCase
{
    private FieldTypeRegistry $registry;

    protected function setUp(): void
    {
        $this->registry = new FieldTypeRegistry();
    }

    /**
     * @dataProvider provideStringFieldTypes
     */
    public function test_string_field_types_return_string(string $type): void
    {
        $result = $this->registry->getTypeForField(['type' => $type]);
        $this->assertInstanceOf(StringType::class, $result);
    }

    public static function provideStringFieldTypes(): iterable
    {
        yield 'text' => ['text'];
        yield 'textarea' => ['textarea'];
        yield 'richtext' => ['richtext'];
        yield 'wysiwyg' => ['wysiwyg'];
        yield 'code' => ['code'];
        yield 'date' => ['date'];
        yield 'datetime' => ['datetime'];
        yield 'classes' => ['classes'];
        yield 'html-tag' => ['html-tag'];
        yield 'unit' => ['unit'];
        yield 'gradient' => ['gradient'];
    }

    /**
     * @dataProvider provideNumericFieldTypes
     */
    public function test_numeric_field_types_return_int_or_float(string $type): void
    {
        $result = $this->registry->getTypeForField(['type' => $type]);
        $this->assertInstanceOf(UnionType::class, $result);
        $description = $result->describe(VerbosityLevel::value());
        $this->assertStringContainsString('int', $description);
        $this->assertStringContainsString('float', $description);
    }

    public static function provideNumericFieldTypes(): iterable
    {
        yield 'number' => ['number'];
        yield 'range' => ['range'];
    }

    public function test_toggle_returns_bool(): void
    {
        $result = $this->registry->getTypeForField(['type' => 'toggle']);
        $this->assertInstanceOf(BooleanType::class, $result);
    }

    public function test_select_single_returns_string_or_int(): void
    {
        $result = $this->registry->getTypeForField(['type' => 'select']);
        $this->assertInstanceOf(UnionType::class, $result);
    }

    public function test_select_multiple_returns_array(): void
    {
        $result = $this->registry->getTypeForField(['type' => 'select', 'multiple' => true]);
        $this->assertInstanceOf(ArrayType::class, $result);
    }

    public function test_checkbox_multiple_returns_array(): void
    {
        $result = $this->registry->getTypeForField(['type' => 'checkbox', 'multiple' => true]);
        $this->assertInstanceOf(ArrayType::class, $result);
    }

    public function test_color_returns_array_with_value_and_opacity(): void
    {
        $result = $this->registry->getTypeForField(['type' => 'color']);
        $this->assertInstanceOf(ConstantArrayType::class, $result);
        $description = $result->describe(VerbosityLevel::precise());
        $this->assertStringContainsString('value', $description);
        $this->assertStringContainsString('opacity', $description);
    }

    public function test_link_returns_array_with_href(): void
    {
        $result = $this->registry->getTypeForField(['type' => 'link']);
        $this->assertInstanceOf(ConstantArrayType::class, $result);
        $description = $result->describe(VerbosityLevel::precise());
        $this->assertStringContainsString('href', $description);
        $this->assertStringContainsString('title', $description);
        $this->assertStringContainsString('target', $description);
        $this->assertStringContainsString('opensInNewTab', $description);
    }

    public function test_icon_returns_array_with_set_subset_icon(): void
    {
        $result = $this->registry->getTypeForField(['type' => 'icon']);
        $this->assertInstanceOf(ConstantArrayType::class, $result);
        $description = $result->describe(VerbosityLevel::precise());
        $this->assertStringContainsString('set', $description);
        $this->assertStringContainsString('subSet', $description);
        $this->assertStringContainsString('icon', $description);
    }

    public function test_files_single_returns_file_shape(): void
    {
        $result = $this->registry->getTypeForField(['type' => 'files']);
        $this->assertInstanceOf(ConstantArrayType::class, $result);
        $description = $result->describe(VerbosityLevel::precise());
        $this->assertStringContainsString('id', $description);
        $this->assertStringContainsString('url', $description);
    }

    public function test_files_multiple_returns_array_of_files(): void
    {
        $result = $this->registry->getTypeForField(['type' => 'files', 'multiple' => true]);
        $this->assertInstanceOf(ArrayType::class, $result);
    }

    public function test_attributes_returns_string_to_string_array(): void
    {
        $result = $this->registry->getTypeForField(['type' => 'attributes']);
        $this->assertInstanceOf(ArrayType::class, $result);
    }

    public function test_block_returns_string(): void
    {
        $result = $this->registry->getTypeForField(['type' => 'block']);
        $this->assertInstanceOf(StringType::class, $result);
    }

    public function test_unknown_type_returns_mixed(): void
    {
        $result = $this->registry->getTypeForField(['type' => 'something-unknown']);
        $this->assertInstanceOf(MixedType::class, $result);
    }

    public function test_no_type_defaults_to_text(): void
    {
        $result = $this->registry->getTypeForField([]);
        $this->assertInstanceOf(StringType::class, $result);
    }
}
