<?php

declare(strict_types=1);

namespace Blockstudio\PHPStan\Rules;

use Blockstudio\PHPStan\Theme\ProjectScanner;
use PhpParser\Node;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * @implements Rule<MethodCall>
 */
final class ExtremeRawDatabaseWriteRule implements Rule
{
    private const WRITE_METHODS = ['delete', 'insert', 'query', 'update'];

    public function __construct(
        private readonly ProjectScanner $scanner
    ) {}

    public function getNodeType(): string
    {
        return MethodCall::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        if (
            !$node->name instanceof Identifier
            || !$node->var instanceof Variable
            || $node->var->name !== 'wpdb'
            || $this->scanner->rootForFile($scope->getFile()) === null
        ) {
            return [];
        }

        $method = strtolower($node->name->toString());
        if (!in_array($method, self::WRITE_METHODS, true)) {
            return [];
        }

        return [
            RuleErrorBuilder::message(
                sprintf(
                    'Use a bounded WordPress data API instead of raw $wpdb->%s() writes.',
                    $method
                )
            )
                ->identifier('blockstudio.wordpress.rawDatabaseWrite')
                ->line($node->getStartLine())
                ->build(),
        ];
    }
}
