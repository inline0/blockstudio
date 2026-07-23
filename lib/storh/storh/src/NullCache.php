<?php

declare(strict_types=1);

namespace BlockstudioVendor\Storh;

final class NullCache implements CacheInterface
{
    public function get(string $key): mixed
    {
        return null;
    }

    public function set(string $key, mixed $value, ?int $ttl_seconds = null): void
    {
    }

    public function delete(string $key): void
    {
    }

    public function clear_prefix(string $prefix): void
    {
    }
}
