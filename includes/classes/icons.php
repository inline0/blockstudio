<?php
/**
 * Icons class.
 *
 * @package Blockstudio
 */

namespace Blockstudio;

/**
 * Locates icon sets for the icon picker and frontend rendering.
 *
 * Sets are looked up in a directory outside the plugin first, then in the
 * bundled folder. The release ZIP ships no sets, only their checksums: a set
 * is downloaded into the outside directory when the picker first asks for
 * it, and in the background when a render or an update finds it missing.
 * Rendering itself never waits for the network.
 *
 * @since 7.7.16
 */
class Icons {

	private const FETCH_HOOK = 'blockstudio/icons/fetch';

	private const FAILED_OPTION = 'blockstudio_icons_fetch_failed';

	/**
	 * Bundled directory override.
	 *
	 * @var string|null
	 */
	private static ?string $bundled_dir = null;

	/**
	 * Checksums by bundled directory.
	 *
	 * @var array<string, array<string, string>>
	 */
	private static array $manifests = array();

	/**
	 * Register the background download and its failure notice.
	 *
	 * @return void
	 */
	public static function init(): void {
		add_action( self::FETCH_HOOK, array( self::class, 'fetch_sets' ) );
		add_action( 'admin_notices', array( self::class, 'render_fetch_notice' ) );
	}

	/**
	 * Directory of the icon sets that ship with the plugin.
	 *
	 * Source checkouts and Composer installs contain the sets. The release
	 * ZIP contains only the checksum file next to this directory.
	 *
	 * @return string
	 */
	public static function bundled_dir(): string {
		return self::$bundled_dir ?? BLOCKSTUDIO_DIR . '/includes/icons';
	}

	/**
	 * Point the bundled directory elsewhere.
	 *
	 * @param string|null $dir Directory, or null for the plugin's own.
	 *
	 * @return void
	 * @internal
	 */
	public static function use_bundled_dir( ?string $dir ): void {
		self::$bundled_dir = $dir;
	}

	/**
	 * SHA-256 checksums of every icon set, keyed by set name.
	 *
	 * @return array<string, string>
	 */
	public static function manifest(): array {
		$file = self::bundled_dir() . '.json';

		if ( ! isset( self::$manifests[ $file ] ) ) {
			$manifest                 = is_file( $file ) ? Utils::read_json_file( $file ) : null;
			self::$manifests[ $file ] = is_array( $manifest ) ? array_filter( $manifest, 'is_string' ) : array();
		}

		return self::$manifests[ $file ];
	}

	/**
	 * Directory of the icon sets stored outside the plugin.
	 *
	 * @return string The directory, or an empty string when sets stay in the plugin only.
	 */
	public static function dir(): string {
		/**
		 * Filter the directory icon sets are stored in outside the plugin.
		 *
		 * Return an empty string to keep icon sets in the plugin folder only.
		 *
		 * @since 7.7.16
		 *
		 * @param string $dir Absolute directory path.
		 */
		$dir = apply_filters( 'blockstudio/icons/dir', WP_CONTENT_DIR . '/blockstudio/icons' );

		return is_string( $dir ) ? rtrim( wp_normalize_path( $dir ), '/' ) : '';
	}

	/**
	 * Resolve an icon set name to its JSON file.
	 *
	 * @param string $name Set name, with the sub set appended after a dash.
	 *
	 * @return string The file path, or an empty string when no set matches.
	 */
	public static function set_path( string $name ): string {
		$dir  = self::dir();
		$path = '' === $dir ? '' : Utils::icon_set_path( $dir, $name );

		return '' === $path ? Utils::icon_set_path( self::bundled_dir(), $name ) : $path;
	}

	/**
	 * Download one icon set into the directory outside the plugin.
	 *
	 * Only sets named in the checksum file are requested, and a download is
	 * kept only when it matches its checksum.
	 *
	 * @param string $name Set name, with the sub set appended after a dash.
	 *
	 * @return bool Whether the set is stored now.
	 */
	public static function fetch( string $name ): bool {
		$dir  = self::dir();
		$hash = self::manifest()[ $name ] ?? '';

		if ( '' === $dir || '' === $hash || ! wp_mkdir_p( $dir ) ) {
			return false;
		}

		/**
		 * Filter the URL an icon set is downloaded from.
		 *
		 * The response has to be the set file of this version. Anything
		 * that does not match its checksum is discarded.
		 *
		 * @since 7.7.16
		 *
		 * @param string $url  Download URL.
		 * @param string $name Set name, with the sub set appended after a dash.
		 */
		$url = apply_filters(
			'blockstudio/icons/url',
			'https://github.com/inline0/blockstudio/releases/download/v' . BLOCKSTUDIO_VERSION . '/blockstudio-icons-' . $name . '.json',
			$name
		);

		$target   = $dir . '/' . $name . '.json';
		$partial  = $target . '.' . uniqid( '', true ) . '.tmp';
		$response = wp_safe_remote_get(
			(string) $url,
			array(
				'timeout'  => 30,
				'stream'   => true,
				'filename' => $partial,
			)
		);

		$stored = ! is_wp_error( $response )
			&& 200 === wp_remote_retrieve_response_code( $response )
			&& is_file( $partial )
			&& hash_equals( $hash, (string) hash_file( 'sha256', $partial ) )
			// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.WP.AlternativeFunctions.rename_rename -- Same-directory atomic swap of a verified download.
			&& @rename( $partial, $target );

		if ( ! $stored ) {
			wp_delete_file( $partial );
		}

		return $stored;
	}

