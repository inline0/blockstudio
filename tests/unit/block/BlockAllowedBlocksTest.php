<?php

use Blockstudio\Block;
use Blockstudio\Block_Registry;
use PHPUnit\Framework\TestCase;

class BlockAllowedBlocksTest extends TestCase {

	private array $registered_wp_blocks = array();

	protected function setUp(): void {
		parent::setUp();

		$this->register_wp_block( 'unitns/alpha', 'unit-token-category' );
		$this->register_wp_block( 'unitns/beta', 'unit-token-category' );
		$this->register_wp_block( 'otherns/gamma', 'other-token-category' );
		$this->register_theme_block( 'themeunit/card', 'theme-token-category' );
	}

	protected function tearDown(): void {
		foreach ( $this->registered_wp_blocks as $name ) {
			if ( WP_Block_Type_Registry::get_instance()->is_registered( $name ) ) {
				unregister_block_type( $name );
			}
		}

		$this->registered_wp_blocks = array();

		parent::tearDown();
	}

	private function register_wp_block( string $name, string $category ): void {
		if ( WP_Block_Type_Registry::get_instance()->is_registered( $name ) ) {
			unregister_block_type( $name );
		}

		register_block_type(
			$name,
			array(
				'title'    => $name,
				'category' => $category,
			)
		);

		$this->registered_wp_blocks[] = $name;
	}

	private function register_theme_block( string $name, string $category ): void {
		$block              = new WP_Block_Type(
			$name,
			array(
				'title'    => $name,
				'category' => $category,
			)
		);
		$block->blockstudio = array(
			'attributes' => array(),
			'data'       => array(
				'path' => get_stylesheet_directory() . '/blockstudio/themeunit/card',
			),
		);

		Block_Registry::instance()->register_block( $name, $block );
	}

	public function test_namespace_wildcard_expands_registered_blocks(): void {
		$this->assertSame(
			array( 'unitns/alpha', 'unitns/beta' ),
			Block::expand_allowed_blocks_tokens( array( 'unitns/*' ) )
		);
	}

	public function test_category_token_expands_registered_blocks(): void {
		$this->assertSame(
			array( 'unitns/alpha', 'unitns/beta' ),
			Block::expand_allowed_blocks_tokens( array( 'category:unit-token-category' ) )
		);
	}

	public function test_theme_token_expands_active_theme_blockstudio_blocks(): void {
		$this->assertContains(
			'themeunit/card',
			Block::expand_allowed_blocks_tokens( array( '@theme' ) )
		);
	}

	public function test_mixed_tokens_preserve_order_and_deduplicate(): void {
		$this->assertSame(
			array( 'core/paragraph', 'unitns/alpha', 'unitns/beta', 'otherns/gamma' ),
			Block::expand_allowed_blocks_tokens(
				array(
					'core/paragraph',
					'unitns/*',
					'unitns/alpha',
					'category:other-token-category',
				)
			)
		);
	}

	public function test_empty_namespace_and_category_tokens_contribute_no_entries(): void {
		$this->assertSame(
			array( 'core/paragraph' ),
			Block::expand_allowed_blocks_tokens(
				array(
					'missingns/*',
					'category:missing-token-category',
					'core/paragraph',
				)
			)
		);
	}

	public function test_editor_innerblocks_allowedblocks_attribute_is_expanded(): void {
		$content = '<InnerBlocks allowedBlocks="[&quot;core/paragraph&quot;,&quot;unitns/*&quot;,&quot;unitns/alpha&quot;]" />';
		$output  = Block::replace_components(
			$content,
			'',
			true,
			(object) array(),
			array(),
			array(),
			array()
		);

		$this->assertMatchesRegularExpression( '/&quot;core\\\\?\/paragraph&quot;/', $output );
		$this->assertMatchesRegularExpression( '/&quot;unitns\\\\?\/alpha&quot;/', $output );
		$this->assertMatchesRegularExpression( '/&quot;unitns\\\\?\/beta&quot;/', $output );
		$this->assertSame( 1, preg_match_all( '/unitns\\\\?\/alpha/', $output ) );
	}
}
