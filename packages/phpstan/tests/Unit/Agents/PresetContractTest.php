<?php

declare(strict_types=1);

namespace Blockstudio\PHPStan\Tests\Unit\Agents;

use Blockstudio\PHPStan\Agents\PresetContract;
use Blockstudio\PHPStan\Rules\ExtremeForbiddenFunctionRule;
use PHPUnit\Framework\TestCase;

final class PresetContractTest extends TestCase
{
    public function testBaseCarriesOnlyTheBaseLayer(): void
    {
        $contract = PresetContract::forPreset('base');

        self::assertSame(['base'], $this->labels($contract));
        self::assertSame([], $contract->strictness());
        self::assertStringNotContainsString(
            'Theme Name',
            implode("\n", $this->contracts($contract))
        );
    }

    public function testExtremeThemeAddsTheStricterLayers(): void
    {
        $contract = PresetContract::forPreset('extreme-theme');
        $contracts = implode("\n", $this->contracts($contract));

        self::assertSame(
            ['base', 'theme', 'extreme-theme'],
            $this->labels($contract)
        );
        self::assertStringContainsString('Theme Name', $contracts);
        self::assertStringContainsString('wp_enqueue_script()', $contracts);
        self::assertStringContainsString('esc_html()', $contracts);
        self::assertStringContainsString('$wpdb->insert()', $contracts);
        self::assertSame(
            [
                'PHPStan runs at level `max`.',
                'Stricter PHPStan behaviour is on: `checkBenevolentUnionTypes`,'
                . ' `checkExplicitMixed`, `checkFunctionNameCase`,'
                . ' `checkInternalClassCaseSensitivity`,'
                . ' `checkMissingCallableSignature`,'
                . ' `checkMissingOverrideMethodAttribute`,'
                . ' `checkTooWideReturnTypesInProtectedAndPublicMethods`,'
                . ' `reportMaybesInMethodSignatures`,'
                . ' `reportMaybesInPropertyPhpDocTypes`,'
                . ' `reportStaticMethodSignatures`.',
                'Analysis reaches beyond PHP: `blockstudioExtremeJavaScript`,'
                . ' `blockstudioExtremeTailwind`.',
            ],
            $contract->strictness()
        );
    }

    public function testWordPressRenderAddsTheLiveProbe(): void
    {
        $contract = PresetContract::forPreset('wordpress-render');

        self::assertSame(
            ['base', 'theme', 'extreme-theme', 'wordpress-render'],
            $this->labels($contract)
        );
        self::assertStringContainsString(
            '{"ok":true}',
            implode("\n", $this->contracts($contract))
        );
    }

    public function testEveryRegisteredRuleIsDescribed(): void
    {
        $package = dirname(__DIR__, 3);
        $registered = [];

        foreach (glob($package . '/*.neon') ?: [] as $preset) {
            preg_match_all(
                '/class:\s*(Blockstudio\\\\PHPStan\\\\Rules\\\\[A-Za-z0-9_]+)/',
                (string) file_get_contents($preset),
                $matches
            );
            foreach ($matches[1] as $class) {
                $registered[$class] = true;
            }
        }

        $described = PresetContract::describedRules();
        sort($described);
        $registered = array_keys($registered);
        sort($registered);

        self::assertNotSame([], $registered);
        self::assertSame($registered, $described);
    }

    public function testTheForbiddenFunctionListMatchesTheRule(): void
    {
        $contracts = implode(
            "\n",
            $this->contracts(PresetContract::forPreset('extreme-theme'))
        );
        $constant = new \ReflectionClassConstant(
            ExtremeForbiddenFunctionRule::class,
            'FORBIDDEN_FUNCTIONS'
        );
        $functions = $constant->getValue();

        self::assertIsArray($functions);
        foreach ($functions as $function) {
            self::assertStringContainsString(
                sprintf('`%s()`', $function),
                $contracts
            );
        }
    }

    public function testAnUnknownPresetIsRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        PresetContract::forPreset('nonexistent');
    }

    /**
     * @return list<string>
     */
    private function labels(PresetContract $contract): array
    {
        return array_map(
            static fn(array $layer): string => $layer['label'],
            $contract->layers()
        );
    }

    /**
     * @return list<string>
     */
    private function contracts(PresetContract $contract): array
    {
        $contracts = [];
        foreach ($contract->layers() as $layer) {
            foreach ($layer['contracts'] as $line) {
                $contracts[] = $line;
            }
        }

        return $contracts;
    }
}
