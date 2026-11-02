<?php

use Blockstudio\Page_Renderers;
use PHPUnit\Framework\TestCase;

class PageRenderersTest extends TestCase {

	protected function setUp(): void {
		Page_Renderers::reset();
		Page_Renderers::load();
	}

	protected function tearDown(): void {
		Page_Renderers::reset();
	}

	public function test_registers_and_reads_a_renderer(): void {
		$render = static fn( array $request ): array => array( 'html' => $request['entry'] );

		Page_Renderers::register(
			'unit',
			array(
				'extensions' => array( '.Unit.html', 'unit' ),
				'layouts'    => array( 'layout.unit' ),
				'render'     => $render,
			)
		);

		$renderer = Page_Renderers::get( 'unit' );

		$this->assertIsArray( $renderer );
		$this->assertSame( array( 'unit.html', 'unit' ), $renderer['extensions'] );
		$this->assertSame( array( 'layout.unit' ), $renderer['layouts'] );
		$this->assertSame( $render, $renderer['render'] );
		$this->assertContains( 'unit', Page_Renderers::ids() );
		$this->assertSame( 'unit', Page_Renderers::layout_names()['layout.unit'] ?? null );
		$this->assertNull( Page_Renderers::get( 'missing' ) );
	}

	public function test_entry_lookup_uses_the_longest_claimed_extension(): void {
		Page_Renderers::register( 'short', $this->definition( array( 'unit' ) ) );
		Page_Renderers::register( 'long', $this->definition( array( 'blade.unit' ) ) );

		$this->assertSame( 'short', Page_Renderers::for_entry( '/srv/pages/home/page.unit' ) );
		$this->assertSame( 'long', Page_Renderers::for_entry( '/srv/pages/home/page.blade.unit' ) );
		$this->assertSame( 'short', Page_Renderers::for_entry( 'PAGE.UNIT' ) );
		$this->assertNull( Page_Renderers::for_entry( '/srv/pages/home/page.unknown' ) );
		$this->assertNull( Page_Renderers::for_entry( '/srv/pages/home/unit' ) );
	}

	public function test_layout_lookup_reads_the_basename(): void {
		Page_Renderers::register( 'unit', $this->definition( array( 'unit' ), array( 'layout.unit' ) ) );

		$this->assertSame( 'unit', Page_Renderers::for_layout( '/srv/pages/account/layout.unit' ) );
		$this->assertNull( Page_Renderers::for_layout( '/srv/pages/account/layout.php' ) );
	}

	public function test_rejects_duplicate_ids(): void {
		Page_Renderers::register( 'unit', $this->definition( array( 'unit' ) ) );

		$this->expectException( InvalidArgumentException::class );
		Page_Renderers::register( 'unit', $this->definition( array( 'other' ) ) );
	}

	public function test_rejects_an_extension_claimed_twice(): void {
		Page_Renderers::register( 'first', $this->definition( array( 'unit' ) ) );

		$this->expectException( InvalidArgumentException::class );
		Page_Renderers::register( 'second', $this->definition( array( 'UNIT' ) ) );
	}

	public function test_rejects_a_layout_claimed_twice(): void {
		Page_Renderers::register( 'first', $this->definition( array( 'first' ), array( 'layout.unit' ) ) );

		$this->expectException( InvalidArgumentException::class );
		Page_Renderers::register( 'second', $this->definition( array( 'second' ), array( 'layout.unit' ) ) );
	}

	/**
	 * @return array<string, array{0:string,1:array}>
	 */
	public static function invalid_definitions(): array {
		$render = static fn(): array => array( 'html' => '' );

		return array(
			'uppercase id'      => array(
				'Unit',
				array(
					'extensions' => array( 'unit' ),
					'render'     => $render,
				),
			),
			'id with a slash'   => array(
				'unit/react',
				array(
					'extensions' => array( 'unit' ),
					'render'     => $render,
				),
			),
			'missing render'    => array( 'unit', array( 'extensions' => array( 'unit' ) ) ),
			'invalid render'    => array(
				'unit',
				array(
					'extensions' => array( 'unit' ),
					'render'     => 'blockstudio_missing_render_function',
				),
			),
			'non-list strings'  => array(
				'unit',
				array(
					'extensions' => 42,
					'render'     => $render,
				),
			),
			'empty extension'   => array(
				'unit',
				array(
					'extensions' => array( '' ),
					'render'     => $render,
				),
			),
			'path as extension' => array(
				'unit',
				array(
					'extensions' => array( 'pages/unit' ),
					'render'     => $render,
				),
			),
			'layout.php layout' => array(
				'unit',
				array(
					'layouts' => array( 'layout.php' ),
					'render'  => $render,
				),
			),
			'data loader name'  => array(
				'unit',
				array(
					'layouts' => array( 'layout.data.php' ),
					'render'  => $render,
				),
			),
			'nested layout'     => array(
				'unit',
				array(
					'layouts' => array( 'shell/layout.unit' ),
					'render'  => $render,
				),
			),
		);
	}

