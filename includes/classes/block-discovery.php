<?php
/**
 * Block Discovery class.
 *
 * @package Blockstudio
 */

namespace Blockstudio;

use Exception;

/**
 * Discovers Blockstudio blocks by scanning filesystem directories.
 *
 * This class handles Phase 1 of Build::init() - recursively scanning directories
 * to find block.json files, classify them, and build metadata for each block.
 *
 * Block Types Discovered:
 * - Regular blocks: Have block.json with 'blockstudio' key and a render template
 * - Override blocks: Have 'blockstudio.override' set to true in block.json
 * - Extension blocks: Have 'blockstudio.extend' set to true (extend core blocks)
 * - Init files: PHP files prefixed with 'init' (executed during build)
 * - Blade templates: Files ending in .blade.php (for Blade templating)
 *
 * Discovery Modes:
 * - Normal mode: Only discovers block.json files and builds registration data
 * - Editor mode: Discovers ALL files for the code editor feature
 *
 * Usage:
 *   $discovery = new Block_Discovery();
 *   $results = $discovery->discover( '/path/to/blocks', 'my-instance' );
 *   // $results['store'] contains all discovered items
 *   // $results['registerable'] contains items that need WP registration
 *
 * @since 7.0.0
 */
class Block_Discovery {

	/**
	 * All discovered items indexed by block name (or file path in editor mode).
	 *
	 * Each entry contains block metadata: path, files, assets, scopedClass, etc.
	 * In editor mode, this includes every file (not just blocks).
	 *
	 * @var array<string, array>
	 */
	private array $store = array();

	/**
	 * Items that require WordPress block registration.
	 *
	 * Only populated in non-editor mode. Contains blocks, overrides, and
	 * extensions with their classification and block.json data needed
	 * for creating WP_Block_Type instances.
	 *
	 * @var array<string, array{data: array, block_json: array, classification: array, contents: string}>
	 */
	private array $registerable = array();

	/**
	 * Discovered Blade templates indexed by instance then template name.
	 *
	 * Structure: ['instance-name' => ['template-name' => 'relative.path']]
	 * Blade templates use .blade.php extension and are registered separately.
	 *
	 * @var array<string, array<string, string>>
	 */
	private array $blade_templates = array();

	/**
	 * Parsed block.json data indexed by block name.
	 *
	 * Cached to avoid re-parsing the same block.json multiple times
	 * when processing related files in the same directory.
	 *
	 * @var array<string, array>
	 */
	private array $block_json_data = array();

	/**
	 * File contents cache to avoid repeated disk reads.
	 *
	 * Key is absolute file path, value is file contents.
	 * Cleared between discover() calls.
	 *
	 * @var array<string, string>
	 */
	private array $contents_cache = array();

	/**
	 * Override blocks tracked separately for editor mode asset merging.
	 *
	 * When in editor mode, override assets need to be merged with their
	 * target block's assets. This tracks overrides regardless of mode
	 * so the merging can happen correctly.
	 *
	 * @var array<string, array{name: string, data: array, classification: array}>
	 */
	private array $overrides = array();

	/**
	 * Active logical discovery source.
	 *
	 * @var Discovery_Source|null
	 */
	private ?Discovery_Source $source = null;

	/**
	 * Discover blocks in a directory path.
	 *
	 * Recursively scans the given path for Blockstudio blocks, classifying
	 * each file and building metadata. Results vary based on editor mode:
	 *
	 * Normal mode: Only processes block.json files and their associated
	 * render templates. Returns blocks ready for WordPress registration.
	 *
	 * Editor mode: Processes ALL files for the code editor feature.
	 * Every file gets an entry in the store, keyed by file path.
	 *
	 * @param string|Discovery_Source $base_path Absolute path or logical discovery source.
	 * @param string                  $instance Instance identifier (e.g., 'themes/mytheme/blockstudio').
	 * @param bool                    $is_editor Whether running in editor mode (discovers all files).
	 *
	 * @return array{
	 *     store: array<string, array>,
	 *     registerable: array<string, array>,
	 *     blade_templates: array<string, array>,
	 *     block_json_data: array<string, array>,
	 *     overrides: array<string, array>
	 * } Discovery results for Build::init() to process.
	 */
	public function discover( string|Discovery_Source $base_path, string $instance, bool $is_editor = false ): array {
		$this->store           = array();
		$this->registerable    = array();
		$this->blade_templates = array();
		$this->block_json_data = array();
		$this->contents_cache  = array();
		$this->overrides       = array();
		$this->source          = is_string( $base_path )
			? Discovery_Sources::for_path( 'blocks', $base_path )
			: $base_path;

		$base_path = wp_normalize_path( $this->source->root() );

		foreach ( $this->source->entries() as $entry ) {
			$logical_path = $entry->logical_path();
			$file_path    = wp_normalize_path( $entry->physical_path() );

			$this->process_file(
				$file_path,
				$logical_path,
				$base_path,
				$instance,
				$is_editor,
				$entry
			);
		}

		return $this->get_results();
	}

