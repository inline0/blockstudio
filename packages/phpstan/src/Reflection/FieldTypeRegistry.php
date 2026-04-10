<?php

declare(strict_types=1);

namespace Blockstudio\PHPStan\Reflection;

use PHPStan\Type\ArrayType;
use PHPStan\Type\BooleanType;
use PHPStan\Type\Constant\ConstantArrayTypeBuilder;
use PHPStan\Type\Constant\ConstantStringType;
use PHPStan\Type\FloatType;
use PHPStan\Type\IntegerType;
use PHPStan\Type\MixedType;
use PHPStan\Type\NullType;
use PHPStan\Type\StringType;
use PHPStan\Type\Type;
use PHPStan\Type\TypeCombinator;
use PHPStan\Type\UnionType;

/**
 * Maps Blockstudio field type names to their data shape types.
 *
 * Single source of truth for "what does this field look like in $a".
 */
final class FieldTypeRegistry
{
    /**
     * @param array<string, mixed> $field
     */
    public function getTypeForField(array $field): Type
    {
        $type = (string) ($field['type'] ?? 'text');
        $multiple = !empty($field['multiple']);

        return match ($type) {
            'text', 'textarea', 'richtext', 'wysiwyg', 'code',
            'date', 'datetime', 'classes', 'html-tag', 'unit',
            'gradient' => new StringType(),

            'number', 'range' => TypeCombinator::union(new IntegerType(), new FloatType()),

            'toggle' => new BooleanType(),

            'select', 'radio', 'checkbox' => $multiple
                ? new ArrayType(new IntegerType(), TypeCombinator::union(new StringType(), new IntegerType()))
                : TypeCombinator::union(new StringType(), new IntegerType()),

            'color' => $this->buildShape([
                'value' => new StringType(),
                'opacity' => TypeCombinator::union(new FloatType(), new NullType()),
            ]),

            'link' => $this->buildShape([
                'href' => new StringType(),
                'title' => TypeCombinator::union(new StringType(), new NullType()),
                'target' => TypeCombinator::union(new StringType(), new NullType()),
                'opensInNewTab' => TypeCombinator::union(new BooleanType(), new NullType()),
            ]),

            'icon' => $this->buildShape([
                'set' => new StringType(),
                'subSet' => new StringType(),
                'icon' => new StringType(),
            ]),

            'files' => $multiple
                ? new ArrayType(new IntegerType(), $this->fileShape())
                : $this->fileShape(),

            'attributes' => new ArrayType(new StringType(), new StringType()),

            'block' => new StringType(),

            'message' => new MixedType(),

            default => new MixedType(),
        };
    }

    private function fileShape(): Type
    {
        return $this->buildShape([
            'id' => new IntegerType(),
            'url' => new StringType(),
            'alt' => TypeCombinator::union(new StringType(), new NullType()),
            'mime_type' => TypeCombinator::union(new StringType(), new NullType()),
        ]);
    }

    /**
     * @param array<string, Type> $properties
     */
    private function buildShape(array $properties): Type
    {
        $builder = ConstantArrayTypeBuilder::createEmpty();
        foreach ($properties as $name => $type) {
            $builder->setOffsetValueType(new ConstantStringType($name), $type);
        }
        return $builder->getArray();
    }
}
