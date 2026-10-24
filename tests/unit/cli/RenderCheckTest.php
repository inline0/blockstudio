<?php
/**
 * Render check tests.
 *
 * @package Blockstudio
 */

use Blockstudio\Block_Registry;
use Blockstudio\Build;
use Blockstudio\Files;
use Blockstudio\Render_Check;
use PHPUnit\Framework\TestCase;

class RenderCheckTest extends TestCase {

	private string $directory;
	private array $registry_state = array();

	protected function setUp(): void {
		parent::setUp();

		$registry = Block_Registry::instance();
		foreach ( ( new ReflectionObject( $registry ) )->getProperties() as $property ) {
			if ( ! $property->isStatic() ) {
				$this->registry_state[ $property->getName() ] = $property->getValue( $registry );
			}
		}

		$GLOBALS['render_check_title'] = null;
		$this->directory               = sys_get_temp_dir() . '/bs-render-check-' . uniqid();

		$fields = array( array( 'id' => 'title', 'type' => 'text', 'default' => 'Default title' ) );

		$this->block( 'clean', '<?php $GLOBALS["render_check_title"] = $a["title"]; ?><p><?php echo esc_html( $a["title"] ); ?></p>', $fields, array( 'attributes' => array( 'title' => 'Example title' ) ) );
		$this->block( 'defaults', '<?php $GLOBALS["render_check_title"] = $a["title"]; ?><p></p>', $fields );
		$this->block( 'placeholder', '<p></p>', $fields, array( 'attributes' => array( 'gallery' => array( 'blockstudio' => true, 'type' => 'image', 'amount' => 3 ) ) ) );
		$this->block( 'missing-key', "<p>\n<?php echo esc_html( \$a['absent'] ); ?>\n<?php echo esc_html( \$a['absent'] ); ?></p>", $fields );
		$this->block( 'silenced', '<p><?php echo esc_html( @$a["absent"] ); ?></p>', $fields );
		$this->block( 'deprecated', '<?php trigger_error( "Legacy helper", E_USER_DEPRECATED ); ?><p></p>', $fields );
		$this->block( 'throws', '<?php ob_start(); throw new RuntimeException( "Template failed" ); ?>', $fields );

		Build::init( array( 'dir' => $this->directory ) );

		foreach ( array( 'clean', 'defaults', 'placeholder', 'missing-key', 'silenced', 'deprecated', 'throws' ) as $slug ) {
			register_block_type( Build::blocks()[ 'render-check-test/' . $slug ] );
		}
	}

	protected function tearDown(): void {
		foreach ( array_keys( WP_Block_Type_Registry::get_instance()->get_all_registered() ) as $name ) {
			if ( str_starts_with( $name, 'render-check-test/' ) ) {
				unregister_block_type( $name );
			}
		}
		foreach ( $this->registry_state as $name => $value ) {
			( new ReflectionProperty( Block_Registry::class, $name ) )->setValue( Block_Registry::instance(), $value );
		}
		Files::delete_all_files( $this->directory );
		unset( $GLOBALS['render_check_title'] );
		parent::tearDown();
	}

	private function block( string $slug, string $template, array $fields, ?array $example = null ): void {
		wp_mkdir_p( $this->directory . '/' . $slug );

		$json = array(
			'name'        => 'render-check-test/' . $slug,
			'title'       => $slug,
			'blockstudio' => array( 'attributes' => $fields ),
		);
		if ( null !== $example ) {
			$json['example'] = $example;
		}

		file_put_contents( $this->directory . '/' . $slug . '/block.json', wp_json_encode( $json ) );
		file_put_contents( $this->directory . '/' . $slug . '/index.php', $template );
	}

	public function test_clean_block_renders_its_example_without_problems(): void {
		$result = Render_Check::block( 'render-check-test/clean' );

		$this->assertSame( 'example', $result['case'] );
		$this->assertSame( array(), $result['problems'] );
		$this->assertSame( 'Example title', $GLOBALS['render_check_title'] );
	}

	public function test_block_without_example_renders_with_field_defaults(): void {
		$result = Render_Check::block( 'render-check-test/defaults' );

		$this->assertSame( 'defaults', $result['case'] );
		$this->assertSame( array(), $result['problems'] );
		$this->assertSame( 'Default title', $GLOBALS['render_check_title'] );
	}

	public function test_attributes_replace_the_example(): void {
		$result = Render_Check::block( 'render-check-test/clean', array( 'title' => 'Custom title' ) );

		$this->assertSame( 'attributes', $result['case'] );
		$this->assertSame( 'Custom title', $GLOBALS['render_check_title'] );
	}

	public function test_image_placeholders_of_the_inserter_preview_are_not_rendered(): void {
		$result = Render_Check::block( 'render-check-test/placeholder' );

		$this->assertSame( 'defaults', $result['case'] );
		$this->assertSame( array(), $result['problems'] );
	}

	public function test_undefined_key_is_reported_once_with_template_file_and_line(): void {
		$result = Render_Check::block( 'render-check-test/missing-key' );

		$this->assertCount( 2, $result['problems'] );
		$this->assertSame( 'Warning', $result['problems'][0]['level'] );
		$this->assertStringContainsString( 'absent', $result['problems'][0]['message'] );
		$this->assertSame( $this->directory . '/missing-key/index.php', $result['problems'][0]['file'] );
		$this->assertSame( 2, $result['problems'][0]['line'] );
		$this->assertSame( 3, $result['problems'][1]['line'] );
	}

	public function test_silenced_problems_are_not_reported(): void {
		$this->assertSame( array(), Render_Check::block( 'render-check-test/silenced' )['problems'] );
	}

	public function test_deprecations_are_reported(): void {
		$problems = Render_Check::block( 'render-check-test/deprecated' )['problems'];

		$this->assertCount( 1, $problems );
		$this->assertSame( 'Deprecated', $problems[0]['level'] );
		$this->assertSame( 'Legacy helper', $problems[0]['message'] );
	}

	public function test_thrown_errors_are_reported_and_leave_no_open_buffer(): void {
		$buffers  = ob_get_level();
		$problems = Render_Check::block( 'render-check-test/throws' )['problems'];

		$this->assertCount( 1, $problems );
		$this->assertSame( 'Error', $problems[0]['level'] );
		$this->assertSame( 'Template failed', $problems[0]['message'] );
		$this->assertSame( $buffers, ob_get_level() );
	}

	public function test_error_handler_and_reporting_are_restored(): void {
		$reporting = error_reporting();
		$sentinel  = static fn(): bool => false;

		set_error_handler( $sentinel );

		try {
			Render_Check::block( 'render-check-test/missing-key' );

			$this->assertSame( $reporting, error_reporting() );
			$this->assertSame( $sentinel, set_error_handler( null ) );
			restore_error_handler();
		} finally {
			restore_error_handler();
		}
	}
}
