<?php
/**
 * Runtime settings class.
 *
 * @package Blockstudio
 */

namespace Blockstudio;

/**
 * Resolves the generic frontend runtime section of blockstudio.json.
 *
 * The compat profile preserves WordPress defaults. Speed and strict are
 * opt-in profiles whose individual values can still be overridden explicitly
 * in blockstudio.json or with Blockstudio filters.
 *
 * @since 7.6.0
 */
final class Runtime_Settings {

	/**
	 * Cached resolved instance.
	 *
	 * @var Runtime_Settings|null
	 */
	private static ?Runtime_Settings $instance = null;

	/**
	 * Settings fingerprint used by the cached instance.
	 *
	 * @var string
	 */
	private static string $settings_fingerprint = '';

	/**
	 * Resolved configuration.
	 *
	 * @var array<string, mixed>
	 */
	private array $data;

	/**
	 * Validation errors.
	 *
	 * @var string[]
	 */
	private array $errors;

	/**
	 * Constructor.
	 *
	 * @param array<string, mixed> $data   Resolved configuration.
	 * @param string[]             $errors Validation errors.
	 */
	private function __construct( array $data, array $errors ) {
		$this->data   = $data;
		$this->errors = $errors;
	}

	/**
	 * Get the current resolved runtime settings.
	 *
	 * @return Runtime_Settings Resolved runtime settings.
	 */
	public static function current(): Runtime_Settings {
		$fingerprint = Settings::fingerprint();

		if ( null === self::$instance || self::$settings_fingerprint !== $fingerprint ) {
			self::$settings_fingerprint = $fingerprint;
			self::$instance             = self::resolve();
		}

		return self::$instance;
	}

	/**
	 * Reset the resolved runtime cache.
	 *
	 * @return void
	 */
	public static function reset(): void {
		self::$instance             = null;
		self::$settings_fingerprint = '';
	}

	/**
	 * Get the complete resolved configuration.
	 *
	 * @return array<string, mixed> Resolved configuration.
	 */
	public function all(): array {
		return $this->data;
	}

	/**
	 * Get a slash- or dot-delimited runtime value.
	 *
	 * @param string $path    Runtime setting path.
	 * @param mixed  $default Fallback value.
	 *
	 * @return mixed Resolved value.
	 */
	public function value( string $path, $default = null ) {
		$split    = preg_split( '#[./]+#', trim( $path ) );
		$segments = array_values(
			array_filter(
				is_array( $split ) ? $split : array(),
				static fn( string $segment ): bool => '' !== $segment
			)
		);
		$value    = $this->data;

		foreach ( $segments as $segment ) {
			if ( ! is_array( $value ) || ! array_key_exists( $segment, $value ) ) {
				return $default;
			}

			$value = $value[ $segment ];
		}

		return $value;
	}

	/**
	 * Test whether a runtime value is strictly enabled.
	 *
	 * @param string $path Runtime setting path.
	 *
	 * @return bool Whether the setting is true.
	 */
	public function enabled( string $path ): bool {
		return true === $this->value( $path, false );
	}

	/**
	 * Get validation errors.
	 *
	 * @return string[] Validation errors.
	 */
	public function errors(): array {
		return $this->errors;
	}

	/**
	 * Get a deterministic hash of the effective runtime configuration.
	 *
	 * @return string Configuration hash.
	 */
	public function hash(): string {
		$encoded = wp_json_encode( $this->data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );

		return hash( 'sha256', is_string( $encoded ) ? $encoded : '' );
	}

