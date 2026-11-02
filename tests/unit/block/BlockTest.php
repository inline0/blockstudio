<?php

use Blockstudio\Block;
use Blockstudio\Build;
use PHPUnit\Framework\TestCase;

class BlockTest extends TestCase {

	protected function setUp(): void {
		$ref = new ReflectionClass( Block::class );

		$count = $ref->getProperty( 'count' );
		$count->setAccessible( true );
		$count->setValue( null, 0 );

		$count_by_block = $ref->getProperty( 'count_by_block' );
		$count_by_block->setAccessible( true );
		$count_by_block->setValue( null, array() );
	}

	// id()

	public function test_id_returns_string(): void {
		$id = Block::id( array( 'name' => 'test' ), array( 'text' => 'hello' ) );
		$this->assertIsString( $id );
	}

	public function test_id_starts_with_blockstudio_prefix(): void {
		$id = Block::id( array( 'name' => 'test' ), array() );
		$this->assertStringStartsWith( 'blockstudio-', $id );
	}

	public function test_id_has_12_char_hash(): void {
		$id = Block::id( array( 'name' => 'test' ), array() );
		$hash = substr( $id, strlen( 'blockstudio-' ) );
		$this->assertSame( 12, strlen( $hash ) );
	}

	public function test_id_is_unique_across_calls(): void {
		$id1 = Block::id( array( 'name' => 'test' ), array() );
		$id2 = Block::id( array( 'name' => 'test' ), array() );
		$this->assertNotSame( $id1, $id2 );
	}

	public function test_id_hash_is_hexadecimal(): void {
		$id   = Block::id( array( 'name' => 'test' ), array( 'text' => 'value' ) );
		$hash = substr( $id, strlen( 'blockstudio-' ) );
		$this->assertMatchesRegularExpression( '/^[0-9a-f]{12}$/', $hash );
	}

	// comment()

	public function test_comment_returns_html_comment(): void {
		$data = Build::data();
		$name = array_key_first( $data );

		$comment = Block::comment( $name );
		$this->assertStringStartsWith( '<!--blockstudio/', $comment );
		$this->assertStringEndsWith( '-->', $comment );
	}

	public function test_comment_contains_block_name(): void {
		$data = Build::data();
		$name = array_key_first( $data );
		$expected_name = $data[ $name ]['name'];

		$comment = Block::comment( $name );
		$this->assertStringContainsString( $expected_name, $comment );
	}

	public function test_comment_format_for_text_block(): void {
		$data = Build::data();
		if ( ! isset( $data['blockstudio/type-text'] ) ) {
			$this->markTestSkipped( 'blockstudio/type-text not registered.' );
		}

		$block_name = $data['blockstudio/type-text']['name'];
		$comment    = Block::comment( 'blockstudio/type-text' );
		$this->assertSame( '<!--blockstudio/' . $block_name . '-->', $comment );
	}

	// render() with a real test block

	public function test_render_returns_string_for_text_block(): void {
		$blocks = Build::blocks();
		if ( ! isset( $blocks['blockstudio/type-text'] ) ) {
			$this->markTestSkipped( 'blockstudio/type-text not registered.' );
		}

		$attributes = array(
			'blockstudio' => array(
				'name'       => 'blockstudio/type-text',
				'attributes' => array(
					'text' => 'Hello World',
				),
			),
		);

		$result = Block::render( $attributes );
		$this->assertIsString( $result );
	}

	public function test_render_contains_block_comment(): void {
		$blocks = Build::blocks();
		if ( ! isset( $blocks['blockstudio/type-text'] ) ) {
			$this->markTestSkipped( 'blockstudio/type-text not registered.' );
		}

		$attributes = array(
			'blockstudio' => array(
				'name'       => 'blockstudio/type-text',
				'attributes' => array(
					'text' => 'Test content',
				),
			),
		);

		$expected_comment = Block::comment( 'blockstudio/type-text' );
		$result           = Block::render( $attributes );
		$this->assertStringContainsString( $expected_comment, $result );
	}

