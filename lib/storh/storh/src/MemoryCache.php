<?php

declare(strict_types=1);

namespace BlockstudioVendor\Storh;

final class MemoryCache implements CacheInterface
{
    /** @var array<string, mixed> */
    private array $values = array();

    /** @var array<string, int> */
    private array $expires = array();

    /** @var array<string, int> */
    private array $ticks = array();

    /** @var list<string> */
    private array $access_keys = array();

    /** @var list<int> */
    private array $access_ticks = array();

    private int $access_offset = 0;

    private int $tick = 0;

    private ?int $max_bytes;

    public function __construct(private readonly int $max_entries = 10000, ?int $max_bytes = null)
    {
        if ($this->max_entries < 1) {
            throw new StorageException('Memory cache max entries must be at least 1.');
        }

        if (null !== $max_bytes && $max_bytes < 1) {
            throw new StorageException('Memory cache max bytes must be at least 1.');
        }

        $this->max_bytes = $max_bytes ?? self::default_max_bytes();
    }

    public function get(string $key): mixed
    {
        if (! array_key_exists($key, $this->values)) {
            return null;
        }

        if (isset($this->expires[ $key ]) && $this->expires[ $key ] < time()) {
            $this->delete($key);
            return null;
        }

        $this->touch($key);

        return $this->values[ $key ];
    }

    public function set(string $key, mixed $value, ?int $ttl_seconds = null): void
    {
        $this->values[ $key ] = $value;
        if (null === $ttl_seconds) {
            unset($this->expires[ $key ]);
        } else {
            $this->expires[ $key ] = time() + max(1, $ttl_seconds);
        }
        $this->touch($key);
        $this->evict($key);
    }

    public function delete(string $key): void
    {
        unset($this->values[ $key ], $this->expires[ $key ], $this->ticks[ $key ]);
    }

    public function clear_prefix(string $prefix): void
    {
        foreach (array_keys($this->values) as $key) {
            if (str_starts_with($key, $prefix)) {
                $this->delete($key);
            }
        }
    }

    private function touch(string $key): void
    {
        $tick = ++$this->tick;
        $this->ticks[ $key ] = $tick;
        $this->access_keys[] = $key;
        $this->access_ticks[] = $tick;
    }

    private function evict(?string $protected_key = null): void
    {
        while (array() !== $this->values && ( count($this->values) > $this->max_entries || $this->over_memory_budget() )) {
            $key = $this->next_evictable_key($protected_key);
            if (null === $key) {
                $key = $this->first_evictable_key($protected_key);
            }
            if (null === $key && null !== $protected_key && array_key_exists($protected_key, $this->values)) {
                $key = $protected_key;
            }

            // @codeCoverageIgnoreStart
            if (! is_string($key)) {
                return;
            }
            // @codeCoverageIgnoreEnd

            $this->delete($key);
        }

        $this->compact_access_order();
    }

    private function next_evictable_key(?string $protected_key = null): ?string
    {
        $count = count($this->access_keys);
        while ($this->access_offset < $count) {
            $index = $this->access_offset++;
            $key   = $this->access_keys[ $index ];
            $tick  = $this->access_ticks[ $index ];

            if ($key === $protected_key) {
                continue;
            }

            if (isset($this->ticks[ $key ]) && $this->ticks[ $key ] === $tick) {
                return $key;
            }
        }

        return null;
    }

    private function first_evictable_key(?string $protected_key): ?string
    {
        foreach ($this->values as $key => $_value) {
            if ($key !== $protected_key) {
                return $key;
            }
        }

        return null;
    }

    private function compact_access_order(): void
    {
        if ($this->access_offset < 1024 || $this->access_offset * 2 < count($this->access_keys)) {
            return;
        }

        $this->access_keys   = array_slice($this->access_keys, $this->access_offset);
        $this->access_ticks  = array_slice($this->access_ticks, $this->access_offset);
        $this->access_offset = 0;
    }

    private function over_memory_budget(): bool
    {
        return null !== $this->max_bytes && memory_get_usage() > $this->max_bytes;
    }

    private static function default_max_bytes(): ?int
    {
        $limit = ini_get('memory_limit');
        if (false === $limit || '-1' === trim($limit)) {
            return null;
        }

        $bytes = self::parse_bytes($limit);
        // @codeCoverageIgnoreStart
        if (null === $bytes) {
            return null;
        }
        // @codeCoverageIgnoreEnd

        return max(1_048_576, (int) floor($bytes * 0.7));
    }

    private static function parse_bytes(string $value): ?int
    {
        $value = trim($value);
        if ('' === $value) {
            return null;
        }

        $unit   = strtolower($value[strlen($value) - 1]);
        $number = (float) $value;
        if ($number <= 0) {
            return null;
        }

        return match ($unit) {
            'g' => (int) floor($number * 1024 * 1024 * 1024),
            'm' => (int) floor($number * 1024 * 1024),
            'k' => (int) floor($number * 1024),
            default => (int) floor($number),
        };
    }
}
