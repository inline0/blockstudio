<?php

use Blockstudio\Populate;
use PHPUnit\Framework\TestCase;

class PopulateTest extends TestCase {

	private array $created_post_ids = array();
	private array $created_user_ids = array();
	private array $created_term_ids = array();

	protected function tearDown(): void {
		foreach ( $this->created_post_ids as $id ) {
			wp_delete_post( $id, true );
		}
		if ( ! function_exists( 'wp_delete_user' ) ) {
			require_once ABSPATH . 'wp-admin/includes/user.php';
		}
		foreach ( $this->created_user_ids as $id ) {
			wp_delete_user( $id );
		}
		foreach ( $this->created_term_ids as $id ) {
			wp_delete_term( $id, 'category' );
		}

		$this->created_post_ids = array();
		$this->created_user_ids = array();
		$this->created_term_ids = array();
	}

	// Posts query

	public function test_posts_query_returns_array(): void {
		$result = Populate::init( array(
			'type'  => 'query',
			'query' => 'posts',
		) );

		$this->assertIsArray( $result );
	}

	public function test_posts_query_returns_post_objects(): void {
		$post_id                = wp_insert_post( array(
			'post_title'  => 'Populate test post',
			'post_status' => 'publish',
			'post_type'   => 'post',
		) );
		$this->created_post_ids[] = $post_id;

		$result = Populate::init( array(
			'type'      => 'query',
			'query'     => 'posts',
			'arguments' => array(
				'post_type' => 'post',
				'include'   => array( $post_id ),
			),
		) );

		$this->assertNotEmpty( $result );
		$this->assertInstanceOf( WP_Post::class, $result[0] );
	}

	public function test_posts_query_respects_arguments(): void {
		$post_id                = wp_insert_post( array(
			'post_title'  => 'Populate specific post',
			'post_status' => 'publish',
			'post_type'   => 'page',
		) );
		$this->created_post_ids[] = $post_id;

		$result = Populate::init( array(
			'type'      => 'query',
			'query'     => 'posts',
			'arguments' => array(
				'post_type' => 'page',
				'include'   => array( $post_id ),
			),
		) );

		$ids = wp_list_pluck( $result, 'ID' );
		$this->assertContains( $post_id, $ids );
	}

	public function test_posts_query_with_extra_ids(): void {
		$post_id                = wp_insert_post( array(
			'post_title'  => 'Extra ID post',
			'post_status' => 'draft',
			'post_type'   => 'post',
		) );
		$this->created_post_ids[] = $post_id;

		$result = Populate::init(
			array(
				'type'      => 'query',
				'query'     => 'posts',
				'arguments' => array(
					'post_type'   => 'post',
					'post_status' => 'publish',
				),
			),
			$post_id
		);

		$ids = wp_list_pluck( $result, 'ID' );
		$this->assertContains( $post_id, $ids );
	}

	public function test_posts_query_with_extra_ids_as_array(): void {
		$post_id                = wp_insert_post( array(
			'post_title'  => 'Extra array post',
			'post_status' => 'draft',
			'post_type'   => 'post',
		) );
		$this->created_post_ids[] = $post_id;

		$result = Populate::init(
			array(
				'type'      => 'query',
				'query'     => 'posts',
				'arguments' => array(
					'post_type'   => 'post',
					'post_status' => 'publish',
				),
			),
			array( $post_id )
		);

		$ids = wp_list_pluck( $result, 'ID' );
		$this->assertContains( $post_id, $ids );
	}

	// Users query

	public function test_users_query_returns_array(): void {
		$result = Populate::init( array(
			'type'  => 'query',
			'query' => 'users',
		) );

		$this->assertIsArray( $result );
	}

	public function test_users_query_returns_user_objects(): void {
		$result = Populate::init( array(
			'type'  => 'query',
			'query' => 'users',
		) );

		$this->assertNotEmpty( $result );
		$this->assertInstanceOf( WP_User::class, $result[0] );
	}

	public function test_users_query_with_extra_ids(): void {
		$user_id                = wp_insert_user( array(
			'user_login' => 'populate_test_user_' . uniqid(),
			'user_pass'  => wp_generate_password(),
			'role'       => 'subscriber',
		) );
		$this->created_user_ids[] = $user_id;

		$result = Populate::init(
			array(
				'type'      => 'query',
				'query'     => 'users',
				'arguments' => array(
					'role' => 'administrator',
				),
			),
			$user_id
		);

		$ids = wp_list_pluck( $result, 'ID' );
		$this->assertContains( $user_id, $ids );
	}

	// Terms query

	public function test_terms_query_returns_array(): void {
		$result = Populate::init( array(
			'type'  => 'query',
			'query' => 'terms',
		) );

		$this->assertIsArray( $result );
	}

