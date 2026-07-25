<?php

use Blockstudio\Attribute_Builder;
use Blockstudio\Build;
use Blockstudio\Block_Registry;
use Blockstudio\Files;
use PHPUnit\Framework\TestCase;

class BuildTest extends TestCase {

	// blocks()

	public function test_blocks_returns_array(): void {
		$blocks = Build::blocks();
		$this->assertIsArray( $blocks );
	}

	public function test_blocks_is_not_empty(): void {
		$blocks = Build::blocks();
		$this->assertNotEmpty( $blocks );
	}

	public function test_blocks_contains_text_block(): void {
		$blocks = Build::blocks();
		$this->assertArrayHasKey( 'blockstudio/type-text', $blocks );
	}

	public function test_blocks_values_are_wp_block_type(): void {
		$blocks = Build::blocks();
		foreach ( $blocks as $block ) {
			$this->assertInstanceOf( WP_Block_Type::class, $block );
			break;
		}
	}

	public function test_blocks_contain_multiple_test_blocks(): void {
		$blocks     = Build::blocks();
		$test_count = 0;
		foreach ( array_keys( $blocks ) as $name ) {
			if ( str_starts_with( $name, 'blockstudio/type-' ) ) {
				++$test_count;
			}
		}
		$this->assertGreaterThan( 5, $test_count );
	}

	public function test_prepare_blocks_for_client_strips_expanded_populate_options_without_mutating_source(): void {
		$block              = new WP_Block_Type( 'blockstudio-test/client-payload', array() );
		$block->attributes  = array(
			'nativeExample' => array(
				'type'                => 'select',
				'optionsPopulateFull' => array( 'expanded-native' ),
			),
		);
		$block->blockstudio = array(
			'attributes' => array(
				'example' => array(
					'type'                => 'select',
					'optionsPopulate'     => array( 1 ),
					'optionsPopulateFull' => array(
						array(
							'ID'         => 1,
							'post_title' => 'Example',
						),
					),
				),
			),
		);

		$prepared = Build::prepare_blocks_for_client(
			array(
				$block->name => $block,
			)
		);

		$this->assertArrayHasKey(
			'optionsPopulateFull',
			$block->attributes['nativeExample']
		);
		$this->assertArrayHasKey(
			'optionsPopulateFull',
			$block->blockstudio['attributes']['example']
		);
		$this->assertArrayNotHasKey(
			'optionsPopulateFull',
			$prepared[ $block->name ]->attributes['nativeExample']
		);
		$this->assertArrayNotHasKey(
			'optionsPopulateFull',
			$prepared[ $block->name ]->blockstudio['attributes']['example']
		);
		$this->assertSame(
			array( 1 ),
			$prepared[ $block->name ]->blockstudio['attributes']['example']['optionsPopulate']
		);
	}

	public function test_fetch_query_populate_is_not_baked_during_build(): void {
		$attributes = ( new Attribute_Builder() )->build(
			array(
				array(
					'id'       => 'liveUsers',
					'type'     => 'select',
					'populate' => array(
						'fetch'     => true,
						'type'      => 'query',
						'query'     => 'users',
						'arguments' => array(
							'number' => 10,
						),
					),
				),
			)
		);

		$this->assertSame( array(), $attributes['liveUsers']['options'] );
		$this->assertArrayNotHasKey( 'optionsPopulate', $attributes['liveUsers'] );
		$this->assertArrayNotHasKey( 'optionsPopulateFull', $attributes['liveUsers'] );
	}

	public function test_fetch_query_populate_resolves_for_editor_requests(): void {
		$attributes = ( new Attribute_Builder() )->build(
			array(
				array(
					'id'         => 'liveUsers',
					'type'       => 'select',
					'fromEditor' => true,
					'populate'   => array(
						'fetch'     => true,
						'type'      => 'query',
						'query'     => 'users',
						'arguments' => array(
							'number' => 10,
						),
					),
				),
			)
		);

		$this->assertNotEmpty( $attributes['liveUsers']['options'] );
		$this->assertArrayHasKey( 'value', $attributes['liveUsers']['options'][0] );
		$this->assertArrayHasKey( 'label', $attributes['liveUsers']['options'][0] );
	}