	/**
	 * Process a single file.
	 *
	 * @param string          $file_path  The file path.
	 * @param string          $logical_path Logical path in the active source.
	 * @param string          $base_path  The base path.
	 * @param string          $instance   The instance name.
	 * @param bool            $is_editor  Whether in editor mode.
	 * @param Discovery_Entry $entry Source entry.
	 *
	 * @return void
	 */
	private function process_file(
		string $file_path,
		string $logical_path,
		string $base_path,
		string $instance,
		bool $is_editor,
		Discovery_Entry $entry
	): void {
		$file_dir    = dirname( $file_path );
		$logical_dir = dirname( $logical_path );
		$logical_dir = '.' === $logical_dir ? '' : $logical_dir;
		$path_info   = pathinfo( $file_path );
		$contents    = $this->get_file_contents( $file_path );

		// Classify the file.
		$classification = $this->classify_file( $file_path, $logical_path, $path_info, $contents );

		// Skip hidden files (unless editor mode and basename is '.').
		if (
			str_starts_with( $path_info['basename'], '.' ) &&
			( ! $is_editor || '.' !== $path_info['basename'] )
		) {
			return;
		}

		// Check if this file should be processed.
		if ( ! $this->should_process( $classification, $is_editor ) ) {
			return;
		}

		// Read block.json if needed.
		$block_json = $this->read_block_json( $file_path, $logical_path );

		// Determine paths.
		$block_json_path = $this->get_block_json_path( $file_path, $classification );

		// Determine name.
		$name = $this->determine_name( $block_json, $file_path, $classification, $is_editor );

		// Handle blade templates separately.
		if ( $classification['is_blade'] ) {
			$this->register_blade_template( $logical_path, $instance, $name );
			return;
		}

		// Handle array names.
		if ( is_array( $name ) ) {
			$name = wp_json_encode( $name );
		}

		// Get name from block.json in editor mode.
		$name_file      = false;
		$manifest_entry = $this->resolve_in_directory( $logical_dir, 'block.json' );
		if ( $is_editor && $manifest_entry ) {
			$dir_block_json = Utils::read_json_file( $manifest_entry->physical_path() ) ?? array();
			$name_file      = $dir_block_json['name'] ?? false;
			if ( ! $name_file ) {
				$block_json = array( 'name' => 'test/test' );
			}
		}

		// Build level and structure info.
		$level                     = '/' . $logical_path;
		$directory_entries         = $this->source?->children( $logical_dir ) ?? array();
		$block_arr_files           = array_map( static fn( Discovery_Entry $item ): string => basename( $item->logical_path() ), $directory_entries );
		$block_arr_files_paths     = array_map( static fn( Discovery_Entry $item ): string => $item->physical_path(), $directory_entries );
		$block_arr_files_map       = array_combine( $block_arr_files, $block_arr_files_paths );
		$block_arr_provenance      = array_combine(
			$block_arr_files,
			array_map( static fn( Discovery_Entry $item ): array => $item->provenance(), $directory_entries )
		);
		$block_arr_folders         = $this->get_directory_folders( $logical_dir );
		$block_arr_structure_array = $this->calculate_structure_array( $level, $path_info );
		$block_arr_structure       = $logical_path;
		$render_template           = $this->find_render_template( $logical_dir );

		// Handle init files.
		if ( $classification['is_init'] ) {
			$block_json = array(
				'name' => 'init-' . str_replace( '/', '-', $file_path ),
			);
		}

		// Build the data array (exact same structure as Build::init).
		$data = array(
			'directory'       => $classification['is_dir'],
			'example'         => $block_json['example'] ?? false,
			'extend'          => $classification['is_extend'],
			'file'            => pathinfo( $block_json_path ),
			'files'           => $block_arr_files,
			'filesMap'        => false === $block_arr_files_map ? array() : $block_arr_files_map,
			'filesPaths'      => $block_arr_files_paths,
			'filesProvenance' => false === $block_arr_provenance ? array() : $block_arr_provenance,
			'folders'         => $block_arr_folders,
			'init'            => $classification['is_init'],
			'instance'        => $instance,
			'instancePath'    => $base_path,
			'logicalPath'     => $logical_path,
			'level'           => substr_count( $level, '/' ),
			'name'            => false !== $name_file ? $name_file : $name,
			'path'            => $block_json_path,
			'previewAssets'   => $block_json['blockstudio']['editor']['assets'] ?? array(),
			'renderTemplate'  => $render_template,
			'scopedClass'     => 'bs-' . md5( $name ),
			'structure'       => $block_arr_structure,
			'structureArray'  => $block_arr_structure_array,
			'sourceId'        => $this->source?->id() ?? '',
			'provenance'      => $entry->provenance(),
			'twig'            => $classification['is_twig'],
		);

		// Add nameAlt if name equals path.
		if ( $data['name'] === $data['path'] ) {
			$data['nameAlt'] = Block::id( $data, $data );
		}

		// Add value in editor mode.
		if ( $is_editor && '.' !== $path_info['basename'] ) {
			$data['value'] = $this->get_file_contents( $file_path );
		}

		// Store the data using $name as the key (original behavior).
		// Note: $name_file is only used for the data's 'name' field, not the store key.
		$this->store[ $name ] = $data;

		// Track overrides (needed for both editor and non-editor modes).
		if ( $classification['is_override'] ) {
			$override_key                     = false !== $name_file ? $name_file : $name;
			$this->overrides[ $override_key ] = array(
				'name'           => $name,
				'data'           => $data,
				'classification' => $classification,
			);
		}

		// Track items that need registration.
		if ( ( $classification['is_block'] || $classification['is_override'] || $classification['is_extend'] || $classification['is_native'] ) && ! $is_editor ) {
			$this->registerable[ $name ] = array(
				'data'           => $data,
				'block_json'     => $block_json,
				'classification' => $classification,
				'contents'       => $contents,
			);
		}

		// Store block_json for later use.
		$this->block_json_data[ $name ] = $block_json;
	}

