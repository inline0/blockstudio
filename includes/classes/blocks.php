<?php
/**
 * Blocks class.
 *
 * @package Blockstudio
 */

namespace Blockstudio;

use WP_Block_Parser;

/**
 * Gutenberg block editor integration and script enqueuing.
 *
 * This class handles the JavaScript side of Blockstudio in the block editor,
 * enqueuing scripts and providing initial block render data.
 *
 * Responsibilities:
 *
 * 1. Script Enqueuing:
 *    - Enqueues the main blocks/index.tsx.js script
 *    - Provides blockstudio and blockstudioAdmin global objects
 *    - Includes nonces, REST URLs, and admin data
 *
 * 2. Initial Block Rendering:
 *    - Parses current post content to find Blockstudio blocks
 *    - Pre-renders each block server-side for instant display
 *    - Provides blockstudioBlocks object with { rendered, block } data
 *
 * 3. CSS Class/Variable Injection:
 *    - Reads cssClasses and cssVariables settings
 *    - Provides style handles to the editor for class autocomplete
 *
 * Global Objects Provided:
 *
 * window.blockstudio:
 * - nonce: AJAX nonce
 * - nonceRest: REST API nonce
 * - rest: REST API base URL
 * - blockstudioBlocks: Pre-rendered block data as ordered array
 *
 * window.blockstudioAdmin:
 * - All data from Admin::data(false)
 * - styles: All enqueued stylesheets
 * - cssClasses: Handles for class extraction
 * - cssVariables: Handles for CSS variable extraction
 *
 * @since 1.0.0
 */
class Blocks {

