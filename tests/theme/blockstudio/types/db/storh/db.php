<?php
/**
 * Storh database fixture.
 *
 * @package Blockstudio
 */

return array(
	'storage'    => 'storh',
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
			'type' => 'string',
		),
		'count'  => array(
			'type' => 'integer',
		),
		'score'  => array(
			'type' => 'number',
		),
		'active' => array(
			'type' => 'boolean',
		),
		'notes'  => array(
			'type' => 'text',
		),
	),
);
