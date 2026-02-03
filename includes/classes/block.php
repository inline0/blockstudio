<?php
/**
 * Block class.
 *
 * @package Blockstudio
 */

namespace Blockstudio;

use DOMDocument;
use ErrorException;
use Throwable;
use Timber\Timber;
use WP_HTML_Tag_Processor;

/**
 * Handles rendering of Blockstudio blocks on the frontend and in the editor.
 *
 * This class is responsible for:
 *
 * 1. Block Rendering (render method):
 *    - Called by WordPress when a block needs to be displayed
 *    - Transforms block attributes into template variables
 *    - Supports PHP, Twig, and Blade template engines
 *    - Handles inline assets, scoped CSS, and interactivity API
 *
 * 2. Attribute Transformation (transform_attributes):
 *    - Converts raw block attributes into structured data
 *    - Resolves media IDs to attachment data (URLs, alt text, etc.)
 *    - Processes repeater fields recursively
 *    - Handles option fields (select, checkbox, radio)
 *
 * 3. Component Replacement:
 *    - Replaces custom tags like <RichText>, <InnerBlocks>, <MediaPlaceholder>
 *    - Processes useBlockProps for wrapper element attributes
 *
 * 4. Block Tracking:
 *    - Tracks render count per block type ($count_by_block)
 *    - Provides index numbers for CSS nth-child styling
 *
 * Template Variables Available:
 *   $block['index']       - Current block index (nth instance of this type)
 *   $block['indexTotal']  - Total blocks rendered so far
 *   $block['id']          - Unique block ID for this render
 *   $block['name']        - Block name (e.g., 'blockstudio/my-block')
 *   $block['classes']     - CSS classes for the wrapper
 *   $attributes           - Transformed block attributes
 *
 * @since 2.4.0
 */
class Block {

	/**
	 * Total number of blocks rendered in the current request.
	 *
	 * Incremented each time any Blockstudio block is rendered.
	 * Available in templates as $block['indexTotal'].
	 *
	 * @var int
	 */
	private static int $count = 0;

	/**
	 * Render count per block type, indexed by block name.
	 *
	 * Tracks how many times each specific block type has been rendered.
	 * Used to provide $block['index'] for nth-child CSS targeting.
	 * Example: ['blockstudio/hero' => 2, 'blockstudio/card' => 5]
	 *
	 * @var array<string, int>
	 */
	private static array $count_by_block = array();

	/**
	 * Get unique ID.
	 *
	 * @since 5.5.0
	 *
	 * @param mixed $block      The block data.
	 * @param array $attributes The block attributes.
	 *
	 * @return string The unique ID.
	 */
	public static function id( $block, $attributes ): string {
		return 'blockstudio-' .
			substr(
				md5( uniqid() . wp_json_encode( $block ) . wp_json_encode( $attributes ) ),
				0,
				12
			);
	}

	/**
	 * Get block ID as an HTML comment.
	 *
	 * @since 3.0.0
	 *
	 * @param string $name The block name.
	 *
	 * @return string The HTML comment.
	 */
	public static function comment( $name ): string {
		return '<!--blockstudio/' . Build::data()[ $name ]['name'] . '-->';
	}

	/**
	 * Get option value.
	 *
	 * @since 3.0.4
	 *
	 * @param array  $data          The field data.
	 * @param string $return_format The return format.
	 * @param mixed  $v             The value.
	 * @param array  $populate      The populate settings.
	 *
	 * @return mixed The option value.
	 */
	public static function get_option_value(
		$data,
		$return_format,
		$v,
		array $populate = array()
	) {
		$fetch       = $populate['fetch'] ?? false;
		$options_map = array();
		$options     = $fetch ? array( $v ) : $data['options'] ?? array();

		foreach ( $options as $option ) {
			$value         = $option['value'] ?? false;
			$query_options = array( 'posts', 'users', 'terms' );
			if (
				isset( $populate['type'] ) &&
				'query' === $populate['type'] &&
				isset( $populate['query'] ) &&
				( ( in_array( $populate['query'], $query_options, true ) &&
					in_array( $value, $data['optionsPopulate'] ?? array(), true ) ) ||
					$fetch )
			) {
				$is_object =
					( isset( $populate['returnFormat']['value'] ) &&
						'id' !== $populate['returnFormat']['value'] ) ||
					! isset( $populate['returnFormat']['value'] );

				$query_function_map = array(
					'posts' => 'get_post',
					'users' => 'get_user_by',
					'terms' => 'get_term',
				);

				if ( $is_object ) {
					$value =
						'users' === $populate['query']
							? get_user_by( 'id', $value )
							: call_user_func(
								$query_function_map[ $populate['query'] ],
								$value
							);
				}
			}

			if ( isset( $option['value'] ) ) {
				$options_map[ $option['value'] ] = array(
					'value' => $value,
					'label' => $option['label'] ?? $value,
				);
			} elseif ( ! $fetch ) {
				$options_map[ $option ] = array(
					'value' => $option,
					'label' => $option,
				);
			}
		}

		try {
			if ( 'label' === $return_format ) {
				return $options_map[ $v['value'] ?? $v ]['label'] ?? false;
			}
			if ( 'both' === $return_format ) {
				return $options_map[ $v['value'] ?? $v ] ?? false;
			}

			return $options_map[ $v['value'] ?? $v ]['value'] ?? false;
		} catch ( Throwable $err ) {
			return false;
		}
	}

