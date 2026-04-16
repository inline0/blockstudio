<?php

namespace Blockstudio;

#[\Attribute(\Attribute::TARGET_METHOD)]
final class Rpc_Definition
{
    /**
     * @param array<int, string|Http_Method> $methods
     * @param Rpc_Access|bool|string|null $access
     * @param string|array<int, string>|null $capability
     */
    public function __construct(
        public ?string $name = null,
        public array $methods = [Http_Method::Post],
        public Rpc_Access|bool|string|null $access = Rpc_Access::Authenticated,
        public string|array|null $capability = null
    ) {}
}
