<?php
/**
 * WordPress optimizations class.
 *
 * @package Blockstudio
 */

namespace Blockstudio;

/**
 * Applies opt-in generic WordPress frontend and editor optimizations.
 *
 * @since 7.6.0
 */
final class WordPress_Optimizations {

	/**
	 * Whether hooks have been registered.
	 *
	 * @var bool
	 */
	private static bool $registered = false;

	/**
	 * Register configured optimizations.
	 *
	 * @return void
	 */
	public static function register(): void {
		if ( self::$registered ) {
			return;
		}

		self::$registered = true;
		$config           = Runtime_Settings::current();

		if ( $config->enabled( 'wordpress/headNoise' ) ) {
			self::remove_default_head_noise();
		}
		if ( $config->enabled( 'wordpress/embeds' ) ) {
			self::remove_embed_noise();
		}
		if ( $config->enabled( 'wordpress/xmlrpc' ) ) {
			self::register_xmlrpc_hardening();
		}
		if ( $config->enabled( 'wordpress/editor' ) ) {
			self::register_editor_optimizations();
		}
		if ( $config->enabled( 'wordpress/frontendAssets' ) ) {
			self::register_frontend_asset_optimizations();
		}
		if ( $config->enabled( 'wordpress/media' ) ) {
			self::register_media_optimizations();
		}
		if ( $config->enabled( 'wordpress/heartbeat' ) ) {
			self::register_runtime_throttles();
		}
	}

	/**
	 * Return false for WordPress filters.
	 *
	 * @param mixed ...$unused Unused filter arguments.
	 *
	 * @return bool False.
	 */
	public static function return_false( ...$unused ): bool {
		unset( $unused );

		return false;
	}

	/**
	 * Return true for WordPress filters.
	 *
	 * @param mixed ...$unused Unused filter arguments.
	 *
	 * @return bool True.
	 */
	public static function return_true( ...$unused ): bool {
		unset( $unused );

		return true;
	}

	/**
	 * Return the closed ping status.
	 *
	 * @param mixed ...$unused Unused filter arguments.
	 *
	 * @return string Closed.
	 */
	public static function return_closed( ...$unused ): string {
		unset( $unused );

		return 'closed';
	}

	/**
	 * Return zero for WordPress filters.
	 *
	 * @param mixed ...$unused Unused filter arguments.
	 *
	 * @return int Zero.
	 */
	public static function return_zero( ...$unused ): int {
		unset( $unused );

		return 0;
	}

	/**
	 * Remove pingback response headers.
	 *
	 * @param array<string, string> $headers Headers.
	 *
	 * @return array<string, string> Filtered headers.
	 */
	public static function filter_wp_headers( array $headers ): array {
		unset( $headers['X-Pingback'], $headers['x-pingback'] );

		return $headers;
	}

	/**
	 * Remove the TinyMCE emoji plugin.
	 *
	 * @param string[] $plugins Plugins.
	 *
	 * @return string[] Filtered plugins.
	 */
	public static function filter_tinymce_plugins( array $plugins ): array {
		return array_values(
			array_filter(
				$plugins,
				static fn( string $plugin ): bool => 'wpemoji' !== $plugin
			)
		);
	}

	/**
	 * Remove obsolete emoji DNS hints.
	 *
	 * @param string[] $urls          URLs.
	 * @param string   $relation_type Hint relation.
	 *
	 * @return string[] Filtered URLs.
	 */
	public static function filter_resource_hints( array $urls, string $relation_type ): array {
		if ( 'dns-prefetch' !== $relation_type ) {
			return $urls;
		}

		return array_values(
			array_filter(
				$urls,
				static function ( string $url ): bool {
					$parts = wp_parse_url( $url );
					$host  = is_array( $parts ) && isset( $parts['host'] )
						? strtolower( (string) $parts['host'] )
						: strtolower( $url );

					return ! in_array(
						$host,
						array( 's.w.org', 'twemoji.maxcdn.com', 'emoji.wordpress.org' ),
						true
					);
				}
			)
		);
	}

	/**
	 * Disable remote editor discovery surfaces.
	 *
	 * @param array<string, mixed> $settings Editor settings.
	 * @param mixed                $context  Editor context.
	 *
	 * @return array<string, mixed> Filtered settings.
	 */
	public static function filter_block_editor_settings( array $settings, $context = null ): array {
		unset( $context );
		$settings['enableOpenverseMediaCategory'] = false;
		$settings['enableRemoteBlockPatterns']    = false;

		return $settings;
	}