	/**
	 * Get attachment data for the file field.
	 *
	 * @since 3.0.11
	 *
	 * @param int|null    $id      The attachment ID.
	 * @param string|bool $example The example file path.
	 * @param int         $index   The index.
	 * @param string      $size    The image size.
	 *
	 * @return array|false The attachment data or false.
	 */
	public static function get_attachment_data(
		$id = null,
		$example = false,
		$index = 0,
		$size = 'full'
	) {
		$image = array();

		if ( $example ) {
			$url                  = Files::get_relative_url( $example );
			$image['ID']          = $index;
			$image['title']       = "Image title $index";
			$image['alt']         = "Image alt $index";
			$image['caption']     = "Image caption $index";
			$image['description'] = "Image description $index";
			$image['href']        = $url;
			$image['url']         = $url;

			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Reading local example file.
			$xml_get        = simplexml_load_string( file_get_contents( $example ) );
			$xml_attributes = $xml_get->attributes();
			$width          = (string) $xml_attributes->width;
			$height         = (string) $xml_attributes->height;

			$sizes = get_intermediate_image_sizes();
			if ( $sizes ) {
				array_unshift( $sizes, 'full' );

				foreach ( $sizes as $size ) {
					$image['sizes'][ $size ]             = $url;
					$image['sizes'][ $size . '-width' ]  = $width;
					$image['sizes'][ $size . '-height' ] = $height;
				}
			}

			return $image;
		}

		$meta = get_post( $id );
		if ( ! empty( $id ) && $meta ) {
			$image['ID']          = $id;
			$image['title']       = $meta->post_title;
			$alt                  = get_post_meta(
				$meta->ID,
				'_wp_attachment_image_alt',
				true
			);
			$image['alt']         = $alt ? $alt : $meta->post_title;
			$image['caption']     = $meta->post_excerpt;
			$image['description'] = $meta->post_content;
			$image['href']        = get_permalink( $meta->ID );
			if ( 'full' !== $size ) {
				$image['url'] =
					wp_get_attachment_image_src( $id, $size )[0] ?? '';
			} else {
				$image['url'] = $meta->guid;
			}

			$sizes = get_intermediate_image_sizes();
			if ( $sizes ) {
				array_unshift( $sizes, 'full' );

				foreach ( $sizes as $size ) {
					$src = wp_get_attachment_image_src( $id, $size );
					if ( $src ) {
						$image['sizes'][ $size ]             = $src[0];
						$image['sizes'][ $size . '-width' ]  = $src[1];
						$image['sizes'][ $size . '-height' ] = $src[2];
					}
				}
			} else {
				$image['sizes'] = null;
			}

			return $image;
		}

		return false;
	}

