<?php

use Blockstudio\Block_Discovery;
use Blockstudio\Files;
use PHPUnit\Framework\TestCase;

class BlockDiscoveryTest extends TestCase {

	private string $tmp_dir;
	private Block_Discovery $discovery;

	protected function setUp(): void {
		$this->tmp_dir   = sys_get_temp_dir() . '/blockstudio-discovery-test-' . uniqid();
		$this->discovery = new Block_Discovery();
		mkdir( $this->tmp_dir, 0755, true );
	}

	protected function tearDown(): void {
		if ( is_dir( $this->tmp_dir ) ) {
			Files::delete_all_files( $this->tmp_dir );
		}
	}

	private function create_block( string $name, array $block_json_extra = array(), string $template = 'index.php' ): string {
		$parts     = explode( '/', $name );
		$block_dir = $this->tmp_dir . '/' . $parts[1];
		mkdir( $block_dir, 0755, true );

		$block_json = array_merge(
			array(
				'name'         => $name,
				'title'        => ucfirst( $parts[1] ),
				'blockstudio'  => array(
					'attributes' => array(),
				),
			),
			$block_json_extra
		);

		file_put_contents( $block_dir . '/block.json', wp_json_encode( $block_json ) );

		if ( 'index.php' === $template ) {
			file_put_contents( $block_dir . '/index.php', '<?php // render' );
		} elseif ( 'index.twig' === $template ) {
			file_put_contents( $block_dir . '/index.twig', '{{ content }}' );
		} elseif ( 'index.blade.php' === $template ) {
			file_put_contents( $block_dir . '/index.blade.php', '@extends("layout")' );
		}

		return $block_dir;
	}

	// discover() return structure

	public function test_discover_returns_expected_keys(): void {
		$results = $this->discovery->discover( $this->tmp_dir, 'test-instance' );

		$this->assertArrayHasKey( 'store', $results );
		$this->assertArrayHasKey( 'registerable', $results );
		$this->assertArrayHasKey( 'blade_templates', $results );
		$this->assertArrayHasKey( 'block_json_data', $results );
		$this->assertArrayHasKey( 'overrides', $results );
	}

	public function test_discover_returns_empty_results_for_nonexistent_path(): void {
		$results = $this->discovery->discover( '/nonexistent/path', 'test-instance' );

		$this->assertSame( array(), $results['store'] );
		$this->assertSame( array(), $results['registerable'] );
		$this->assertSame( array(), $results['blade_templates'] );
		$this->assertSame( array(), $results['block_json_data'] );
		$this->assertSame( array(), $results['overrides'] );
	}

	public function test_discover_returns_empty_results_for_empty_directory(): void {
		$results = $this->discovery->discover( $this->tmp_dir, 'test-instance' );

		$this->assertSame( array(), $results['store'] );
		$this->assertSame( array(), $results['registerable'] );
	}

	// Regular block discovery

	public function test_discovers_block_with_php_template(): void {
		$this->create_block( 'test/my-block' );
		$results = $this->discovery->discover( $this->tmp_dir, 'test-instance' );

		$this->assertArrayHasKey( 'test/my-block', $results['store'] );
		$this->assertArrayHasKey( 'test/my-block', $results['registerable'] );
	}

	public function test_discovers_block_with_twig_template(): void {
		$this->create_block( 'test/twig-block', array(), 'index.twig' );
		$results = $this->discovery->discover( $this->tmp_dir, 'test-instance' );

		$this->assertArrayHasKey( 'test/twig-block', $results['store'] );
		$this->assertArrayHasKey( 'test/twig-block', $results['registerable'] );
	}

	public function test_discovers_multiple_blocks(): void {
		$this->create_block( 'test/block-one' );
		$this->create_block( 'test/block-two' );
		$this->create_block( 'test/block-three' );

		$results = $this->discovery->discover( $this->tmp_dir, 'test-instance' );

		$this->assertCount( 3, $results['registerable'] );
		$this->assertArrayHasKey( 'test/block-one', $results['store'] );
		$this->assertArrayHasKey( 'test/block-two', $results['store'] );
		$this->assertArrayHasKey( 'test/block-three', $results['store'] );
	}

	public function test_block_without_render_template_is_not_a_block(): void {
		$block_dir = $this->tmp_dir . '/no-render';
		mkdir( $block_dir, 0755, true );

		$block_json = array(
			'name'        => 'test/no-render',
			'title'       => 'No Render',
			'blockstudio' => array( 'attributes' => array() ),
		);
		file_put_contents( $block_dir . '/block.json', wp_json_encode( $block_json ) );

		$results = $this->discovery->discover( $this->tmp_dir, 'test-instance' );

		$this->assertArrayNotHasKey( 'test/no-render', $results['registerable'] );
	}

	// Store data structure

