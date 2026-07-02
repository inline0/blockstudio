<?php

use Blockstudio\Site_Template_Discovery;
use Blockstudio\Site_Templates;
use Blockstudio\Settings;
use PHPUnit\Framework\TestCase;

class SiteTemplatesTest extends TestCase {

	private array $created_posts = array();
	private array $temp_roots    = array();

	protected function setUp(): void {
		parent::setUp();

		add_filter( 'blockstudio/settings/cache/enabled', '__return_false' );
		$this->reset_settings();

		Site_Templates::reset();
		Site_Templates::init();
	}

	protected function tearDown(): void {
		foreach ( $this->created_posts as $post_id ) {
			wp_delete_post( $post_id, true );
		}

		$this->created_posts = array();

		foreach ( $this->temp_roots as $root ) {
			$this->delete_directory( $root );
		}

		$this->temp_roots = array();

		remove_filter( 'blockstudio/settings/cache/enabled', '__return_false' );
		$this->reset_settings();
		Site_Templates::reset();
		$this->delete_directory( Blockstudio\Build_Cache::get_cache_dir( 'site-templates' ) );

		parent::tearDown();
	}

	public function test_discovers_file_backed_templates_and_parts(): void {
		$templates = Site_Templates::templates();
		$parts     = Site_Templates::parts();

		$this->assertArrayHasKey( 'blockstudio-custom', $templates );
		$this->assertArrayHasKey( 'blockstudio-single', $templates );
		$this->assertArrayHasKey( 'blockstudio-header', $parts );
		$this->assertSame( 'Blockstudio Custom', $templates['blockstudio-custom']['title'] );
		$this->assertSame( 'header', $parts['blockstudio-header']['area'] );
	}

	public function test_compiles_php_and_twig_sources_to_block_markup(): void {
		$templates = Site_Templates::templates();

		$this->assertStringContainsString( '<!-- wp:heading', $templates['blockstudio-custom']['content'] );
		$this->assertStringContainsString( 'Blockstudio Site Template', $templates['blockstudio-custom']['content'] );
		$this->assertStringContainsString( 'BLOCKSTUDIO TWIG TEMPLATE', $templates['blockstudio-single']['content'] );
	}

	public function test_get_block_template_returns_file_backed_template_object(): void {
		$template = get_block_template( get_stylesheet() . '//blockstudio-custom', 'wp_template' );

		$this->assertInstanceOf( WP_Block_Template::class, $template );
		$this->assertSame( get_stylesheet() . '//blockstudio-custom', $template->id );
		$this->assertSame( 'wp_template', $template->type );
		$this->assertSame( 'theme', $template->source );
		$this->assertSame( 'theme', $template->origin );
		$this->assertTrue( $template->has_theme_file );
		$this->assertTrue( $template->is_custom );
		$this->assertSame( array( 'page' ), $template->post_types );
		$this->assertStringContainsString( 'Blockstudio Site Template', $template->content );
	}

	public function test_get_block_template_returns_file_backed_template_part_object(): void {
		$part = get_block_template( get_stylesheet() . '//blockstudio-header', 'wp_template_part' );

		$this->assertInstanceOf( WP_Block_Template::class, $part );
		$this->assertSame( get_stylesheet() . '//blockstudio-header', $part->id );
		$this->assertSame( 'wp_template_part', $part->type );
		$this->assertSame( 'header', $part->area );
		$this->assertTrue( $part->has_theme_file );
		$this->assertStringContainsString( 'Blockstudio Header Part', $part->content );
	}

	public function test_template_queries_respect_slug_post_type_and_area_filters(): void {
		$page_templates = get_block_templates(
			array(
				'slug__in'  => array( 'blockstudio-custom', 'blockstudio-single' ),
				'post_type' => 'page',
			),
			'wp_template'
		);
		$post_templates = get_block_templates(
			array(
				'slug__in'  => array( 'blockstudio-custom', 'blockstudio-single' ),
				'post_type' => 'post',
			),
			'wp_template'
		);
		$header_parts   = get_block_templates(
			array(
				'slug__in' => array( 'blockstudio-header' ),
				'area'     => 'header',
			),
			'wp_template_part'
		);
		$footer_parts   = get_block_templates(
			array(
				'slug__in' => array( 'blockstudio-header' ),
				'area'     => 'footer',
			),
			'wp_template_part'
		);

		$this->assertSame( array( 'blockstudio-custom' ), wp_list_pluck( $page_templates, 'slug' ) );
		$this->assertSame( array( 'blockstudio-single' ), wp_list_pluck( $post_templates, 'slug' ) );
		$this->assertSame( array( 'blockstudio-header' ), wp_list_pluck( $header_parts, 'slug' ) );
		$this->assertSame( array(), wp_list_pluck( $footer_parts, 'slug' ) );
	}

