<?php
/**
 * Optional feature registration tests.
 *
 * @package Blockstudio
 */

use Blockstudio\Assets;
use Blockstudio\Block_Editor_Policy;
use Blockstudio\Build_Cache;
use Blockstudio\Canvas;
use Blockstudio\Devtools;
use Blockstudio\Link_Preload;
use Blockstudio\LLM;
use Blockstudio\Media;
use Blockstudio\Performance_Measurement;
use Blockstudio\Settings;
use Blockstudio\Tailwind;
use Blockstudio\Theme_Defaults;
use Blockstudio\Ui;
use Blockstudio\WordPress_Optimizations;
use PHPUnit\Framework\TestCase;

/**
 * Verifies disabled settings do not attach operational hooks.
 */
class FeatureRegistrationTest extends TestCase {

	/**
	 * Registered setting filters.
	 *
	 * @var array<int,array{string,callable}>
	 */
	private array $filters = array();

	protected function tearDown(): void {
		foreach ( $this->filters as $filter ) {
			remove_filter( $filter[0], $filter[1] );
		}

		$this->filters = array();
		Settings::reset();
		parent::tearDown();
	}

	public function test_disabled_tailwind_registers_no_runtime_or_editor_filters(): void {
		$this->disable( 'tailwind/enabled' );
		$tailwind = new Tailwind();

		$this->assertFalse( has_filter( 'blockstudio/buffer/output', array( $tailwind, 'compile' ) ) );
		$this->assertFalse( has_filter( 'block_editor_settings_all', array( $tailwind, 'inject_editor_styles' ) ) );
	}

	public function test_disabled_canvas_registers_no_admin_or_rest_actions(): void {
		$this->disable( 'dev/canvas/enabled' );
		$canvas = new Canvas();

		$this->assertFalse( has_action( 'admin_menu', array( $canvas, 'register_admin_page' ) ) );
		$this->assertFalse( has_action( 'admin_enqueue_scripts', array( $canvas, 'enqueue_admin_assets' ) ) );
		$this->assertFalse( has_action( 'rest_api_init', array( $canvas, 'register_endpoints' ) ) );
	}

	public function test_disabled_devtools_registers_no_frontend_enqueue_action(): void {
		$this->disable( 'dev/grab/enabled' );
		$devtools = new Devtools();

		$this->assertFalse( has_action( 'wp_enqueue_scripts', array( $devtools, 'enqueue' ) ) );
	}

	public function test_disabled_llm_context_registers_no_route_action(): void {
		$this->disable( 'ai/enableContextGeneration' );
		$llm = new LLM();

		$this->assertFalse( has_action( 'template_redirect', array( $llm, 'serve' ) ) );
	}

	public function test_enabled_optional_features_register_their_operational_hooks(): void {
		$this->enable( 'tailwind/enabled' );
		$this->enable( 'dev/canvas/enabled' );
		$this->enable( 'dev/grab/enabled' );
		$this->enable( 'ai/enableContextGeneration' );

		$tailwind = new Tailwind();
		$canvas   = new Canvas();
		$devtools = new Devtools();
		$llm      = new LLM();

		try {
			$this->assertNotFalse( has_filter( 'blockstudio/buffer/output', array( $tailwind, 'compile' ) ) );
			$this->assertNotFalse( has_action( 'rest_api_init', array( $canvas, 'register_endpoints' ) ) );
			$this->assertNotFalse( has_action( 'wp_enqueue_scripts', array( $devtools, 'enqueue' ) ) );
			$this->assertNotFalse( has_action( 'template_redirect', array( $llm, 'serve' ) ) );
		} finally {
			remove_filter( 'blockstudio/buffer/output', array( $tailwind, 'compile' ), 999999 );
			remove_filter( 'block_editor_settings_all', array( $tailwind, 'inject_editor_styles' ), PHP_INT_MAX );
			remove_action( 'admin_menu', array( $canvas, 'register_admin_page' ) );
			remove_action( 'admin_enqueue_scripts', array( $canvas, 'enqueue_admin_assets' ) );
			remove_action( 'rest_api_init', array( $canvas, 'register_endpoints' ) );
			remove_action( 'wp_enqueue_scripts', array( $devtools, 'enqueue' ) );
			remove_action( 'template_redirect', array( $llm, 'serve' ) );
		}
	}

