<?php
/**
 * Render check for registered blocks.
 *
 * @package Blockstudio
 */

namespace Blockstudio;

use Throwable;

/**
 * Renders registered blocks the way a page does and collects PHP problems.
 *
 * Each block is rendered through `do_blocks()` from a block comment that
 * carries `blockstudio.attributes`, the shape the editor stores. Nothing is
 * written to the database.
 *
 * @since 7.7.9
 */
final class Render_Check {

	/**
	 * Render one block and collect the PHP problems it raises.
	 *
	 * Without `$attributes` the block renders with the `example.attributes`
	 * of its block.json, or with its field defaults when it declares none.
	 * Example values that are editor placeholders for generated images are
	 * left out, as they only make sense in the inserter preview.
	 *
	 * Notices, warnings, deprecations, and thrown errors are collected. The same
	 * problem raised repeatedly, for example in a loop, is reported once.
	 * WordPress raises its own deprecation notices only while `WP_DEBUG` is on.
	 *
	 * @param string     $name       Registered block name.
	 * @param array|null $attributes Blockstudio field values to render with, or null for the example.
	 * @return array{block:string,case:string,problems:array<int,array{level:string,message:string,file:string,line:int}>} Result. `case` is `attributes`, `example`, or `defaults`.
	 */
	public static function block( string $name, ?array $attributes = null ): array {
		$case = 'attributes';
		if ( null === $attributes ) {
			$attributes = self::example( $name );
			$case       = array() === $attributes ? 'defaults' : 'example';
		}

		$comment = array() === $attributes
			? array()
			: array( 'blockstudio' => array( 'attributes' => $attributes ) );
		$markup  = get_comment_delimited_block_content( $name, $comment, '' );

		Batch_Render::reset();

		// phpcs:disable WordPress.PHP.DevelopmentFunctions.prevent_path_disclosure_error_reporting, WordPress.PHP.DiscouragedPHPFunctions.runtime_configuration_error_reporting, WordPress.PHP.DevelopmentFunctions.error_log_set_error_handler -- Collecting every PHP problem of one render is the purpose of this class, and nothing is displayed.
		$problems  = array();
		$reporting = error_reporting( E_ALL );
		$buffers   = ob_get_level();

		set_error_handler(
			static function ( int $number, string $message, string $file, int $line ) use ( &$problems ): bool {
				// The silence operator lowers error_reporting() for the call.
				if ( ! ( error_reporting() & $number ) ) {
					return false;
				}

				$problems[] = array(
					'level'   => self::level( $number ),
					'message' => $message,
					'file'    => $file,
					'line'    => $line,
				);

				return true;
			}
		);

		try {
			do_blocks( $markup );
		} catch ( Throwable $error ) {
			$problems[] = array(
				'level'   => 'Error',
				'message' => $error->getMessage(),
				'file'    => $error->getFile(),
				'line'    => $error->getLine(),
			);
		} finally {
			restore_error_handler();
			error_reporting( $reporting );

			// A template that throws leaves its output buffer open.
			while ( ob_get_level() > $buffers ) {
				ob_end_clean();
			}
		}
		// phpcs:enable

		$unique = array();
		foreach ( $problems as $problem ) {
			$unique[ implode( "\0", $problem ) ] = $problem;
		}

		return array(
			'block'    => $name,
			'case'     => $case,
			'problems' => array_values( $unique ),
		);
	}

	/**
	 * Read the renderable example values of a block.
	 *
	 * @param string $name Registered block name.
	 * @return array<string, mixed> Field values keyed by storage key.
	 */
	private static function example( string $name ): array {
		$example = Build::blocks()[ $name ]->example ?? null;
		$values  = is_array( $example ) && is_array( $example['attributes'] ?? null ) ? $example['attributes'] : array();

		return array_filter(
			$values,
			static fn( $value ): bool => ! ( is_array( $value ) && isset( $value['blockstudio'] ) && 'image' === ( $value['type'] ?? '' ) )
		);
	}

	/**
	 * Name the severity of a PHP error number.
	 *
	 * @param int $number PHP error number.
	 * @return string Notice, Warning, Deprecated, or Error.
	 */
	private static function level( int $number ): string {
		return match ( $number ) {
			E_NOTICE, E_USER_NOTICE => 'Notice',
			E_WARNING, E_USER_WARNING, E_CORE_WARNING, E_COMPILE_WARNING => 'Warning',
			E_DEPRECATED, E_USER_DEPRECATED => 'Deprecated',
			default => 'Error',
		};
	}
}
