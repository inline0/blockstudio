<?php
/**
 * Populate class.
 *
 * @package Blockstudio
 */

namespace Blockstudio;

/**
 * Handles population of attribute options.
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
