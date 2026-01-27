<?php
/**
 * Field class.
 *
 * @package Blockstudio
 */

namespace Blockstudio;

/**
 * Field helper utilities.
 */
class Field {

	/**
	 * Get attributes belonging to a group.
	 *
	 * @param array  $attributes All block attributes.
	 * @param string $group      The group prefix to filter by.
	 *
	 * @return array Filtered attributes with group prefix removed from keys.
	 */
	public static function group( $attributes, $group ): array {
		$g = array();
		foreach ( $attributes as $k => $v ) {
			if ( 0 === strpos( $k, $group ) ) {
				$len     = strlen( $group . '_' );
				$key     = substr( $k, $len );
				$g[ $key ] = $v;
			}
		}

		return $g;
	}
}
