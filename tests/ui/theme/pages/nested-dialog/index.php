<block name="bsui/dialog">
	<block name="bsui/dialog-trigger" label="Open Settings"></block>
	<block name="bsui/dialog-backdrop"></block>
	<block name="bsui/dialog-popup">
		<h3>Settings</h3>
		<p>Configure your preferences.</p>
		<block name="bsui/dialog">
			<block name="bsui/dialog-trigger" label="Delete Account"></block>
			<block name="bsui/dialog-backdrop"></block>
			<block name="bsui/dialog-popup">
				<h3>Confirm Delete</h3>
				<p>This action is permanent.</p>
				<block name="bsui/dialog-close" label="Cancel"></block>
			</block>
		</block>
		<block name="bsui/dialog-close" label="Close Settings"></block>
	</block>
</block>