	public function test_default_off_runtime_settings_register_no_operational_hooks(): void {
		$this->assertFalse( has_action( 'after_setup_theme', array( Theme_Defaults::class, 'enable_title_tag' ) ) );
		$this->assertFalse(
			has_filter( 'site_transient_update_themes', array( Theme_Defaults::class, 'suppress_directory_updates' ) )
		);
		$this->assertFalse( has_action( 'wp_enqueue_scripts', array( Link_Preload::class, 'enqueue' ) ) );
		$this->assertFalse( has_action( 'wp_enqueue_scripts', array( Media::class, 'enqueue' ) ) );
		$this->assertFalse( has_action( 'send_headers', array( Performance_Measurement::class, 'send_headers' ) ) );
		$this->assertFalse( has_filter( 'xmlrpc_enabled', array( WordPress_Optimizations::class, 'return_false' ) ) );
		$this->assertFalse(
			has_filter( 'block_editor_settings_all', array( WordPress_Optimizations::class, 'filter_block_editor_settings' ) )
		);
		$this->assertFalse(
			has_action( 'wp_enqueue_scripts', array( WordPress_Optimizations::class, 'optimize_frontend_assets' ) )
		);
		$this->assertFalse(
			has_filter( 'wp_editor_set_quality', array( WordPress_Optimizations::class, 'filter_image_quality' ) )
		);
		$this->assertFalse(
			has_filter( 'heartbeat_settings', array( WordPress_Optimizations::class, 'filter_heartbeat_settings' ) )
		);
	}

	public function test_disabled_ui_registers_no_build_action(): void {
		$this->disable( 'ui/enabled' );
		$initialized = new ReflectionProperty( Ui::class, 'initialized' );
		$before      = (bool) $initialized->getValue();
		$hook_before = has_action( 'init', array( Ui::class, 'register' ) );

		try {
			$initialized->setValue( null, false );
			Ui::init();

			$this->assertFalse( $initialized->getValue() );
			$this->assertSame( $hook_before, has_action( 'init', array( Ui::class, 'register' ) ) );
		} finally {
			$initialized->setValue( null, $before );
		}
	}

	public function test_disabled_build_cache_registers_no_invalidation_actions(): void {
		$this->disable( 'cache/enabled' );
		$registered = new ReflectionProperty( Build_Cache::class, 'hooks_registered' );
		$before     = (bool) $registered->getValue();

		try {
			$registered->setValue( null, false );
			Build_Cache::init();

			$this->assertFalse( $registered->getValue() );
		} finally {
			$registered->setValue( null, $before );
		}
	}

	public function test_disabled_asset_reset_and_editor_enhancement_register_no_hooks(): void {
		$this->disable( 'assets/reset/enabled' );
		$this->disable( 'blockEditor/enhance' );
		$this->disable( 'assets/reset/fullWidth' );
		$assets = new Assets();

		$assets->register_configured_hooks();

		$this->assertFalse( has_action( 'wp_enqueue_scripts', array( $assets, 'maybe_reset_styles' ) ) );
		$this->assertFalse( has_action( 'admin_enqueue_scripts', array( $assets, 'maybe_reset_styles' ) ) );
		$this->assertFalse( has_action( 'admin_head', array( $assets, 'render_parent_editor_enhancement_styles' ) ) );
		$this->assertFalse( has_filter( 'admin_body_class', array( $assets, 'add_parent_editor_enhancement_body_class' ) ) );
		$this->assertFalse( has_filter( 'block_editor_settings_all', array( $assets, 'maybe_reset_editor_styles' ) ) );
		$this->assertFalse( has_filter( 'block_editor_settings_all', array( $assets, 'add_canvas_body_classes' ) ) );
		$this->assertFalse( has_filter( 'block_editor_settings_all', array( $assets, 'maybe_fullwidth_editor' ) ) );
	}

	public function test_default_editor_policy_registers_no_noop_callbacks(): void {
		$this->assertFalse(
			has_filter( 'allowed_block_types_all', array( Block_Editor_Policy::class, 'filter_allowed_block_types' ) )
		);
		$this->assertFalse(
			has_filter( 'block_categories_all', array( Block_Editor_Policy::class, 'filter_block_categories' ) )
		);
		$this->assertFalse(
			has_filter( 'should_load_remote_block_patterns', array( Block_Editor_Policy::class, 'filter_remote_patterns' ) )
		);
		$this->assertFalse(
			has_filter( 'image_size_names_choose', array( Block_Editor_Policy::class, 'filter_image_sizes' ) )
		);
	}

