<?php

use Blockstudio\Db\Field;
use Blockstudio\Db\Schema;
use Blockstudio\Db\Storage;

return Schema::make(
    storage: Storage::Table,
    fields: [
        'name' => Field::string(required: true),
        'age' => Field::integer(),
    ],
);
