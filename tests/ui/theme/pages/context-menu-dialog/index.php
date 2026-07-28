<block name="bsui/dialog">
	<block name="bsui/dialog-trigger" label="Open Notes"></block>
	<block name="bsui/dialog-backdrop"></block>
	<block name="bsui/dialog-popup">
		<h3>Notes</h3>
		<block name="bsui/dialog-content">
			<block name="bsui/context-menu">
				<block name="bsui/context-menu-trigger">
					<p>Right click this paragraph to open the context menu and pick an action for the note.</p>
				</block>
				<block name="bsui/context-menu-popup">
					<block name="bsui/context-menu-item" label="Copy"></block>
					<block name="bsui/context-menu-item" label="Rename"></block>
					<block name="bsui/context-menu-item" label="Delete"></block>
				</block>
			</block>
		</block>
		<block name="bsui/dialog-close" label="Close"></block>
	</block>
</block>
