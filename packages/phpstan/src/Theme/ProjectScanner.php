<?php

declare(strict_types=1);

namespace Blockstudio\PHPStan\Theme;

/**
 * Reads ordinary, materialized theme directories for the opt-in presets.
 *
 * The base extension keeps its existing discovery behavior. This scanner is
 * instantiated only by theme.neon and receives normal filesystem roots.
 */
final class ProjectScanner
{
    private const DEFAULT_EXCLUDES = [
        '.git/**',
        '.cache/**',
        '.phpunit.cache/**',
        '_dist/**',
        '_references/**',
        'build/**',
        'coverage/**',
        'dist/**',
        'node_modules/**',
        'vendor/**',
    ];

    /** @var list<string>|null */
    private ?array $allFiles = null;

    /** @var array<string, string> */
    private array $contents = [];

    private bool $limitReached = false;

    /**
     * @param list<string> $configuredRoots
     * @param list<string> $configuredExcludePaths
     */
    public function __construct(
        private readonly string $currentWorkingDirectory,
        private readonly array $configuredRoots = [],
        private readonly array $configuredExcludePaths = [],
        private readonly int $maxFiles = 10000
    ) {}

    /**
     * @return list<string>
     */
    public function roots(): array
    {
        $roots = $this->configuredRoots === []
            ? [$this->currentWorkingDirectory]
            : $this->configuredRoots;

        $normalized = [];
        foreach ($roots as $root) {
            $path = $this->absolutePath($root);
            $identity = realpath($path);
            $normalized[] = $this->normalizePath($identity !== false ? $identity : $path);
        }

        $normalized = array_values(array_unique($normalized));
        sort($normalized);

        return $normalized;
    }

    /**
     * @param list<string> $extensions
     * @return list<string>
     */
    public function files(array $extensions = []): array
    {
        $files = $this->scan();
        if ($extensions === []) {
            return $files;
        }

        $lookup = array_fill_keys(array_map('strtolower', $extensions), true);

        return array_values(array_filter(
            $files,
            static function (string $file) use ($lookup): bool {
                $extension = strtolower(pathinfo($file, PATHINFO_EXTENSION));
                return isset($lookup[$extension])
                    || ($extension === 'php' && isset($lookup['blade.php']) && str_ends_with($file, '.blade.php'));
            }
        ));
    }

    /**
     * @return list<string>
     */
    public function filesNamed(string $filename): array
    {
        return array_values(array_filter(
            $this->scan(),
            static fn(string $file): bool => basename($file) === $filename
        ));
    }

    public function rootForFile(string $file): ?string
    {
        $file = $this->normalizePath($this->absolutePath($file));
        foreach ($this->roots() as $root) {
            if ($file === $root || str_starts_with($file, rtrim($root, '/') . '/')) {
                return $root;
            }
        }

        return null;
    }

    public function relativePath(string $file, ?string $root = null): string
    {
        $file = $this->normalizePath($this->absolutePath($file));
        $root ??= $this->rootForFile($file);
        if ($root === null) {
            return $file;
        }

        return ltrim(substr($file, strlen(rtrim($root, '/'))), '/');
    }

    public function read(string $file): string
    {
        $file = $this->normalizePath($file);
        if (!array_key_exists($file, $this->contents)) {
            $contents = @file_get_contents($file);
            $this->contents[$file] = $contents !== false ? $contents : '';
        }

        return $this->contents[$file];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function jsonObject(string $file): ?array
    {
        $decoded = json_decode($this->read($file), true);
        return is_array($decoded) && !array_is_list($decoded) ? $decoded : null;
    }

    public function limitReached(): bool
    {
        $this->scan();
        return $this->limitReached;
    }

    public function scanLimit(): int
    {
        return max(1, $this->maxFiles);
    }

    public static function lineForOffset(string $contents, int $offset): int
    {
        return substr_count(substr($contents, 0, max(0, $offset)), "\n") + 1;
    }

    /**
     * @return list<string>
     */
    private function scan(): array
    {
        if ($this->allFiles !== null) {
            return $this->allFiles;
        }

        $files = [];
        $seen = [];
        $limit = max(1, $this->maxFiles);

        foreach ($this->roots() as $root) {
            if (!is_dir($root)) {
                continue;
            }

            try {
                $directory = new \RecursiveDirectoryIterator(
                    $root,
                    \FilesystemIterator::SKIP_DOTS
                );
                $filter = new \RecursiveCallbackFilterIterator(
                    $directory,
                    function (\SplFileInfo $item) use ($root): bool {
                        return !$this->isExcluded($item->getPathname(), $root);
                    }
                );
                $iterator = new \RecursiveIteratorIterator(
                    $filter,
                    \RecursiveIteratorIterator::LEAVES_ONLY
                );
            } catch (\Throwable) {
                continue;
            }

            foreach ($iterator as $item) {
                if (!$item instanceof \SplFileInfo || !$item->isFile()) {
                    continue;
                }

                $path = $this->normalizePath($item->getPathname());
                $identity = realpath($path);
                $identity = $this->normalizePath($identity !== false ? $identity : $path);
                if (isset($seen[$identity])) {
                    continue;
                }

                if (count($files) >= $limit) {
                    $this->limitReached = true;
                    break 2;
                }

                $seen[$identity] = true;
                $files[] = $path;
            }
        }

        sort($files);
        $this->allFiles = $files;

        return $files;
    }

    private function isExcluded(string $path, string $root): bool
    {
        $path = $this->normalizePath($path);
        $relative = ltrim(substr($path, strlen(rtrim($root, '/'))), '/');
        $patterns = array_merge(self::DEFAULT_EXCLUDES, $this->configuredExcludePaths);

        foreach ($patterns as $pattern) {
            $pattern = $this->normalizePath(trim($pattern));
            if ($pattern === '') {
                continue;
            }

            $candidate = str_starts_with($pattern, '/')
                ? $path
                : $relative;
            $normalizedPattern = str_starts_with($pattern, '/')
                ? $this->absolutePath($pattern)
                : ltrim($pattern, '/');

            $base = rtrim($normalizedPattern, '/*');

            if (
                fnmatch($normalizedPattern, $candidate, FNM_PATHNAME)
                || fnmatch($base, $candidate, FNM_PATHNAME)
                || fnmatch($base . '/*', $candidate, FNM_PATHNAME)
                || $candidate === $base
                || str_starts_with($candidate, $base . '/')
                || self::pathIsUnder($candidate, $base)
            ) {
                return true;
            }

            if (
                !str_contains($base, '/')
                && !str_contains($base, '*')
                && $base !== ''
                && str_contains($candidate, '/' . $base . '/')
            ) {
                return true;
            }
        }

        return false;
    }

    private static function pathIsUnder(string $candidate, string $base): bool
    {
        if ($base === '') {
            return false;
        }

        $segments = explode('/', $candidate);
        $prefix = '';

        foreach ($segments as $segment) {
            $prefix = $prefix === '' ? $segment : $prefix . '/' . $segment;

            if (fnmatch($base, $prefix, FNM_PATHNAME)) {
                return true;
            }
        }

        return false;
    }

    private function absolutePath(string $path): string
    {
        if ($path === '') {
            return $this->normalizePath($this->currentWorkingDirectory);
        }

        if ($path[0] === '/' || preg_match('/^[A-Za-z]:[\\\\\/]/', $path) === 1) {
            return $this->normalizePath($path);
        }

        return $this->normalizePath(
            rtrim($this->currentWorkingDirectory, '/\\') . '/' . $path
        );
    }

    private function normalizePath(string $path): string
    {
        return str_replace('\\', '/', $path);
    }
}
