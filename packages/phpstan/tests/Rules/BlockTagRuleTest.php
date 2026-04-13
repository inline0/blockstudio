<?php

declare(strict_types=1);

namespace Blockstudio\PHPStan\Tests\Rules;

use Blockstudio\PHPStan\Reflection\FieldTypeRegistry;
use Blockstudio\PHPStan\Rules\BlockTagRule;
use Blockstudio\PHPStan\Schema\BlockJsonReader;
use Blockstudio\PHPStan\Schema\ProjectScanner;
use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;
use ReflectionClass;

/**
 * @extends RuleTestCase<BlockTagRule>
 */
final class BlockTagRuleTest extends RuleTestCase
{
    private string $fixtureDir = '';

    protected function setUp(): void
    {
        parent::setUp();
        $reflection = new ReflectionClass(BlockTagRule::class);
        $property = $reflection->getProperty('scannedFiles');
        $property->setAccessible(true);
        $property->setValue(null, []);
    }

    protected function getRule(): Rule
    {
        return new BlockTagRule(
            new ProjectScanner($this->fixtureDir),
            new BlockJsonReader(new FieldTypeRegistry())
        );
    }

    public function test_valid_block_tags_pass(): void
    {
        $this->fixtureDir = __DIR__ . '/data/block-tags/valid';
        $this->analyse(
            [__DIR__ . '/data/block-tags/valid/blockstudio/hero/index.php'],
            []
        );
    }

    public function test_unknown_block_name_reported(): void
    {
        $this->fixtureDir = __DIR__ . '/data/block-tags/unknown-block';
        $errors = $this->gatherErrorMessages(
            [__DIR__ . '/data/block-tags/unknown-block/blockstudio/hero/index.php']
        );
        $this->assertContainsErrorMessage('unknown block "mytheme/nonexistent"', $errors);
        $this->assertContainsErrorMessage('unknown block "mytheme/doesnotexist"', $errors);
    }

    public function test_unknown_attribute_reported(): void
    {
        $this->fixtureDir = __DIR__ . '/data/block-tags/unknown-attr';
        $errors = $this->gatherErrorMessages(
            [__DIR__ . '/data/block-tags/unknown-attr/blockstudio/hero/index.php']
        );
        $this->assertContainsErrorMessage('unknown attribute "badattr"', $errors);
        $this->assertContainsErrorMessage('unknown attribute "nonexistent"', $errors);
    }

    public function test_data_attributes_are_passthrough(): void
    {
        $this->fixtureDir = __DIR__ . '/data/block-tags/unknown-attr';
        $errors = $this->gatherErrorMessages(
            [__DIR__ . '/data/block-tags/unknown-attr/blockstudio/hero/index.php']
        );
        foreach ($errors as $error) {
            $this->assertStringNotContainsString('data-custom', $error);
        }
    }

    public function test_core_blocks_are_always_valid(): void
    {
        $this->fixtureDir = __DIR__ . '/data/block-tags/valid';
        $errors = $this->gatherErrorMessages(
            [__DIR__ . '/data/block-tags/valid/blockstudio/hero/index.php']
        );
        $this->assertEmpty(
            array_filter($errors, static fn($e) => str_contains($e, 'core/separator'))
        );
    }

    public function test_twig_templates_are_scanned(): void
    {
        $this->fixtureDir = __DIR__ . '/data/block-tags/twig';
        $errors = $this->gatherErrorMessages(
            [__DIR__ . '/data/block-tags/twig/blockstudio/hero/index.php']
        );
        $this->assertContainsErrorMessage('unknown block "mytheme/nonexistent-twig"', $errors);
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