	public function test_store_data_has_expected_fields(): void {
		$this->create_block( 'test/data-block' );
		$results = $this->discovery->discover( $this->tmp_dir, 'test-instance' );

		$data = $results['store']['test/data-block'];

		$this->assertSame( 'test/data-block', $data['name'] );
		$this->assertSame( 'test-instance', $data['instance'] );
		$this->assertSame( wp_normalize_path( $this->tmp_dir ), $data['instancePath'] );
		$this->assertFalse( $data['extend'] );
		$this->assertFalse( $data['init'] );
		$this->assertIsArray( $data['files'] );
		$this->assertIsArray( $data['filesPaths'] );
		$this->assertIsArray( $data['folders'] );
		$this->assertIsArray( $data['structureArray'] );
		$this->assertIsString( $data['structure'] );
		$this->assertIsInt( $data['level'] );
		$this->assertIsArray( $data['file'] );
	}

	public function test_store_data_has_scoped_class(): void {
		$this->create_block( 'test/scoped-block' );
		$results = $this->discovery->discover( $this->tmp_dir, 'test-instance' );

		$data = $results['store']['test/scoped-block'];

		$this->assertStringStartsWith( 'bs-', $data['scopedClass'] );
		$this->assertSame( 'bs-' . md5( 'test/scoped-block' ), $data['scopedClass'] );
	}

	public function test_store_data_files_lists_directory_contents(): void {
		$block_dir = $this->create_block( 'test/files-block' );
		file_put_contents( $block_dir . '/style.css', 'body{}' );
		file_put_contents( $block_dir . '/script.js', 'console.log()' );

		$results = $this->discovery->discover( $this->tmp_dir, 'test-instance' );
		$data    = $results['store']['test/files-block'];

		$this->assertContains( 'block.json', $data['files'] );
		$this->assertContains( 'index.php', $data['files'] );
		$this->assertContains( 'style.css', $data['files'] );
		$this->assertContains( 'script.js', $data['files'] );
	}

	public function test_store_data_preview_assets_from_block_json(): void {
		$this->create_block(
			'test/preview-block',
			array(
				'blockstudio' => array(
					'attributes' => array(),
					'editor'     => array(
						'assets' => array( 'preview.css' ),
					),
				),
			)
		);

		$results = $this->discovery->discover( $this->tmp_dir, 'test-instance' );
		$data    = $results['store']['test/preview-block'];

		$this->assertSame( array( 'preview.css' ), $data['previewAssets'] );
	}

	public function test_store_data_preview_assets_empty_when_not_set(): void {
		$this->create_block( 'test/no-preview' );
		$results = $this->discovery->discover( $this->tmp_dir, 'test-instance' );
		$data    = $results['store']['test/no-preview'];

		$this->assertSame( array(), $data['previewAssets'] );
	}

	public function test_store_data_example_from_block_json(): void {
		$this->create_block(
			'test/example-block',
			array(
				'example' => array( 'attributes' => array( 'text' => 'Hello' ) ),
			)
		);

		$results = $this->discovery->discover( $this->tmp_dir, 'test-instance' );
		$data    = $results['store']['test/example-block'];

		$this->assertSame( array( 'attributes' => array( 'text' => 'Hello' ) ), $data['example'] );
	}

	public function test_store_data_example_false_when_not_set(): void {
		$this->create_block( 'test/no-example' );
		$results = $this->discovery->discover( $this->tmp_dir, 'test-instance' );
		$data    = $results['store']['test/no-example'];

		$this->assertFalse( $data['example'] );
	}

	// Registerable data structure

	public function test_registerable_has_expected_fields(): void {
		$this->create_block( 'test/reg-block' );
		$results = $this->discovery->discover( $this->tmp_dir, 'test-instance' );

		$reg = $results['registerable']['test/reg-block'];

		$this->assertArrayHasKey( 'data', $reg );
		$this->assertArrayHasKey( 'block_json', $reg );
		$this->assertArrayHasKey( 'classification', $reg );
		$this->assertArrayHasKey( 'contents', $reg );
	}

	public function test_registerable_classification_flags(): void {
		$this->create_block( 'test/class-block' );
		$results = $this->discovery->discover( $this->tmp_dir, 'test-instance' );

		$classification = $results['registerable']['test/class-block']['classification'];

		$this->assertTrue( $classification['is_blockstudio'] );
		$this->assertTrue( $classification['is_block'] );
		$this->assertFalse( $classification['is_extend'] );
		$this->assertFalse( $classification['is_override'] );
		$this->assertFalse( $classification['is_init'] );
		$this->assertFalse( $classification['is_twig'] );
		$this->assertTrue( $classification['is_php'] );
		$this->assertFalse( $classification['is_blade'] );
		$this->assertFalse( $classification['is_native'] );
		$this->assertFalse( $classification['is_component'] );
	}

	public function test_registerable_block_json_has_name(): void {
		$this->create_block( 'test/json-block' );
		$results = $this->discovery->discover( $this->tmp_dir, 'test-instance' );

		$block_json = $results['registerable']['test/json-block']['block_json'];

		$this->assertSame( 'test/json-block', $block_json['name'] );
	}

