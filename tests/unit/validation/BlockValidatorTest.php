<?php

use Blockstudio\Block_Registry;
use Blockstudio\Block_Validator;
use Blockstudio\Build;
use Blockstudio\Field_Type_Registry;
use Blockstudio\Files;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

function blockstudio_validation_populate_options() {
	++$GLOBALS['blockstudio_validation_populate_calls'];
	return array( 'resolved', 'alternate' );
}

class BlockValidatorTest extends TestCase {

	private const NAME = 'validation-test/fields';
	private string $directory;
	private array $registry_state = array();
	private int $post_id;

	protected function setUp(): void {
		parent::setUp();
		$registry = Block_Registry::instance();
		foreach ( ( new ReflectionObject( $registry ) )->getProperties() as $property ) {
			if ( ! $property->isStatic() ) {
				$this->registry_state[ $property->getName() ] = $property->getValue( $registry );
			}
		}
		$GLOBALS['blockstudio_validation_populate_calls'] = 0;
		Field_Type_Registry::instance()->register( 'validation/object', array( 'attribute' => 'object' ) );
		Field_Type_Registry::instance()->register( 'validation/display', array( 'attribute' => null, 'produces_attribute' => false ) );
		$this->post_id = wp_insert_post( array( 'post_title' => 'Validation option', 'post_status' => 'publish' ) );
		$this->directory = sys_get_temp_dir() . '/bs-validator-' . uniqid();
		wp_mkdir_p( $this->directory . '/block' );
		wp_mkdir_p( $this->directory . '/empty' );
		wp_mkdir_p( $this->directory . '/conflicts' );
		wp_mkdir_p( $this->directory . '/extensions' );
		$this->write( 'block/block.json', array( 'name' => self::NAME, 'title' => 'Validation', 'blockstudio' => array( 'attributes' => $this->fields() ) ) );
		$this->write( 'empty/block.json', array( 'name' => 'validation-test/empty', 'title' => 'Empty', 'blockstudio' => array( 'attributes' => array() ) ) );
		$this->write(
			'conflicts/block.json',
			array(
				'name' => 'validation-test/conflicts',
				'title' => 'Conflicts',
				'blockstudio' => array( 'attributes' => array(
					array( 'id' => 'tone', 'type' => 'text' ),
					array( 'id' => 'note', 'type' => 'text' ),
					array( 'id' => 'rows', 'type' => 'repeater', 'attributes' => array(
						array( 'id' => 'duplicate', 'type' => 'text' ),
						array( 'id' => 'duplicate', 'type' => 'select', 'options' => array( 'a' ) ),
					) ),
				) ),
			)
		);
		foreach ( array( 'block', 'empty', 'conflicts' ) as $folder ) {
			file_put_contents( $this->directory . '/' . $folder . '/index.php', '<div class="validation-result" data-values="<?php echo esc_attr( wp_json_encode( $a ) ); ?>"></div>' );
		}
		$this->write( 'extensions/a-first.json', array(
			'name' => array( self::NAME, 'core/paragraph' ),
			'blockstudio' => array( 'extend' => true, 'attributes' => array(
				array( 'id' => 'extra', 'type' => 'text', 'set' => array( array( 'attribute' => 'data-extra', 'value' => '{attributes.extra}' ) ) ),
				array( 'id' => 'later', 'type' => 'text' ),
				array( 'id' => 'html', 'type' => 'attributes', 'media' => true ),
			) ),
		) );
		$this->write( 'extensions/b-last.json', array(
			'name' => array( self::NAME, 'core/paragraph' ),
			'blockstudio' => array( 'extend' => true, 'attributes' => array( array( 'id' => 'later', 'type' => 'select', 'options' => array( 'b' ) ) ) ),
		) );
		$this->write( 'extensions/conflicts.json', array(
			'name' => 'validation-test/conflicts',
			'blockstudio' => array( 'extend' => true, 'attributes' => array(
				array( 'id' => 'tone', 'type' => 'select', 'options' => array( 'a' ) ),
				array( 'id' => 'note', 'type' => 'textarea' ),
			) ),
		) );
		Build::init( array( 'dir' => $this->directory ) );
		foreach ( array( self::NAME, 'validation-test/empty', 'validation-test/conflicts' ) as $name ) {
			register_block_type( Build::blocks()[ $name ] );
		}
	}

