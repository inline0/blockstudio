<?php
/**
 * Block Registry class.
 *
 * @package Blockstudio
 */

namespace Blockstudio;

use WP_Block_Type;

/**
 * Centralized singleton registry for all Blockstudio state.
 *
 * This class replaces the static properties that were previously scattered across
 * the Build class (v6). It provides a single source of truth for:
 *
 * - Registered blocks and their metadata
 * - Block extensions and overrides
 * - Discovered files from filesystem scanning
 * - Assets (CSS/JS) for various contexts (admin, editor, global)
 * - Blade template configurations
 * - Instance paths for block discovery
 *
 * By centralizing state here, we eliminate circular dependencies between Build
 * and Block classes, and make the codebase easier to test (via reset() method).
 *
 * Usage:
 *   $registry = Block_Registry::instance();
 *   $blocks = $registry->get_blocks();
 *   $registry->register_block( 'blockstudio/my-block', $block_type );
 *
 * @since 7.0.0
 */
final class Block_Registry {

	/**
	 * Singleton instance.
	 *
	 * @var Block_Registry|null
	 */
	private static ?Block_Registry $instance = null;

	/**
	 * Registered WP_Block_Type instances indexed by block name.
	 *
	 * Contains all Blockstudio blocks that have been registered with WordPress.
	 * Key is the full block name (e.g., 'blockstudio/my-block').
	 *
	 * @var array<string, WP_Block_Type>
	 */
	private array $blocks = array();

	/**
	 * Block metadata indexed by block name.
	 *
	 * Contains parsed block.json data plus computed fields like:
	 * - path: Absolute path to block directory
	 * - files: List of files in the block directory
	 * - assets: Processed CSS/JS assets
	 * - scopedClass: Generated CSS class for scoping styles
	 *
	 * @var array<string, array>
	 */
	private array $data = array();

	/**
	 * Block extensions (blocks that extend core WordPress blocks).
	 *
	 * Extensions add attributes/controls to existing blocks without
	 * replacing them. Used for features like adding custom fields
	 * to core/paragraph or core/image blocks.
	 *
	 * @var array<WP_Block_Type>
	 */
	private array $extensions = array();

	/**
	 * All discovered files during editor mode scanning.
	 *
	 * In editor mode, every file in block directories is tracked for
	 * the code editor feature. Key is file path, value is file metadata.
	 *
	 * @var array<string, array>
	 */
	private array $files = array();

	/**
	 * Assets to register with WordPress (styles and scripts).
	 *
	 * Structured as: ['style' => [...], 'script' => [...]]
	 * Each contains handles mapped to asset data (path, mtime).
	 *
	 * @var array<string, array>
	 */
	private array $assets = array();

	/**
	 * Admin-only assets (files prefixed with 'admin-').
	 *
	 * Enqueued only on WordPress admin pages, not on frontend.
	 *
	 * @var array<string, array>
	 */
	private array $assets_admin = array();

	/**
	 * Block editor assets (files prefixed with 'block-editor-').
	 *
	 * Enqueued only when the Gutenberg block editor is active.
	 *
	 * @var array<string, array>
	 */
	private array $assets_block_editor = array();

	/**
	 * Global assets (files prefixed with 'global-').
	 *
	 * Enqueued on every page (frontend and admin).
	 * Key is sanitized handle, value is asset URL.
	 *
	 * @var array<string, string>
	 */
	private array $assets_global = array();

	/**
	 * Override block types indexed by the block name they override.
	 *
	 * Overrides modify existing Blockstudio blocks, adding or changing
	 * attributes, render callbacks, etc.
	 *
	 * @var array<string, WP_Block_Type>
	 */
	private array $overrides = array();

	/**
	 * Override configurations (parsed from override block.json files).
	 *
	 * Contains the raw configuration that will be merged into the
	 * original block during the apply_overrides phase.
	 *
	 * @var array<string, array>
	 */
	private array $override_configs = array();

	/**
	 * Override data for editor mode.
	 *
	 * Tracks override blocks separately during editor mode so their
	 * assets can be merged with the original block's assets.
	 *
	 * @var array<string, array>
	 */
	private array $data_overrides = array();

