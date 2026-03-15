<?php
return array(
	'storage'    => 'jsonc',
	'capability' => array(
		'create' => true,
		'read'   => true,
		'update' => true,
		'delete' => true,
	),
	'fields'     => array(
		'action'  => array(
			'type'     => 'string',
			'required' => true,
		),
		'details' => array(
			'type' => 'string',
		),
	),
);
