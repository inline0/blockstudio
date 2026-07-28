<?php

declare(strict_types=1);

namespace Blockstudio\PHPStan\Rules;

use Blockstudio\PHPStan\Theme\ExtremeAnalyzer;
use PhpParser\Node;
use PHPStan\Analyser\Scope;
use PHPStan\Node\FileNode;
use PHPStan\Rules\Rule;

/**
 * @implements Rule<FileNode>
 */
final class ExtremeThemeRule implements Rule
{
    private bool $processed = false;

    public function __construct(
        private readonly ExtremeAnalyzer $analyzer
    ) {}

    public function getNodeType(): string
    {
        return FileNode::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        if ($this->processed) {
            return [];
        }
        $this->processed = true;

        return array_map(
            [DiagnosticRuleErrorFactory::class, 'build'],
            $this->analyzer->analyse()
        );
    }
}