	/**
	 * Resolve defaults, profile values, explicit settings, and filters.
	 *
	 * @return Runtime_Settings Resolved runtime settings.
	 */
	private static function resolve(): Runtime_Settings {
		$errors   = Settings::errors();
		$defaults = apply_filters( 'blockstudio/performance/default_config', self::defaults() );

		if ( ! is_array( $defaults ) ) {
			$errors[] = 'blockstudio/performance/default_config must return an array.';
			$defaults = self::defaults();
		}

		$default_errors = self::validate( $defaults );
		if ( array() !== $default_errors ) {
			$errors   = array_merge( $errors, $default_errors );
			$defaults = self::defaults();
		}

		$raw_source = Settings::get_raw();
		$raw        = $raw_source['performance'] ?? array();

		if ( ! is_array( $raw ) ) {
			$errors[] = 'performance must be an object.';
			$raw      = array();
		}

		$raw_errors = self::validate( $raw, false );
		if ( array() !== $raw_errors ) {
			$errors = array_merge( $errors, $raw_errors );
			$raw    = array();
		}

		$profile = is_string( $raw['profile'] ?? null )
			? strtolower( trim( $raw['profile'] ) )
			: (string) ( $defaults['profile'] ?? 'compat' );
		$config  = self::merge( $defaults, self::profile( $profile ) );
		$config  = self::merge( $config, $raw );
		$config  = self::filter_values( $config );
		$config  = apply_filters( 'blockstudio/performance/config', $config );

		if ( ! is_array( $config ) ) {
			$errors[] = 'blockstudio/performance/config must return an array.';
			$config   = $defaults;
		}

		$effective_errors = self::validate( $config );
		if ( array() !== $effective_errors ) {
			$errors = array_merge( $errors, $effective_errors );
			$config = $defaults;
		}

		return new self( $config, array_values( array_unique( $errors ) ) );
	}

	/**
	 * Default compat configuration.
	 *
	 * @return array<string, mixed> Default configuration.
	 */
	private static function defaults(): array {
		return array(
			'profile'         => 'compat',
			'wordpress'       => array(
				'headNoise'      => false,
				'embeds'         => false,
				'xmlrpc'         => false,
				'editor'         => false,
				'frontendAssets' => false,
				'media'          => false,
				'heartbeat'      => false,
			),
			'preload'         => array(
				'links' => 'off',
			),
			'media'           => array(
				'lazy'       => false,
				'skeleton'   => false,
				'metadata'   => false,
				'rootMargin' => '300px',
			),
			'measurement'     => array(
				'enabled'      => false,
				'queryMonitor' => false,
				'headers'      => false,
				'timings'      => false,
			),
			'staticPrerender' => array(
				'enabled'       => false,
				'ttl'           => 86400,
				'invalidate'    => 'signature',
				'earlyServe'    => false,
				'serveLoggedIn' => false,
				'dynamicPaths'  => array(),
				'warm'          => array(
					'enabled'     => false,
					'interval'    => 3600,
					'concurrency' => 2,
					'transport'   => 'http',
				),
			),
		);
	}

	/**
	 * Resolve an opt-in performance profile.
	 *
	 * @param string $profile Profile name.
	 *
	 * @return array<string, mixed> Profile overrides.
	 */
	private static function profile( string $profile ): array {
		if ( ! in_array( $profile, array( 'speed', 'strict' ), true ) ) {
			return array(
				'profile' => 'compat',
			);
		}

		return array(
			'profile'   => $profile,
			'wordpress' => array(
				'headNoise'      => true,
				'embeds'         => true,
				'xmlrpc'         => true,
				'editor'         => true,
				'frontendAssets' => true,
				'media'          => true,
				'heartbeat'      => true,
			),
			'preload'   => array(
				'links' => 'intent',
			),
			'media'     => array(
				'lazy'     => true,
				'skeleton' => true,
				'metadata' => true,
			),
		);
	}

	/**
	 * Recursively apply stable setting filters.
	 *
	 * @param mixed    $value Current value.
	 * @param string[] $path  Current path.
	 *
	 * @return mixed Filtered value.
	 */
	private static function filter_values( $value, array $path = array() ) {
		if ( array() !== $path ) {
			$key   = implode( '/', $path );
			$value = apply_filters( 'blockstudio/settings/performance/' . $key, $value );
			$value = apply_filters( 'blockstudio/performance/' . $key, $value );
		}

		if ( ! is_array( $value ) ) {
			return $value;
		}

		foreach ( $value as $key => $item ) {
			if ( is_string( $key ) ) {
				$value[ $key ] = self::filter_values( $item, array_merge( $path, array( $key ) ) );
			}
		}

		return $value;
	}

