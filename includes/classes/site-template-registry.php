<?php
/**
 * Site template registry class.
 *
 * @package Blockstudio
 */

namespace Blockstudio;

/**
 * Central registry for file-backed Site Editor templates and template parts.
 *
 * @since 7.5.0
 */
final class Site_Template_Registry {

	/**
	 * Singleton instance.
	 *
	 * @var Site_Template_Registry|null
	 */
	private static ?Site_Template_Registry $instance = null;

	/**
	 * Registered wp_template definitions indexed by slug.
	 *
	 * @var array<string, array>
	 */
	private array $templates = array();

	/**
	 * Registered wp_template_part definitions indexed by slug.
	 *
	 * @var array<string, array>
	 */
	private array $parts = array();

	/**
	 * Registered discovery paths.
	 *
	 * @var array{templates: array<int, string>, parts: array<int, string>}
	 */
	private array $paths = array(
		'templates' => array(),
		'parts'     => array(),
	);

	/**
	 * Discovery errors.
	 *
	 * @var array<int, array>
	 */
	private array $errors = array();

	/**
	 * Get the singleton instance.
	 *
	 * @return Site_Template_Registry Registry instance.
	 */
	public static function instance(): Site_Template_Registry {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Reset the singleton registry.
	 *
	 * @return void
	 */
	public static function reset(): void {
		if ( null !== self::$instance ) {
			self::$instance->templates = array();
			self::$instance->parts     = array();
			self::$instance->paths     = array(
				'templates' => array(),
				'parts'     => array(),
			);
			self::$instance->errors    = array();
		}
	}

	/**
	 * Private constructor.
	 */
	private function __construct() {
	}

	/**
	 * Load registry data from a cache payload.
	 *
	 * @param array $payload Cache payload.
	 *
	 * @return void
	 */
	public function load( array $payload ): void {
		$this->templates = is_array( $payload['templates'] ?? null ) ? $payload['templates'] : array();
		$this->parts     = is_array( $payload['parts'] ?? null ) ? $payload['parts'] : array();
		$this->paths     = is_array( $payload['paths'] ?? null )
			? wp_parse_args(
				$payload['paths'],
				array(
					'templates' => array(),
					'parts'     => array(),
				)
			)
			: array(
				'templates' => array(),
				'parts'     => array(),
			);
		$this->errors    = is_array( $payload['errors'] ?? null ) ? $payload['errors'] : array();
	}

	/**
	 * Get cacheable registry data.
	 *
	 * @return array Registry payload.
	 */
	public function to_array(): array {
		return array(
			'templates' => $this->templates,
			'parts'     => $this->parts,
			'paths'     => $this->paths,
			'errors'    => $this->errors,
		);
	}

	/**
	 * Register a template.
	 *
	 * @param string $slug Template slug.
	 * @param array  $data Template data.
	 *
	 * @return void
	 */
	public function register_template( string $slug, array $data ): void {
		$this->templates[ $slug ] = $data;
	}

	/**
	 * Register a template part.
	 *
	 * @param string $slug Template part slug.
	 * @param array  $data Template part data.
	 *
	 * @return void
	 */
	public function register_part( string $slug, array $data ): void {
		$this->parts[ $slug ] = $data;
	}

	/**
	 * Get registered templates.
	 *
	 * @return array<string, array> Templates.
	 */
	public function get_templates(): array {
		return $this->templates;
	}

	/**
	 * Get registered template parts.
	 *
	 * @return array<string, array> Template parts.
	 */
	public function get_parts(): array {
		return $this->parts;
	}

	/**
	 * Get a template by slug.
	 *
	 * @param string $slug Template slug.
	 *
	 * @return array|null Template data.
	 */
	public function get_template( string $slug ): ?array {
		return $this->templates[ $slug ] ?? null;
	}

	/**
	 * Get a template part by slug.
	 *
	 * @param string $slug Template part slug.
	 *
	 * @return array|null Template part data.
	 */
	public function get_part( string $slug ): ?array {
		return $this->parts[ $slug ] ?? null;
	}

	/**
	 * Set registered paths.
	 *
	 * @param array $paths Paths keyed by templates and parts.
	 *
	 * @return void
	 */
	public function set_paths( array $paths ): void {
		$this->paths = array(
			'templates' => array_values( array_unique( array_map( 'wp_normalize_path', $paths['templates'] ?? array() ) ) ),
			'parts'     => array_values( array_unique( array_map( 'wp_normalize_path', $paths['parts'] ?? array() ) ) ),
		);
	}

	/**
	 * Get registered paths.
	 *
	 * @return array{templates: array<int, string>, parts: array<int, string>} Paths.
	 */
	public function get_paths(): array {
		return $this->paths;
	}

	/**
	 * Add discovery errors.
	 *
	 * @param array $errors Errors.
	 *
	 * @return void
	 */
	public function add_errors( array $errors ): void {
		$this->errors = array_merge( $this->errors, $errors );
	}

	/**
	 * Get discovery errors.
	 *
	 * @return array<int, array> Errors.
	 */
	public function get_errors(): array {
		return $this->errors;
	}
}
