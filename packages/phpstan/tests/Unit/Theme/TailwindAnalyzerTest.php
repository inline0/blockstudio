<?php

declare(strict_types=1);

namespace Blockstudio\PHPStan\Tests\Unit\Theme;

use Blockstudio\PHPStan\Theme\ProjectScanner;
use Blockstudio\PHPStan\Theme\TailwindAnalyzer;
use PHPUnit\Framework\TestCase;

final class TailwindAnalyzerTest extends TestCase
{
    public function test_valid_tailwind_contract_passes(): void
    {
        $root = $this->fixture('theme-valid');
        $analyzer = new TailwindAnalyzer();

        $this->assertSame(
            [],
            $analyzer->analyse(new ProjectScanner($root))
        );
    }

    public function test_unknown_utility_and_semantic_token_are_reported(): void
    {
        $root = $this->fixture('theme-invalid');
        $analyzer = new TailwindAnalyzer();
        $identifiers = array_values(array_unique(array_map(
            static fn($diagnostic): string => $diagnostic->identifier,
            $analyzer->analyse(new ProjectScanner($root))
        )));
        sort($identifiers);

        $this->assertSame(
            [
                'blockstudio.tailwind.semanticToken',
                'blockstudio.tailwind.unknownUtility',
            ],
            $identifiers
        );
    }

    private function fixture(string $name): string
    {
        return dirname(__DIR__, 2) . '/fixtures/' . $name;
    }
}
