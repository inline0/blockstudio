<?php

use Blockstudio\Attributes\Rpc;
use Blockstudio\Rpc\Access;
use Blockstudio\Rpc\Method;

return new class {
    #[Rpc(access: Access::Session)]
    public function subscribe(array $params): array
    {
        return [];
    }

    #[Rpc(name: 'status', methods: [Method::Get, Method::Post])]
    public function getStatus(array $params): array
    {
        return [];
    }
};
