<?php

declare(strict_types=1);

namespace Blockstudio\PHPStan\Rules;

use Blockstudio\PHPStan\Schema\BlockJsonReader;
use Blockstudio\PHPStan\Schema\ProjectScanner;
use PhpParser\Node;
use PHPStan\Analyser\Scope;
use PHPStan\Node\FileNode;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * Validates block.json files in the project.
 *
 * Triggered once per analysis run via FileNode (which fires once per file).
 * For each block.json discovered by the scanner, validates the schema and reports
 * errors. Uses an instance flag to only run validation once per run.
 *
 * @implements Rule<FileNode>
 */
final class BlockJsonShapeRule implements Rule
{
    private const VALID_FIELD_TYPES = [
        'text', 'textarea', 'richtext', 'wysiwyg', 'code',
        'number', 'range', 'toggle',
        'select', 'radio', 'checkbox',
        'color', 'gradient', 'link', 'files', 'icon',
        'date', 'datetime', 'classes', 'html-tag', 'unit',
        'attributes', 'block', 'message',
        'group', 'repeater', 'tabs',
    ];

    /** @var array<string, true> */
    private static array $validatedPaths = [];

    public function __construct(
        private readonly ProjectScanner $scanner,
        private readonly BlockJsonReader $reader
    ) {}

    public function getNodeType(): string
    {
        return FileNode::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        $errors = [];

        foreach ($this->scanner->getBlockJsonPaths() as $path) {
            if (isset(self::$validatedPaths[$path])) {
                continue;
            }
            self::$validatedPaths[$path] = true;
            $errors = array_merge($errors, $this->validateFile($path));
        }

        return $errors;
    }

    /**
     * @return list<\PHPStan\Rules\IdentifierRuleError>
     */
    private function validateFile(string $path): array
    {
        $data = $this->reader->load($path);
        if ($data === null) {
            return [
                RuleErrorBuilder::message(sprintf(
                    'Invalid block.json: %s could not be parsed as JSON.',
                    $path
                ))
                    ->identifier('blockstudio.blockJson')
                    ->file($path)
                    ->build(),
            ];
        }

        $errors = [];

        if (!isset($data['name']) || !is_string($data['name']) || $data['name'] === '') {
            $errors[] = RuleErrorBuilder::message(sprintf(
                'block.json missing required "name" field: %s',
                $path
            ))
                ->identifier('blockstudio.blockJson.name')
                ->file($path)
                ->build();
        }

        $bs = $data['blockstudio'] ?? null;
        if ($bs !== null && !is_array($bs)) {
            $errors[] = RuleErrorBuilder::message(sprintf(
                'block.json "blockstudio" must be an object: %s',
                $path
            ))
                ->identifier('blockstudio.blockJson.blockstudio')
                ->file($path)
                ->build();
            return $errors;
        }

        $attributes = $bs['attributes'] ?? null;
        if ($attributes !== null && !is_array($attributes)) {
            $errors[] = RuleErrorBuilder::message(sprintf(
                'block.json "blockstudio.attributes" must be an array: %s',
                $path
            ))
                ->identifier('blockstudio.blockJson.attributes')
                ->file($path)
                ->build();
            return $errors;
        }

        if (is_array($attributes)) {
            $errors = array_merge($errors, $this->validateAttributes($attributes, $path));
        }

        return $errors;
    }

    /**
     * @param array<int, mixed> $attributes
     * @return list<\PHPStan\Rules\IdentifierRuleError>
     */
    private function validateAttributes(array $attributes, string $path, string $prefix = ''): array
    {
        $errors = [];
        $seenIds = [];

        foreach ($attributes as $i => $field) {
            if (!is_array($field)) {
                $errors[] = RuleErrorBuilder::message(sprintf(
                    'block.json attributes[%d] must be an object: %s',
                    $i,
                    $path
                ))
                    ->identifier('blockstudio.blockJson.attributes')
                    ->file($path)
                    ->build();
                continue;
            }

            $id = $field['id'] ?? $field['key'] ?? null;
            $type = $field['type'] ?? null;

            if ($type === null || !is_string($type)) {
                $errors[] = RuleErrorBuilder::message(sprintf(
                    'block.json field "%s" missing "type": %s',
                    $this->getFieldLabel($field, $i),
                    $path
                ))
                    ->identifier('blockstudio.blockJson.type')
                    ->file($path)
                    ->build();
                continue;
            }

            if (!str_starts_with($type, 'custom/') && !in_array($type, self::VALID_FIELD_TYPES, true)) {
                $errors[] = RuleErrorBuilder::message(sprintf(
                    'block.json field "%s" has unknown type "%s" in %s',
                    $this->getFieldLabel($field, $i),
                    $type,
                    $path
                ))
                    ->identifier('blockstudio.blockJson.type')
                    ->file($path)
                    ->build();
                continue;
            }

            if ($this->requiresFieldId($type) && ($id === null || !is_string($id) || $id === '')) {
                $errors[] = RuleErrorBuilder::message(sprintf(
                    'block.json attributes[%d] missing "id": %s',
                    $i,
                    $path
                ))
                    ->identifier('blockstudio.blockJson.attributes')
                    ->file($path)
                    ->build();
                continue;
            }

            if (is_string($id) && $id !== '') {
                if (isset($seenIds[$id])) {
                    $errors[] = RuleErrorBuilder::message(sprintf(
                        'block.json duplicate field id "%s" in %s',
                        $id,
                        $path
                    ))
                        ->identifier('blockstudio.blockJson.duplicate')
                        ->file($path)
                        ->build();
                }
                $seenIds[$id] = true;
            }

            if (in_array($type, ['select', 'radio', 'checkbox'], true)) {
                if (!isset($field['options']) && !isset($field['populate'])) {
                    $errors[] = RuleErrorBuilder::message(sprintf(
                        'block.json field "%s" of type "%s" must have "options" or "populate" in %s',
                        $id,
                        $type,
                        $path
                    ))
                        ->identifier('blockstudio.blockJson.options')
                        ->file($path)
                        ->build();
                }
            }

            if ($type === 'group' || $type === 'repeater') {
                if (isset($field['attributes']) && is_array($field['attributes'])) {
                    $errors = array_merge(
                        $errors,
                        $this->validateAttributes($field['attributes'], $path, is_string($id) ? $id : '')
                    );
                }
            }

            if ($type === 'tabs' && isset($field['tabs']) && is_array($field['tabs'])) {
                foreach ($field['tabs'] as $j => $tab) {
                    if (is_array($tab) && isset($tab['attributes']) && is_array($tab['attributes'])) {
                        $errors = array_merge(
                            $errors,
                            $this->validateAttributes($tab['attributes'], $path, 'tab' . $j)
                        );
                    }
                }
            }
        }

        return $errors;
    }

    private function requiresFieldId(string $type): bool
    {
        return $type !== 'group'
            && $type !== 'tabs'
            && !str_starts_with($type, 'custom/');
    }

    /**
     * @param array<string, mixed> $field
     */
    private function getFieldLabel(array $field, int $index): string
    {
        $id = $field['id'] ?? $field['key'] ?? null;
        if (is_string($id) && $id !== '') {
            return $id;
        }

        return sprintf('attributes[%d]', $index);
    }
}