	public function test_fetch_query_populate_preserves_defaults_without_baked_options(): void {
		$attributes = ( new Attribute_Builder() )->build(
			array(
				array(
					'id'       => 'liveUsers',
					'type'     => 'select',
					'multiple' => true,
					'default'  => array( 1, 446 ),
					'populate' => array(
						'fetch' => true,
						'type'  => 'query',
						'query' => 'users',
					),
				),
			)
		);

		$this->assertSame( array(), $attributes['liveUsers']['options'] );
		$this->assertSame(
			array(
				array(
					'value' => 1,
					'label' => '1',
				),
				array(
					'value' => 446,
					'label' => '446',
				),
			),
			$attributes['liveUsers']['default']
		);
	}

	public function test_extensions_keep_expanded_populate_options_for_set_templates(): void {
		$found = false;

		foreach ( Build::extensions() as $extension ) {
			foreach ( $extension->attributes ?? array() as $attribute ) {
				if ( 'headingClassSelectPopulateValue' !== ( $attribute['id'] ?? '' ) ) {
					continue;
				}

				$found = true;

				$this->assertArrayHasKey( 'optionsPopulateFull', $attribute );
				$this->assertNotEmpty( $attribute['optionsPopulateFull'] );
			}
		}

		$this->assertTrue( $found );
	}

	// data()

	public function test_data_returns_array(): void {
		$data = Build::data();
		$this->assertIsArray( $data );
	}

	public function test_data_is_not_empty(): void {
		$data = Build::data();
		$this->assertNotEmpty( $data );
	}

	public function test_data_contains_text_block(): void {
		$data = Build::data();
		$this->assertArrayHasKey( 'blockstudio/type-text', $data );
	}

	public function test_data_entry_has_name(): void {
		$data  = Build::data();
		$entry = $data['blockstudio/type-text'];
		$this->assertArrayHasKey( 'name', $entry );
		$this->assertSame( 'blockstudio/type-text', $entry['name'] );
	}

	public function test_data_entry_has_instance(): void {
		$data = Build::data();
		$entry = $data['blockstudio/type-text'];
		$this->assertArrayHasKey( 'instance', $entry );
	}

	public function test_data_and_blocks_share_common_keys(): void {
		$data_keys   = array_keys( Build::data() );
		$blocks_keys = array_keys( Build::blocks() );
		$common      = array_intersect( $data_keys, $blocks_keys );

		$this->assertNotEmpty( $common );
		$this->assertGreaterThan( 10, count( $common ) );
	}

	// extensions()

	public function test_extensions_returns_array(): void {
		$extensions = Build::extensions();
		$this->assertIsArray( $extensions );
	}

	// overrides()

	public function test_overrides_returns_array(): void {
		$overrides = Build::overrides();
		$this->assertIsArray( $overrides );
	}

	// assets()

	public function test_assets_returns_array(): void {
		$assets = Build::assets();
		$this->assertIsArray( $assets );
	}

	public function test_assets_admin_returns_array(): void {
		$assets = Build::assets_admin();
		$this->assertIsArray( $assets );
	}

	public function test_assets_block_editor_returns_array(): void {
		$assets = Build::assets_block_editor();
		$this->assertIsArray( $assets );
	}

	public function test_assets_global_returns_array(): void {
		$assets = Build::assets_global();
		$this->assertIsArray( $assets );
	}

	public function test_get_instance_name_handles_paths_outside_wordpress_root(): void {
		$path = wp_normalize_path( sys_get_temp_dir() . '/blockstudio-outside-root' );

		$this->assertSame( trim( $path, '/\\' ), Build::get_instance_name( $path ) );
	}

	public function test_reserved_asset_prefix_requires_boundary(): void {
		$method = new ReflectionMethod( Build::class, 'asset_basename_matches_prefix' );
		$method->setAccessible( true );

		$this->assertTrue( $method->invoke( null, 'global.css', 'global' ) );
		$this->assertTrue( $method->invoke( null, 'global-view.js', 'global' ) );
		$this->assertFalse( $method->invoke( null, 'globals.css', 'global' ) );
		$this->assertFalse( $method->invoke( null, 'administration.css', 'admin' ) );
	}

	// blade()

	public function test_blade_returns_array(): void {
		$blade = Build::blade();
		$this->assertIsArray( $blade );
	}

	// paths()

	public function test_paths_returns_array(): void {
		$paths = Build::paths();
		$this->assertIsArray( $paths );
	}

	public function test_paths_is_not_empty(): void {
		$paths = Build::paths();
		$this->assertNotEmpty( $paths );
	}

