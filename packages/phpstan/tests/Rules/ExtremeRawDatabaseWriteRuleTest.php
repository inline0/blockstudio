<?php

declare(strict_types=1);

namespace Blockstudio\PHPStan\Tests\Rules;

use Blockstudio\PHPStan\Rules\ExtremeRawDatabaseWriteRule;
use Blockstudio\PHPStan\Theme\ProjectScanner;
use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;

/** @extends RuleTestCase<ExtremeRawDatabaseWriteRule> */
final class ExtremeRawDatabaseWriteRuleTest extends RuleTestCase
{
    protected function getRule(): Rule
    {
        $root = __DIR__ . '/data';
        return new ExtremeRawDatabaseWriteRule(new ProjectScanner($root));
    }

    public function test_raw_writes_are_rejected_but_reads_pass(): void
    {
        $this->analyse(
            [__DIR__ . '/data/extreme-php.php'],
            [
                [
                    'Use a bounded WordPress data API instead of raw $wpdb->insert() writes.',
                    6,
                ],
            ]
        );
    }
}