	/**
	 * Blade template engine configurations per instance.
	 *
	 * Structure: ['instance-name' => ['path' => '...', 'templates' => [...]]]
	 * Allows blocks to use Laravel Blade templating.
	 *
	 * @var array<string, array>
	 */
	private array $blade = array();

	/**
	 * Registered block discovery paths.
	 *
	 * Each entry maps an instance name to its filesystem path.
	 * Used to track where blocks are loaded from (theme, plugin, etc.).
	 *
	 * @var array<array{instance: string, path: string}>
	 */
	private array $paths = array();

	/**
	 * Registered block source instances.
	 *
	 * Tracks each location where Build::init() was called.
	 *
	 * @var array<array{path: string}>
	 */
	private array $instances = array();

	/**
	 * Whether any block uses Tailwind CSS classes field.
	 *
	 * When true, indicates Tailwind integration should be active.
	 * Set when a field has 'tailwind' => true in its config.
	 *
	 * @var bool
	 */
	private bool $is_tailwind_active = false;

	/**
	 * Get singleton instance.
	 *
	 * @return Block_Registry The singleton instance.
	 */
	public static function instance(): Block_Registry {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Private constructor.
	 */
	private function __construct() {
		// Singleton pattern.
	}

	/**
	 * Reset the registry (mainly for testing).
	 *
	 * @return void
	 */
	public function reset(): void {
		$this->blocks              = array();
		$this->data                = array();
		$this->extensions          = array();
		$this->files               = array();
		$this->assets              = array();
		$this->assets_admin        = array();
		$this->assets_block_editor = array();
		$this->assets_global       = array();
		$this->overrides           = array();
		$this->override_configs    = array();
		$this->data_overrides      = array();
		$this->blade               = array();
		$this->paths               = array();
		$this->instances           = array();
		$this->is_tailwind_active  = false;
	}

	/**
	 * Get all registered blocks.
	 *
	 * @return array<string, WP_Block_Type> The blocks.
	 */
	public function get_blocks(): array {
		return $this->blocks;
	}

	/**
	 * Get a single block by name.
	 *
	 * @param string $name The block name.
	 *
	 * @return WP_Block_Type|null The block or null.
	 */
	public function get_block( string $name ): ?WP_Block_Type {
		return $this->blocks[ $name ] ?? null;
	}

	/**
	 * Check if a block exists.
	 *
	 * @param string $name The block name.
	 *
	 * @return bool Whether the block exists.
	 */
	public function has_block( string $name ): bool {
		return isset( $this->blocks[ $name ] );
	}

	/**
	 * Get all block data.
	 *
	 * @return array<string, array> The data.
	 */
	public function get_data(): array {
		return $this->data;
	}

	/**
	 * Get data for a specific block.
	 *
	 * @param string $name The block name.
	 *
	 * @return array|null The block data or null.
	 */
	public function get_block_data( string $name ): ?array {
		return $this->data[ $name ] ?? null;
	}

	/**
	 * Get all extensions.
	 *
	 * @return array<WP_Block_Type> The extensions.
	 */
	public function get_extensions(): array {
		return $this->extensions;
	}

	/**
	 * Get all files.
	 *
	 * @return array<string, array> The files.
	 */
	public function get_files(): array {
		return $this->files;
	}

	/**
	 * Get all overrides.
	 *
	 * @return array<string, WP_Block_Type> The overrides.
	 */
	public function get_overrides(): array {
		return $this->overrides;
	}

	/**
	 * Get an override for a specific block.
	 *
	 * @param string $name The block name.
	 *
	 * @return WP_Block_Type|null The override or null.
	 */
	public function get_override( string $name ): ?WP_Block_Type {
		return $this->overrides[ $name ] ?? null;
	}

	/**
	 * Get override configuration.
	 *
	 * @param string $name The block name.
	 *
	 * @return array|null The override config or null.
	 */
	public function get_override_config( string $name ): ?array {
		return $this->override_configs[ $name ] ?? null;
	}

	/**
	 * Get all override configs.
	 *
	 * @return array<string, array> The override configs.
	 */
	public function get_override_configs(): array {
		return $this->override_configs;
	}

	/**
	 * Get Blade templates.
	 *
	 * @return array<string, array> The blade templates.
	 */
	public function get_blade(): array {
		return $this->blade;
	}

	/**
	 * Get Blade templates for a specific instance.
	 *
	 * @param string $instance The instance name.
	 *
	 * @return array|null The blade templates or null.
	 */
	public function get_blade_for_instance( string $instance ): ?array {
		return $this->blade[ $instance ] ?? null;
	}

	/**
	 * Get all registered paths.
	 *
	 * @return array<array{instance: string, path: string}> The paths.
	 */
	public function get_paths(): array {
		return $this->paths;
	}

	/**
	 * Get all instances.
	 *
	 * @return array<array{path: string}> The instances.
	 */
	public function get_instances(): array {
		return $this->instances;
	}

	/**
	 * Get all assets.
	 *
	 * @return array<string, array> The assets.
	 */
	public function get_assets(): array {
		return $this->assets;
	}

	/**
	 * Get admin assets.
	 *
	 * @return array<string, array> The admin assets.
	 */
	public function get_assets_admin(): array {
		return $this->assets_admin;
	}

	/**
	 * Get block editor assets.
	 *
	 * @return array<string, array> The block editor assets.
	 */
	public function get_assets_block_editor(): array {
		return $this->assets_block_editor;
	}

	/**
	 * Get global assets.
	 *
	 * @return array<string, string> The global assets.
	 */
	public function get_assets_global(): array {
		return $this->assets_global;
	}

	/**
	 * Check if Tailwind is active.
	 *
	 * @return bool Whether Tailwind is active.
	 */
	public function is_tailwind_active(): bool {
		return $this->is_tailwind_active;
	}

	/**
	 * Register a block.
	 *
	 * @param string        $name  The block name.
	 * @param WP_Block_Type $block The block type.
	 *
	 * @return void
	 */
	public function register_block( string $name, WP_Block_Type $block ): void {
		$this->blocks[ $name ] = $block;
	}

	/**
	 * Set block data.
	 *
	 * @param string $name The block name.
	 * @param array  $data The block data.
	 *
	 * @return void
	 */
	public function set_block_data( string $name, array $data ): void {
		$this->data[ $name ] = $data;
	}

	/**
	 * Merge block data.
	 *
	 * @param array<string, array> $data The data to merge.
	 *
	 * @return void
	 */
	public function merge_data( array $data ): void {
		$this->data = array_merge( $this->data, $data );
	}

	/**
	 * Register an extension.
	 *
	 * @param WP_Block_Type $extension The extension.
	 *
	 * @return void
	 */
	public function register_extension( WP_Block_Type $extension ): void {
		$this->extensions[] = $extension;
	}

	/**
	 * Set files.
	 *
	 * @param array<string, array> $files The files.
	 *
	 * @return void
	 */
	public function set_files( array $files ): void {
		$this->files = $files;
	}

	/**
	 * Merge files.
	 *
	 * @param array<string, array> $files The files to merge.
	 *
	 * @return void
	 */
	public function merge_files( array $files ): void {
		$this->files = array_merge( $this->files, $files );
	}

	/**
	 * Register a block override.
	 *
	 * @param string        $name   The block name.
	 * @param WP_Block_Type $block  The override block.
	 * @param array         $config The override configuration.
	 *
	 * @return void
	 */
	public function register_override( string $name, WP_Block_Type $block, array $config ): void {
		$this->overrides[ $name ]        = $block;
		$this->override_configs[ $name ] = $config;
	}

	/**
	 * Set data override.
	 *
	 * @param string $name The block name.
	 * @param array  $data The override data.
	 *
	 * @return void
	 */
	public function set_data_override( string $name, array $data ): void {
		$this->data_overrides[ $name ] = $data;
	}

	/**
	 * Get data overrides.
	 *
	 * @return array<string, array> The data overrides.
	 */
	public function get_data_overrides(): array {
		return $this->data_overrides;
	}

	/**
	 * Set Blade templates for an instance.
	 *
	 * @param string $instance  The instance name.
	 * @param string $path      The path.
	 * @param array  $templates The templates (optional).
	 *
	 * @return void
	 */
	public function set_blade_instance( string $instance, string $path, array $templates = array() ): void {
		// Preserve existing templates when re-setting the instance.
		$existing_templates       = $this->blade[ $instance ]['templates'] ?? array();
		$this->blade[ $instance ] = array(
			'path'      => $path,
			'templates' => array_merge( $existing_templates, $templates ),
		);
	}

	/**
	 * Add a Blade template.
	 *
	 * @param string $instance The instance name.
	 * @param string $name     The template name.
	 * @param string $path     The template path.
	 *
	 * @return void
	 */
	public function add_blade_template( string $instance, string $name, string $path ): void {
		if ( ! isset( $this->blade[ $instance ] ) ) {
			$this->blade[ $instance ] = array(
				'templates' => array(),
			);
		}
		$this->blade[ $instance ]['templates'][ $name ] = $path;
	}

	/**
	 * Add a path.
	 *
	 * @param string $instance The instance name.
	 * @param string $path     The path.
	 *
	 * @return void
	 */
	public function add_path( string $instance, string $path ): void {
		$this->paths[] = array(
			'instance' => $instance,
			'path'     => $path,
		);
	}

	/**
	 * Add an instance.
	 *
	 * @param string $path The path.
	 *
	 * @return void
	 */
	public function add_instance( string $path ): void {
		$this->instances[] = array(
			'path' => $path,
		);
	}

	/**
	 * Set assets.
	 *
	 * @param array<string, array> $assets The assets.
	 *
	 * @return void
	 */
	public function set_assets( array $assets ): void {
		$this->assets = $assets;
	}

	/**
	 * Add an asset.
	 *
	 * @param string $type   The asset type ('style' or 'script').
	 * @param string $handle The asset handle.
	 * @param array  $data   The asset data.
	 *
	 * @return void
	 */
	public function add_asset( string $type, string $handle, array $data ): void {
		$this->assets[ $type ][ $handle ] = $data;
	}

	/**
	 * Add an admin asset.
	 *
	 * @param string $handle The asset handle.
	 * @param array  $data   The asset data.
	 *
	 * @return void
	 */
	public function add_admin_asset( string $handle, array $data ): void {
		$this->assets_admin[ $handle ] = $data;
	}

	/**
	 * Add a block editor asset.
	 *
	 * @param string $handle The asset handle.
	 * @param array  $data   The asset data.
	 *
	 * @return void
	 */
	public function add_block_editor_asset( string $handle, array $data ): void {
		$this->assets_block_editor[ $handle ] = $data;
	}

	/**
	 * Add a global asset.
	 *
	 * @param string $handle The asset handle.
	 * @param string $url    The asset URL.
	 *
	 * @return void
	 */
	public function add_global_asset( string $handle, string $url ): void {
		$this->assets_global[ $handle ] = $url;
	}

	/**
	 * Set Tailwind active state.
	 *
	 * @param bool $active Whether Tailwind is active.
	 *
	 * @return void
	 */
	public function set_tailwind_active( bool $active ): void {
		$this->is_tailwind_active = $active;
	}

	/**
	 * Update block data for a specific block.
	 *
	 * @param string $name The block name.
	 * @param string $key  The data key.
	 * @param mixed  $value The value.
	 *
	 * @return void
	 */
	public function update_block_data( string $name, string $key, mixed $value ): void {
		if ( isset( $this->data[ $name ] ) ) {
			$this->data[ $name ][ $key ] = $value;
		}
	}

	/**
	 * Get file data for a specific path.
	 *
	 * @param string $path The file path.
	 *
	 * @return array|null The file data or null.
	 */
	public function get_file_data( string $path ): ?array {
		return $this->files[ $path ] ?? null;
	}
}
