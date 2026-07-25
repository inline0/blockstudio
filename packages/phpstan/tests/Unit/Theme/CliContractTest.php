<?php

declare(strict_types=1);

namespace Blockstudio\PHPStan\Tests\Unit\Theme;

use Blockstudio\PHPStan\Theme\CommandRunner;
use PHPUnit\Framework\TestCase;

final class CliContractTest extends TestCase
{
    public function test_help_and_usage_exit_codes_are_stable(): void
    {
        $package = dirname(__DIR__, 3);
        $runner = new CommandRunner();

        $help = $runner->run(
            [PHP_BINARY, $package . '/bin/blockstudio-phpstan', '--help'],
            $package,
            10
        );
        $this->assertSame(0, $help->exitCode);
        $this->assertStringContainsString('Exit codes:', $help->stdout);

        $invalid = $runner->run(
            [
                PHP_BINARY,
                $package . '/bin/blockstudio-phpstan',
                '--preset',
                'missing',
            ],
            $package,
            10
        );
        $this->assertSame(2, $invalid->exitCode);
        $this->assertStringContainsString('Unknown preset', $invalid->stderr);
    }

    public function test_base_theme_and_extreme_presets_run_without_project_artifacts(): void
    {
        $package = dirname(__DIR__, 3);
        $root = dirname(__DIR__, 2) . '/fixtures/theme-minimal';
        $runner = new CommandRunner();

        foreach (['base', 'theme', 'extreme-theme'] as $preset) {
            $result = $runner->run(
                [
                    PHP_BINARY,
                    $package . '/bin/blockstudio-phpstan',
                    '--preset',
                    $preset,
                    '--root',
                    $root,
                    '--',
                    '--no-progress',
                ],
                $package,
                30
            );

            $this->assertSame(
                0,
                $result->exitCode,
                $preset . " failed:\n" . $result->stdout . $result->stderr
            );
        }

        $this->assertFileDoesNotExist($root . '/phpstan.neon');
        $this->assertFileDoesNotExist($root . '/phpstan-baseline.neon');
        $this->assertFileDoesNotExist($root . '/.phpstan');
    }

    public function test_wordpress_render_preset_accepts_an_explicit_json_probe(): void
    {
        $package = dirname(__DIR__, 3);
        $root = dirname(__DIR__, 2) . '/fixtures/theme-minimal';
        $runner = new CommandRunner();
        $command = json_encode(
            [PHP_BINARY, '-r', 'fwrite(STDOUT, \'{"ok":true}\');'],
            JSON_THROW_ON_ERROR
        );

        $result = $runner->run(
            [
                PHP_BINARY,
                $package . '/bin/blockstudio-phpstan',
                '--preset',
                'wordpress-render',
                '--root',
                $root,
                '--render-command',
                $command,
                '--render-working-directory',
                $root,
                '--render-timeout',
                '5',
                '--',
                '--no-progress',
            ],
            $package,
            30
        );

        $this->assertSame(
            0,
            $result->exitCode,
            $result->stdout . $result->stderr
        );
    }

    public function test_wordpress_render_requires_a_valid_command_contract(): void
    {
        $package = dirname(__DIR__, 3);
        $root = dirname(__DIR__, 2) . '/fixtures/theme-minimal';
        $runner = new CommandRunner();

        $missing = $runner->run(
            [
                PHP_BINARY,
                $package . '/bin/blockstudio-phpstan',
                '--preset',
                'wordpress-render',
                '--root',
                $root,
            ],
            $package,
            10
        );
        $this->assertSame(2, $missing->exitCode);
        $this->assertStringContainsString(
            'requires --render-command',
            $missing->stderr
        );

        $invalid = $runner->run(
            [
                PHP_BINARY,
                $package . '/bin/blockstudio-phpstan',
                '--render-command',
                '{"not":"argv"}',
            ],
            $package,
            10
        );
        $this->assertSame(2, $invalid->exitCode);
        $this->assertStringContainsString(
            'must be a non-empty JSON argv array',
            $invalid->stderr
        );
    }

    public function test_json_output_and_diagnostic_exit_code_are_stable(): void
    {
        $package = dirname(__DIR__, 3);
        $root = dirname(__DIR__, 2) . '/fixtures/theme-invalid';
        $runner = new CommandRunner();

        $result = $runner->run(
            [
                PHP_BINARY,
                $package . '/bin/blockstudio-phpstan',
                '--preset',
                'extreme-theme',
                '--root',
                $root,
                '--error-format',
                'json',
                '--',
                '--no-progress',
            ],
            $package,
            30
        );

        $this->assertSame(1, $result->exitCode);
        $payload = json_decode(
            $result->stdout,
            true,
            512,
            JSON_THROW_ON_ERROR
        );
        $this->assertGreaterThan(0, $payload['totals']['file_errors'] ?? 0);
        $this->assertArrayHasKey('files', $payload);
    }
}
