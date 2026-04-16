<?php

use Blockstudio\Api\Attributes\Rpc;
use Blockstudio\Api\Rpc\Access;
use Blockstudio\Api\Rpc\Method;

return new class {
    #[Rpc(access: Access::Session)]
    public function subscribe(array $params): array
    {
        return ['success' => true];
    }

    #[Rpc(name: 'configured', methods: [Method::Post, Method::Get])]
    public function configuredEndpoint(array $params): array
    {
        return [];
    }

    #[Rpc(access: Access::Open)]
    public function openEndpoint(array $params): array
    {
        return [];
    }
};
