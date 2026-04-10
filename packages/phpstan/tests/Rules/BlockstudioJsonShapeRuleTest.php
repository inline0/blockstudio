<?php

declare(strict_types=1);

namespace Blockstudio\PHPStan\Tests\Rules;

use Blockstudio\PHPStan\Rules\BlockstudioJsonShapeRule;
use Blockstudio\PHPStan\Schema\BlockstudioJsonReader;
use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;
use ReflectionClass;

/**
 * @extends RuleTestCase<BlockstudioJsonShapeRule>
 */
final class BlockstudioJsonShapeRuleTest extends RuleTestCase
{
    private string $fixtureDir = '';

    protected function setUp(): void
    {
        parent::setUp();
        $reflection = new ReflectionClass(BlockstudioJsonShapeRule::class);
        $property = $reflection->getProperty('validatedPaths');
        $property->setAccessible(true);
        $property->setValue(null, []);
    }

    protected function getRule(): Rule
    {
        return new BlockstudioJsonShapeRule(new BlockstudioJsonReader(), $this->fixtureDir);
    }

    public function test_valid_blockstudio_json_passes(): void
    {
        $this->fixtureDir = __DIR__ . '/data/blockstudio-json/valid';
        $this->analyse(
            [$this->fixtureDir . '/trigger.php'],
            []
        );
    }

    public function test_tailwind_shorthand_boolean_reported(): void
    {
        $this->fixtureDir = __DIR__ . '/data/blockstudio-json/tailwind-shorthand';
        $errors = $this->gatherErrorMessages([$this->fixtureDir . '/trigger.php']);
        $this->assertContainsErrorMessage('"tailwind" shorthand boolean is not supported', $errors);
    }

    public function test_assets_reset_shorthand_boolean_reported(): void
    {
        $this->fixtureDir = __DIR__ . '/data/blockstudio-json/assets-shorthand';
        $errors = $this->gatherErrorMessages([$this->fixtureDir . '/trigger.php']);
        $this->assertContainsErrorMessage('"assets.reset" shorthand boolean is not supported', $errors);
    }

    public function test_no_blockstudio_json_passes(): void
    {
        $this->fixtureDir = __DIR__ . '/data/blockstudio-json/none';
        $this->analyse(
            [$this->fixtureDir . '/trigger.php'],
            []
        );
    }

    /**
     * @param list<string> $files
     * @return list<string>
     */
    private function gatherErrorMessages(array $files): array
    {
        $errors = $this->gatherAnalyserErrors($files);
        return array_map(static fn($e) => $e->getMessage(), $errors);
    }

    /**
     * @param list<string> $errors
     */
    private function assertContainsErrorMessage(string $needle, array $errors): void
    {
        foreach ($errors as $error) {
            if (str_contains($error, $needle)) {
                $this->assertTrue(true);
                return;
            }
        }
        $this->fail("No error contained '$needle'. Got: " . implode("\n", $errors));
    }
}