	public function test_database_template_customization_wins_over_file_source(): void {
		$post_id = $this->create_template_post(
			'wp_template',
			'blockstudio-custom',
			'Customized Template',
			'<!-- wp:paragraph --><p>Database customized template.</p><!-- /wp:paragraph -->'
		);

		$template = get_block_template( get_stylesheet() . '//blockstudio-custom', 'wp_template' );
		$list     = get_block_templates(
			array(
				'slug__in' => array( 'blockstudio-custom' ),
			),
			'wp_template'
		);

		$this->assertSame( $post_id, $template->wp_id );
		$this->assertSame( 'custom', $template->source );
		$this->assertStringContainsString( 'Database customized template.', $template->content );
		$this->assertCount( 1, $list );
		$this->assertSame( $post_id, $list[0]->wp_id );
		$this->assertSame( 'custom', $list[0]->source );
		$this->assertSame( 'theme', $list[0]->origin );
		$this->assertTrue( $list[0]->has_theme_file );
	}

	public function test_database_template_part_customization_wins_over_file_source(): void {
		$post_id = $this->create_template_post(
			'wp_template_part',
			'blockstudio-header',
			'Customized Header',
			'<!-- wp:paragraph --><p>Database customized header.</p><!-- /wp:paragraph -->',
			'header'
		);

		$part = get_block_template( get_stylesheet() . '//blockstudio-header', 'wp_template_part' );
		$list = get_block_templates(
			array(
				'slug__in' => array( 'blockstudio-header' ),
				'area'     => 'header',
			),
			'wp_template_part'
		);

		$this->assertSame( $post_id, $part->wp_id );
		$this->assertSame( 'custom', $part->source );
		$this->assertStringContainsString( 'Database customized header.', $part->content );
		$this->assertCount( 1, $list );
		$this->assertSame( $post_id, $list[0]->wp_id );
		$this->assertSame( 'custom', $list[0]->source );
		$this->assertSame( 'theme', $list[0]->origin );
		$this->assertTrue( $list[0]->has_theme_file );
	}

	public function test_request_memoizes_discovery(): void {
		Site_Templates::templates();
		Site_Templates::parts();
		get_block_template( get_stylesheet() . '//blockstudio-custom', 'wp_template' );
		get_block_templates(
			array(
				'slug__in' => array( 'blockstudio-header' ),
			),
			'wp_template_part'
		);

		$this->assertSame( 1, Site_Templates::discovery_runs() );
	}

	public function test_persistent_cache_reuses_discovery_until_source_changes(): void {
		remove_filter( 'blockstudio/settings/cache/enabled', '__return_false' );
		add_filter( 'blockstudio/settings/cache/enabled', '__return_true' );
		$this->reset_settings();

		$root   = $this->create_temp_template_root();
		$filter = static function () use ( $root ): array {
			return array( $root );
		};

		add_filter( 'blockstudio/site_templates/template_paths', $filter );

		try {
			Site_Templates::reset();
			$templates = Site_Templates::templates();

			$this->assertSame( 1, Site_Templates::discovery_runs() );
			$this->assertStringContainsString( 'External Template', $templates['external-template']['content'] );

			Site_Templates::reset();
			$templates = Site_Templates::templates();

			$this->assertSame( 0, Site_Templates::discovery_runs() );
			$this->assertStringContainsString( 'External Template', $templates['external-template']['content'] );

			file_put_contents( $root . '/external-template/index.php', '<p>External Template Changed</p>' );

			Site_Templates::reset();
			$templates = Site_Templates::templates();

			$this->assertSame( 1, Site_Templates::discovery_runs() );
			$this->assertStringContainsString( 'External Template Changed', $templates['external-template']['content'] );
		} finally {
			remove_filter( 'blockstudio/site_templates/template_paths', $filter );
			remove_filter( 'blockstudio/settings/cache/enabled', '__return_true' );
			add_filter( 'blockstudio/settings/cache/enabled', '__return_false' );
			$this->reset_settings();
			Site_Templates::reset();
		}
	}