	public function test_terms_query_with_taxonomy(): void {
		$term                   = wp_insert_term( 'Populate test term ' . uniqid(), 'category' );
		$this->created_term_ids[] = $term['term_id'];

		$result = Populate::init( array(
			'type'      => 'query',
			'query'     => 'terms',
			'arguments' => array(
				'taxonomy'   => 'category',
				'include'    => array( $term['term_id'] ),
				'hide_empty' => false,
			),
		) );

		$ids = wp_list_pluck( $result, 'term_id' );
		$this->assertContains( $term['term_id'], $ids );
	}

	public function test_terms_query_defaults_hide_empty_to_false(): void {
		$term                   = wp_insert_term( 'Empty term ' . uniqid(), 'category' );
		$this->created_term_ids[] = $term['term_id'];

		$result = Populate::init( array(
			'type'      => 'query',
			'query'     => 'terms',
			'arguments' => array(
				'taxonomy' => 'category',
				'include'  => array( $term['term_id'] ),
			),
		) );

		$ids = wp_list_pluck( $result, 'term_id' );
		$this->assertContains( $term['term_id'], $ids );
	}

	public function test_terms_query_with_extra_ids(): void {
		$term                   = wp_insert_term( 'Extra term ' . uniqid(), 'category' );
		$this->created_term_ids[] = $term['term_id'];

		$result = Populate::init(
			array(
				'type'      => 'query',
				'query'     => 'terms',
				'arguments' => array(
					'taxonomy' => 'category',
					'include'  => array(),
				),
			),
			$term['term_id']
		);

		$ids = wp_list_pluck( $result, 'term_id' );
		$this->assertContains( $term['term_id'], $ids );
	}

	public function test_terms_query_defaults_to_public_taxonomies(): void {
		$result = Populate::init( array(
			'type'      => 'query',
			'query'     => 'terms',
			'arguments' => array(),
		) );

		$this->assertIsArray( $result );
	}

	// Function-based populate

	public function test_function_populate_calls_function(): void {
		$result = Populate::init( array(
			'type'     => 'function',
			'function' => 'get_post_types',
		) );

		$this->assertIsArray( $result );
		$this->assertContains( 'post', $result );
		$this->assertContains( 'page', $result );
	}

	public function test_function_populate_with_arguments(): void {
		$result = Populate::init( array(
			'type'      => 'function',
			'function'  => 'get_post_types',
			'arguments' => array( array( 'public' => true ), 'names' ),
		) );

		$this->assertIsArray( $result );
		$this->assertContains( 'post', $result );
	}

	public function test_function_populate_nonexistent_function_returns_empty(): void {
		$result = Populate::init( array(
			'type'     => 'function',
			'function' => 'nonexistent_populate_function_xyz',
		) );

		$this->assertSame( array(), $result );
	}

	// Custom populate

	public function test_custom_populate_via_filter(): void {
		$cb = function ( $options ) {
			$options['test_options'] = array(
				array( 'value' => 'a', 'label' => 'Option A' ),
				array( 'value' => 'b', 'label' => 'Option B' ),
			);
			return $options;
		};

		add_filter( 'blockstudio/blocks/attributes/populate', $cb );

		$result = Populate::init( array(
			'type'   => 'custom',
			'custom' => 'test_options',
		) );

		remove_filter( 'blockstudio/blocks/attributes/populate', $cb );

		$this->assertCount( 2, $result );
		$this->assertSame( 'a', $result[0]['value'] );
		$this->assertSame( 'Option B', $result[1]['label'] );
	}

	public function test_custom_populate_missing_key_returns_empty(): void {
		$result = Populate::init( array(
			'type'   => 'custom',
			'custom' => 'nonexistent_custom_key',
		) );

		$this->assertSame( array(), $result );
	}

	// Type without query returns early

	public function test_no_query_key_returns_early(): void {
		$result = Populate::init( array(
			'type' => 'query',
		) );

		$this->assertSame( array(), $result );
	}

	// Legacy filter fallback

	public function test_legacy_filter_fallback(): void {
		// The legacy filter is only used when the new filter returns empty.
		// Temporarily remove all hooks on the new filter to test fallback.
		global $wp_filter;
		$saved_new = $wp_filter['blockstudio/blocks/attributes/populate'] ?? null;
		unset( $wp_filter['blockstudio/blocks/attributes/populate'] );

		$cb = function ( $options ) {
			$options['legacy_options'] = array(
				array( 'value' => 'x', 'label' => 'Legacy' ),
			);
			return $options;
		};

		add_filter( 'blockstudio/blocks/populate', $cb );

		$result = Populate::init( array(
			'type'   => 'custom',
			'custom' => 'legacy_options',
		) );

		remove_filter( 'blockstudio/blocks/populate', $cb );

		if ( null !== $saved_new ) {
			$wp_filter['blockstudio/blocks/attributes/populate'] = $saved_new;
		}

		$this->assertCount( 1, $result );
		$this->assertSame( 'x', $result[0]['value'] );
	}
}
