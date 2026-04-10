<?php

declare(strict_types=1);

namespace Blockstudio\PHPStan\Schema;

/**
 * Reads a theme's blockstudio.json settings file.
 */
final class BlockstudioJsonReader
{
    /** @var array<string, array{mtime: int, data: array<string, mixed>}> */
    private array $cache = [];

    /**
     * @return array<string, mixed>|null
     */
    public function load(string $path): ?array
    {
        if (!file_exists($path)) {
            return null;
        }

        $mtime = (int) filemtime($path);
        if (isset($this->cache[$path]) && $this->cache[$path]['mtime'] === $mtime) {
            return $this->cache[$path]['data'];
        }

        $content = file_get_contents($path);
        if ($content === false) {
            return null;
        }

        $decoded = json_decode($content, true);
        if (!is_array($decoded)) {
            return null;
        }

        $this->cache[$path] = [
            'mtime' => $mtime,
            'data' => $decoded,
        ];

        return $decoded;
    }
}
