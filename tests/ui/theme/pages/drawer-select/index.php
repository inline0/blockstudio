<block name="bsui/drawer" position="right">
	<block name="bsui/drawer-trigger" label="Open Filters"></block>
	<block name="bsui/drawer-backdrop"></block>
	<block name="bsui/drawer-popup">
		<p>Filter the list below.</p>
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
		<block name="bsui/drawer-close" label="Close"></block>
	</block>
</block>
