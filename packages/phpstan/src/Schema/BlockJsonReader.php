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
    /** @var array<string, array{mtime: int, raw: array<string, mixed>}> */
    private array $cache = [];

    /**
     * @var array<string, array{
     *     mtime: int,
     *     result: array{
     *         attributes: array<int, mixed>,
     *         issues: list<array{
     *             type: 'missing'|'ambiguous'|'cycle'|'invalid',
     *             name: string,
     *             paths: list<string>
     *         }>
     *     }
     * }>
     */
    private array $attributeCache = [];

    public function __construct(
        private readonly FieldTypeRegistry $registry,
        private readonly CustomFieldResolver $customFields
    ) {}

    /**
     * Get the attribute shape type for a block.json file.
     * Returns null if the file doesn't exist or is invalid.
     */
    public function getAttributeType(string $blockJsonPath): ?Type
    {
        $result = $this->getResolvedAttributes($blockJsonPath);
        if ($result === null) {
            return null;
        }

        return $this->buildShape($result['attributes']);
    }

    /**
     * Return flattened template keys after custom fields are expanded.
     *
     * @return list<string>|null
     */
    public function getAttributeKeys(string $blockJsonPath): ?array
    {
        $result = $this->getResolvedAttributes($blockJsonPath);
        if ($result === null) {
            return null;
        }

        return $this->collectKeys($result['attributes']);
    }

    /**
     * @return list<array{
     *     type: 'missing'|'ambiguous'|'cycle'|'invalid',
     *     name: string,
     *     paths: list<string>
     * }>
     */
    public function getCustomFieldIssues(string $blockJsonPath): array
    {
        $result = $this->getResolvedAttributes($blockJsonPath);
        return $result['issues'] ?? [];
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
            'raw' => $decoded,
        ];

        return $decoded;
    }

    /**
     * @return array{
     *     attributes: array<int, mixed>,
     *     issues: list<array{
     *         type: 'missing'|'ambiguous'|'cycle'|'invalid',
     *         name: string,
     *         paths: list<string>
     *     }>
     * }|null
     */
    private function getResolvedAttributes(string $blockJsonPath): ?array
    {
        $data = $this->load($blockJsonPath);
        if ($data === null) {
            return null;
        }

        $attributes = $data['blockstudio']['attributes'] ?? [];
        if (!is_array($attributes)) {
            return null;
        }

        $mtime = (int) filemtime($blockJsonPath);
        if (
            isset($this->attributeCache[$blockJsonPath])
            && $this->attributeCache[$blockJsonPath]['mtime'] === $mtime
        ) {
            return $this->attributeCache[$blockJsonPath]['result'];
        }

        $result = $this->customFields->resolve($attributes);
        $this->attributeCache[$blockJsonPath] = [
            'mtime' => $mtime,
            'result' => $result,
        ];

        return $result;
    }

    /**
     * @param array<int, mixed> $attributes
     * @return list<string>
     */
    private function collectKeys(array $attributes, string $prefix = ''): array
    {
        $keys = [];

        foreach ($attributes as $field) {
            if (!is_array($field)) {
                continue;
            }

            $type = is_string($field['type'] ?? null) ? $field['type'] : 'text';

            if ($type === 'tabs' && is_array($field['tabs'] ?? null)) {
                foreach ($field['tabs'] as $tab) {
                    if (is_array($tab) && is_array($tab['attributes'] ?? null)) {
                        $keys = array_merge(
                            $keys,
                            $this->collectKeys($tab['attributes'], $prefix)
                        );
                    }
                }
                continue;
            }

            $id = is_string($field['id'] ?? null)
                ? $field['id']
                : (is_string($field['key'] ?? null) ? $field['key'] : '');

            if ($type === 'group' && is_array($field['attributes'] ?? null)) {
                $groupPrefix = $id === ''
                    ? $prefix
                    : ($prefix === '' ? $id : $prefix . '_' . $id);
                $keys = array_merge(
                    $keys,
                    $this->collectKeys($field['attributes'], $groupPrefix)
                );
                continue;
            }

            if ($id === '' || $type === 'message') {
                continue;
            }

            $keys[] = $prefix === '' ? $id : $prefix . '_' . $id;
        }

        return array_values(array_unique($keys));
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
        $type = (string) ($field['type'] ?? 'text');

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

        $id = (string) ($field['id'] ?? $field['key'] ?? '');

        if ($type === 'group' && isset($field['attributes']) && is_array($field['attributes'])) {
            $groupPrefix = $id === ''
                ? $prefix
                : ($prefix === '' ? $id : $prefix . '_' . $id);
            foreach ($field['attributes'] as $child) {
                if (is_array($child)) {
                    $this->addFieldToShape($builder, $child, $groupPrefix);
                }
            }
            return;
        }

        if ($id === '') {
            return;
        }

        $key = $prefix === '' ? $id : $prefix . '_' . $id;

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
