<?php

declare(strict_types=1);

namespace Blockstudio\PHPStan\Tests\Rules;

use Blockstudio\PHPStan\Rules\ExtremeForbiddenFunctionRule;
use Blockstudio\PHPStan\Theme\ProjectScanner;
use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;

/** @extends RuleTestCase<ExtremeForbiddenFunctionRule> */
final class ExtremeForbiddenFunctionRuleTest extends RuleTestCase
{
    private string $root = '';

    protected function getRule(): Rule
    {
        $root = $this->root !== '' ? $this->root : __DIR__ . '/data';
        return new ExtremeForbiddenFunctionRule(new ProjectScanner($root));
    }

    public function test_eval_and_shell_execution_are_rejected(): void
    {
        $this->analyse(
            [__DIR__ . '/data/extreme-php.php'],
            [
                [
                    'Theme code must not call "eval()"; use a bounded platform API instead.',
                    3,
                ],
                [
                    'Theme code must not call "exec()"; use a bounded platform API instead.',
                    4,
                ],
            ]
        );
    }

    public function test_files_outside_configured_roots_are_ignored(): void
    {
        $this->root = dirname(__DIR__) . '/fixtures/theme-minimal';

        $this->analyse([__DIR__ . '/data/extreme-outside.php'], []);
    }
}
