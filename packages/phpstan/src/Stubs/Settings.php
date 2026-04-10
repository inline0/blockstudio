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

    /**
     * Get the full settings array.
     *
     * @return array<string, mixed>
     */
    public static function get_all(): array {}

    public static function get_instance(): self {}
}