	// block_json_data

	public function test_block_json_data_stored_for_discovered_blocks(): void {
		$this->create_block( 'test/json-data' );
		$results = $this->discovery->discover( $this->tmp_dir, 'test-instance' );

		$this->assertArrayHasKey( 'test/json-data', $results['block_json_data'] );
		$this->assertSame( 'test/json-data', $results['block_json_data']['test/json-data']['name'] );
	}

	// Override blocks

	public function test_discovers_override_block(): void {
		$block_dir = $this->tmp_dir . '/override-block';
		mkdir( $block_dir, 0755, true );

		$block_json = array(
			'name'        => 'core/paragraph',
			'blockstudio' => array(
				'override'   => true,
				'attributes' => array(),
			),
		);
		file_put_contents( $block_dir . '/block.json', wp_json_encode( $block_json ) );
		file_put_contents( $block_dir . '/index.php', '<?php // render' );

		$results = $this->discovery->discover( $this->tmp_dir, 'test-instance' );

		$this->assertArrayHasKey( 'core/paragraph-override', $results['registerable'] );
		$this->assertTrue( $results['registerable']['core/paragraph-override']['classification']['is_override'] );
	}

	public function test_override_tracked_in_overrides_array(): void {
		$block_dir = $this->tmp_dir . '/my-override';
		mkdir( $block_dir, 0755, true );

		$block_json = array(
			'name'        => 'core/heading',
			'blockstudio' => array(
				'override'   => true,
				'attributes' => array(),
			),
		);
		file_put_contents( $block_dir . '/block.json', wp_json_encode( $block_json ) );
		file_put_contents( $block_dir . '/index.php', '<?php // override render' );

		$results = $this->discovery->discover( $this->tmp_dir, 'test-instance' );

		// In non-editor mode, $name_file is false so override key = $name = 'core/heading-override'.
		$this->assertArrayHasKey( 'core/heading-override', $results['overrides'] );
		$this->assertSame( 'core/heading-override', $results['overrides']['core/heading-override']['name'] );
	}

	public function test_override_name_has_suffix(): void {
		$block_dir = $this->tmp_dir . '/override-suffix';
		mkdir( $block_dir, 0755, true );

		$block_json = array(
			'name'        => 'core/image',
			'blockstudio' => array(
				'override'   => true,
				'attributes' => array(),
			),
		);
		file_put_contents( $block_dir . '/block.json', wp_json_encode( $block_json ) );
		file_put_contents( $block_dir . '/index.php', '<?php' );

		$results = $this->discovery->discover( $this->tmp_dir, 'test-instance' );

		$this->assertArrayHasKey( 'core/image-override', $results['store'] );
	}

	// Extend blocks

	public function test_discovers_extend_block(): void {
		$block_dir = $this->tmp_dir . '/extend-block';
		mkdir( $block_dir, 0755, true );

		$block_json = array(
			'name'        => 'test/extend-block',
			'blockstudio' => array(
				'extend'     => true,
				'attributes' => array(),
			),
		);
		file_put_contents( $block_dir . '/block.json', wp_json_encode( $block_json ) );

		$results = $this->discovery->discover( $this->tmp_dir, 'test-instance' );

		$this->assertArrayHasKey( 'test/extend-block', $results['registerable'] );
		$this->assertTrue( $results['registerable']['test/extend-block']['classification']['is_extend'] );
	}

	public function test_extend_block_not_classified_as_regular_block(): void {
		$block_dir = $this->tmp_dir . '/extend-only';
		mkdir( $block_dir, 0755, true );

		$block_json = array(
			'name'        => 'test/extend-only',
			'blockstudio' => array(
				'extend'     => true,
				'attributes' => array(),
			),
		);
		file_put_contents( $block_dir . '/block.json', wp_json_encode( $block_json ) );
		file_put_contents( $block_dir . '/index.php', '<?php' );

		$results = $this->discovery->discover( $this->tmp_dir, 'test-instance' );

		$this->assertFalse( $results['registerable']['test/extend-only']['classification']['is_block'] );
		$this->assertTrue( $results['registerable']['test/extend-only']['classification']['is_extend'] );
	}

	// Native blocks (block.json without blockstudio key)

	public function test_discovers_native_block(): void {
		$block_dir = $this->tmp_dir . '/native-block';
		mkdir( $block_dir, 0755, true );

		$block_json = array(
			'name'  => 'my-plugin/native',
			'title' => 'Native Block',
		);
		file_put_contents( $block_dir . '/block.json', wp_json_encode( $block_json ) );

		$results = $this->discovery->discover( $this->tmp_dir, 'test-instance' );

		$this->assertArrayHasKey( 'my-plugin/native', $results['registerable'] );
		$this->assertTrue( $results['registerable']['my-plugin/native']['classification']['is_native'] );
		$this->assertFalse( $results['registerable']['my-plugin/native']['classification']['is_blockstudio'] );
	}