	/**
	 * Replace custom block tags.
	 *
	 * @since 4.2.0
	 *
	 * @param string $content          The block content (passed by reference).
	 * @param string $replace          The replacement content.
	 * @param object $block            The block data.
	 * @param array  $block_attributes The block attributes.
	 * @param string $tag              The tag to replace.
	 * @param string $type             The attribute type.
	 *
	 * @return void
	 */
	public static function replace_custom_tag(
		&$content,
		$replace,
		$block,
		$block_attributes,
		$tag,
		$type
	) {
		$regex =
			'InnerBlocks' !== $type
				? '/<' .
					preg_quote( $tag, '/' ) .
					'(?=[^>]*(\battribute=["\']' .
					preg_quote( $type, '/' ) .
					'["\']))\s*(.*?)\s*\/?>/s'
				: '/<' . preg_quote( $tag, '/' ) . '\s*(.*?)\s*\/?>/s';

		$replace = str_replace( '$', '\$', $replace );
		preg_match( $regex, $content, $matches );

		$has_match = count( $matches ) >= 2;

		if ( $has_match ) {
			$attribute_map = array();
			$attributes    =
				'InnerBlocks' === $tag
					? $matches[1] ?? $matches[2]
					: $matches[2] ?? $matches[1];

			$attributes = str_replace( array( '"', '"' ), '"', $attributes );

			$pattern =
				'/([a-zA-Z_][a-zA-Z0-9\-_:.]*)(?:\s*=\s*(?:"([^"]*)"|\'([^\']*)\'|([^ \/>]+)))?/';

			preg_match_all( $pattern, $attributes, $matches, PREG_SET_ORDER );

			foreach ( $matches as $match ) {
				$attr_name                   = $match[1];
				$attr_value                  = $match[2] ?? ( $match[3] ?? ( $match[4] ?? true ) );
				$attribute_map[ $attr_name ] = $attr_value;
			}

			$attribute   =
				$block_attributes[ $attribute_map['attribute'] ?? null ] ?? null;
			$element_tag =
				$attribute_map['tag'] ?? ( 'InnerBlocks' === $type ? 'div' : 'p' );

			$attr = '';
			foreach ( $attribute_map as $name => $value ) {
				if ( true === $value ) {
					$attr .= "$name ";
				} else {
					$attr .= sprintf(
						'%s="%s" ',
						$name,
						htmlspecialchars( $value )
					);
				}
			}
			$attr = trim( $attr );

			if ( 'RichText' === $tag ) {
				$rich_text_content = apply_filters(
					'blockstudio/blocks/components/richtext/render',
					$attribute,
					$block
				);

				$rich_text_content = str_replace( '$', '\$', $rich_text_content ?? '' );

				$content = preg_replace(
					$regex,
					$attribute
						? "<$element_tag $attr>" .
							$rich_text_content .
							"</$element_tag>"
						: '',
					$content
				);
			} else {
				// phpcs:disable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- Internal property name.
				$inner_blocks_content =
					isset( $block->blockstudioEditor ) &&
					isset( $block->blockstudio['editor']['innerBlocks'] )
						? $block->blockstudio['editor']['innerBlocks']
						: $replace;
				// phpcs:enable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
				$inner_blocks_content = apply_filters(
					'blockstudio/blocks/components/innerblocks/render',
					$inner_blocks_content,
					$block
				);
				$content              = preg_replace(
					$regex,
					apply_filters(
						'blockstudio/blocks/components/innerblocks/frontend/wrap',
						true,
						$block
					)
						? "<$element_tag $attr>" .
							$inner_blocks_content .
							"</$element_tag>"
						: $inner_blocks_content,
					$content
				);
			}
		}
	}

	/**
	 * Remove component from block content.
	 *
	 * @since 5.3.0
	 *
	 * @param string $content   The block content (passed by reference).
	 * @param string $component The component to remove.
	 *
	 * @return void
	 */
	public static function remove_custom_tag( &$content, $component ) {
		$regex   = '/<' . preg_quote( $component, '/' ) . '\s*(.*?)\s*\/?>/s';
		$content = preg_replace( $regex, '', $content );
	}