	public function test_files_does_not_duplicate_registered_instances(): void {
		$registry = Block_Registry::instance();

		Build::files();
		$instances_after_first = $registry->get_instances();
		$paths_after_first     = $registry->get_paths();
		$files_after_first     = Build::files();

		$instances_after_second = $registry->get_instances();
		$paths_after_second     = $registry->get_paths();
		$files_after_second     = Build::files();

		$this->assertSame( $instances_after_first, $instances_after_second );
		$this->assertSame( $paths_after_first, $paths_after_second );
		$this->assertSame( array_keys( $files_after_first ), array_keys( $files_after_second ) );
	}

	// has_interactivity()

	public function test_has_interactivity_true_boolean(): void {
		$this->assertTrue( Build::has_interactivity( array( 'interactivity' => true ) ) );
	}

	public function test_has_interactivity_false_boolean(): void {
		$this->assertFalse( Build::has_interactivity( array( 'interactivity' => false ) ) );
	}

	public function test_has_interactivity_with_enqueue_true(): void {
		$this->assertTrue(
			Build::has_interactivity( array( 'interactivity' => array( 'enqueue' => true ) ) )
		);
	}

	public function test_has_interactivity_with_enqueue_false(): void {
		$this->assertFalse(
			Build::has_interactivity( array( 'interactivity' => array( 'enqueue' => false ) ) )
		);
	}

	public function test_has_interactivity_empty_array(): void {
		$this->assertFalse( Build::has_interactivity( array() ) );
	}

	public function test_has_interactivity_missing_key(): void {
		$this->assertFalse( Build::has_interactivity( array( 'attributes' => array() ) ) );
	}

	public function test_has_interactivity_empty_interactivity_array(): void {
		$this->assertFalse( Build::has_interactivity( array( 'interactivity' => array() ) ) );
	}

	// plugin dependencies()

	public function test_normalize_plugin_dependencies_filters_empty_values_and_duplicates(): void {
		$this->assertSame(
			array(
				'woocommerce' => array(),
			),
			Build::normalize_plugin_dependencies(
				array(
					' woocommerce ',
					'advanced-custom-fields/acf.php',
					'woocommerce',
					'',
					42,
				)
			)
		);
	}

	public function test_normalize_plugin_dependencies_accepts_version_constraints(): void {
		$this->assertSame(
			array(
				'woocommerce' => array(
					'version' => '>6',
				),
				'advanced-custom-fields' => array(),
			),
			Build::normalize_plugin_dependencies(
				array(
					'woocommerce'            => array(
						'version' => ' >6 ',
					),
					'advanced-custom-fields' => array(),
				)
			)
		);
	}

	public function test_plugin_dependency_version_constraints_use_version_compare(): void {
		$this->assertTrue( Build::is_plugin_dependency_version_compatible( '6.1.0', '>6' ) );
		$this->assertTrue( Build::is_plugin_dependency_version_compatible( '6.1.0', '6' ) );
		$this->assertTrue( Build::is_plugin_dependency_version_compatible( '6.1.0', '>=6.1' ) );
		$this->assertFalse( Build::is_plugin_dependency_version_compatible( '6.0.0', '>6.0.0' ) );
		$this->assertFalse( Build::is_plugin_dependency_version_compatible( '', '>6' ) );
	}

	public function test_has_active_plugin_dependencies_accepts_slugs_and_version_constraints(): void {
		$active_plugins = get_option( 'active_plugins', array() );
		$slug           = 'blockstudio-dependency-test-plugin';
		$plugin_dir     = WP_PLUGIN_DIR . '/' . $slug;
		$plugin_file    = $slug . '/' . $slug . '.php';

		wp_mkdir_p( $plugin_dir );
		file_put_contents(
			$plugin_dir . '/' . $slug . '.php',
			"<?php\n/**\n * Plugin Name: Blockstudio Dependency Test Plugin\n * Version: 6.2.0\n */\n"
		);
		wp_cache_delete( 'plugins', 'plugins' );

		update_option(
			'active_plugins',
			array(
				$plugin_file,
			)
		);

		try {
			$this->assertTrue(
				Build::has_active_plugin_dependencies( array( $slug ) )
			);
			$this->assertTrue(
				Build::has_active_plugin_dependencies(
					array(
						$slug => array(
							'version' => '>6',
						),
					)
				)
			);
			$this->assertFalse(
				Build::has_active_plugin_dependencies(
					array(
						$slug => array(
							'version' => '>7',
						),
					)
				)
			);
			$this->assertFalse(
				Build::has_active_plugin_dependencies( array( 'missing-plugin' ) )
			);
			$this->assertFalse(
				Build::has_active_plugin_dependencies( array( $slug, 'missing-plugin' ) )
			);
		} finally {
			update_option( 'active_plugins', $active_plugins );

			if ( is_dir( $plugin_dir ) ) {
				Files::delete_all_files( $plugin_dir );
			}

			wp_cache_delete( 'plugins', 'plugins' );
		}
	}

