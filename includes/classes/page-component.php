<?php
/**
 * Component page block.
 *
 * @package Blockstudio
 */

namespace Blockstudio;

use RuntimeException;
use Throwable;
use WP_Block;

/**
 * Renders component pages through their registered page renderer.
 *
 * Page sync stores one `blockstudio/page` block for a page whose definition
 * names a renderer entry. WordPress renders it where `do_blocks()` runs, so
 * the renderer output passes through the same `the_content` filters as any
 * other page content. The block's inner blocks are the page's slot.
 *
 * @since 7.7.11
 */
final class Page_Component {

	/**
	 * Block name.
	 */
	public const BLOCK_NAME = 'blockstudio/page';

	/**
	 * Attribute that marks a page block nested inside another block.
	 */
	private const NESTED_ATTRIBUTE = '__blockstudioNested';

	/**
	 * Post IDs whose component page is rendering.
	 *
	 * @var array<int, true>
	 */
	private static array $rendering = array();

	/**
	 * Register the block and its render hooks.
	 *
	 * @since 7.7.11
	 *
	 * @return void
	 */
	public static function register(): void {
		add_filter( 'render_block_data', array( self::class, 'mark_nested' ), 10, 3 );

		if ( \WP_Block_Type_Registry::get_instance()->is_registered( self::BLOCK_NAME ) ) {
			return;
		}

		register_block_type(
			self::BLOCK_NAME,
			array(
				'api_version'     => 3,
				'title'           => 'Page',
				'category'        => 'design',
				'description'     => 'The component that renders this file-backed page.',
				'render_callback' => array( self::class, 'render' ),
				'uses_context'    => array( 'postId', 'postType' ),
				'attributes'      => array(),
				'supports'        => array(
					'inserter' => false,
					'html'     => false,
					'reusable' => false,
					'lock'     => false,
					'multiple' => false,
				),
			)
		);
	}

	/**
	 * Mark a page block that renders inside another block.
	 *
	 * @param array         $parsed_block Parsed block.
	 * @param array         $source_block Source block.
	 * @param WP_Block|null $parent_block Parent block.
	 *
	 * @return array Parsed block.
	 */
	public static function mark_nested( array $parsed_block, array $source_block = array(), ?WP_Block $parent_block = null ): array {
		if ( self::BLOCK_NAME === ( $parsed_block['blockName'] ?? null ) && null !== $parent_block ) {
			$parsed_block['attrs']                           = is_array( $parsed_block['attrs'] ?? null ) ? $parsed_block['attrs'] : array();
			$parsed_block['attrs'][ self::NESTED_ATTRIBUTE ] = true;
		}

		return $parsed_block;
	}

	/**
	 * Render a component page.
	 *
	 * @since 7.7.11
	 *
	 * @param array         $attributes Block attributes.
	 * @param string        $content    Rendered slot content.
	 * @param WP_Block|null $block      Block instance.
	 *
	 * @throws RuntimeException When the block is nested inside another block.
	 * @throws Throwable When rendering fails outside a REST editor preview.
	 *
	 * @return string Rendered page.
	 */
	public static function render( array $attributes, string $content = '', ?WP_Block $block = null ): string {
		$context = self::request_context();

		try {
			if ( ! empty( $attributes[ self::NESTED_ATTRIBUTE ] ) ) {
				throw new RuntimeException( 'A blockstudio/page block cannot be nested inside another block.' );
			}

			return self::render_post( self::context_post_id( $block ), $content, $context );
		} catch ( Throwable $throwable ) {
			if ( 'rest' !== $context ) {
				throw $throwable;
			}

			return '<div class="blockstudio-page-error" role="alert"><p>' . esc_html( $throwable->getMessage() ) . '</p></div>';
		}
	}