	public function test_configured_editor_policies_register_only_their_callbacks(): void {
		$this->disable( 'blockEditor/blocks/directory' );
		$this->set_value( 'blockEditor/blocks/allow', array( 'core/*' ) );
		$this->set_value( 'blockEditor/blocks/categories/rename', array( 'text' => 'Writing' ) );
		$this->set_value( 'blockEditor/blocks/styles/deny', array( 'core/image' => array( 'rounded' ) ) );
		$this->disable( 'blockEditor/patterns/remote' );
		$this->set_value( 'blockEditor/patterns/categories/order', array( 'featured' ) );
		$this->disable( 'blockEditor/media/openverse' );
		$this->set_value( 'blockEditor/media/imageSizes/deny', array( 'medium' ) );
		$this->set_value( 'blockEditor/blocks/legacyWidgets/hide', array( 'archives' ) );

		$initialized = new ReflectionProperty( Block_Editor_Policy::class, 'initialized' );
		$before      = (bool) $initialized->getValue();

		try {
			$initialized->setValue( null, false );
			Block_Editor_Policy::init();

			$this->assertNotFalse(
				has_action( 'enqueue_block_editor_assets', array( Block_Editor_Policy::class, 'maybe_disable_block_directory' ) )
			);
			$this->assertNotFalse(
				has_filter( 'allowed_block_types_all', array( Block_Editor_Policy::class, 'filter_allowed_block_types' ) )
			);
			$this->assertNotFalse(
				has_filter( 'block_categories_all', array( Block_Editor_Policy::class, 'filter_block_categories' ) )
			);
			$this->assertNotFalse(
				has_filter( 'should_load_remote_block_patterns', array( Block_Editor_Policy::class, 'filter_remote_patterns' ) )
			);
			$this->assertNotFalse(
				has_filter( 'block_editor_settings_all', array( Block_Editor_Policy::class, 'filter_editor_settings' ) )
			);
			$this->assertNotFalse(
				has_filter( 'image_size_names_choose', array( Block_Editor_Policy::class, 'filter_image_sizes' ) )
			);
			$this->assertNotFalse(
				has_filter(
					'widget_types_to_hide_from_legacy_widget_block',
					array( Block_Editor_Policy::class, 'filter_hidden_legacy_widgets' )
				)
			);
			$this->assertNotFalse(
				has_action( 'init', array( Block_Editor_Policy::class, 'apply_late_init_policy' ) )
			);
		} finally {
			remove_action(
				'enqueue_block_editor_assets',
				array( Block_Editor_Policy::class, 'maybe_disable_block_directory' ),
				0
			);
			remove_filter( 'allowed_block_types_all', array( Block_Editor_Policy::class, 'filter_allowed_block_types' ) );
			remove_filter( 'block_categories_all', array( Block_Editor_Policy::class, 'filter_block_categories' ) );
			remove_filter(
				'should_load_remote_block_patterns',
				array( Block_Editor_Policy::class, 'filter_remote_patterns' )
			);
			remove_filter( 'block_editor_settings_all', array( Block_Editor_Policy::class, 'filter_editor_settings' ) );
			remove_filter( 'image_size_names_choose', array( Block_Editor_Policy::class, 'filter_image_sizes' ) );
			remove_filter(
				'widget_types_to_hide_from_legacy_widget_block',
				array( Block_Editor_Policy::class, 'filter_hidden_legacy_widgets' )
			);
			remove_action( 'init', array( Block_Editor_Policy::class, 'apply_late_init_policy' ), PHP_INT_MAX );
			$initialized->setValue( null, $before );
		}
	}

	/**
	 * Disable one setting for the current test.
	 *
	 * @param string $path Slash-delimited setting path.
	 *
	 * @return void
	 */
	private function disable( string $path ): void {
		$hook     = 'blockstudio/settings/' . $path;
		$callback = '__return_false';

		add_filter( $hook, $callback );
		$this->filters[] = array( $hook, $callback );
		Settings::reset();
	}

	/**
	 * Enable one setting for the current test.
	 *
	 * @param string $path Slash-delimited setting path.
	 *
	 * @return void
	 */
	private function enable( string $path ): void {
		$hook     = 'blockstudio/settings/' . $path;
		$callback = '__return_true';

		add_filter( $hook, $callback );
		$this->filters[] = array( $hook, $callback );
		Settings::reset();
	}

	/**
	 * Set one setting for the current test.
	 *
	 * @param string $path  Slash-delimited setting path.
	 * @param mixed  $value Setting value.
	 *
	 * @return void
	 */
	private function set_value( string $path, $value ): void {
		$hook     = 'blockstudio/settings/' . $path;
		$callback = static fn() => $value;

		add_filter( $hook, $callback );
		$this->filters[] = array( $hook, $callback );
		Settings::reset();
	}
}
