<?php

use Blockstudio\Db_Field as Field;
use Blockstudio\Db_Schema as Schema;
use Blockstudio\Db_Storage;

return [
    'subscribers' => Schema::make(
        storage: Db_Storage::Table,
        fields: [
            'email' => Field::string(required: true),
            'active' => Field::boolean(required: true),
        ],
    ),
    'logs' => Schema::make(
        storage: Db_Storage::Jsonc,
        fields: [
            'message' => Field::text(required: true),
        ],
    ),
];
