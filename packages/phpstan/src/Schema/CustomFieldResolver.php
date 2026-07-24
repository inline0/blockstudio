<?php

declare(strict_types=1);

namespace Blockstudio\PHPStan\Schema;

/**
 * Expands file-backed custom field references using Blockstudio runtime rules.
 */
final class CustomFieldResolver
{
    /**
     * @var array<string, array<string, mixed>|null>
     */
    private array $definitionCache = [];

    public function __construct(private readonly ProjectScanner $scanner) {}

    /**
     * @param array{
     *     type: 'missing'|'ambiguous'|'cycle'|'invalid',
     *     name: string,
     *     paths: list<string>
     * } $issue
     */
    public static function describeIssue(array $issue, string $source): string
    {
        $name = $issue['name'] !== '' ? $issue['name'] : '(empty name)';

        return match ($issue['type']) {
            'missing' => sprintf(
                'Custom field "%s" referenced in %s has no discoverable field.json definition.',
                $name,
                $source
            ),
            'ambiguous' => sprintf(
                'Custom field "%s" referenced in %s is ambiguous; definitions were found at: %s',
                $name,
                $source,
                implode(', ', $issue['paths'])
            ),
            'cycle' => sprintf(
                'Custom field "%s" referenced in %s creates a definition cycle: %s',
                $name,
                $source,
                implode(' -> ', $issue['paths'])
            ),
            'invalid' => sprintf(
                'Custom field "%s" referenced in %s has an invalid definition at: %s',
                $name,
                $source,
                implode(', ', $issue['paths'])
            ),
        };
    }

    /**
     * @param array<int, mixed> $attributes
     * @return array{
     *     attributes: array<int, mixed>,
     *     issues: list<array{
     *         type: 'missing'|'ambiguous'|'cycle'|'invalid',
     *         name: string,
     *         paths: list<string>
     *     }>
     * }
     */
    public function resolve(array $attributes): array
    {
        $issues = [];
        $resolved = $this->expandAttributes($attributes, [], $issues);

        return [
            'attributes' => $resolved,
            'issues' => $this->deduplicateIssues($issues),
        ];
    }

    /**
     * Resolve references inside one field.json while treating that definition
     * as the first item in the cycle-detection stack.
     *
     * @return array{
     *     attributes: array<int, mixed>,
     *     issues: list<array{
     *         type: 'missing'|'ambiguous'|'cycle'|'invalid',
     *         name: string,
     *         paths: list<string>
     *     }>
     * }
     */
    public function resolveDefinition(string $path): array
    {
        $definition = $this->loadDefinition($path);
        $name = is_array($definition) && is_string($definition['name'] ?? null)
            ? $definition['name']
            : '';
        $attributes = is_array($definition) ? ($definition['attributes'] ?? null) : null;

        if (
            !is_array($attributes)
            || $attributes === []
            || $this->containsInvalidAttribute($attributes)
        ) {
            return [
                'attributes' => [],
                'issues' => [[
                    'type' => 'invalid',
                    'name' => $name,
                    'paths' => [$path],
                ]],
            ];
        }

        $issues = [];
        $resolved = $this->expandAttributes(array_values($attributes), [$path], $issues);

        return [
            'attributes' => $resolved,
            'issues' => $this->deduplicateIssues($issues),
        ];
    }

    /**
     * @param array<int, mixed> $attributes
     * @param list<string> $definitionStack
     * @param list<array{
     *     type: 'missing'|'ambiguous'|'cycle'|'invalid',
     *     name: string,
     *     paths: list<string>
     * }> $issues
     * @return array<int, mixed>
     */
    private function expandAttributes(
        array $attributes,
        array $definitionStack,
        array &$issues
    ): array {
        $expanded = [];

        foreach ($attributes as $attribute) {
            if (!is_array($attribute)) {
                $expanded[] = $attribute;
                continue;
            }

            $type = is_string($attribute['type'] ?? null)
                ? $attribute['type']
                : '';

            if ($type === 'group' || $type === 'repeater') {
                if (is_array($attribute['attributes'] ?? null)) {
                    $attribute['attributes'] = $this->expandAttributes(
                        $attribute['attributes'],
                        $definitionStack,
                        $issues
                    );
                }
                $expanded[] = $attribute;
                continue;
            }

            if ($type === 'tabs') {
                if (is_array($attribute['tabs'] ?? null)) {
                    foreach ($attribute['tabs'] as &$tab) {
                        if (is_array($tab) && is_array($tab['attributes'] ?? null)) {
                            $tab['attributes'] = $this->expandAttributes(
                                $tab['attributes'],
                                $definitionStack,
                                $issues
                            );
                        }
                    }
                    unset($tab);
                }
                $expanded[] = $attribute;
                continue;
            }

            if (!str_starts_with($type, 'custom/')) {
                $expanded[] = $attribute;
                continue;
            }

            $fieldName = substr($type, 7);
            $paths = $this->scanner->findFieldJsonPathsByName($fieldName);

            if ($paths === []) {
                $issues[] = [
                    'type' => 'missing',
                    'name' => $fieldName,
                    'paths' => [],
                ];
                $expanded[] = $attribute;
                continue;
            }

            if (count($paths) > 1) {
                $issues[] = [
                    'type' => 'ambiguous',
                    'name' => $fieldName,
                    'paths' => $paths,
                ];
                $expanded[] = $attribute;
                continue;
            }

            $definitionPath = $paths[0];
            if (in_array($definitionPath, $definitionStack, true)) {
                $issues[] = [
                    'type' => 'cycle',
                    'name' => $fieldName,
                    'paths' => [...$definitionStack, $definitionPath],
                ];
                $expanded[] = $attribute;
                continue;
            }

            $definition = $this->loadDefinition($definitionPath);
            $definitionAttributes = is_array($definition)
                ? ($definition['attributes'] ?? null)
                : null;

            if (
                !is_array($definitionAttributes)
                || $definitionAttributes === []
                || $this->containsInvalidAttribute($definitionAttributes)
            ) {
                $issues[] = [
                    'type' => 'invalid',
                    'name' => $fieldName,
                    'paths' => [$definitionPath],
                ];
                $expanded[] = $attribute;
                continue;
            }

            $definitionAttributes = $this->expandAttributes(
                array_values($definitionAttributes),
                [...$definitionStack, $definitionPath],
                $issues
            );
            $idStructure = is_string($attribute['idStructure'] ?? null)
                ? $attribute['idStructure']
                : '{id}';
            $overrides = is_array($attribute['overrides'] ?? null)
                ? $attribute['overrides']
                : [];
            $referenceConditions = is_array($attribute['conditions'] ?? null)
                ? $attribute['conditions']
                : null;

            foreach ($definitionAttributes as $fieldAttribute) {
                if (!is_array($fieldAttribute)) {
                    $expanded[] = $fieldAttribute;
                    continue;
                }

                $originalId = is_string($fieldAttribute['id'] ?? null)
                    ? $fieldAttribute['id']
                    : '';
                $override = is_array($overrides[$originalId] ?? null)
                    ? $overrides[$originalId]
                    : [];
                $merged = array_merge($fieldAttribute, $override);

                if ($idStructure !== '{id}') {
                    $this->rewriteAttributeIds(
                        $merged,
                        $idStructure,
                        false,
                        false,
                        array_key_exists('id', $override)
                    );
                }

                if ($referenceConditions !== null) {
                    $merged['conditions'] = is_array($merged['conditions'] ?? null)
                        ? array_merge($merged['conditions'], $referenceConditions)
                        : $referenceConditions;
                }

                $expanded[] = $merged;
            }
        }

        return $expanded;
    }

