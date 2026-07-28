<block name="bsui/dialog">
	<block name="bsui/dialog-trigger" label="Open Settings"></block>
	<block name="bsui/dialog-backdrop"></block>
	<block name="bsui/dialog-popup">
		<h3>Settings</h3>
		<p>Configure your preferences.</p>
		<block name="bsui/dialog">
			<block name="bsui/dialog-trigger" label="Open Preferences"></block>
			<block name="bsui/dialog-backdrop"></block>
			<block name="bsui/dialog-popup">
				<h3>Preferences</h3>
				<block name="bsui/dialog-content">
					<block name="bsui/field">
						<block name="bsui/field-label" text="Fruit"></block>
						<block name="bsui/select" placeholder="Choose a fruit" nameAlt="fruit">
							<block name="bsui/select-trigger"></block>
							<block name="bsui/select-popup">
								<block name="bsui/select-option" value="apple" label="Apple"></block>
								<block name="bsui/select-option" value="banana" label="Banana"></block>
								<block name="bsui/select-option" value="cherry" label="Cherry"></block>
							</block>
						</block>
					</block>
				</block>
				<block name="bsui/dialog-close" label="Close Preferences"></block>
			</block>
		</block>
		<block name="bsui/dialog-close" label="Close Settings"></block>
	</block>
</block>
