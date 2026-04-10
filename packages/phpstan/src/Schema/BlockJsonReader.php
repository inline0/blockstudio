<?php

declare(strict_types=1);

namespace Blockstudio\PHPStan\Schema;

use Blockstudio\PHPStan\Reflection\FieldTypeRegistry;
use PHPStan\Type\ArrayType;
use PHPStan\Type\Constant\ConstantArrayTypeBuilder;
use PHPStan\Type\Constant\ConstantStringType;
use PHPStan\Type\IntegerType;
use PHPStan\Type\Type;

/**
 * Reads a block.json file and computes the array shape type for $a in templates.
 */
final class BlockJsonReader
{
    /** @var array<string, array{mtime: int, type: Type, raw: array<string, mixed>}> */
    private array $cache = [];

    public function __construct(private readonly FieldTypeRegistry $registry) {}

    /**
     * Get the attribute shape type for a block.json file.
     * Returns null if the file doesn't exist or is invalid.
     */
    public function getAttributeType(string $blockJsonPath): ?Type
    {
        $data = $this->load($blockJsonPath);
        if ($data === null) {
            return null;
        }

        $attributes = $data['blockstudio']['attributes'] ?? [];
        if (!is_array($attributes)) {
            return null;
        }

        return $this->buildShape($attributes);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function load(string $path): ?array
    {
        if (!file_exists($path)) {
            return null;
        }

        $mtime = (int) filemtime($path);
        if (isset($this->cache[$path]) && $this->cache[$path]['mtime'] === $mtime) {
            return $this->cache[$path]['raw'];
        }

        $content = file_get_contents($path);
        if ($content === false) {
            return null;
        }

        $decoded = json_decode($content, true);
        if (!is_array($decoded)) {
            return null;
        }

        $this->cache[$path] = [
            'mtime' => $mtime,
            'type' => $this->buildShape($decoded['blockstudio']['attributes'] ?? []),
            'raw' => $decoded,
        ];

        return $decoded;
    }

    /**
     * Build a constant array type from a list of attribute definitions.
     * Handles group, repeater, tabs flattening recursively.
     *
     * @param array<int, mixed> $attributes
     */
    private function buildShape(array $attributes): Type
    {
        $builder = ConstantArrayTypeBuilder::createEmpty();

        foreach ($attributes as $field) {
            if (!is_array($field)) {
                continue;
            }

            $this->addFieldToShape($builder, $field, '');
        }

        return $builder->getArray();
    }

    /**
     * @param array<string, mixed> $field
     */
    private function addFieldToShape(
        ConstantArrayTypeBuilder $builder,
        array $field,
        string $prefix
    ): void {
        $id = (string) ($field['id'] ?? $field['key'] ?? '');
        if ($id === '') {
            return;
        }

        $type = (string) ($field['type'] ?? 'text');
        $key = $prefix === '' ? $id : $prefix . '_' . $id;

        if ($type === 'group' && isset($field['attributes']) && is_array($field['attributes'])) {
            foreach ($field['attributes'] as $child) {
                if (is_array($child)) {
                    $this->addFieldToShape($builder, $child, $key);
                }
            }
            return;
        }

        if ($type === 'tabs' && isset($field['tabs']) && is_array($field['tabs'])) {
            foreach ($field['tabs'] as $tab) {
                if (!is_array($tab) || !isset($tab['attributes']) || !is_array($tab['attributes'])) {
                    continue;
                }
                foreach ($tab['attributes'] as $child) {
                    if (is_array($child)) {
                        $this->addFieldToShape($builder, $child, $prefix);
                    }
                }
            }
            return;
        }

        if ($type === 'repeater' && isset($field['attributes']) && is_array($field['attributes'])) {
            $childBuilder = ConstantArrayTypeBuilder::createEmpty();
            foreach ($field['attributes'] as $child) {
                if (is_array($child)) {
                    $this->addFieldToShape($childBuilder, $child, '');
                }
            }
            $repeaterType = new ArrayType(new IntegerType(), $childBuilder->getArray());
            $builder->setOffsetValueType(new ConstantStringType($key), $repeaterType);
            return;
        }

        $fieldType = $this->registry->getTypeForField($field);
        $builder->setOffsetValueType(new ConstantStringType($key), $fieldType);
    }
}
