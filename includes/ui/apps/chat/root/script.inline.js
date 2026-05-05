import { store, getContext } from '@wordpress/interactivity';

function syncActiveFlags( chatId ) {
	state.chats.forEach( function( c ) { c.active = c.id === chatId; } );
}

const { state } = store( 'app/chat', {
	state: {
		get hasMessages() {
			return state.messages.length > 0;
		},
		get cannotSend() {
			return ! getContext().input.trim() || getContext().sending;
		},
		get isUserMessage() {
			return getContext().msg.role === 'user';
		},
		get isAssistantMessage() {
			return getContext().msg.role === 'assistant';
		},
		get avatarLabel() {
			return getContext().msg.role === 'user' ? 'U' : 'AI';
		},
		get messageRoleLabel() {
			return getContext().msg.role === 'user' ? 'You' : 'Assistant';
		},
		get isChatActive() {
			return getContext().chat.id === state.currentChatId;
		},
		get chatItemVariant() {
			return getContext().chat.id === state.currentChatId ? 'sidebar-active' : 'ghost';
		},
	},
	actions: {
		updateInput( event ) {
			getContext().input = event.target.value;
		},
		handleKeyDown( event ) {
			if ( event.key === 'Enter' && ! event.shiftKey ) {
				event.preventDefault();
				store( 'app/chat' ).actions.sendMessage();
			}
		},
		*sendMessage() {
			var ctx = getContext();
			var content = ctx.input.trim();
			if ( ! content || ctx.sending ) return;

			ctx.input = '';

			yield bs.mutate( {
				fn: function () {
					return bs.fn( 'send', {
						chat_id: state.currentChatId,
						content: content,
					}, 'app/chat' );
				},
				state: state,
				key: 'messages',
				action: 'create',
				optimistic: { role: 'user', content: content, chat_id: state.currentChatId },
			} );

			var lastMsg = state.messages[ state.messages.length - 1 ];
			if ( lastMsg && lastMsg.chat_id && ! state.currentChatId ) {
				state.currentChatId = lastMsg.chat_id;
				state.currentChatTitle = content.length > 40 ? content.substring( 0, 40 ) + '...' : content;
				var chats = yield bs.db( 'app/chat', 'chats' ).list();
				state.chats = chats.reverse();
				syncActiveFlags( lastMsg.chat_id );
				var url = new URL( window.location );
				url.searchParams.set( 'chat', lastMsg.chat_id );
				window.history.pushState( {}, '', url );
			}

			requestAnimationFrame( function() {
				var el = document.querySelector( '[data-chat-messages]' );
				if ( el ) el.scrollTop = el.scrollHeight;
				var textarea = document.querySelector( '[data-chat-textarea]' );
				if ( textarea ) textarea.focus();
			} );
		},
		*selectChat( event ) {
			event.preventDefault();
			var chat = getContext().chat;
			state.currentChatId = chat.id;
			state.currentChatTitle = chat.title;
			syncActiveFlags( chat.id );

			var url = new URL( window.location );
			url.searchParams.set( 'chat', chat.id );
			window.history.pushState( {}, '', url );

			try {
				var msgs = yield bs.fn( 'messages', { chat_id: chat.id }, 'app/chat' );
				state.messages = Array.isArray( msgs ) ? msgs : [];
			} catch ( e ) {
				state.messages = [];
			}

			requestAnimationFrame( function() {
				var el = document.querySelector( '[data-chat-messages]' );
				if ( el ) el.scrollTop = el.scrollHeight;
			} );
		},
		newChat() {
			state.currentChatId = 0;
			state.currentChatTitle = 'New Chat';
			state.messages = [];
			getContext().input = '';
			syncActiveFlags( 0 );

			var url = new URL( window.location );
			url.searchParams.delete( 'chat' );
			window.history.pushState( {}, '', url );
		},
		collapseSidebar() {
			getContext().sidebarCollapsed = true;
		},
		expandSidebar() {
			getContext().sidebarCollapsed = false;
		},
	},
} );
