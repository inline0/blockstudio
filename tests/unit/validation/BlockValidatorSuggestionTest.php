<?php

use Blockstudio\Block_Validator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class BlockValidatorSuggestionTest extends TestCase {

	#[DataProvider( 'suggestions' )]
	public function test_closest_key_is_bounded_and_deterministic( string $key, array $known, ?string $expected ): void {
		$method = new ReflectionMethod( Block_Validator::class, 'closest_key' );
		$this->assertSame( $expected, $method->invoke( null, $key, $known ) );
	}

	public static function suggestions(): array {
		return array(
			'typo' => array( 'titel', array( 'title', 'description' ), 'title' ),
			'prefixed group storage key' => array( 'meta_titel', array( 'meta_title', 'meta_note' ), 'meta_title' ),
			'case' => array( 'TITLE', array( 'title' ), 'title' ),
			'equal distances keep definition order' => array( 'cit', array( 'cat', 'cut' ), 'cat' ),
			'exact case match beats an earlier near match' => array( 'Choice', array( 'choices', 'choice' ), 'choice' ),
			'numeric keys' => array( '0', array( 0, 1 ), '0' ),
			'empty container' => array( 'title', array(), null ),
			'three edit boundary' => array( 'abc', array( 'def' ), 'def' ),
			'outside boundary' => array( 'abcd', array( 'efgh' ), null ),
			'long unknown key' => array( str_repeat( 'x', 10000 ), array( 'title', 'description' ), null ),
		);
	}
}