	/**
	 * Classify a file.
	 *
	 * @param string $file_path The file path.
	 * @param string $logical_path Logical path in the active source.
	 * @param array  $path_info The pathinfo array.
	 * @param string $contents  The file contents.
	 *
	 * @return array The classification.
	 */
	private function classify_file( string $file_path, string $logical_path, array $path_info, string $contents ): array {
		$json_data      = $this->decode_json( $contents );
		$is_block_json  = 'block.json' === $path_info['basename'];
		$is_blockstudio = $is_block_json && isset( $json_data['blockstudio'] );
		$is_native      = $is_block_json && ! $is_blockstudio && isset( $json_data['name'] );

		$is_extend    = $this->check_blockstudio_flag( $contents, 'extend' );
		$is_override  = $is_blockstudio && $this->check_blockstudio_flag( $contents, 'override' );
		$is_component = $is_blockstudio && $this->check_blockstudio_flag( $contents, 'component' );
		$logical_dir  = dirname( $logical_path );
		$logical_dir  = '.' === $logical_dir ? '' : $logical_dir;
		$is_block     = $is_blockstudio && $this->find_render_template( $logical_dir ) && ! $is_extend;
		$is_init      = 'php' === ( $path_info['extension'] ?? '' )
			&& str_starts_with( $path_info['basename'], 'init' );
		$is_dir       = is_dir( $file_path )
			&& ! file_exists( $file_path . '/block.json' )
			&& ! Files::get_render_template( $file_path );
		$is_twig      = str_ends_with( $file_path, '.twig' );
		$is_php       = ! $is_twig;
		$is_blade     = str_ends_with( $file_path, '.blade.php' );

		return array(
			'is_blockstudio' => $is_blockstudio,
			'is_block'       => $is_block,
			'is_component'   => $is_component,
			'is_extend'      => $is_extend,
			'is_native'      => $is_native,
			'is_override'    => $is_override,
			'is_init'        => $is_init,
			'is_dir'         => $is_dir,
			'is_twig'        => $is_twig,
			'is_php'         => $is_php,
			'is_blade'       => $is_blade,
		);
	}

