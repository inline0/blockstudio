<?php
$name     = ! empty( $a['name'] ?: $a['nameAlt'] ) ? $a['name'] ?: $a['nameAlt'] : '';
$accept   = ! empty( $a['accept'] ) ? $a['accept'] : '*';
$multiple = ! empty( $a['multiple'] );
$disabled = ! empty( $a['disabled'] );
?>
<label
	data-bsui-file-upload
	<?php if ( $disabled ) echo 'data-disabled'; ?>
>
	<input
		type="file"
		<?php if ( '' !== $name ) : ?>name="<?php echo esc_attr( $name ); ?>"<?php endif; ?>
		accept="<?php echo esc_attr( $accept ); ?>"
		<?php if ( $multiple ) echo 'multiple'; ?>
		<?php if ( $disabled ) echo 'disabled'; ?>
	/>
	<div data-bsui-file-upload-content>
		<div data-bsui-file-upload-icon></div>
		<span data-bsui-text data-variant="muted">Drag & drop or click to upload</span>
	</div>
</label>
