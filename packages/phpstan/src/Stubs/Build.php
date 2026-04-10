<?php

namespace Blockstudio;

class Build
{
    /**
     * Get all registered Blockstudio block types.
     *
     * @return array<string, \WP_Block_Type>
     */
    public static function blocks(): array {}

    /**
     * Get all registered Blockstudio extensions.
     *
     * @return array<string, \WP_Block_Type>
     */
    public static function extensions(): array {}

    /**
     * Get the Blockstudio build directory.
     */
    public static function get_build_dir(string $path = '/blockstudio', string $filter = 'path'): string {}
}