	#[PHPUnit\Framework\Attributes\DataProvider( 'invalid_definitions' )]
	public function test_rejects_invalid_definitions( string $id, array $definition ): void {
		$this->expectException( InvalidArgumentException::class );
		Page_Renderers::register( $id, $definition );
	}

	public function test_reset_clears_renderers_and_fires_registration_again(): void {
		$fired    = 0;
		$register = static function () use ( &$fired ): void {
			++$fired;
			Page_Renderers::register(
				'hooked',
				array(
					'extensions' => array( 'hooked' ),
					'render'     => static fn(): array => array( 'html' => '' ),
				)
			);
		};

		Page_Renderers::reset();
		add_action( 'blockstudio/pages/renderers', $register );

		try {
			$this->assertSame( 'hooked', Page_Renderers::for_entry( 'page.hooked' ) );
			$this->assertSame( 'hooked', Page_Renderers::for_entry( 'page.hooked' ) );
			$this->assertSame( 1, $fired );

			Page_Renderers::reset();

			$this->assertSame( 'hooked', Page_Renderers::get( 'hooked' )['id'] ?? null );
			$this->assertSame( 2, $fired );
		} finally {
			remove_action( 'blockstudio/pages/renderers', $register );
		}
	}

	public function test_render_validates_and_normalizes_the_result(): void {
		Page_Renderers::register(
			'unit',
			array(
				'extensions' => array( 'unit' ),
				'render'     => static fn( array $request ): array => array(
					'html'         => '<p>' . $request['slot'] . '</p>',
					'dependencies' => array( 'C:\\theme\\page.unit', '', 7, 'C:\\theme\\page.unit' ),
					'assets'       => array(
						'/srv/theme/script.js',
						array(
							'path'    => '/srv/theme/island.js',
							'type'    => 'module',
							'version' => 'build-1',
						),
					),
				),
			)
		);

		$result = Page_Renderers::render(
			array(
				'renderer' => 'unit',
				'slot'     => 'Slot',
			)
		);

		$this->assertSame( '<p>Slot</p>', $result['html'] );
		$this->assertSame( array( 'C:/theme/page.unit' ), $result['dependencies'] );
		$this->assertSame(
			array(
				array( 'path' => '/srv/theme/script.js' ),
				array(
					'path'    => '/srv/theme/island.js',
					'type'    => 'module',
					'version' => 'build-1',
				),
			),
			$result['assets']
		);
	}

	public function test_render_rejects_unknown_renderers_and_invalid_results(): void {
		Page_Renderers::register(
			'broken',
			array(
				'extensions' => array( 'broken' ),
				'render'     => static fn(): array => array( 'markup' => '' ),
			)
		);

		foreach ( array( 'missing', 'broken' ) as $renderer ) {
			try {
				Page_Renderers::render( array( 'renderer' => $renderer ) );
				$this->fail( 'Expected a render failure for ' . $renderer );
			} catch ( RuntimeException $exception ) {
				$this->assertStringContainsString( $renderer, $exception->getMessage() );
			}
		}
	}

	/**
	 * @param array<int, string> $extensions Extensions.
	 * @param array<int, string> $layouts    Layout names.
	 *
	 * @return array Renderer definition.
	 */
	private function definition( array $extensions, array $layouts = array() ): array {
		return array(
			'extensions' => $extensions,
			'layouts'    => $layouts,
			'render'     => static fn(): array => array( 'html' => '' ),
		);
	}
}
