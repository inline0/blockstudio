<?php

namespace Blockstudio\Api\Db;

use Blockstudio\Api\Definition;

final class Schema implements Definition
{
    /**
     * @param array<string, array<string, mixed>|Definition> $fields
     * @param string|Storage $storage
     * @param array<string, mixed> $capability
     * @param bool|array<string, mixed> $realtime
     * @param array<string, callable> $hooks
     * @param array<string, mixed> $extra
     */
    public static function make(
        array $fields,
        string|Storage $storage = 'table',
        array $capability = [],
        bool|array $realtime = false,
        bool $userScoped = false,
        ?int $postId = null,
        array $hooks = [],
        array $extra = []
    ): self {}

    /**
     * @return array<string, mixed>
     */
    public function to_array(): array {}
}
