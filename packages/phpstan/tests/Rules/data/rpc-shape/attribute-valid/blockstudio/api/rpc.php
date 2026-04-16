<?php

use Blockstudio\Api\Attributes\Rpc;
use Blockstudio\Api\Rpc\Access;
use Blockstudio\Api\Rpc\Method;

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
