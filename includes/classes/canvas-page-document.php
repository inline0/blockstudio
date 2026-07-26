<?php
/**
 * Contextual Canvas page document rendering.
 *
 * @package Blockstudio
 */

namespace Blockstudio;

/**
 * Renders one Canvas page through its normal frontend WordPress context.
 *
 * Page-specific conditionals, body classes, shortcodes, layouts, and enqueued
 * assets must observe the selected page rather than the REST or admin request
 * that asked Canvas for the document. All temporary request and dependency
 * state is restored after each document.
 *
 * @since 7.6.0
 */
final class Canvas_Page_Document {

	/**
	 * Render one selected page as a complete frontend document.
	 *
	 * @param array              $item             Normalized Canvas page item.
	 * @param array              $document_options Shared Render document options.
	 * @param array<int, string> $block_names      Canonical block names in the source.
	 *
	 * @return array Render document.
	 */
	public static function render( array $item, array $document_options, array $block_names ): array {
		$page       = is_array( $item['page'] ?? null ) ? $item['page'] : array();
		$post       = self::post( $page );
		$route_path = self::route_path( $page, $item );
		$url        = self::url( $page, $route_path );

		$context  = self::with_page_context(
			$post,
			$route_path,
			$url,
			static function () use ( $item, $page, $document_options, $block_names ): array {
				$captured = self::capture_frontend_assets(
					static function () use ( $item, $page, $document_options ): array {
						$content = self::render_content( self::record_string( $item, 'content' ) );
						$content = Pages::render_layout(
							$content,
							$page,
							self::record_string( $item, 'layoutPath' )
						);

						return array(
							'content'     => $content,
							'bodyClasses' => self::body_classes( $document_options['bodyClasses'] ?? array() ),
						);
					}
				);

				$result_options                   = $document_options;
				$result_options['bodyClasses']    = $captured['result']['bodyClasses'];
				$result_options['contentElement'] = $result_options['contentElement'] ?? 'main';
				$result_options['head']           = self::option_string( $result_options, 'head' )
					. $captured['head'];
				$result_options['footer']         = self::option_string( $result_options, 'footer' )
					. $captured['footer'];

				return Render::document_from_html(
					$captured['result']['content'],
					$block_names,
					$result_options
				);
			}
		);
		$document = $context['result'];

		if ( null !== $context['redirect'] ) {
			$document['warnings'][] = array(
				'code'    => 'page_redirect_suppressed',
				'message' => sprintf(
					'Canvas suppressed a %d redirect to "%s" while rendering the selected page.',
					$context['redirect']['status'],
					$context['redirect']['location']
				),
			);
		}

		return $document;
	}

