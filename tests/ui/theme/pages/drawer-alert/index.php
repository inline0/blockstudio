<block name="bsui/drawer" position="right">
	<block name="bsui/drawer-trigger" label="Open Panel"></block>
	<block name="bsui/drawer-backdrop"></block>
	<block name="bsui/drawer-popup">
		<p>Manage this item from the panel.</p>
		<block name="bsui/alert-dialog">
			<block name="bsui/alert-dialog-trigger" label="Delete Item"></block>
			<block name="bsui/alert-dialog-backdrop"></block>
			<block name="bsui/alert-dialog-popup">
				<p>Are you sure? This cannot be undone.</p>
				<block name="bsui/alert-dialog-close" label="Cancel"></block>
			</block>
		</block>
		<block name="bsui/drawer-close" label="Close"></block>
	</block>
</block>
