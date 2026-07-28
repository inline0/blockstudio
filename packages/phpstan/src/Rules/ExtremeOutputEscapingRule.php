<?php

declare(strict_types=1);

namespace Blockstudio\PHPStan\Rules;

use Blockstudio\PHPStan\Theme\ProjectScanner;
use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\ArrayDimFetch;
use PhpParser\Node\Expr\Cast\Double;
use PhpParser\Node\Expr\Cast\Int_;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\Print_;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Expr\Ternary;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Name;
use PhpParser\Node\Stmt\Echo_;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * @implements Rule<Node>
 */
final class ExtremeOutputEscapingRule implements Rule
{
    private const SAFE_OUTPUT_FUNCTIONS = [
        'esc_attr',
        'esc_html',
        'esc_textarea',
        'esc_url',
        'wp_json_encode',
        'wp_kses',
        'wp_kses_data',
        'wp_kses_post',
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

        if ($node instanceof Echo_) {
            foreach ($node->exprs as $expression) {
                if ($this->requiresEscape($expression)) {
                    return [$this->error($node->getStartLine())];
                }
            }
        }

        if ($node instanceof Print_ && $this->requiresEscape($node->expr)) {
            return [$this->error($node->getStartLine())];
        }

        return [];
    }

    private function error(int $line): \PHPStan\Rules\IdentifierRuleError
    {
        return RuleErrorBuilder::message(
            'Dynamic Blockstudio attributes must be escaped before output.'
        )
            ->identifier('blockstudio.output.unescaped')
            ->line($line)
            ->build();
    }

    private function requiresEscape(Expr $expression): bool
    {
        if ($this->isSafeOutputCall($expression)) {
            return false;
        }

        if (
            $expression instanceof FuncCall
            || $expression instanceof MethodCall
            || $expression instanceof StaticCall
            || $expression instanceof Int_
            || $expression instanceof Double
        ) {
            return false;
        }

        if ($expression instanceof Ternary) {
            return (
                $expression->if === null
                && $this->requiresEscape($expression->cond)
            )
                || (
                    $expression->if instanceof Expr
                    && $this->requiresEscape($expression->if)
                )
                || $this->requiresEscape($expression->else);
        }

        if (
            $expression instanceof ArrayDimFetch
            && $this->isAttributeFetch($expression)
        ) {
            return true;
        }

        if ($expression instanceof ArrayDimFetch) {
            return false;
        }

        foreach ($expression->getSubNodeNames() as $name) {
            $value = $expression->{$name};
            if ($value instanceof Expr && $this->requiresEscape($value)) {
                return true;
            }

            if (!is_array($value)) {
                continue;
            }

            foreach ($value as $child) {
                if ($child instanceof Expr && $this->requiresEscape($child)) {
                    return true;
                }
            }
        }

        return false;
    }

    private function isSafeOutputCall(Expr $expression): bool
    {
        if (
            !$expression instanceof FuncCall
            || !$expression->name instanceof Name
        ) {
            return false;
        }

        return in_array(
            strtolower($expression->name->toString()),
            self::SAFE_OUTPUT_FUNCTIONS,
            true
        );
    }

    private function isAttributeFetch(ArrayDimFetch $expression): bool
    {
        while ($expression->var instanceof ArrayDimFetch) {
            $expression = $expression->var;
        }

        return $expression->var instanceof Variable
            && is_string($expression->var->name)
            && in_array($expression->var->name, ['a', 'attributes'], true);
    }
}
