<?php
/**
 * Extensions class.
 *
 * @package Blockstudio
 */

namespace Blockstudio;

use WP_HTML_Tag_Processor;

/**
 * Handles block extensions.
 */
class Extensions {

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_filter( 'render_block', array( __CLASS__, 'render_blocks' ), 10, 2 );
	}

	/**
	 * Render blocks with extensions.
	 *
	 * @param string $block_content The block content.
	 * @param array  $block         The block data.
	 *
	 * @return string The modified block content.
	 */
	public static function render_blocks( $block_content, $block ): string {
		if ( ! class_exists( 'WP_HTML_Tag_Processor' ) ) {
			return $block_content;
		}

		$extensions = Build::extensions();
		$matches    = self::get_matches( $block['blockName'], $extensions );

		$attributes       = array();
		$block_attributes = $block['attrs']['blockstudio']['attributes'] ?? array();

		foreach ( $matches as $match ) {
			foreach ( $match->attributes as $attribute_id => $attribute ) {
				if ( isset( $block_attributes[ $attribute_id ] ) ) {
					$attributes[ $attribute_id ] = $attribute;
				}
			}
		}

		$blockstudio_attributes = array(
			'blockstudio' => array(
				'attributes' => $block_attributes,
				'disabled'   => $block['attrs']['blockstudio']['disabled'] ?? array(),
			),
		);
		$ref                    = $blockstudio_attributes;

		$attribute_data = Block::transform(
			$blockstudio_attributes,
			$ref,
			null,
			false,
			false,
			$attributes
		);

		if ( empty( $matches ) && ! ( $attribute_data['hasCodeSelector'] ?? false ) ) {
			return $block_content;
		}

		$content = new WP_HTML_Tag_Processor( $block_content );

		$is_sequential = function ( $arr ) {
			return is_array( $arr ) &&
				array_keys( $arr ) === range( 0, count( $arr ) - 1 );
		};

		if ( $content->next_tag() ) {
			$class         = '';
			$style         = '';
			$current_class = $content->get_attribute( 'class' );
			$current_style = $content->get_attribute( 'style' );

			if (
				'' !== trim( $current_style ?? '' ) &&
				! Files::ends_with( $current_style, ';' )
			) {
				$current_style .= ';';
			}

			if ( $attribute_data['hasCodeSelector'] ) {
				$content->set_attribute(
					'data-assets',
					$attribute_data['selectorAttributeId']
				);
			}

			foreach ( $attributes as $key => $value ) {
				if (
					! isset( $value['set'] ) &&
					isset( $value['field'] ) &&
					'attributes' === $value['field']
				) {
					if ( is_array( $blockstudio_attributes[ $key ] ) ) {
						foreach ( $blockstudio_attributes[ $key ] as $attr ) {
							$content->set_attribute(
								$attr['attribute'],
								$attr['value']
							);
						}
					}
				}

				foreach ( $value['set'] ?? array() as $set ) {
					$val = $blockstudio_attributes[ $key ];

					if ( ! $val ) {
						continue;
					}

					$apply_value = function ( $value, $attr ) use (
						&$class,
						&$style,
						$set,
						$content
					) {
						if ( $set['value'] ?? false ) {
							$value = self::parse_template(
								$set['value'],
								array(
									'attributes' => $attr,
								)
							);
						}

						if ( 'class' === $set['attribute'] ) {
							$class .= ' ' . $value;
						} elseif ( 'style' === $set['attribute'] ) {
							$style .= ' ' . $value . ';';
							$style  = str_replace( ';;', ';', $style );
						} else {
							$content->set_attribute( $set['attribute'], $value );
						}
					};

					if ( $is_sequential( $val ) ) {
						$index = -1;
						foreach ( $val as $v ) {
							++$index;

							if ( ! isset( $blockstudio_attributes[ $key ][ $index ] ) ) {
								continue;
							}

							$apply_value(
								$v,
								array(
									$key => $blockstudio_attributes[ $key ][ $index ],
								)
							);
						}
					} else {
						$apply_value( $value, $blockstudio_attributes );
					}
				}
			}

			$combined_class = trim( $current_class . $class );
			if ( '' !== $combined_class ) {
				$content->set_attribute( 'class', $combined_class );
			}

			$combined_style = trim( $current_style . $style );
			if ( '' !== $combined_style ) {
				$content->set_attribute(
					'style',
					str_replace( ';;', ';', $combined_style )
				);
			}
		}

		$element = $content->get_updated_html();
		$assets  = Assets::render_code_field_assets( $attribute_data );

		return $element . $assets;
	}

	/**
	 * Get matching extensions for a block.
	 *
	 * @param string $string     The block name.
	 * @param array  $extensions The extensions array.
	 *
	 * @return array Array of matching extensions.
	 */
	public static function get_matches( $string, $extensions ): array {
		$matches     = array();
		$match_found = function ( $name, $string ) {
			if ( ! $name || ! $string ) {
				return false;
			}

			if ( '*' === substr( $name, -1 ) ) {
				$prefix = substr( $name, 0, -1 );

				return 0 === strpos( $string, $prefix );
			} else {
				return $name === $string;
			}
		};

		foreach ( $extensions as $e ) {
			if ( is_array( $e->name ) ) {
				foreach ( $e->name as $name ) {
					if ( $match_found( $name, $string ) ) {
						$matches[] = $e;
					}
				}
			} else {
				if ( $match_found( $e->name, $string ) ) {
					$matches[] = $e;
				}
			}
		}

		return $matches;
	}

	/**
	 * Replace template string placeholders.
	 *
	 * @param string $template_string The template string.
	 * @param array  $values          The values to replace.
	 *
	 * @return string|null The parsed string.
	 */
	public static function parse_template( $template_string, $values ) {
		return preg_replace_callback(
			'/\{([^}]+)\}/',
			function ( $matches ) use ( $values ) {
				$path = $matches[1];

				return self::get( $values, $path );
			},
			$template_string
		);
	}

	/**
	 * Get a nested array element similar to Lodash/Get.
	 *
	 * @param mixed       $target  The target array or object.
	 * @param string|null $key     The key path.
	 * @param mixed       $default The default value.
	 *
	 * @return mixed The value or default.
	 */
	public static function get( $target, $key, $default = null ) {
		if ( is_null( $key ) ) {
			return $target;
		}

		$key = is_array( $key ) ? $key : explode( '.', $key );

		foreach ( $key as $segment ) {
			if ( ! is_array( $target ) && ! is_object( $target ) ) {
				return $default;
			}
			if ( is_array( $target ) && array_key_exists( $segment, $target ) ) {
				$target = $target[ $segment ];
			} elseif (
				is_object( $target ) &&
				property_exists( $target, $segment )
			) {
				$target = $target->$segment;
			} else {
				return $default;
			}
		}

		return $target;
	}
}

new Extensions();
