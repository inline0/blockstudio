<?php

use Blockstudio\Db\Field;
use Blockstudio\Db\Schema;
use Blockstudio\Db\Storage;

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
