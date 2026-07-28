<?php

declare(strict_types=1);

namespace Blockstudio\PHPStan\Tests\Rules;

use Blockstudio\PHPStan\Rules\WordPressRenderRule;
use Blockstudio\PHPStan\Theme\CommandRunner;
use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;

/** @extends RuleTestCase<WordPressRenderRule> */
final class WordPressRenderRuleTest extends RuleTestCase
{
    private string $payload = '{"ok":true}';
    private int $exitCode = 0;
    private ?string $workingDirectory = null;

    protected function getRule(): Rule
    {
        $script = sprintf(
            'fwrite(STDOUT, %s); exit(%d);',
            var_export($this->payload, true),
            $this->exitCode
        );

        return new WordPressRenderRule(
            [PHP_BINARY, '-r', $script],
            $this->workingDirectory ?? __DIR__ . '/data',
            5,
            new CommandRunner()
        );
    }

    public function test_successful_json_probe_passes(): void
    {
        $this->analyse([__DIR__ . '/data/extreme-php.php'], []);
    }

    public function test_failed_json_probe_reports_supplied_location(): void
    {
        $this->payload = json_encode([
            'ok' => false,
            'message' => 'Rendered block failed.',
            'file' => __DIR__ . '/data/render-source.php',
            'line' => 12,
        ], JSON_THROW_ON_ERROR);

        $errors = $this->gatherAnalyserErrors([
            __DIR__ . '/data/extreme-php.php',
        ]);

        $this->assertCount(1, $errors);
        $this->assertSame('Rendered block failed.', $errors[0]->getMessage());
        $this->assertSame(12, $errors[0]->getLine());
        $this->assertSame(
            __DIR__ . '/data/render-source.php',
            $errors[0]->getFilePath()
        );
    }

    public function test_invalid_json_is_reported(): void
    {
        $this->payload = 'not-json';
        $errors = $this->gatherAnalyserErrors([
            __DIR__ . '/data/extreme-php.php',
        ]);
        $this->assertSame(
            'Live render command must print a JSON object with a boolean "ok" field.',
            $errors[0]->getMessage()
        );
    }

    public function test_nonzero_exit_is_reported(): void
    {
        $this->payload = '';
        $this->exitCode = 3;
        $errors = $this->gatherAnalyserErrors([
            __DIR__ . '/data/extreme-php.php',
        ]);
        $this->assertStringContainsString(
            'failed with exit code 3',
            $errors[0]->getMessage()
        );
    }

    public function test_missing_working_directory_is_reported_without_execution(): void
    {
        $this->workingDirectory = __DIR__ . '/data/missing-directory';
        $errors = $this->gatherAnalyserErrors([
            __DIR__ . '/data/extreme-php.php',
        ]);

        $this->assertStringContainsString(
            'working directory does not exist',
            $errors[0]->getMessage()
        );
    }
}