	/**
	 * Run a callback in the selected page's WordPress request context.
	 *
	 * @param object|null $post       Selected post.
	 * @param string      $route_path Selected frontend route.
	 * @param string      $url        Selected frontend URL.
	 * @param callable    $callback Renderer returning a document array.
	 *
	 * @return array{result:array,redirect:array{location:string,status:int}|null} Context result.
	 */
	private static function with_page_context( ?object $post, string $route_path, string $url, callable $callback ): array {
		$get_state       = self::capture_array_state( $_GET, array( 'blockstudioMode', 'postId' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Capturing exact temporary preview state for restoration.
		$global_state    = self::capture_global_state( array( 'post', 'wp_query', 'wp_the_query' ) );
		$server_state    = self::capture_array_state(
			$_SERVER,
			array( 'REQUEST_METHOD', 'REQUEST_URI', 'QUERY_STRING' )
		);
		$redirect        = null;
		$page_uri        = static function ( mixed $uri, mixed $page ) use ( $post, $route_path ): mixed {
			if ( null === $post || '' === $route_path || ! is_object( $page ) ) {
				return $uri;
			}

			return (int) ( $page->ID ?? 0 ) === (int) ( $post->ID ?? 0 )
				? $route_path
				: $uri;
		};
		$wp_redirect     = static function ( mixed $location, mixed $status = 302 ) use ( &$redirect ): string {
			$redirect = array(
				'location' => is_scalar( $location ) ? trim( (string) $location ) : '',
				'status'   => is_numeric( $status ) ? (int) $status : 302,
			);

			return '';
		};
		$filter_page_uri = null !== $post
			&& '' !== $route_path
			&& self::has_host_function( 'add_filter' )
			&& self::has_host_function( 'remove_filter' );
		$filter_redirect = self::has_host_function( 'add_filter' )
			&& self::has_host_function( 'remove_filter' );

		try {
			unset( $_GET['blockstudioMode'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Temporary static page context only.

			if ( null !== $post ) {
				$_GET['postId']          = (string) (int) ( $post->ID ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Temporary static page context only.
				$GLOBALS['post']         = $post; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Frontend conditionals need the selected page.
				$query                   = self::snapshot_query( $post, $route_path, $global_state['wp_query']['value'] ?? null );
				$GLOBALS['wp_query']     = $query; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Frontend conditionals need the selected page query.
				$GLOBALS['wp_the_query'] = $query; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Match WordPress main-query identity.
			} else {
				unset( $_GET['postId'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Prevent ambient post context from leaking into a source-only page.
			}

			self::apply_url_state( $url );

			if ( $filter_page_uri ) {
				add_filter( 'get_page_uri', $page_uri, PHP_INT_MAX, 2 );
			}

			if ( $filter_redirect ) {
				add_filter( 'wp_redirect', $wp_redirect, PHP_INT_MAX, 2 );
			}

			$result = $callback();

			return array(
				'result'   => $result,
				'redirect' => $redirect,
			);
		} finally {
			if ( $filter_page_uri ) {
				remove_filter( 'get_page_uri', $page_uri, PHP_INT_MAX );
			}

			if ( $filter_redirect ) {
				remove_filter( 'wp_redirect', $wp_redirect, PHP_INT_MAX );
			}

			self::restore_array_state( $_GET, $get_state ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Restoring exact temporary preview state.
			self::restore_global_state( $global_state );
			self::restore_array_state( $_SERVER, $server_state );
		}
	}

	/**
	 * Capture the normal frontend enqueue lifecycle in isolated registries.
	 *
	 * @template T of array
	 *
	 * @param callable():T $callback Content renderer.
	 *
	 * @return array{result:T,head:string,footer:string}
	 */
	private static function capture_frontend_assets( callable $callback ): array {
		if ( ! self::has_host_function( 'do_action' ) ) {
			return array(
				'result' => $callback(),
				'head'   => '',
				'footer' => '',
			);
		}

		$global_state = self::capture_global_state( array( 'wp_styles', 'wp_scripts', 'wp_actions' ) );
		$buffer_level = ob_get_level();

		try {
			$GLOBALS['wp_styles']  = self::isolated_dependencies( $global_state['wp_styles']['value'] ?? null ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Each document needs an isolated frontend style registry.
			$GLOBALS['wp_scripts'] = self::isolated_dependencies( $global_state['wp_scripts']['value'] ?? null ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Each document needs an isolated frontend script registry.

			do_action( 'wp_enqueue_scripts' ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Core WordPress frontend lifecycle.
			$result = $callback();
			$head   = self::capture_output(
				static function (): void {
					if ( self::has_host_function( 'wp_print_styles' ) ) {
						wp_print_styles();
					}

					if ( self::has_host_function( 'wp_print_head_scripts' ) ) {
						wp_print_head_scripts();
					}
				}
			);
			$footer = self::capture_output(
				static function (): void {
					if ( self::has_host_function( 'wp_print_footer_scripts' ) ) {
						wp_print_footer_scripts();
					}
				}
			);

			return array(
				'result' => $result,
				'head'   => $head,
				'footer' => $footer,
			);
		} finally {
			while ( ob_get_level() > $buffer_level ) {
				ob_end_clean();
			}

			self::restore_global_state( $global_state );
		}
	}

	/**
	 * Clone a dependency registry without a previous document's queue state.
	 *
	 * @param mixed $dependencies Existing registry.
	 *
	 * @return object|null Isolated registry.
	 */
	private static function isolated_dependencies( mixed $dependencies ): ?object {
		if ( ! is_object( $dependencies ) ) {
			return null;
		}

		$isolated = clone $dependencies;

		foreach ( array( 'queue', 'to_do', 'done', 'groups', 'args' ) as $property ) {
			if ( property_exists( $isolated, $property ) ) {
				$isolated->{$property} = array();
			}
		}

		return $isolated;
	}

	/**
	 * Capture buffered output.
	 *
	 * @param callable():void $callback Printer.
	 *
	 * @return string Output.
	 */
	private static function capture_output( callable $callback ): string {
		ob_start();
		$callback();
		$output = ob_get_clean();

		return false === $output ? '' : $output;
	}

	/**
	 * Run page content through the normal frontend content pipeline.
	 *
	 * Canvas establishes a singular query without entering the main loop, so
	 * the regular Blockstudio layout filter remains inactive here. The layout
	 * is applied exactly once by the caller after blocks, shortcodes, and other
	 * public content filters have rendered.
	 *
	 * @param string $content Source page content.
	 *
	 * @return string Rendered page content.
	 */
	private static function render_content( string $content ): string {
		if ( ! self::has_host_function( 'apply_filters' ) ) {
			return Render::content( $content );
		}

		$rendered = apply_filters( 'the_content', $content ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Core WordPress frontend content pipeline.

		return is_string( $rendered ) ? $rendered : Render::content( $content );
	}

	/**
	 * Merge caller classes with the selected page's normal body classes.
	 *
	 * @param mixed $classes Caller classes.
	 *
	 * @return array<int, string> Classes.
	 */
	private static function body_classes( mixed $classes ): array {
		if ( is_string( $classes ) ) {
			$classes = preg_split( '/\s+/', trim( $classes ) );
		}

		$result = is_array( $classes ) ? $classes : array();

		if ( self::has_host_function( 'get_body_class' ) ) {
			$body_classes = get_body_class();

			if ( is_array( $body_classes ) ) {
				$result = array_merge( $result, $body_classes );
			}
		}

		return array_values(
			array_unique(
				array_filter(
					array_map(
						static fn( mixed $class ): string => is_scalar( $class )
							? trim( (string) $class )
							: '',
						$result
					)
				)
			)
		);
	}

	/**
	 * Resolve the selected page's persisted post.
	 *
	 * @param array $page Page data.
	 *
	 * @return object|null Post.
	 */
	private static function post( array $page ): ?object {
		$post_id = 0;

		foreach ( array( 'post_id', 'postId' ) as $key ) {
			if ( is_numeric( $page[ $key ] ?? null ) ) {
				$post_id = (int) $page[ $key ];
				break;
			}
		}

		if ( $post_id <= 0 || ! self::has_host_function( 'get_post' ) ) {
			return null;
		}

		$post = get_post( $post_id );

		return is_object( $post ) ? $post : null;
	}

	/**
	 * Resolve the selected page's frontend route path.
	 *
	 * @param array $page Page data.
	 * @param array $item Normalized item.
	 *
	 * @return string Route path without surrounding slashes.
	 */
	private static function route_path( array $page, array $item ): string {
		$permalink = self::record_string( $page, 'permalink' );

		if ( '' !== $permalink ) {
			$path = wp_parse_url( $permalink, PHP_URL_PATH );

			if ( is_string( $path ) && '' !== trim( $path, '/' ) ) {
				return trim( $path, '/' );
			}
		}

		foreach ( array( 'path', 'slug', 'name' ) as $key ) {
			$value = self::record_string( $page, $key );

			if ( '' !== trim( $value, '/.' ) ) {
				return trim( $value, '/' );
			}
		}

		return trim( self::record_string( $item, 'slug' ), '/' );
	}

	/**
	 * Resolve the selected page's frontend URL.
	 *
	 * @param array  $page       Page data.
	 * @param string $route_path Route path.
	 *
	 * @return string URL.
	 */
	private static function url( array $page, string $route_path ): string {
		$permalink = self::record_string( $page, 'permalink' );

		if ( '' !== $permalink ) {
			return $permalink;
		}

		return self::has_host_function( 'home_url' )
			? (string) home_url( '/' . trim( $route_path, '/' ) . '/' )
			: '/' . trim( $route_path, '/' ) . '/';
	}

	/**
	 * Determine whether the embedding WordPress host exposes a callable.
	 *
	 * Keeping the public function name behind this runtime boundary prevents
	 * namespace relocation tools from mistaking it for a package-owned symbol.
	 *
	 * @param string $name Public WordPress function name.
	 *
	 * @return bool Whether the host callable is available.
	 */
	private static function has_host_function( string $name ): bool {
		return is_callable( $name );
	}

	/**
	 * Create a temporary main query for the selected post.
	 *
	 * @param object $post           Selected post.
	 * @param string $route_path     Selected route.
	 * @param mixed  $previous_query Previous query.
	 *
	 * @return \WP_Query|\stdClass Query.
	 */
	private static function snapshot_query( object $post, string $route_path, mixed $previous_query ): \WP_Query|\stdClass {
		if ( $previous_query instanceof \WP_Query || $previous_query instanceof \stdClass ) {
			$query = clone $previous_query;
		} else {
			$query = class_exists( 'WP_Query' ) ? new \WP_Query() : new \stdClass();
		}

		$post_type = is_scalar( $post->post_type ?? null ) ? (string) $post->post_type : 'page';
		$is_page   = 'page' === $post_type;
		$post_id   = (int) ( $post->ID ?? 0 );

		$query->post              = $post;
		$query->posts             = array( $post );
		$query->post_count        = 1;
		$query->found_posts       = 1;
		$query->queried_object    = $post;
		$query->queried_object_id = $post_id;
		$query->is_page           = $is_page;
		$query->is_single         = ! $is_page;
		$query->is_singular       = true;
		$query->is_home           = false;
		$query->is_archive        = false;
		$query->is_404            = false;
		$query->in_the_loop       = false;
		$query_vars               = is_array( $query->query_vars ?? null ) ? $query->query_vars : array();
		$query->query_vars        = array_merge(
			$query_vars,
			array(
				'page_id'  => $is_page ? $post_id : 0,
				'pagename' => $is_page ? $route_path : '',
			)
		);

		return $query;
	}

	/**
	 * Apply the selected page's request URL.
	 *
	 * @param string $url URL.
	 *
	 * @return void
	 */
	private static function apply_url_state( string $url ): void {
		$parts = '' !== trim( $url ) ? wp_parse_url( $url ) : false;
		$path  = is_array( $parts ) && isset( $parts['path'] ) ? (string) $parts['path'] : '/';
		$query = is_array( $parts ) && isset( $parts['query'] ) ? (string) $parts['query'] : '';

		$_SERVER['REQUEST_METHOD'] = 'GET';
		$_SERVER['REQUEST_URI']    = $path . ( '' !== $query ? '?' . $query : '' );
		$_SERVER['QUERY_STRING']   = $query;
	}

	/**
	 * Read a string record value.
	 *
	 * @param array  $record Record.
	 * @param string $key    Key.
	 *
	 * @return string Value.
	 */
	private static function record_string( array $record, string $key ): string {
		return is_scalar( $record[ $key ] ?? null ) ? (string) $record[ $key ] : '';
	}

	/**
	 * Read a scalar document option.
	 *
	 * @param array  $options Options.
	 * @param string $key     Key.
	 *
	 * @return string Value.
	 */
	private static function option_string( array $options, string $key ): string {
		return is_scalar( $options[ $key ] ?? null ) ? (string) $options[ $key ] : '';
	}

	/**
	 * Capture selected entries from an array.
	 *
	 * @param array              $source Source.
	 * @param array<int, string> $keys Keys.
	 *
	 * @return array<string, array{exists:bool,value:mixed}> State.
	 */
	private static function capture_array_state( array $source, array $keys ): array {
		$state = array();

		foreach ( $keys as $key ) {
			$state[ $key ] = array(
				'exists' => array_key_exists( $key, $source ),
				'value'  => $source[ $key ] ?? null,
			);
		}

		return $state;
	}

	/**
	 * Restore selected entries to an array.
	 *
	 * @param array                                         $target Target.
	 * @param array<string, array{exists:bool,value:mixed}> $state State.
	 *
	 * @return void
	 */
	private static function restore_array_state( array &$target, array $state ): void {
		foreach ( $state as $key => $entry ) {
			if ( $entry['exists'] ) {
				$target[ $key ] = $entry['value'];
			} else {
				unset( $target[ $key ] );
			}
		}
	}

	/**
	 * Capture selected global entries.
	 *
	 * @param array<int, string> $keys Keys.
	 *
	 * @return array<string, array{exists:bool,value:mixed}> State.
	 */
	private static function capture_global_state( array $keys ): array {
		$state = array();

		foreach ( $keys as $key ) {
			$state[ $key ] = array(
				'exists' => array_key_exists( $key, $GLOBALS ),
				'value'  => $GLOBALS[ $key ] ?? null,
			);
		}

		return $state;
	}

	/**
	 * Restore selected global entries.
	 *
	 * @param array<string, array{exists:bool,value:mixed}> $state State.
	 *
	 * @return void
	 */
	private static function restore_global_state( array $state ): void {
		foreach ( $state as $key => $entry ) {
			if ( $entry['exists'] ) {
				$GLOBALS[ $key ] = $entry['value']; // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Restoring selected WordPress core globals by name.
			} else {
				unset( $GLOBALS[ $key ] );
			}
		}
	}
}
