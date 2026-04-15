<?php

declare(strict_types=1);

namespace Blockstudio\PHPStan\Tests\Rules;

use Blockstudio\PHPStan\Reflection\FieldTypeRegistry;
use Blockstudio\PHPStan\Rules\TemplateAttributeAccessRule;
use Blockstudio\PHPStan\Schema\BlockJsonReader;
use Blockstudio\PHPStan\Schema\ProjectScanner;
use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;
use ReflectionClass;

/**
 * @extends RuleTestCase<TemplateAttributeAccessRule>
 */
final class TemplateAttributeAccessRuleTest extends RuleTestCase
{
    private string $fixtureDir = '';

    protected function setUp(): void
    {
        parent::setUp();
        $reflection = new ReflectionClass(TemplateAttributeAccessRule::class);
        $property = $reflection->getProperty('scannedFiles');
        $property->setAccessible(true);
        $property->setValue(null, []);
    }

    protected function getRule(): Rule
    {
        return new TemplateAttributeAccessRule(
            new ProjectScanner($this->fixtureDir),
            new BlockJsonReader(new FieldTypeRegistry())
        );
    }

    public function test_valid_twig_passes(): void
    {
        $this->fixtureDir = __DIR__ . '/data/template-access/valid-twig';
        $this->analyse(
            [$this->fixtureDir . '/blockstudio/hero/index.php'],
            []
        );
    }

    public function test_invalid_twig_reports_errors(): void
    {
        $this->fixtureDir = __DIR__ . '/data/template-access/invalid-twig';
        $errors = $this->gatherErrorMessages(
            [$this->fixtureDir . '/blockstudio/hero/index.php']
        );
        $this->assertContainsErrorMessage('"titl"', $errors);
        $this->assertContainsErrorMessage('"nonexistent"', $errors);
    }

    public function test_valid_blade_passes(): void
    {
        $this->fixtureDir = __DIR__ . '/data/template-access/valid-blade';
        $this->analyse(
            [$this->fixtureDir . '/blockstudio/hero/index.php'],
            []
        );
    }

    public function test_invalid_blade_reports_errors(): void
    {
        $this->fixtureDir = __DIR__ . '/data/template-access/invalid-blade';
        $errors = $this->gatherErrorMessages(
            [$this->fixtureDir . '/blockstudio/hero/index.php']
        );
        $this->assertContainsErrorMessage('"typo"', $errors);
        $this->assertContainsErrorMessage('"nonexistent"', $errors);
    }

    public function test_twig_typo_includes_suggestion(): void
    {
        $this->fixtureDir = __DIR__ . '/data/template-access/invalid-twig';
        $errors = $this->gatherErrorMessages(
            [$this->fixtureDir . '/blockstudio/hero/index.php']
        );
        $this->assertContainsErrorMessage('Did you mean "title"', $errors);
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
