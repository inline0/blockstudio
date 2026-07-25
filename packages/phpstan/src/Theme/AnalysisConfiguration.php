<?php

declare(strict_types=1);

namespace Blockstudio\PHPStan\Theme;

/**
 * Reads the tooling-owned PHPStan section from blockstudio.json.
 */
final class AnalysisConfiguration
{
    private const PRESETS = [
        'base',
        'theme',
        'extreme-theme',
        'wordpress-render',
    ];

    private const KEYS = [
        'preset',
        'roots',
        'excludePaths',
        'maxFiles',
    ];

    /**
     * @return array{
     *   path: ?string,
     *   preset: ?string,
     *   roots: ?list<string>,
     *   excludes: ?list<string>,
     *   maxFiles: ?int
     * }
     */
    public function read(
        string $workingDirectory,
        ?string $configurationPath = null
    ): array {
        $workingDirectory = $this->absolutePath(
            $workingDirectory,
            getcwd() ?: '.'
        );
        $explicit = $configurationPath !== null;
        $path = $explicit
            ? $this->absolutePath($configurationPath, $workingDirectory)
            : $workingDirectory . '/blockstudio.json';

        if (!is_file($path)) {
            if ($explicit) {
                throw new \InvalidArgumentException(
                    'Blockstudio configuration does not exist: ' . $path
                );
            }

            return $this->emptyConfiguration();
        }

        $contents = file_get_contents($path);
        if ($contents === false) {
            throw new \InvalidArgumentException(
                'Unable to read Blockstudio configuration: ' . $path
            );
        }

        try {
            $root = json_decode($contents, false, 512, JSON_THROW_ON_ERROR);
            $decoded = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            throw new \InvalidArgumentException(
                sprintf(
                    'Invalid JSON in %s: %s',
                    $path,
                    $exception->getMessage()
                ),
                0,
                $exception
            );
        }

        if (!is_object($root) || !is_array($decoded)) {
            throw new \InvalidArgumentException(
                $path . ' must contain a JSON object.'
            );
        }

        if (!array_key_exists('phpstan', $decoded)) {
            return [
                ...$this->emptyConfiguration(),
                'path' => $path,
            ];
        }

        $phpstanRoot = $root->phpstan ?? null;
        $phpstan = $decoded['phpstan'];
        if (!is_object($phpstanRoot) || !is_array($phpstan)) {
            throw new \InvalidArgumentException(
                'blockstudio.json phpstan must be a JSON object.'
            );
        }

        $unknown = array_values(array_diff(array_keys($phpstan), self::KEYS));
        sort($unknown);
        if ($unknown !== []) {
            throw new \InvalidArgumentException(
                sprintf(
                    'Unsupported blockstudio.json phpstan key%s: %s.',
                    count($unknown) === 1 ? '' : 's',
                    implode(', ', $unknown)
                )
            );
        }

        $preset = $this->optionalPreset($phpstan);
        $roots = $this->optionalStringList($phpstan, 'roots', false);
        $excludes = $this->optionalStringList(
            $phpstan,
            'excludePaths',
            true
        );
        $maxFiles = $this->optionalPositiveInteger($phpstan, 'maxFiles');
        $directory = dirname($path);

        if ($roots !== null) {
            $roots = array_map(
                fn(string $root): string => $this->absolutePath(
                    $root,
                    $directory
                ),
                $roots
            );
            $roots = array_values(array_unique($roots));
        }

        return [
            'path' => $path,
            'preset' => $preset,
            'roots' => $roots,
            'excludes' => $excludes,
            'maxFiles' => $maxFiles,
        ];
    }

    /**
     * @return array{
     *   path: null,
     *   preset: null,
     *   roots: null,
     *   excludes: null,
     *   maxFiles: null
     * }
     */
    private function emptyConfiguration(): array
    {
        return [
            'path' => null,
            'preset' => null,
            'roots' => null,
            'excludes' => null,
            'maxFiles' => null,
        ];
    }

    /**
     * @param array<string, mixed> $configuration
     */
    private function optionalPreset(array $configuration): ?string
    {
        if (!array_key_exists('preset', $configuration)) {
            return null;
        }

        $preset = $configuration['preset'];
        if (!is_string($preset) || !in_array($preset, self::PRESETS, true)) {
            throw new \InvalidArgumentException(
                'blockstudio.json phpstan.preset must be base, theme, '
                . 'extreme-theme, or wordpress-render.'
            );
        }

        return $preset;
    }

    /**
     * @param array<string, mixed> $configuration
     * @return list<string>|null
     */
    private function optionalStringList(
        array $configuration,
        string $key,
        bool $emptyAllowed
    ): ?array {
        if (!array_key_exists($key, $configuration)) {
            return null;
        }

        $values = $configuration[$key];
        if (
            !is_array($values)
            || !array_is_list($values)
            || (!$emptyAllowed && $values === [])
        ) {
            throw new \InvalidArgumentException(
                sprintf(
                    'blockstudio.json phpstan.%s must be a %slist of non-empty strings.',
                    $key,
                    $emptyAllowed ? '' : 'non-empty '
                )
            );
        }

        foreach ($values as $value) {
            if (!is_string($value) || trim($value) === '') {
                throw new \InvalidArgumentException(
                    sprintf(
                        'blockstudio.json phpstan.%s must be a %slist of non-empty strings.',
                        $key,
                        $emptyAllowed ? '' : 'non-empty '
                    )
                );
            }
        }

        return array_values(array_unique($values));
    }

    /**
     * @param array<string, mixed> $configuration
     */
    private function optionalPositiveInteger(
        array $configuration,
        string $key
    ): ?int {
        if (!array_key_exists($key, $configuration)) {
            return null;
        }

        $value = $configuration[$key];
        if (!is_int($value) || $value < 1) {
            throw new \InvalidArgumentException(
                'blockstudio.json phpstan.maxFiles must be a positive integer.'
            );
        }

        return $value;
    }

    private function absolutePath(string $path, string $base): string
    {
        $path = str_replace('\\', '/', trim($path));
        $base = str_replace('\\', '/', rtrim($base, '/'));
        $absolute = str_starts_with($path, '/')
            || preg_match('/^[A-Za-z]:[\\\\\/]/', $path) === 1
            ? $path
            : $base . '/' . $path;
        $real = realpath($absolute);

        return $this->normalizePath($real !== false ? $real : $absolute);
    }

    private function normalizePath(string $path): string
    {
        $path = str_replace('\\', '/', $path);
        $prefix = str_starts_with($path, '/') ? '/' : '';
        $segments = [];

        foreach (explode('/', $path) as $segment) {
            if ($segment === '' || $segment === '.') {
                continue;
            }
            if ($segment === '..' && $segments !== []) {
                array_pop($segments);
                continue;
            }
            $segments[] = $segment;
        }

        return $prefix . implode('/', $segments);
    }
}
