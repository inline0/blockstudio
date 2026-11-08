<?php
/**
 * Settings collection merge semantics, without WordPress dependencies.
 *
 * @package Blockstudio
 */

use Blockstudio\Settings;
use PHPUnit\Framework\TestCase;

class SettingsMergeTest extends TestCase {

	public static function merge_provider(): array {
		return array(
			'empty list clears defaults' => array(
				array( 'headers' => array( 'Link' ) ),
				array( 'headers' => array() ),
				array( 'headers' => array() ),
			),
			'shorter list replaces defaults' => array(
				array( 'headers' => array( 'Link', 'X-Robots-Tag' ) ),
				array( 'headers' => array( 'Content-Language' ) ),
				array( 'headers' => array( 'Content-Language' ) ),
			),
			'nested objects preserve unspecified keys' => array(
				array( 'performance' => array( 'enabled' => true, 'cache' => array( 'ttl' => 60, 'headers' => array( 'Link', 'X-Robots-Tag' ) ) ) ),
				array( 'performance' => array( 'cache' => array( 'headers' => array() ) ) ),
				array( 'performance' => array( 'enabled' => true, 'cache' => array( 'ttl' => 60, 'headers' => array() ) ) ),
			),
			'empty object leaves associative defaults' => array(
				array( 'cache' => array( 'enabled' => true ) ),
				array( 'cache' => array() ),
				array( 'cache' => array( 'enabled' => true ) ),
			),
		);
	}

	#[\PHPUnit\Framework\Attributes\DataProvider( 'merge_provider' )]
	public function test_lists_replace_and_objects_merge( array $base, array $configured, array $expected ): void {
		$settings = ( new ReflectionClass( Settings::class ) )->newInstanceWithoutConstructor();
		$method   = new ReflectionMethod( Settings::class, 'array_deep_merge' );
		$this->assertSame( $expected, $method->invoke( $settings, $base, $configured ) );
	}
}
