<?php

declare(strict_types=1);

namespace BlockstudioVendor\Storh;

final class StorageRoot
{
    private readonly string $root;

    public function __construct(string $root)
    {
        $normalized = self::normalize_path($root);
        if ('' === $normalized) {
            throw new StorageException('Storage root cannot be empty.');
        }

        $this->root = $normalized;
    }

    public static function at(string $root): self
    {
        return new self($root);
    }

    public function path(string $namespace = 'runtime-storage'): string
    {
        return $this->root . '/' . self::sanitize_namespace($namespace);
    }

    public function root(): string
    {
        return $this->root;
    }

    public static function resolve(string $root, string $namespace = 'runtime-storage'): string
    {
        return self::at($root)->path($namespace);
    }

    private static function normalize_path(string $path): string
    {
        return rtrim(str_replace('\\', '/', trim($path)), '/');
    }

    private static function sanitize_namespace(string $namespace): string
    {
        $sanitized = preg_replace('/[^A-Za-z0-9_.-]+/', '-', $namespace) ?? '';
        $sanitized = trim($sanitized, '-.');

        return '' === $sanitized ? 'runtime-storage' : $sanitized;
    }
}
