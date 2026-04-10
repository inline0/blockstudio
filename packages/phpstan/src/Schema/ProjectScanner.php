<?php

declare(strict_types=1);

namespace Blockstudio\PHPStan\Schema;

/**
 * Discovers Blockstudio files in the user's project.
 *
 * Walks from the working directory looking for block.json files (and the
 * sibling db.php / rpc.php / template files).
 */
final class ProjectScanner
{
    /** @var array<string, string> blockName => block.json path */
    private array $blocks = [];

    /** @var list<string> */
    private array $blockJsonPaths = [];

    private bool $scanned = false;

    public function __construct(private readonly string $currentWorkingDirectory) {}

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

        $roots = $this->getScanRoots();

        foreach ($roots as $root) {
            if (!is_dir($root)) {
                continue;
            }
            $this->walkDirectory($root);
        }
    }

    /**
     * @return list<string>
     */
    private function getScanRoots(): array
    {
        $candidates = [
            $this->currentWorkingDirectory . '/blockstudio',
            $this->currentWorkingDirectory . '/src/blockstudio',
            $this->currentWorkingDirectory . '/wp-content/themes',
            $this->currentWorkingDirectory,
        ];

        return array_values(array_unique($candidates));
    }

    private function walkDirectory(string $dir): void
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
            if (!$file instanceof \SplFileInfo || $file->getFilename() !== 'block.json') {
                continue;
            }
            $path = $file->getPathname();
            $this->blockJsonPaths[] = $path;

            $name = $this->extractBlockName($path);
            if ($name !== null) {
                $this->blocks[$name] = $path;
            }
        }
    }

    private function shouldSkipDirectory(string $path): bool
    {
        $base = basename($path);
        return in_array($base, [
            'node_modules',
            'vendor',
            '.git',
            '_dist',
            '_references',
            'tmp',
        ], true);
    }

    private function extractBlockName(string $blockJsonPath): ?string
    {
        $content = @file_get_contents($blockJsonPath);
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
