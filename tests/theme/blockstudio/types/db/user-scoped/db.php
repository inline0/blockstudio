<?php
return array(
	'storage'    => 'table',
	'userScoped' => true,
	'capability' => array(
		'create' => true,
		'read'   => true,
		'update' => true,
		'delete' => true,
	),
	'fields'     => array(
		'title' => array(
			'type'     => 'string',
			'required' => true,
		),
		'done'  => array(
			'type'    => 'boolean',
			'default' => false,
		),
	),
);
