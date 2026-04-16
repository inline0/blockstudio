<?php

use Blockstudio\Db_Field as Field;
use Blockstudio\Db_Schema as Schema;
use Blockstudio\Db_Storage;

return Schema::make(
    storage: Db_Storage::Table,
    fields: [
        'name' => Field::string(required: true),
        'age' => Field::integer(),
    ],
);
