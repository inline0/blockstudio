<?php

use Blockstudio\Db\Field;
use Blockstudio\Db\Schema;
use Blockstudio\Db\Storage;

return Schema::make(
    storage: Storage::Table,
    fields: [
        'email' => Field::string(required: true, format: 'email'),
        'count' => Field::integer(),
        'active' => Field::boolean(required: true),
        'notes' => Field::text(),
    ],
);