	/**
	 * Remove jquery-migrate from the jquery dependency list.
	 *
	 * @param mixed $scripts WordPress script registry.
	 *
	 * @return void
	 */
	public static function remove_jquery_migrate( $scripts ): void {
		if (
			! is_object( $scripts ) ||
			! isset( $scripts->registered ) ||
			! is_array( $scripts->registered ) ||
			! isset( $scripts->registered['jquery'] ) ||
			! is_object( $scripts->registered['jquery'] )
		) {
			return;
		}

		$jquery = $scripts->registered['jquery'];
		if ( ! isset( $jquery->deps ) || ! is_array( $jquery->deps ) ) {
			return;
		}

		$jquery->deps = array_values(
			array_filter(
				$jquery->deps,
				static fn( $dependency ): bool => 'jquery-migrate' !== $dependency
			)
		);
	}

	/**
	 * Dequeue generic WordPress frontend assets.
	 *
	 * @return void
	 */
	public static function optimize_frontend_assets(): void {
		if ( is_admin() ) {
			return;
		}

		foreach ( array( 'wp-block-library', 'wp-block-library-theme', 'global-styles', 'classic-theme-styles' ) as $handle ) {
			wp_dequeue_style( $handle );
		}
		if ( ! is_user_logged_in() ) {
			wp_dequeue_style( 'dashicons' );
		}
		foreach ( array( 'wp-embed', 'jquery', 'jquery-migrate' ) as $handle ) {
			wp_dequeue_script( $handle );
		}
		if ( ! self::should_keep_comment_reply_script() ) {
			wp_dequeue_script( 'comment-reply' );
		}
	}

	/**
	 * Slow heartbeat outside editor screens.
	 *
	 * @param array<string, mixed> $settings Heartbeat settings.
	 *
	 * @return array<string, mixed> Filtered settings.
	 */
	public static function filter_heartbeat_settings( array $settings ): array {
		if ( self::is_editor_screen() ) {
			return $settings;
		}

		$settings['interval'] = max( 60, (int) ( $settings['interval'] ?? 0 ) );

		return $settings;
	}

	/**
	 * Set modern image quality defaults.
	 *
	 * @param int    $quality   Existing quality.
	 * @param string $mime_type Image mime type.
	 *
	 * @return int Quality.
	 */
	public static function filter_image_quality( int $quality, string $mime_type ): int {
		unset( $quality );

		return match ( $mime_type ) {
			'image/jpeg', 'image/webp' => 82,
			default => 90,
		};
	}

	/**
	 * Remove very large generated image sizes.
	 *
	 * @param array<string, mixed> $sizes         Image sizes.
	 * @param mixed                $image_meta    Image metadata.
	 * @param mixed                $attachment_id Attachment ID.
	 *
	 * @return array<string, mixed> Filtered sizes.
	 */
	public static function filter_intermediate_image_sizes( array $sizes, $image_meta = null, $attachment_id = null ): array {
		unset( $image_meta, $attachment_id );
		unset( $sizes['1536x1536'], $sizes['2048x2048'] );

		return $sizes;
	}

	/**
	 * Add async decoding to attachment images.
	 *
	 * @param array<string, string> $attributes Image attributes.
	 * @param mixed                 $attachment Attachment.
	 * @param mixed                 $size       Image size.
	 *
	 * @return array<string, string> Filtered attributes.
	 */
	public static function filter_attachment_image_attributes( array $attributes, $attachment = null, $size = null ): array {
		unset( $attachment, $size );
		$attributes['decoding'] ??= 'async';

		return $attributes;
	}

	/**
	 * Remove default head noise.
	 *
	 * @return void
	 */
	private static function remove_default_head_noise(): void {
		remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
		remove_action( 'admin_print_scripts', 'print_emoji_detection_script' );
		remove_action( 'embed_head', 'print_emoji_detection_script' );
		remove_action( 'wp_print_styles', 'print_emoji_styles' );
		remove_action( 'admin_print_styles', 'print_emoji_styles' );
		remove_action( 'wp_enqueue_scripts', 'wp_enqueue_emoji_styles' );
		remove_action( 'admin_print_styles', 'wp_enqueue_emoji_styles' );
		remove_filter( 'the_content_feed', 'wp_staticize_emoji' );
		remove_filter( 'comment_text_rss', 'wp_staticize_emoji' );
		remove_filter( 'wp_mail', 'wp_staticize_emoji_for_email' );
		remove_action( 'wp_head', 'wp_generator' );
		remove_action( 'wp_head', 'wlwmanifest_link' );
		remove_action( 'wp_head', 'rsd_link' );
		remove_action( 'wp_head', 'wp_shortlink_wp_head' );
		remove_action( 'template_redirect', 'wp_shortlink_header', 11 );
		remove_action( 'wp_head', 'rest_output_link_wp_head', 10 );
		remove_action( 'template_redirect', 'rest_output_link_header', 11 );
		remove_action( 'wp_head', 'feed_links', 2 );
		remove_action( 'wp_head', 'feed_links_extra', 3 );
		remove_action( 'wp_head', 'adjacent_posts_rel_link_wp_head', 10 );
		add_filter( 'tiny_mce_plugins', array( self::class, 'filter_tinymce_plugins' ) );
		add_filter( 'wp_resource_hints', array( self::class, 'filter_resource_hints' ), 10, 2 );
	}

