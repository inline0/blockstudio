<?php

declare(strict_types=1);

namespace Blockstudio\PHPStan\Rules;

use Blockstudio\PHPStan\Schema\ProjectScanner;
use PhpParser\Node;
use PHPStan\Analyser\Scope;
use PHPStan\Node\FileNode;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * Validates field.json files (custom reusable field definitions).
 *
 * @implements Rule<FileNode>
 */
final class FieldJsonShapeRule implements Rule
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
        private readonly ProjectScanner $scanner
    ) {}

    public function getNodeType(): string
    {
        return FileNode::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        $errors = [];

        foreach ($this->findFieldJsonFiles() as $path) {
            if (isset(self::$validatedPaths[$path])) {
                continue;
            }
            self::$validatedPaths[$path] = true;
            $errors = array_merge($errors, $this->validateFile($path));
        }

        return $errors;
    }

    /**
     * @return list<string>
     */
    private function findFieldJsonFiles(): array
    {
        $files = [];
        foreach ($this->scanner->getBlockJsonPaths() as $blockJsonPath) {
            $projectRoot = dirname($blockJsonPath, 2);
            $fieldsDir = $projectRoot . '/fields';
            if (!is_dir($fieldsDir)) {
                $fieldsDir = dirname($blockJsonPath, 3) . '/fields';
            }
            if (is_dir($fieldsDir)) {
                $iterator = new \RecursiveIteratorIterator(
                    new \RecursiveDirectoryIterator($fieldsDir, \FilesystemIterator::SKIP_DOTS)
                );
                foreach ($iterator as $file) {
                    if ($file instanceof \SplFileInfo && $file->getFilename() === 'field.json') {
                        $files[$file->getPathname()] = $file->getPathname();
                    }
                }
            }
        }
        return array_values($files);
    }

    /**
     * @return list<\PHPStan\Rules\IdentifierRuleError>
     */
    private function validateFile(string $path): array
    {
        $content = @file_get_contents($path);
        if ($content === false) {
            return [];
        }

        $data = json_decode($content, true);
        if (!is_array($data)) {
            return [
                RuleErrorBuilder::message(sprintf('Invalid field.json: %s', $path))
                    ->identifier('blockstudio.fieldJson')
                    ->file($path)
                    ->build(),
            ];
        }

        $errors = [];

        if (!isset($data['name']) || !is_string($data['name']) || $data['name'] === '') {
            $errors[] = RuleErrorBuilder::message(sprintf(
                'field.json missing required "name": %s',
                $path
            ))
                ->identifier('blockstudio.fieldJson.name')
                ->file($path)
                ->build();
        }

        $attributes = $data['attributes'] ?? null;
        if ($attributes !== null && !is_array($attributes)) {
            $errors[] = RuleErrorBuilder::message(sprintf(
                'field.json "attributes" must be an array: %s',
                $path
            ))
                ->identifier('blockstudio.fieldJson.attributes')
                ->file($path)
                ->build();
        } elseif (is_array($attributes)) {
            $errors = array_merge($errors, $this->validateAttributes($attributes, $path));
        }

        return $errors;
    }

    /**
     * @param array<int, mixed> $attributes
     * @return list<\PHPStan\Rules\IdentifierRuleError>
     */
    private function validateAttributes(array $attributes, string $path): array
    {
        $errors = [];

        foreach ($attributes as $i => $field) {
            if (!is_array($field)) {
                continue;
            }

            $id = $field['id'] ?? $field['key'] ?? null;
            $type = $field['type'] ?? null;

            if ($type === null || !is_string($type)) {
                $errors[] = RuleErrorBuilder::message(sprintf(
                    'field.json field "%s" missing "type": %s',
                    $this->getFieldLabel($field, $i),
                    $path
                ))
                    ->identifier('blockstudio.fieldJson.type')
                    ->file($path)
                    ->build();
                continue;
            }

            if (!str_starts_with($type, 'custom/') && !in_array($type, self::VALID_FIELD_TYPES, true)) {
                $errors[] = RuleErrorBuilder::message(sprintf(
                    'field.json field "%s" has unknown type "%s": %s',
                    $this->getFieldLabel($field, $i),
                    $type,
                    $path
                ))
                    ->identifier('blockstudio.fieldJson.type')
                    ->file($path)
                    ->build();
                continue;
            }

            if ($this->requiresFieldId($type) && ($id === null || !is_string($id) || $id === '')) {
                $errors[] = RuleErrorBuilder::message(sprintf(
                    'field.json attributes[%d] missing "id": %s',
                    $i,
                    $path
                ))
                    ->identifier('blockstudio.fieldJson.attributes')
                    ->file($path)
                    ->build();
            }

            if (($type === 'group' || $type === 'repeater') && isset($field['attributes']) && is_array($field['attributes'])) {
                $errors = array_merge($errors, $this->validateAttributes($field['attributes'], $path));
            }

            if ($type === 'tabs' && isset($field['tabs']) && is_array($field['tabs'])) {
                foreach ($field['tabs'] as $tab) {
                    if (is_array($tab) && isset($tab['attributes']) && is_array($tab['attributes'])) {
                        $errors = array_merge($errors, $this->validateAttributes($tab['attributes'], $path));
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
