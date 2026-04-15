<?php

declare(strict_types=1);

namespace Blockstudio\PHPStan\Tests\Rules;

use Blockstudio\PHPStan\Rules\PageJsonShapeRule;
use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;
use ReflectionClass;

/** @extends RuleTestCase<PageJsonShapeRule> */
final class PageJsonShapeRuleTest extends RuleTestCase
{
    private string $fixtureDir = '';

    protected function setUp(): void
    {
        parent::setUp();
        $r = new ReflectionClass(PageJsonShapeRule::class);
        $p = $r->getProperty('validatedPaths');
        $p->setAccessible(true);
        $p->setValue(null, []);
    }

    protected function getRule(): Rule
    {
        return new PageJsonShapeRule($this->fixtureDir);
    }

    public function test_valid_page_json_passes(): void
    {
        $this->fixtureDir = __DIR__ . '/data/page-json/valid';
        $this->analyse([$this->fixtureDir . '/blockstudio/hero/index.php'], []);
    }

    public function test_invalid_page_json_reports_errors(): void
    {
        $this->fixtureDir = __DIR__ . '/data/page-json/invalid';
        $errors = array_map(
            static fn($e) => $e->getMessage(),
            $this->gatherAnalyserErrors([$this->fixtureDir . '/blockstudio/hero/index.php'])
        );
        $found = false;
        foreach ($errors as $e) {
            if (str_contains($e, 'missing') || str_contains($e, 'invalid')) {
                $found = true;
                break;
            }
        }
        $this->assertTrue($found, 'Expected page.json errors. Got: ' . implode("\n", $errors));
    }
}