	/**
	 * Render the component page of one post.
	 *
	 * @since 7.7.11
	 *
	 * @param int    $post_id Post ID.
	 * @param string $slot    Rendered slot content.
	 * @param string $context Request context.
	 *
	 * @throws RuntimeException When the post has no component page or rendering fails.
	 *
	 * @return string Rendered page.
	 */
	public static function render_post( int $post_id, string $slot = '', string $context = '' ): string {
		$post_id = self::page_post_id( $post_id );
		$render  = $post_id > 0 ? Page_Sync::stored_render( $post_id ) : null;

		if ( null === $render ) {
			throw new RuntimeException( sprintf( 'Post %d has no component page to render.', absint( $post_id ) ) );
		}

		if ( isset( self::$rendering[ $post_id ] ) ) {
			throw new RuntimeException( sprintf( 'The component page of post %d renders itself.', absint( $post_id ) ) );
		}

		$page = Pages::page_for_post_id( $post_id ) ?? self::page_from_post( $post_id );

		self::$rendering[ $post_id ] = true;

		try {
			$result = Page_Renderers::render(
				array(
					'kind'     => 'page',
					'renderer' => $render['renderer'],
					'entry'    => $render['entry'],
					'data'     => $render['data'],
					'page'     => $page,
					'post_id'  => $post_id,
					'slot'     => $slot,
					'context'  => '' !== $context ? $context : self::request_context(),
				)
			);
		} finally {
			unset( self::$rendering[ $post_id ] );
		}

		$dependencies = array_values(
			array_unique(
				array_merge(
					$result['dependencies'],
					array_filter(
						array(
							is_string( $page['json_path'] ?? null ) ? wp_normalize_path( $page['json_path'] ) : null,
							$render['entry'],
							$render['data'],
						)
					)
				)
			)
		);

		Static_Prerender_Runtime::record_dependencies( $dependencies );

		$html = self::with_assets( 'page-' . self::owner_key( $page, $post_id ), $result['assets'] ) . $result['html'];

		/**
		 * Fires after a component page rendered.
		 *
		 * @since 7.7.11
		 *
		 * @param array    $page         Canonical page data.
		 * @param string   $html         Rendered HTML.
		 * @param string[] $dependencies Files the render read.
		 */
		do_action( 'blockstudio/pages/rendered', $page, $html, $dependencies );

		return $html;
	}

	/**
	 * Prefix rendered HTML with the marker of its page assets.
	 *
	 * @since 7.7.11
	 *
	 * @param string $owner  Asset owner id.
	 * @param array  $assets Renderer assets.
	 *
	 * @return string Asset marker, or an empty string without assets.
	 */
	public static function with_assets( string $owner, array $assets ): string {
		if ( array() === $assets ) {
			return '';
		}

		return Assets::register_owner( $owner, $assets );
	}

	/**
	 * Derive the request context from the request type, never from the visitor.
	 *
	 * @since 7.7.11
	 *
	 * @return string One of frontend, rest, feed or cli.
	 */
	public static function request_context(): string {
		if ( Static_Prerender_Batch_Renderer::is_rendering() ) {
			return 'frontend';
		}

		if ( ( defined( 'REST_REQUEST' ) && REST_REQUEST ) || ( function_exists( 'wp_is_serving_rest_request' ) && wp_is_serving_rest_request() ) ) {
			return 'rest';
		}

		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			return 'cli';
		}

		if ( function_exists( 'is_feed' ) && did_action( 'wp' ) && is_feed() ) {
			return 'feed';
		}

		return 'frontend';
	}

	/**
	 * Read the post ID a page block renders for.
	 *
	 * @param WP_Block|null $block Block instance.
	 *
	 * @return int Post ID.
	 */
	private static function context_post_id( ?WP_Block $block ): int {
		$post_id = $block instanceof WP_Block && isset( $block->context['postId'] ) && is_numeric( $block->context['postId'] )
			? (int) $block->context['postId']
			: 0;

		return $post_id > 0 ? $post_id : (int) get_the_ID();
	}

	/**
	 * Resolve revisions and autosaves to the page post that owns the render definition.
	 *
	 * @param int $post_id Post ID.
	 *
	 * @return int Page post ID.
	 */
	private static function page_post_id( int $post_id ): int {
		if ( $post_id <= 0 ) {
			return 0;
		}

		$parent = wp_is_post_revision( $post_id );

		return false !== $parent ? (int) $parent : $post_id;
	}

	/**
	 * Build page data from stored meta when the registry has no entry for a post.
	 *
	 * @param int $post_id Post ID.
	 *
	 * @return array Page data.
	 */
	private static function page_from_post( int $post_id ): array {
		$collection = (string) get_post_meta( $post_id, '_blockstudio_page_collection', true );
		$meta       = get_post_meta( $post_id, '_blockstudio_page_meta', true );
		$directory  = Page_Paths::from_meta( $post_id, '_blockstudio_page_directory' );

		return array(
			'name'        => (string) get_post_meta( $post_id, '_blockstudio_page_name', true ),
			'key'         => (string) get_post_meta( $post_id, '_blockstudio_page_key', true ),
			'path'        => (string) get_post_meta( $post_id, '_blockstudio_page_path', true ),
			'collection'  => '' !== $collection ? $collection : null,
			'contentType' => 'component',
			'directory'   => '' !== $directory ? $directory : null,
			'render'      => Page_Sync::stored_render( $post_id ),
			'post_id'     => $post_id,
			'meta'        => is_array( $meta ) ? $meta : array(),
		);
	}

	/**
	 * Build a stable asset owner key for a page.
	 *
	 * @param array $page    Page data.
	 * @param int   $post_id Post ID.
	 *
	 * @return string Owner key.
	 */
	private static function owner_key( array $page, int $post_id ): string {
		$key = is_scalar( $page['key'] ?? null ) ? (string) $page['key'] : '';

		return '' !== $key ? $key : (string) $post_id;
	}
}
