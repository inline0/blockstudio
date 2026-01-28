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
		$custom    = count( apply_filters( 'blockstudio/blocks/attributes/populate', array() ) ) >= 1
			? apply_filters( 'blockstudio/blocks/attributes/populate', array() )
			: apply_filters( 'blockstudio/blocks/populate', array() );

		if ( 'custom' === $data['type'] && isset( $custom[ $data['custom'] ] ) ) {
			$query = $custom[ $data['custom'] ];
		}

		if (
			'function' === $data['type'] &&
			isset( $data['function'] ) &&
			function_exists( $data['function'] )
		) {
			$query = (array) call_user_func_array(
				$data['function'],
				$data['arguments'] ?? array()
			);
		}

		if ( ! isset( $data['query'] ) ) {
			return $query;
		}

		if ( 'posts' === $data['query'] ) {
			$original_posts = get_posts( $arguments );

			if ( $extra_ids ) {
				$extra_ids       = is_array( $extra_ids ) ? $extra_ids : array( $extra_ids );
				$extra_posts_args = array(
					'include'    => $extra_ids,
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
				$extra_ids       = is_array( $extra_ids ) ? $extra_ids : array( $extra_ids );
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
			$original_terms = get_terms( $arguments );

			if ( is_wp_error( $original_terms ) ) {
				$original_terms = array();
			}

			if ( $extra_ids ) {
				$extra_ids       = is_array( $extra_ids ) ? $extra_ids : array( $extra_ids );
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

		return $query;
	}
}
