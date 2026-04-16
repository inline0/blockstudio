<?php

use Blockstudio\Db_Field as Field;
use Blockstudio\Db_Schema as Schema;
use Blockstudio\Db_Storage;

return Schema::make(
    storage: Db_Storage::Table,
    fields: [
        'email' => Field::string(required: true, format: 'email'),
        'count' => Field::integer(),
        'active' => Field::boolean(required: true),
        'notes' => Field::text(),
    ],
);
