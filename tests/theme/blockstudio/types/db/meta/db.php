<?php
return array(
	'storage'    => 'meta',
	'postId'     => 1483,
	'capability' => array(
		'create' => true,
		'read'   => true,
		'update' => true,
		'delete' => true,
	),
	'fields'     => array(
		'key'   => array(
			'type'     => 'string',
			'required' => true,
		),
		'value' => array(
			'type' => 'string',
		),
	),
);