	/**
	 * Replace block content with components.
	 *
	 * @since 4.0.0
	 *
	 * @param string $content              The block content.
	 * @param string $inner_blocks         The inner blocks content.
	 * @param bool   $is_editor_or_preview Whether in editor or preview mode.
	 * @param object $block                The block data.
	 * @param array  $attributes           The block attributes.
	 * @param array  $attributes_block     The block's attributes array.
	 * @param array  $attribute_data       The attribute data.
	 *
	 * @return string The modified content.
	 */
	public static function replace_components(
		$content,
		$inner_blocks,
		$is_editor_or_preview,
		$block,
		$attributes,
		$attributes_block,
		$attribute_data
	) {
		if ( $is_editor_or_preview ) {
			if (
				class_exists( 'WP_HTML_Tag_Processor' ) &&
				false !== strpos( $content, 'useBlockProps' )
			) {
				$content = new WP_HTML_Tag_Processor( $content );
				if ( $content->next_tag() ) {
					$classes = $content->get_attribute( 'class' );
					$content->set_attribute(
						'class',
						$classes . ' wp-block block-editor-block-list__block'
					);
				}
			}

			return str_replace(
				'useBlockProps',
				'useblockprops="true"',
				$content
			);
		}

		self::replace_custom_tag(
			$content,
			$inner_blocks,
			$block,
			$attributes,
			'InnerBlocks',
			'InnerBlocks'
		);

		foreach ( $block->attributes ?? array() as $attribute ) {
			if (
				isset( $attribute['id'] ) &&
				isset( $attribute['type'] ) &&
				'richtext' !== $attribute['type']
			) {
				self::replace_custom_tag(
					$content,
					$inner_blocks,
					$block,
					$attributes,
					'RichText',
					$attribute['id']
				);
			}
		}

		self::remove_custom_tag( $content, 'MediaPlaceholder' );

		$attributes_to_remove = array(
			// General.
			'useBlockProps',
			'tag',
			// InnerBlocks.
			'allowedBlocks',
			'defaultBlock',
			'directInsert',
			'prioritizedInserterBlocks',
			'renderAppender',
			'template',
			'templateInsertUpdatesSelection',
			'templateLock',
			// RichText.
			'attribute',
			'placeholder',
			'allowedFormats',
			'autocompleters',
			'multiline',
			'preserveWhiteSpace',
			'withoutInteractiveFormatting',
		);

		$has_attribute = false;
		foreach ( $attributes_to_remove as $attribute ) {
			if ( false !== strpos( $content, $attribute ) ) {
				$has_attribute = true;
				break;
			}
		}

		if ( $has_attribute ) {
			$content = str_replace(
				'useBlockProps',
				'useblockprops="true"',
				$content
			);

			$doc = new DOMDocument();
			libxml_use_internal_errors( true );
			$doc->loadHTML(
				mb_encode_numericentity(
					$content,
					array( 0x80, 0x10ffff, 0, 0xffffff ),
					'UTF-8'
				)
			);
			libxml_clear_errors();
			$elements = $doc->getElementsByTagName( '*' );
			foreach ( $elements as $element ) {
				if ( $element->hasAttribute( 'useblockprops' ) ) {
					$classes = $element->getAttribute( 'class' );

					if ( $attribute_data['hasCodeSelector'] ?? false ) {
						$element->setAttribute(
							'data-assets',
							$attribute_data['selectorAttributeId'] ?? ''
						);
					}

					$attributes = array();
					preg_match_all(
						'/(\S+)="([^"]+)"/',
						apply_filters(
							'blockstudio/blocks/components/useblockprops/render',
							get_block_wrapper_attributes(
								array(
									'class' => $classes,
									'id'    =>
										$attributes_block['anchor'] ??
										$element->getAttribute( 'id' ),
								)
							),
							$block
						),
						$attributes,
						PREG_SET_ORDER
					);

					foreach ( $attributes as $attribute ) {
						$element->setAttribute( $attribute[1], $attribute[2] );
					}
				}
				foreach ( $attributes_to_remove as $attribute ) {
					$attr = strtolower( $attribute );
					// phpcs:disable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- DOM property.
					if (
						$element->hasAttribute( $attr ) &&
						'input' !== $element->nodeName &&
						'textarea' !== $element->nodeName
					) {
						// phpcs:enable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
						$element->removeAttribute( $attr );
					}
				}
			}
			$trim_off_front = strpos( $doc->saveHTML(), '<body>' ) + 6;
			$trim_off_end   =
				strrpos( $doc->saveHTML(), '</body>' ) - strlen( $doc->saveHTML() );

			$content = substr( $doc->saveHTML(), $trim_off_front, $trim_off_end );
		}

		return $content;
	}

