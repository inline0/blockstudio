<?php

use Blockstudio\Block;
use Blockstudio\Build;
use PHPUnit\Framework\TestCase;

/**
 * Attributes written at the top level of a block's attributes.
 *
 * The editor always nests values under `blockstudio.attributes`, but every
 * other writer (the REST API, WP-CLI, a content migration) discovers a
 * block from the attributes Blockstudio registers on the `WP_Block_Type` and
 * writes them at the top level. Those values used to be stored, parsed back
 * byte-identically, and then dropped at render: the block showed its default
 * with no error and no warning.
 */
class BlockTopLevelAttributesTest extends TestCase {

	private const BLOCK = 'blockstudio/native';

	protected function setUp(): void {
		parent::setUp();

		if ( ! isset( Build::blocks()[ self::BLOCK ] ) ) {
			$this->markTestSkipped( self::BLOCK . ' fixture block is not registered.' );
		}
	}

	/**
	 * Render the fixture and return the `$a` array it dumps as JSON.
	 *
	 * @param array $attributes Attributes as a caller would write them.
	 * @return array
	 */
	private function rendered_attributes( array $attributes ): array {
		$html = (string) Block::render( $attributes );

		$this->assertMatchesRegularExpression(
			'/<code>Attributes: (.+?)<\/code>/s',
			$html,
			'fixture did not render its attribute dump'
		);

		preg_match( '/<code>Attributes: (.+?)<\/code>/s', $html, $matches );

		return (array) json_decode( $matches[1], true );
	}

	public function test_top_level_attribute_reaches_the_template(): void {
		$attributes = $this->rendered_attributes(
			array(
				'blockstudio' => array( 'name' => self::BLOCK ),
				'message'     => 'from the top level',
			)
		);

		$this->assertSame( 'from the top level', $attributes['message'] ?? null );
	}

	public function test_nested_attribute_wins_over_a_top_level_one(): void {
		$attributes = $this->rendered_attributes(
			array(
				'blockstudio' => array(
					'name'       => self::BLOCK,
					'attributes' => array( 'message' => 'from blockstudio.attributes' ),
				),
				'message'     => 'from the top level',
			)
		);

		$this->assertSame(
			'from blockstudio.attributes',
			$attributes['message'] ?? null,
			'a caller who wrote the nested form meant it'
		);
	}

	public function test_undeclared_top_level_keys_are_not_adopted(): void {
		$attributes = $this->rendered_attributes(
			array(
				'blockstudio' => array( 'name' => self::BLOCK ),
				'className'   => 'wordpress-owns-this',
				'anchor'      => 'wordpress-owns-this-too',
				'notAField'   => 'nobody owns this',
			)
		);

		$this->assertArrayNotHasKey( 'className', $attributes );
		$this->assertArrayNotHasKey( 'anchor', $attributes );
		$this->assertArrayNotHasKey( 'notAField', $attributes );
	}

	public function test_nested_attributes_are_unaffected(): void {
		$attributes = $this->rendered_attributes(
			array(
				'blockstudio' => array(
					'name'       => self::BLOCK,
					'attributes' => array( 'message' => 'unchanged' ),
				),
			)
		);

		$this->assertSame( 'unchanged', $attributes['message'] ?? null );
	}
}
