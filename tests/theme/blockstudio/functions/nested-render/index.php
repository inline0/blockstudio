<div class="nested-render-helper">
	<?php
	bs_render_block(
		array(
			'name' => 'blockstudio/component-richtext-default',
			'data' => array(
				'richtext'  => $a['label'] ?? 'Nested rendered label',
				'richtext2' => 'Nested render second',
				'richtext3' => 'Nested render third',
			),
		)
	);
	?>
</div>
