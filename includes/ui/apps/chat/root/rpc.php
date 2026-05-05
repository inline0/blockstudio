<?php
use Blockstudio\Db;

return array(
	'send'        => array(
		'callback' => function ( array $params ) {
			$chat_id = (int) ( $params['chat_id'] ?? 0 );
			$content = trim( $params['content'] ?? '' );

			if ( ! $content ) {
				return new \WP_Error( 'empty', 'Message cannot be empty.', array( 'status' => 400 ) );
			}

			$chats_db    = Db::get( 'app/chat', 'chats' );
			$messages_db = Db::get( 'app/chat', 'messages' );

			if ( ! $chat_id ) {
				$title = mb_strlen( $content ) > 40 ? mb_substr( $content, 0, 40 ) . '...' : $content;
				$chat  = $chats_db->create( array( 'title' => $title ) );

				if ( is_wp_error( $chat ) ) {
					return $chat;
				}

				$chat_id = (int) $chat['id'];
			}

			$message = $messages_db->create( array(
				'chat_id' => $chat_id,
				'role'    => 'user',
				'content' => $content,
			) );

			if ( is_wp_error( $message ) ) {
				return $message;
			}

			return array(
				'chat_id' => $chat_id,
				'message' => $message,
			);
		},
		'public'   => true,
		'methods'  => array( 'POST' ),
	),
	'messages'    => array(
		'callback' => function ( array $params ) {
			$chat_id     = (int) ( $params['chat_id'] ?? 0 );
			$messages_db = Db::get( 'app/chat', 'messages' );
			$all         = $messages_db->list( array(), 1000 );

			return array_values( array_filter( $all, function ( $m ) use ( $chat_id ) {
				return (int) $m['chat_id'] === $chat_id;
			} ) );
		},
		'public'   => true,
		'methods'  => array( 'GET', 'POST' ),
	),
	'delete_chat' => array(
		'callback' => function ( array $params ) {
			$chat_id     = (int) ( $params['chat_id'] ?? 0 );
			$chats_db    = Db::get( 'app/chat', 'chats' );
			$messages_db = Db::get( 'app/chat', 'messages' );

			$all = $messages_db->list( array(), 1000 );
			foreach ( $all as $m ) {
				if ( (int) $m['chat_id'] === $chat_id ) {
					$messages_db->delete( (int) $m['id'] );
				}
			}

			$chats_db->delete( $chat_id );

			return array( 'deleted' => true );
		},
		'public'   => true,
		'methods'  => array( 'POST' ),
	),
);