	// Init files

	public function test_discovers_init_file(): void {
		file_put_contents( $this->tmp_dir . '/init.php', '<?php // init code' );

		$results = $this->discovery->discover( $this->tmp_dir, 'test-instance' );

		$found_init = false;
		foreach ( $results['store'] as $data ) {
			if ( $data['init'] ) {
				$found_init = true;
				break;
			}
		}
		$this->assertTrue( $found_init );
	}

	public function test_init_file_with_prefix_discovered(): void {
		file_put_contents( $this->tmp_dir . '/init-custom.php', '<?php // custom init' );

		$results = $this->discovery->discover( $this->tmp_dir, 'test-instance' );

		$found_init = false;
		foreach ( $results['store'] as $data ) {
			if ( $data['init'] ) {
				$found_init = true;
				break;
			}
		}
		$this->assertTrue( $found_init );
	}

	public function test_non_init_php_file_not_discovered_in_normal_mode(): void {
		file_put_contents( $this->tmp_dir . '/helper.php', '<?php // helper' );

		$results = $this->discovery->discover( $this->tmp_dir, 'test-instance' );

		$found = false;
		foreach ( $results['store'] as $data ) {
			if ( isset( $data['init'] ) && $data['init'] ) {
				$found = true;
			}
		}
		$this->assertFalse( $found );
	}

	// Blade templates

	public function test_discovers_blade_template(): void {
		$block_dir = $this->tmp_dir . '/blade-block';
		mkdir( $block_dir, 0755, true );

		$block_json = array(
			'name'        => 'test/blade-block',
			'blockstudio' => array( 'attributes' => array() ),
		);
		file_put_contents( $block_dir . '/block.json', wp_json_encode( $block_json ) );
		file_put_contents( $block_dir . '/index.blade.php', '@extends("layout")' );

		$results = $this->discovery->discover( $this->tmp_dir, 'test-instance' );

		$this->assertArrayHasKey( 'test-instance', $results['blade_templates'] );
	}

	public function test_blade_block_json_still_in_store(): void {
		$block_dir = $this->tmp_dir . '/blade-only';
		mkdir( $block_dir, 0755, true );

		$block_json = array(
			'name'        => 'test/blade-only',
			'blockstudio' => array( 'attributes' => array() ),
		);
		file_put_contents( $block_dir . '/block.json', wp_json_encode( $block_json ) );
		file_put_contents( $block_dir . '/index.blade.php', '@extends("layout")' );

		$results = $this->discovery->discover( $this->tmp_dir, 'test-instance' );

		// The block.json file is processed as a block (has blockstudio + render template).
		$this->assertArrayHasKey( 'test/blade-only', $results['store'] );
		$this->assertArrayHasKey( 'test-instance', $results['blade_templates'] );
	}

	// Classification of block.json for twig blocks
	// The block.json file itself is classified (not the .twig template), so is_twig
	// on the block.json entry is false. But the block IS discovered and registerable.

	public function test_twig_block_is_discovered_and_registerable(): void {
		$this->create_block( 'test/twig-classify', array(), 'index.twig' );
		$results = $this->discovery->discover( $this->tmp_dir, 'test-instance' );

		$this->assertArrayHasKey( 'test/twig-classify', $results['store'] );
		$this->assertArrayHasKey( 'test/twig-classify', $results['registerable'] );
	}

	public function test_php_block_classified_as_non_twig(): void {
		$this->create_block( 'test/php-classify' );
		$results = $this->discovery->discover( $this->tmp_dir, 'test-instance' );

		// block.json itself is not twig, so is_twig is false on the classification.
		$classification = $results['registerable']['test/php-classify']['classification'];
		$this->assertFalse( $classification['is_twig'] );
		$this->assertTrue( $classification['is_php'] );
	}

	// Component blocks

	public function test_component_block_without_template_not_registerable(): void {
		$block_dir = $this->tmp_dir . '/component-block';
		mkdir( $block_dir, 0755, true );

		$block_json = array(
			'name'        => 'test/my-component',
			'blockstudio' => array(
				'component'  => true,
				'attributes' => array(),
			),
		);
		file_put_contents( $block_dir . '/block.json', wp_json_encode( $block_json ) );

		$results = $this->discovery->discover( $this->tmp_dir, 'test-instance' );

		$this->assertArrayNotHasKey( 'test/my-component', $results['registerable'] );
	}

	// Hidden files

	public function test_hidden_files_skipped_in_normal_mode(): void {
		$this->create_block( 'test/visible-block' );

		$block_dir = $this->tmp_dir . '/visible-block';
		file_put_contents( $block_dir . '/.hidden-file', 'secret' );

		$results = $this->discovery->discover( $this->tmp_dir, 'test-instance' );

		// The hidden file should not appear as its own entry in the store.
		foreach ( array_keys( $results['store'] ) as $key ) {
			$this->assertStringNotContainsString( '.hidden-file', $key );
		}
	}

