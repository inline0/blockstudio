<?php

declare(strict_types=1);

namespace Blockstudio\PHPStan\Tests\Rules;

use Blockstudio\PHPStan\Rules\CronSchemaShapeRule;
use Blockstudio\PHPStan\Schema\ProjectScanner;
use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;
use ReflectionClass;

/** @extends RuleTestCase<CronSchemaShapeRule> */
final class CronSchemaShapeRuleTest extends RuleTestCase
{
    private string $fixtureDir = '';

    protected function setUp(): void
    {
        parent::setUp();
        $r = new ReflectionClass(CronSchemaShapeRule::class);
        $p = $r->getProperty('validatedPaths');
        $p->setAccessible(true);
        $p->setValue(null, []);
    }

    protected function getRule(): Rule
    {
        return new CronSchemaShapeRule(new ProjectScanner($this->fixtureDir));
    }

    public function test_valid_cron_passes(): void
    {
        $this->fixtureDir = __DIR__ . '/data/cron-shape/valid';
        $this->analyse([$this->fixtureDir . '/blockstudio/app/index.php'], []);
    }

    public function test_missing_schedule_reported(): void
    {
        $this->fixtureDir = __DIR__ . '/data/cron-shape/missing-schedule';
        $errors = array_map(
            static fn($e) => $e->getMessage(),
            $this->gatherAnalyserErrors([$this->fixtureDir . '/blockstudio/app/index.php'])
        );
        $found = false;
        foreach ($errors as $e) {
            if (str_contains($e, 'missing "schedule"')) {
                $found = true;
                break;
            }
        }
        $this->assertTrue($found, 'Expected missing schedule error. Got: ' . implode("\n", $errors));
    }

    public function test_missing_callback_reported(): void
    {
        $this->fixtureDir = __DIR__ . '/data/cron-shape/missing-callback';
        $errors = array_map(
            static fn($e) => $e->getMessage(),
            $this->gatherAnalyserErrors([$this->fixtureDir . '/blockstudio/app/index.php'])
        );
        $found = false;
        foreach ($errors as $e) {
            if (str_contains($e, 'missing "callback"')) {
                $found = true;
                break;
            }
        }
        $this->assertTrue($found, 'Expected missing callback error. Got: ' . implode("\n", $errors));
    }
}
