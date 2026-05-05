<div data-chat-input>
	<div data-chat-input-box>
		<bs:bsui-textarea
			placeholder="Type a message..."
			rows="3"
			data-wp-on--input="actions.updateInput"
			data-wp-on--keydown="actions.handleKeyDown"
			data-wp-bind--value="context.input"
			data-wp-bind--disabled="context.sending"
			html-data-chat-textarea
		/>
		<div data-chat-input-actions>
			<bs:bsui-button
				label="Send &#8593;"
				disabled="true"
				data-wp-on--click="actions.sendMessage"
				data-wp-bind--disabled="state.cannotSend"
				data-wp-bind--aria-disabled="state.cannotSend"
				html-data-chat-send
			/>
		</div>
	</div>
</div>