	// Editor mode

	public function test_editor_mode_discovers_all_files(): void {
		$this->create_block( 'test/editor-block' );
		$block_dir = $this->tmp_dir . '/editor-block';
		file_put_contents( $block_dir . '/style.css', 'body{}' );

		$results = $this->discovery->discover( $this->tmp_dir, 'test-instance', true );

		$this->assertNotEmpty( $results['store'] );

		$found_css = false;
		foreach ( array_keys( $results['store'] ) as $key ) {
			if ( str_contains( $key, 'style.css' ) ) {
				$found_css = true;
				break;
			}
		}
		$this->assertTrue( $found_css );
	}

	public function test_editor_mode_uses_file_path_as_store_key(): void {
		$this->create_block( 'test/path-block' );
		$results = $this->discovery->discover( $this->tmp_dir, 'test-instance', true );

		// In editor mode, determine_name returns $file_path, so keys are paths.
		foreach ( array_keys( $results['store'] ) as $key ) {
			$this->assertStringContainsString( '/', $key );
		}
	}

	public function test_editor_mode_does_not_populate_registerable(): void {
		$this->create_block( 'test/no-reg-block' );
		$results = $this->discovery->discover( $this->tmp_dir, 'test-instance', true );

		$this->assertSame( array(), $results['registerable'] );
	}

	public function test_editor_mode_includes_file_value(): void {
		$this->create_block( 'test/value-block' );
		$results = $this->discovery->discover( $this->tmp_dir, 'test-instance', true );

		$found_value = false;
		foreach ( $results['store'] as $data ) {
			if ( isset( $data['value'] ) && ! empty( $data['value'] ) ) {
				$found_value = true;
				break;
			}
		}
		$this->assertTrue( $found_value );
	}

	public function test_editor_mode_store_data_has_name_from_block_json(): void {
		$this->create_block( 'test/editor-name' );
		$results = $this->discovery->discover( $this->tmp_dir, 'test-instance', true );

		// Files in a directory with block.json get the name from block.json.
		$found_named = false;
		foreach ( $results['store'] as $data ) {
			if ( 'test/editor-name' === ( $data['name'] ?? '' ) ) {
				$found_named = true;
				break;
			}
		}
		$this->assertTrue( $found_named );
	}

	// State reset between calls

	public function test_discover_resets_state_between_calls(): void {
		$this->create_block( 'test/first-block' );
		$results1 = $this->discovery->discover( $this->tmp_dir, 'instance-1' );
		$this->assertArrayHasKey( 'test/first-block', $results1['store'] );

		$tmp_dir_2 = sys_get_temp_dir() . '/blockstudio-discovery-test-2-' . uniqid();
		mkdir( $tmp_dir_2, 0755, true );

		$results2 = $this->discovery->discover( $tmp_dir_2, 'instance-2' );
		$this->assertSame( array(), $results2['store'] );
		$this->assertArrayNotHasKey( 'test/first-block', $results2['store'] );

		Files::delete_all_files( $tmp_dir_2 );
	}

	// Getter methods

	public function test_get_store_returns_discovered_store(): void {
		$this->create_block( 'test/getter-block' );
		$this->discovery->discover( $this->tmp_dir, 'test-instance' );

		$store = $this->discovery->get_store();
		$this->assertArrayHasKey( 'test/getter-block', $store );
	}

	public function test_get_registerable_returns_registerable(): void {
		$this->create_block( 'test/reg-getter' );
		$this->discovery->discover( $this->tmp_dir, 'test-instance' );

		$registerable = $this->discovery->get_registerable();
		$this->assertArrayHasKey( 'test/reg-getter', $registerable );
	}

	public function test_get_blade_templates_returns_blade_templates(): void {
		$block_dir = $this->tmp_dir . '/blade-getter';
		mkdir( $block_dir, 0755, true );

		$block_json = array(
			'name'        => 'test/blade-getter',
			'blockstudio' => array( 'attributes' => array() ),
		);
		file_put_contents( $block_dir . '/block.json', wp_json_encode( $block_json ) );
		file_put_contents( $block_dir . '/index.blade.php', '@extends("layout")' );

		$this->discovery->discover( $this->tmp_dir, 'blade-instance' );

		$templates = $this->discovery->get_blade_templates();
		$this->assertArrayHasKey( 'blade-instance', $templates );
	}

	public function test_get_block_json_data_returns_parsed_data(): void {
		$this->create_block( 'test/json-getter' );
		$this->discovery->discover( $this->tmp_dir, 'test-instance' );

		$json_data = $this->discovery->get_block_json_data();
		$this->assertArrayHasKey( 'test/json-getter', $json_data );
		$this->assertSame( 'test/json-getter', $json_data['test/json-getter']['name'] );
	}

