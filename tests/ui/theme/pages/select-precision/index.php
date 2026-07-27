<p>The overlay select sits far enough down the page for the popup to cover it without clamping.</p>
<p>Opening it must place the selected option label exactly over the trigger label.</p>
<p>The plain select next to it has no default value and must drop below its trigger.</p>
<block name="bsui/select" placeholder="Choose a fruit" defaultValue="cherry" nameAlt="picked">
	<block name="bsui/select-trigger"></block>
	<block name="bsui/select-popup">
		<block name="bsui/select-option" value="apple" label="Apple"></block>
		<block name="bsui/select-option" value="banana" label="Banana"></block>
		<block name="bsui/select-option" value="cherry" label="Cherry"></block>
		<block name="bsui/select-option" value="grape" label="Grape"></block>
		<block name="bsui/select-option" value="mango" label="Mango"></block>
	</block>
</block>
<block name="bsui/select" placeholder="Pick a colour" nameAlt="plain">
	<block name="bsui/select-trigger"></block>
	<block name="bsui/select-popup">
		<block name="bsui/select-option" value="red" label="Red"></block>
		<block name="bsui/select-option" value="green" label="Green"></block>
		<block name="bsui/select-option" value="blue" label="Blue"></block>
	</block>
</block>
