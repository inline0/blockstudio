<?php

declare(strict_types=1);

namespace Blockstudio\PHPStan\Tests\Unit\Theme;

use Blockstudio\PHPStan\Theme\JavaScriptAnalyzer;
use Blockstudio\PHPStan\Theme\ProjectScanner;
use PHPUnit\Framework\TestCase;

final class JavaScriptAnalyzerTest extends TestCase
{
    public function test_valid_javascript_and_interactivity_contract_passes(): void
    {
        $root = $this->fixture('theme-valid');
        $analyzer = new JavaScriptAnalyzer();

        $this->assertSame(
            [],
            $analyzer->analyse(new ProjectScanner($root))
        );
    }

    public function test_invalid_contract_ports_each_generic_diagnostic(): void
    {
        $root = $this->fixture('theme-invalid');
        $analyzer = new JavaScriptAnalyzer();
        $identifiers = array_values(array_unique(array_map(
            static fn($diagnostic): string => $diagnostic->identifier,
            $analyzer->analyse(new ProjectScanner($root))
        )));
        sort($identifiers);

        $this->assertSame(
            [
                'blockstudio.interactivity.binding',
                'blockstudio.interactivity.context',
                'blockstudio.interactivity.derivedState',
                'blockstudio.interactivity.handler',
                'blockstudio.interactivity.import',
                'blockstudio.interactivity.moduleImport',
                'blockstudio.interactivity.namespace',
                'blockstudio.interactivity.orphan',
                'blockstudio.interactivity.scopedDom',
                'blockstudio.javascript.bannedApi',
                'blockstudio.javascript.debugOutput',
                'blockstudio.javascript.domContract',
                'blockstudio.javascript.importSpecifier',
                'blockstudio.javascript.initShape',
                'blockstudio.javascript.leakedGlobal',
                'blockstudio.javascript.listenerCleanup',
                'blockstudio.javascript.reducedMotion',
                'blockstudio.javascript.rootGuard',
                'blockstudio.javascript.syntax',
            ],
            $identifiers
        );
    }

    private function fixture(string $name): string
    {
        return dirname(__DIR__, 2) . '/fixtures/' . $name;
    }
}
