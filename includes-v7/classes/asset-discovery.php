<?php
/**
 * Asset Discovery class.
 *
 * @package Blockstudio
 */

namespace Blockstudio;

/**
 * Discovers and processes assets for blocks.
 *
 * This class extracts asset discovery logic from the Build class.
 *
 * @since 7.0.0
 */
class Asset_Discovery {

	/**
	 * Admin assets.
	 *
	 * @var array
	 */
	private array $admin_assets = array();

	/**
	 * Block editor assets.
	 *
	 * @var array
	 */
	private array $block_editor_assets = array();

	/**
	 * Global assets.
	 *
	 * @var array
	 */
	private array $global_assets = array();

	/**
	 * Registered assets.
	 *
	 * @var array
	 */
	private array $registered_assets = array();

	/**
	 * File classifier instance.
	 *
	 * @var File_Classifier
	 */
	private File_Classifier $classifier;

	/**
	 * Constructor.
	 *
	 * @param File_Classifier|null $classifier Optional file classifier.
	 */
	public function __construct( ?File_Classifier $classifier = null ) {
		$this->classifier = $classifier ?? new File_Classifier();
	}

	/**
	 * Discover assets for a block.
	 *
	 * @param array $block_data  The block data.
	 * @param bool  $is_editor   Whether in editor mode.
	 *
	 * @return array The discovered assets.
	 */
	public function discover_for_block( array $block_data, bool $is_editor = false ): array {
		$file_dir     = dirname( $block_data['path'] );
		$files        = $block_data['files'] ?? array();
		$scoped_class = $block_data['scopedClass'] ?? '';
		$instance     = $block_data['instance'] ?? '';
		$name         = $block_data['name'] ?? '';

		$assets           = array();
		$processed_assets = array();

		// Filter to only CSS and JS files.
		$asset_files = array_filter(
			$files,
			fn( $e ) => $this->classifier->is_css_asset( $e ) || $this->classifier->is_js_asset( $e )
		);

		foreach ( $asset_files as $asset_filename ) {
			$asset_data = $this->process_asset_file(
				$file_dir,
				$asset_filename,
				$scoped_class,
				$instance,
				$name,
				$is_editor
			);

			if ( null === $asset_data ) {
				continue;
			}

			$assets[ $asset_data['id'] ] = $asset_data['asset'];

			if ( ! empty( $asset_data['processed'] ) ) {
				$processed_assets = array_merge( $processed_assets, $asset_data['processed'] );
			}
		}

		return array(
			'assets'    => $assets,
			'processed' => $processed_assets,
		);
	}

	/**
	 * Process a single asset file.
	 *
	 * @param string $file_dir      The file directory.
	 * @param string $asset_filename The asset filename.
	 * @param string $scoped_class  The scoped class.
	 * @param string $instance      The instance name.
	 * @param string $name          The block name.
	 * @param bool   $is_editor     Whether in editor mode.
	 *
	 * @return array|null The processed asset data or null.
	 */
	private function process_asset_file(
		string $file_dir,
		string $asset_filename,
		string $scoped_class,
		string $instance,
		string $name,
		bool $is_editor
	): ?array {
		$is_css     = $this->classifier->is_css_asset( $asset_filename );
		$asset_path = $file_dir . '/' . $asset_filename;
		$asset_url  = Files::get_relative_url( $asset_path );
		$asset_file = pathinfo( $asset_path );

		// Apply filter to check if asset should be enabled.
		if ( false === apply_filters(
			'blockstudio/assets/enable',
			true,
			array(
				'file' => $asset_file,
				'path' => $asset_path,
				'url'  => $asset_url,
				'type' => $is_css ? 'css' : 'js',
			)
		) ) {
			return null;
		}

		$processed_assets = array();

		// Process asset if not in editor mode.
		if ( ! $is_editor ) {
			$processed_asset = Assets::process( $asset_path, $scoped_class );

			if ( is_array( $processed_asset ) ) {
				$processed_assets = array_merge( $processed_assets, $processed_asset );
			} else {
				$processed_assets[] = $processed_asset;
			}
		}

		// Categorize by asset type.
		$this->categorize_asset( $asset_file, $asset_path, $asset_url, $is_editor );

		// Build asset ID.
		$id = strtolower( preg_replace( '/(?<!^)[A-Z]/', '-$0', $asset_filename ) );

		$handle    = Assets::get_id( $id, array( 'name' => $name ) );
		$is_inline = $this->classifier->is_inline_asset( $asset_filename );
		$is_editor_only = $this->classifier->is_editor_asset( $asset_filename );

		$asset = array(
			'type'     => $is_inline ? 'inline' : 'external',
			'path'     => $asset_path,
			'url'      => $asset_url,
			'editor'   => $is_editor_only,
			'instance' => $instance,
			'file'     => $asset_file,
		);

		// Register asset.
		if ( ! $is_editor ) {
			$this->register_asset( $handle, $asset_path, $asset_url, $is_css );
		}

		return array(
			'id'        => $id,
			'asset'     => $asset,
			'processed' => $processed_assets,
		);
	}