	public function test_render_contains_template_output(): void {
		$blocks = Build::blocks();
		if ( ! isset( $blocks['blockstudio/type-text'] ) ) {
			$this->markTestSkipped( 'blockstudio/type-text not registered.' );
		}

		$attributes = array(
			'blockstudio' => array(
				'name'       => 'blockstudio/type-text',
				'attributes' => array(
					'text' => 'Rendered text value',
				),
			),
		);

		$result = Block::render( $attributes );
		$this->assertStringContainsString( 'blockstudio-test__block', $result );
	}

	public function test_render_returns_false_without_block_name(): void {
		$result = Block::render( array() );
		$this->assertFalse( $result );
	}

	// Block index counting

	public function test_render_increments_block_index(): void {
		$blocks = Build::blocks();
		if ( ! isset( $blocks['blockstudio/type-text'] ) ) {
			$this->markTestSkipped( 'blockstudio/type-text not registered.' );
		}

		$attributes = array(
			'blockstudio' => array(
				'name'       => 'blockstudio/type-text',
				'attributes' => array(
					'text' => 'First',
				),
			),
		);

		Block::render( $attributes );
		Block::render( $attributes );

		$ref = new ReflectionClass( Block::class );
		$count_by_block = $ref->getProperty( 'count_by_block' );
		$count_by_block->setAccessible( true );
		$counts = $count_by_block->getValue();

		$this->assertSame( 2, $counts['blockstudio/type-text'] );
	}

	public function test_render_tracks_total_count(): void {
		$blocks = Build::blocks();
		if ( ! isset( $blocks['blockstudio/type-text'] ) ) {
			$this->markTestSkipped( 'blockstudio/type-text not registered.' );
		}

		$text_attrs = array(
			'blockstudio' => array(
				'name'       => 'blockstudio/type-text',
				'attributes' => array(
					'text' => 'Count test',
				),
			),
		);

		Block::render( $text_attrs );
		Block::render( $text_attrs );
		Block::render( $text_attrs );

		$ref   = new ReflectionClass( Block::class );
		$count = $ref->getProperty( 'count' );
		$count->setAccessible( true );

		$this->assertSame( 3, $count->getValue() );
	}

	// resolve_attribute_path()

	public function test_resolve_simple_path(): void {
		$attributes = array( 'title' => 'Hello' );
		$result     = Block::resolve_attribute_path( 'title', $attributes );
		$this->assertSame( 'Hello', $result );
	}

	public function test_resolve_nested_bracket_path(): void {
		$attributes = array(
			'items' => array(
				array( 'content' => 'First' ),
				array( 'content' => 'Second' ),
			),
		);
		$result = Block::resolve_attribute_path( 'items[1].content', $attributes );
		$this->assertSame( 'Second', $result );
	}

	public function test_resolve_nonexistent_path_returns_null(): void {
		$attributes = array( 'title' => 'Hello' );
		$result     = Block::resolve_attribute_path( 'nonexistent', $attributes );
		$this->assertNull( $result );
	}

	public function test_resolve_deeply_nested_path(): void {
		$attributes = array(
			'level1' => array(
				'level2' => array(
					'level3' => 'deep value',
				),
			),
		);
		$result = Block::resolve_attribute_path( 'level1.level2.level3', $attributes );
		$this->assertSame( 'deep value', $result );
	}

	public function test_frontend_inner_blocks_without_attribute_avoids_null_offset_deprecation(): void {
		$deprecations = array();

		set_error_handler(
			static function ( int $severity, string $message ) use ( &$deprecations ): bool {
				if ( E_DEPRECATED === $severity && str_contains( $message, 'null as an array offset' ) ) {
					$deprecations[] = $message;
					return true;
				}

				return false;
			}
		);

		try {
			$output = Block::replace_components(
				'<InnerBlocks />',
				'Nested content',
				false,
				(object) array(),
				array(),
				array(),
				array()
			);
		} finally {
			restore_error_handler();
		}

		$this->assertSame( array(), $deprecations );
		$this->assertStringContainsString( 'Nested content', $output );
		$this->assertStringNotContainsString( '<InnerBlocks', $output );
	}

