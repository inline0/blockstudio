<div data-chat-sidebar data-wp-interactive="app/chat">
	<div data-chat-sidebar-header>
		<span data-chat-sidebar-title>Chat</span>
		<bs:bsui-button label="&#171;" variant="ghost" size="icon" data-wp-on--click="actions.collapseSidebar" html-data-chat-minimize />
	</div>
	<div data-chat-sidebar-list>
		<template data-wp-each--chat="state.chats" data-wp-each-key="context.chat.id">
			<div
				data-chat-sidebar-item
				data-wp-bind--data-active="context.chat.active"
			>
				<bs:bsui-button
					variant="ghost"
					href="#"
					data-wp-bind--href="context.chat.url"
					data-wp-bind--data-variant="state.chatItemVariant"
					data-wp-on--click="actions.selectChat"
					data-wp-text="context.chat.title"
					html-data-chat-sidebar-item-btn
				/>
			</div>
		</template>
	</div>
</div>