	/**
	 * Validate runtime configuration.
	 *
	 * @param array<string, mixed> $config   Configuration to validate.
	 * @param bool                 $complete Whether all required keys are expected.
	 *
	 * @return string[] Validation errors.
	 */
	private static function validate( array $config, bool $complete = true ): array {
		$errors = array();
		$known  = array( 'profile', 'wordpress', 'preload', 'media', 'measurement', 'staticPrerender' );

		self::known_keys( $config, $known, 'performance', $errors );

		if (
			array_key_exists( 'profile', $config ) &&
			( ! is_string( $config['profile'] ) || ! in_array( $config['profile'], array( 'compat', 'speed', 'strict' ), true ) )
		) {
			$errors[] = 'performance.profile must be "compat", "speed", or "strict".';
		}

		self::bool_map(
			$config,
			// phpcs:ignore WordPress.WP.CapitalPDangit.MisspelledInText -- Public JSON key.
			'wordpress',
			array( 'headNoise', 'embeds', 'xmlrpc', 'editor', 'frontendAssets', 'media', 'heartbeat' ),
			$errors
		);
		self::bool_map(
			$config,
			'measurement',
			array( 'enabled', 'queryMonitor', 'headers', 'timings' ),
			$errors
		);

		if ( array_key_exists( 'preload', $config ) ) {
			if ( ! is_array( $config['preload'] ) ) {
				$errors[] = 'performance.preload must be an object.';
			} else {
				self::known_keys( $config['preload'], array( 'links' ), 'performance.preload', $errors );
				if (
					array_key_exists( 'links', $config['preload'] ) &&
					( ! is_string( $config['preload']['links'] ) || ! in_array( $config['preload']['links'], array( 'off', 'intent' ), true ) )
				) {
					$errors[] = 'performance.preload.links must be "off" or "intent".';
				}
			}
		}

		if ( array_key_exists( 'media', $config ) ) {
			if ( ! is_array( $config['media'] ) ) {
				$errors[] = 'performance.media must be an object.';
			} else {
				self::known_keys( $config['media'], array( 'lazy', 'skeleton', 'metadata', 'rootMargin' ), 'performance.media', $errors );
				foreach ( array( 'lazy', 'skeleton', 'metadata' ) as $key ) {
					if ( array_key_exists( $key, $config['media'] ) && ! is_bool( $config['media'][ $key ] ) ) {
						$errors[] = sprintf( 'performance.media.%s must be true or false.', $key );
					}
				}
				if (
					array_key_exists( 'rootMargin', $config['media'] ) &&
					( ! is_string( $config['media']['rootMargin'] ) || ! preg_match( '/^-?[0-9]+(?:px|%)$/', $config['media']['rootMargin'] ) )
				) {
					$errors[] = 'performance.media.rootMargin must be a pixel or percentage length.';
				}
			}
		}

		if ( array_key_exists( 'staticPrerender', $config ) ) {
			self::validate_static_prerender( $config['staticPrerender'], $errors );
		}

		if ( $complete ) {
			foreach ( $known as $key ) {
				if ( ! array_key_exists( $key, $config ) ) {
					$errors[] = sprintf( 'performance.%s is required in effective configuration.', $key );
				}
			}
		}

		return $errors;
	}

