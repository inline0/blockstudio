<?php

declare(strict_types=1);

namespace BlockstudioVendor\Storh;

final class Cache
{
    private static ?NullCache $null_cache = null;

    public static function memory(int $maxEntries = 10000, ?int $maxBytes = null): CacheInterface
    {
        return new MemoryCache($maxEntries, $maxBytes);
    }

    public static function apcu(string $prefix = 'storh'): CacheInterface
    {
        return new ApcuCache($prefix);
    }

    public static function null(): CacheInterface
    {
        return self::$null_cache ??= new NullCache();
    }
}
