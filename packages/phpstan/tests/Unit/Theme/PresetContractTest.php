<?php

declare(strict_types=1);

namespace Blockstudio\PHPStan\Tests\Unit\Theme;

use PHPUnit\Framework\TestCase;

final class PresetContractTest extends TestCase
{
    public function test_base_extension_does_not_enable_opt_in_layers(): void
    {
        $package = dirname(__DIR__, 3);
        $extension = file_get_contents($package . '/extension.neon');
        $base = file_get_contents($package . '/base.neon');

        $this->assertIsString($extension);
        $this->assertIsString($base);
        $this->assertSame("includes:\n  - extension.neon\n", $base);
        $this->assertStringNotContainsString('Theme\\', $extension);
        $this->assertStringNotContainsString('blockstudioTheme', $extension);
        $this->assertStringNotContainsString('blockstudioExtreme', $extension);
    }

    public function test_composer_contract_exposes_canonical_binary_and_dependencies(): void
    {
        $package = dirname(__DIR__, 3);
        $composer = json_decode(
            (string) file_get_contents($package . '/composer.json'),
            true,
            512,
            JSON_THROW_ON_ERROR
        );

        $this->assertSame(
            ['bin/blockstudio-phpstan', 'bin/blockstudio-githooks'],
            $composer['bin'] ?? null
        );
        $this->assertSame('0.4.1', $composer['require']['phasis/phasis'] ?? null);
        $this->assertSame(
            '^1.5',
            $composer['require']['tailwindphp/tailwindphp'] ?? null
        );
    }
}
