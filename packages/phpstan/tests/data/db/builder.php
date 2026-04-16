<?php

use Blockstudio\Api\Db\Field;
use Blockstudio\Api\Db\Schema;
use Blockstudio\Api\Db\Storage;

return Schema::make(
    storage: Storage::Table,
    fields: [
        'email' => Field::string(required: true, format: 'email'),
        'count' => Field::integer(),
        'active' => Field::boolean(required: true),
        'notes' => Field::text(),
    ],
);
