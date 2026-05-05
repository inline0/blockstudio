<?php

use Blockstudio\Settings;
use PHPUnit\Framework\TestCase;

/**
 * Tests for the Settings class.
 *
 * These tests run inside wp-env where the test theme has a blockstudio.json
 * that overrides many defaults. Tests verify both the resolved values (after
 * JSON cascading) and that filters can further override them.
 */
class SettingsTest extends TestCase {

	private array $filter_callbacks = array();

	protected function setUp(): void {
		$this->reset_singleton();
	}

	protected function tearDown(): void {
		foreach ( $this->filter_callbacks as $cb ) {
			remove_filter( $cb[0], $cb[1], $cb[2] );
		}
		$this->filter_callbacks = array();

		$this->reset_singleton();
	}

	private function reset_singleton(): void {
		$ref = new ReflectionClass( Settings::class );

		$instance = $ref->getProperty( 'instance' );
		$instance->setAccessible( true );
		$instance->setValue( null, null );

		$settings = $ref->getProperty( 'settings' );
		$settings->setAccessible( true );
		$settings->setValue( null, array() );

		$options = $ref->getProperty( 'settings_options' );
		$options->setAccessible( true );
		$options->setValue( null, array() );

		$json = $ref->getProperty( 'settings_json' );
		$json->setAccessible( true );
		$json->setValue( null, null );

		$filters = $ref->getProperty( 'settings_filters' );
		$filters->setAccessible( true );
		$filters->setValue( null, array() );

		$filters_values = $ref->getProperty( 'settings_filters_values' );
		$filters_values->setAccessible( true );
		$filters_values->setValue( null, array() );

		Settings::get_instance();
	}

	private function add_filter( string $name, callable $cb, int $priority = 10 ): void {
		add_filter( $name, $cb, $priority );
		$this->filter_callbacks[] = array( $name, $cb, $priority );
	}

	// Resolved values (defaults merged with test theme's blockstudio.json)

	public function test_tailwind_enabled_from_json(): void {
		$this->assertTrue( Settings::get( 'tailwind/enabled' ) );
	}

	public function test_tailwind_config_from_json(): void {
		$this->assertStringContainsString( '--color-weird', Settings::get( 'tailwind/config' ) );
	}

	public function test_ui_enabled_keeps_default(): void {
		$this->assertFalse( Settings::get( 'ui/enabled' ) );
	}

	public function test_assets_enqueue_from_json(): void {
		$this->assertTrue( Settings::get( 'assets/enqueue' ) );
	}

	public function test_assets_reset_enabled_keeps_default(): void {
		$this->assertFalse( Settings::get( 'assets/reset/enabled' ) );
	}

	public function test_assets_reset_full_width_keeps_default(): void {
		$this->assertSame( array(), Settings::get( 'assets/reset/fullWidth' ) );
	}

	public function test_assets_minify_css_from_json(): void {
		$this->assertTrue( Settings::get( 'assets/minify/css' ) );
	}

	public function test_assets_minify_js_from_json(): void {
		$this->assertTrue( Settings::get( 'assets/minify/js' ) );
	}

	public function test_assets_process_scss_from_json(): void {
		$this->assertTrue( Settings::get( 'assets/process/scss' ) );
	}

	public function test_assets_process_scss_files_from_json(): void {
		$this->assertTrue( Settings::get( 'assets/process/scssFiles' ) );
	}

	public function test_editor_format_on_save_from_json(): void {
		$this->assertTrue( Settings::get( 'editor/formatOnSave' ) );
	}

	public function test_editor_assets_from_json(): void {
		$assets = Settings::get( 'editor/assets' );
		$this->assertIsArray( $assets );
		$this->assertContains( 'blockstudio-editor-test', $assets );
		$this->assertContains( 'wp-block-library-theme', $assets );
	}

	public function test_editor_markup_from_json(): void {
		$markup = Settings::get( 'editor/markup' );
		$this->assertIsString( $markup );
		$this->assertStringContainsString( '<style>', $markup );
	}

	public function test_block_editor_disable_loading_from_json(): void {
		$this->assertFalse( Settings::get( 'blockEditor/disableLoading' ) );
	}

	public function test_block_editor_css_classes_from_json(): void {
		$classes = Settings::get( 'blockEditor/cssClasses' );
		$this->assertIsArray( $classes );
		$this->assertContains( 'wp-block-library-theme', $classes );
	}

