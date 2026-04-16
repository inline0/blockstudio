<?php

namespace Blockstudio\Attributes;

use Blockstudio\Rpc\Access;
use Blockstudio\Rpc\Method;

#[\Attribute(\Attribute::TARGET_METHOD)]
final class Rpc
{
    /**
     * @param array<int, string|Method> $methods
     * @param Access|bool|string|null $access
     * @param string|array<int, string>|null $capability
     */
    public function __construct(
        public ?string $name = null,
        public array $methods = [Method::Post],
        public Access|bool|string|null $access = Access::Authenticated,
        public string|array|null $capability = null
    ) {}
}
