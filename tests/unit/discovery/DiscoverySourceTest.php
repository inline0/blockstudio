<?php
/**
 * Logical discovery source tests.
 *
 * @package Blockstudio
 */

use Blockstudio\Assets;
use Blockstudio\Block_Discovery;
use Blockstudio\Build_Cache;
use Blockstudio\Discovery_Sources;
use Blockstudio\Field_Discovery;
use Blockstudio\Files;
use Blockstudio\Inventory_Discovery_Source;
use Blockstudio\Page_Discovery;
use Blockstudio\Pattern_Discovery;
use Blockstudio\Runtime_Cache;
use Blockstudio\Site_Template_Discovery;
use Blockstudio\Tailwind;
use PHPUnit\Framework\TestCase;

/**
 * Tests logical inventories across Blockstudio discovery systems.
 */
class DiscoverySourceTest extends TestCase {

	/**
	 * Temporary directories.
	 *
	 * @var array<int, string>
	 */
	private array $directories = array();

	/**
	 * Reset request state and remove fixtures.
	 *
	 * @return void
	 */
	protected function tearDown(): void {
		remove_all_filters( 'blockstudio/discovery/sources' );
		remove_all_filters( 'blockstudio/cache/context' );
		remove_all_filters( 'blockstudio/generated_output/path' );
		remove_all_filters( 'blockstudio/files/url' );
		Discovery_Sources::reset();

		foreach ( $this->directories as $directory ) {
			if ( is_dir( $directory ) ) {
				Files::delete_all_files( $directory );
			}
		}

		$this->directories = array();
		parent::tearDown();
	}

	/**
	 * A composed inventory resolves logical siblings across physical roots.
	 *
	 * @return void
	 */
	public function test_composed_block_inherits_manifest_and_shadows_template(): void {
		$parent  = $this->temporary_directory( 'parent' );
		$overlay = $this->temporary_directory( 'overlay' );

		$this->write(
			$parent . '/hero/block.json',
			wp_json_encode(
				array(
					'name'        => 'test/hero',
					'title'       => 'Hero',
					'blockstudio' => array( 'attributes' => array() ),
				)
			)
		);
		$this->write( $parent . '/hero/style.css', '.parent {}' );
		$this->write( $overlay . '/hero/index.php', '<?php echo "overlay";' );

		$source = $this->source(
			$overlay,
			array(
				'hero/block.json' => array(
					'path'       => $parent . '/hero/block.json',
					'provenance' => array( 'layer' => 'parent' ),
				),
				'hero/index.php'  => array(
					'path'       => $overlay . '/hero/index.php',
					'provenance' => array( 'layer' => 'overlay' ),
				),
				'hero/style.css'  => $parent . '/hero/style.css',
			)
		);

		$results = ( new Block_Discovery() )->discover( $source, 'test-overlay' );
		$data    = $results['store']['test/hero'];

		$this->assertSame( $overlay . '/hero/index.php', $data['renderTemplate'] );
		$this->assertSame( $overlay . '/hero/index.php', $data['filesMap']['index.php'] );
		$this->assertSame( $parent . '/hero/style.css', $data['filesMap']['style.css'] );
		$this->assertSame( 'parent', $data['provenance']['layer'] );
	}

	/**
	 * A composed page inherits its collection and layout while shadowing content.
	 *
	 * @return void
	 */
	public function test_composed_page_inherits_collection_and_layout(): void {
		$parent  = $this->temporary_directory( 'parent-pages' );
		$overlay = $this->temporary_directory( 'overlay-pages' );

		$this->write(
			$parent . '/docs/pages.json',
			wp_json_encode(
				array(
					'collection' => 'docs',
					'postType'   => 'page',
				)
			)
		);
		$this->write( $parent . '/docs/layout.php', '<main><?php echo Blockstudio\\Pages::page_content(); ?></main>' );
		$this->write(
			$parent . '/docs/start/page.json',
			wp_json_encode(
				array(
					'name'  => 'docs-start',
					'title' => 'Start',
					'path'  => 'start',
				)
			)
		);
		$this->write( $overlay . '/docs/start/index.php', '<p>Overlay page</p>' );

		$source    = $this->source(
			$overlay,
			array(
				'docs/pages.json'      => $parent . '/docs/pages.json',
				'docs/layout.php'      => $parent . '/docs/layout.php',
				'docs/start/page.json' => $parent . '/docs/start/page.json',
				'docs/start/index.php' => $overlay . '/docs/start/index.php',
			)
		);
		$discovery = new Page_Discovery();
		$pages     = $discovery->discover( $source );

		$this->assertArrayHasKey( 'docs:docs-start', $pages );
		$this->assertSame( $overlay . '/docs/start/index.php', $pages['docs:docs-start']['template_path'] );
		$this->assertSame( $parent . '/docs/layout.php', $pages['docs:docs-start']['layout_path'] );
		$this->assertSame( 'docs', $pages['docs:docs-start']['collection'] );
	}

