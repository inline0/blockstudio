<div data-chat-header>
	<bs:bsui-button label="&#9776;" variant="ghost" size="icon" data-wp-on--click="actions.expandSidebar" html-data-chat-expand-sidebar />
	<bs:bsui-drawer position="left">
		<bs:bsui-drawer-trigger trigger_label="&#9776;" trigger_variant="ghost" trigger_size="icon" html-data-chat-sidebar-toggle />
		<bs:bsui-drawer-backdrop />
		<bs:bsui-drawer-popup flush="true">
			<bs:app-chat-sidebar />
		</bs:bsui-drawer-popup>
	</bs:bsui-drawer>
	<span data-chat-header-title data-wp-text="state.currentChatTitle"></span>
</div>
