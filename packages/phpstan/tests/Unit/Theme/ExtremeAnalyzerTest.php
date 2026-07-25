<?php

declare(strict_types=1);

namespace Blockstudio\PHPStan\Tests\Unit\Theme;

use Blockstudio\PHPStan\Theme\ExtremeAnalyzer;
use Blockstudio\PHPStan\Theme\JavaScriptAnalyzer;
use Blockstudio\PHPStan\Theme\ProjectScanner;
use Blockstudio\PHPStan\Theme\TailwindAnalyzer;
use PHPUnit\Framework\TestCase;

final class ExtremeAnalyzerTest extends TestCase
{
    public function test_checks_are_independently_opt_in(): void
    {
        $root = dirname(__DIR__, 2) . '/fixtures/theme-invalid';
        $disabled = new ExtremeAnalyzer(
            new ProjectScanner($root),
            new JavaScriptAnalyzer(),
            new TailwindAnalyzer(),
            false,
            false
        );

        $this->assertSame([], $disabled->analyse());

        $javascriptOnly = new ExtremeAnalyzer(
            new ProjectScanner($root),
            new JavaScriptAnalyzer(),
            new TailwindAnalyzer(),
            true,
            false
        );
        $identifiers = array_map(
            static fn($diagnostic): string => $diagnostic->identifier,
            $javascriptOnly->analyse()
        );

        $this->assertContains('blockstudio.javascript.syntax', $identifiers);
        $this->assertNotContains('blockstudio.tailwind.unknownUtility', $identifiers);
    }
}