	public function test_get_overrides_returns_override_data(): void {
		$block_dir = $this->tmp_dir . '/override-getter';
		mkdir( $block_dir, 0755, true );

		$block_json = array(
			'name'        => 'core/button',
			'blockstudio' => array(
				'override'   => true,
				'attributes' => array(),
			),
		);
		file_put_contents( $block_dir . '/block.json', wp_json_encode( $block_json ) );
		file_put_contents( $block_dir . '/index.php', '<?php' );

		$this->discovery->discover( $this->tmp_dir, 'test-instance' );

		$overrides = $this->discovery->get_overrides();
		$this->assertArrayHasKey( 'core/button-override', $overrides );
	}

	// Getters return empty before discover()

	public function test_get_store_empty_before_discover(): void {
		$this->assertSame( array(), $this->discovery->get_store() );
	}

	public function test_get_registerable_empty_before_discover(): void {
		$this->assertSame( array(), $this->discovery->get_registerable() );
	}

	public function test_get_blade_templates_empty_before_discover(): void {
		$this->assertSame( array(), $this->discovery->get_blade_templates() );
	}

	public function test_get_block_json_data_empty_before_discover(): void {
		$this->assertSame( array(), $this->discovery->get_block_json_data() );
	}

	public function test_get_overrides_empty_before_discover(): void {
		$this->assertSame( array(), $this->discovery->get_overrides() );
	}

	// Block.json without name falls back to file path

	public function test_block_json_without_name_uses_path(): void {
		$block_dir = $this->tmp_dir . '/nameless';
		mkdir( $block_dir, 0755, true );

		$block_json = array(
			'blockstudio' => array( 'attributes' => array() ),
		);
		file_put_contents( $block_dir . '/block.json', wp_json_encode( $block_json ) );
		file_put_contents( $block_dir . '/index.php', '<?php' );

		$results = $this->discovery->discover( $this->tmp_dir, 'test-instance' );

		$found = false;
		foreach ( $results['store'] as $key => $data ) {
			if ( str_contains( $key, 'nameless' ) ) {
				$found = true;
				break;
			}
		}
		$this->assertTrue( $found );
	}

	// Directory entries (discovered via iterator '.' entries in editor mode)

	public function test_directory_with_block_json_not_classified_as_directory(): void {
		$this->create_block( 'test/block-dir' );

		$results = $this->discovery->discover( $this->tmp_dir, 'test-instance' );
		$data    = $results['store']['test/block-dir'];

		$this->assertFalse( $data['directory'] );
	}

	// Edge cases

	public function test_invalid_json_in_block_json_treated_gracefully(): void {
		$block_dir = $this->tmp_dir . '/bad-json';
		mkdir( $block_dir, 0755, true );

		file_put_contents( $block_dir . '/block.json', '{invalid json content' );
		file_put_contents( $block_dir . '/index.php', '<?php' );

		$results = $this->discovery->discover( $this->tmp_dir, 'test-instance' );

		$this->assertIsArray( $results['store'] );
	}

	public function test_nested_block_directories(): void {
		$nested = $this->tmp_dir . '/category/my-block';
		mkdir( $nested, 0755, true );

		$block_json = array(
			'name'        => 'test/nested-block',
			'blockstudio' => array( 'attributes' => array() ),
		);
		file_put_contents( $nested . '/block.json', wp_json_encode( $block_json ) );
		file_put_contents( $nested . '/index.php', '<?php' );

		$results = $this->discovery->discover( $this->tmp_dir, 'test-instance' );

		$this->assertArrayHasKey( 'test/nested-block', $results['store'] );
	}

	public function test_deeply_nested_block(): void {
		$deep = $this->tmp_dir . '/a/b/c/deep-block';
		mkdir( $deep, 0755, true );

		$block_json = array(
			'name'        => 'test/deep-block',
			'blockstudio' => array( 'attributes' => array() ),
		);
		file_put_contents( $deep . '/block.json', wp_json_encode( $block_json ) );
		file_put_contents( $deep . '/index.php', '<?php' );

		$results = $this->discovery->discover( $this->tmp_dir, 'test-instance' );

		$this->assertArrayHasKey( 'test/deep-block', $results['store'] );
		$this->assertGreaterThan( 0, $results['store']['test/deep-block']['level'] );
	}

	// Override with render template but no blockstudio.override flag

	public function test_block_with_blockstudio_but_no_override_flag_is_regular(): void {
		$this->create_block( 'test/regular-block' );
		$results = $this->discovery->discover( $this->tmp_dir, 'test-instance' );

		$classification = $results['registerable']['test/regular-block']['classification'];
		$this->assertFalse( $classification['is_override'] );
		$this->assertTrue( $classification['is_block'] );
	}

	// Multiple override blocks

