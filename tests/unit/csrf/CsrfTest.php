<?php

use Blockstudio\Csrf;
use PHPUnit\Framework\TestCase;

class CsrfTest extends TestCase {

	// Token generation

	public function test_generate_returns_non_empty_string(): void {
		$token = Csrf::generate();
		$this->assertIsString( $token );
		$this->assertNotEmpty( $token );
	}

	public function test_generate_is_consistent_within_same_request(): void {
		$first  = Csrf::generate();
		$second = Csrf::generate();
		$this->assertSame( $first, $second );
	}

	public function test_generate_returns_hex_string(): void {
		$token = Csrf::generate();
		$this->assertMatchesRegularExpression( '/^[a-f0-9]+$/', $token );
	}

	// Token verification

	public function test_verify_succeeds_for_valid_token(): void {
		$token = Csrf::generate();
		$this->assertTrue( Csrf::verify( $token ) );
	}

	public function test_verify_fails_for_empty_string(): void {
		$this->assertFalse( Csrf::verify( '' ) );
	}

	public function test_verify_fails_for_random_string(): void {
		$this->assertFalse( Csrf::verify( 'completely-random-invalid-token' ) );
	}

	public function test_verify_fails_for_tampered_token(): void {
		$token   = Csrf::generate();
		$tampered = $token . 'x';
		$this->assertFalse( Csrf::verify( $tampered ) );
	}

	public function test_verify_fails_for_truncated_token(): void {
		$token = Csrf::generate();
		$this->assertFalse( Csrf::verify( substr( $token, 0, -4 ) ) );
	}

	// verify_request with WP_REST_Request

	public function test_verify_request_succeeds_with_valid_header(): void {
		$token   = Csrf::generate();
		$request = new WP_REST_Request( 'POST', '/blockstudio/v1/test' );
		$request->set_header( 'X-BS-Token', $token );

		$this->assertTrue( Csrf::verify_request( $request ) );
	}

	public function test_verify_request_fails_with_missing_header(): void {
		$request = new WP_REST_Request( 'POST', '/blockstudio/v1/test' );

		$this->assertFalse( Csrf::verify_request( $request ) );
	}

	public function test_verify_request_fails_with_invalid_header(): void {
		$request = new WP_REST_Request( 'POST', '/blockstudio/v1/test' );
		$request->set_header( 'X-BS-Token', 'bad-token-value' );

		$this->assertFalse( Csrf::verify_request( $request ) );
	}

	// Header constant

	public function test_header_constant_value(): void {
		$this->assertSame( 'X-BS-Token', Csrf::HEADER );
	}
}
