<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$isP = isset( $isPreview ) && $isPreview;
$isE = isset( $isEditor ) && $isEditor;

?>

<?php if ( $isP ) : ?>
	<div class="blockstudio-element__preview">
		<?php echo esc_html__( 'Gallery', 'blockstudio' ); ?>
	</div>
<?php elseif ( ! empty( $a['images'] ) ) : ?>
	<div
		class="blockstudio-element-gallery blockstudio-element-gallery--<?php echo esc_attr( $a['type'] ); ?>"
		style="
			--blockstudio-element-gallery--width: <?php echo esc_attr( $a['width'] ); ?>px;
			--blockstudio-element-gallery--gap: <?php echo esc_attr( $a['gap'] ); ?>px;
		"
	>
		<?php foreach ( $a['images'] as $image ) : ?>
			<div class="blockstudio-element-gallery__content">
				<img
					src="<?php echo esc_url( $image['url'] ?? '' ); ?>"
					class="blockstudio-element-gallery__image"
                    alt=""
				/>
			</div>
		<?php endforeach; ?>
	</div>
<?php elseif ( $isE ) : ?>
	<div class="blockstudio-element__placeholder">
		<?php echo esc_html__( 'No images selected', 'blockstudio' ); ?>
	</div>
<?php endif; ?>