	public function test_block_editor_css_variables_from_json(): void {
		$vars = Settings::get( 'blockEditor/cssVariables' );
		$this->assertIsArray( $vars );
		$this->assertContains( 'global-styles-css-custom-properties', $vars );
	}

	public function test_ai_enable_context_generation_from_json(): void {
		$this->assertTrue( Settings::get( 'ai/enableContextGeneration' ) );
	}

	public function test_block_tags_enabled_from_json(): void {
		$this->assertTrue( Settings::get( 'blockTags/enabled' ) );
	}

	public function test_block_tags_allow_keeps_default(): void {
		$this->assertSame( array(), Settings::get( 'blockTags/allow' ) );
	}

	public function test_block_tags_deny_keeps_default(): void {
		$this->assertSame( array(), Settings::get( 'blockTags/deny' ) );
	}

	public function test_dev_grab_enabled_from_json(): void {
		$this->assertTrue( Settings::get( 'dev/grab/enabled' ) );
	}

	public function test_dev_canvas_enabled_from_json(): void {
		$this->assertTrue( Settings::get( 'dev/canvas/enabled' ) );
	}

	public function test_dev_canvas_admin_bar_from_json(): void {
		$this->assertFalse( Settings::get( 'dev/canvas/adminBar' ) );
	}

	public function test_users_ids_from_json(): void {
		$this->assertSame( array( 1 ), Settings::get( 'users/ids' ) );
	}

	public function test_users_roles_keeps_default(): void {
		$this->assertSame( array(), Settings::get( 'users/roles' ) );
	}

	// Unknown paths

	public function test_get_unknown_path_returns_null(): void {
		$this->assertNull( Settings::get( 'nonexistent/path' ) );
	}

	public function test_get_unknown_path_returns_provided_default(): void {
		$this->assertSame( 'fallback', Settings::get( 'nonexistent/path', 'fallback' ) );
	}

	public function test_get_unknown_single_key_returns_null(): void {
		$this->assertNull( Settings::get( 'nope' ) );
	}

	public function test_get_deeply_nested_unknown_returns_null(): void {
		$this->assertNull( Settings::get( 'a/b/c/d/e' ) );
	}

	public function test_unknown_with_default_false(): void {
		$this->assertFalse( Settings::get( 'does/not/exist', false ) );
	}

	public function test_unknown_with_default_array(): void {
		$this->assertSame( array( 'x' ), Settings::get( 'nope/nope', array( 'x' ) ) );
	}

	// Path notation

	public function test_get_top_level_key_returns_full_subtree(): void {
		$tailwind = Settings::get( 'tailwind' );
		$this->assertIsArray( $tailwind );
		$this->assertArrayHasKey( 'enabled', $tailwind );
		$this->assertArrayHasKey( 'config', $tailwind );
	}

	public function test_get_two_level_path(): void {
		$this->assertTrue( Settings::get( 'tailwind/enabled' ) );
	}

	public function test_get_three_level_path(): void {
		$this->assertTrue( Settings::get( 'assets/minify/css' ) );
	}

	public function test_get_assets_subtree(): void {
		$assets = Settings::get( 'assets' );
		$this->assertIsArray( $assets );
		$this->assertArrayHasKey( 'enqueue', $assets );
		$this->assertArrayHasKey( 'reset', $assets );
		$this->assertArrayHasKey( 'minify', $assets );
		$this->assertArrayHasKey( 'process', $assets );
	}

	public function test_get_dev_subtree(): void {
		$dev = Settings::get( 'dev' );
		$this->assertIsArray( $dev );
		$this->assertArrayHasKey( 'grab', $dev );
		$this->assertArrayHasKey( 'canvas', $dev );
		$this->assertArrayHasKey( 'enabled', $dev['grab'] );
		$this->assertArrayHasKey( 'enabled', $dev['canvas'] );
		$this->assertArrayHasKey( 'adminBar', $dev['canvas'] );
	}

	// Filter overrides via get()

	public function test_filter_overrides_json_value_to_false(): void {
		$this->add_filter( 'blockstudio/settings/tailwind/enabled', function () {
			return false;
		} );

		$this->assertFalse( Settings::get( 'tailwind/enabled' ) );
	}

	public function test_filter_overrides_json_string_value(): void {
		$this->add_filter( 'blockstudio/settings/tailwind/config', function () {
			return 'custom-config';
		} );

		$this->assertSame( 'custom-config', Settings::get( 'tailwind/config' ) );
	}

