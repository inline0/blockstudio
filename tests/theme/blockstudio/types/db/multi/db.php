<?php
return array(
	'contacts' => array(
		'storage'    => 'table',
		'capability' => array(
			'create' => true,
			'read'   => true,
			'update' => true,
			'delete' => true,
		),
		'fields'     => array(
			'name'  => array(
				'type'     => 'string',
				'required' => true,
			),
			'email' => array(
				'type'   => 'string',
				'format' => 'email',
			),
		),
	),
	'notes'    => array(
		'storage'    => 'jsonc',
		'capability' => array(
			'create' => true,
			'read'   => true,
			'update' => true,
			'delete' => true,
		),
		'fields'     => array(
			'body' => array(
				'type'     => 'string',
				'required' => true,
			),
		),
	),
);
