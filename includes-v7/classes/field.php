<?php
/**
 * Field class.
 *
 * @package Blockstudio
 */

namespace Blockstudio;

/**
 * Field helper utilities for working with block attributes.
 *
 * This class provides utility methods for template developers to work
 * with Blockstudio block attributes, particularly grouped fields.
 *
 * Group Fields:
 * When you define a "group" field in block.json, child attributes are
 * stored with a prefix: groupName_fieldName. This class helps extract
 * and work with grouped attributes.
 *
 * Example block.json:
 * ```json
 * {
 *   "attributes": {
 *     "cta": {
 *       "type": "object",
 *       "field": "group",
 *       "attributes": {
 *         "text": { "type": "string", "field": "text" },
 *         "url": { "type": "string", "field": "url" }
 *       }
 *     }
 *   }
 * }
 * ```
 *
 * In template, attributes arrive as:
 * - $attributes['cta_text'] = 'Learn More'
 * - $attributes['cta_url'] = 'https://...'
 *
 * Using Field::group():
 * ```php
 * $cta = Field::group($attributes, 'cta');
 * // Result: ['text' => 'Learn More', 'url' => 'https://...']
 *
 * echo '<a href="' . $cta['url'] . '">' . $cta['text'] . '</a>';
 * ```
 *
 * @since 3.0.0
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