	public function test_filter_overrides_deep_nested_setting(): void {
		$this->add_filter( 'blockstudio/settings/assets/minify/css', function () {
			return false;
		} );

		$this->assertFalse( Settings::get( 'assets/minify/css' ) );
	}

	public function test_filter_on_array_setting(): void {
		$this->add_filter( 'blockstudio/settings/users/ids', function () {
			return array( 10, 20, 30 );
		} );

		$this->assertSame( array( 10, 20, 30 ), Settings::get( 'users/ids' ) );
	}

	public function test_multiple_filters_on_different_settings(): void {
		$this->add_filter( 'blockstudio/settings/tailwind/enabled', function () {
			return false;
		} );
		$this->add_filter( 'blockstudio/settings/dev/grab/enabled', function () {
			return false;
		} );

		$this->assertFalse( Settings::get( 'tailwind/enabled' ) );
		$this->assertFalse( Settings::get( 'dev/grab/enabled' ) );
	}

	public function test_filter_receives_current_value(): void {
		$received = null;
		$this->add_filter( 'blockstudio/settings/tailwind/enabled', function ( $value ) use ( &$received ) {
			$received = $value;
			return $value;
		} );

		Settings::get( 'tailwind/enabled' );
		$this->assertTrue( $received );
	}

	public function test_filter_removed_restores_previous_value(): void {
		$cb = function () {
			return false;
		};
		add_filter( 'blockstudio/settings/tailwind/enabled', $cb );
		$this->assertFalse( Settings::get( 'tailwind/enabled' ) );

		remove_filter( 'blockstudio/settings/tailwind/enabled', $cb );
		$this->assertTrue( Settings::get( 'tailwind/enabled' ) );
	}

	public function test_filter_with_priority(): void {
		$this->add_filter( 'blockstudio/settings/tailwind/enabled', function () {
			return false;
		}, 10 );
		$this->add_filter( 'blockstudio/settings/tailwind/enabled', function () {
			return true;
		}, 20 );

		$this->assertTrue( Settings::get( 'tailwind/enabled' ) );
	}

	public function test_lower_priority_filter_wins_when_last(): void {
		$this->add_filter( 'blockstudio/settings/dev/canvas/enabled', function () {
			return 'first';
		}, 10 );
		$this->add_filter( 'blockstudio/settings/dev/canvas/enabled', function () {
			return 'second';
		}, 20 );

		$this->assertSame( 'second', Settings::get( 'dev/canvas/enabled' ) );
	}

	// Snake case filter compatibility

	public function test_snake_case_filter_works_for_camel_case_path(): void {
		$this->add_filter( 'blockstudio/settings/block_editor/disable_loading', function () {
			return true;
		} );

		$this->assertTrue( Settings::get( 'blockEditor/disableLoading' ) );
	}

	public function test_camel_case_filter_still_works_for_backwards_compat(): void {
		$this->add_filter( 'blockstudio/settings/blockEditor/disableLoading', function () {
			return true;
		} );

		$this->assertTrue( Settings::get( 'blockEditor/disableLoading' ) );
	}

	public function test_snake_case_conversion_on_multiple_segments(): void {
		$this->add_filter( 'blockstudio/settings/editor/format_on_save', function () {
			return false;
		} );

		$this->assertFalse( Settings::get( 'editor/formatOnSave' ) );
	}

	public function test_snake_case_filter_for_ai_setting(): void {
		$this->add_filter( 'blockstudio/settings/ai/enable_context_generation', function () {
			return false;
		} );

		$this->assertFalse( Settings::get( 'ai/enableContextGeneration' ) );
	}

	public function test_snake_case_filter_for_scss_files(): void {
		$this->add_filter( 'blockstudio/settings/assets/process/scss_files', function () {
			return false;
		} );

		$this->assertFalse( Settings::get( 'assets/process/scssFiles' ) );
	}

	public function test_already_snake_case_path_is_unchanged(): void {
		$this->add_filter( 'blockstudio/settings/assets/enqueue', function () {
			return false;
		} );

		$this->assertFalse( Settings::get( 'assets/enqueue' ) );
	}

	// get_all()

	public function test_get_all_returns_array(): void {
		$all = Settings::get_all();
		$this->assertIsArray( $all );
	}

	public function test_get_all_contains_top_level_keys(): void {
		$all  = Settings::get_all();
		$keys = array( 'users', 'assets', 'editor', 'tailwind', 'ui', 'blockEditor', 'ai', 'blockTags', 'dev' );
		foreach ( $keys as $key ) {
			$this->assertArrayHasKey( $key, $all, "Missing top-level key: {$key}" );
		}
	}

