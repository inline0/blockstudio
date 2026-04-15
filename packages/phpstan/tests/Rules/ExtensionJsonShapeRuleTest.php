<?php

declare(strict_types=1);

namespace Blockstudio\PHPStan\Tests\Rules;

use Blockstudio\PHPStan\Rules\ExtensionJsonShapeRule;
use Blockstudio\PHPStan\Schema\ProjectScanner;
use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;
use ReflectionClass;

/** @extends RuleTestCase<ExtensionJsonShapeRule> */
final class ExtensionJsonShapeRuleTest extends RuleTestCase
{
    private string $fixtureDir = '';

    protected function setUp(): void
    {
        parent::setUp();
        $r = new ReflectionClass(ExtensionJsonShapeRule::class);
        $p = $r->getProperty('validatedPaths');
        $p->setAccessible(true);
        $p->setValue(null, []);
    }

    protected function getRule(): Rule
    {
        return new ExtensionJsonShapeRule(new ProjectScanner($this->fixtureDir));
    }

    public function test_valid_extension_passes(): void
    {
        $this->fixtureDir = __DIR__ . '/data/ext-json/valid';
        $this->analyse([$this->fixtureDir . '/blockstudio/hero/index.php'], []);
    }

    public function test_invalid_extension_reports_errors(): void
    {
        $this->fixtureDir = __DIR__ . '/data/ext-json/invalid';
        $errors = array_map(
            static fn($e) => $e->getMessage(),
            $this->gatherAnalyserErrors([$this->fixtureDir . '/blockstudio/hero/index.php'])
        );
        $found = false;
        foreach ($errors as $e) {
            if (str_contains($e, 'missing')) {
                $found = true;
                break;
            }
        }
        $this->assertTrue($found, 'Expected extension errors. Got: ' . implode("\n", $errors));
    }
}