	/**
	 * Categorize an asset by its type.
	 *
	 * @param array  $asset_file The asset file info.
	 * @param string $asset_path The asset path.
	 * @param string $asset_url  The asset URL.
	 * @param bool   $is_editor  Whether in editor mode.
	 *
	 * @return void
	 */
	private function categorize_asset( array $asset_file, string $asset_path, string $asset_url, bool $is_editor ): void {
		if ( $is_editor ) {
			return;
		}

		$basename = $asset_file['basename'] ?? '';

		if ( $this->classifier->is_admin_asset( $basename ) ) {
			$this->admin_assets[ sanitize_title( $asset_path ) ] = array(
				'path' => $asset_path,
				'key'  => filemtime( $asset_path ),
			);
		}

		if ( $this->classifier->is_block_editor_asset( $basename ) ) {
			$this->block_editor_assets[ sanitize_title( $asset_path ) ] = array(
				'path' => $asset_path,
				'key'  => filemtime( $asset_path ),
			);
		}

		if ( $this->classifier->is_global_asset( $basename ) ) {
			$this->global_assets[ sanitize_title( $asset_path ) ] = $asset_url;
		}
	}

	/**
	 * Register an asset.
	 *
	 * @param string $handle     The asset handle.
	 * @param string $asset_path The asset path.
	 * @param string $asset_url  The asset URL.
	 * @param bool   $is_css     Whether the asset is CSS.
	 *
	 * @return void
	 */
	private function register_asset( string $handle, string $asset_path, string $asset_url, bool $is_css ): void {
		$type = $is_css ? 'style' : 'script';

		$this->registered_assets[ $type ][ $handle ] = array(
			'path'  => $asset_url,
			'mtime' => filemtime( $asset_path ),
		);
	}

	/**
	 * Get admin assets.
	 *
	 * @return array The admin assets.
	 */
	public function get_admin_assets(): array {
		return $this->admin_assets;
	}

	/**
	 * Get block editor assets.
	 *
	 * @return array The block editor assets.
	 */
	public function get_block_editor_assets(): array {
		return $this->block_editor_assets;
	}

	/**
	 * Get global assets.
	 *
	 * @return array The global assets.
	 */
	public function get_global_assets(): array {
		return $this->global_assets;
	}

	/**
	 * Get registered assets.
	 *
	 * @return array The registered assets.
	 */
	public function get_registered_assets(): array {
		return $this->registered_assets;
	}

	/**
	 * Cleanup processed assets in dist folder.
	 *
	 * @param string $dist_folder      The dist folder path.
	 * @param array  $processed_assets The processed assets to keep.
	 *
	 * @return array Empty folders that should be removed.
	 */
	public function cleanup_dist_folder( string $dist_folder, array $processed_assets ): array {
		$empty_folders = array();

		$all_processed_assets = Files::get_files_recursively_and_delete_empty_folders( $dist_folder );

		// Remove files that are no longer needed.
		foreach ( $all_processed_assets as $file_path ) {
			if ( ! in_array( $file_path, $processed_assets, true ) && file_exists( $file_path ) ) {
				unlink( $file_path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink
			}
		}

		// Remove empty directories.
		foreach ( $all_processed_assets as $file_path ) {
			$directory = dirname( $file_path );
			if ( false !== glob( $directory . '/*' ) && 0 !== count( glob( $directory . '/*' ) ) ) {
				continue;
			}

			if ( is_dir( $directory ) ) {
				rmdir( $directory ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir
			}
		}

		if ( Files::is_directory_empty( $dist_folder ) ) {
			$empty_folders[] = $dist_folder;
		}

		return $empty_folders;
	}

	/**
	 * Reset the discovery state.
	 *
	 * @return void
	 */
	public function reset(): void {
		$this->admin_assets        = array();
		$this->block_editor_assets = array();
		$this->global_assets       = array();
		$this->registered_assets   = array();
	}

	/**
	 * Merge assets from another discovery instance.
	 *
	 * @param Asset_Discovery $other The other instance.
	 *
	 * @return void
	 */
	public function merge( Asset_Discovery $other ): void {
		$this->admin_assets        = array_merge( $this->admin_assets, $other->get_admin_assets() );
		$this->block_editor_assets = array_merge( $this->block_editor_assets, $other->get_block_editor_assets() );
		$this->global_assets       = array_merge( $this->global_assets, $other->get_global_assets() );

		foreach ( $other->get_registered_assets() as $type => $assets ) {
			$this->registered_assets[ $type ] = array_merge(
				$this->registered_assets[ $type ] ?? array(),
				$assets
			);
		}
	}
}