	public function test_get_all_reflects_json_values(): void {
		$all = Settings::get_all();
		$this->assertTrue( $all['tailwind']['enabled'] );
		$this->assertTrue( $all['assets']['minify']['css'] );
	}

	// get_json()

	public function test_get_json_returns_array_when_json_exists(): void {
		$json = Settings::get_json();
		$this->assertIsArray( $json );
	}

	public function test_get_json_reflects_json_file_values(): void {
		$json = Settings::get_json();
		$this->assertTrue( $json['tailwind']['enabled'] );
		$this->assertSame( array( 1 ), $json['users']['ids'] );
	}

	// get_options()

	public function test_get_options_returns_array(): void {
		$options = Settings::get_options();
		$this->assertIsArray( $options );
	}

	// get_filters()

	public function test_get_filters_returns_array(): void {
		$filters = Settings::get_filters();
		$this->assertIsArray( $filters );
	}

	public function test_get_filters_contains_top_level_keys(): void {
		$filters = Settings::get_filters();
		$this->assertArrayHasKey( 'tailwind', $filters );
		$this->assertArrayHasKey( 'ui', $filters );
		$this->assertArrayHasKey( 'assets', $filters );
		$this->assertArrayHasKey( 'dev', $filters );
	}

	// get_filters_values()

	public function test_get_filters_values_returns_array(): void {
		$values = Settings::get_filters_values();
		$this->assertIsArray( $values );
	}

	public function test_constructor_filter_tracked_in_filters_values(): void {
		$cb = function () {
			return 'tracked';
		};
		add_filter( 'blockstudio/settings/dev/canvas/enabled', $cb );
		$this->filter_callbacks[] = array( 'blockstudio/settings/dev/canvas/enabled', $cb, 10 );

		$this->reset_singleton();

		$values = Settings::get_filters_values();
		$this->assertArrayHasKey( 'blockstudio/settings/dev/canvas/enabled', $values );
		$this->assertSame( 'tracked', $values['blockstudio/settings/dev/canvas/enabled'] );
	}

	// Singleton

	public function test_get_instance_returns_same_object(): void {
		$a = Settings::get_instance();
		$b = Settings::get_instance();
		$this->assertSame( $a, $b );
	}

	public function test_get_instance_returns_settings_object(): void {
		$this->assertInstanceOf( Settings::class, Settings::get_instance() );
	}

	// Constructor filter cascading

	public function test_constructor_filter_overrides_json_value(): void {
		$cb = function () {
			return false;
		};
		add_filter( 'blockstudio/settings/tailwind/enabled', $cb );
		$this->filter_callbacks[] = array( 'blockstudio/settings/tailwind/enabled', $cb, 10 );

		$this->reset_singleton();

		$all = Settings::get_all();
		$this->assertFalse( $all['tailwind']['enabled'] );
	}

	// JSON loading

	public function test_json_path_returns_string(): void {
		$path = Settings::json_path();
		$this->assertIsString( $path );
		$this->assertStringContainsString( 'blockstudio.json', $path );
	}

	public function test_json_returns_path_when_file_exists(): void {
		$json = Settings::json();
		$this->assertNotFalse( $json );
		$this->assertStringContainsString( 'blockstudio.json', $json );
	}

	// Legacy filter migration
	// When blockstudio.json exists, JSON values merge after migration,
	// so the legacy filter only affects the pre-JSON layer. The JSON
	// value for assets/enqueue (true) takes precedence.

	public function test_legacy_assets_filter_overridden_by_json(): void {
		$cb = function () {
			return false;
		};
		add_filter( 'blockstudio/assets', $cb );
		$this->filter_callbacks[] = array( 'blockstudio/assets', $cb, 10 );

		$this->reset_singleton();

		// JSON sets assets.enqueue = true, which merges on top of migration.
		$all = Settings::get_all();
		$this->assertTrue( $all['assets']['enqueue'] );
	}

	public function test_legacy_assets_filter_combined_with_new_filter(): void {
		$legacy_cb = function () {
			return false;
		};
		add_filter( 'blockstudio/assets', $legacy_cb );
		$this->filter_callbacks[] = array( 'blockstudio/assets', $legacy_cb, 10 );

		// New filter applied via get() takes final precedence.
		$this->add_filter( 'blockstudio/settings/assets/enqueue', function () {
			return false;
		} );

		$this->reset_singleton();

		$this->assertFalse( Settings::get( 'assets/enqueue' ) );
	}
}
