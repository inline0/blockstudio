<?php

declare(strict_types=1);

namespace Blockstudio\PHPStan\Tests\Rules;

use Blockstudio\PHPStan\Rules\HookCallbackRule;
use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;

/**
 * @extends RuleTestCase<HookCallbackRule>
 */
final class HookCallbackRuleTest extends RuleTestCase
{
    protected function getRule(): Rule
    {
        return new HookCallbackRule();
    }

    public function test_known_hooks_pass(): void
    {
        $this->analyse(
            [__DIR__ . '/data/hook-callback-valid.php'],
            []
        );
    }

    public function test_unknown_hook_reports_error(): void
    {
        $this->analyse(
            [__DIR__ . '/data/hook-callback-unknown.php'],
            [
                [
                    'Unknown Blockstudio hook "blockstudio/typo". Did you mean "blockstudio/rpc"?',
                    3,
                ],
            ]
        );
    }

    public function test_typo_includes_did_you_mean_suggestion(): void
    {
        $this->analyse(
            [__DIR__ . '/data/hook-callback-typo.php'],
            [
                [
                    'Unknown Blockstudio hook "blockstudio/rendrr". Did you mean "blockstudio/render"?',
                    3,
                ],
            ]
        );
    }

    public function test_dynamic_settings_hooks_pass(): void
    {
        $this->analyse(
            [__DIR__ . '/data/hook-callback-settings.php'],
            []
        );
    }

    public function test_non_blockstudio_hooks_pass(): void
    {
        $this->analyse(
            [__DIR__ . '/data/hook-callback-non-bs.php'],
            []
        );
    }
}