	public function test_blocks_with_missing_or_unmet_plugin_dependencies_are_skipped_during_registration(): void {
		$active_plugins   = get_option( 'active_plugins', array() );
		$tmp_dir          = BLOCKSTUDIO_DIR . '/.tmp-tests/blockstudio-build-dependencies-test-' . uniqid();
		$missing_dir      = $tmp_dir . '/dependency-block';
		$version_dir      = $tmp_dir . '/version-block';
		$missing_name     = 'blockstudio-test/dependency-block';
		$version_name     = 'blockstudio-test/version-block';
		$plugin_slug      = 'blockstudio-version-test-plugin';
		$plugin_dir       = WP_PLUGIN_DIR . '/' . $plugin_slug;
		$plugin_file      = $plugin_slug . '/' . $plugin_slug . '.php';

		wp_mkdir_p( $plugin_dir );
		file_put_contents(
			$plugin_dir . '/' . $plugin_slug . '.php',
			"<?php\n/**\n * Plugin Name: Blockstudio Version Test Plugin\n * Version: 6.2.0\n */\n"
		);
		wp_cache_delete( 'plugins', 'plugins' );

		update_option( 'active_plugins', array( $plugin_file ) );

		mkdir( $missing_dir, 0755, true );
		file_put_contents(
			$missing_dir . '/block.json',
			wp_json_encode(
				array(
					'name'        => $missing_name,
					'title'       => 'Dependency Block',
					'blockstudio' => array(
						'pluginDependencies' => array( 'missing-plugin-for-blockstudio-tests' ),
					),
				)
			)
		);
		file_put_contents( $missing_dir . '/index.php', '<?php // render' );

		mkdir( $version_dir, 0755, true );
		file_put_contents(
			$version_dir . '/block.json',
			wp_json_encode(
				array(
					'name'        => $version_name,
					'title'       => 'Version Block',
					'blockstudio' => array(
						'pluginDependencies' => array(
							$plugin_slug => array(
								'version' => '>7',
							),
						),
					),
				)
			)
		);
		file_put_contents( $version_dir . '/index.php', '<?php // render' );

		try {
			Build::init(
				array(
					'dir' => $tmp_dir,
				)
			);

			$this->assertArrayNotHasKey( $missing_name, Build::blocks() );
			$this->assertArrayNotHasKey( $missing_name, Build::data() );
			$this->assertArrayNotHasKey( $version_name, Build::blocks() );
			$this->assertArrayNotHasKey( $version_name, Build::data() );
		} finally {
			update_option( 'active_plugins', $active_plugins );

			if ( is_dir( $tmp_dir ) ) {
				Files::delete_all_files( $tmp_dir );
			}

			if ( is_dir( $plugin_dir ) ) {
				Files::delete_all_files( $plugin_dir );
			}

			wp_cache_delete( 'plugins', 'plugins' );
		}
	}

	public function test_blocks_attributes_filter_reaches_built_render_attributes(): void {
		$tmp_dir   = sys_get_temp_dir() . '/bs-attr-filter-' . uniqid();
		$block_dir = $tmp_dir . '/filtered-select';
		mkdir( $block_dir, 0755, true );

		$name = 'blockstudio-test/filtered-select';
		file_put_contents(
			$block_dir . '/block.json',
			wp_json_encode(
				array(
					'name'        => $name,
					'title'       => 'Filtered Select',
					'blockstudio' => array(
						'attributes' => array(
							array(
								'id'      => 'variant',
								'type'    => 'select',
								'options' => array(
									array(
										'label' => 'Default',
										'value' => 'default',
									),
								),
								'default' => 'default',
							),
						),
					),
				)
			)
		);
		file_put_contents( $block_dir . '/index.php', '<?php // render' );

		$filter = function ( $attribute, $block ) use ( $name ) {
			if ( ( $block['name'] ?? '' ) === $name && ( $attribute['id'] ?? '' ) === 'variant' ) {
				$attribute['options'][] = array(
					'label' => 'Brand',
					'value' => 'brand',
				);
			}

			return $attribute;
		};
		add_filter( 'blockstudio/blocks/attributes', $filter, 10, 2 );

		try {
			Build::init(
				array(
					'dir' => $tmp_dir,
				)
			);

			$block = Build::blocks()[ $name ] ?? null;
			$this->assertInstanceOf( WP_Block_Type::class, $block );

			$options = array_column( $block->attributes['variant']['options'] ?? array(), 'value' );
			$this->assertContains( 'brand', $options, 'Filter-added select options must reach the built render attributes.' );
		} finally {
			remove_filter( 'blockstudio/blocks/attributes', $filter, 10 );

			if ( is_dir( $tmp_dir ) ) {
				Files::delete_all_files( $tmp_dir );
			}

			Build::refresh_blocks();

			if ( WP_Block_Type_Registry::get_instance()->is_registered( $name ) ) {
				WP_Block_Type_Registry::get_instance()->unregister( $name );
			}
		}
	}

