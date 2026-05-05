<?php
$chats_db = \Blockstudio\Db::get( 'app/chat', 'chats' );
$chats    = $chats_db ? $chats_db->list( array(), 100 ) : array();
$chats    = array_reverse( $chats );

$current_chat_id = 0;
$messages        = array();

if ( ! empty( $_GET['chat'] ) ) {
	$current_chat_id = (int) $_GET['chat'];
	$messages_db     = \Blockstudio\Db::get( 'app/chat', 'messages' );
	if ( $messages_db ) {
		$all      = $messages_db->list( array(), 1000 );
		$messages = array_values( array_filter( $all, function ( $m ) use ( $current_chat_id ) {
			return (int) $m['chat_id'] === $current_chat_id;
		} ) );
	}
}

$page_url          = strtok( $_SERVER['REQUEST_URI'] ?? '', '?' );
$chats_with_active = array_map( function ( $chat ) use ( $current_chat_id, $page_url ) {
	$chat['active'] = (int) $chat['id'] === $current_chat_id;
	$chat['url']    = $page_url . '?chat=' . $chat['id'];
	return $chat;
}, $chats );

$current_title = 'New Chat';
foreach ( $chats_with_active as $chat ) {
	if ( (int) $chat['id'] === $current_chat_id ) {
		$current_title = $chat['title'];
		break;
	}
}

wp_interactivity_state( 'app/chat', array(
	'chats'            => array_values( $chats_with_active ),
	'messages'         => $messages,
	'currentChatId'    => $current_chat_id,
	'currentChatTitle' => $current_title,
	'cannotSend'       => true,
) );
?>
<div
	useBlockProps
	data-wp-interactive="app/chat"
	data-wp-context='<?php echo esc_attr( wp_json_encode( array(
		'input'            => '',
		'sending'          => false,
		'sidebarCollapsed' => false,
	) ) ); ?>'
	data-app-chat
	data-wp-class--chat-sidebar-collapsed="context.sidebarCollapsed"
>
	<InnerBlocks
		allowedBlocks='<?php echo esc_attr( wp_json_encode( array( 'app/chat-sidebar', 'app/chat-header', 'app/chat-messages', 'app/chat-input' ) ) ); ?>'
		template='<?php echo esc_attr( wp_json_encode( array(
			array( 'app/chat-sidebar', array() ),
			array( 'app/chat-header', array() ),
			array( 'app/chat-messages', array() ),
			array( 'app/chat-input', array() ),
		) ) ); ?>'
	/>
</div>
