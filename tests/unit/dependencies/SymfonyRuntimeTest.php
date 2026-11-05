<?php

use BlockstudioVendor\Symfony\Component\Filesystem\Path;
use BlockstudioVendor\Symfony\Component\Yaml\Yaml;
use PHPUnit\Framework\TestCase;

class SymfonyRuntimeTest extends TestCase {

	public function test_yaml_empty_sequence_items_do_not_nest_following_items(): void {
		$this->assertSame( array( null, 'x' ), Yaml::parse( "-\n\n- x" ) );
	}

	public function test_yaml_zero_anchor_and_frontmatter_values_are_preserved(): void {
		$this->assertSame( array( 'first' => array( 'item' ), 'second' => array( 'item' ) ), Yaml::parse( "first: &0 [item]\nsecond: *0" ) );
		$this->assertSame( array( 'title' => 'Example', 'pattern' => '\\d+\\s' ), Yaml::parse( "title: Example\npattern: '\\d+\\s'" ) );
	}

	public function test_paths_with_fake_schemes_cannot_appear_inside_the_base(): void {
		$path = '/base/../x://../secret.txt';
		$this->assertSame( '/secret.txt', Path::canonicalize( $path ) );
		$this->assertFalse( Path::isBasePath( '/base', $path ) );
		$this->assertSame( 'phar://example.phar/b', Path::canonicalize( 'phar://example.phar/a/../b' ) );
		$this->assertSame( 'style.scss', Path::makeRelative( '/base/css/../style.scss', '/base' ) );
	}
}
