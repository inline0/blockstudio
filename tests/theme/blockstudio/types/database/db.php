<?php
/**
 * Test block database schema.
 *
 * @package Blockstudio
 */

return array(
	'storage'    => 'table',
	'capability' => array(
		'create' => true,
		'read'   => true,
		'update' => 'edit_posts',
		'delete' => 'manage_options',
	),
	'fields'     => array(
		'email' => array(
			'type'     => 'string',
			'format'   => 'email',
			'required' => true,
		),
		'name'  => array(
			'type'      => 'string',
			'maxLength' => 100,
		),
		'plan'  => array(
			'type'    => 'string',
			'enum'    => array( 'free', 'pro', 'enterprise' ),
			'default' => 'free',
		),
	),
);
