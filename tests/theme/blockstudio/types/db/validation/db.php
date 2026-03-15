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
		'email'    => array(
			'type'     => 'string',
			'format'   => 'email',
			'required' => true,
			'validate' => function ( $value ) {
				if ( str_ends_with( $value, '@banned.com' ) ) {
					return 'This domain is not allowed.';
				}
				return true;
			},
		),
		'url'      => array(
			'type'   => 'string',
			'format' => 'url',
		),
		'username' => array(
			'type'      => 'string',
			'minLength' => 3,
			'maxLength' => 20,
		),
		'role'     => array(
			'type' => 'string',
			'enum' => array( 'admin', 'editor', 'viewer' ),
		),
		'age'      => array(
			'type' => 'integer',
		),
		'score'    => array(
			'type' => 'number',
		),
		'verified' => array(
			'type' => 'boolean',
		),
	),
);