	protected function tearDown(): void {
		foreach ( array( self::NAME, 'validation-test/empty', 'validation-test/conflicts' ) as $name ) {
			unregister_block_type( $name );
		}
		foreach ( $this->registry_state as $name => $value ) {
			( new ReflectionProperty( Block_Registry::class, $name ) )->setValue( Block_Registry::instance(), $value );
		}
		Field_Type_Registry::instance()->unregister( 'validation/object' );
		Field_Type_Registry::instance()->unregister( 'validation/display' );
		wp_delete_post( $this->post_id, true );
		Files::delete_all_files( $this->directory );
		unset( $GLOBALS['blockstudio_validation_populate_calls'] );
		parent::tearDown();
	}

	private function write( string $path, array $data ): void {
		file_put_contents( $this->directory . '/' . $path, wp_json_encode( $data ) );
	}

	private function fields(): array {
		return array(
			array( 'id' => 'text', 'type' => 'text' ),
			array( 'id' => 'number', 'type' => 'number' ),
			array( 'id' => 'toggle', 'type' => 'toggle' ),
			array( 'id' => 'choice', 'type' => 'select', 'options' => array( 'a', 'b', '0' ) ),
			array( 'id' => 'presetChoice', 'type' => 'select', 'options' => array( 'a', 'b' ), 'default' => 'b' ),
			array( 'id' => 'many', 'type' => 'select', 'multiple' => true, 'options' => array( 'a', 'b', '0' ) ),
			array( 'id' => 'checks', 'type' => 'checkbox', 'options' => array( array( 'value' => 'a', 'label' => 'A' ), array( 'value' => 'b', 'label' => 'B' ), array( 'value' => 0, 'label' => 'Zero' ) ) ),
			array( 'id' => 'radio', 'type' => 'radio', 'options' => array( 'a', 'b' ) ),
			array( 'id' => 'tokens', 'type' => 'token' ),
			array( 'id' => 'color', 'type' => 'color', 'options' => array( '#fff' ) ),
			array( 'id' => 'closedColor', 'type' => 'color', 'disableCustomColors' => true, 'options' => array( '#fff' ) ),
			array( 'id' => 'gradient', 'type' => 'gradient', 'options' => array( 'linear-gradient(red, blue)' ) ),
			array( 'id' => 'image', 'type' => 'files', 'returnFormat' => 'id' ),
			array( 'id' => 'gallery', 'type' => 'files', 'returnFormat' => 'id', 'multiple' => true ),
			array( 'id' => 'link', 'type' => 'link' ),
			array( 'id' => 'icon', 'type' => 'icon' ),
			array( 'id' => 'html', 'type' => 'attributes', 'media' => true ),
			array( 'id' => 'duplicates', 'type' => 'repeater', 'attributes' => array(
				array( 'id' => 'choice', 'type' => 'select', 'options' => array( 'a' ) ),
				array( 'id' => 'choice', 'type' => 'select', 'options' => array( 'b' ) ),
			) ),
			array( 'id' => 'notice', 'type' => 'message' ),
			array( 'id' => 'custom', 'type' => 'validation/object' ),
			array( 'id' => 'display', 'type' => 'validation/display' ),
			array( 'id' => 'meta', 'type' => 'group', 'attributes' => array(
				array( 'id' => 'title', 'type' => 'text' ),
				array( 'type' => 'group', 'attributes' => array( array( 'id' => 'note', 'type' => 'textarea' ) ) ),
				array( 'id' => 'dropped', 'type' => 'group', 'attributes' => array( array( 'id' => 'title', 'type' => 'text' ) ) ),
			) ),
			array( 'type' => 'tabs', 'tabs' => array( array( 'attributes' => array( array( 'id' => 'tabText', 'type' => 'text' ) ) ) ) ),
			array( 'id' => 'rows', 'type' => 'repeater', 'min' => 1, 'max' => 2, 'attributes' => array(
				array( 'id' => 'title', 'type' => 'text' ),
				array( 'id' => 'style', 'type' => 'select', 'options' => array( 'a', 'b' ) ),
				array( 'id' => 'photo', 'type' => 'files', 'returnFormat' => 'id' ),
				array( 'type' => 'group', 'attributes' => array( array( 'id' => 'note', 'type' => 'text' ) ) ),
				array( 'id' => 'dropped', 'type' => 'group', 'attributes' => array( array( 'id' => 'title', 'type' => 'text' ) ) ),
				array( 'id' => 'items', 'type' => 'repeater', 'attributes' => array( array( 'id' => 'text', 'type' => 'textarea' ) ) ),
			) ),
			array( 'id' => 'loaded', 'type' => 'select', 'populate' => array( 'type' => 'function', 'function' => 'blockstudio_validation_populate_options' ) ),
			array( 'id' => 'queried', 'type' => 'select', 'populate' => array( 'type' => 'query', 'query' => 'posts', 'arguments' => array( 'post__in' => array( $this->post_id ), 'post_type' => 'post' ), 'returnFormat' => array( 'value' => 'id' ) ) ),
			array( 'id' => 'remote', 'type' => 'select', 'populate' => array( 'type' => 'fetch' ) ),
			array( 'id' => 'dynamic', 'type' => 'select', 'populate' => array( 'type' => 'function', 'function' => 'blockstudio_validation_populate_options', 'arguments' => array( '{attributes.choice.value}' ) ) ),
		);
	}

