<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$isP = isset( $isPreview ) && $isPreview;
$isE = isset( $isEditor ) && $isEditor;

?>

<?php if ( $isP ) : ?>
	<div class="blockstudio-element__preview">
		<?php echo esc_html__( 'Icon', 'blockstudio' ); ?>
	</div>
<?php elseif ( ! empty( $a['icon']['icon'] ) ) : ?>
	<div
		class="blockstudio-element-icon"
		style="
			--blockstudio-element-icon--size: <?php echo ! empty( $a['size'] ) ? esc_attr( $a['size'] ) . 'px' : '1em'; ?>; <?php // Changed default from 100% to 1em ?>
			--blockstudio-element-icon--color: <?php echo esc_attr( $a['color']['value'] ?? 'currentColor' ); ?>;
		"
	>
		<?php bs_render_icon( $a['icon'] ); ?>
	</div>
<?php elseif ( $isE ) : ?>
	<div class="blockstudio-element__placeholder">
		<?php echo esc_html__( 'No icon selected', 'blockstudio' ); ?>
	</div>
<?php endif; ?> 
