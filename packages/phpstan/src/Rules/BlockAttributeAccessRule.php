<?php

declare(strict_types=1);

namespace Blockstudio\PHPStan\Rules;

use Blockstudio\PHPStan\Reflection\BlockTemplateDetector;
use Blockstudio\PHPStan\Schema\BlockJsonReader;
use PhpParser\Node;
use PhpParser\Node\Expr\ArrayDimFetch;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Scalar\String_;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * Validates $a['key'] and $attributes['key'] accesses inside Blockstudio
 * block templates against the sibling block.json schema.
 *
 * @implements Rule<ArrayDimFetch>
 */
final class BlockAttributeAccessRule implements Rule
{
    public function __construct(
        private readonly BlockTemplateDetector $detector,
        private readonly BlockJsonReader $reader
    ) {}

    public function getNodeType(): string
    {
        return ArrayDimFetch::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        if (!$node->var instanceof Variable) {
            return [];
        }

        $varName = $node->var->name;
        if (!is_string($varName) || !in_array($varName, ['a', 'attributes'], true)) {
            return [];
        }

        if (!$node->dim instanceof String_) {
            return [];
        }

        $blockJson = $this->detector->getBlockJsonForTemplate($scope->getFile());
        if ($blockJson === null) {
            return [];
        }

        $data = $this->reader->load($blockJson);
        if ($data === null) {
            return [];
        }

        $attributes = $data['blockstudio']['attributes'] ?? [];
        if (!is_array($attributes)) {
            return [];
        }

        $validKeys = $this->collectKeys($attributes);
        $accessedKey = $node->dim->value;

        if (in_array($accessedKey, $validKeys, true)) {
            return [];
        }

        $suggestion = $this->findSimilar($accessedKey, $validKeys);
        $message = sprintf(
            'Field "%s" does not exist in block.json (block: %s).',
            $accessedKey,
            (string) ($data['name'] ?? basename(dirname($blockJson)))
        );

        if ($suggestion !== null) {
            $message .= sprintf(' Did you mean "%s"?', $suggestion);
        }

        return [
            RuleErrorBuilder::message($message)
                ->identifier('blockstudio.field')
                ->build(),
        ];
    }

    /**
     * Collect all valid attribute keys from a block.json attributes array.
     * Handles group, repeater, tabs flattening.
     *
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

            $id = (string) ($field['id'] ?? $field['key'] ?? '');
            if ($id === '') {
                continue;
            }

            $type = (string) ($field['type'] ?? 'text');
            $key = $prefix === '' ? $id : $prefix . '_' . $id;

            if ($type === 'group' && isset($field['attributes']) && is_array($field['attributes'])) {
                $keys = array_merge($keys, $this->collectKeys($field['attributes'], $key));
                continue;
            }

            if ($type === 'tabs' && isset($field['tabs']) && is_array($field['tabs'])) {
                foreach ($field['tabs'] as $tab) {
                    if (is_array($tab) && isset($tab['attributes']) && is_array($tab['attributes'])) {
                        $keys = array_merge($keys, $this->collectKeys($tab['attributes'], $prefix));
                    }
                }
                continue;
            }

            if ($type === 'message') {
                continue;
            }

            $keys[] = $key;
        }

        return $keys;
    }

    /**
     * @param list<string> $candidates
     */
    private function findSimilar(string $needle, array $candidates): ?string
    {
        $best = null;
        $bestDistance = PHP_INT_MAX;

        foreach ($candidates as $candidate) {
            $distance = levenshtein($needle, $candidate);
            if ($distance < $bestDistance && $distance <= 3) {
                $bestDistance = $distance;
                $best = $candidate;
            }
        }

        return $best;
    }
}
