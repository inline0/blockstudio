<?php

declare(strict_types=1);

namespace Blockstudio\PHPStan\Tests\Unit\Theme;

use Blockstudio\PHPStan\Theme\CommandRunner;
use PHPUnit\Framework\TestCase;

final class CliContractTest extends TestCase
{
    /** @var list<string> */
    private array $temporaryDirectories = [];

    protected function tearDown(): void
    {
        foreach (array_reverse($this->temporaryDirectories) as $directory) {
            $this->removeDirectory($directory);
        }
        $this->temporaryDirectories = [];
    }

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
        $this->assertStringContainsString(
            'defaults to --memory-limit=1G',
            $help->stdout
        );

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

    public function test_blockstudio_json_supplies_defaults_and_cli_options_override_them(): void
    {
        $package = dirname(__DIR__, 3);
        $theme = dirname(__DIR__, 2) . '/fixtures/theme-minimal';
        $project = $this->temporaryDirectory('configured project');
        $runner = new CommandRunner();
        file_put_contents(
            $project . '/blockstudio.json',
            json_encode(
                [
                    'phpstan' => [
                        'preset' => 'base',
                        'roots' => [$theme],
                        'excludePaths' => ['fixtures/**'],
                        'maxFiles' => 250,
                    ],
                ],
                JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR
            )
        );

        $configured = $runner->run(
            [
                PHP_BINARY,
                $package . '/bin/blockstudio-phpstan',
                '--',
                '--no-progress',
            ],
            $project,
            30
        );
        $this->assertSame(
            0,
            $configured->exitCode,
            $configured->stdout . $configured->stderr
        );

        file_put_contents(
            $project . '/blockstudio.json',
            json_encode(
                [
                    'phpstan' => [
                        'preset' => 'wordpress-render',
                        'roots' => [$project . '/missing'],
                    ],
                ],
                JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR
            )
        );
        $overridden = $runner->run(
            [
                PHP_BINARY,
                $package . '/bin/blockstudio-phpstan',
                '--preset',
                'base',
                '--root',
                $theme,
                '--',
                '--no-progress',
            ],
            $project,
            30
        );
        $this->assertSame(
            0,
            $overridden->exitCode,
            $overridden->stdout . $overridden->stderr
        );
    }

    public function test_canonical_exclusions_bound_phpstan_analysis_as_well_as_the_theme_scanner(): void
    {
        $package = dirname(__DIR__, 3);
        $project = $this->temporaryDirectory('analysis exclusions');
        $runner = new CommandRunner();
        mkdir($project . '/vendor', 0777, true);
        file_put_contents(
            $project . '/index.php',
            "<?php\n\ndeclare(strict_types=1);\n\nfunction included_value(): int\n{\n    return 1;\n}\n"
        );
        file_put_contents(
            $project . '/vendor/invalid.php',
            "<?php\n\ndeclare(strict_types=1);\n\nfunction excluded_value(): int\n{\n    return 'invalid';\n}\n"
        );
        file_put_contents(
            $project . '/blockstudio.json',
            json_encode(
                [
                    'phpstan' => [
                        'preset' => 'base',
                        'roots' => ['.'],
                        'excludePaths' => ['vendor/**'],
                    ],
                ],
                JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR
            )
        );

        $excluded = $runner->run(
            [
                PHP_BINARY,
                $package . '/bin/blockstudio-phpstan',
                '--',
                '--no-progress',
            ],
            $project,
            30
        );
        $this->assertSame(
            0,
            $excluded->exitCode,
            $excluded->stdout . $excluded->stderr
        );

        $overriddenMemory = $runner->run(
            [
                PHP_BINARY,
                $package . '/bin/blockstudio-phpstan',
                '--',
                '--memory-limit=256M',
                '--no-progress',
            ],
            $project,
            30
        );
        $this->assertSame(
            0,
            $overriddenMemory->exitCode,
            $overriddenMemory->stdout . $overriddenMemory->stderr
        );

        file_put_contents(
            $project . '/blockstudio.json',
            json_encode(
                [
                    'phpstan' => [
                        'preset' => 'base',
                        'roots' => ['.'],
                        'excludePaths' => [],
                    ],
                ],
                JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR
            )
        );
        $included = $runner->run(
            [
                PHP_BINARY,
                $package . '/bin/blockstudio-phpstan',
                '--',
                '--no-progress',
            ],
            $project,
            30
        );
        $this->assertSame(1, $included->exitCode);
        $this->assertStringContainsString(
            'invalid.php',
            $included->stdout . $included->stderr
        );
    }

    public function test_alternate_and_malformed_blockstudio_sources_have_stable_contracts(): void
    {
        $package = dirname(__DIR__, 3);
        $theme = dirname(__DIR__, 2) . '/fixtures/theme-minimal';
        $project = $this->temporaryDirectory('alternate project');
        $configuration = $project . '/config/project.json';
        mkdir(dirname($configuration), 0777, true);
        file_put_contents(
            $configuration,
            json_encode(
                [
                    'phpstan' => [
                        'preset' => 'base',
                        'roots' => [$theme],
                    ],
                ],
                JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR
            )
        );
        $runner = new CommandRunner();

        $alternate = $runner->run(
            [
                PHP_BINARY,
                $package . '/bin/blockstudio-phpstan',
                '--blockstudio-json',
                $configuration,
                '--',
                '--no-progress',
            ],
            $project,
            30
        );
        $this->assertSame(
            0,
            $alternate->exitCode,
            $alternate->stdout . $alternate->stderr
        );

        file_put_contents($project . '/blockstudio.json', '{broken');
        $invalid = $runner->run(
            [PHP_BINARY, $package . '/bin/blockstudio-phpstan'],
            $project,
            10
        );
        $this->assertSame(2, $invalid->exitCode);
        $this->assertStringContainsString(
            'Invalid JSON in ' . $project . '/blockstudio.json',
            $invalid->stderr
        );

        $help = $runner->run(
            [
                PHP_BINARY,
                $package . '/bin/blockstudio-phpstan',
                '--help',
            ],
            $project,
            10
        );
        $this->assertSame(0, $help->exitCode);
        $this->assertStringContainsString(
            '--blockstudio-json <path>',
            $help->stdout
        );
    }

    private function temporaryDirectory(string $name): string
    {
        $directory = sys_get_temp_dir()
            . '/blockstudio-cli-'
            . preg_replace('/[^a-z]+/i', '-', $name)
            . '-'
            . bin2hex(random_bytes(5));
        mkdir($directory, 0777, true);
        $directory = realpath($directory) ?: $directory;
        $this->temporaryDirectories[] = $directory;

        return $directory;
    }

    private function removeDirectory(string $directory): void
    {
        if (!is_dir($directory)) {
            return;
        }

        foreach (scandir($directory) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $path = $directory . '/' . $entry;
            if (is_dir($path) && !is_link($path)) {
                $this->removeDirectory($path);
            } else {
                @unlink($path);
            }
        }

        @rmdir($directory);
    }
}
