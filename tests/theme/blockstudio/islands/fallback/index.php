<?php if ( $isIslandPlaceholder ) : ?>
	<div class="bs-island-fallback-placeholder" data-phase="<?php echo esc_attr( $islandPhase ); ?>">
		<?php echo esc_html( $a['message'] ?? '' ); ?>
	</div>
<?php else : ?>
	<div class="bs-island-fallback-fragment" data-phase="<?php echo esc_attr( $islandPhase ); ?>">
		<?php echo esc_html( $a['message'] ?? '' ); ?>
	</div>
<?php endif; ?>