	/**
	 * Validate static prerender settings reserved for the runtime cache layer.
	 *
	 * @param mixed    $value  Static prerender value.
	 * @param string[] $errors Error accumulator.
	 *
	 * @return void
	 */
	private static function validate_static_prerender( $value, array &$errors ): void {
		if ( ! is_array( $value ) ) {
			$errors[] = 'performance.staticPrerender must be an object.';

			return;
		}

		self::known_keys(
			$value,
			array( 'enabled', 'ttl', 'invalidate', 'earlyServe', 'serveLoggedIn', 'dynamicPaths', 'warm' ),
			'performance.staticPrerender',
			$errors
		);

		foreach ( array( 'enabled', 'earlyServe', 'serveLoggedIn' ) as $key ) {
			if ( array_key_exists( $key, $value ) && ! is_bool( $value[ $key ] ) ) {
				$errors[] = sprintf( 'performance.staticPrerender.%s must be true or false.', $key );
			}
		}
		if ( array_key_exists( 'ttl', $value ) && ( ! is_int( $value['ttl'] ) || $value['ttl'] < 1 ) ) {
			$errors[] = 'performance.staticPrerender.ttl must be a positive integer.';
		}
		if (
			array_key_exists( 'invalidate', $value ) &&
			( ! is_string( $value['invalidate'] ) || ! in_array( $value['invalidate'], array( 'signature', 'graph' ), true ) )
		) {
			$errors[] = 'performance.staticPrerender.invalidate must be "signature" or "graph".';
		}
		if ( array_key_exists( 'dynamicPaths', $value ) ) {
			if ( ! is_array( $value['dynamicPaths'] ) ) {
				$errors[] = 'performance.staticPrerender.dynamicPaths must be a list.';
			} else {
				foreach ( $value['dynamicPaths'] as $path ) {
					if ( ! is_string( $path ) || '' === trim( $path ) ) {
						$errors[] = 'performance.staticPrerender.dynamicPaths entries must be non-empty strings.';
						break;
					}
				}
			}
		}
		if ( array_key_exists( 'warm', $value ) ) {
			if ( ! is_array( $value['warm'] ) ) {
				$errors[] = 'performance.staticPrerender.warm must be an object.';

				return;
			}
			self::known_keys(
				$value['warm'],
				array( 'enabled', 'interval', 'concurrency', 'transport' ),
				'performance.staticPrerender.warm',
				$errors
			);
			if ( array_key_exists( 'enabled', $value['warm'] ) && ! is_bool( $value['warm']['enabled'] ) ) {
				$errors[] = 'performance.staticPrerender.warm.enabled must be true or false.';
			}
			if ( array_key_exists( 'interval', $value['warm'] ) && ( ! is_int( $value['warm']['interval'] ) || $value['warm']['interval'] < 60 ) ) {
				$errors[] = 'performance.staticPrerender.warm.interval must be at least 60.';
			}
			if ( array_key_exists( 'concurrency', $value['warm'] ) && ( ! is_int( $value['warm']['concurrency'] ) || $value['warm']['concurrency'] < 1 ) ) {
				$errors[] = 'performance.staticPrerender.warm.concurrency must be positive.';
			}
			if (
				array_key_exists( 'transport', $value['warm'] ) &&
				( ! is_string( $value['warm']['transport'] ) || ! in_array( $value['warm']['transport'], array( 'http', 'internal' ), true ) )
			) {
				$errors[] = 'performance.staticPrerender.warm.transport must be "http" or "internal".';
			}
		}
	}

	/**
	 * Validate an object containing only booleans.
	 *
	 * @param array<string, mixed> $config Configuration.
	 * @param string               $key    Object key.
	 * @param string[]             $known  Known child keys.
	 * @param string[]             $errors Error accumulator.
	 *
	 * @return void
	 */
	private static function bool_map( array $config, string $key, array $known, array &$errors ): void {
		if ( ! array_key_exists( $key, $config ) ) {
			return;
		}
		if ( ! is_array( $config[ $key ] ) ) {
			$errors[] = sprintf( 'performance.%s must be an object.', $key );

			return;
		}

		self::known_keys( $config[ $key ], $known, 'performance.' . $key, $errors );
		foreach ( $config[ $key ] as $child => $value ) {
			if ( ! is_bool( $value ) ) {
				$errors[] = sprintf( 'performance.%s.%s must be true or false.', $key, (string) $child );
			}
		}
	}

	/**
	 * Report unsupported object keys.
	 *
	 * @param array<mixed> $value  Object values.
	 * @param string[]     $known  Known keys.
	 * @param string       $path   Error path.
	 * @param string[]     $errors Error accumulator.
	 *
	 * @return void
	 */
	private static function known_keys( array $value, array $known, string $path, array &$errors ): void {
		foreach ( array_keys( $value ) as $key ) {
			if ( ! is_string( $key ) || ! in_array( $key, $known, true ) ) {
				$errors[] = sprintf( '%s.%s is not supported.', $path, (string) $key );
			}
		}
	}

	/**
	 * Deep merge configuration arrays.
	 *
	 * @param array<string, mixed> $base      Base configuration.
	 * @param array<string, mixed> $overrides Overrides.
	 *
	 * @return array<string, mixed> Merged configuration.
	 */
	private static function merge( array $base, array $overrides ): array {
		foreach ( $overrides as $key => $value ) {
			if ( is_array( $value ) && is_array( $base[ $key ] ?? null ) ) {
				$base[ $key ] = self::merge( $base[ $key ], $value );
			} else {
				$base[ $key ] = $value;
			}
		}

		return $base;
	}
}