	private function attrs( array $values, array $disabled = array() ): array {
		return array( 'blockstudio' => array( 'attributes' => $values, 'disabled' => $disabled ) );
	}

	private function validate( array $values ): array {
		$result = bs_validate_block( self::NAME, $this->attrs( $values ) );
		$this->assertIsArray( $result );
		return $result;
	}

	private function rendered( array $attrs, string $name = self::NAME ): array {
		$html = do_blocks( '<!-- wp:' . $name . ' ' . wp_json_encode( $attrs ) . ' /-->' );
		$tags = new WP_HTML_Tag_Processor( $html );
		$this->assertTrue( $tags->next_tag( array( 'class_name' => 'validation-result' ) ), $html );
		return json_decode( $tags->get_attribute( 'data-values' ), true );
	}

	public function test_managed_empty_blocks_and_unmanaged_blocks_are_distinct(): void {
		$this->assertNull( bs_validate_block( 'validation-missing/unknown', array() ) );
		$this->assertSame( array(), bs_validate_block( 'validation-test/empty', array( 'anchor' => 'a', 'metadata' => array( 'name' => 'Example' ) ) ) );
		$this->assertSame( array(), bs_validate_block( self::NAME, array() ) );
	}

	#[DataProvider( 'accepted_values' )]
	public function test_accepts_supported_stored_shapes( array $values ): void {
		$this->assertSame( array(), $this->validate( $values ) );
	}

	public static function accepted_values(): array {
		return array(
			'text and numeric strings' => array( array( 'text' => 12, 'number' => '12.5', 'toggle' => true ) ),
			'option object' => array( array( 'choice' => array( 'value' => 'a', 'label' => 'A' ) ) ),
			'imported multiple scalar' => array( array( 'many' => 'a', 'checks' => 'b' ) ),
			'imported multiple object' => array( array( 'many' => array( 'value' => 'a', 'label' => 'A' ), 'checks' => array( 'value' => 'b', 'label' => 'B' ) ) ),
			'zero as an actual selection' => array( array( 'many' => 0, 'checks' => 0 ) ),
			'mixed selection list' => array( array( 'many' => array( 'a', array( 'value' => 'b', 'label' => 'B' ) ), 'checks' => array( 0, 'a' ) ) ),
			'empty and unset forms' => array( array( 'choice' => '', 'many' => array(), 'checks' => false, 'link' => null, 'text' => false, 'number' => '', 'rows' => false ) ),
			'file references and metadata' => array( array( 'image' => 'asset://hero', 'image__size' => 'large', 'gallery' => array( 12, '13', array( 'id' => 14 ) ) ) ),
			'link and custom objects' => array( array( 'link' => array( 'url' => '/contact/', 'title' => 'Contact' ), 'custom' => array( 'width' => 12 ) ) ),
			'freeform tokens and colors' => array( array( 'tokens' => array( 'x', array( 'value' => 'y', 'label' => 'Y' ) ), 'color' => '#123456', 'gradient' => 'linear-gradient(black, white)' ) ),
			'groups and tabs' => array( array( 'meta_title' => 'Title', 'meta_note' => 'Note', 'tabText' => 'Tab' ) ),
			'nested rows' => array( array( 'rows' => array( array( 'title' => 'One', 'style' => 'a', 'note' => 'Note', 'photo' => 12, 'photo__size' => 'large', 'items' => array( array( 'text' => 'Nested' ) ) ) ) ) ),
			'remote and dynamic values' => array( array( 'remote' => 'not-known-at-registration', 'dynamic' => array( 'value' => 'not-known', 'label' => 'Dynamic' ) ) ),
		);
	}

	#[DataProvider( 'invalid_values' )]
	public function test_reports_wrong_types_and_options_with_storage_paths( array $values, string $code, string $path ): void {
		$findings = $this->validate( $values );
		$this->assertCount( 1, $findings, wp_json_encode( $findings ) );
		$this->assertSame( $code, $findings[0]['code'] );
		$this->assertSame( $path, $findings[0]['path'] );
		$this->assertSame( 'block', $findings[0]['source'] );
		$this->assertSame( array( 'code', 'path', 'source', 'message', 'value', 'expected' ), array_keys( $findings[0] ) );
		$this->assertNotSame( '', $findings[0]['message'] );
	}

