<?php
return array(
	'chats'    => array(
		'storage'    => 'jsonc',
		'capability' => array(
			'create' => true,
			'read'   => true,
			'update' => true,
			'delete' => true,
		),
		'fields'     => array(
			'title' => array(
				'type'      => 'string',
				'required'  => true,
				'maxLength' => 200,
			),
		),
	),
	'messages' => array(
		'storage'    => 'jsonc',
		'capability' => array(
			'create' => true,
			'read'   => true,
			'update' => true,
			'delete' => true,
		),
		'fields'     => array(
			'chat_id' => array(
				'type'     => 'integer',
				'required' => true,
			),
			'role'    => array(
				'type'     => 'string',
				'required' => true,
				'enum'     => array( 'user', 'assistant' ),
			),
			'content' => array(
				'type'      => 'string',
				'required'  => true,
				'maxLength' => 10000,
			),
		),
	),
);
