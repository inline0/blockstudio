<?php

declare(strict_types=1);

namespace Blockstudio\PHPStan\Rules;

use Blockstudio\PHPStan\Type\SettingsGetReturnType;
use PhpParser\Node;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar\String_;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * Validates Settings::get() path arguments against the known settings schema.
 *
 * @implements Rule<StaticCall>
 */
final class SettingsPathRule implements Rule
{
    public function getNodeType(): string
    {
        return StaticCall::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        if (!$node->class instanceof Name) {
            return [];
        }

        $className = (string) $node->class;
        if ($className !== 'Blockstudio\\Settings' && $className !== '\\Blockstudio\\Settings' && $className !== 'Settings') {
            return [];
        }

        if (!$node->name instanceof Identifier || $node->name->name !== 'get') {
            return [];
        }

        $args = $node->getArgs();
        if (count($args) < 1 || !$args[0]->value instanceof String_) {
            return [];
        }

        $path = $args[0]->value->value;

        if (SettingsGetReturnType::isKnownPath($path)) {
            return [];
        }

        $suggestion = $this->findSimilar($path, SettingsGetReturnType::getAllPaths());
        $message = sprintf('Settings path "%s" is not a known Blockstudio setting.', $path);
        if ($suggestion !== null) {
            $message .= sprintf(' Did you mean "%s"?', $suggestion);
        }

        return [
            RuleErrorBuilder::message($message)
                ->identifier('blockstudio.settingsPath')
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
