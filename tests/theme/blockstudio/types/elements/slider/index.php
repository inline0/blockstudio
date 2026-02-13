<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$options = esc_attr(
	wp_json_encode(
		[
			'loop'      => $a['loop'],
			'direction' => $a['direction'],
		]
	)
);

$isP = isset( $isPreview ) && $isPreview;
$isE = isset( $isEditor ) && $isEditor;

?>

<?php if ( $isP ) : ?>
	<div class="blockstudio-element__preview">
		<?php echo esc_html__( 'Slider', 'blockstudio' ); ?>
	</div>
<?php elseif ( ! empty( $a['images'] ) ) : ?>
	<div
		data-options="<?php echo $options; ?>"
		class="blockstudio-element-slider"
		style="--blockstudio-element-slider--height: <?php echo ! empty( $a['height'] ) ? esc_attr( $a['height'] ) . 'px' : '100%'; ?>;"
	>
		<div class="blockstudio-element-slider__wrapper">
			<?php foreach ( $a['images'] as $image ) : ?>
				<img
					src="<?php echo esc_url( $image['url'] ?? '' ); ?>"
					class="blockstudio-element-slider__slide"
					alt="" <?php // Consider adding alt text if available in $image ?>
				/>
			<?php endforeach; ?>
		</div>

		<?php if ( $a['pagination'] ) : ?>
			<div class="blockstudio-element-slider__pagination">
				<?php foreach ( $a['images'] as $index => $image ) : ?>
					<div class="blockstudio-element-slider__pagination-bullet<?php echo $index === 0 ? ' is-active' : ''; ?>"></div>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>

		<?php if ( $a['navigation'] ) : ?>
			<div
				role="button"
				class="blockstudio-element-slider__navigation blockstudio-element-slider__navigation--prev<?php echo ! $a['loop'] ? ' is-disabled' : ''; ?>"
				aria-label="<?php esc_attr_e( 'Previous slide', 'blockstudio' ); ?>"
				tabindex="0"
			>
				<?php bs_render_icon( [ 'set' => 'octicons', 'icon' => 'arrow-left-24' ] ); ?>
			</div>
			<div
				role="button"
				class="blockstudio-element-slider__navigation blockstudio-element-slider__navigation--next"
				aria-label="<?php esc_attr_e( 'Next slide', 'blockstudio' ); ?>"
				tabindex="0"
			>
				<?php bs_render_icon( [ 'set' => 'octicons', 'icon' => 'arrow-right-24' ] ); ?>
			</div>
		<?php endif; ?>
	</div>
<?php elseif ( $isE ) : ?>
	<div class="blockstudio-element__placeholder">
		<?php echo esc_html__( 'No images selected', 'blockstudio' ); ?>
	</div>
<?php endif; ?> 
