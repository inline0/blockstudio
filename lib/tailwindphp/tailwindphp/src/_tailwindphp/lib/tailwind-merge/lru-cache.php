<?php

declare (strict_types=1);
/**
 * Port of: https://github.com/dcastil/tailwind-merge/blob/main/src/lib/lru-cache.ts
 *
 * LRU cache implementation using plain arrays.
 *
 * @port-deviation:storage Uses PHP arrays instead of JS objects
 */
namespace BlockstudioVendor\TailwindPHP\Lib\TailwindMerge;

class LruCache
{
    private int $maxCacheSize;
    private int $cacheSize = 0;
    /** @var array<string, string> */
    private array $cache = [];
    /** @var array<string, string> */
    private array $previousCache = [];
    public function __construct(int $maxCacheSize)
    {
        $this->maxCacheSize = $maxCacheSize;
    }
    public function get(string $key): ?string
    {
        if (isset($this->cache[$key])) {
            return $this->cache[$key];
        }
        if (isset($this->previousCache[$key])) {
            $value = $this->previousCache[$key];
            $this->update($key, $value);
            return $value;
        }
        return null;
    }
    public function set(string $key, string $value): void
    {
        if (isset($this->cache[$key])) {
            $this->cache[$key] = $value;
        } else {
            $this->update($key, $value);
        }
    }
    private function update(string $key, string $value): void
    {
        $this->cache[$key] = $value;
        $this->cacheSize++;
        if ($this->cacheSize > $this->maxCacheSize) {
            $this->cacheSize = 0;
            $this->previousCache = $this->cache;
            $this->cache = [];
        }
    }
}
