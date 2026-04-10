<?php

declare(strict_types=1);

namespace Blockstudio\PHPStan\Rules;

use PhpParser\Node;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar\String_;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * Warns when add_filter/add_action is used with an unknown blockstudio/* hook.
 *
 * @implements Rule<FuncCall>
 */
final class HookCallbackRule implements Rule
{
    /** @var list<string> */
    private const KNOWN_HOOKS = [
        'blockstudio/init',
        'blockstudio/init/before',
        'blockstudio/buffer/output',
        'blockstudio/render',
        'blockstudio/render/head',
        'blockstudio/render/footer',
        'blockstudio/blocks/conditions',
        'blockstudio/blocks/attributes/populate',
        'blockstudio/blocks/populate',
        'blockstudio/blocks/meta',
        'blockstudio/fields',
        'blockstudio/fields/paths',
        'blockstudio/assets',
        'blockstudio/assets/disable',
        'blockstudio/assets/process/css/content',
        'blockstudio/assets/process/js/content',
        'blockstudio/assets/process/scss/import_paths',
        'blockstudio/assets/process/scss/prelude',
        'blockstudio/block_tags/renderers',
        'blockstudio/parser/renderers',
        'blockstudio/block_tags/allow',
        'blockstudio/block_tags/deny',
        'blockstudio/block_tags/builders',
        'blockstudio/database',
        'blockstudio/rpc',
        'blockstudio/rpc/before_call',
        'blockstudio/rpc/after_call',
        'blockstudio/pages/paths',
        'blockstudio/patterns/paths',
        'blockstudio/pages/post_created',
        'blockstudio/pages/post_updated',
        'blockstudio/pages/synced',
        'blockstudio/patterns/registered',
        'blockstudio/tailwind/css',
        'blockstudio/error/logged',
        'blockstudio/error/exception',
        'blockstudio/admin/enabled',
    ];

    public function getNodeType(): string
    {
        return FuncCall::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        if (!$node->name instanceof Name) {
            return [];
        }

        $funcName = (string) $node->name;
        if (!in_array($funcName, ['add_filter', 'add_action'], true)) {
            return [];
        }

        $args = $node->getArgs();
        if (count($args) < 1 || !$args[0]->value instanceof String_) {
            return [];
        }

        $hook = $args[0]->value->value;
        if (!str_starts_with($hook, 'blockstudio/')) {
            return [];
        }

        // Allow dynamic settings hooks
        if (str_starts_with($hook, 'blockstudio/settings/')) {
            return [];
        }

        if (in_array($hook, self::KNOWN_HOOKS, true)) {
            return [];
        }

        $suggestion = $this->findSimilar($hook, self::KNOWN_HOOKS);
        $message = sprintf('Unknown Blockstudio hook "%s".', $hook);
        if ($suggestion !== null) {
            $message .= sprintf(' Did you mean "%s"?', $suggestion);
        }

        return [
            RuleErrorBuilder::message($message)
                ->identifier('blockstudio.hook')
                ->build(),
        ];
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
            if ($distance < $bestDistance && $distance <= 5) {
                $bestDistance = $distance;
                $best = $candidate;
            }
        }
        return $best;
    }
}