	public static function invalid_values(): array {
		return array(
			'unknown root field' => array( array( 'misspelled' => 'x' ), 'unknown_field', 'misspelled' ),
			'message has no value' => array( array( 'notice' => 'x' ), 'unknown_field', 'notice' ),
			'non-attribute custom field' => array( array( 'display' => 'x' ), 'unknown_field', 'display' ),
			'fake size companion' => array( array( 'text__size' => 'x' ), 'unknown_field', 'text__size' ),
			'nested group was dropped' => array( array( 'meta_dropped_title' => 'x' ), 'unknown_field', 'meta_dropped_title' ),
			'text array' => array( array( 'text' => array( 'x' ) ), 'wrong_type', 'text' ),
			'text boolean' => array( array( 'text' => true ), 'wrong_type', 'text' ),
			'non-numeric number' => array( array( 'number' => 'twelve' ), 'wrong_type', 'number' ),
			'non-boolean toggle' => array( array( 'toggle' => 1 ), 'wrong_type', 'toggle' ),
			'bad option object' => array( array( 'choice' => array( 'label' => 'A' ) ), 'wrong_type', 'choice' ),
			'unknown option' => array( array( 'choice' => 'unknown' ), 'unknown_option', 'choice' ),
			'unknown imported multiple option' => array( array( 'many' => 'unknown' ), 'unknown_option', 'many' ),
			'unknown checkbox option' => array( array( 'checks' => array( array( 'value' => 'unknown' ) ) ), 'unknown_option', 'checks/0' ),
			'wrong multiple shape' => array( array( 'many' => array( 'wrong' => 'a' ) ), 'wrong_type', 'many' ),
			'tokens are a list' => array( array( 'tokens' => 'a' ), 'wrong_type', 'tokens' ),
			'wrong token entry' => array( array( 'tokens' => array( array( 'wrong' => 'a' ) ) ), 'wrong_type', 'tokens/0' ),
			'closed palette' => array( array( 'closedColor' => '#000' ), 'unknown_option', 'closedColor' ),
			'file boolean' => array( array( 'image' => true ), 'wrong_type', 'image' ),
			'file map without ID' => array( array( 'image' => array( 'url' => 'x.jpg' ) ), 'wrong_type', 'image' ),
			'negative file ID' => array( array( 'image' => -1 ), 'wrong_type', 'image' ),
			'link scalar' => array( array( 'link' => '/contact/' ), 'wrong_type', 'link' ),
			'icon scalar' => array( array( 'icon' => 'star' ), 'wrong_type', 'icon' ),
			'custom object type' => array( array( 'custom' => 'wrong' ), 'wrong_type', 'custom' ),
			'repeater not a list' => array( array( 'rows' => array( 'title' => 'x' ) ), 'wrong_type', 'rows' ),
			'repeater row not an object' => array( array( 'rows' => array( 'x' ) ), 'wrong_type', 'rows/0' ),
			'repeater minimum' => array( array( 'rows' => array() ), 'too_few_rows', 'rows' ),
			'repeater maximum' => array( array( 'rows' => array( array(), array(), array() ) ), 'too_many_rows', 'rows' ),
			'unknown repeater key' => array( array( 'rows' => array( array( 'unknown' => 'x' ) ) ), 'unknown_field', 'rows/0/unknown' ),
			'unsupported row group' => array( array( 'rows' => array( array( 'dropped_title' => 'x' ) ) ), 'unknown_field', 'rows/0/dropped_title' ),
			'nested option' => array( array( 'rows' => array( array( 'style' => 'unknown' ) ) ), 'unknown_option', 'rows/0/style' ),
			'deep nested storage' => array( array( 'rows' => array( array( 'items' => array( array( 'text' => true ) ) ) ) ), 'wrong_type', 'rows/0/items/0/text' ),
		);
	}

	public function test_reports_invalid_containers_without_throwing(): void {
		foreach ( array( false, 'wrong', array( 'not-an-object' ) ) as $value ) {
			$result = bs_validate_block( self::NAME, array( 'blockstudio' => $value ) );
			$this->assertSame( 'invalid_container', $result[0]['code'] );
			$this->assertSame( 'blockstudio', $result[0]['path'] );
			$result = bs_validate_block( self::NAME, array( 'blockstudio' => array( 'attributes' => $value ) ) );
			$this->assertSame( 'invalid_container', $result[0]['code'] );
			$this->assertSame( 'blockstudio/attributes', $result[0]['path'] );
		}
	}

