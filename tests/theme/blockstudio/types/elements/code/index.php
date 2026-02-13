<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$isP = isset( $isPreview ) && $isPreview;
$isE = isset( $isEditor ) && $isEditor;

$classes = [ 'blockstudio-element-code', 'language-' . esc_attr( $a['language'] ) ];
if ( $a['lineNumbers'] ) {
	$classes[] = 'line-numbers';
}

?>

<?php if ( $isP ) : ?>
	<div class="blockstudio-element__preview">
		<?php echo esc_html__( 'Code', 'blockstudio' ); ?>
	</div>
<?php elseif ( ! empty( $a['code'] ) ) : ?>
	<pre class="<?php echo esc_attr( implode( ' ', $classes ) ); ?>" data-language="<?php echo esc_attr( $a['language'] ); ?>">
		<code><?php echo esc_html( $a['code'] ); ?></code>
		<?php if ( $a['copyButton'] ) : ?>
			<div role="button" class="blockstudio-element-code__copy" aria-label="<?php esc_attr_e( 'Copy code to clipboard', 'blockstudio' ); ?>" tabindex="0">
				<span class="blockstudio-element-code__copy-text"><?php echo esc_html( $a['copyButtonText'] ); ?></span>
				<span class="blockstudio-element-code__copy-check">
					<?php
                        bs_render_icon(
                            [
                                'set'    => 'fontawesome-free',
                                'subSet' => 'solid',
                                'icon'   => 'check',
                            ]
                        );
					?>
				</span>
			</div>
		<?php endif; ?>
	</pre>
<?php elseif ( $isE ) : ?>
	<div class="blockstudio-element__placeholder">
		<?php echo esc_html__( 'No code inserted', 'blockstudio' ); ?>
	</div>
<?php endif; ?> 