	public function test_multiple_overrides_tracked_separately(): void {
		foreach ( array( 'core/paragraph', 'core/heading', 'core/image' ) as $i => $name ) {
			$block_dir = $this->tmp_dir . '/override-' . $i;
			mkdir( $block_dir, 0755, true );

			$block_json = array(
				'name'        => $name,
				'blockstudio' => array(
					'override'   => true,
					'attributes' => array(),
				),
			);
			file_put_contents( $block_dir . '/block.json', wp_json_encode( $block_json ) );
			file_put_contents( $block_dir . '/index.php', '<?php' );
		}

		$results = $this->discovery->discover( $this->tmp_dir, 'test-instance' );

		$this->assertArrayHasKey( 'core/paragraph-override', $results['overrides'] );
		$this->assertArrayHasKey( 'core/heading-override', $results['overrides'] );
		$this->assertArrayHasKey( 'core/image-override', $results['overrides'] );
	}

	// Editor mode tracks overrides too

	public function test_editor_mode_still_tracks_overrides(): void {
		$block_dir = $this->tmp_dir . '/editor-override';
		mkdir( $block_dir, 0755, true );

		$block_json = array(
			'name'        => 'core/list',
			'blockstudio' => array(
				'override'   => true,
				'attributes' => array(),
			),
		);
		file_put_contents( $block_dir . '/block.json', wp_json_encode( $block_json ) );
		file_put_contents( $block_dir . '/index.php', '<?php' );

		$results = $this->discovery->discover( $this->tmp_dir, 'test-instance', true );

		$this->assertNotEmpty( $results['overrides'] );
	}

	// Instance path in store data

	public function test_instance_path_matches_base_path(): void {
		$this->create_block( 'test/path-check' );
		$results = $this->discovery->discover( $this->tmp_dir, 'my-theme' );

		$data = $results['store']['test/path-check'];
		$this->assertSame( wp_normalize_path( $this->tmp_dir ), $data['instancePath'] );
		$this->assertSame( 'my-theme', $data['instance'] );
	}

	// Folders field in store data

	public function test_folders_field_lists_subdirectories(): void {
		$block_dir = $this->create_block( 'test/with-folders' );
		mkdir( $block_dir . '/assets', 0755, true );
		mkdir( $block_dir . '/components', 0755, true );

		$results = $this->discovery->discover( $this->tmp_dir, 'test-instance' );
		$data    = $results['store']['test/with-folders'];

		$this->assertContains( 'assets', $data['folders'] );
		$this->assertContains( 'components', $data['folders'] );
	}

	public function test_folders_field_empty_when_no_subdirs(): void {
		$this->create_block( 'test/no-folders' );
		$results = $this->discovery->discover( $this->tmp_dir, 'test-instance' );
		$data    = $results['store']['test/no-folders'];

		$this->assertSame( array(), $data['folders'] );
	}

	// File pathinfo in store data

	public function test_file_field_contains_pathinfo(): void {
		$this->create_block( 'test/pathinfo-block' );
		$results = $this->discovery->discover( $this->tmp_dir, 'test-instance' );
		$data    = $results['store']['test/pathinfo-block'];

		$this->assertArrayHasKey( 'dirname', $data['file'] );
		$this->assertArrayHasKey( 'basename', $data['file'] );
		$this->assertArrayHasKey( 'extension', $data['file'] );
		$this->assertArrayHasKey( 'filename', $data['file'] );
	}

	// Extend block with render template still classified as extend (not block)

	public function test_extend_with_render_template_still_classified_as_extend(): void {
		$block_dir = $this->tmp_dir . '/extend-with-render';
		mkdir( $block_dir, 0755, true );

		$block_json = array(
			'name'        => 'test/extend-render',
			'blockstudio' => array(
				'extend'     => true,
				'attributes' => array(),
			),
		);
		file_put_contents( $block_dir . '/block.json', wp_json_encode( $block_json ) );
		file_put_contents( $block_dir . '/index.php', '<?php' );

		$results = $this->discovery->discover( $this->tmp_dir, 'test-instance' );

		$classification = $results['registerable']['test/extend-render']['classification'];
		$this->assertTrue( $classification['is_extend'] );
		$this->assertFalse( $classification['is_block'] );
	}

	// Scoped class is deterministic

	public function test_scoped_class_deterministic_for_same_name(): void {
		$this->create_block( 'test/deterministic' );
		$results1 = $this->discovery->discover( $this->tmp_dir, 'test-instance' );

		$results2 = $this->discovery->discover( $this->tmp_dir, 'test-instance' );

		$this->assertSame(
			$results1['store']['test/deterministic']['scopedClass'],
			$results2['store']['test/deterministic']['scopedClass']
		);
	}

	public function test_scoped_class_differs_for_different_names(): void {
		$this->create_block( 'test/block-alpha' );
		$this->create_block( 'test/block-beta' );

		$results = $this->discovery->discover( $this->tmp_dir, 'test-instance' );

		$this->assertNotSame(
			$results['store']['test/block-alpha']['scopedClass'],
			$results['store']['test/block-beta']['scopedClass']
		);
	}

