<?php
/**
 * LLM class.
 *
 * @package Blockstudio
 */

namespace Blockstudio;

use BlockstudioVendor\ScssPhp\ScssPhp\Exception\SassException;

/**
 * Handles LLM context generation.
 */
class LLM {

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'template_redirect', array( $this, 'serve_custom_txt_file' ) );
	}

	/**
	 * Recursively transforms the block data tree.
	 *
	 * @param array $node The node to transform.
	 *
	 * @return array The transformed node.
	 */
	private static function transform_block_tree( array $node ): array {
		$result = array();

		foreach ( $node as $key => $value ) {
			if (
				'.' === $key &&
				is_array( $value ) &&
				isset( $value['directory'] ) &&
				true === $value['directory']
			) {
				continue;
			}

			if ( is_array( $value ) ) {
				if (
					isset( $value['directory'] ) &&
					false === $value['directory'] &&
					isset( $value['name'] )
				) {
					$result[ $key ] = new \stdClass();
				} else {
					$transformed_child = self::transform_block_tree( $value );
					if ( ! empty( $transformed_child ) || empty( $value ) ) {
						$result[ $key ] = $transformed_child;
					} elseif (
						is_array( $value ) &&
						! empty( $value ) &&
						empty( $transformed_child )
					) {
						if (
							array_keys( $value ) !== range( 0, count( $value ) - 1 )
						) {
							$result[ $key ] = new \stdClass();
						} else {
							$result[ $key ] = array();
						}
					}
				}
			} else {
				$result[ $key ] = $value;
			}
		}

		return $result;
	}

	/**
	 * Get .txt URL.
	 *
	 * @return string The URL to the LLM context file.
	 */
	public static function get_txt_url(): string {
		return site_url() . '/blockstudio-llm.txt';
	}

	/**
	 * Get compiled .txt data.
	 *
	 * @return string The compiled LLM context data.
	 *
	 * @throws SassException If SCSS compilation fails.
	 */
	public static function get_txt_data(): string {
		// phpcs:disable WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Reading local template files.
		$txt  = file_get_contents( BLOCKSTUDIO_DIR . '/includes-v7/llm/llm.txt' );
		$docs = file_get_contents( BLOCKSTUDIO_DIR . '/includes-v7/llm/blockstudio.md' );

		$block_schema = wp_json_encode(
			json_decode(
				file_get_contents( BLOCKSTUDIO_DIR . '/includes-v7/schemas/block.json' )
			)
		);

		$extensions_schema = wp_json_encode(
			json_decode(
				file_get_contents( BLOCKSTUDIO_DIR . '/includes-v7/schemas/extensions.json' )
			)
		);
		// phpcs:enable WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents

		$blocks_raw         = Build::data_sorted();
		$transformed_blocks = array();

		foreach ( $blocks_raw as $instance_key => $instance_data ) {
			if ( is_array( $instance_data ) ) {
				$transformed_blocks[ $instance_key ] = self::transform_block_tree( $instance_data );
			} else {
				$transformed_blocks[ $instance_key ] = $instance_data;
			}
		}

		$txt = str_replace( '%%docs%%', $docs, $txt );
		$txt = str_replace( '%%schemaBlocks%%', $block_schema, $txt );
		$txt = str_replace( '%%schemaExtensions%%', $extensions_schema, $txt );
		$txt = str_replace( '%%settings%%', wp_json_encode( Settings::get_all() ), $txt );
		$txt = str_replace( '%%blocks%%', wp_json_encode( $transformed_blocks ), $txt );

		$example_blocks_base_path = BLOCKSTUDIO_DIR . '/includes-v7/library/blockstudio-element/';
		$example_block_names      = array( 'gallery', 'icon', 'image-comparison', 'slider' );
		$block_creation_examples  = PHP_EOL;

		$file_type_mappings = array(
			'block.json'        => 'json',
			'index.php'         => 'php',
			'style.css'         => 'css',
			'style-inline.css'  => 'css',
			'style.scss'        => 'scss',
			'style-inline.scss' => 'scss',
			'script.js'         => 'javascript',
			'script-inline.js'  => 'javascript',
		);

		foreach ( $example_block_names as $block_name ) {
			$block_path = $example_blocks_base_path . $block_name . '/';

			if ( ! is_dir( $block_path ) ) {
				continue;
			}

			$block_creation_examples .= "--- Block Example: blockstudio-element/{$block_name} ---" . PHP_EOL . PHP_EOL;

			foreach ( $file_type_mappings as $file_name => $lang ) {
				$file_path = $block_path . $file_name;

				if ( file_exists( $file_path ) ) {
					// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Reading local block files.
					$content                   = trim( file_get_contents( $file_path ) );
					$relative_path_for_display = "includes/library/blockstudio-element/{$block_name}/{$file_name}";
					$block_creation_examples  .= "--- File: {$relative_path_for_display} ---" . PHP_EOL;
					$block_creation_examples  .= "```{$lang}" . PHP_EOL;
					$block_creation_examples  .= $content . PHP_EOL;
					$block_creation_examples  .= '```' . PHP_EOL . PHP_EOL;
				}
			}
		}

		$txt = str_replace( '%%blockCreationExamples%%', $block_creation_examples, $txt );

		return $txt;
	}

	/**
	 * Serve a custom .txt file.
	 *
	 * @return void
	 */
	public function serve_custom_txt_file(): void {
		$request_uri = isset( $_SERVER['REQUEST_URI'] )
			? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) )
			: '';

		$enabled = Settings::get( 'ai/enableContextGeneration' );

		if ( Files::ends_with( $request_uri, '/blockstudio-llm.txt' ) && $enabled ) {
			header( 'Content-Type: text/plain; charset=utf-8' );
			status_header( 200 );
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Plain text output for LLM context.
			echo self::get_txt_data();
			die();
		}
	}
}

new LLM();