	public function test_transform_without_block_name_avoids_null_offset_deprecation(): void {
		$attributes   = array( 'blockstudio' => array( 'attributes' => array() ) );
		$block        = array();
		$deprecations = array();

		set_error_handler(
			static function ( int $severity, string $message ) use ( &$deprecations ): bool {
				if ( E_DEPRECATED === $severity && str_contains( $message, 'null as an array offset' ) ) {
					$deprecations[] = $message;
					return true;
				}

				return false;
			}
		);

		try {
			Block::transform( $attributes, $block, null, true, false, array() );
		} finally {
			restore_error_handler();
		}

		$this->assertSame( array(), $deprecations );
	}

	public function test_render_filter_output_resolves_richtext(): void {
		$filter = function ( $html, $block ) {
			if ( 'blockstudio/type-text' === ( $block->name ?? '' ) ) {
				return '<div><RichText attribute="text" tag="h1" class="heading" /></div>';
			}
			return $html;
		};

		add_filter( 'blockstudio/blocks/render', $filter, 10, 2 );

		$result = Block::render(
			array(
				'blockstudio' => array(
					'name'       => 'blockstudio/type-text',
					'attributes' => array( 'text' => 'Hello World' ),
				),
			),
			'',
			'',
			''
		);

		remove_filter( 'blockstudio/blocks/render', $filter );

		$this->assertIsString( $result );
		$this->assertStringNotContainsString( '<RichText', $result, 'RichText pseudo-component should be resolved' );
		$this->assertStringNotContainsString( '<richtext', strtolower( $result ), 'RichText should not appear as raw HTML element' );
	}

	public function test_replace_custom_tag_reads_single_quoted_attributes(): void {
		$block = (object) array( 'name' => 'blockstudio/type-text' );

		foreach ( array( '"', "'" ) as $quote ) {
			$content = "<RichText attribute={$quote}text{$quote} tag={$quote}h2{$quote} class={$quote}heading{$quote} />";

			Block::replace_custom_tag(
				$content,
				'',
				$block,
				array( 'text' => 'Hello World' ),
				'RichText',
				'text'
			);

			$this->assertSame(
				'<h2 attribute="text" tag="h2" class="heading">Hello World</h2>',
				$content,
				"RichText with {$quote} quotes should render its attribute value"
			);
		}
	}

	public function test_component_cleanup_attribute_detection_ignores_plain_text_substrings(): void {
		$method = new ReflectionMethod( Block::class, 'content_has_component_cleanup_attribute' );
		$method->setAccessible( true );
		$attributes = array( 'useBlockProps', 'tag', 'allowedBlocks', 'attribute', 'placeholder' );

		$this->assertFalse(
			$method->invoke(
				null,
				'<span class="vintage-stage">Resolved text without component attributes.</span>',
				$attributes
			)
		);
		$this->assertTrue(
			$method->invoke(
				null,
				'<section useBlockProps class="hero">Content</section>',
				$attributes
			)
		);
		$this->assertTrue(
			$method->invoke(
				null,
				'<InnerBlocks allowedBlocks="[&quot;core/paragraph&quot;]" />',
				$attributes
			)
		);
	}

	public function test_get_option_value_preserves_float_option_keys(): void {
		$data = array(
			'options' => array(
				array(
					'value' => 1.5,
					'label' => 'One and a half',
				),
			),
		);

		$this->assertSame( 'One and a half', Block::get_option_value( $data, 'label', 1.5 ) );
		$this->assertSame( 1.5, Block::get_option_value( $data, 'value', 1.5 ) );
		$this->assertSame(
			array(
				'value' => 1.5,
				'label' => 'One and a half',
			),
			Block::get_option_value( $data, 'both', 1.5 )
		);
	}