	// refresh_blocks()

	public function test_refresh_blocks_does_not_break_registry(): void {
		$blocks_before = Build::blocks();
		Build::refresh_blocks();
		$blocks_after = Build::blocks();

		$this->assertSame(
			array_keys( $blocks_before ),
			array_keys( $blocks_after )
		);
	}

	public function test_refresh_block_paths_registers_only_changed_topology(): void {
		$tmp_dir        = sys_get_temp_dir() . '/bs-changed-topology-' . uniqid();
		$existing_dir   = $tmp_dir . '/existing';
		$changed_dir    = $tmp_dir . '/changed';
		$unrelated_dir  = $tmp_dir . '/unrelated';
		$existing_name  = 'blockstudio-test/topology-existing';
		$changed_name   = 'blockstudio-test/topology-changed';
		$unrelated_name = 'blockstudio-test/topology-unrelated';
		$observed        = null;
		$observer        = static function ( array $names, array $paths ) use ( &$observed ): void {
			$observed = compact( 'names', 'paths' );
		};

		$write_block = static function ( string $directory, string $name ): void {
			wp_mkdir_p( $directory );
			file_put_contents(
				$directory . '/block.json',
				wp_json_encode(
					array(
						'name'        => $name,
						'title'       => $name,
						'blockstudio' => array(),
					)
				)
			);
			file_put_contents( $directory . '/index.php', '<?php // render' );
		};

		$write_block( $existing_dir, $existing_name );
		Build::init(
			array(
				'dir' => $tmp_dir,
			)
		);

		$write_block( $changed_dir, $changed_name );
		$write_block( $unrelated_dir, $unrelated_name );
		$changed_paths = array(
			wp_normalize_path( $changed_dir . '/block.json' ),
			wp_normalize_path( $changed_dir . '/index.php' ),
		);

		add_action( 'blockstudio/blocks/topology_refreshed', $observer, 10, 2 );

		try {
			$this->assertSame(
				array( $changed_name ),
				Build::refresh_block_paths( $changed_paths )
			);
			$this->assertArrayHasKey( $changed_name, Build::blocks() );
			$this->assertArrayNotHasKey( $unrelated_name, Build::blocks() );
			$this->assertSame(
				array(
					'names' => array( $changed_name ),
					'paths' => $changed_paths,
				),
				$observed
			);
		} finally {
			remove_action( 'blockstudio/blocks/topology_refreshed', $observer, 10 );

			if ( is_dir( $tmp_dir ) ) {
				Files::delete_all_files( $tmp_dir );
			}

			Build::refresh_blocks();

			foreach ( array( $existing_name, $changed_name, $unrelated_name ) as $name ) {
				if ( WP_Block_Type_Registry::get_instance()->is_registered( $name ) ) {
					WP_Block_Type_Registry::get_instance()->unregister( $name );
				}
			}
		}
	}

	// Block type properties

	public function test_block_type_has_render_callback(): void {
		$blocks = Build::blocks();
		$block  = $blocks['blockstudio/type-text'];

		$this->assertNotNull( $block->render_callback );
	}

	public function test_block_type_has_attributes(): void {
		$blocks = Build::blocks();
		$block  = $blocks['blockstudio/type-text'];

		$this->assertIsArray( $block->attributes );
	}

	public function test_block_type_has_blockstudio_data(): void {
		$blocks = Build::blocks();
		$block  = $blocks['blockstudio/type-text'];

		$this->assertIsArray( $block->blockstudio );
		$this->assertArrayHasKey( 'attributes', $block->blockstudio );
	}
}