	public function test_top_level_values_shadowing_and_disabled_fields_follow_rendering(): void {
		$attrs = array( 'text' => 'Top', 'number' => '12.5', 'anchor' => 'anchor' );
		$this->assertSame( array(), bs_validate_block( self::NAME, $attrs ) );
		$this->assertSame( 'Top', $this->rendered( $attrs )['text'] );
		$attrs['blockstudio'] = array( 'attributes' => array( 'text' => 'Nested' ) );
		$findings = bs_validate_block( self::NAME, $attrs );
		$this->assertSame( 'shadowed_value', $findings[0]['code'] );
		$this->assertSame( 'Top', $findings[0]['value'] );
		$this->assertSame( 'Nested', $findings[0]['expected'] );
		$this->assertSame( 'Nested', $this->rendered( $attrs )['text'] );
		$attrs = $this->attrs( array( 'choice' => 'not-an-option', 'toggle' => 'not-a-boolean', 'rows' => array() ), array( 'choice', 'toggle', 'rows' ) );
		$this->assertSame( array(), bs_validate_block( self::NAME, $attrs ) );
		$rendered = $this->rendered( $attrs );
		$this->assertFalse( $rendered['choice'] );
		$this->assertFalse( $rendered['rows'] );
	}

	public function test_static_populate_is_checked_without_running_populate_or_rendering(): void {
		$calls = $GLOBALS['blockstudio_validation_populate_calls'];
		$this->assertGreaterThan( 0, $calls );
		$this->assertSame( array(), $this->validate( array( 'loaded' => 'resolved', 'queried' => (string) $this->post_id ) ) );
		$findings = $this->validate( array( 'loaded' => 'unknown', 'queried' => -1 ) );
		$this->assertSame( array( 'unknown_option', 'unknown_option' ), array_column( $findings, 'code' ) );
		$this->assertSame( array(), $this->validate( array( 'dynamic' => 'unknown', 'remote' => 'unknown' ) ) );
		$this->assertSame( $calls, $GLOBALS['blockstudio_validation_populate_calls'] );
	}

	public function test_extension_fields_work_for_core_blocks_and_later_extensions_win(): void {
		$attrs = $this->attrs( array( 'extra' => 'Hello', 'later' => 'b' ) );
		$this->assertSame( array(), bs_validate_block( 'core/paragraph', $attrs ) );
		$html = do_blocks( '<!-- wp:paragraph ' . wp_json_encode( $attrs ) . ' --><p>Text</p><!-- /wp:paragraph -->' );
		$this->assertStringContainsString( 'data-extra="Hello"', $html );
		$result = bs_validate_block( 'core/paragraph', $this->attrs( array( 'later' => 'a' ) ) );
		$this->assertSame( 'unknown_option', $result[0]['code'] );
		$this->assertSame( 'extension', $result[0]['source'] );
		$this->assertSame( array(), bs_validate_block( 'core/paragraph', array( 'extra' => array( 'not-rendered' ), 'content' => 'Text' ) ) );
	}

	public function test_conflicts_are_reported_for_block_extension_and_repeated_row_definitions(): void {
		$result = bs_validate_block( 'validation-test/conflicts', $this->attrs( array( 'note' => 'Compatible', 'rows' => array( array( 'duplicate' => 'a' ) ) ) ) );
		$this->assertSame( array( 'definition_conflict', 'definition_conflict' ), array_column( $result, 'code' ) );
		$this->assertSame( array( 'tone', 'rows/0/duplicate' ), array_column( $result, 'path' ) );
		$this->assertSame( array( 'block:text', 'extension:select' ), $result[0]['expected'] );
	}

	public function test_findings_match_real_render_normalization_and_do_not_mutate_input(): void {
		$attrs = $this->attrs( array( 'text' => 'Original', 'many' => 'a', 'checks' => 0, 'image' => 'custom-reference', 'rows' => array( array( 'title' => 'Nested', 'style' => 'b' ) ) ) );
		$before = $attrs;
		$this->assertSame( array(), bs_validate_block( self::NAME, $attrs ) );
		$this->assertSame( $before, $attrs );
		$result = $this->rendered( $attrs );
		$this->assertSame( array( 'a' ), $result['many'] );
		$this->assertSame( array( 0 ), $result['checks'] );
		$this->assertSame( 'custom-reference', $result['image'] );
		$this->assertSame( 'b', $result['rows'][0]['style'] );
		$attrs = $this->attrs( array( 'choice' => 'unknown', 'notDeclared' => 'discarded' ) );
		$this->assertSame( array( 'unknown_field', 'unknown_option' ), array_column( bs_validate_block( self::NAME, $attrs ), 'code' ) );
		$result = $this->rendered( $attrs );
		$this->assertFalse( $result['choice'] );
		$this->assertArrayNotHasKey( 'notDeclared', $result );
	}

