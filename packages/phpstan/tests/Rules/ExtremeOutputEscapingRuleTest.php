<?php

declare(strict_types=1);

namespace Blockstudio\PHPStan\Tests\Rules;

use Blockstudio\PHPStan\Rules\ExtremeOutputEscapingRule;
use Blockstudio\PHPStan\Theme\ProjectScanner;
use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;

/** @extends RuleTestCase<ExtremeOutputEscapingRule> */
final class ExtremeOutputEscapingRuleTest extends RuleTestCase
{
    protected function getRule(): Rule
    {
        $root = __DIR__ . '/data';
        return new ExtremeOutputEscapingRule(new ProjectScanner($root));
    }

    public function test_dynamic_attributes_require_escaping(): void
    {
        $message = 'Dynamic Blockstudio attributes must be escaped before output.';
        $this->analyse(
            [__DIR__ . '/data/extreme-php.php'],
            [
                [$message, 7],
                [$message, 9],
            ]
        );
    }
}
