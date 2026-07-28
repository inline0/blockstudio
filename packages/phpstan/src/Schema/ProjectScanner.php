<?php

declare(strict_types=1);

namespace Blockstudio\PHPStan\Schema;

/**
 * Discovers Blockstudio files in the user's project.
 *
 * Walks from the working directory looking for block.json and field.json
 * files (and sibling db.php / rpc.php / template files).
 */
final class ProjectScanner
{
    /** @var array<string, string> blockName => block.json path */
    private array $blocks = [];

    /** @var list<string> */
    private array $blockJsonPaths = [];

    /** @var array<string, list<string>> fieldName => field.json paths */
    private array $fields = [];

    /** @var list<string> */
    private array $fieldJsonPaths = [];

    /** @var array<string, true> */
    private array $seenBlockJsonPaths = [];

    /** @var array<string, true> */
    private array $seenFieldJsonPaths = [];

    private bool $scanned = false;

    /** @var list<string> */
    private array $resolvedExcludePaths;

    /**
     * @param list<string> $additionalScanRoots
     * @param list<string> $configuredExcludePaths
     * @param list<string> $analysedPaths PHPStan's own configured paths.
     * @param mixed $phpstanExcludePaths PHPStan's own excludePaths structure.
     */
    public function __construct(
        private readonly string $currentWorkingDirectory,
        private readonly array $additionalScanRoots = [],
        private readonly array $configuredExcludePaths = [],
        private readonly array $analysedPaths = [],
        mixed $phpstanExcludePaths = null
    ) {
        $this->resolvedExcludePaths = array_values(array_merge(
            $this->configuredExcludePaths,
            self::flattenPhpstanExcludePaths($phpstanExcludePaths)
        ));
    }

    /**
     * PHPStan stores excludePaths either as a plain list or keyed by
     * analyse / analyseAndScan. Schema discovery honours every entry, since a
     * path the user excluded from analysis should not fail the run through a
     * side door.
     *
     * @return list<string>
     */
    private static function flattenPhpstanExcludePaths(mixed $excludePaths): array
    {
        if (!is_array($excludePaths)) {
            return [];
        }

        $flat = [];
        foreach ($excludePaths as $key => $value) {
            if (is_string($value)) {
                $flat[] = $value;
                continue;
            }
            if (!is_array($value) || !in_array($key, ['analyse', 'analyseAndScan'], true)) {
                continue;
            }
            foreach ($value as $entry) {
                if (is_string($entry)) {
                    $flat[] = $entry;
                }
            }
        }

        return $flat;
    }

    public function relativePath(string $path): string
    {
        $normalized = str_replace('\\', '/', $path);
        $root = rtrim(str_replace('\\', '/', $this->currentWorkingDirectory), '/');

        if ($root !== '' && str_starts_with($normalized, $root . '/')) {
            $normalized = substr($normalized, strlen($root) + 1);
        }

        // A scan root of "." arrives as "<root>/." and yields "./path" entries.
        // Left in place the prefix defeats every exclude pattern and leaks into
        // error messages.
        while (str_starts_with($normalized, './')) {
            $normalized = substr($normalized, 2);
        }

        return $normalized;
    }

    /**
     * @return list<string>
     */
    public function getBlockJsonPaths(): array
    {
        $this->scan();
        return $this->blockJsonPaths;
    }

    /**
     * Find a block.json by block name (e.g. "mytheme/hero").
     */
    public function findBlockJsonByName(string $blockName): ?string
    {
        $this->scan();
        return $this->blocks[$blockName] ?? null;
    }

    /**
     * @return list<string>
     */
    public function getFieldJsonPaths(): array
    {
        $this->scan();
        return $this->fieldJsonPaths;
    }

    /**
     * Return every file-backed definition for a custom field name.
     *
     * Multiple paths are returned so consumers can report ambiguous
     * definitions instead of silently choosing one.
     *
     * @return list<string>
     */
    public function findFieldJsonPathsByName(string $fieldName): array
    {
        $this->scan();
        return $this->fields[$fieldName] ?? [];
    }

    /**
     * Find the block.json that is a sibling of the given file path.
     * Used to type $a in template files.
     */
    public function findSiblingBlockJson(string $filePath): ?string
    {
        $dir = dirname($filePath);
        $candidate = $dir . '/block.json';
        return file_exists($candidate) ? $candidate : null;
    }

    /**
     * Find a sibling db.php for a given block name.
     */
    public function findDbPhpByBlockName(string $blockName): ?string
    {
        $blockJson = $this->findBlockJsonByName($blockName);
        if ($blockJson === null) {
            return null;
        }
        $candidate = dirname($blockJson) . '/db.php';
        return file_exists($candidate) ? $candidate : null;
    }

    private function scan(): void
    {
        if ($this->scanned) {
            return;
        }
        $this->scanned = true;

        foreach ($this->getProjectScanRoots() as $root) {
            if (!is_dir($root)) {
                continue;
            }
            $this->walkDirectory($root, true, false);
        }

        foreach ($this->additionalScanRoots as $root) {
            if (!is_dir($root)) {
                continue;
            }
            $this->walkDirectory($root, false, true);
        }

        sort($this->blockJsonPaths);
        sort($this->fieldJsonPaths);

        foreach ($this->fields as &$paths) {
            sort($paths);
        }
        unset($paths);
    }

