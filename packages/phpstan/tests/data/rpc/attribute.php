<?php

use Blockstudio\Http_Method;
use Blockstudio\Rpc_Access;
use Blockstudio\Rpc_Definition as Rpc;

return new class {
    #[Rpc(access: Rpc_Access::Session)]
    public function subscribe(array $params): array
    {
        return ['success' => true];
    }

    #[Rpc(name: 'configured', methods: [Http_Method::Post, Http_Method::Get])]
    public function configuredEndpoint(array $params): array
    {
        return [];
    }

    #[Rpc(access: Rpc_Access::Open)]
    public function openEndpoint(array $params): array
    {
        return [];
    }
};