	public function test_filter_receives_the_original_attrs_and_can_accept_custom_values(): void {
		$attrs = $this->attrs( array( 'choice' => 'external' ) );
		$seen = null;
		$filter = static function ( $findings, $name, $original ) use ( &$seen ) {
			$seen = array( $name, $original, $findings[0]['code'] );
			return array();
		};
		add_filter( 'blockstudio/blocks/attributes/validate', $filter, 10, 3 );
		try {
			$this->assertSame( array(), bs_validate_block( self::NAME, $attrs ) );
			$this->assertSame( array( self::NAME, $attrs, 'unknown_option' ), $seen );
		} finally {
			remove_filter( 'blockstudio/blocks/attributes/validate', $filter, 10 );
		}
	}

	public function test_parsed_blocks_are_walked_through_unmanaged_wrappers_without_following_references(): void {
		$blocks = array( array( 'blockName' => 'validation-missing/wrapper', 'attrs' => array(), 'innerBlocks' => array(
			array( 'blockName' => self::NAME, 'attrs' => $this->attrs( array( 'text' => true ) ), 'innerBlocks' => array() ),
		) ) );
		$result = Block_Validator::blocks( $blocks );
		$this->assertCount( 1, $result );
		$this->assertSame( '0/0', $result[0]['block_path'] );
		$this->assertSame( self::NAME, $result[0]['block_name'] );
	}

	public function test_root_html_attributes_and_bookkeeping_are_not_unknown_fields(): void {
		$attrs = $this->attrs( array( 'data-example' => 'Forwarded', 'aria-label' => 'Label', 'key' => 'stable-key', 'typo' => 'Unknown' ) );
		$findings = bs_validate_block( self::NAME, $attrs );
		$this->assertSame( array( 'typo' ), array_column( $findings, 'path' ) );
		$html = do_blocks( '<!-- wp:' . self::NAME . ' ' . wp_json_encode( $attrs ) . ' /-->' );
		$tags = new WP_HTML_Tag_Processor( $html );
		$this->assertTrue( $tags->next_tag( array( 'class_name' => 'validation-result' ) ), $html );
		$this->assertSame( 'Forwarded', $tags->get_attribute( 'data-example' ) );
		$this->assertSame( 'Label', $tags->get_attribute( 'aria-label' ) );
		$this->assertSame( 'unknown_field', bs_validate_block( 'core/paragraph', $this->attrs( array( 'data-example' => 'Not forwarded' ) ) )[0]['code'] );
		$result = $this->validate( array( 'rows' => array( array( 'data-example' => 'Not a row field' ) ) ) );
		$this->assertSame( 'rows/0/data-example', $result[0]['path'] );
	}

	public function test_unknown_top_level_attributes_are_reported_and_never_reach_the_template(): void {
		$attrs    = array( 'titl' => 'Typo' );
		$findings = bs_validate_block( self::NAME, $attrs );
		$this->assertSame( array( 'unknown_attribute' ), array_column( $findings, 'code' ) );
		$this->assertSame( 'titl', $findings[0]['path'] );
		$this->assertSame( 'block', $findings[0]['source'] );
		$this->assertSame( 'Typo', $findings[0]['value'] );
		$this->assertSame( array( 'code', 'path', 'source', 'message', 'value', 'expected' ), array_keys( $findings[0] ) );
		foreach ( array( 'text', 'rows', 'meta_title', 'extra', 'className', 'lock', 'metadata', 'blockstudio' ) as $name ) {
			$this->assertContains( $name, $findings[0]['expected'] );
		}
		$this->assertNotContains( 'titl', $findings[0]['expected'] );
		$this->assertArrayNotHasKey( 'titl', $this->rendered( $attrs ) );
	}

	public function test_values_stored_without_the_blockstudio_wrapper_name_the_missing_container(): void {
		$attrs    = array( 'attributes' => array( 'text' => 'Lost' ), 'disabled' => array( 'choice' ) );
		$findings = bs_validate_block( self::NAME, $attrs );
		$this->assertSame( array( 'missing_container', 'missing_container' ), array_column( $findings, 'code' ) );
		$this->assertSame( array( 'attributes', 'disabled' ), array_column( $findings, 'path' ) );
		$this->assertSame( array( 'blockstudio/attributes', 'blockstudio/disabled' ), array_column( $findings, 'expected' ) );
		$this->assertStringContainsString( 'blockstudio', $findings[0]['message'] );
		$this->assertSame( 'Kept', $this->rendered( array( 'text' => 'Kept' ) )['text'] );
		$this->assertFalse( $this->rendered( $attrs )['text'] );
	}