	/**
	 * Check if a file should be processed.
	 *
	 * @param array $classification The classification.
	 * @param bool  $is_editor      Whether in editor mode.
	 *
	 * @return bool Whether to process.
	 */
	private function should_process( array $classification, bool $is_editor ): bool {
		return $classification['is_block']
			|| $classification['is_blade']
			|| $classification['is_init']
			|| $classification['is_dir']
			|| $classification['is_native']
			|| $classification['is_override']
			|| $classification['is_extend']
			|| $is_editor;
	}

	/**
	 * Check for a blockstudio flag in contents.
	 *
	 * @param string $contents The file contents.
	 * @param string $flag     The flag to check.
	 *
	 * @return bool Whether the flag is set.
	 */
	private function check_blockstudio_flag( string $contents, string $flag ): bool {
		$data = $this->decode_json( $contents );
		return isset( $data['blockstudio'][ $flag ] ) && $data['blockstudio'][ $flag ];
	}

	/**
	 * Decode JSON safely.
	 *
	 * @param string $contents The JSON string.
	 *
	 * @return array The decoded array or empty array.
	 */
	private function decode_json( string $contents ): array {
		try {
			$result = json_decode( $contents, true );
			return is_array( $result ) ? $result : array();
		} catch ( Exception $e ) {
			return array();
		}
	}

	/**
	 * Read block.json for a file.
	 *
	 * @param string $file_path The file path.
	 * @param string $logical_path Logical path in the active source.
	 *
	 * @return array The block.json data.
	 */
	private function read_block_json( string $file_path, string $logical_path ): array {
		if ( is_dir( $file_path ) ) {
			return array();
		}

		$logical_dir = dirname( $logical_path );
		$logical_dir = '.' === $logical_dir ? '' : $logical_dir;
		$manifest    = $this->resolve_in_directory( $logical_dir, 'block.json' );
		$json_path   = $manifest?->physical_path() ?? $file_path;

		return Utils::read_json_file( $json_path ) ?? array();
	}

	/**
	 * Get the block.json path for a file.
	 *
	 * @param string $file_path      The file path.
	 * @param array  $classification The classification.
	 *
	 * @return string The block.json path.
	 */
	private function get_block_json_path( string $file_path, array $classification ): string {
		if ( str_ends_with( $file_path, 'block.json' ) ) {
			return $file_path;
		}

		$replacement = 'index.php';
		if ( $classification['is_blade'] ) {
			$replacement = 'index.blade.php';
		} elseif ( $classification['is_twig'] ) {
			$replacement = 'index.twig';
		}

		return str_replace( 'block.json', $replacement, $file_path );
	}

	/**
	 * Determine the block name.
	 *
	 * @param array  $block_json     The block.json data.
	 * @param string $file_path      The file path.
	 * @param array  $classification The classification.
	 * @param bool   $is_editor      Whether in editor mode.
	 *
	 * @return string|array The block name.
	 */
	private function determine_name( array $block_json, string $file_path, array $classification, bool $is_editor ) {
		if ( $is_editor ) {
			return $file_path;
		}

		if ( $classification['is_override'] ) {
			return ( $block_json['name'] ?? '' ) . '-override';
		}

		return $block_json['name'] ?? $file_path;
	}

	/**
	 * Register a blade template.
	 *
	 * @param string $logical_path Logical template path.
	 * @param string $instance  The instance name.
	 * @param string $name      The template name.
	 *
	 * @return void
	 */
	private function register_blade_template( string $logical_path, string $instance, string $name ): void {
		$relative_path = str_replace( '.blade.php', '', $logical_path );
		$relative_path = str_replace( DIRECTORY_SEPARATOR, '.', $relative_path );

		$this->blade_templates[ $instance ][ $name ] = ltrim( $relative_path, '.' );
	}

	/**
	 * Calculate the level string.
	 *
	 * @param string $base_path The base path.
	 * @param string $filename  The filename.
	 *
	 * @return string The level string.
	 */
	private function calculate_level( string $base_path, string $filename ): string {
		$level_explode = explode( Files::get_root_folder(), $base_path );
		$level_parts   = explode( $level_explode[ count( $level_explode ) - 1 ], $filename );
		return $level_parts[1] ?? '';
	}

