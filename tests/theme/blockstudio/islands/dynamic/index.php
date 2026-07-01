<div class="bs-island-fragment" data-phase="<?php echo esc_attr( $islandPhase ); ?>">
	<span class="bs-island-message"><?php echo esc_html( $a['message'] ?? '' ); ?></span>
	<span class="bs-island-secret"><?php echo esc_html( $a['secret'] ?? '' ); ?></span>
	<span class="bs-island-user"><?php echo esc_html( is_user_logged_in() ? wp_get_current_user()->user_login : 'guest' ); ?></span>
</div>