	public function test_a_container_member_next_to_the_wrapper_is_a_plain_unknown_attribute(): void {
		$attrs = array( 'blockstudio' => array( 'attributes' => array( 'text' => 'Kept' ) ), 'attributes' => array( 'text' => 'Lost' ) );
		$this->assertSame( array( 'unknown_attribute' ), array_column( bs_validate_block( self::NAME, $attrs ), 'code' ) );
		$this->assertSame( 'Kept', $this->rendered( $attrs )['text'] );
	}

	public function test_unknown_top_level_attributes_do_not_hide_container_findings(): void {
		$findings = bs_validate_block( self::NAME, array( 'blockstudio' => 'wrong', 'titl' => 'Typo' ) );
		$this->assertSame( array( 'unknown_attribute', 'invalid_container' ), array_column( $findings, 'code' ) );
		$findings = bs_validate_block( self::NAME, array( 'titl' => 'Typo', 'blockstudio' => array( 'attributes' => array( 'choice' => 'unknown' ) ) ) );
		$this->assertSame( array( 'unknown_attribute', 'unknown_option' ), array_column( $findings, 'code' ) );
	}

	public function test_attributes_wordpress_registers_or_blockstudio_forwards_are_not_unknown(): void {
		$attrs = array(
			'blockstudio'     => array( 'attributes' => array( 'text' => 'Nested' ) ),
			'className'       => 'is-style-wide',
			'anchor'          => 'section',
			'lock'            => array( 'move' => true ),
			'metadata'        => array( 'name' => 'Label' ),
			'style'           => array( 'color' => array( 'text' => '#000' ) ),
			'id'              => 'forwarded',
			'title'           => 'Forwarded',
			'data-example'    => 'Forwarded',
			'html-data-extra' => 'Forwarded',
			'aria-label'      => 'Label',
			'key'             => 'stable-key',
			'text'            => 'Declared',
			'meta_title'      => 'Group child',
			'extra'           => 'Extension field',
		);
		$unknown = array_filter( bs_validate_block( self::NAME, $attrs ), static fn( array $finding ): bool => in_array( $finding['code'], array( 'unknown_attribute', 'missing_container' ), true ) );
		$this->assertSame( array(), $unknown );
	}

	public function test_blocks_without_blockstudio_fields_do_not_check_top_level_keys(): void {
		$this->assertSame( array(), bs_validate_block( 'core/paragraph', array( 'unregistered' => 'x', 'content' => 'Text' ) ) );
		$this->assertNull( bs_validate_block( 'core/group', array( 'unregistered' => 'x' ) ) );
	}

	public function test_parsed_blocks_report_unknown_top_level_attributes_with_their_block_path(): void {
		$content  = '<!-- wp:' . self::NAME . ' {"attributes":{"text":"Lost"}} /--><!-- wp:' . self::NAME . ' {"blockstudio":{"attributes":{"text":"Kept"}}} /-->';
		$findings = Block_Validator::blocks( parse_blocks( $content ) );
		$this->assertCount( 1, $findings );
		$this->assertSame( '0', $findings[0]['block_path'] );
		$this->assertSame( self::NAME, $findings[0]['block_name'] );
		$this->assertSame( 'missing_container', $findings[0]['code'] );
	}

	public function test_top_level_values_follow_wordpress_attribute_validation(): void {
		$this->assertSame( array(), $this->validate( array( 'radio' => 'a' ) ) );
		$this->assertSame( 'a', $this->rendered( $this->attrs( array( 'radio' => 'a' ) ) )['radio'] );
		$findings = bs_validate_block( self::NAME, array( 'radio' => 'a' ) );
		$this->assertSame( array( 'wrong_type' ), array_column( $findings, 'code' ) );
		$this->assertSame( 'radio', $findings[0]['path'] );
		$this->assertSame( 'object', $findings[0]['expected'] );
		$this->assertFalse( $this->rendered( array( 'radio' => 'a' ) )['radio'] );
		$this->assertSame( array(), bs_validate_block( self::NAME, array( 'radio' => array( 'value' => 'a', 'label' => 'A' ) ) ) );
		$this->assertSame( array(), bs_validate_block( self::NAME, array( 'radio' => '', 'blockstudio' => array( 'disabled' => array( 'radio' ) ) ) ) );
	}

