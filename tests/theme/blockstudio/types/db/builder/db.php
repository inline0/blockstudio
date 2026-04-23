<?php

use Blockstudio\Db\Field;
use Blockstudio\Db\Schema;
use Blockstudio\Db\Storage;

return Schema::make(
	storage: Storage::Table,
	capability: array(
		'create' => true,
		'read'   => true,
		'update' => true,
		'delete' => true,
	),
	fields: array(
		'title'  => Field::string( required: true ),
		'count'  => Field::integer( default: 0 ),
		'active' => Field::boolean( default: false ),
		'notes'  => Field::text(),
	)
);