    /**
     * @param array<string, mixed> $attribute
     */
    private function rewriteAttributeIds(
        array &$attribute,
        string $idStructure,
        bool $insideRepeater,
        bool $insideIdGroup,
        bool $skipCurrentId = false
    ): void {
        $type = is_string($attribute['type'] ?? null) ? $attribute['type'] : '';
        $hasId = is_string($attribute['id'] ?? null) && $attribute['id'] !== '';

        if (!$insideRepeater && !$insideIdGroup && $hasId && !$skipCurrentId) {
            $attribute['id'] = str_replace('{id}', $attribute['id'], $idStructure);
        }

        if (!$insideRepeater && is_array($attribute['conditions'] ?? null)) {
            $this->rewriteConditionIds($attribute['conditions'], $idStructure);
        }

        if ($type === 'tabs' && is_array($attribute['tabs'] ?? null)) {
            foreach ($attribute['tabs'] as &$tab) {
                if (!is_array($tab) || !is_array($tab['attributes'] ?? null)) {
                    continue;
                }

                foreach ($tab['attributes'] as &$tabAttribute) {
                    if (is_array($tabAttribute)) {
                        $this->rewriteAttributeIds(
                            $tabAttribute,
                            $idStructure,
                            $insideRepeater,
                            $insideIdGroup
                        );
                    }
                }
                unset($tabAttribute);
            }
            unset($tab);
        }

        if ($type === 'group' && is_array($attribute['attributes'] ?? null)) {
            $childrenInsideIdGroup = $insideIdGroup || (!$insideRepeater && $hasId);

            foreach ($attribute['attributes'] as &$groupAttribute) {
                if (is_array($groupAttribute)) {
                    $this->rewriteAttributeIds(
                        $groupAttribute,
                        $idStructure,
                        $insideRepeater,
                        $childrenInsideIdGroup
                    );
                }
            }
            unset($groupAttribute);
        }
    }

    /**
     * @param array<int, mixed> $conditions
     */
    private function rewriteConditionIds(array &$conditions, string $idStructure): void
    {
        foreach ($conditions as &$group) {
            if (!is_array($group)) {
                continue;
            }

            foreach ($group as &$condition) {
                if (is_array($condition) && is_string($condition['id'] ?? null)) {
                    $condition['id'] = str_replace('{id}', $condition['id'], $idStructure);
                }
            }
            unset($condition);
        }
        unset($group);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function loadDefinition(string $path): ?array
    {
        if (array_key_exists($path, $this->definitionCache)) {
            return $this->definitionCache[$path];
        }

        $content = @file_get_contents($path);
        if ($content === false) {
            return $this->definitionCache[$path] = null;
        }

        $decoded = json_decode($content, true);
        return $this->definitionCache[$path] = is_array($decoded) ? $decoded : null;
    }

    /**
     * @param array<int|string, mixed> $attributes
     */
    private function containsInvalidAttribute(array $attributes): bool
    {
        foreach ($attributes as $attribute) {
            if (!is_array($attribute)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param list<array{
     *     type: 'missing'|'ambiguous'|'cycle'|'invalid',
     *     name: string,
     *     paths: list<string>
     * }> $issues
     * @return list<array{
     *     type: 'missing'|'ambiguous'|'cycle'|'invalid',
     *     name: string,
     *     paths: list<string>
     * }>
     */
    private function deduplicateIssues(array $issues): array
    {
        $unique = [];

        foreach ($issues as $issue) {
            $key = $issue['type'] . "\0" . $issue['name'] . "\0" . implode("\0", $issue['paths']);
            $unique[$key] = $issue;
        }

        return array_values($unique);
    }
}