	/**
	 * Transform attributes.
	 *
	 * @since 4.1.5
	 *
	 * @param array      $attributes       The attributes (passed by reference).
	 * @param array      $attribute_names  The attribute names (passed by reference).
	 * @param array      $disabled         The disabled attributes.
	 * @param string     $name             The block name.
	 * @param mixed      $block            The block data.
	 * @param array|bool $repeater         The repeater attributes.
	 * @param array      $block_attributes The block attributes.
	 * @param array      $attribute_data   The attribute data (passed by reference).
	 *
	 * @return array The transformed attribute data.
	 */
	public static function transform_attributes(
		&$attributes,
		&$attribute_names,
		$disabled,
		$name,
		$block,
		$repeater = false,
		$block_attributes = array(),
		&$attribute_data = array()
	): array {
		if ( $attribute_data['selectorAttributeId'] ?? false ) {
			$selector_attribute_id = $attribute_data['selectorAttributeId'];
		} else {
			$selector_attribute_id                 = self::id( $block, $attributes );
			$attribute_data['selectorAttributeId'] = $selector_attribute_id;
		}
		$selector_attribute = "data-assets='$selector_attribute_id'";

		foreach ( $attributes as $k => $v ) {
			$att = $repeater
				? array_values(
					array_filter(
						$repeater,
						fn( $item ) => ( $item['id'] ?? false ) === $k
					)
				)[0] ?? false
				: $block_attributes[ $k ] ?? false;

			if ( isset( $att['blockstudio'] ) && ! $repeater ) {
				$attribute_names[] = $k;
			}

			if ( isset( $att['type'] ) && $att && ( ! empty( $v ) || '0' === $v ) ) {
				$return_format = $att['returnFormat'] ?? 'value';
				$populate      = $att['populate'] ?? array();
				$type          = $att['field'] ?? false;

				if ( ! $type ) {
					continue;
				}

				if (
					'select' === $type ||
					'radio' === $type ||
					'checkbox' === $type ||
					'token' === $type
				) {
					if (
						'select' === $type &&
						isset( $populate['type'] ) &&
						'fetch' === $populate['type']
					) {
						$attributes[ $k ] = $v;
					} else {
						if ( 'select' === $type || 'radio' === $type ) {
							$attributes[ $k ] = self::get_option_value(
								$att,
								$return_format,
								$v,
								$populate
							);
						}
						if (
							'checkbox' === $type ||
							( 'select' === $type && ( $att['multiple'] ?? false ) )
						) {
							$new_values = array();
							foreach ( $v as $l ) {
								$val = self::get_option_value(
									$att,
									$return_format,
									$l,
									$populate
								);

								if ( $val ) {
									$new_values[] = $val;
								}
							}

							if ( 'checkbox' === $type ) {
								if (
									isset( $new_values[0]->ID ) ||
									isset( $new_values[0]->term_id )
								) {
									$is_id   = isset( $new_values[0]->ID );
									$is_term = isset( $new_values[0]->term_id );
									$key     = $is_id ? 'ID' : 'term_id';

									$sorting_arr = array_column(
										$att['options'],
										'value'
									);

									if ( $is_id || $is_term ) {
										uasort(
											$new_values,
											function (
												$a,
												$b
											) use (
												$key,
												$sorting_arr
											) {
												return array_search(
													$a->{$key} ??
														( $a['value'] ?? $a ),
													$sorting_arr,
													true
												) <=>
													array_search(
														$b->{$key} ??
															( $b['value'] ?? $b ),
														$sorting_arr,
														true
													);
											}
										);
									}
								} else {
									if (
										isset( $att['options'][0]['label'] ) &&
										'label' === $return_format
									) {
										$sorting_arr = array_column(
											$att['options'],
											'label'
										);
									} elseif (
										isset( $att['options'][0]['value'] )
									) {
										$sorting_arr = array_column(
											$att['options'],
											'value'
										);
									} else {
										$sorting_arr = $att['options'];
									}

									uasort(
										$new_values,
										function (
											$a,
											$b
										) use (
											$sorting_arr
										) {
											return array_search(
												$a['value'] ?? $a,
												$sorting_arr,
												true
											) <=>
												array_search(
													$b['value'] ?? $b,
													$sorting_arr,
													true
												);
										}
									);
								}
							}
							$attributes[ $k ] = array_values( $new_values );
						}
						if ( 'token' === $type && 'both' !== $return_format ) {
							$new_values = array();
							foreach ( $v as $l ) {
								$new_values[] = $l[ $return_format ] ?? $l;
							}
							$attributes[ $k ] = $new_values;
						}
					}
				}

				if ( 'files' === $type ) {
					if ( is_array( $v ) ) {
						foreach ( $v as $file_id ) {
							if ( in_array( $k . '_' . $file_id, $disabled, true ) ) {
								$attributes[ $k ] = array_filter(
									$attributes[ $k ],
									fn( $val ) => $val !== $file_id
								);
							}
						}
						$attributes[ $k ] = array_values( $attributes[ $k ] );
					} elseif ( in_array( $k . '_' . $v, $disabled, true ) ) {
						$attributes[ $k ] = false;
					}

					$size = 'full';

					if ( isset( $attributes[ $k . '__size' ] ) ) {
						$size = $attributes[ $k . '__size' ] ?? 'full';
					}

					if ( 'id' !== $return_format && 'url' !== $return_format ) {
						if ( is_array( $attributes[ $k ] ) ) {
							$object_array = array();
							foreach ( $attributes[ $k ] as $o ) {
								$object_array[] = self::get_attachment_data(
									$o,
									false,
									0,
									$size
								);
							}
							$attributes[ $k ] = $object_array;
						} elseif ( $attributes[ $k ] ) {
							$attributes[ $k ] = self::get_attachment_data(
								$attributes[ $k ],
								false,
								0,
								$size
							);
						}
					}

					if ( 'url' === $return_format ) {
						$media = fn( $id, $size ) => wp_attachment_is(
							'image',
							$id
						)
							? wp_get_attachment_image_src( $id, $size )[0] ??
								false
							: wp_get_attachment_url( $id ) ?? false;

						if ( is_array( $attributes[ $k ] ) ) {
							$url_array = array();
							foreach ( $attributes[ $k ] as $o ) {
								$url_array[] = $media( $o, $att['returnSize'] );
							}
							$attributes[ $k ] = $url_array;
						} elseif ( $attributes[ $k ] ) {
							$attributes[ $k ] = $media(
								$attributes[ $k ],
								$att['returnSize']
							);
						}

						if (
							( $att['multiple'] ?? false ) &&
							! is_array( $attributes[ $k ] )
						) {
							$attributes[ $k ] = array( $attributes[ $k ] );
						}
					}

					if (
						$attributes[ $k ] &&
						( $att['multiple'] ?? false ) &&
						( $attributes[ $k ]['ID'] ??
							( is_numeric( $attributes[ $k ] ) ?? false ) )
					) {
						$attributes[ $k ] = array( $attributes[ $k ] );
					}
				}

				if ( 'number' === $type || 'range' === $type ) {
					$attributes[ $k ] = floatval( $v );
				}

				if ( 'repeater' === $type ) {
					foreach ( $attributes[ $k ] as $i => $r ) {
						self::transform_attributes(
							$attributes[ $k ][ $i ],
							$attribute_names,
							array(),
							$name,
							$block,
							$att['attributes'],
							array(),
							$attribute_data
						);
					}
				}

				if ( 'icon' === $type ) {
					if ( 'element' === $return_format ) {
						$attributes[ $k ] = bs_icon( $v );
					} else {
						$attributes[ $k ]['element'] = bs_icon( $v );
					}
				}

				if ( 'code' === $type ) {
					$lang           = $att['language'];
					$replaced_value = str_replace(
						'%selector%',
						"[$selector_attribute]",
						$v
					);

					if ( str_contains( $v, '%selector%' ) ) {
						$attribute_data['hasCodeSelector'] = true;
					}

					if ( 'css' === $lang || 'javascript' === $lang ) {
						$asset_data                 = array(
							'language' => $lang,
							'value'    => $replaced_value,
						);
						$attribute_data['assets'][] = $asset_data;
						if ( $att['asset'] ?? false ) {
							$attribute_data['assetsAsset'][] = $asset_data;
						}
					}

					$attributes[ $k ] = $replaced_value;
				}
			}

			$is_false =
				'' === $v ||
				( is_array( $attributes[ $k ] ) && 0 === count( $attributes[ $k ] ) ) ||
				in_array( $k, $disabled, true );

			if ( ( $att['fallback'] ?? false ) && $is_false ) {
				$attributes[ $k ] = $att['fallback'];
			} elseif ( $is_false ) {
				$attributes[ $k ] = false;
			}

			$attributes[ $k ] = apply_filters(
				'blockstudio/blocks/attributes/render',
				$attributes[ $k ],
				$k,
				$block
			);
		}

		return array(
			'assets'              => $attribute_data['assets'] ?? array(),
			'assetsAsset'         => $attribute_data['assetsAsset'] ?? array(),
			'selectorAttribute'   => $selector_attribute,
			'selectorAttributeId' => $selector_attribute_id,
			'hasCodeSelector'     => $attribute_data['hasCodeSelector'] ?? false,
		);
	}