	// filesPaths are absolute paths

	public function test_files_paths_are_absolute(): void {
		$this->create_block( 'test/abs-paths' );
		$results = $this->discovery->discover( $this->tmp_dir, 'test-instance' );
		$data    = $results['store']['test/abs-paths'];

		foreach ( $data['filesPaths'] as $path ) {
			$this->assertStringStartsWith( '/', $path );
		}
	}

	// Non-PHP, non-init, non-block files not discovered in normal mode

	public function test_plain_css_file_not_discovered_in_normal_mode(): void {
		file_put_contents( $this->tmp_dir . '/style.css', 'body{}' );

		$results = $this->discovery->discover( $this->tmp_dir, 'test-instance' );

		$found_css = false;
		foreach ( array_keys( $results['store'] ) as $key ) {
			if ( str_contains( $key, 'style.css' ) ) {
				$found_css = true;
			}
		}
		$this->assertFalse( $found_css );
	}

	public function test_plain_css_file_discovered_in_editor_mode(): void {
		file_put_contents( $this->tmp_dir . '/style.css', 'body{}' );

		$results = $this->discovery->discover( $this->tmp_dir, 'test-instance', true );

		$found_css = false;
		foreach ( array_keys( $results['store'] ) as $key ) {
			if ( str_contains( $key, 'style.css' ) ) {
				$found_css = true;
			}
		}
		$this->assertTrue( $found_css );
	}

	// Registerable contents field contains block.json string

	public function test_registerable_contents_is_block_json_string(): void {
		$this->create_block( 'test/contents-block' );
		$results = $this->discovery->discover( $this->tmp_dir, 'test-instance' );

		$contents = $results['registerable']['test/contents-block']['contents'];
		$decoded  = json_decode( $contents, true );

		$this->assertIsArray( $decoded );
		$this->assertSame( 'test/contents-block', $decoded['name'] );
	}

	// Init file gets synthetic block_json with name.
	// The store key for init files is the file path (from determine_name fallback),
	// but the block_json inside has a synthetic 'init-...' name.

	public function test_init_file_has_synthetic_name_in_block_json(): void {
		file_put_contents( $this->tmp_dir . '/init.php', '<?php // init' );

		$results = $this->discovery->discover( $this->tmp_dir, 'test-instance' );

		foreach ( $results['block_json_data'] as $json ) {
			if ( isset( $json['name'] ) && str_starts_with( $json['name'], 'init-' ) ) {
				$this->assertStringStartsWith( 'init-', $json['name'] );
				return;
			}
		}
		$this->fail( 'No init block_json_data entry found' );
	}

	// Override in editor mode uses block.json name for override key

	public function test_editor_mode_override_uses_block_json_name_for_key(): void {
		$block_dir = $this->tmp_dir . '/editor-ov';
		mkdir( $block_dir, 0755, true );

		$block_json = array(
			'name'        => 'core/quote',
			'blockstudio' => array(
				'override'   => true,
				'attributes' => array(),
			),
		);
		file_put_contents( $block_dir . '/block.json', wp_json_encode( $block_json ) );
		file_put_contents( $block_dir . '/index.php', '<?php' );

		$results = $this->discovery->discover( $this->tmp_dir, 'test-instance', true );

		// In editor mode, $name_file is set from block.json, so override key = $name_file = 'core/quote'.
		$this->assertArrayHasKey( 'core/quote', $results['overrides'] );
	}

	// Block with both block.json blockstudio and render template

	public function test_block_with_both_blockstudio_and_render_is_block(): void {
		$this->create_block( 'test/full-block' );
		$results = $this->discovery->discover( $this->tmp_dir, 'test-instance' );

		$classification = $results['registerable']['test/full-block']['classification'];
		$this->assertTrue( $classification['is_block'] );
		$this->assertTrue( $classification['is_blockstudio'] );
	}

	// Empty block.json (valid JSON but no meaningful keys)

	public function test_empty_block_json_not_registerable(): void {
		$block_dir = $this->tmp_dir . '/empty-json';
		mkdir( $block_dir, 0755, true );

		file_put_contents( $block_dir . '/block.json', '{}' );
		file_put_contents( $block_dir . '/index.php', '<?php' );

		$results = $this->discovery->discover( $this->tmp_dir, 'test-instance' );

		// No blockstudio key, no name key -> not blockstudio, not native.
		$this->assertEmpty( $results['registerable'] );
	}

	// Multiple instances

	public function test_different_instances_produce_correct_instance_field(): void {
		$this->create_block( 'test/inst-block' );
		$results = $this->discovery->discover( $this->tmp_dir, 'my-custom-instance' );

		$data = $results['store']['test/inst-block'];
		$this->assertSame( 'my-custom-instance', $data['instance'] );
	}
}
