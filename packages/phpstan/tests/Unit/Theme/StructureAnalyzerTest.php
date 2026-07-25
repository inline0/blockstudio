<?php

declare(strict_types=1);

namespace Blockstudio\PHPStan\Tests\Unit\Theme;

use Blockstudio\PHPStan\Theme\ProjectScanner;
use Blockstudio\PHPStan\Theme\StructureAnalyzer;
use PHPUnit\Framework\TestCase;

final class StructureAnalyzerTest extends TestCase
{
    public function test_valid_theme_contract_passes(): void
    {
        $root = $this->fixture('theme-valid');
        $analyzer = new StructureAnalyzer(new ProjectScanner($root));

        $this->assertSame([], $analyzer->analyse());
    }

    public function test_invalid_theme_contract_reports_stable_identifiers(): void
    {
        $root = $this->fixture('theme-invalid');
        $analyzer = new StructureAnalyzer(new ProjectScanner($root));

        $this->assertSame(
            [
                'blockstudio.field.default',
                'blockstudio.field.default',
                'blockstudio.field.default',
                'blockstudio.field.repeaterBounds',
                'blockstudio.theme.asset.missing',
                'blockstudio.theme.asset.manualEnqueue',
                'blockstudio.theme.asset.selectorScope',
                'blockstudio.theme.style.header',
            ],
            array_map(
                static fn($diagnostic): string => $diagnostic->identifier,
                $analyzer->analyse()
            )
        );
    }

    public function test_missing_root_is_reported(): void
    {
        $root = $this->fixture('missing-theme');
        $analyzer = new StructureAnalyzer(
            new ProjectScanner(dirname($root), [$root])
        );

        $this->assertSame(
            ['blockstudio.theme.root.missing'],
            array_map(
                static fn($diagnostic): string => $diagnostic->identifier,
                $analyzer->analyse()
            )
        );
    }

    private function fixture(string $name): string
    {
        return dirname(__DIR__, 2) . '/fixtures/' . $name;
    }
}
