<?php

declare (strict_types=1);
namespace BlockstudioVendor\TailwindPHP\Utils;

/**
 * A Map that can generate default values for keys that don't exist.
 * Generated default values are added to the map to avoid recomputation.
 *
 * Port of: packages/tailwindcss/src/utils/default-map.ts
 *
 * @port-deviation:extends TypeScript DefaultMap extends native Map.
 * PHP uses composition with internal array since PHP doesn't have Map class.
 *
 * @port-deviation:keys TypeScript Map supports any type as key.
 * PHP normalizes array keys via serialize() since PHP arrays only accept string|int keys.
 *
 * @template TKey
 * @template TValue
 */
class DefaultMap
{
    /**
     * @var array<TKey, TValue>
     */
    private array $map = [];
    /**
     * @var callable(TKey, DefaultMap<TKey, TValue>): TValue
     */
    private $factory;
    /**
     * @param callable(TKey, DefaultMap<TKey, TValue>): TValue $factory
     */
    public function __construct(callable $factory)
    {
        $this->factory = $factory;
    }
    /**
     * Normalize key for array storage (PHP arrays can't have array keys).
     *
     * @param mixed $key
     * @return string|int
     */
    private function normalizeKey(mixed $key): string|int
    {
        if (is_array($key)) {
            return serialize($key);
        }
        return $key;
    }
    /**
     * @param TKey $key
     * @return TValue
     */
    public function get(mixed $key): mixed
    {
        $normalizedKey = $this->normalizeKey($key);
        if (!array_key_exists($normalizedKey, $this->map)) {
            $this->map[$normalizedKey] = ($this->factory)($key, $this);
        }
        return $this->map[$normalizedKey];
    }
    /**
     * @param TKey $key
     * @param TValue $value
     */
    public function set(mixed $key, mixed $value): void
    {
        $this->map[$this->normalizeKey($key)] = $value;
    }
    /**
     * @param TKey $key
     * @return bool
     */
    public function has(mixed $key): bool
    {
        return array_key_exists($this->normalizeKey($key), $this->map);
    }
    /**
     * @param TKey $key
     */
    public function delete(mixed $key): void
    {
        unset($this->map[$this->normalizeKey($key)]);
    }
    public function clear(): void
    {
        $this->map = [];
    }
    public function size(): int
    {
        return count($this->map);
    }
    /**
     * @return array<TKey, TValue>
     */
    public function entries(): array
    {
        return $this->map;
    }
}
