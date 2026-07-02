<?php
/**
 * Tests for the admin overview page.
 *
 * @package Blockstudio
 */

use Blockstudio\Admin_Page;
use PHPUnit\Framework\TestCase;

/**
 * Admin page tests.
 */
class AdminPageTest extends TestCase {

	/**
	 * Remove request filters after each test.
	 *
	 * @return void
	 */
	protected function tearDown(): void {
		remove_all_filters( 'pre_http_request' );
		parent::tearDown();
	}

	/**
	 * Registry imports reject traversal paths from remote registry file lists.
	 *
	 * @return void
	 */
	public function test_registry_import_rejects_traversal_file_names(): void {
		add_filter(
			'pre_http_request',
			static function ( $preempt, array $args, string $url ) {
				if ( false !== strpos( $url, '/e2e/registry' ) ) {
					return array(
						'headers'  => array(),
						'body'     => wp_json_encode(
							array(
								'baseUrl' => 'https://registry.example.test/files',
								'blocks'  => array(
									array(
										'name'  => 'evil-block',
										'files' => array( '../../../../wp-content/mu-plugins/payload.php' ),
									),
								),
							)
						),
						'response' => array(
							'code'    => 200,
							'message' => 'OK',
						),
						'cookies'  => array(),
						'filename' => null,
					);
				}

				return $preempt;
			},
			10,
			3
		);

		$request = new WP_REST_Request( 'POST', '/blockstudio/v1/registry/import' );
		$request->set_param( 'registry', 'test' );
		$request->set_param( 'block', 'evil-block' );

		$result = ( new Admin_Page() )->handle_import( $request );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'invalid_registry_file', $result->get_error_code() );
		$this->assertFileDoesNotExist( WP_CONTENT_DIR . '/mu-plugins/payload.php' );
	}
}
