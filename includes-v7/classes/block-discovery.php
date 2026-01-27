<?php
/**
 * Block Discovery class.
 *
 * @package Blockstudio
 */

namespace Blockstudio;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Exception;

/**
 * Discovers blocks from filesystem paths.
 *
 * This class extracts block discovery logic from the Build class.
 *
 * @since 7.0.0
 */
class Block_Discovery {

	/**
	 * Discovered blocks.
	 *
	 * @var array
	 */
	private array $blocks = array();

	/**
	 * Discovered extensions.
	 *
	 * @var array
	 */
	private array $extensions = array();

	/**
	 * Discovered overrides.
	 *
	 * @var array
	 */
	private array $overrides = array();

	/**
	 * Discovered Blade templates.
	 *
	 * @var array
	 */
	private array $blade_templates = array();

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
	 * Discover blocks in a path.
	 *
	 * @param string $path         The path to scan.
	 * @param bool   $is_library   Whether this is a library path.
	 * @param bool   $is_editor    Whether in editor mode.
	 *
	 * @return array The discovered items.
	 */
	public function discover( string $path, bool $is_library = false, bool $is_editor = false ): array {
		$this->blocks          = array();
		$this->extensions      = array();
		$this->overrides       = array();
		$this->blade_templates = array();

		if ( ! is_dir( $path ) ) {
			return $this->get_results();
		}

		$path     = wp_normalize_path( $path );
		$instance = $this->get_instance_name( $path );

		$iterator = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator( $path )
		);

		foreach ( $iterator as $filename => $file ) {
			$filename  = wp_normalize_path( $filename );
			$file_path = wp_normalize_path( $file );

			$classification = $this->classifier->classify( $file_path );

			if ( null === $classification ) {
				continue;
			}

			$block_data = $this->build_block_data(
				$file_path,
				$filename,
				$path,
				$instance,
				$is_library,
				$classification
			);

			if ( null === $block_data ) {
				continue;
			}

			$this->categorize_discovery( $block_data, $classification, $instance );
		}

