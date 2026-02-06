<?php
/**
 * File Classifier class.
 *
 * @package Blockstudio
 */

namespace Blockstudio;

use Exception;

/**
 * Classifies files in Blockstudio block directories.
 *
 * This class determines what type of file is being processed based on:
 * - File extension (.php, .twig, .blade.php, .css, .scss, .js)
 * - File naming conventions (admin-, block-editor-, global-, -inline, -editor)
 * - JSON content for block.json files (blockstudio key, extend/override flags)
 *
 * File Classification Types:
 * - Block: Has block.json with 'blockstudio' key and a render template
 * - Override: Has 'blockstudio.override: true' - modifies existing blocks
 * - Extension: Has 'blockstudio.extend: true' - adds to core blocks
 * - Init file: PHP file starting with 'init' - executed during build
 * - Directory: Empty directory (no block.json or template)
 * - Blade: Template using Laravel Blade syntax (.blade.php)
 * - Twig: Template using Twig syntax (.twig)
 *
 * Asset Classification by Naming Convention:
 * - admin-*.css/js: Only loaded in WordPress admin
 * - block-editor-*.css/js: Only loaded in Gutenberg editor
 * - global-*.css/js: Loaded everywhere (frontend + admin)
 * - *.editor.css/js or *-editor.css/js: Only loaded in editor, not frontend
 * - *.inline.css/js or *-inline.css/js: Injected inline rather than enqueued
 * - *.scoped.css/scss or *-scoped.css/scss: CSS scoped to block's wrapper element
 * - *.view.js or *-view.js: Only loaded on frontend, not editor
 *
 * @since 7.0.0
 */
class File_Classifier {

	/**
	 * Classify a file.
	 *
	 * @param string $file_path The file path.
	 *
	 * @return array|null Classification data or null if not a Blockstudio file.
	 */
	public function classify( string $file_path ): ?array {
		$path_info = pathinfo( $file_path );
		$basename  = $path_info['basename'] ?? '';
		$extension = $path_info['extension'] ?? '';

		// Skip hidden files.
		if ( str_starts_with( $basename, '.' ) ) {
			return null;
		}

		$contents = $this->get_file_contents( $file_path );

		$is_blockstudio = $this->is_blockstudio_file( $file_path, $contents );
		$is_extend      = $this->check_blockstudio_flag( $contents, 'extend' );
		$is_override    = $this->check_blockstudio_flag( $contents, 'override' );
		$is_init        = $this->is_init_file( $path_info );
		$is_directory   = $this->is_block_directory( $file_path );
		$is_blade       = str_ends_with( $file_path, '.blade.php' );
		$is_twig        = str_ends_with( $file_path, '.twig' );
		$is_block       = $is_blockstudio
			&& Files::get_render_template( $file_path )
			&& ! $is_extend;

		// Must be a recognizable type to classify.
		if ( ! $is_block && ! $is_blade && ! $is_init && ! $is_directory && ! $is_override && ! $is_extend ) {
			return null;
		}

		return array(
			'is_blockstudio' => $is_blockstudio,
			'is_block'       => $is_block,
			'is_extend'      => $is_extend,
			'is_override'    => $is_override,
			'is_init'        => $is_init,
			'is_directory'   => $is_directory,
			'is_blade'       => $is_blade,
			'is_twig'        => $is_twig,
			'is_php'         => ! $is_twig,
			'file_type'      => $this->determine_file_type( $extension ),
		);
	}

	/**
	 * Check if a file is a Blockstudio block.json file.
	 *
	 * @param string $file_path The file path.
	 * @param string $contents  The file contents.
	 *
	 * @return bool Whether the file is a Blockstudio file.
	 */
	private function is_blockstudio_file( string $file_path, string $contents ): bool {
		if ( 'block.json' !== pathinfo( $file_path )['basename'] ) {
			return false;
		}

		try {
			$data = json_decode( $contents, true );
			return isset( $data['blockstudio'] );
		} catch ( Exception $e ) {
			return false;
		}
	}

	/**
	 * Check for a Blockstudio flag in block.json.
	 *
	 * @param string $contents The file contents.
	 * @param string $flag     The flag to check.
	 *
	 * @return bool Whether the flag is set.
	 */
	private function check_blockstudio_flag( string $contents, string $flag ): bool {
		try {
			$data = json_decode( $contents, true );
			return isset( $data['blockstudio'][ $flag ] ) && $data['blockstudio'][ $flag ];
		} catch ( Exception $e ) {
			return false;
		}
	}

	/**
	 * Check if a file is an init file.
	 *
	 * @param array $path_info The pathinfo array.
	 *
	 * @return bool Whether the file is an init file.
	 */
	private function is_init_file( array $path_info ): bool {
		return 'php' === ( $path_info['extension'] ?? '' )
			&& str_starts_with( $path_info['basename'] ?? '', 'init' );
	}

