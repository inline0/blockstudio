<?php

use Blockstudio\Api\Db\Field;
use Blockstudio\Api\Db\Schema;
use Blockstudio\Api\Db\Storage;

return Schema::make(
    storage: Storage::Table,
    fields: [
        'name' => Field::string(required: true),
        'age' => Field::integer(),
    ],
);
