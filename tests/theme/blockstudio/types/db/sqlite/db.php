<?php
return array(
	'storage'    => 'sqlite',
	'capability' => array(
		'create' => true,
		'read'   => true,
		'update' => true,
		'delete' => true,
	),
	'fields'     => array(
		'title'  => array(
			'type'     => 'string',
			'required' => true,
		),
		'count'  => array(
			'type'    => 'integer',
			'default' => 0,
		),
		'score'  => array(
			'type' => 'number',
		),
		'active' => array(
			'type'    => 'boolean',
			'default' => false,
		),
	),
);
