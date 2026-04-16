<?php

use Blockstudio\Http_Method;
use Blockstudio\Rpc_Access;
use Blockstudio\Rpc_Definition as Rpc;

return new class {
    #[Rpc(access: Rpc_Access::Session)]
    public function subscribe(array $params): array
    {
        return [];
    }

    #[Rpc(name: 'status', methods: [Http_Method::Get, Http_Method::Post])]
    public function getStatus(array $params): array
    {
        return [];
    }
};