	public function test_transform_attributes_maps_multiple_select_without_array_conversion_warning(): void {
		$attributes      = array(
			'caseStudies' => array( 'curated', 'featured' ),
		);
		$attribute_names = array();
		$attribute_data  = array();
		$warning         = null;

		set_error_handler(
			static function ( int $severity, string $message ) use ( &$warning ): bool {
				if ( E_WARNING === $severity && str_contains( $message, 'Array to string conversion' ) ) {
					$warning = $message;
					return true;
				}

				return false;
			}
		);

		try {
			Block::transform_attributes(
				$attributes,
				$attribute_names,
				array(),
				'test/multiple-select',
				array( 'name' => 'test/multiple-select' ),
				false,
				array(
					'caseStudies' => array(
						'type'         => 'array',
						'field'        => 'select',
						'multiple'     => true,
						'returnFormat' => 'label',
						'options'      => array(
							array(
								'value' => 'curated',
								'label' => 'Curated',
							),
							array(
								'value' => 'featured',
								'label' => 'Featured',
							),
						),
					),
				),
				$attribute_data
			);
		} finally {
			restore_error_handler();
		}

		$this->assertNull( $warning );
		$this->assertSame( array( 'Curated', 'Featured' ), $attributes['caseStudies'] );
	}

	public static function single_multiple_option_value_provider(): array {
		$languages = array(
			array(
				'value' => 'de',
				'label' => 'Deutsch',
			),
			array(
				'value' => 'en',
				'label' => 'English',
			),
		);
		$numbers   = array(
			array(
				'value' => 1,
				'label' => 'One',
			),
			array(
				'value' => 2,
				'label' => 'Two',
			),
		);

		return array(
			'select string'               => array( 'select', $languages, 'value', 'de', array( 'de' ) ),
			'select string as both'       => array( 'select', $languages, 'both', 'de', array( $languages[0] ) ),
			'select number'               => array( 'select', $numbers, 'label', 2, array( 'Two' ) ),
			'select numeric string'       => array( 'select', $numbers, 'value', '2', array( 2 ) ),
			'select object'               => array( 'select', $languages, 'label', $languages[1], array( 'English' ) ),
			'select object, plain option' => array(
				'select',
				array( 'red', 'green' ),
				'value',
				array(
					'value' => 'red',
					'label' => 'red',
				),
				array( 'red' ),
			),
			'select list'                 => array( 'select', $languages, 'value', $languages, array( 'de', 'en' ) ),
			'select list of values'       => array( 'select', $languages, 'label', array( 'en', 'de' ), array( 'English', 'Deutsch' ) ),
			'select unknown string'       => array( 'select', $languages, 'value', 'fr', false ),
			'select empty string'         => array( 'select', $languages, 'value', '', false ),
			'select false'                => array( 'select', $languages, 'value', false, false ),
			'select empty list'           => array( 'select', $languages, 'value', array(), false ),
			'checkbox string'             => array( 'checkbox', $languages, 'value', 'en', array( 'en' ) ),
			'checkbox number'             => array( 'checkbox', $numbers, 'value', 1, array( 1 ) ),
			'checkbox object'             => array( 'checkbox', $languages, 'value', $languages[1], array( 'en' ) ),
			'checkbox list'               => array( 'checkbox', $languages, 'value', array_reverse( $languages ), array( 'de', 'en' ) ),
			'fetch select string'         => array(
				'select',
				array(),
				'value',
				'de',
				array(
					array(
						'value' => 'de',
						'label' => 'de',
					),
				),
				array( 'type' => 'fetch' ),
			),
			'fetch select object'         => array( 'select', array(), 'value', $languages[0], array( $languages[0] ), array( 'type' => 'fetch' ) ),
			'fetch select list'           => array( 'select', array(), 'value', $languages, $languages, array( 'type' => 'fetch' ) ),
		);
	}

