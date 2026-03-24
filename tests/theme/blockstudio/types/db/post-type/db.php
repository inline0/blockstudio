<?php
return array(
	'storage'    => 'post_type',
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
		'status' => array(
			'type'    => 'string',
			'enum'    => array( 'draft', 'published' ),
			'default' => 'draft',
		),
	),
);
