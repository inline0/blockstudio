<?php
/**
 * CSRF class.
 *
 * Provides CSRF protection for public Blockstudio endpoints.
 * Works for both logged-in and logged-out users.
 *
 * @package Blockstudio
 */

namespace Blockstudio;

/**
 * Generates and verifies CSRF tokens independent of WordPress user sessions.
 *
 * @since 7.1.0
 */
class Csrf {

	/**
	 * Header name for the CSRF token.
	 *
	 * @var string
	 */
	const HEADER = 'X-BS-Token';

	/**
	 * Generate a CSRF token.
	 *
	 * Uses the WordPress nonce tick (rotates every 12 hours) combined with
	 * a site-specific secret. Not tied to user ID so it works for logged-out
	 * visitors.
	 *
	 * @return string The token.
	 */
	public static function generate(): string {
		return wp_hash( wp_nonce_tick() . '|blockstudio_csrf' );
	}

	/**
	 * Verify a CSRF token.
	 *
	 * Checks both current and previous tick to handle the rotation window.
	 *
	 * @param string $token The token to verify.
	 *
	 * @return bool Whether the token is valid.
	 */
	public static function verify( string $token ): bool {
		if ( empty( $token ) ) {
			return false;
		}

		$current = wp_hash( wp_nonce_tick() . '|blockstudio_csrf' );

		if ( hash_equals( $current, $token ) ) {
			return true;
		}

		$previous = wp_hash( ( wp_nonce_tick() - 1 ) . '|blockstudio_csrf' );

		return hash_equals( $previous, $token );
	}

	/**
	 * Verify the CSRF token from a REST request.
	 *
	 * @param \WP_REST_Request $request The request.
	 *
	 * @return bool Whether the token is valid.
	 */
	public static function verify_request( $request ): bool {
		$token = $request->get_header( 'x_bs_token' );

		return self::verify( $token ?? '' );
	}
}
