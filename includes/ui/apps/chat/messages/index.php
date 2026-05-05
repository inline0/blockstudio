<div data-chat-messages>
	<div data-chat-messages-inner>
		<template data-wp-each--msg="state.messages" data-wp-each-key="context.msg.id">
			<div
				data-chat-msg
				data-wp-bind--data-role="context.msg.role"
			>
				<div data-chat-msg-bubble data-wp-text="context.msg.content"></div>
			</div>
		</template>
	</div>
</div>
