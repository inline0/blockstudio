<block name="bsui/dialog">
	<block name="bsui/dialog-trigger" label="Open Search"></block>
	<block name="bsui/dialog-backdrop"></block>
	<block name="bsui/dialog-popup">
		<h3>Search</h3>
		<block name="bsui/dialog-content">
			<block name="bsui/combobox" placeholder="Search frameworks">
				<block name="bsui/combobox-input"></block>
				<block name="bsui/combobox-popup">
					<block name="bsui/combobox-option" value="next" label="Next.js"></block>
					<block name="bsui/combobox-option" value="remix" label="Remix"></block>
					<block name="bsui/combobox-option" value="astro" label="Astro"></block>
					<block name="bsui/combobox-option" value="nuxt" label="Nuxt"></block>
				</block>
			</block>
		</block>
		<block name="bsui/dialog-close" label="Close"></block>
	</block>
</block>
