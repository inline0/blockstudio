<?php

use Blockstudio\Block;
use Blockstudio\Build;
use PHPUnit\Framework\TestCase;

/**
 * bs_strip_render_markers() removes the block identity comments from output
 * that never passes through the page buffer.
 */
class RenderMarkersTest extends TestCase {

	private string $name = 'blockstudio/type-text';

	protected function setUp(): void {
		parent::setUp();

		if ( ! isset( Build::data()[ $this->name ] ) ) {
			$this->markTestSkipped( "{$this->name} not registered." );
		}
	}

	private function render(): string {
		return Block::render(
			array(
				'blockstudio' => array(
					'name'       => $this->name,
					'attributes' => array( 'text' => 'Marker test' ),
				),
			)
		);
	}

	public function test_block_output_starts_with_the_marker(): void {
		$this->assertStringStartsWith( Block::comment( $this->name ), $this->render() );
	}

	public function test_strip_removes_the_marker_and_keeps_the_markup(): void {
		$rendered = $this->render();
		$stripped = bs_strip_render_markers( $rendered );

		$this->assertStringNotContainsString( '<!--blockstudio/', $stripped );
		$this->assertSame( str_replace( Block::comment( $this->name ), '', $rendered ), $stripped );
		$this->assertStringContainsString( 'blockstudio-test__block', $stripped );
	}

	public function test_strip_cleans_do_blocks_output(): void {
		$html = do_blocks( '<!-- wp:' . $this->name . ' {"blockstudio":{"attributes":{"text":"Marker test"}}} /-->' );

		$this->assertStringContainsString( Block::comment( $this->name ), $html );
		$this->assertStringNotContainsString( '<!--blockstudio/', bs_strip_render_markers( $html ) );
	}

	public function test_strip_removes_the_marker_of_every_registered_block(): void {
		$html = '';

		foreach ( Build::data() as $block ) {
			$html .= Block::comment( $block['name'] ) . '<p>' . $block['name'] . '</p>';
		}

		$this->assertStringContainsString( '<!--blockstudio/', $html );

		$stripped = bs_strip_render_markers( $html );

		$this->assertStringNotContainsString( '<!--blockstudio/', $stripped );
		$this->assertStringContainsString( '<p>' . $this->name . '</p>', $stripped );
	}

	public function test_strip_removes_repeated_markers(): void {
		$comment = Block::comment( $this->name );

		$this->assertSame(
			'<p>a</p><p>b</p>',
			bs_strip_render_markers( $comment . '<p>a</p>' . $comment . '<p>b</p>' )
		);
	}

	public function test_strip_leaves_other_comments_and_markup_alone(): void {
		$html = '<!-- wp:paragraph --><p>a</p><!-- /wp:paragraph --><!--more--><!--blockstudio/not-registered/block--><p>b</p>';

		$this->assertSame( $html, bs_strip_render_markers( $html ) );
	}

	public function test_strip_returns_markup_without_markers_unchanged(): void {
		$this->assertSame( '', bs_strip_render_markers( '' ) );
		$this->assertSame( '<div class="a">b</div>', bs_strip_render_markers( '<div class="a">b</div>' ) );
	}

	public function test_strip_removes_a_marker_that_follows_an_unclosed_block_comment(): void {
		$comment = Block::comment( $this->name );

		$this->assertSame(
			'<p>a</p><!--blockstudio/unclosed <p>b</p>',
			bs_strip_render_markers( '<p>a</p><!--blockstudio/unclosed ' . $comment . '<p>b</p>' )
		);
	}

	public function test_strip_removes_only_the_registered_marker_between_unregistered_ones(): void {
		$comment = Block::comment( $this->name );

		$this->assertSame(
			'<!--blockstudio/not-registered/a--><!--blockstudio/not-registered/b-->',
			bs_strip_render_markers( '<!--blockstudio/not-registered/a-->' . $comment . '<!--blockstudio/not-registered/b-->' )
		);
	}

	public function test_find_comments_lists_each_distinct_comment_once(): void {
		$comment = Block::comment( $this->name );
		$other   = '<!--blockstudio/not-registered/block-->';

		$this->assertSame(
			array( $comment, $other ),
			array_keys( Block::find_comments( $comment . '<p>a</p>' . $other . $comment ) )
		);
	}

	public function test_find_comments_ignores_other_markup(): void {
		$this->assertSame( array(), Block::find_comments( '' ) );
		$this->assertSame(
			array(),
			Block::find_comments( '<!--more--><!-- wp:paragraph --><p>blockstudio/x</p><!--blockstudio/open' )
		);
	}

	public function test_marker_detection_and_removal_fall_back_when_pcre_limits_are_exhausted(): void {
		$comment = Block::comment( $this->name );
		$other   = '<!--blockstudio/not-registered/block-->';
		$html    = $comment . $other . '<p>Kept</p>' . $comment;
		$limit   = ini_get( 'pcre.backtrack_limit' );
		try {
			ini_set( 'pcre.backtrack_limit', '0' );
			$found    = Block::find_comments( $html );
			$stripped = Block::strip_comments( $html );
		} finally {
			ini_set( 'pcre.backtrack_limit', $limit );
		}
		$this->assertArrayHasKey( $comment, $found );
		$this->assertSame( $other . '<p>Kept</p>', $stripped );
	}
}