	public function test_path_filter_can_register_external_template_root(): void {
		$root = $this->create_temp_template_root();

		$filter = static function ( array $paths ) use ( $root ): array {
			$paths[] = $root;
			return $paths;
		};

		add_filter( 'blockstudio/site_templates/template_paths', $filter );
		Site_Templates::reset();

		$templates = Site_Templates::templates();

		remove_filter( 'blockstudio/site_templates/template_paths', $filter );

		$this->assertArrayHasKey( 'external-template', $templates );
		$this->assertStringContainsString( 'External Template', $templates['external-template']['content'] );
	}

	public function test_explicit_manifest_title_is_preserved(): void {
		$root = $this->create_temp_template_root();
		file_put_contents(
			$root . '/external-template/template.json',
			wp_json_encode(
				array(
					'slug'  => 'external-template',
					'title' => 'Two-Column',
				)
			)
		);

		$filter = static function () use ( $root ): array {
			return array( $root );
		};

		add_filter( 'blockstudio/site_templates/template_paths', $filter );
		Site_Templates::reset();

		$templates = Site_Templates::templates();

		remove_filter( 'blockstudio/site_templates/template_paths', $filter );

		$this->assertSame( 'Two-Column', $templates['external-template']['title'] );
	}

	public function test_invalid_manifest_records_error(): void {
		$root = $this->create_temp_template_root( false );

		$filter = static function () use ( $root ): array {
			return array( $root );
		};

		add_filter( 'blockstudio/site_templates/template_paths', $filter );
		Site_Templates::reset();

		Site_Templates::templates();
		$errors = Site_Templates::errors();

		remove_filter( 'blockstudio/site_templates/template_paths', $filter );

		$this->assertNotEmpty( $errors );
		$this->assertSame( 'invalid_manifest', $errors[0]['code'] );
	}

	private function create_template_post( string $type, string $slug, string $title, string $content, string $area = '' ): int {
		$post_id = wp_insert_post(
			array(
				'post_type'    => $type,
				'post_name'    => $slug,
				'post_title'   => $title,
				'post_content' => $content,
				'post_status'  => 'publish',
			)
		);

		$this->assertIsInt( $post_id );
		$this->assertGreaterThan( 0, $post_id );

		wp_set_object_terms( $post_id, get_stylesheet(), 'wp_theme' );

		if ( 'wp_template_part' === $type ) {
			wp_set_object_terms( $post_id, $area, 'wp_template_part_area' );
		}

		$this->created_posts[] = $post_id;

		return $post_id;
	}

	private function create_temp_template_root( bool $valid = true ): string {
		$root = wp_normalize_path( sys_get_temp_dir() . '/blockstudio-site-template-' . wp_generate_uuid4() );

		wp_mkdir_p( $root . '/external-template' );
		$this->temp_roots[] = $root;

		if ( $valid ) {
			file_put_contents(
				$root . '/external-template/template.json',
				wp_json_encode(
					array(
						'slug'  => 'external-template',
						'title' => 'External Template',
					)
				)
			);
			file_put_contents( $root . '/external-template/index.php', '<p>External Template</p>' );
		} else {
			file_put_contents( $root . '/external-template/template.json', '{ invalid json' );
		}

		return $root;
	}

	private function delete_directory( string $directory ): void {
		if ( ! is_dir( $directory ) ) {
			return;
		}

		$iterator = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator( $directory, FilesystemIterator::SKIP_DOTS ),
			RecursiveIteratorIterator::CHILD_FIRST
		);

		foreach ( $iterator as $file ) {
			if ( $file->isDir() ) {
				rmdir( $file->getPathname() );
			} else {
				unlink( $file->getPathname() );
			}
		}

		rmdir( $directory );
	}

	private function reset_settings(): void {
		$ref = new ReflectionClass( Settings::class );

		foreach ( array( 'instance', 'settings_json' ) as $property ) {
			$ref_property = $ref->getProperty( $property );
			$ref_property->setAccessible( true );
			$ref_property->setValue( null, null );
		}

		foreach ( array( 'settings', 'settings_options', 'settings_filters', 'settings_filters_values' ) as $property ) {
			$ref_property = $ref->getProperty( $property );
			$ref_property->setAccessible( true );
			$ref_property->setValue( null, array() );
		}

		Settings::get_instance();
	}
}
