<?php

declare(strict_types=1);

namespace BlockstudioVendor\Storh;

/**
 * @codeCoverageIgnore Optional backend depends on the host PHP SAPI APCu mode.
 */
final class ApcuCache implements CacheInterface
{
    private readonly bool $enabled;

    private readonly string $key_prefix;

    public function __construct(string $prefix = 'storh')
    {
        $this->enabled    = function_exists('apcu_enabled') && apcu_enabled();
        $this->key_prefix = $prefix . ':';
    }

    public function get(string $key): mixed
    {
        if (! $this->enabled) {
            return null;
        }

        $success = false;
        $value   = apcu_fetch($this->key($key), $success);

        return $success ? $value : null;
    }

    public function set(string $key, mixed $value, ?int $ttl_seconds = null): void
    {
        if (! $this->enabled) {
            return;
        }

        apcu_store($this->key($key), $value, $ttl_seconds ?? 0);
    }

    public function delete(string $key): void
    {
        if ($this->enabled) {
            apcu_delete($this->key($key));
        }
    }

    public function clear_prefix(string $prefix): void
    {
        if (! $this->enabled || ! class_exists(\APCUIterator::class)) {
            return;
        }

        apcu_delete(new \APCUIterator('/^' . preg_quote($this->key($prefix), '/') . '/'));
    }

    private function key(string $key): string
    {
        return $this->key_prefix . $key;
    }
}