	/**
	 * Calculate the structure array.
	 *
	 * @param string $level     The level string.
	 * @param array  $path_info The pathinfo array.
	 *
	 * @return array The structure array.
	 */
	private function calculate_structure_array( string $level, array $path_info ): array {
		return explode(
			'/',
			str_replace(
				array( '/' . $path_info['basename'], $path_info['basename'] ),
				'',
				$level
			)
		);
	}

	/**
	 * Calculate the structure string.
	 *
	 * @param string $base_path       The base path.
	 * @param string $block_json_path The block.json path.
	 *
	 * @return string The structure string.
	 */
	private function calculate_structure( string $base_path, string $block_json_path ): string {
		$structure_explode = explode( Files::get_root_folder() . '/', $base_path );
		$structure_parts   = explode(
			$structure_explode[ count( $structure_explode ) - 1 ],
			$block_json_path
		);
		return ltrim( $structure_parts[1] ?? '', '/' );
	}

	/**
	 * Get logical folders in a directory.
	 *
	 * @param string $directory Logical directory path.
	 *
	 * @return array The files.
	 */
	private function get_directory_folders( string $directory ): array {
		$prefix  = '' === $directory ? '' : $directory . '/';
		$folders = array_fill_keys(
			array_map(
				'basename',
				$this->source?->directories( $directory, false ) ?? array()
			),
			true
		);

		foreach ( $this->source?->entries( $directory ) ?? array() as $entry ) {
			$relative = substr( $entry->logical_path(), strlen( $prefix ) );
			$parts    = explode( '/', $relative );

			if ( count( $parts ) > 1 && '' !== $parts[0] ) {
				$folders[ $parts[0] ] = true;
			}
		}

		$folders = array_keys( $folders );
		sort( $folders, SORT_STRING );

		return $folders;
	}

	/**
	 * Resolve a logical sibling in a directory.
	 *
	 * @param string $directory Logical directory.
	 * @param string $filename File name.
	 *
	 * @return Discovery_Entry|null Resolved entry.
	 */
	private function resolve_in_directory( string $directory, string $filename ): ?Discovery_Entry {
		$logical_path = '' === $directory ? $filename : $directory . '/' . $filename;

		return $this->source?->resolve( $logical_path );
	}

	/**
	 * Find a render template in a logical directory.
	 *
	 * @param string $directory Logical directory.
	 *
	 * @return string|null Physical template path.
	 */
	private function find_render_template( string $directory ): ?string {
		foreach ( array( 'index.php', 'index.blade.php', 'index.twig' ) as $filename ) {
			$entry = $this->resolve_in_directory( $directory, $filename );

			if ( $entry ) {
				return $entry->physical_path();
			}
		}

		return null;
	}

	/**
	 * Get file contents with caching.
	 *
	 * @param string $file_path The file path.
	 *
	 * @return string The file contents.
	 */
	private function get_file_contents( string $file_path ): string {
		if ( isset( $this->contents_cache[ $file_path ] ) ) {
			return $this->contents_cache[ $file_path ];
		}

		$contents = is_file( $file_path )
			? file_get_contents( $file_path ) // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
			: '{}';

		$this->contents_cache[ $file_path ] = $contents;
		return $contents;
	}

	/**
	 * Get the discovery results.
	 *
	 * @return array The results.
	 */
	private function get_results(): array {
		return array(
			'store'           => $this->store,
			'registerable'    => $this->registerable,
			'blade_templates' => $this->blade_templates,
			'block_json_data' => $this->block_json_data,
			'overrides'       => $this->overrides,
		);
	}

	/**
	 * Get discovered store.
	 *
	 * @return array The store.
	 */
	public function get_store(): array {
		return $this->store;
	}

	/**
	 * Get registerable items.
	 *
	 * @return array The registerable items.
	 */
	public function get_registerable(): array {
		return $this->registerable;
	}

	/**
	 * Get blade templates.
	 *
	 * @return array The blade templates.
	 */
	public function get_blade_templates(): array {
		return $this->blade_templates;
	}

	/**
	 * Get block JSON data.
	 *
	 * @return array The block JSON data.
	 */
	public function get_block_json_data(): array {
		return $this->block_json_data;
	}

	/**
	 * Get overrides.
	 *
	 * @return array The overrides.
	 */
	public function get_overrides(): array {
		return $this->overrides;
	}
}
