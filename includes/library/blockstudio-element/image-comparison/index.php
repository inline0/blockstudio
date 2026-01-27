<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$isP = isset( $isPreview ) && $isPreview;
$isE = isset( $isEditor ) && $isEditor;

?>

<?php if ( $isP ) : ?>
	<div class="blockstudio-element__preview">
		<?php echo esc_html__( 'Image Comparison', 'blockstudio' ); ?>
	</div>
<?php elseif ( isset( $a['images'] ) && count( $a['images'] ) === 2 ) : ?>
	<div
		style="
			--blockstudio-element-image-comparison--height: <?php echo esc_attr( $a['height'] ); ?>px;
			--blockstudio-element-image-comparison--start: <?php echo esc_attr( $a['start'] ); ?>%; <?php // Assuming start is a percentage now based on default ?>
		"
		class="blockstudio-element-image-comparison"
	>
		<div class="blockstudio-element-image-comparison__container blockstudio-element-image-comparison__container--before">
			<img
				class="blockstudio-element-image-comparison__image"
				src="<?php echo esc_url( $a['images'][0]['url'] ?? '' ); ?>"
				alt="<?php echo esc_attr__( 'Before', 'blockstudio' ); ?>"
			/>
		</div>
		<div class="blockstudio-element-image-comparison__container blockstudio-element-image-comparison__container--after">
			<img
				class="blockstudio-element-image-comparison__image"
				src="<?php echo esc_url( $a['images'][1]['url'] ?? '' ); ?>"
				alt="<?php echo esc_attr__( 'After', 'blockstudio' ); ?>"
			/>
		</div>
		<div
			role="button"
			class="blockstudio-element-image-comparison__scroller"
			aria-label="<?php esc_attr_e( 'Drag to compare images', 'blockstudio' ); ?>"
			tabindex="0"
		>
			<div class="blockstudio-element-image-comparison__thumb">
				<?php bs_render_icon( $a['icon'] ); ?>
			</div>
		</div>
	</div>
<?php elseif ( $isE ) : ?>
	<div class="blockstudio-element__placeholder">
		<?php echo esc_html__( 'Please select exactly two images', 'blockstudio' ); ?>
	</div>
<?php endif; ?> 
