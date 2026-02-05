<?php
/**
 * Media text renderer trait.
 *
 * @package Blockstudio
 */

namespace Blockstudio;

use DOMElement;

/**
 * Renders core/media-text blocks.
 */
trait Media_Text_Renderer {

	/**
	 * Render core/media-text block.
	 *
	 * @param DOMElement  $element The element.
	 * @param array       $attrs   The attributes.
	 * @param Html_Parser $parser  The parser instance.
	 *
	 * @return array The block array.
	 */
	public function render_media_text( DOMElement $element, array $attrs, Html_Parser $parser ): array {
		$inner_blocks = $parser->parse_children( $element );

		// DOMDocument lowercases attribute names; remap camelCase attributes.
		$camel_map = array(
			'mediaurl'      => 'mediaUrl',
			'mediatype'     => 'mediaType',
			'mediaid'       => 'mediaId',
			'medialink'     => 'mediaLink',
			'mediawidth'    => 'mediaWidth',
			'mediaposition' => 'mediaPosition',
		);

		foreach ( $camel_map as $lower => $camel ) {
			if ( isset( $attrs[ $lower ] ) ) {
				$attrs[ $camel ] = $attrs[ $lower ];
				unset( $attrs[ $lower ] );
			}
		}

		if ( isset( $attrs['mediaWidth'] ) && is_numeric( $attrs['mediaWidth'] ) ) {
			$attrs['mediaWidth'] = (int) $attrs['mediaWidth'];
		}

		$class = 'wp-block-media-text is-stacked-on-mobile';
		if ( isset( $attrs['mediaPosition'] ) && 'right' === $attrs['mediaPosition'] ) {
			$class .= ' has-media-on-the-right';
		}

		$media_url  = isset( $attrs['mediaUrl'] ) ? $attrs['mediaUrl'] : '';
		$media_type = isset( $attrs['mediaType'] ) ? $attrs['mediaType'] : 'image';

		$media_id = isset( $attrs['mediaId'] ) ? (int) $attrs['mediaId'] : 0;

		$figure = '<figure class="wp-block-media-text__media">';
		if ( 'video' === $media_type ) {
			$figure .= '<video controls src="' . esc_attr( $media_url ) . '"></video>';
		} else {
			$img_class = '';
			if ( $media_id ) {
				$img_class = 'wp-image-' . $media_id . ' size-full';
			}
			$figure .= '<img src="' . esc_attr( $media_url ) . '" alt=""' . ( $img_class ? ' class="' . esc_attr( $img_class ) . '"' : '' ) . '/>';
		}
		$figure .= '</figure>';

		$content = array( '<div class="' . $class . '">' . $figure . '<div class="wp-block-media-text__content">' );
		foreach ( $inner_blocks as $block ) {
			$content[] = null;
		}
		$content[] = '</div></div>';

		return array(
			'blockName'    => 'core/media-text',
			'attrs'        => $attrs,
			'innerBlocks'  => $inner_blocks,
			'innerHTML'    => '<div class="' . $class . '">' . $figure . '<div class="wp-block-media-text__content"></div></div>',
			'innerContent' => $content,
		);
	}
}