	public function test_top_level_extension_values_do_not_reach_extension_bindings(): void {
		$attrs = array( 'extra' => 'Top' );
		$findings = bs_validate_block( self::NAME, $attrs );
		$this->assertSame( array( 'extension_top_level' ), array_column( $findings, 'code' ) );
		$this->assertSame( 'extension', $findings[0]['source'] );
		$this->assertSame( 'Top', $this->rendered( $attrs )['extra'] );
		$html = do_blocks( '<!-- wp:' . self::NAME . ' ' . wp_json_encode( $attrs ) . ' /-->' );
		$tags = new WP_HTML_Tag_Processor( $html );
		$this->assertTrue( $tags->next_tag(), $html );
		$this->assertNull( $tags->get_attribute( 'data-extra' ) );
		$this->assertSame( array(), $this->validate( array( 'extra' => 'Nested' ) ) );
	}

	public function test_empty_top_level_values_report_when_wordpress_restores_a_default(): void {
		$attrs = array( 'presetChoice' => false );
		$findings = bs_validate_block( self::NAME, $attrs );
		$this->assertSame( 'wrong_type', $findings[0]['code'] );
		$this->assertSame( 'b', $this->rendered( $attrs )['presetChoice'] );
		$this->assertSame( array(), $this->validate( array( 'presetChoice' => false ) ) );
		$this->assertFalse( $this->rendered( $this->attrs( array( 'presetChoice' => false ) ) )['presetChoice'] );
	}

	public function test_palette_controls_store_strings_not_option_objects(): void {
		$values = array( 'color' => '#123456', 'closedColor' => '#fff', 'gradient' => 'linear-gradient(black, white)' );
		$this->assertSame( array(), $this->validate( $values ) );
		$rendered = $this->rendered( $this->attrs( $values ) );
		foreach ( $values as $key => $value ) {
			$this->assertSame( $value, $rendered[ $key ] );
		}
	}

	public function test_first_compatible_duplicate_row_field_controls_rendering(): void {
		$values = array( 'duplicates' => array( array( 'choice' => 'a' ) ) );
		$this->assertSame( array(), $this->validate( $values ) );
		$this->assertSame( 'a', $this->rendered( $this->attrs( $values ) )['duplicates'][0]['choice'] );
		$values['duplicates'][0]['choice'] = 'b';
		$findings = $this->validate( $values );
		$this->assertCount( 1, $findings );
		$this->assertSame( array( 'a' ), $findings[0]['expected'] );
		$this->assertFalse( $this->rendered( $this->attrs( $values ) )['duplicates'][0]['choice'] );
	}

	public function test_html_attribute_pairs_keep_media_metadata_and_reject_invalid_entries(): void {
		$valid = array( array( 'attribute' => 'data-image', 'value' => 'https://example.test/image.jpg', 'data' => array( 'media' => 123 ) ) );
		$this->assertSame( array(), $this->validate( array( 'html' => $valid ) ) );
		$html = do_blocks( '<!-- wp:paragraph ' . wp_json_encode( $this->attrs( array( 'html' => $valid ) ) ) . ' --><p>Text</p><!-- /wp:paragraph -->' );
		$this->assertStringContainsString( 'data-image="https://example.test/image.jpg"', $html );
		$findings = $this->validate( array( 'html' => array( 'invalid', array( 'attribute' => 'data-x', 'value' => array( 'not scalar' ) ) ) ) );
		$this->assertSame( array( 'wrong_type', 'wrong_type', 'wrong_type', 'wrong_type' ), array_column( $findings, 'code' ) );
		$this->assertSame( array( 'html/0', 'html/1', 'html/0', 'html/1' ), array_column( $findings, 'path' ) );
		$this->assertSame( array( 'block', 'block', 'extension', 'extension' ), array_column( $findings, 'source' ) );
	}

	public function test_option_findings_point_to_the_original_stored_selection(): void {
		$findings = $this->validate( array( 'many' => 'unknown' ) );
		$this->assertSame( 'unknown', $findings[0]['value'] );
		$findings = $this->validate( array( 'many' => array( 'a', 'unknown' ) ) );
		$this->assertSame( 'many/1', $findings[0]['path'] );
		$this->assertSame( 'unknown', $findings[0]['value'] );
	}

	public function test_validation_does_not_query_attachments_or_rerun_populate(): void {
		$queries = $GLOBALS['wpdb']->num_queries;
		$calls = $GLOBALS['blockstudio_validation_populate_calls'];
		$this->assertSame( array(), $this->validate( array( 'image' => 2147483647, 'gallery' => array( 'external-reference' ), 'loaded' => 'resolved' ) ) );
		$this->assertSame( $queries, $GLOBALS['wpdb']->num_queries );
		$this->assertSame( $calls, $GLOBALS['blockstudio_validation_populate_calls'] );
	}
}