	public static function zero_option_value_provider(): array {
		$zeros        = array(
			array(
				'value' => 0,
				'label' => 'Zero',
			),
			array(
				'value' => 1,
				'label' => 'One',
			),
		);
		$zero_strings = array(
			array(
				'value' => '0',
				'label' => 'Zero',
			),
			array(
				'value' => '1',
				'label' => 'One',
			),
		);
		$empty_string = array(
			array(
				'value' => '',
				'label' => 'None',
			),
			array(
				'value' => 'all',
				'label' => 'All',
			),
		);

		return array(
			'checkbox zero'                     => array( 'checkbox', $zeros, 'value', 0, array( 0 ) ),
			'checkbox zero as string'           => array( 'checkbox', $zeros, 'value', '0', array( 0 ) ),
			'checkbox zero object'              => array( 'checkbox', $zeros, 'value', $zeros[0], array( 0 ) ),
			'checkbox zero in list'             => array( 'checkbox', $zeros, 'value', array_reverse( $zeros ), array( 0, 1 ) ),
			'checkbox zero as label'            => array( 'checkbox', $zeros, 'label', 0, array( 'Zero' ) ),
			'checkbox zero as both'             => array( 'checkbox', $zeros, 'both', 0, array( $zeros[0] ) ),
			'checkbox zero without option'      => array( 'checkbox', array_slice( $zeros, 1 ), 'value', 0, false ),
			'checkbox string zero'              => array( 'checkbox', $zero_strings, 'value', '0', array( '0' ) ),
			'checkbox string zero object'       => array( 'checkbox', $zero_strings, 'value', $zero_strings[0], array( '0' ) ),
			'checkbox plain zero'               => array( 'checkbox', array( 0, 1 ), 'value', 0, array( 0 ) ),
			'checkbox empty string option'      => array( 'checkbox', $empty_string, 'value', array( $empty_string[0] ), array( '' ) ),
			'select zero'                       => array( 'select', $zeros, 'value', 0, array( 0 ) ),
			'select zero object'                => array( 'select', $zeros, 'label', $zeros[0], array( 'Zero' ) ),
			'select zero in list'               => array( 'select', $zeros, 'value', array_reverse( $zeros ), array( 1, 0 ) ),
			'select string zero'                => array( 'select', $zero_strings, 'value', '0', array( '0' ) ),
			'select string zero in list'        => array( 'select', $zero_strings, 'value', $zero_strings, array( '0', '1' ) ),
			'select plain string zero'          => array( 'select', array( '0', '1' ), 'value', '0', array( '0' ) ),
			'select plain string zero as label' => array( 'select', array( '0', '1' ), 'label', array( '1', '0' ), array( '1', '0' ) ),
		);
	}

	#[\PHPUnit\Framework\Attributes\DataProvider( 'single_multiple_option_value_provider' )]
	#[\PHPUnit\Framework\Attributes\DataProvider( 'zero_option_value_provider' )]
	public function test_transform_attributes_reads_single_multiple_option_value_as_list(
		string $field,
		array $options,
		string $return_format,
		mixed $stored,
		mixed $expected,
		array $populate = array()
	): void {
		$attributes      = array( 'language' => $stored );
		$attribute_names = array();
		$attribute_data  = array();
		$errors          = array();

		set_error_handler(
			static function ( int $severity, string $message ) use ( &$errors ): bool {
				$errors[] = $message;
				return true;
			},
			E_WARNING | E_NOTICE
		);

		try {
			Block::transform_attributes(
				$attributes,
				$attribute_names,
				array(),
				'test/multiple-option',
				array( 'name' => 'test/multiple-option' ),
				false,
				array(
					'language' => array(
						'type'         => 'array',
						'field'        => $field,
						'multiple'     => 'select' === $field,
						'returnFormat' => $return_format,
						'options'      => $options,
						'populate'     => $populate,
					),
				),
				$attribute_data
			);
		} finally {
			restore_error_handler();
		}

		$this->assertSame( array(), $errors );
		$this->assertSame( $expected, $attributes['language'] );
	}

	public function test_repeater_options_render_imported_zero_and_single_objects(): void {
		$values = array(
			'items' => array(
				array( 'checkbox' => 0, 'select' => 0 ),
				array(
					'checkbox' => array( 'value' => 1, 'label' => 'One' ),
					'select'   => array( 'value' => 1, 'label' => 'One' ),
				),
			),
		);
		$html = do_blocks(
			'<!-- wp:blockstudio/type-repeater-option-values ' .
			wp_json_encode( array( 'blockstudio' => array( 'attributes' => $values ) ) ) . ' /-->'
		);

		$this->assertStringContainsString(
			wp_json_encode(
				array(
					'items' => array(
						array( 'checkbox' => array( 0 ), 'select' => array( 0 ) ),
						array( 'checkbox' => array( 1 ), 'select' => array( 1 ) ),
					),
				)
			),
			$html
		);
	}
}
