<?php

use PHPUnit\Framework\TestCase;

class IconRenderTest extends TestCase {

	private const ICON_KEY_PREFIX = 'blockstudio_1_icon_';

	private const SET_KEY_PREFIX = 'blockstudio_1_icon_set_';

	protected function setUp(): void {
		$this->clear_transients();
	}

	protected function tearDown(): void {
		$this->clear_transients();
	}

	private function clear_transients(): void {
		delete_transient( self::ICON_KEY_PREFIX . md5( 'octiconsalert-16' ) );
		delete_transient( self::ICON_KEY_PREFIX . md5( 'octiconsno-such-icon' ) );
		delete_transient( self::SET_KEY_PREFIX . md5( 'octicons' ) );
		delete_transient( self::SET_KEY_PREFIX . md5( 'no-such-set' ) );
		delete_transient( self::ICON_KEY_PREFIX . md5( 'no-such-seticon' ) );
		delete_transient( self::ICON_KEY_PREFIX . md5( 'fontawesome-free-solidheart' ) );
		delete_transient( self::SET_KEY_PREFIX . md5( 'fontawesome-free-solid' ) );
	}

	private function svg( string $file, string $icon ): string {
		$set = json_decode( file_get_contents( BLOCKSTUDIO_DIR . '/includes/icons/' . $file . '.json' ), true );

		return $set[ $icon . '.svg' ];
	}

	public function test_renders_the_svg_from_the_set_file(): void {
		$this->assertSame(
			$this->svg( 'octicons', 'alert-16' ),
			bs_icon(
				array(
					'set'  => 'octicons',
					'icon' => 'alert-16',
				)
			)
		);
	}

	public function test_renders_icons_of_a_sub_set(): void {
		$this->assertSame(
			$this->svg( 'fontawesome-free-solid', 'heart' ),
			bs_icon(
				array(
					'set'    => 'fontawesome-free',
					'subSet' => 'solid',
					'icon'   => 'heart',
				)
			)
		);
	}

	public function test_renders_several_icons_of_one_set(): void {
		foreach ( array( 'alert-16', 'alert-24' ) as $icon ) {
			$this->assertSame(
				$this->svg( 'octicons', $icon ),
				bs_icon(
					array(
						'set'  => 'octicons',
						'icon' => $icon,
					)
				)
			);
		}

		delete_transient( self::ICON_KEY_PREFIX . md5( 'octiconsalert-24' ) );
	}

	public function test_does_not_store_the_whole_set_as_a_transient(): void {
		bs_icon(
			array(
				'set'  => 'octicons',
				'icon' => 'alert-16',
			)
		);

		$this->assertFalse( get_transient( self::SET_KEY_PREFIX . md5( 'octicons' ) ) );
	}

	public function test_stores_only_the_rendered_icon_as_a_transient(): void {
		bs_icon(
			array(
				'set'  => 'octicons',
				'icon' => 'alert-16',
			)
		);

		$this->assertSame(
			$this->svg( 'octicons', 'alert-16' ),
			get_transient( self::ICON_KEY_PREFIX . md5( 'octiconsalert-16' ) )
		);
	}

	public function test_serves_the_icon_from_its_transient(): void {
		set_transient( self::ICON_KEY_PREFIX . md5( 'octiconsalert-16' ), '<svg id="cached"></svg>', 60 );

		$this->assertSame(
			'<svg id="cached"></svg>',
			bs_icon(
				array(
					'set'  => 'octicons',
					'icon' => 'alert-16',
				)
			)
		);
	}

	public function test_unknown_set_renders_nothing_and_stores_nothing(): void {
		$this->assertSame(
			'',
			bs_icon(
				array(
					'set'  => 'no-such-set',
					'icon' => 'icon',
				)
			)
		);
		$this->assertFalse( get_transient( self::ICON_KEY_PREFIX . md5( 'no-such-seticon' ) ) );
		$this->assertFalse( get_transient( self::SET_KEY_PREFIX . md5( 'no-such-set' ) ) );
	}

	public function test_unknown_icon_renders_nothing_and_stores_nothing(): void {
		$this->assertSame(
			'',
			bs_icon(
				array(
					'set'  => 'octicons',
					'icon' => 'no-such-icon',
				)
			)
		);
		$this->assertFalse( get_transient( self::ICON_KEY_PREFIX . md5( 'octiconsno-such-icon' ) ) );
		$this->assertFalse( get_transient( self::SET_KEY_PREFIX . md5( 'octicons' ) ) );
	}
}
