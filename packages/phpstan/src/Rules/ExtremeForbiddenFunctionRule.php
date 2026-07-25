<?php

declare(strict_types=1);

namespace Blockstudio\PHPStan\Rules;

use Blockstudio\PHPStan\Theme\ProjectScanner;
use PhpParser\Node;
use PhpParser\Node\Expr\Eval_;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Name;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * @implements Rule<Node>
 */
final class ExtremeForbiddenFunctionRule implements Rule
{
    private const FORBIDDEN_FUNCTIONS = [
        'exec',
        'passthru',
        'popen',
        'proc_open',
        'shell_exec',
        'system',
    ];

    public function __construct(
        private readonly ProjectScanner $scanner
    ) {}

    public function getNodeType(): string
    {
        return Node::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        if ($this->scanner->rootForFile($scope->getFile()) === null) {
            return [];
        }

        if ($node instanceof Eval_) {
            return [$this->error('eval', $node->getStartLine())];
        }

        if (!$node instanceof FuncCall || !$node->name instanceof Name) {
            return [];
        }

        $function = strtolower(ltrim($scope->resolveName($node->name), '\\'));
        if (!in_array($function, self::FORBIDDEN_FUNCTIONS, true)) {
            return [];
        }

        return [$this->error($function, $node->getStartLine())];
    }

    private function error(
        string $function,
        int $line
    ): \PHPStan\Rules\IdentifierRuleError {
        return RuleErrorBuilder::message(
            sprintf(
                'Theme code must not call "%s()"; use a bounded platform API instead.',
                $function
            )
        )
            ->identifier('blockstudio.php.forbiddenFunction')
            ->line($line)
            ->build();
    }
}
