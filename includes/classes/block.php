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
	 * Reset counters that are scoped to one rendered request.
	 *
	 * @return void
	 */
	public static function reset_request_state(): void {
		self::$count          = 0;
		self::$count_by_block = array();
	}

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
				$options_map[ self::option_map_key( $option['value'] ) ] = array(
					'value' => $value,
					'label' => $option['label'] ?? $value,
				);
			} elseif ( ! $fetch && is_scalar( $option ) ) {
				$options_map[ self::option_map_key( $option ) ] = array(
					'value' => $option,
					'label' => $option,
				);
			}
		}

		try {
			$key = self::option_map_key( $v['value'] ?? $v );
			if ( 'label' === $return_format ) {
				return $options_map[ $key ]['label'] ?? false;
			}
			if ( 'both' === $return_format ) {
				return $options_map[ $key ] ?? false;
			}

			return $options_map[ $key ]['value'] ?? false;
		} catch ( Throwable $err ) {
			return false;
		}
	}

	/**
	 * Normalize option values before using them as array keys.
	 *
	 * PHP casts float keys to integers, which loses precision for values such
	 * as `1.5` and emits deprecation warnings on modern runtimes.
	 *
	 * @param mixed $value Option value.
	 *
	 * @return int|string Array key.
	 */
	private static function option_map_key( mixed $value ): int|string {
		if ( is_int( $value ) || is_string( $value ) ) {
			return $value;
		}

		if ( is_float( $value ) ) {
			return (string) $value;
		}

		if ( is_bool( $value ) ) {
			return $value ? 1 : 0;
		}

		return (string) $value;
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
	 * Resolve a bracket-notation attribute path from nested arrays.
	 *
	 * Converts paths like "items[0].content" into the corresponding value
	 * from the attributes array.
	 *
	 * @since 7.1.0
	 *
	 * @param string $path       The attribute path (e.g., "items[0].content").
	 * @param array  $attributes The block attributes.
	 *
	 * @return mixed The resolved value, or null if not found.
	 */
	public static function resolve_attribute_path( $path, $attributes ) {
		$parts   = preg_split( '/[\[\]\.]+/', $path, -1, PREG_SPLIT_NO_EMPTY );
		$current = $attributes;

		foreach ( $parts as $part ) {
			if ( is_array( $current ) && array_key_exists( $part, $current ) ) {
				$current = $current[ $part ];
			} elseif ( is_array( $current ) && is_numeric( $part ) && array_key_exists( (int) $part, $current ) ) {
				$current = $current[ (int) $part ];
			} else {
				return null;
			}
		}

		return $current;
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

			$attr_key    = $attribute_map['attribute'] ?? null;
			$attribute   = ( $attr_key && false !== strpos( $attr_key, '[' ) )
				? self::resolve_attribute_path( $attr_key, $block_attributes )
				: ( $block_attributes[ $attr_key ] ?? null );
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
					'blockstudio/blocks/components/rich_text/render',
					$attribute,
					$block
				);
				// Backwards compatibility.
				$rich_text_content = apply_filters(
					'blockstudio/blocks/components/richtext/render',
					$rich_text_content,
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
				) ?? $content;
			} else {
				$inner_blocks_content = apply_filters(
					'blockstudio/blocks/components/inner_blocks/render',
					$replace,
					$block
				);
				// Backwards compatibility.
				$inner_blocks_content = apply_filters(
					'blockstudio/blocks/components/innerblocks/render',
					$inner_blocks_content,
					$block
				);
				$wrap                 = apply_filters(
					'blockstudio/blocks/components/inner_blocks/frontend/wrap',
					true,
					$block
				);
				// Backwards compatibility.
				$wrap    = apply_filters(
					'blockstudio/blocks/components/innerblocks/frontend/wrap',
					$wrap,
					$block
				);
				$content = preg_replace(
					$regex,
					$wrap
						? "<$element_tag $attr>" .
							$inner_blocks_content .
							"</$element_tag>"
						: $inner_blocks_content,
					$content
				) ?? $content;
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
		$content = preg_replace( $regex, '', $content ) ?? $content;
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
		if ( ! $is_editor_or_preview
			&& false === strpos( $content, '<InnerBlocks' )
			&& false === strpos( $content, '<RichText' )
			&& false === strpos( $content, '<MediaPlaceholder' )
			&& false === strpos( $content, 'useBlockProps' )
		) {
			return $content;
		}

		if ( $is_editor_or_preview ) {
			if (
				class_exists( 'WP_HTML_Tag_Processor' ) &&
				false !== strpos( $content, 'useBlockProps' )
			) {
				$processor = new WP_HTML_Tag_Processor( $content );
				if ( $processor->next_tag() ) {
					$classes = (string) $processor->get_attribute( 'class' );

					$wrapper_attributes = apply_filters(
						'blockstudio/blocks/components/use_block_props/render',
						'class="' . esc_attr( $classes ) . '"',
						$block
					);
					$wrapper_attributes = apply_filters(
						'blockstudio/blocks/components/useblockprops/render',
						$wrapper_attributes,
						$block
					);

					if ( preg_match( '/class="([^"]*)"/', $wrapper_attributes, $matches ) ) {
						$classes = html_entity_decode( $matches[1], ENT_QUOTES, 'UTF-8' );
					}

					$processor->set_attribute(
						'class',
						$classes . ' wp-block block-editor-block-list__block'
					);
				}
				$content = $processor->get_updated_html();
			}

			$content = self::expand_inner_blocks_allowed_blocks( $content );

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

		if ( false !== strpos( $content, '<RichText' ) ) {
			preg_match_all(
				'/<RichText\s+[^>]*attribute=["\']([^"\']*\[[^"\']*)["\'][^>]*\/?>/s',
				$content,
				$remaining_rich_texts
			);

			if ( ! empty( $remaining_rich_texts[1] ) ) {
				foreach ( $remaining_rich_texts[1] as $rich_text_path ) {
					self::replace_custom_tag(
						$content,
						$inner_blocks,
						$block,
						$attributes,
						'RichText',
						$rich_text_path
					);
				}
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

		$has_attribute = self::content_has_component_cleanup_attribute( $content, $attributes_to_remove );

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

					$wrapper_attributes = apply_filters(
						'blockstudio/blocks/components/use_block_props/render',
						get_block_wrapper_attributes(
							array(
								'class' => $classes,
								'id'    =>
									$attributes_block['anchor'] ??
									$element->getAttribute( 'id' ),
							)
						),
						$block
					);
					// Backwards compatibility.
					$wrapper_attributes = apply_filters(
						'blockstudio/blocks/components/useblockprops/render',
						$wrapper_attributes,
						$block
					);
					$attributes         = array();
					preg_match_all(
						'/(\S+)="([^"]+)"/',
						$wrapper_attributes,
						$attributes,
						PREG_SET_ORDER
					);

					foreach ( $attributes as $attribute ) {
						$element->setAttribute( $attribute[1], $attribute[2] );
					}

					// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only feature toggle, no state change.
					if ( ! $is_editor_or_preview && isset( $_GET['blockstudio-devtools'] ) && Settings::get_bool( 'dev/grab/enabled', false ) && current_user_can( 'edit_posts' ) ) {
						$block_path = $block->blockstudio['data']['path'] ?? '';
						if ( $block_path ) {
							$element->setAttribute( 'data-blockstudio-path', $block_path );
						}
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
			$html           = $doc->saveHTML();
			$trim_off_front = strpos( $html, '<body>' ) + 6;
			$trim_off_end   = strrpos( $html, '</body>' ) - strlen( $html );

			$content = substr( $html, $trim_off_front, $trim_off_end );
		}

		return $content;
	}

	/**
	 * Check whether rendered component HTML still contains cleanup attributes.
	 *
	 * A broad substring scan makes common words like "stage" match the `tag`
	 * cleanup attribute and forces DOMDocument parsing for blocks that no
	 * longer contain component attributes after RichText/InnerBlocks handling.
	 *
	 * @param string $content    Rendered content.
	 * @param array  $attributes Attribute names to remove.
	 *
	 * @return bool Whether DOM cleanup is required.
	 */
	private static function content_has_component_cleanup_attribute( string $content, array $attributes ): bool {
		if ( array() === $attributes ) {
			return false;
		}

		$attribute_pattern = implode(
			'|',
			array_map(
				static fn( string $attribute ): string => preg_quote( $attribute, '/' ),
				$attributes
			)
		);

		return 1 === preg_match( '/\s(?:' . $attribute_pattern . ')(?:\s*=\s*|(?=\s|\/?>))/i', $content );
	}

	/**
	 * Expand supported InnerBlocks allowedBlocks tokens.
	 *
	 * @param array $list Allowed block names and tokens.
	 *
	 * @return array Expanded block names.
	 */
	public static function expand_allowed_blocks_tokens( array $list ): array {
		$expanded = array();

		foreach ( $list as $entry ) {
			if ( ! is_string( $entry ) ) {
				continue;
			}

			$entry = trim( $entry );
			if ( '' === $entry ) {
				continue;
			}

			$resolved = self::resolve_allowed_blocks_token( $entry );
			$names    = null === $resolved ? array( $entry ) : $resolved;

			foreach ( $names as $name ) {
				if ( is_string( $name ) && '' !== $name && ! in_array( $name, $expanded, true ) ) {
					$expanded[] = $name;
				}
			}
		}

		return $expanded;
	}

	/**
	 * Expand allowedBlocks JSON attributes on editor InnerBlocks tags.
	 *
	 * @param string $content Rendered editor handoff content.
	 *
	 * @return string Content with expanded allowedBlocks attributes.
	 */
	private static function expand_inner_blocks_allowed_blocks( string $content ): string {
		if ( false === strpos( $content, 'allowedBlocks' ) || false === strpos( $content, '<InnerBlocks' ) ) {
			return $content;
		}

		return preg_replace_callback(
			'/<InnerBlocks\b[^>]*>/i',
			static function ( array $matches ): string {
				$tag = $matches[0];

				if ( ! preg_match( '/\ballowedBlocks\s*=\s*([\'"])(.*?)\1/s', $tag, $attribute_match ) ) {
					return $tag;
				}

				$quote   = $attribute_match[1];
				$raw     = $attribute_match[2];
				$decoded = html_entity_decode( $raw, ENT_QUOTES, 'UTF-8' );
				$list    = json_decode( $decoded, true );

				if ( ! is_array( $list ) ) {
					return $tag;
				}

				$expanded = self::expand_allowed_blocks_tokens( $list );
				if ( $expanded === $list ) {
					return $tag;
				}

				$replacement = 'allowedBlocks=' . $quote . esc_attr( wp_json_encode( $expanded ) ) . $quote;

				return preg_replace( '/\ballowedBlocks\s*=\s*([\'"])(.*?)\1/s', $replacement, $tag, 1 ) ?? $tag;
			},
			$content
		) ?? $content;
	}

	/**
	 * Resolve a single allowedBlocks token.
	 *
	 * @param string $token Token or literal block name.
	 *
	 * @return array|null Expanded names, or null when the token is a literal.
	 */
	private static function resolve_allowed_blocks_token( string $token ): ?array {
		if ( '@theme' === $token ) {
			return self::get_theme_allowed_block_names();
		}

		if ( str_starts_with( $token, 'category:' ) ) {
			$category = substr( $token, strlen( 'category:' ) );
			if ( '' === $category ) {
				return array();
			}

			return self::get_allowed_block_names_by_category( $category );
		}

		if ( str_ends_with( $token, '/*' ) ) {
			$namespace = substr( $token, 0, -2 );
			if ( ! preg_match( '/^[a-z][a-z0-9-]*$/', $namespace ) ) {
				return array();
			}

			return self::get_allowed_block_names_by_namespace( $namespace );
		}

		return null;
	}

	/**
	 * Get all registered block names in a namespace.
	 *
	 * @param string $namespace Block namespace.
	 *
	 * @return array Block names.
	 */
	private static function get_allowed_block_names_by_namespace( string $namespace ): array {
		return array_values(
			array_map(
				static fn( array $block ): string => $block['name'],
				array_filter(
					self::get_registered_allowed_blocks(),
					static fn( array $block ): bool => str_starts_with( $block['name'], $namespace . '/' )
				)
			)
		);
	}

	/**
	 * Get all registered block names in a category.
	 *
	 * @param string $category Block category.
	 *
	 * @return array Block names.
	 */
	private static function get_allowed_block_names_by_category( string $category ): array {
		return array_values(
			array_map(
				static fn( array $block ): string => $block['name'],
				array_filter(
					self::get_registered_allowed_blocks(),
					static fn( array $block ): bool => $category === $block['category']
				)
			)
		);
	}

	/**
	 * Get all active-theme Blockstudio block names.
	 *
	 * @return array Block names.
	 */
	private static function get_theme_allowed_block_names(): array {
		$theme_roots = array_filter(
			array_unique(
				array_map(
					'wp_normalize_path',
					array(
						get_stylesheet_directory(),
						get_template_directory(),
					)
				)
			)
		);

		return array_values(
			array_map(
				static fn( array $block ): string => $block['name'],
				array_filter(
					self::get_registered_allowed_blocks(),
					static function ( array $block ) use ( $theme_roots ): bool {
						if ( '' === $block['path'] ) {
							return false;
						}

						foreach ( $theme_roots as $theme_root ) {
							if ( str_starts_with( $block['path'], trailingslashit( $theme_root ) ) || $block['path'] === $theme_root ) {
								return true;
							}
						}

						return false;
					}
				)
			)
		);
	}

	/**
	 * Return normalized registered block metadata for allowedBlocks expansion.
	 *
	 * @return array<int, array{name:string, category:string, path:string}>
	 */
	private static function get_registered_allowed_blocks(): array {
		$items = array();
		$data  = Build::data();

		foreach ( Build::blocks() as $name => $block ) {
			self::add_registered_allowed_block( $items, (string) $name, $block, $data[ $name ] ?? array() );
		}

		$registry = \WP_Block_Type_Registry::get_instance();
		if ( method_exists( $registry, 'get_all_registered' ) ) {
			foreach ( $registry->get_all_registered() as $name => $block ) {
				self::add_registered_allowed_block( $items, (string) $name, $block, $data[ $name ] ?? array() );
			}
		}

		return array_values( $items );
	}

	/**
	 * Add normalized block data if it has not already been added.
	 *
	 * @param array  $items Block metadata map, passed by reference.
	 * @param string $name  Block name.
	 * @param mixed  $block Block object or array.
	 * @param array  $data  Blockstudio build data.
	 *
	 * @return void
	 */
	private static function add_registered_allowed_block( array &$items, string $name, mixed $block, array $data = array() ): void {
		if ( '' === $name || isset( $items[ $name ] ) ) {
			return;
		}

		$items[ $name ] = array(
			'name'     => $name,
			'category' => self::get_registered_block_category( $block, $data ),
			'path'     => self::get_registered_block_path( $block, $data ),
		);
	}

	/**
	 * Get a block category from any known registry shape.
	 *
	 * @param mixed $block Block object or array.
	 * @param array $data  Blockstudio build data.
	 *
	 * @return string Category slug.
	 */
	private static function get_registered_block_category( mixed $block, array $data ): string {
		if ( is_object( $block ) ) {
			$category = (string) ( $block->category ?? ( $block->blockstudio['category'] ?? '' ) );
			return '' === $category ? (string) ( $data['category'] ?? '' ) : $category;
		}

		if ( is_array( $block ) ) {
			$category = (string) ( $block['category'] ?? ( $block['blockstudio']['category'] ?? '' ) );
			return '' === $category ? (string) ( $data['category'] ?? '' ) : $category;
		}

		return (string) ( $data['category'] ?? '' );
	}

	/**
	 * Get a block source path from any known registry shape.
	 *
	 * @param mixed $block Block object or array.
	 * @param array $data  Blockstudio build data.
	 *
	 * @return string Normalized path.
	 */
	private static function get_registered_block_path( mixed $block, array $data ): string {
		$path = '';

		if ( is_object( $block ) ) {
			$path = $block->blockstudio['data']['path']
				?? $block->blockstudio['path']
				?? $block->file['dirname']
				?? '';
		} elseif ( is_array( $block ) ) {
			$path = $block['blockstudio']['data']['path']
				?? $block['blockstudio']['path']
				?? $block['file']['dirname']
				?? '';
		}

		if ( '' === $path ) {
			$path = $data['path'] ?? ( $data['file']['dirname'] ?? '' );
		}

		return '' === $path ? '' : wp_normalize_path( $path );
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
			$att = self::attribute_definition( (string) $k, $repeater, $block_attributes );

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
					self::transform_option_attribute(
						$attributes,
						(string) $k,
						$v,
						$att,
						$type,
						$return_format,
						$populate
					);
				}

				if ( 'files' === $type ) {
					self::transform_files_attribute(
						$attributes,
						(string) $k,
						$v,
						$att,
						$return_format,
						$disabled
					);
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
					self::transform_code_attribute(
						$attributes,
						(string) $k,
						$v,
						$att,
						$selector_attribute,
						$attribute_data
					);
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

		self::resolve_block_field_references( $attributes, $repeater, $block_attributes );

		return array(
			'assets'              => $attribute_data['assets'] ?? array(),
			'assetsAsset'         => $attribute_data['assetsAsset'] ?? array(),
			'selectorAttribute'   => $selector_attribute,
			'selectorAttributeId' => $selector_attribute_id,
			'hasCodeSelector'     => $attribute_data['hasCodeSelector'] ?? false,
		);
	}

	/**
	 * Put back the identity comment and assets a render filter dropped.
	 *
	 * A filter that replaces the output wholesale loses both, and without the
	 * identity comment the block stops being addressable downstream.
	 *
	 * @param string $result         The filtered output.
	 * @param string $blockstudio_id The block identity comment.
	 * @param mixed  $assets         The rendered asset markup.
	 * @param string $island_phase   The island render phase.
	 *
	 * @return string The output with both restored.
	 */
	private static function restore_block_markers(
		string $result,
		string $blockstudio_id,
		$assets,
		string $island_phase
	): string {
		if ( ! str_contains( $result, $blockstudio_id ) ) {
			$result = $blockstudio_id . $result;
		}

		if (
			'fragment' !== $island_phase &&
			is_string( $assets ) &&
			'' !== $assets &&
			! str_contains( $result, $assets )
		) {
			$result .= $assets;
		}

		return $result;
	}

	/**
	 * Collect the field attributes every extension adds to a block.
	 *
	 * @param string $name       The block name.
	 * @param array  $extensions All registered extensions.
	 *
	 * @return array The attributes keyed by name.
	 */
	private static function extension_attributes( string $name, array $extensions ): array {
		$extension_attributes = array();

		foreach ( Extensions::get_matches( $name, $extensions ) as $match ) {
			foreach ( $match->attributes as $key => $value ) {
				if ( $value['field'] ?? false ) {
					$extension_attributes[ $key ] = $value;
				}
			}
		}

		return $extension_attributes;
	}

	/**
	 * Resolve every source file a block render reads.
	 *
	 * @param string $path       The resolved template path.
	 * @param array  $block_data The block registry entry.
	 *
	 * @return array<int, string> The unique dependency paths.
	 */
	private static function render_dependencies( string $path, array $block_data ): array {
		$dependencies = array( $path );

		foreach ( $block_data['filesPaths'] ?? array() as $dependency_path ) {
			if ( is_string( $dependency_path ) && '' !== $dependency_path ) {
				$dependencies[] = $dependency_path;
			}
		}

		foreach ( $block_data['assets'] ?? array() as $asset ) {
			$dependency_path = is_array( $asset ) ? $asset['path'] ?? '' : '';

			if ( is_string( $dependency_path ) && '' !== $dependency_path ) {
				$dependencies[] = $dependency_path;
			}
		}

		return array_values( array_unique( $dependencies ) );
	}

	/**
	 * Compile the attributes of every block this one takes context from.
	 *
	 * A provider reached through `_BLOCKSTUDIO_CONTEXT` is compiled directly.
	 * Anything else is only reachable through the render call stack, which is
	 * why the fallback walks a backtrace looking for the provider's own block
	 * instance.
	 *
	 * @param object $data       The block type.
	 * @param array  $block      The block data (passed by reference).
	 * @param array  $blocks     All registered blocks.
	 * @param mixed  $editor     The editor template, or false.
	 * @param bool   $is_preview Whether this is a preview render.
	 *
	 * @return array The compiled context, keyed by provider name.
	 */
	private static function compile_uses_context(
		$data,
		array &$block,
		array $blocks,
		$editor,
		bool $is_preview
	): array {
		$compiled_context = array();

		// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- WordPress block API property.
		foreach ( $data->usesContext ?? array() as $context_provider ) {
			if ( ! isset( $blocks[ $context_provider ] ) ) {
				continue;
			}

			if ( $block['_BLOCKSTUDIO_CONTEXT'][ $context_provider ] ?? false ) {
				$trace_attributes = array(
					'blockstudio' => array(
						'attributes' =>
							$block['_BLOCKSTUDIO_CONTEXT'][ $context_provider ]['attributes'],
					),
				);

				self::transform(
					$trace_attributes,
					$block,
					$context_provider,
					$editor,
					$is_preview,
					$blocks[ $context_provider ]->attributes
				);

				$compiled_context[ $context_provider ] = $trace_attributes;

				continue;
			}

			$stack_trace = debug_backtrace(); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_debug_backtrace

			foreach ( $stack_trace as $trace ) {
				$trace_name = $trace['object']->block_type->name ?? '';

				if ( $trace_name !== $context_provider ) {
					continue;
				}

				$trace_attributes = $trace['object']->attributes;

				self::transform(
					$trace_attributes,
					$block,
					$context_provider,
					$editor,
					$is_preview,
					$blocks[ $context_provider ]->attributes
				);

				$compiled_context[ $context_provider ] = $trace_attributes;
			}
		}

		return $compiled_context;
	}

	/**
	 * Make an interactive block usable inside the editor canvas.
	 *
	 * The editor hydrates from markup alone, so server state has to travel
	 * with it. Only array and object values are embedded: scalars are usually
	 * computed by JS getters and injecting them would overwrite those.
	 *
	 * @param string $rendered_block The rendered block markup.
	 * @param array  $block_data     The block registry entry.
	 *
	 * @return string The markup with server state and inline scripts attached.
	 */
	private static function prepare_editor_interactivity(
		string $rendered_block,
		array $block_data
	): string {
		$rendered_block = wp_interactivity_process_directives( $rendered_block );

		if ( preg_match( '/data-wp-interactive="([^"]+)"/', $rendered_block, $m ) ) {
			$ns    = $m[1];
			$state = array_filter(
				wp_interactivity_state( $ns ),
				fn( $v ) => is_array( $v )
			);

			if ( ! empty( $state ) ) {
				$encoded        = esc_attr( wp_json_encode( $state ) );
				$rendered_block = preg_replace(
					'/data-wp-interactive="' . preg_quote( $ns, '/' ) . '"/',
					'data-wp-interactive="' . $ns . '" data-wp-server-state="' . $encoded . '"',
					$rendered_block,
					1
				);
			}
		}

		return $rendered_block . Assets::get_preview_assets( $block_data, false );
	}

	/**
	 * Resolve a code value and collect the assets it contributes.
	 *
	 * `%selector%` resolves to the block's own attribute selector, so a code
	 * field can scope its own CSS without knowing the instance ID.
	 *
	 * @param array  $attributes         The attributes (passed by reference).
	 * @param string $key                The attribute key.
	 * @param mixed  $value              The stored value.
	 * @param array  $att                The attribute definition.
	 * @param string $selector_attribute The block's selector attribute.
	 * @param array  $attribute_data     The attribute data (passed by reference).
	 *
	 * @return void
	 */
	private static function transform_code_attribute(
		array &$attributes,
		string $key,
		$value,
		array $att,
		string $selector_attribute,
		array &$attribute_data
	): void {
		$lang           = $att['language'];
		$replaced_value = str_replace( '%selector%', "[$selector_attribute]", $value );

		if ( str_contains( $value, '%selector%' ) ) {
			$attribute_data['hasCodeSelector'] = true;
		}

		if ( 'css' === $lang || 'scss' === $lang || 'javascript' === $lang ) {
			$asset_data = array(
				'language' => $lang,
				'value'    => $replaced_value,
			);

			$attribute_data['assets'][] = $asset_data;

			if ( $att['asset'] ?? false ) {
				$attribute_data['assetsAsset'][] = $asset_data;
			}
		}

		$attributes[ $key ] = $replaced_value;
	}

	/**
	 * Transform a select, radio, checkbox, or token value.
	 *
	 * @param array  $attributes    The attributes (passed by reference).
	 * @param string $key           The attribute key.
	 * @param mixed  $value         The stored value.
	 * @param array  $att           The attribute definition.
	 * @param string $type          The field type.
	 * @param string $return_format The return format.
	 * @param array  $populate      The populate configuration.
	 *
	 * @return void
	 */
	private static function transform_option_attribute(
		array &$attributes,
		string $key,
		$value,
		array $att,
		string $type,
		string $return_format,
		array $populate
	): void {
		if (
			'select' === $type &&
			isset( $populate['type'] ) &&
			'fetch' === $populate['type']
		) {
			$attributes[ $key ] = $value;

			return;
		}

		$is_multiple_option =
			'checkbox' === $type ||
			( 'select' === $type && ( $att['multiple'] ?? false ) );

		if ( ( 'select' === $type || 'radio' === $type ) && ! $is_multiple_option ) {
			$attributes[ $key ] = self::get_option_value(
				$att,
				$return_format,
				$value,
				$populate
			);
		}

		if ( $is_multiple_option ) {
			$new_values = array();

			foreach ( $value as $l ) {
				$val = self::get_option_value( $att, $return_format, $l, $populate );

				if ( $val ) {
					$new_values[] = $val;
				}
			}

			if ( 'checkbox' === $type ) {
				self::sort_option_values( $new_values, $att, $return_format );
			}

			$attributes[ $key ] = array_values( $new_values );
		}

		if ( 'token' === $type && 'both' !== $return_format ) {
			$new_values = array();

			foreach ( $value as $l ) {
				$new_values[] = $l[ $return_format ] ?? $l;
			}

			$attributes[ $key ] = $new_values;
		}
	}

	/**
	 * Restore a checkbox selection to the order its options declare.
	 *
	 * Selections arrive in the order the editor stored them. Post and term
	 * objects sort by their identifier, everything else by the option value or
	 * label the field declares.
	 *
	 * @param array  $values        The resolved option values (passed by reference).
	 * @param array  $att           The attribute definition.
	 * @param string $return_format The return format.
	 *
	 * @return void
	 */
	private static function sort_option_values(
		array &$values,
		array $att,
		string $return_format
	): void {
		$is_id   = isset( $values[0]->ID );
		$is_term = isset( $values[0]->term_id );

		if ( $is_id || $is_term ) {
			$key         = $is_id ? 'ID' : 'term_id';
			$sorting_arr = array_column( $att['options'], 'value' );

			uasort(
				$values,
				function ( $a, $b ) use ( $key, $sorting_arr ) {
					return array_search(
						$a->{$key} ?? ( $a['value'] ?? $a ),
						$sorting_arr,
						true
					) <=> array_search(
						$b->{$key} ?? ( $b['value'] ?? $b ),
						$sorting_arr,
						true
					);
				}
			);

			return;
		}

		if ( isset( $att['options'][0]['label'] ) && 'label' === $return_format ) {
			$sorting_arr = array_column( $att['options'], 'label' );
		} elseif ( isset( $att['options'][0]['value'] ) ) {
			$sorting_arr = array_column( $att['options'], 'value' );
		} else {
			$sorting_arr = $att['options'];
		}

		uasort(
			$values,
			function ( $a, $b ) use ( $sorting_arr ) {
				return array_search( $a['value'] ?? $a, $sorting_arr, true )
					<=> array_search( $b['value'] ?? $b, $sorting_arr, true );
			}
		);
	}

	/**
	 * Transform a files value into the shape its return format asks for.
	 *
	 * @param array  $attributes    The attributes (passed by reference).
	 * @param string $key           The attribute key.
	 * @param mixed  $value         The stored value.
	 * @param array  $att           The attribute definition.
	 * @param string $return_format The return format.
	 * @param array  $disabled      The disabled attribute keys.
	 *
	 * @return void
	 */
	private static function transform_files_attribute(
		array &$attributes,
		string $key,
		$value,
		array $att,
		string $return_format,
		array $disabled
	): void {
		if ( is_array( $value ) ) {
			foreach ( $value as $file_id ) {
				if ( in_array( $key . '_' . $file_id, $disabled, true ) ) {
					$attributes[ $key ] = array_filter(
						$attributes[ $key ],
						fn( $val ) => $val !== $file_id
					);
				}
			}

			$attributes[ $key ] = array_values( $attributes[ $key ] );
		} elseif ( in_array( $key . '_' . $value, $disabled, true ) ) {
			$attributes[ $key ] = false;
		}

		$size = $attributes[ $key . '__size' ] ?? 'full';

		if ( 'id' !== $return_format && 'url' !== $return_format ) {
			if ( is_array( $attributes[ $key ] ) ) {
				$object_array = array();

				foreach ( $attributes[ $key ] as $o ) {
					$object_array[] = self::get_attachment_data( $o, false, 0, $size );
				}

				$attributes[ $key ] = $object_array;
			} elseif ( $attributes[ $key ] ) {
				$attributes[ $key ] = self::get_attachment_data(
					$attributes[ $key ],
					false,
					0,
					$size
				);
			}
		}

		if ( 'url' === $return_format ) {
			$media = fn( $id, $media_size ) => wp_attachment_is( 'image', $id )
				? wp_get_attachment_image_src( $id, $media_size )[0] ?? false
				: wp_get_attachment_url( $id ) ?? false;

			if ( is_array( $attributes[ $key ] ) ) {
				$url_array = array();

				foreach ( $attributes[ $key ] as $o ) {
					$url_array[] = $media( $o, $att['returnSize'] );
				}

				$attributes[ $key ] = $url_array;
			} elseif ( $attributes[ $key ] ) {
				$attributes[ $key ] = $media( $attributes[ $key ], $att['returnSize'] );
			}

			if ( ( $att['multiple'] ?? false ) && ! is_array( $attributes[ $key ] ) ) {
				$attributes[ $key ] = array( $attributes[ $key ] );
			}
		}

		if (
			$attributes[ $key ] &&
			( $att['multiple'] ?? false ) &&
			( $attributes[ $key ]['ID'] ?? ( is_numeric( $attributes[ $key ] ) ?? false ) )
		) {
			$attributes[ $key ] = array( $attributes[ $key ] );
		}
	}

	/**
	 * Find the definition for one attribute key.
	 *
	 * Repeater rows carry their definitions as a list keyed by an `id`
	 * property, everything else is keyed by name.
	 *
	 * @param string     $key              The attribute key.
	 * @param array|bool $repeater         The repeater attributes, or false.
	 * @param array      $block_attributes The block attributes.
	 *
	 * @return array|false The attribute definition.
	 */
	private static function attribute_definition( string $key, $repeater, array $block_attributes ) {
		if ( ! $repeater ) {
			return $block_attributes[ $key ] ?? false;
		}

		return array_values(
			array_filter(
				$repeater,
				fn( $item ) => ( $item['id'] ?? false ) === $key
			)
		)[0] ?? false;
	}

	/**
	 * Resolve block field references once every attribute is transformed.
	 *
	 * A block field renders another block with the sibling attributes that
	 * share its ID structure, so it can only run after those siblings hold
	 * their final values.
	 *
	 * @param array      $attributes       The attributes (passed by reference).
	 * @param array|bool $repeater         The repeater attributes, or false.
	 * @param array      $block_attributes The block attributes.
	 *
	 * @return void
	 */
	private static function resolve_block_field_references(
		array &$attributes,
		$repeater,
		array $block_attributes
	): void {
		foreach ( $attributes as $k => $v ) {
			$att = self::attribute_definition( (string) $k, $repeater, $block_attributes );

			if ( ! $att || empty( $att['_blockField'] ) ) {
				continue;
			}

			$default_block_name = $att['_blockName'] ?? '';
			$override_key       = $k . '_block';
			$ref_block_name     = ! empty( $attributes[ $override_key ] )
				? $attributes[ $override_key ]
				: $default_block_name;
			$block_ids          = $att['_blockIds'] ?? array();
			$id_structure       = $att['_idStructure'] ?? '{id}';
			$return_format      = $att['returnFormat'] ?? 'rendered';

			if ( ! $ref_block_name ) {
				$attributes[ $k ] = false;
				continue;
			}

			$block_data = array();

			if ( $ref_block_name !== $default_block_name || empty( $block_ids ) ) {
				$prefix = str_replace( '{id}', '', $id_structure );
				foreach ( $attributes as $ak => $av ) {
					if ( str_starts_with( $ak, $prefix ) && $ak !== $k && $ak !== $override_key ) {
						$original_id = substr( $ak, strlen( $prefix ) );

						$block_data[ $original_id ] = $av;
					}
				}
			} else {
				foreach ( $block_ids as $mapped_id => $original_id ) {
					$block_data[ $original_id ] = $attributes[ $mapped_id ] ?? false;
				}
			}

			if ( empty( $block_data ) ) {
				$attributes[ $k ] = false;
				continue;
			}

			if ( 'data' === $return_format ) {
				$attributes[ $k ] = $block_data;
				continue;
			}

			ob_start();
			Render::block(
				array(
					'name' => $ref_block_name,
					'data' => $block_data,
				)
			);
			$rendered = ob_get_clean();

			if ( 'both' === $return_format ) {
				$attributes[ $k ] = array(
					'rendered' => $rendered,
					'data'     => $block_data,
				);
			} else {
				$attributes[ $k ] = $rendered;
			}
		}
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
			if ( 'repeater' === ( $v['field'] ?? '' ) && ! isset( $v['default'] ) && ! empty( $v['min'] ) ) {
				$row = array();
				foreach ( $v['attributes'] ?? array() as $inner ) {
					$id = $inner['id'] ?? null;
					if ( $id ) {
						$row[ $id ] = $inner['default'] ?? false;
					}
				}
				$attr[ $k ] = array_fill( 0, (int) $v['min'], $row );
			} else {
				$attr[ $k ] = $v['default'] ?? false;
			}
		}
		$attributes = array_merge(
			$attr,
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
		$is_editor  =
			isset( $_GET['blockstudioMode'] ) &&
			'editor' === $_GET['blockstudioMode'];
		$is_preview =
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

		$raw_blockstudio_attributes =
			isset( $attributes['blockstudio']['attributes'] ) &&
			is_array( $attributes['blockstudio']['attributes'] )
				? $attributes['blockstudio']['attributes']
				: array_filter(
					$attributes,
					static fn( $key ) => ! in_array( $key, array( 'blockstudio', '_BLOCKSTUDIO_CONTEXT' ), true ),
					ARRAY_FILTER_USE_KEY
				);

		$perf_start = Perf::active() ? microtime( true ) : 0;

		++self::$count;
		if ( ! isset( self::$count_by_block[ $name ] ) ) {
			self::$count_by_block[ $name ] = 1;
		} else {
			++self::$count_by_block[ $name ];
		}

		$blocks               = Build::blocks();
		$block_data_map       = Build::data();
		$overrides            = Build::overrides();
		$blade                = Build::blade();
		$extensions           = Build::extensions();
		$extension_attributes = self::extension_attributes( $name, $extensions );

		$blockstudio_id    = self::comment( $name );
		$block_data        = $block_data_map[ $name ];
		$data              = $blocks[ $name ] ?? false;
		$override_data     = $overrides[ $name ] ?? false;
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

		$island_phase = Islands::render_phase( $name, $is_editor, $is_preview );
		if ( 'placeholder' === $island_phase ) {
			$placeholder_path = Islands::placeholder_path( $name, $block_data );
			if ( $placeholder_path ) {
				$path = $placeholder_path;
			}
		}

		$dependencies = self::render_dependencies( $path, $block_data );

		/**
		 * Filters the resolved dependencies for a rendered block.
		 *
		 * Collectors can observe the returned paths to build a page dependency
		 * graph, and integrations can append files loaded indirectly.
		 *
		 * @param array<int, string> $dependencies Resolved source and template paths.
		 * @param string             $name         Block name.
		 * @param array              $block_data   Block definition data.
		 * @param bool               $is_editor    Whether this is an editor render.
		 * @param bool               $is_preview   Whether this is a preview render.
		 */
		apply_filters( 'blockstudio/render/dependencies', $dependencies, $name, $block_data, $is_editor, $is_preview );

		$editor = $attributes['blockstudio']['editor'] ?? false;

		$block = $attributes;
		unset( $block['blockstudio'] );
		unset( $block['__internalWidgetId'] );
		$block['id']                  = self::id( $block, $attributes );
		$block['name']                = $name;
		$block['postId']              = $object_id;
		$block['postType']            = get_post_type( $object_id );
		$block['index']               = self::$count_by_block[ $name ];
		$block['indexTotal']          = self::$count;
		$block['islandPhase']         = $island_phase;
		$block['isIsland']            = in_array( $island_phase, array( 'hydrate', 'placeholder', 'fragment' ), true );
		$block['isIslandPlaceholder'] = 'placeholder' === $island_phase;
		$block['isIslandFragment']    = 'fragment' === $island_phase;

		$compiled_context = self::compile_uses_context(
			$data,
			$block,
			$blocks,
			$editor,
			$is_preview
		);

		$block['context'] =
			$block['_BLOCKSTUDIO_CONTEXT'] ?? ( $wp_block->context ?? array() );

		unset( $block['_BLOCKSTUDIO_CONTEXT'] );

		$context = $compiled_context;

		$perf_phase = Perf::active() ? microtime( true ) : 0;

		$attribute_data = self::transform(
			$attributes,
			$block,
			$name,
			$editor,
			$is_preview,
			$blocks[ $name ]->attributes + $extension_attributes
		);
		$assets         = Assets::render_code_field_assets( $attribute_data, 'assetsAsset' );

		if ( $perf_phase ) {
			Perf::track( 'phase:transform', ( microtime( true ) - $perf_phase ) * 1000 );
		}

		$filter_data = $data;
		if ( $filter_data ) {
			$filter_data->blockstudio['data']['block']      = $block;
			$filter_data->blockstudio['data']['context']    = $context;
			$filter_data->blockstudio['data']['attributes'] = $attributes;
			$filter_data->blockstudio['data']['path']       = $path;
			$filter_data->blockstudio['data']['blade']      =
				$blade[ $block_data['instance'] ] ?? array();
		}

		if (
			0 === substr_compare( $path, '.twig', -strlen( '.twig' ) ) &&
			class_exists( 'Timber\Site' )
		) {
			if ( $perf_phase ) {
				$perf_phase = microtime( true );
			}

			Timber::init();
			$twig_context = Timber::context();

			$twig_context['attributes']          = $attributes;
			$twig_context['a']                   = $attributes;
			$twig_context['block']               = $block;
			$twig_context['b']                   = $block;
			$twig_context['context']             = $context;
			$twig_context['c']                   = $context;
			$twig_context['content']             = $content;
			$twig_context['isEditor']            = $is_editor;
			$twig_context['isPreview']           = $is_preview;
			$twig_context['isIsland']            = $block['isIsland'];
			$twig_context['isIslandPlaceholder'] = $block['isIslandPlaceholder'];
			$twig_context['isIslandFragment']    = $block['isIslandFragment'];
			$twig_context['islandPhase']         = $island_phase;
			$twig_context['postId']              = $post_id;
			$twig_context['post_id']             = $post_id;

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
					$template_content,
					$twig_context
				);

				if ( $perf_phase ) {
					Perf::track( 'phase:twig', ( microtime( true ) - $perf_phase ) * 1000 );
					$perf_phase = microtime( true );
				}
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

			if ( Block_Tags::output_has_tags( $compiled_string ) ) {
				$compiled_string = Block_Tags::render( $compiled_string );
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

			if ( $perf_phase ) {
				Perf::track( 'phase:components', ( microtime( true ) - $perf_phase ) * 1000 );
			}

			$rendered_block =
				( '' !== trim( $render ) ? $blockstudio_id : '' ) .
				( $is_preview ? Assets::get_preview_assets( $block_data ) : '' ) .
				$render .
				( $is_preview ? Assets::get_preview_assets( $block_data, false ) : '' );

			remove_filter( 'timber/locations', $add_custom_path );
		} else {
			if ( $perf_phase ) {
				$perf_phase = microtime( true );
			}

			ob_start();
			$a                   = $attributes;
			$b                   = $block;
			$c                   = $context;
			$isEditor            = $is_editor; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.VariableNotSnakeCase -- backward-compatible template alias.
			$isPreview           = $is_preview; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.VariableNotSnakeCase -- backward-compatible template alias.
			$isIsland            = $block['isIsland']; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.VariableNotSnakeCase -- template alias.
			$isIslandPlaceholder = $block['isIslandPlaceholder']; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.VariableNotSnakeCase -- template alias.
			$isIslandFragment    = $block['isIslandFragment']; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.VariableNotSnakeCase -- template alias.
			$islandPhase         = $island_phase; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.VariableNotSnakeCase -- template alias.

			$render = true;

			if ( $editor ) {
				@eval( ' ?>' . $editor . '<?php ' ); // phpcs:ignore Squiz.PHP.Eval.Discouraged, WordPress.PHP.NoSilencedErrors.Discouraged
			} else {
				if ( $is_preview ) {
					echo Assets::get_preview_assets( $block_data ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				}
				$render = include $path;
				$render = trim( (string) $render );
				if ( $is_preview ) {
					echo Assets::get_preview_assets( $block_data, false ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				}
			}

			$php_output = ob_get_clean();

			if ( $perf_phase ) {
				Perf::track( 'phase:template', ( microtime( true ) - $perf_phase ) * 1000 );
				$perf_phase = microtime( true );
			}

			if ( Block_Tags::output_has_tags( $php_output ) ) {
				$php_output = Block_Tags::render( $php_output );
			}

			$rendered_block =
				( '' !== $render ? $blockstudio_id : '' ) .
				self::replace_components(
					$php_output,
					$inner_blocks,
					$is_editor || $is_preview,
					$filter_data,
					$attributes,
					$block,
					$attribute_data
				);

			if ( $perf_phase ) {
				Perf::track( 'phase:components', ( microtime( true ) - $perf_phase ) * 1000 );
			}
		}

		if ( ! $is_editor && 'fragment' !== $island_phase ) {
			$rendered_block .= $assets;
		}

		if ( $is_editor && str_contains( $rendered_block, 'data-wp-interactive' ) ) {
			$rendered_block = self::prepare_editor_interactivity( $rendered_block, $block_data );
		}

		if ( $perf_start ) {
			Perf::track( 'block:' . $name, ( microtime( true ) - $perf_start ) * 1000 );
		}

		$result = apply_filters(
			'blockstudio/blocks/render',
			$rendered_block,
			$filter_data,
			$is_editor,
			$is_preview
		);

		// If a filter replaced the output and it still has pseudo-components,
		// resolve them (supports external template engines like Blade).
		if (
			$result !== $rendered_block
			&& is_string( $result )
			&& ( str_contains( $result, '<RichText' )
				|| str_contains( $result, '<InnerBlocks' )
				|| str_contains( $result, '<MediaPlaceholder' )
				|| str_contains( $result, 'useBlockProps' ) )
		) {
			$result = self::replace_components(
				$result,
				$inner_blocks,
				$is_editor || $is_preview,
				$filter_data,
				$attributes,
				$block,
				$attribute_data
			);
		}

		if (
			! $is_editor &&
			is_string( $result ) &&
			$result !== $rendered_block &&
			'' !== trim( $result )
		) {
			$result = self::restore_block_markers(
				$result,
				$blockstudio_id,
				$assets,
				$island_phase
			);
		}

		if (
			is_string( $result ) &&
			in_array( $island_phase, array( 'hydrate', 'placeholder' ), true )
		) {
			$result = Islands::marker(
				$name,
				$raw_blockstudio_attributes,
				$result,
				$island_phase
			);
		}

		return $result;
	}
}