	/**
	 * Patterns, fields, and Site Editor templates consume logical sources.
	 *
	 * @return void
	 */
	public function test_other_discovery_systems_consume_composed_sources(): void {
		$parent  = $this->temporary_directory( 'parent-other' );
		$overlay = $this->temporary_directory( 'overlay-other' );

		$this->write(
			$parent . '/patterns/card/pattern.json',
			wp_json_encode(
				array(
					'name'  => 'card',
					'title' => 'Card',
				)
			)
		);
		$this->write( $overlay . '/patterns/card/index.php', '<p>Overlay pattern</p>' );
		$this->write(
			$overlay . '/fields/address/field.json',
			wp_json_encode(
				array(
					'name'       => 'address',
					'attributes' => array( 'city' => array( 'type' => 'text' ) ),
				)
			)
		);
		$this->write(
			$parent . '/templates/home/template.json',
			wp_json_encode(
				array(
					'slug'  => 'home',
					'title' => 'Home',
				)
			)
		);
		$this->write( $overlay . '/templates/home/index.html', '<!-- wp:paragraph --><p>Overlay template</p><!-- /wp:paragraph -->' );

		$pattern_source  = $this->source(
			$overlay . '/patterns',
			array(
				'card/pattern.json' => $parent . '/patterns/card/pattern.json',
				'card/index.php'    => $overlay . '/patterns/card/index.php',
			)
		);
		$field_source    = $this->source(
			$overlay . '/fields',
			array( 'address/field.json' => $overlay . '/fields/address/field.json' )
		);
		$template_source = $this->source(
			$overlay . '/templates',
			array(
				'home/template.json' => $parent . '/templates/home/template.json',
				'home/index.html'    => $overlay . '/templates/home/index.html',
			)
		);

		$patterns  = ( new Pattern_Discovery() )->discover( $pattern_source );
		$fields    = ( new Field_Discovery() )->discover( $field_source );
		$templates = ( new Site_Template_Discovery() )->discover( array( $template_source ), array() );

		$this->assertSame( $overlay . '/patterns/card/index.php', $patterns['card']['template_path'] );
		$this->assertArrayHasKey( 'address', $fields );
		$this->assertSame( $overlay . '/templates/home/index.html', $templates['templates']['home']['source_path'] );
	}

	/**
	 * Omitted entries model deletion and inventories are deterministic.
	 *
	 * @return void
	 */
	public function test_inventory_order_and_deletion_are_consumer_controlled(): void {
		$root = $this->temporary_directory( 'inventory' );
		$this->write( $root . '/a.php', 'a' );
		$this->write( $root . '/b.php', 'b' );
		$this->write(
			$root . '/pattern/pattern.json',
			wp_json_encode(
				array(
					'name'  => 'deleted',
					'title' => 'Deleted',
				)
			)
		);
		$this->write( $root . '/pattern/index.php', '<p>Must stay hidden</p>' );

		$source = $this->source(
			$root,
			array(
				'b.php' => $root . '/b.php',
				'a.php' => $root . '/a.php',
			)
		);

		$this->assertSame( array( 'a.php', 'b.php' ), array_map( static fn( $entry ): string => $entry->logical_path(), $source->entries() ) );
		$this->assertNull( $source->resolve( 'deleted.php' ) );
		$this->assertNotSame( '', $source->fingerprint() );

		$deleted_pattern = $this->source(
			$root,
			array( 'pattern/pattern.json' => $root . '/pattern/pattern.json' )
		);

		$this->assertSame( array(), ( new Pattern_Discovery() )->discover( $deleted_pattern ) );
	}

	/**
	 * A consumer can hide an entire single-root source.
	 *
	 * @return void
	 */
	public function test_empty_filtered_source_does_not_fall_back_to_filesystem(): void {
		$root = $this->temporary_directory( 'empty-source' );
		$this->write( $root . '/visible.php', '<?php' );

		add_filter( 'blockstudio/discovery/sources', '__return_empty_array', 10, 3 );

		$source = Discovery_Sources::for_path( 'blocks', $root );

		$this->assertSame( array(), $source->entries() );
		$this->assertStringStartsWith( 'empty:blocks:', $source->id() );
		$this->assertSame( $source->id(), Discovery_Sources::active_sources( 'blocks' )[0]->id() );
	}

