<block name="bsui/dialog">
	<block name="bsui/dialog-trigger" label="Open Level 1"></block>
	<block name="bsui/dialog-backdrop"></block>
	<block name="bsui/dialog-popup">
		<h3>Level 1: Settings</h3>
		<p>Main settings panel.</p>
		<block name="bsui/dialog">
			<block name="bsui/dialog-trigger" label="Open Level 2"></block>
			<block name="bsui/dialog-backdrop"></block>
			<block name="bsui/dialog-popup">
				<h3>Level 2: Advanced</h3>
				<p>Advanced configuration.</p>
				<block name="bsui/dialog">
					<block name="bsui/dialog-trigger" label="Open Level 3"></block>
					<block name="bsui/dialog-backdrop"></block>
					<block name="bsui/dialog-popup">
						<h3>Level 3: Confirm</h3>
						<p>Are you absolutely sure?</p>
						<block name="bsui/dialog-close" label="Yes, do it"></block>
					</block>
				</block>
				<block name="bsui/dialog-close" label="Back"></block>
			</block>
		</block>
		<block name="bsui/dialog-close" label="Close"></block>
	</block>
</block>
