<?php

namespace Blockstudio;

class Settings
{
    /**
     * Get a setting by path. Examples:
     *   Settings::get('tailwind/enabled') → bool
     *   Settings::get('users/ids') → int[]
     *   Settings::get('assets/enqueue') → bool
     *
     * @param mixed $default
     * @return mixed
     */
    public static function get(string $path, $default = null) {}

    public static function get_bool(string $path, bool $default = false): bool {}

    public static function get_int(string $path, int $default = 0): int {}

    public static function get_string(string $path, string $default = ''): string {}

    /**
     * @param array<mixed> $default
     * @return array<mixed>
     */
    public static function get_array(string $path, array $default = []): array {}

    /**
     * Get the full settings array.
     *
     * @return array<string, mixed>
     */
    public static function get_all(): array {}

    /** @return array<string, mixed> */
    public static function get_raw(): array {}

    /** @return list<string> */
    public static function errors(): array {}

    public static function fingerprint(): string {}

    public static function reset(): void {}

    public static function reload(): ?self {}

    public static function get_instance(): self {}
}