    /**
     * The analysed paths PHPStan was configured with bound discovery when they
     * exist; a project that analyses only its theme directory should not have
     * schemas validated from anywhere else. Without configured paths the
     * scanner keeps its working directory heuristics.
     *
     * @return list<string>
     */
    private function getProjectScanRoots(): array
    {
        $configured = [];
        foreach ($this->analysedPaths as $path) {
            $absolute = $this->absolutePath($path);
            if ($absolute !== '' && is_dir($absolute)) {
                $configured[] = $absolute;
            }
        }

        if ($configured !== []) {
            return array_values(array_unique($configured));
        }

        $candidates = [
            $this->currentWorkingDirectory . '/blockstudio',
            $this->currentWorkingDirectory . '/src/blockstudio',
            $this->currentWorkingDirectory . '/wp-content/themes',
            $this->currentWorkingDirectory,
        ];

        return array_values(array_unique(array_filter(
            $candidates,
            static fn(string $path): bool => $path !== ''
        )));
    }

    private function absolutePath(string $path): string
    {
        $normalized = str_replace('\\', '/', trim($path));
        if ($normalized === '') {
            return '';
        }

        if (
            str_starts_with($normalized, '/')
            || preg_match('~^[A-Za-z]:/~', $normalized) === 1
        ) {
            return rtrim($normalized, '/');
        }

        $root = rtrim(str_replace('\\', '/', $this->currentWorkingDirectory), '/');
        return rtrim($root . '/' . ltrim($normalized, './'), '/');
    }

    private function walkDirectory(
        string $dir,
        bool $includeInProjectPaths,
        bool $includeAllFieldDefinitions
    ): void
    {
        if ($this->shouldSkipDirectory($dir)) {
            return;
        }

        try {
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveCallbackFilterIterator(
                    new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
                    function (\SplFileInfo $current): bool {
                        if ($current->isDir()) {
                            return !$this->shouldSkipDirectory($current->getPathname());
                        }
                        return true;
                    }
                ),
                \RecursiveIteratorIterator::LEAVES_ONLY
            );
        } catch (\Throwable) {
            return;
        }

        foreach ($iterator as $file) {
            if (!$file instanceof \SplFileInfo) {
                continue;
            }

            $filename = $file->getFilename();
            if ($filename !== 'block.json' && $filename !== 'field.json') {
                continue;
            }

            $path = $this->normalizePath($file->getPathname());

            if ($filename === 'block.json') {
                $this->indexBlockJson($path, $includeInProjectPaths);
                continue;
            }

            if ($includeAllFieldDefinitions || $this->isFieldDefinitionPath($path)) {
                $this->indexFieldJson($path);
            }
        }
    }

    private function indexBlockJson(string $path, bool $includeInProjectPaths): void
    {
        $identity = $this->pathIdentity($path);

        if ($includeInProjectPaths && !isset($this->seenBlockJsonPaths[$identity])) {
            $this->seenBlockJsonPaths[$identity] = true;
            $this->blockJsonPaths[] = $path;
        }

        $name = $this->extractJsonName($path);
        if ($name !== null) {
            $this->blocks[$name] = $path;
        }
    }

    private function indexFieldJson(string $path): void
    {
        $identity = $this->pathIdentity($path);
        if (isset($this->seenFieldJsonPaths[$identity])) {
            return;
        }

        $this->seenFieldJsonPaths[$identity] = true;
        $this->fieldJsonPaths[] = $path;

        $name = $this->extractJsonName($path);
        if ($name === null) {
            return;
        }

        $this->fields[$name] ??= [];
        $this->fields[$name][] = $path;
    }

    private function shouldSkipDirectory(string $path): bool
    {
        $base = basename($path);
        if (in_array($base, [
            'node_modules',
            'vendor',
            '.git',
            '_dist',
            '_references',
            'tmp',
        ], true)) {
            return true;
        }

        return $this->isConfiguredExcluded($path);
    }

    private function isConfiguredExcluded(string $path): bool
    {
        if ($this->resolvedExcludePaths === []) {
            return false;
        }

        $candidate = $this->relativePath($path);

        foreach ($this->resolvedExcludePaths as $pattern) {
            $pattern = str_replace('\\', '/', trim($pattern));
            if (str_starts_with($pattern, '/') || preg_match('~^[A-Za-z]:/~', $pattern) === 1) {
                $pattern = $this->relativePath($pattern);
            }
            $pattern = ltrim($pattern, '/');
            if ($pattern === '') {
                continue;
            }

            $base = rtrim($pattern, '/*');

            if (
                fnmatch($pattern, $candidate, FNM_PATHNAME)
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

    private function isFieldDefinitionPath(string $path): bool
    {
        $normalized = str_replace('\\', '/', $path);
        return str_contains($normalized, '/fields/');
    }

    private function normalizePath(string $path): string
    {
        return str_replace('\\', '/', $path);
    }

    private function pathIdentity(string $path): string
    {
        $realPath = realpath($path);
        return $this->normalizePath($realPath !== false ? $realPath : $path);
    }

    private function extractJsonName(string $jsonPath): ?string
    {
        $content = @file_get_contents($jsonPath);
        if ($content === false) {
            return null;
        }
        $decoded = json_decode($content, true);
        if (!is_array($decoded)) {
            return null;
        }
        $name = $decoded['name'] ?? null;
        return is_string($name) && $name !== '' ? $name : null;
    }
}