	/**
	 * Download missing icon sets in the background.
	 *
	 * Stops at the first failed download, records it for the admin notice
	 * and tries again in an hour, so a site without outbound access makes
	 * one request per hour instead of one per set.
	 *
	 * @param array $names Set names. Empty for every set that is missing.
	 *
	 * @return void
	 */
	public static function fetch_sets( array $names = array() ): void {
		$failed = false;

		foreach ( array() === $names ? array_keys( self::manifest() ) : $names as $name ) {
			if ( is_string( $name ) && '' === self::set_path( $name ) && isset( self::manifest()[ $name ] ) && ! self::fetch( $name ) ) {
				$failed = true;
				break;
			}
		}

		if ( ! $failed ) {
			delete_option( self::FAILED_OPTION );
			return;
		}

		update_option( self::FAILED_OPTION, time(), false );

		if ( ! wp_next_scheduled( self::FETCH_HOOK, array( $names ) ) ) {
			wp_schedule_single_event( time() + HOUR_IN_SECONDS, self::FETCH_HOOK, array( $names ) );
		}
	}

	/**
	 * Ask for a missing icon set without waiting for it.
	 *
	 * Called when a render finds no file for a set. The download runs in
	 * WP-Cron, and not at all within an hour of a failed attempt.
	 *
	 * @param string $name Set name, with the sub set appended after a dash.
	 *
	 * @return void
	 */
	public static function request( string $name ): void {
		if ( '' === self::dir() || ! isset( self::manifest()[ $name ] ) || (int) get_option( self::FAILED_OPTION ) > time() - HOUR_IN_SECONDS ) {
			return;
		}

		if ( ! wp_next_scheduled( self::FETCH_HOOK, array( array( $name ) ) ) ) {
			wp_schedule_single_event( time(), self::FETCH_HOOK, array( array( $name ) ) );
		}
	}

	/**
	 * Download every missing icon set in the background after an update.
	 *
	 * A site that updates from a version that still shipped the sets, without
	 * passing through one that copied them, would otherwise lose its icons.
	 *
	 * @return void
	 */
	public static function request_missing(): void {
		if ( '' === self::dir() ) {
			return;
		}

		foreach ( array_keys( self::manifest() ) as $name ) {
			if ( '' === self::set_path( $name ) ) {
				if ( ! wp_next_scheduled( self::FETCH_HOOK, array( array() ) ) ) {
					wp_schedule_single_event( time(), self::FETCH_HOOK, array( array() ) );
				}
				return;
			}
		}
	}

	/**
	 * Tell administrators that icon sets could not be downloaded.
	 *
	 * @return void
	 */
	public static function render_fetch_notice(): void {
		if ( ! current_user_can( 'manage_options' ) || ! get_option( self::FAILED_OPTION ) ) {
			return;
		}

		echo '<div class="notice notice-warning"><p>'
			. esc_html__( 'Blockstudio could not download icon sets that are missing on this site. Their icons are not shown until the hourly retry succeeds.', 'blockstudio' )
			. '</p></div>';
	}

	/**
	 * Copy the bundled icon sets to the directory outside the plugin.
	 *
	 * A set that is already stored with the same content is left alone, so
	 * repeated runs only write sets that are new or changed in this version.
	 * A failed copy is not an error: the bundled set keeps resolving.
	 *
	 * @return void
	 */
	public static function store_bundled_sets(): void {
		$dir   = self::dir();
		$files = glob( self::bundled_dir() . '/*.json' );

		if ( '' === $dir || ! $files || ! wp_mkdir_p( $dir ) ) {
			return;
		}

		foreach ( $files as $file ) {
			$target = $dir . '/' . basename( $file );

			if ( is_file( $target ) && md5_file( $target ) === md5_file( $file ) ) {
				continue;
			}

			// Readers never see a partly written set: copy aside, then swap.
			$partial = $target . '.' . uniqid( '', true ) . '.tmp';

			// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.WP.AlternativeFunctions.rename_rename -- Same-directory atomic swap; the bundled set remains the fallback.
			if ( ! @copy( $file, $partial ) || ! @rename( $partial, $target ) ) {
				wp_delete_file( $partial );
			}
		}
	}
}
