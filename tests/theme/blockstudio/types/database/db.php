<?php
/**
 * Test block database schemas.
 *
 * @package Blockstudio
 */

return array(
	'subscribers' => array(
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
	),
	'logs'        => array(
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
	),
);