	/**
	 * Cache, generated output, and URL hooks isolate alternate runtimes.
	 *
	 * @return void
	 */
	public function test_runtime_context_isolates_caches_outputs_and_urls(): void {
		$root = $this->temporary_directory( 'runtime' );
		$this->write( $root . '/style.css', '.test {}' );

		add_filter( 'blockstudio/cache/context', static fn(): string => 'variant-a' );
		$key_a = Build_Cache::get_runtime_key( $root, 'runtime-test' );
		$dir_a = Tailwind::get_cache_dir();
		remove_all_filters( 'blockstudio/cache/context' );
		add_filter( 'blockstudio/cache/context', static fn(): string => 'variant-b' );
		$key_b = Build_Cache::get_runtime_key( $root, 'runtime-test' );
		$dir_b = Tailwind::get_cache_dir();

		$this->assertNotSame( $key_a, $key_b );
		$this->assertNotSame( $dir_a, $dir_b );

		add_filter(
			'blockstudio/generated_output/path',
			static fn( string $suggested, string $source ): string => $root . '/generated/' . basename( $source ),
			10,
			4
		);
		add_filter( 'blockstudio/files/url', static fn( string $url, string $path ): string => 'https://runtime.test/' . basename( $path ), 10, 3 );

		$this->assertSame( $root . '/generated/style.css', Assets::get_dist_folder( $root . '/style.css' ) );
		$this->assertSame( 'https://runtime.test/style.css', Files::get_relative_url( $root . '/style.css' ) );
	}

	/**
	 * Read-only inherited entries use the generated-output cache automatically.
	 *
	 * @return void
	 */
	public function test_read_only_source_never_generates_beside_parent_file(): void {
		$root = $this->temporary_directory( 'read-only' );
		$this->write( $root . '/style.css', '.test {}' );

		$source = new Inventory_Discovery_Source(
			'test:read-only',
			$root,
			array(
				'style.css' => array(
					'path'       => $root . '/style.css',
					'provenance' => array( 'inherited' => true ),
				),
			)
		);

		add_filter( 'blockstudio/discovery/sources', static fn(): array => array( $source ), 10, 3 );
		Discovery_Sources::for_paths( 'blocks', array( $root ) );

		$output = Assets::get_dist_folder( $root . '/style.css' );

		$this->assertStringStartsWith( Runtime_Cache::directory( 'generated' ) . '/', $output );
		$this->assertNotSame( $root . '/_dist', $output );
	}

	/**
	 * A writable source keeps generating beside the block by default.
	 *
	 * @return void
	 */
	public function test_writable_source_generates_beside_the_block_by_default(): void {
		$root = $this->temporary_directory( 'output-default' );
		$this->write( $root . '/style.css', '.test {}' );

		$this->assertSame( $root . '/_dist', Assets::get_dist_folder( $root . '/style.css' ) );
	}

	/**
	 * The assets/output setting moves generated output out of the source tree.
	 *
	 * @return void
	 */
	public function test_cache_output_setting_keeps_the_source_tree_clean(): void {
		$root = $this->temporary_directory( 'output-cache' );
		$this->write( $root . '/style.css', '.test {}' );

		$filter = static fn(): string => 'cache';
		add_filter( 'blockstudio/settings/assets/output', $filter );

		try {
			$output = Assets::get_dist_folder( $root . '/style.css' );

			$this->assertStringStartsWith( Runtime_Cache::directory( 'generated' ) . '/', $output );
			$this->assertNotSame( $root . '/_dist', $output );
		} finally {
			remove_filter( 'blockstudio/settings/assets/output', $filter );
		}
	}

	/**
	 * Create a temporary directory.
	 *
	 * @param string $name Directory suffix.
	 *
	 * @return string Directory path.
	 */
	private function temporary_directory( string $name ): string {
		$directory = sys_get_temp_dir() . '/blockstudio-source-' . $name . '-' . uniqid();
		wp_mkdir_p( $directory );
		$this->directories[] = $directory;

		return wp_normalize_path( $directory );
	}

	/**
	 * Write a fixture file.
	 *
	 * @param string $path File path.
	 * @param string $contents File contents.
	 *
	 * @return void
	 */
	private function write( string $path, string $contents ): void {
		wp_mkdir_p( dirname( $path ) );
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Writing a test fixture.
		file_put_contents( $path, $contents );
	}

	/**
	 * Create an inventory source.
	 *
	 * @param string $root Compatibility root.
	 * @param array  $entries Visible logical entries.
	 *
	 * @return Inventory_Discovery_Source Inventory source.
	 */
	private function source( string $root, array $entries ): Inventory_Discovery_Source {
		return new Inventory_Discovery_Source(
			'test:' . basename( $root ),
			$root,
			$entries,
			null,
			array( dirname( $root ), $root )
		);
	}
}
