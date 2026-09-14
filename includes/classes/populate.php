<?php
/**
 * Populate class.
 *
 * @package Blockstudio
 */

namespace Blockstudio;

/**
 * Dynamic option population for select, checkbox, and radio fields.
 *
 * This class fetches options from WordPress data sources (posts, users,
 * terms) or custom sources to populate choice-type block attributes.
 *
 * Population Types:
 *
 * 1. Query-based (posts, users, terms):
 *    ```json
 *    "populate": {
 *      "type": "query",
 *      "query": "posts",
 *      "arguments": { "post_type": "page", "posts_per_page": -1 }
 *    }
 *    ```
 *
 * 2. Function-based:
 *    ```json
 *    "populate": {
 *      "type": "function",
 *      "function": "get_my_options",
 *      "arguments": ["arg1", "arg2"]
 *    }
 *    ```
 *
 * 3. Custom filter-based:
 *    ```json
 *    "populate": {
 *      "type": "custom",
 *      "custom": "my_options"
 *    }
 *    ```
 *    Then register via filter:
 *    ```php
 *    add_filter('blockstudio/blocks/attributes/populate', function($options) {
 *      $options['my_options'] = [
 *        ['value' => 'a', 'label' => 'Option A'],
 *        ['value' => 'b', 'label' => 'Option B'],
 *      ];
 *      return $options;
 *    });
 *    ```
 *
 * Extra IDs:
 * The $extra_ids parameter ensures selected values are always included
 * in query results, even if they wouldn't match the query arguments.
 * This prevents "orphaned" selections when items are modified.
 *
 * @since 1.0.0
 */
class Populate {

	/**
	 * Request-local cache for identical WordPress query populates.
	 *
	 * @var array<string,array>
	 */
	private static array $query_cache = array();

	/**
	 * Initialize population for attributes.
	 *
	 * @param array $data      Population configuration data.
	 * @param mixed $extra_ids Optional extra IDs to include.
	 *
	 * @return array The populated query results.
	 */
	public static function init( array $data, mixed $extra_ids = false ): array {
		$query     = array();
		$arguments = $data['arguments'] ?? array();

		if ( 'custom' === $data['type'] ) {
			$custom = self::get_custom_populate_options();

			if ( isset( $custom[ $data['custom'] ] ) ) {
				return $custom[ $data['custom'] ];
			}

			return $query;
		}

		if ( 'function' === $data['type'] ) {
			if (
				isset( $data['function'] ) &&
				function_exists( $data['function'] )
			) {
				return (array) call_user_func_array(
					$data['function'],
					$data['arguments'] ?? array()
				);
			}

			return $query;
		}

		if ( 'query' !== $data['type'] || ! isset( $data['query'] ) ) {
			return $query;
		}

		$cache_key = self::get_query_cache_key( $data['query'], $arguments, $extra_ids );

		if ( isset( self::$query_cache[ $cache_key ] ) ) {
			return self::$query_cache[ $cache_key ];
		}

		if ( 'posts' === $data['query'] ) {
			$original_posts = get_posts( $arguments );

			if ( $extra_ids ) {
				$extra_ids        = is_array( $extra_ids ) ? $extra_ids : array( $extra_ids );
				$extra_posts_args = array(
					'include'     => $extra_ids,
					'post_type'   => 'any',
					'post_status' => 'any',
				);

				$extra_posts = get_posts( $extra_posts_args );

				$original_posts = array_unique(
					array_merge( $original_posts, $extra_posts ),
					SORT_REGULAR
				);
			}

			$query = $original_posts;
		}

		if ( 'users' === $data['query'] ) {
			$original_users = get_users( $arguments );

			if ( $extra_ids ) {
				$extra_ids        = is_array( $extra_ids ) ? $extra_ids : array( $extra_ids );
				$extra_users_args = array(
					'include' => $extra_ids,
				);

				$extra_users = get_users( $extra_users_args );

				$original_users = array_unique(
					array_merge( $original_users, $extra_users ),
					SORT_REGULAR
				);
			}

			$query = $original_users;
		}

		if ( 'terms' === $data['query'] ) {
			// If no taxonomy specified, query all public taxonomies.
			if ( empty( $arguments['taxonomy'] ) ) {
				$arguments['taxonomy'] = get_taxonomies( array( 'public' => true ), 'names' );
			}
			// Default to showing empty terms unless explicitly set.
			if ( ! isset( $arguments['hide_empty'] ) ) {
				$arguments['hide_empty'] = false;
			}
			$original_terms = get_terms( $arguments );

			if ( is_wp_error( $original_terms ) ) {
				$original_terms = array();
			}

			if ( $extra_ids ) {
				$extra_ids        = is_array( $extra_ids ) ? $extra_ids : array( $extra_ids );
				$extra_terms_args = array(
					'include' => $extra_ids,
				);

				$extra_terms = get_terms( $extra_terms_args );

				if ( is_wp_error( $extra_terms ) ) {
					$extra_terms = array();
				}

				$original_terms = array_unique(
					array_merge( $original_terms, $extra_terms ),
					SORT_REGULAR
				);
			}

			$query = $original_terms;
		}

		self::$query_cache[ $cache_key ] = $query;

		return $query;
	}

	/**
	 * Get custom populate options from the current filters.
	 *
	 * @return array Custom populate options.
	 */
	private static function get_custom_populate_options(): array {
		$options = apply_filters( 'blockstudio/blocks/attributes/populate', array() );

		if ( count( $options ) >= 1 ) {
			return $options;
		}

		return apply_filters( 'blockstudio/blocks/populate', array() );
	}

	/**
	 * Build a stable cache key for query-based populate calls.
	 *
	 * @param string $query     Query type.
	 * @param array  $arguments Query arguments.
	 * @param mixed  $extra_ids Extra IDs.
	 *
	 * @return string Cache key.
	 */
	private static function get_query_cache_key( string $query, array $arguments, mixed $extra_ids ): string {
		return md5(
			wp_json_encode(
				array(
					'version'   => Build_Cache::get_populate_write_signal(),
					'query'     => $query,
					'arguments' => $arguments,
					'extraIds'  => $extra_ids,
				)
			)
		);
	}
}
