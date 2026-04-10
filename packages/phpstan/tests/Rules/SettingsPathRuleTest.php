<?php

declare(strict_types=1);

namespace Blockstudio\PHPStan\Tests\Rules;

use Blockstudio\PHPStan\Rules\SettingsPathRule;
use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;

/**
 * @extends RuleTestCase<SettingsPathRule>
 */
final class SettingsPathRuleTest extends RuleTestCase
{
    protected function getRule(): Rule
    {
        return new SettingsPathRule();
    }

    public function test_known_paths_pass(): void
    {
        $this->analyse(
            [__DIR__ . '/data/settings-path-valid.php'],
            []
        );
    }

    public function test_unknown_path_reports_error(): void
    {
        $this->analyse(
            [__DIR__ . '/data/settings-path-unknown.php'],
            [
                [
                    'Settings path "not/a/real/path" is not a known Blockstudio setting.',
                    5,
                ],
            ]
        );
    }

    public function test_typo_includes_suggestion(): void
    {
        $this->analyse(
            [__DIR__ . '/data/settings-path-typo.php'],
            [
                [
                    'Settings path "tailwind/enabld" is not a known Blockstudio setting. Did you mean "tailwind/enabled"?',
                    5,
                ],
            ]
        );
    }

    public function test_non_string_argument_skipped(): void
    {
        $this->analyse(
            [__DIR__ . '/data/settings-path-variable.php'],
            []
        );
    }
}
