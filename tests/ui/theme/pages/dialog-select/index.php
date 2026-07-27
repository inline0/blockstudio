<block name="bsui/dialog">
	<block name="bsui/dialog-trigger" label="Open Settings"></block>
	<block name="bsui/dialog-backdrop"></block>
	<block name="bsui/dialog-popup">
		<h3>Settings</h3>
		<block name="bsui/dialog-content">
			<block name="bsui/field">
				<block name="bsui/field-label" text="Fruit"></block>
				<block name="bsui/select" placeholder="Choose a fruit" defaultValue="banana" nameAlt="fruit">
					<block name="bsui/select-trigger"></block>
					<block name="bsui/select-popup">
						<block name="bsui/select-option" value="apple" label="Apple"></block>
						<block name="bsui/select-option" value="banana" label="Banana"></block>
						<block name="bsui/select-option" value="cherry" label="Cherry"></block>
					</block>
				</block>
			</block>
			<block name="bsui/field">
				<block name="bsui/field-label" text="Notes"></block>
				<block name="bsui/input" type="text" nameAlt="notes" placeholder="Add a note"></block>
			</block>
		</block>
		<block name="bsui/dialog-close" label="Close"></block>
	</block>
</block>