		return $this->get_results();
	}

	/**
	 * Build block data from a file.
	 *
	 * @param string $file_path      The file path.
	 * @param string $filename       The filename.
	 * @param string $base_path      The base path.
	 * @param string $instance       The instance name.
	 * @param bool   $is_library     Whether this is a library path.
	 * @param array  $classification The file classification.
	 *
	 * @return array|null The block data or null.
	 */
	private function build_block_data(
		string $file_path,
		string $filename,
		string $base_path,
		string $instance,
		bool $is_library,
		array $classification
	): ?array {
		$file_dir  = dirname( $file_path );
		$path_info = pathinfo( $file_path );

		// Skip hidden files.
		if ( str_starts_with( $path_info['basename'], '.' ) ) {
			return null;
		}

		$block_json = $this->get_block_json( $file_path, $classification );
		if ( null === $block_json && ! $classification['is_init'] && ! $classification['is_directory'] ) {
			return null;
		}

		$name = $this->determine_block_name( $block_json, $file_path, $classification );

		$block_json_path = $this->get_block_json_path( $file_path, $classification );
		$level           = $this->calculate_level( $base_path, $filename );
		$structure       = $this->calculate_structure( $base_path, $block_json_path );

		$files       = $this->get_directory_files( $file_dir );
		$files_paths = array_map(
			fn( $item ) => $file_dir . '/' . $item,
			$files
		);
		$folders = array_values(
			array_map(
				'basename',
				array_filter( glob( $file_dir . '/*' ), 'is_dir' )
			)
		);

		$structure_array = explode(
			'/',
			str_replace(
				array(
					'/' . $path_info['basename'],
					$path_info['basename'],
				),
				'',
				$level
			)
		);

		return array(
			'block_json'       => $block_json,
			'classification'   => $classification,
			'directory'        => $classification['is_directory'],
			'example'          => $block_json['example'] ?? false,
			'extend'           => $classification['is_extend'],
			'file'             => pathinfo( $block_json_path ),
			'files'            => $files,
			'filesPaths'       => $files_paths,
			'folders'          => $folders,
			'init'             => $classification['is_init'],
			'instance'         => $instance,
			'instancePath'     => $base_path,
			'level'            => substr_count( $level, '/' ),
			'library'          => $is_library,
			'name'             => $name,
			'path'             => $block_json_path,
			'previewAssets'    => $block_json['blockstudio']['editor']['assets'] ?? array(),
			'scopedClass'      => 'bs-' . md5( $name ),
			'structure'        => $structure,
			'structureArray'   => $structure_array,
			'twig'             => $classification['is_twig'],
		);
	}

	/**
	 * Categorize a discovered item.
	 *
	 * @param array  $block_data     The block data.
	 * @param array  $classification The classification.
	 * @param string $instance       The instance name.
	 *
	 * @return void
	 */
	private function categorize_discovery( array $block_data, array $classification, string $instance ): void {
		if ( $classification['is_blade'] ) {
			$this->blade_templates[ $instance ][ $block_data['name'] ] = $block_data;
			return;
		}

		if ( $classification['is_override'] ) {
			$this->overrides[ $block_data['name'] ] = $block_data;
			return;
		}

		if ( $classification['is_extend'] ) {
			$this->extensions[ $block_data['name'] ] = $block_data;
			return;
		}

		if ( $classification['is_block'] ) {
			$this->blocks[ $block_data['name'] ] = $block_data;
		}
	}

	/**
	 * Get the block.json content.
	 *
	 * @param string $file_path      The file path.
	 * @param array  $classification The classification.
	 *
	 * @return array|null The block.json data or null.
	 */
	private function get_block_json( string $file_path, array $classification ): ?array {
		if ( is_dir( $file_path ) ) {
			return null;
		}

		$json_path = str_ends_with( $file_path, 'block.json' )
			? $file_path
			: str_replace(
				array( 'index.blade.php', 'index.php', 'index.twig' ),
				'block.json',
				$file_path
			);

		if ( ! file_exists( $json_path ) ) {
			return null;
		}

		$contents = file_get_contents( $json_path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents

		try {
			return json_decode( $contents, true );
		} catch ( Exception $e ) {
			return null;
		}
	}

	/**
	 * Determine the block name.
	 *
	 * @param array|null $block_json     The block.json data.
	 * @param string     $file_path      The file path.
	 * @param array      $classification The classification.
	 *
	 * @return string The block name.
	 */
	private function determine_block_name( ?array $block_json, string $file_path, array $classification ): string {
		if ( $classification['is_init'] ) {
			return 'init-' . str_replace( '/', '-', $file_path );
		}

		if ( $classification['is_override'] && isset( $block_json['name'] ) ) {
			return $block_json['name'] . '-override';
		}

		return $block_json['name'] ?? $file_path;
	}

	/**
	 * Get the block.json path from a file path.
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
	 * Calculate the level string.
	 *
	 * @param string $base_path The base path.
	 * @param string $filename  The filename.
	 *
	 * @return string The level string.
	 */
	private function calculate_level( string $base_path, string $filename ): string {
		$level_explode = explode( Files::get_root_folder(), $base_path );
		$level         = explode(
			$level_explode[ count( $level_explode ) - 1 ],
			$filename
		)[1] ?? '';

		return $level;
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
		$structure_explode = explode(
			Files::get_root_folder() . '/',
			$base_path
		);

		return ltrim(
			explode(
				$structure_explode[ count( $structure_explode ) - 1 ],
				$block_json_path
			)[1] ?? '',
			'/'
		);
	}

	/**
	 * Get files in a directory.
	 *
	 * @param string $directory The directory path.
	 *
	 * @return array The files.
	 */
	private function get_directory_files( string $directory ): array {
		if ( ! is_dir( $directory ) ) {
			return array();
		}

		return array_values(
			array_filter(
				scandir( $directory ),
				fn( $item ) => ! is_dir( $directory . '/' . $item ) && '.' !== $item[0]
			)
		);
	}

	/**
	 * Get instance name from path.
	 *
	 * @param string $path The path.
	 *
	 * @return string The instance name.
	 */
	private function get_instance_name( string $path ): string {
		return wp_normalize_path(
			trim( explode( Files::get_root_folder(), $path )[1] ?? '', '/\\' )
		);
	}

	/**
	 * Get the discovery results.
	 *
	 * @return array The results.
	 */
	private function get_results(): array {
		return array(
			'blocks'          => $this->blocks,
			'extensions'      => $this->extensions,
			'overrides'       => $this->overrides,
			'blade_templates' => $this->blade_templates,
		);
	}

	/**
	 * Get discovered blocks.
	 *
	 * @return array The blocks.
	 */
	public function get_blocks(): array {
		return $this->blocks;
	}

	/**
	 * Get discovered extensions.
	 *
	 * @return array The extensions.
	 */
	public function get_extensions(): array {
		return $this->extensions;
	}

	/**
	 * Get discovered overrides.
	 *
	 * @return array The overrides.
	 */
	public function get_overrides(): array {
		return $this->overrides;
	}

	/**
	 * Get discovered Blade templates.
	 *
	 * @return array The Blade templates.
	 */
	public function get_blade_templates(): array {
		return $this->blade_templates;
	}
}
