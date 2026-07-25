<?php

declare(strict_types=1);

namespace Blockstudio\PHPStan\Theme;

/**
 * Runs the non-PHP checks enabled by the opt-in extreme-theme preset.
 */
final class ExtremeAnalyzer
{
    public function __construct(
        private readonly ProjectScanner $scanner,
        private readonly JavaScriptAnalyzer $javaScriptAnalyzer,
        private readonly TailwindAnalyzer $tailwindAnalyzer,
        private readonly bool $checkJavaScript = true,
        private readonly bool $checkTailwind = true
    ) {}

    /**
     * @return list<Diagnostic>
     */
    public function analyse(): array
    {
        $diagnostics = [];

        if ($this->checkJavaScript) {
            $diagnostics = array_merge(
                $diagnostics,
                $this->javaScriptAnalyzer->analyse($this->scanner)
            );
        }

        if ($this->checkTailwind) {
            $diagnostics = array_merge(
                $diagnostics,
                $this->tailwindAnalyzer->analyse($this->scanner)
            );
        }

        usort(
            $diagnostics,
            static fn(Diagnostic $a, Diagnostic $b): int => [
                $a->file,
                $a->line,
                $a->identifier,
                $a->message,
            ] <=> [
                $b->file,
                $b->line,
                $b->identifier,
                $b->message,
            ]
        );

        return $diagnostics;
    }
}
