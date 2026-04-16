<?php

use Blockstudio\Api\Db\Field;
use Blockstudio\Api\Db\Schema;
use Blockstudio\Api\Db\Storage;

return [
    'subscribers' => Schema::make(
        storage: Storage::Table,
        fields: [
            'email' => Field::string(required: true),
            'active' => Field::boolean(required: true),
        ],
    ),
    'logs' => Schema::make(
        storage: Storage::Jsonc,
        fields: [
            'message' => Field::text(required: true),
        ],
    ),
];