	/**
	 * Remove oEmbed discovery output.
	 *
	 * @return void
	 */
	private static function remove_embed_noise(): void {
		remove_action( 'wp_head', 'wp_oembed_add_discovery_links' );
		remove_action( 'wp_head', 'wp_oembed_add_host_js' );
		remove_filter( 'oembed_dataparse', 'wp_filter_oembed_result', 10 );
	}

	/**
	 * Register XML-RPC and pingback hardening.
	 *
	 * @return void
	 */
	private static function register_xmlrpc_hardening(): void {
		add_filter( 'xmlrpc_enabled', array( self::class, 'return_false' ) );
		add_filter( 'wp_headers', array( self::class, 'filter_wp_headers' ) );
		add_filter( 'pings_open', array( self::class, 'return_false' ) );
		add_filter( 'pre_option_default_ping_status', array( self::class, 'return_closed' ) );
		add_filter( 'pre_option_default_pingback_flag', array( self::class, 'return_zero' ) );
	}

	/**
	 * Register editor optimizations.
	 *
	 * @return void
	 */
	private static function register_editor_optimizations(): void {
		add_filter( 'should_load_remote_block_patterns', array( self::class, 'return_false' ) );
		add_filter( 'block_editor_settings_all', array( self::class, 'filter_block_editor_settings' ), 10, 2 );
		add_filter( 'wp_is_application_passwords_available', array( self::class, 'return_false' ) );
		remove_action( 'enqueue_block_editor_assets', 'wp_enqueue_editor_block_directory_assets' );
	}

	/**
	 * Register frontend asset optimizations.
	 *
	 * @return void
	 */
	private static function register_frontend_asset_optimizations(): void {
		add_action( 'wp_default_scripts', array( self::class, 'remove_jquery_migrate' ) );
		add_filter( 'should_load_separate_core_block_assets', array( self::class, 'return_true' ) );
		add_action( 'wp_enqueue_scripts', array( self::class, 'optimize_frontend_assets' ), 100 );
		remove_action( 'wp_enqueue_scripts', 'wp_enqueue_global_styles' );
		remove_action( 'wp_footer', 'wp_enqueue_global_styles', 1 );
		remove_action( 'wp_body_open', 'wp_global_styles_render_svg_filters' );
		remove_action( 'wp_enqueue_scripts', 'wp_enqueue_classic_theme_styles' );
	}

	/**
	 * Register image optimizations.
	 *
	 * @return void
	 */
	private static function register_media_optimizations(): void {
		add_filter( 'wp_editor_set_quality', array( self::class, 'filter_image_quality' ), 10, 2 );
		add_filter( 'intermediate_image_sizes_advanced', array( self::class, 'filter_intermediate_image_sizes' ), 10, 3 );
		add_filter( 'wp_get_attachment_image_attributes', array( self::class, 'filter_attachment_image_attributes' ), 10, 3 );
	}

	/**
	 * Register runtime throttles.
	 *
	 * @return void
	 */
	private static function register_runtime_throttles(): void {
		add_filter( 'heartbeat_settings', array( self::class, 'filter_heartbeat_settings' ) );
	}

	/**
	 * Whether comment-reply must remain enqueued.
	 *
	 * @return bool Whether the script is needed.
	 */
	private static function should_keep_comment_reply_script(): bool {
		return is_singular() && comments_open() && (bool) get_option( 'thread_comments' );
	}

	/**
	 * Whether the current request is an editor screen.
	 *
	 * @return bool Whether this is an editor screen.
	 */
	private static function is_editor_screen(): bool {
		if ( ! is_admin() ) {
			return false;
		}
		if ( function_exists( 'get_current_screen' ) ) {
			$screen = get_current_screen();
			if ( $screen instanceof \WP_Screen ) {
				return in_array( $screen->id, array( 'post', 'page', 'site-editor' ), true );
			}
		}

		$pagenow = isset( $GLOBALS['pagenow'] ) && is_string( $GLOBALS['pagenow'] ) ? $GLOBALS['pagenow'] : '';

		return in_array( $pagenow, array( 'post.php', 'post-new.php', 'site-editor.php' ), true );
	}
}
