<?php

declare(strict_types=1);

namespace Blockstudio\PHPStan\Tests\Unit\Theme;

use Blockstudio\PHPStan\Theme\AnalysisConfiguration;
use PHPUnit\Framework\TestCase;

final class AnalysisConfigurationTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir()
            . '/blockstudio-analysis-config-'
            . bin2hex(random_bytes(5));
        mkdir($this->root, 0777, true);
        $this->root = realpath($this->root) ?: $this->root;
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->root);
    }

    public function test_missing_default_file_preserves_command_defaults(): void
    {
        $configuration = (new AnalysisConfiguration())->read($this->root);

        $this->assertSame([
            'path' => null,
            'configuration' => null,
            'preset' => null,
            'roots' => null,
            'excludes' => null,
            'maxFiles' => null,
        ], $configuration);
    }

    public function test_project_configuration_resolves_from_the_config_directory(): void
    {
        mkdir($this->root . '/config');
        $path = $this->root . '/config/project.json';
        file_put_contents($path, json_encode([
            'phpstan' => [
                'configuration' => 'phpstan.neon',
            ],
        ], JSON_THROW_ON_ERROR));

        $configuration = (new AnalysisConfiguration())->read(
            $this->root,
            'config/project.json'
        );

        $this->assertSame(
            $this->root . '/config/phpstan.neon',
            $configuration['configuration']
        );
    }

    public function test_project_configuration_rejects_an_empty_value(): void
    {
        $path = $this->root . '/blockstudio.json';
        file_put_contents($path, json_encode([
            'phpstan' => ['configuration' => '   '],
        ], JSON_THROW_ON_ERROR));

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('phpstan.configuration must be a non-empty string');

        (new AnalysisConfiguration())->read($this->root);
    }

    public function test_reads_typed_values_and_resolves_roots_from_source(): void
    {
        mkdir($this->root . '/config');
        mkdir($this->root . '/theme');
        $path = $this->root . '/config/project.json';
        file_put_contents($path, json_encode([
            'phpstan' => [
                'preset' => 'extreme-theme',
                'roots' => ['../theme', '../theme'],
                'excludePaths' => ['fixtures/**', 'vendor/**'],
                'maxFiles' => 4321,
            ],
        ], JSON_THROW_ON_ERROR));

        $configuration = (new AnalysisConfiguration())->read(
            $this->root,
            'config/project.json'
        );

        $this->assertSame($path, $configuration['path']);
        $this->assertSame('extreme-theme', $configuration['preset']);
        $this->assertSame([$this->root . '/theme'], $configuration['roots']);
        $this->assertSame(
            ['fixtures/**', 'vendor/**'],
            $configuration['excludes']
        );
        $this->assertSame(4321, $configuration['maxFiles']);
    }

    /**
     * @dataProvider invalidConfigurations
     * @param array<string, mixed>|string $contents
     */
    public function test_rejects_malformed_and_unknown_configuration(
        array|string $contents,
        string $message
    ): void {
        $path = $this->root . '/blockstudio.json';
        file_put_contents(
            $path,
            is_string($contents)
                ? $contents
                : json_encode($contents, JSON_THROW_ON_ERROR)
        );

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage($message);

        (new AnalysisConfiguration())->read($this->root);
    }

    /**
     * @return iterable<string, array{array<string, mixed>|string, string}>
     */
    public static function invalidConfigurations(): iterable
    {
        yield 'invalid JSON' => ['{broken', 'Invalid JSON'];
        yield 'list root' => ['[]', 'must contain a JSON object'];
        yield 'phpstan list' => [
            ['phpstan' => ['extreme-theme']],
            'phpstan must be a JSON object',
        ];
        yield 'unknown key' => [
            ['phpstan' => ['format' => true]],
            'Unsupported blockstudio.json phpstan key: format',
        ];
        yield 'invalid preset' => [
            ['phpstan' => ['preset' => 'maximum']],
            'phpstan.preset must be base, theme, extreme-theme, or wordpress-render',
        ];
        yield 'empty roots' => [
            ['phpstan' => ['roots' => []]],
            'phpstan.roots must be a non-empty list',
        ];
        yield 'invalid exclude' => [
            ['phpstan' => ['excludePaths' => ['']]],
            'phpstan.excludePaths must be a list of non-empty strings',
        ];
        yield 'invalid limit' => [
            ['phpstan' => ['maxFiles' => 0]],
            'phpstan.maxFiles must be a positive integer',
        ];
    }

    public function test_explicit_missing_source_fails_deterministically(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage(
            'Blockstudio configuration does not exist: '
            . $this->root
            . '/missing.json'
        );

        (new AnalysisConfiguration())->read($this->root, 'missing.json');
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
