<div class="blockstudio-test__block" id="<?php echo str_replace( "/", "-", $b["name"] ); ?>">
    <h1>ID: <?php echo str_replace( "/", "-", $b["name"] ); ?></h1>
    <RichText
		style="padding: 1rem; background: #f1f1f1;"
	    class='blockstudio-test__block test test2 test3'
	    tag=h1
	    attribute='richtext'
	    placeholder="Enter text here"
		allowedFormats="<?php echo esc_attr( wp_json_encode( array() ) ); ?>"
    />
	<RichText
		style="padding: 1rem; background: #f1f1f1;"
		class="blockstudio-test__block test test2 test3"
		tag="h2"
		attribute="richtext2"
		placeholder="Enter text here"
		allowedFormats="<?php echo esc_attr( wp_json_encode( array( 'core/bold', 'core/link' ) ) ); ?>"
		withoutInteractiveFormatting="true"
	/>
	<RichText
		style="padding: 1rem; background: #f1f1f1;"
		class="blockstudio-test__block test test2 test3"
		tag="h3"
		attribute="richtext3"
		placeholder="Enter text here"
		allowedFormats="<?php echo esc_attr( wp_json_encode( array( 'core/link' ) ) ); ?>"
	/>
</div>