	/**
	 * Flag to prevent recursive calls when getting assets.
	 *
	 * @var bool
	 */
	private static bool $getting_assets = false;

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'enqueue_block_editor_assets', array( $this, 'enqueue_editor_assets' ) );
	}

	/**
	 * Enqueue block editor assets.
	 *
	 * @return void
	 */
	public function enqueue_editor_assets(): void {
		global $post;

		Field_Type_Registry::instance()->enqueue_editor_assets();

		$block_scripts = include BLOCKSTUDIO_DIR . '/includes/admin/assets/blocks/index.tsx.asset.php';
		wp_enqueue_script(
			'blockstudio-blocks',
			BLOCKSTUDIO_URL . 'includes/admin/assets/blocks/index.tsx.js',
			$block_scripts['dependencies'],
			$block_scripts['version'],
			true
		);

		if ( Admin::is_capturing_assets() ) {
			return;
		}

		$blockstudio_blocks = array();
		$media_ids          = array();
		$blocks             = Build::blocks();
		$block_names        = array_keys( $blocks );

		$parser = new WP_Block_Parser();

		$content       = $this->get_content( $post );
		$parsed_blocks = $parser->parse( $content );
		$visited_refs  = array();

		$block_renderer = function ( $block ) use (
			&$block_renderer,
			&$blockstudio_blocks,
			&$media_ids,
			$blocks,
			$block_names,
			$parser,
			&$visited_refs
		) {
			if (
				'core/block' === $block['blockName'] &&
				isset( $block['attrs']['ref'] )
			) {
				$ref = absint( $block['attrs']['ref'] );

				if ( $ref && ! isset( $visited_refs[ $ref ] ) ) {
					$visited_refs[ $ref ] = true;
					$referenced_block     = get_post( $ref );

					if (
						$referenced_block &&
						'wp_block' === $referenced_block->post_type &&
						! empty( $referenced_block->post_content )
					) {
						$referenced_blocks = $parser->parse( $referenced_block->post_content );

						foreach ( $referenced_blocks as $referenced_inner_block ) {
							$block_renderer( $referenced_inner_block );
						}
					}
				}
			}

			if ( in_array( $block['blockName'], $block_names, true ) ) {
				$media_ids = array_replace(
					$media_ids,
					$this->get_media_ids_from_block( $block, $blocks[ $block['blockName'] ] ?? null )
				);

				// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Setting mode for rendering.
				$_GET['blockstudioMode'] = 'editor';

				$blockstudio_blocks[] = array(
					'rendered'   => render_block( $block ),
					'blockName'  => $block['blockName'],
					'attributes' => $block['attrs'],
					'mode'       => 'editor',
				);
			}
			if ( count( $block['innerBlocks'] ) > 0 ) {
				foreach ( $block['innerBlocks'] as $inner_block ) {
					$block_renderer( $inner_block );
				}
			}
		};

		foreach ( $parsed_blocks as $block ) {
			$block_renderer( $block );
		}

		$localize_array = array(
			'nonce'             => wp_create_nonce( 'ajax-nonce' ),
			'nonceRest'         => wp_create_nonce( 'wp_rest' ),
			'rest'              => esc_url_raw( rest_url() ),
			'blockstudioBlocks' => $blockstudio_blocks,
			'media'             => $this->get_media_data( $media_ids ),
		);

		wp_localize_script(
			'blockstudio-blocks',
			'blockstudio',
			$localize_array
		);

		// Build styles data for CSS classes and variables autocomplete.
		// Use flag to prevent infinite recursion (get_all_assets triggers enqueue_block_editor_assets).
		$styles        = array();
		$css_classes   = array();
		$css_variables = array();

		$chosen_css_class_styles = Settings::get( 'blockEditor/cssClasses' );
		$chosen_css_vars_styles  = Settings::get( 'blockEditor/cssVariables' );
		$needs_asset_capture     =
			( is_array( $chosen_css_class_styles ) && count( $chosen_css_class_styles ) > 0 ) ||
			( is_array( $chosen_css_vars_styles ) && count( $chosen_css_vars_styles ) > 0 );

		if ( $needs_asset_capture && ! self::$getting_assets ) {
			self::$getting_assets = true;

			$all_assets = Admin::get_all_assets();
			$styles     = $all_assets['styles'];

			if ( is_array( $chosen_css_class_styles ) && count( $chosen_css_class_styles ) > 0 ) {
				foreach ( $styles as $key => $style ) {
					if ( ! in_array( $key, $chosen_css_class_styles, true ) ) {
						continue;
					}

					$css_classes[] = $key;
				}
			}

			if ( is_array( $chosen_css_vars_styles ) && count( $chosen_css_vars_styles ) > 0 ) {
				foreach ( $styles as $key => $style ) {
					if ( ! in_array( $key, $chosen_css_vars_styles, true ) ) {
						continue;
					}

					$css_variables[] = $key;
				}
			}

			self::$getting_assets = false;
		}

		wp_localize_script(
			'blockstudio-blocks',
			'blockstudioAdmin',
			array_merge(
				Admin::data( false ),
				apply_filters( 'blockstudio/blocks/conditions', array() ),
				array(
					'styles'       => $styles,
					'cssClasses'   => $css_classes,
					'cssVariables' => $css_variables,
				)
			)
		);
	}

	/**
	 * Get content for parsing blocks.
	 *
	 * @param object|null $post The post object.
	 *
	 * @return string The post content.
	 */
	private function get_content( ?object $post ): string {
		if ( $post && ! empty( $post->post_content ) ) {
			return $post->post_content;
		}

		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Reading query params for template detection.
		if (
			isset( $_GET['p'] ) &&
			isset( $_GET['canvas'] ) &&
			'edit' === $_GET['canvas']
		) {
			$template_path = sanitize_text_field( wp_unslash( $_GET['p'] ) );

			if ( str_starts_with( $template_path, '/wp_template/' ) ) {
				$template_id = substr( $template_path, strlen( '/wp_template/' ) );
				$template    = get_block_template( $template_id );
				if ( $template && ! empty( $template->content ) ) {
					return $template->content;
				}
			}
		}
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		return '';
	}

	/**
	 * Get attachment IDs referenced by Blockstudio media fields in a parsed block.
	 *
	 * @param array       $block Parsed block data.
	 * @param object|null $block_type Registered block type.
	 *
	 * @return array<int, int> Attachment IDs keyed by ID.
	 */
	private function get_media_ids_from_block( array $block, ?object $block_type ): array {
		if ( ! $block_type || empty( $block_type->blockstudio['attributes'] ) ) {
			return array();
		}

		$values = $block['attrs']['blockstudio']['attributes'] ?? array();

		if ( ! is_array( $values ) ) {
			return array();
		}

		return $this->get_media_ids_from_attributes(
			$values,
			$block_type->blockstudio['attributes']
		);
	}

	/**
	 * Collect attachment IDs from attribute values using Blockstudio field definitions.
	 *
	 * @param array  $values Saved attribute values.
	 * @param array  $fields Blockstudio field definitions.
	 * @param string $prefix Field ID prefix for grouped attributes.
	 *
	 * @return array<int, int> Attachment IDs keyed by ID.
	 */
	private function get_media_ids_from_attributes( array $values, array $fields, string $prefix = '' ): array {
		$ids = array();

		foreach ( $fields as $field ) {
			if ( ! is_array( $field ) ) {
				continue;
			}

			$field_type   = $field['type'] ?? '';
			$attributes   = $field['attributes'] ?? array();
			$has_children = is_array( $attributes ) && ! empty( $attributes );

			if ( 'group' === $field_type && $has_children ) {
				$group_prefix = $prefix;

				if ( ! empty( $field['id'] ) ) {
					$group_prefix .= $field['id'] . '_';
				}

				$ids = array_replace(
					$ids,
					$this->get_media_ids_from_attributes( $values, $attributes, $group_prefix )
				);
				continue;
			}

			if ( 'tabs' === $field_type && ! empty( $field['tabs'] ) && is_array( $field['tabs'] ) ) {
				foreach ( $field['tabs'] as $tab ) {
					if ( ! empty( $tab['attributes'] ) && is_array( $tab['attributes'] ) ) {
						$ids = array_replace(
							$ids,
							$this->get_media_ids_from_attributes( $values, $tab['attributes'], $prefix )
						);
					}
				}
				continue;
			}

			if ( empty( $field['id'] ) ) {
				continue;
			}

			$field_id     = $prefix . $field['id'];
			$field_exists = array_key_exists( $field_id, $values );

			if ( 'files' === $field_type && $field_exists ) {
				$this->add_media_ids_from_value( $values[ $field_id ], $ids );
				continue;
			}

			if (
				'attributes' === $field_type &&
				! empty( $field['media'] ) &&
				$field_exists &&
				is_array( $values[ $field_id ] )
			) {
				foreach ( $values[ $field_id ] as $attribute_pair ) {
					if ( is_array( $attribute_pair ) && isset( $attribute_pair['data']['media'] ) ) {
						$this->add_media_ids_from_value( $attribute_pair['data']['media'], $ids );
					}
				}
			}

			if (
				'repeater' === $field_type &&
				$has_children &&
				$field_exists &&
				is_array( $values[ $field_id ] )
			) {
				foreach ( $values[ $field_id ] as $row ) {
					if ( is_array( $row ) ) {
						$ids = array_replace(
							$ids,
							$this->get_media_ids_from_attributes( $row, $attributes )
						);
					}
				}
			}
		}

		return $ids;
	}

	/**
	 * Add attachment IDs from scalar, array, or media-object-like values.
	 *
	 * @param mixed $value Attribute value.
	 * @param array $ids Attachment IDs keyed by ID.
	 *
	 * @return void
	 */
	private function add_media_ids_from_value( $value, array &$ids ): void {
		if ( is_array( $value ) ) {
			if ( isset( $value['id'] ) ) {
				$this->add_media_ids_from_value( $value['id'], $ids );
				return;
			}

			foreach ( $value as $item ) {
				$this->add_media_ids_from_value( $item, $ids );
			}

			return;
		}

		if ( is_object( $value ) && isset( $value->id ) ) {
			$this->add_media_ids_from_value( $value->id, $ids );
			return;
		}

		if ( ! is_scalar( $value ) || ! is_numeric( $value ) ) {
			return;
		}

		$id = absint( $value );

		if ( $id > 0 ) {
			$ids[ $id ] = $id;
		}
	}

	/**
	 * Get REST-shaped attachment data for initial editor media hydration.
	 *
	 * @param array<int, int> $ids Attachment IDs keyed by ID.
	 *
	 * @return array<int, array<string, mixed>> Attachment data keyed by ID.
	 */
	private function get_media_data( array $ids ): array {
		$ids = array_values(
			array_unique(
				array_filter(
					array_map( 'absint', $ids )
				)
			)
		);

		if ( empty( $ids ) ) {
			return array();
		}

		$attachments = get_posts(
			array(
				'post_type'      => 'attachment',
				'post_status'    => 'inherit',
				'post__in'       => $ids,
				'posts_per_page' => count( $ids ),
				'orderby'        => 'post__in',
			)
		);

		if ( empty( $attachments ) ) {
			return array();
		}

		$media = array();

		foreach ( $attachments as $attachment ) {
			$mime_type = get_post_mime_type( $attachment );

			$media[ $attachment->ID ] = array(
				'id'         => $attachment->ID,
				'alt_text'   => get_post_meta( $attachment->ID, '_wp_attachment_image_alt', true ),
				'media_type' => $mime_type ? strtok( $mime_type, '/' ) : '',
				'mime_type'  => $mime_type,
				'slug'       => $attachment->post_name,
				'source_url' => wp_get_attachment_url( $attachment->ID ),
			);
		}

		return $media;
	}
}

new Blocks();