	/**
	 * Transform block data.
	 *
	 * @since 3.1.0
	 *
	 * @param array $attributes       The attributes (passed by reference).
	 * @param mixed $block            The block data (passed by reference).
	 * @param mixed $name             The block name.
	 * @param bool  $editor           Whether in editor mode.
	 * @param bool  $is_preview       Whether in preview mode.
	 * @param array $block_attributes The block attributes.
	 *
	 * @return array The transformed data.
	 */
	public static function transform(
		&$attributes,
		&$block,
		$name,
		$editor,
		$is_preview,
		$block_attributes = array()
	): array {
		$attr     = $block_attributes;
		$disabled = $attributes['blockstudio']['disabled'] ?? array();

		// Defaults.
		foreach ( $attr as $k => $v ) {
			$attr[ $k ] = $v['default'] ?? false;
		}
		$attributes = array_merge(
			$attr ?? array(),
			$attributes['blockstudio']['attributes'] ?? array()
		);

		// Transform.
		$attribute_names = array();
		$attribute_data  = self::transform_attributes(
			$attributes,
			$attribute_names,
			$disabled,
			$name,
			$block,
			false,
			$block_attributes
		);

		// Examples.
		if (
			isset( Build::blocks()[ $name ]->example['attributes'] ) &&
			( $editor || $is_preview )
		) {
			foreach (
				Build::blocks()[ $name ]->example['attributes']
				as $k => $v
			) {
				if (
					isset( $v['blockstudio'] ) &&
					isset( $v['type'] ) &&
					'image' === $v['type']
				) {
					$files       = array();
					$index       = 0;
					$index_total = 0;
					foreach ( range( 1, $v['amount'] ?? 1 ) as $i ) {
						++$index_total;
						++$index;
						if ( 12 === $index ) {
							$index = 1;
						}
						$files[] = self::get_attachment_data(
							null,
							BLOCKSTUDIO_DIR .
								'/includes/examples/images/' .
								$index .
								'.svg',
							$index_total
						);
					}
					$attributes[ $k ] = $files;
				} elseif ( $is_preview ) {
					$attributes[ $k ] = $v;
				}
			}
		}

		unset( $attributes['blockstudio'] );

		foreach ( $attributes as $k => $v ) {
			if (
				! in_array( $k, $attribute_names, true ) &&
				false === strpos( $k, '__size' )
			) {
				unset( $attributes[ $k ] );
			} else {
				unset( $block[ $k ] );
			}
		}

		return $attribute_data;
	}

