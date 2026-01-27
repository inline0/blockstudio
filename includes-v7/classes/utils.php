<?php
/**
 * Utils class.
 *
 * @package Blockstudio
 */

namespace Blockstudio;

/**
 * Utility functions for Blockstudio.
 */
class Utils {

	/**
	 * Render attributes as HTML data attributes or CSS variables.
	 *
	 * @param array $data      The data to render.
	 * @param array $allowed   Allowed keys (empty = all allowed).
	 * @param bool  $variables Whether to render as CSS variables.
	 *
	 * @return string
	 */
	public static function attributes( $data, $allowed = array(), $variables = false ): string {
		$attributes = '';

		foreach ( $data as $key => $value ) {
			if (
				( count( $allowed ) >= 1 && ! in_array( $key, $allowed, true ) ) ||
				empty( $value )
			) {
				continue;
			}
			$key   = preg_replace( '/([a-z])([A-Z])/', '$1_$2', $key );
			$key   = strtolower( $key );
			$key   = str_replace( '_', '-', $key );
			$value = $value['value'] ?? ( is_array( $value ) ? esc_attr( wp_json_encode( $value ) ) : $value );

			if ( ! $variables ) {
				$attributes .= 'data-' . $key . '="' . $value . '" ';
			} elseif ( ! is_array( $value ) ) {
				$attributes .= '--' . $key . ': ' . $value . ';';
			}
		}

		return $attributes;
	}

	/**
	 * Render data attributes from an array.
	 *
	 * @param array $data_attributes Array of attribute data.
	 *
	 * @return string
	 */
	public static function data_attributes( $data_attributes ): string {
		$attributes = '';
		foreach ( $data_attributes ?? array() as $data ) {
			$attr  = $data['attribute'];
			$value = $data['value'];

			if ( isset( $data['data']['media'] ) && 'src' === $attr ) {
				$srcset      = wp_get_attachment_image_srcset( $data['data']['media'] );
				$src         = wp_get_attachment_image_url( $data['data']['media'] );
				$attributes .= " src='" . esc_url( $src ) . "'";
				$attributes .= " srcset='" . esc_attr( $srcset ) . "'";
			} else {
				$attributes .= ' ' . esc_attr( $attr ) . "='" . esc_attr( $value ) . "'";
			}
		}

		return $attributes;
	}

	/**
	 * Output data to browser console.
	 *
	 * @param mixed $data The data to log.
	 *
	 * @return void
	 */
	public static function console_log( $data ): void {
		echo '<script>console.log(' . wp_json_encode( $data ) . ')</script>';
	}
}