	/**
	 * Check if a path is a block directory.
	 *
	 * @param string $file_path The file path.
	 *
	 * @return bool Whether the path is a block directory.
	 */
	private function is_block_directory( string $file_path ): bool {
		return is_dir( $file_path )
			&& ! file_exists( $file_path . '/block.json' )
			&& ! Files::get_render_template( $file_path );
	}

	/**
	 * Get file contents safely.
	 *
	 * @param string $file_path The file path.
	 *
	 * @return string The file contents or empty string.
	 */
	private function get_file_contents( string $file_path ): string {
		if ( ! is_file( $file_path ) ) {
			return '{}';
		}

		$contents = file_get_contents( $file_path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		return false !== $contents ? $contents : '{}';
	}

	/**
	 * Determine the file type from extension.
	 *
	 * @param string $extension The file extension.
	 *
	 * @return string The file type.
	 */
	private function determine_file_type( string $extension ): string {
		$type_map = array(
			'php'  => 'php',
			'twig' => 'twig',
			'json' => 'json',
			'css'  => 'css',
			'scss' => 'scss',
			'js'   => 'js',
			'ts'   => 'typescript',
			'jsx'  => 'jsx',
			'tsx'  => 'tsx',
		);

		return $type_map[ $extension ] ?? 'unknown';
	}

	/**
	 * Get the asset type from a file.
	 *
	 * @param string $file_path The file path.
	 *
	 * @return string|null The asset type or null if not an asset.
	 */
	public function get_asset_type( string $file_path ): ?string {
		if ( str_ends_with( $file_path, '.css' ) || str_ends_with( $file_path, '.scss' ) ) {
			return 'css';
		}

		if ( str_ends_with( $file_path, '.js' ) ) {
			return 'js';
		}

		return null;
	}

	/**
	 * Get the block type from a render template file.
	 *
	 * @param string $file_path The file path.
	 *
	 * @return string The block type (php, twig, blade).
	 */
	public function get_block_type( string $file_path ): string {
		if ( str_ends_with( $file_path, '.blade.php' ) ) {
			return 'blade';
		}

		if ( str_ends_with( $file_path, '.twig' ) ) {
			return 'twig';
		}

		return 'php';
	}

	/**
	 * Check if a file is a CSS asset.
	 *
	 * @param string $filename The filename.
	 *
	 * @return bool Whether the file is a CSS asset.
	 */
	public function is_css_asset( string $filename ): bool {
		return str_ends_with( $filename, '.css' ) || str_ends_with( $filename, '.scss' );
	}

	/**
	 * Check if a file is a JS asset.
	 *
	 * @param string $filename The filename.
	 *
	 * @return bool Whether the file is a JS asset.
	 */
	public function is_js_asset( string $filename ): bool {
		return str_ends_with( $filename, '.js' );
	}

	/**
	 * Check if a file is an admin asset.
	 *
	 * @param string $filename The filename.
	 *
	 * @return bool Whether the file is an admin asset.
	 */
	public function is_admin_asset( string $filename ): bool {
		return str_starts_with( basename( $filename ), 'admin' );
	}

	/**
	 * Check if a file is a block editor asset.
	 *
	 * @param string $filename The filename.
	 *
	 * @return bool Whether the file is a block editor asset.
	 */
	public function is_block_editor_asset( string $filename ): bool {
		return str_starts_with( basename( $filename ), 'block-editor' );
	}

	/**
	 * Check if a file is a global asset.
	 *
	 * @param string $filename The filename.
	 *
	 * @return bool Whether the file is a global asset.
	 */
	public function is_global_asset( string $filename ): bool {
		return str_starts_with( basename( $filename ), 'global' );
	}

	/**
	 * Check if a file is an inline asset.
	 *
	 * @param string $filename The filename.
	 *
	 * @return bool Whether the file is an inline asset.
	 */
	public function is_inline_asset( string $filename ): bool {
		return str_ends_with( $filename, '.inline.css' )
			|| str_ends_with( $filename, '.inline.scss' )
			|| str_ends_with( $filename, '.inline.js' )
			|| str_ends_with( $filename, '.scoped.css' )
			|| str_ends_with( $filename, '.scoped.scss' )
			|| str_ends_with( $filename, '-inline.css' )
			|| str_ends_with( $filename, '-inline.scss' )
			|| str_ends_with( $filename, '-inline.js' )
			|| str_ends_with( $filename, '-scoped.css' )
			|| str_ends_with( $filename, '-scoped.scss' );
	}

	/**
	 * Check if a file is an editor-only asset.
	 *
	 * @param string $filename The filename.
	 *
	 * @return bool Whether the file is an editor-only asset.
	 */
	public function is_editor_asset( string $filename ): bool {
		return str_ends_with( $filename, '.editor.css' )
			|| str_ends_with( $filename, '.editor.scss' )
			|| str_ends_with( $filename, '.editor.js' )
			|| str_ends_with( $filename, '-editor.css' )
			|| str_ends_with( $filename, '-editor.scss' )
			|| str_ends_with( $filename, '-editor.js' );
	}
}
