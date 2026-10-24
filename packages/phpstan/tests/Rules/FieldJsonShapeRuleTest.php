<?php

declare(strict_types=1);

namespace Blockstudio\PHPStan\Tests\Rules;

use Blockstudio\PHPStan\Rules\FieldJsonShapeRule;
use Blockstudio\PHPStan\Schema\CustomFieldResolver;
use Blockstudio\PHPStan\Schema\ProjectScanner;
use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;
use ReflectionClass;

/** @extends RuleTestCase<FieldJsonShapeRule> */
final class FieldJsonShapeRuleTest extends RuleTestCase
{
    private string $fixtureDir = '';

    protected function setUp(): void
    {
        parent::setUp();
        $r = new ReflectionClass(FieldJsonShapeRule::class);
        $p = $r->getProperty('validatedPaths');
        $p->setAccessible(true);
        $p->setValue(null, []);
    }

    protected function getRule(): Rule
    {
        $scanner = new ProjectScanner($this->fixtureDir);
        return new FieldJsonShapeRule($scanner, new CustomFieldResolver($scanner));
    }

    public function test_valid_field_json_passes(): void
    {
        $this->fixtureDir = __DIR__ . '/data/field-json/valid';
        $this->analyse([$this->fixtureDir . '/blockstudio/hero/index.php'], []);
    }

    public function test_invalid_field_json_reports_errors(): void
    {
        $this->fixtureDir = __DIR__ . '/data/field-json/invalid';
        $errors = array_map(
            static fn($e) => $e->getMessage(),
            $this->gatherAnalyserErrors([$this->fixtureDir . '/blockstudio/hero/index.php'])
        );
        $found = false;
        foreach ($errors as $e) {
            if (str_contains($e, 'missing') || str_contains($e, 'unknown type')) {
                $found = true;
                break;
            }
        }
        $this->assertTrue($found, 'Expected field.json errors. Got: ' . implode("\n", $errors));
    }

    public function test_nested_custom_field_cycle_is_reported(): void
    {
        $this->fixtureDir = __DIR__ . '/data/field-json/cycle';
        $errors = array_map(
            static fn($e) => $e->getMessage(),
            $this->gatherAnalyserErrors([
                $this->fixtureDir . '/blockstudio/hero/index.php',
            ])
        );

        $this->assertNotEmpty(
            array_filter(
                $errors,
                static fn(string $error): bool => str_contains(
                    $error,
                    'creates a definition cycle'
                )
            ),
            'Expected a custom field cycle error. Got: ' . implode("\n", $errors)
        );
    }

    public function test_tabs_in_repeater_reported(): void
    {
        $this->fixtureDir = __DIR__ . '/data/field-json/repeater-tabs';
        $this->assertSame(
            ['field.json field "sections" of type "tabs" is not supported inside a repeater: fields/spacing/field.json'],
            $this->gatherNestingErrors()
        );
    }

    public function test_tabs_in_anonymous_group_in_repeater_reported(): void
    {
        $this->fixtureDir = __DIR__ . '/data/field-json/repeater-group-tabs';
        $this->assertSame(
            ['field.json field "attributes[1]" of type "tabs" is not supported inside a repeater: fields/spacing/field.json'],
            $this->gatherNestingErrors()
        );
    }

    public function test_tabs_in_nested_repeater_reported(): void
    {
        $this->fixtureDir = __DIR__ . '/data/field-json/repeater-nested-tabs';
        $this->assertSame(
            ['field.json field "attributes[0]" of type "tabs" is not supported inside a repeater: fields/spacing/field.json'],
            $this->gatherNestingErrors()
        );
    }

    public function test_tabs_outside_repeater_passes(): void
    {
        $this->fixtureDir = __DIR__ . '/data/field-json/tabs-outside-repeater';
        $this->analyse([$this->fixtureDir . '/blockstudio/hero/index.php'], []);
    }

    /**
     * @return list<string>
     */
    private function gatherNestingErrors(): array
    {
        $errors = array_map(
            static fn($e) => $e->getMessage(),
            $this->gatherAnalyserErrors([$this->fixtureDir . '/blockstudio/hero/index.php'])
        );

        return array_values(array_filter(
            $errors,
            static fn(string $error): bool => str_contains($error, 'is not supported inside a repeater')
        ));
    }
}
