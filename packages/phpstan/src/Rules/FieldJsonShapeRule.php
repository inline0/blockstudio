<?php

declare(strict_types=1);

namespace Blockstudio\PHPStan\Rules;

use Blockstudio\PHPStan\Schema\CustomFieldResolver;
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
        private readonly ProjectScanner $scanner,
        private readonly CustomFieldResolver $customFields
    ) {}

    public function getNodeType(): string
    {
        return FileNode::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        $errors = [];

        foreach ($this->scanner->getFieldJsonPaths() as $path) {
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
        $content = @file_get_contents($path);
        if ($content === false) {
            return [];
        }

        $data = json_decode($content, true);
        if (!is_array($data)) {
            return [
                RuleErrorBuilder::message(sprintf('Invalid field.json: %s', $this->scanner->relativePath($path)))
                    ->identifier('blockstudio.fieldJson')
                    ->file($path)
                    ->build(),
            ];
        }

        $errors = [];

        if (!isset($data['name']) || !is_string($data['name']) || $data['name'] === '') {
            $errors[] = RuleErrorBuilder::message(sprintf(
                'field.json missing required "name": %s',
                $this->scanner->relativePath($path)
            ))
                ->identifier('blockstudio.fieldJson.name')
                ->file($path)
                ->build();
        }

        $attributes = $data['attributes'] ?? null;
        if (!is_array($attributes) || $attributes === []) {
            $errors[] = RuleErrorBuilder::message(sprintf(
                'field.json "attributes" must be a non-empty array: %s',
                $this->scanner->relativePath($path)
            ))
                ->identifier('blockstudio.fieldJson.attributes')
                ->file($path)
                ->build();
        } else {
            $errors = array_merge($errors, $this->validateAttributes($attributes, $path));
            $errors = array_merge(
                $errors,
                $this->buildCustomFieldErrors(
                    $this->customFields->resolveDefinition($path)['issues'],
                    $path
                )
            );
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
                $errors[] = RuleErrorBuilder::message(sprintf(
                    'field.json attributes[%d] must be an object: %s',
                    $i,
                    $this->scanner->relativePath($path)
                ))
                    ->identifier('blockstudio.fieldJson.attributes')
                    ->file($path)
                    ->build();
                continue;
            }

            $id = $field['id'] ?? $field['key'] ?? null;
            $type = $field['type'] ?? null;

            if ($type === null || !is_string($type)) {
                $errors[] = RuleErrorBuilder::message(sprintf(
                    'field.json field "%s" missing "type": %s',
                    $this->getFieldLabel($field, $i),
                    $this->scanner->relativePath($path)
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
                    $this->scanner->relativePath($path)
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
                    $this->scanner->relativePath($path)
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
     * @param list<array{
     *     type: 'missing'|'ambiguous'|'cycle'|'invalid',
     *     name: string,
     *     paths: list<string>
     * }> $issues
     * @return list<\PHPStan\Rules\IdentifierRuleError>
     */
    private function buildCustomFieldErrors(array $issues, string $path): array
    {
        $errors = [];

        foreach ($issues as $issue) {
            $errors[] = RuleErrorBuilder::message(
                CustomFieldResolver::describeIssue($issue, 'field.json')
            )
                ->identifier('blockstudio.customField.' . $issue['type'])
                ->file($path)
                ->build();
        }

        return $errors;
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