	/**
	 * Native render.
	 *
	 * @since 2.4.0
	 *
	 * @param array  $attributes   The block attributes.
	 * @param string $inner_blocks The inner blocks content.
	 * @param mixed  $wp_block     The WordPress block instance.
	 * @param string $content      The block content.
	 *
	 * @return string|false|null The rendered block or false/null on failure.
	 * @throws ErrorException When rendering fails.
	 */
	public static function render(
		$attributes,
		$inner_blocks = '',
		$wp_block = '',
		$content = ''
	) {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Reading query params for render mode detection.
		$is_inline_editor =
			isset( $_GET['blockstudioEditor'] ) &&
			'true' === $_GET['blockstudioEditor'];
		$is_editor        =
			isset( $_GET['blockstudioMode'] ) &&
			'editor' === $_GET['blockstudioMode'];
		$is_preview       =
			isset( $_GET['blockstudioMode'] ) &&
			'preview' === $_GET['blockstudioMode'];

		$post_id   = isset( $_GET['postId'] )
			? intval( $_GET['postId'] )
			: get_the_ID();
		$object_id = isset( $_GET['postId'] )
			? intval( $_GET['postId'] )
			: get_queried_object_id();
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		$name =
			$attributes['blockstudio']['name'] ??
			( $wp_block->parsed_block['blockName'] ?? false );
		if ( ! $name ) {
			return false;
		}

		++self::$count;
		if ( ! isset( self::$count_by_block[ $name ] ) ) {
			self::$count_by_block[ $name ] = 1;
		} else {
			++self::$count_by_block[ $name ];
		}

		$extension_attributes = array();
		$matches              = Extensions::get_matches( $name, Build::extensions() );
		if ( count( $matches ) >= 1 ) {
			foreach ( $matches as $match ) {
				foreach ( $match->attributes as $key => $value ) {
					if ( $value['field'] ?? false ) {
						$extension_attributes[ $key ] = $value;
					}
				}
			}
		}

		$blockstudio_id    = self::comment( $name );
		$block_data        = Build::data()[ $name ];
		$data              = Build::blocks()[ $name ] ?? false;
		$override_data     = Build::overrides()[ $name ] ?? false;
		$has_override_path =
			$override_data &&
			isset( $override_data->path ) &&
			Files::get_render_template( $override_data->path );
		$path              =
			$has_override_path && isset( $override_data->path )
				? Files::get_render_template( $override_data->path )
				: $data->path ?? false;

		if ( ! $path ) {
			return null;
		}

		$editor = $attributes['blockstudio']['editor'] ?? false;
		if ( $editor && ( $data->name ?? false ) ) {
			$data->blockstudioEditor = true; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
		}

		$block = $attributes;
		unset( $block['blockstudio'] );
		unset( $block['__internalWidgetId'] );
		$block['id']         = self::id( $block, $attributes );
		$block['name']       = $name;
		$block['postId']     = $object_id;
		$block['postType']   = get_post_type( $object_id );
		$block['index']      = self::$count_by_block[ $name ];
		$block['indexTotal'] = self::$count;

		$compiled_context = array();
		$block_names      = array_keys( Build::blocks() );
		// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- WordPress block API property.
		foreach ( $data->usesContext ?? array() as $context_provider ) {
			if ( ! in_array( $context_provider, $block_names, true ) ) {
				continue;
			}

			if ( $block['_BLOCKSTUDIO_CONTEXT'][ $context_provider ] ?? false ) {
				$trace_attributes                      = array(
					'blockstudio' => array(
						'attributes' =>
							$block['_BLOCKSTUDIO_CONTEXT'][ $context_provider ]['attributes'],
					),
				);
				$attribute_data                        = self::transform(
					$trace_attributes,
					$block,
					$context_provider,
					$editor,
					$is_preview,
					Build::blocks()[ $context_provider ]->attributes
				);
				$compiled_context[ $context_provider ] = $trace_attributes;
			} else {
				$stack_trace = debug_backtrace(); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_debug_backtrace

				foreach ( $stack_trace as $trace ) {
					$trace_name = $trace['object']->block_type->name ?? '';
					if ( $trace_name === $context_provider ) {
						$trace_attributes                      = $trace['object']->attributes;
						$attribute_data                        = self::transform(
							$trace_attributes,
							$block,
							$context_provider,
							$editor,
							$is_preview,
							Build::blocks()[ $context_provider ]->attributes
						);
						$compiled_context[ $context_provider ] = $trace_attributes;
					}
				}
			}
		}

		$block['context'] =
			$block['_BLOCKSTUDIO_CONTEXT'] ?? ( $wp_block->context ?? array() );

		unset( $block['_BLOCKSTUDIO_CONTEXT'] );

		$context = $compiled_context;

		$attribute_data = self::transform(
			$attributes,
			$block,
			$name,
			$editor,
			$is_preview,
			Build::blocks()[ $name ]->attributes + $extension_attributes
		);
		$assets         = Assets::render_code_field_assets( $attribute_data, 'assetsAsset' );

		$filter_data = $data;
		if ( $filter_data ) {
			$filter_data->blockstudio['data']['block']      = $block;
			$filter_data->blockstudio['data']['context']    = $context;
			$filter_data->blockstudio['data']['attributes'] = $attributes;
			$filter_data->blockstudio['data']['path']       = $path;
			$filter_data->blockstudio['data']['blade']      =
				Build::blade()[ $block_data['instance'] ] ?? array();
		}

		if (
			0 === substr_compare( $path, '.twig', -strlen( '.twig' ) ) &&
			class_exists( 'Timber\Site' )
		) {
			Timber::init();
			$twig_context = Timber::context();

			$twig_context['attributes'] = $attributes;
			$twig_context['a']          = $attributes;
			$twig_context['block']      = $block;
			$twig_context['b']          = $block;
			$twig_context['context']    = $context;
			$twig_context['c']          = $context;
			$twig_context['content']    = $content;
			$twig_context['isEditor']   = $is_editor;
			$twig_context['isPreview']  = $is_preview;
			$twig_context['postId']     = $post_id;
			$twig_context['post_id']    = $post_id;

			$add_custom_path = function ( $paths ) use (
				$has_override_path,
				$override_data,
				$data
			) {
				if ( ! isset( $paths[0] ) ) {
					$paths[0] = array();
				}
				$paths[0][] = dirname( $data->path );
				if ( $has_override_path ) {
					$paths[0][] = dirname( $override_data->path );
				}

				return $paths;
			};

			add_filter( 'timber/locations', $add_custom_path );

			try {
				// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Reading local template file.
				$template_content = $editor ? $editor : file_get_contents( $path );
				$compiled_string  = Timber::compile_string(
					$is_inline_editor
						? get_transient(
							'blockstudio_gutenberg_' . $name . '_index.twig'
						)
						: $template_content,
					$twig_context
				);
			} catch ( Throwable $e ) {
				$previous_error = $e->getPrevious();
				if (
					$previous_error &&
					str_starts_with(
						$e->getMessage(),
						'An exception has been thrown during the rendering'
					)
				) {
					$e = $previous_error;
				}

				// phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Constructing exception for error handling.
				throw new ErrorException(
					$e->getMessage(),
					$e->getCode() ?? 0,
					$e instanceof ErrorException ? $e->getSeverity() : E_ERROR,
					$e->getFile(),
					$e->getLine()
				);
				// phpcs:enable WordPress.Security.EscapeOutput.ExceptionNotEscaped
			}

			$render = self::replace_components(
				$compiled_string,
				$inner_blocks,
				$is_editor || $is_preview,
				$data,
				$attributes,
				$block,
				$attribute_data
			);

			$rendered_block =
				( '' !== trim( $render ?? '' ) ? $blockstudio_id : '' ) .
				( $is_preview ? Assets::get_preview_assets( $block_data ) : '' ) .
				$render .
				( $is_preview ? Assets::get_preview_assets( $block_data, false ) : '' );

			remove_filter( 'timber/locations', $add_custom_path );
		} else {
			ob_start();
			$a = $attributes;
			$b = $block;
			$c = $context;

			$render = true;

			if ( $editor ) {
				@eval( ' ?>' . $editor . '<?php ' ); // phpcs:ignore Squiz.PHP.Eval.Discouraged, WordPress.PHP.NoSilencedErrors.Discouraged
			} else {
				ob_start();
				$render = trim( include $path );
				ob_end_clean();

				if ( $is_preview ) {
					echo Assets::get_preview_assets( $block_data ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				}
				$is_inline_editor
					? @eval( // phpcs:ignore Squiz.PHP.Eval.Discouraged, WordPress.PHP.NoSilencedErrors.Discouraged
						' ?>' .
							get_transient(
								'blockstudio_gutenberg_' . $name . '_index.php'
							) .
							'<?php '
					)
					: include $path;
				if ( $is_preview ) {
					echo Assets::get_preview_assets( $block_data, false ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				}
			}

			$rendered_block =
				( '' !== $render ? $blockstudio_id : '' ) .
				self::replace_components(
					ob_get_clean(),
					$inner_blocks,
					$is_editor || $is_preview,
					$filter_data,
					$attributes,
					$block,
					$attribute_data
				);
		}

		$rendered_block = $rendered_block . ( ! $is_editor ? $assets : '' );

		return apply_filters(
			'blockstudio/blocks/render',
			$rendered_block,
			$filter_data,
			$is_editor,
			$is_preview
		);
	}
}
