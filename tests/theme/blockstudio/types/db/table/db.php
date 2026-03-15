<?php
return array(
	'storage'    => 'table',
	'capability' => array(
		'create' => true,
		'read'   => true,
		'update' => true,
		'delete' => true,
	),
	'fields'     => array(
		'title'   => array(
			'type'     => 'string',
			'required' => true,
		),
		'count'   => array(
			'type'    => 'integer',
			'default' => 0,
		),
		'price'   => array(
			'type' => 'number',
		),
		'active'  => array(
			'type'    => 'boolean',
			'default' => false,
		),
		'content' => array(
			'type' => 'text',
		),
	),
);
