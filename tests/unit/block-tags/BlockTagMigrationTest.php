<?php
/**
 * Canonical block-tag migration tests.
 *
 * @package Blockstudio
 */

use Blockstudio\Block_Tag_Migration;
use PHPUnit\Framework\TestCase;

// phpcs:disable WordPress.WP.AlternativeFunctions -- The standalone command test intentionally runs without WordPress.

/**
 * Covers the product-neutral canonical block-tag migration utility.
 */
class BlockTagMigrationTest extends TestCase {

	/**
	 * Prefixes used by the fixture consumer.
	 *
	 * @return array<string,array<int,string>> Prefixes.
	 */
	private static function prefixes(): array {
		return array(
			'brand' => array( 'theme-components' ),
			'ui'    => array( 'bsui' ),
		);
	}

	/**
	 * Registered blocks used by the fixture consumer.
	 *
	 * @return array<int,string> Blocks.
	 */
	private static function blocks(): array {
		return array(
			'theme-components/card',
			'theme-components/section',
			'bsui/input',
			'bsui/button',
		);
	}

	/**
	 * Rewrites nested, paired, self-closing, and composed prefix tags.
	 *
	 * @return void
	 */
	public function test_rewrites_nested_paired_self_closing_and_composed_prefix_tags(): void {
		$source = <<<'HTML'
<brand-section data-label="brand-card">
	<brand-card title="A > B">
		<ui-input name="email" />
	</brand-card>
</brand-section>
HTML;

		$result = Block_Tag_Migration::migrate_source(
			$source,
			'templates/home.html',
			self::prefixes(),
			self::blocks()
		);

		$this->assertSame(
			<<<'HTML'
<bs:theme-components-section data-label="brand-card">
	<bs:theme-components-card title="A > B">
		<bs:bsui-input name="email" />
	</bs:theme-components-card>
</bs:theme-components-section>
HTML,
			$result['source']
		);
		$this->assertCount( 5, $result['replacements'] );
		$this->assertSame( array(), $result['unknown'] );
		$this->assertSame( array(), $result['ambiguous'] );
	}

	/**
	 * Rewrites executable PHP strings while retaining examples in comments.
	 *
	 * @return void
	 */
	public function test_rewrites_exact_aliases_and_php_strings_but_not_php_comments(): void {
		$source = <<<'PHP'
<?php
// Keep this example for review: <legacy-card />
$markup = '<legacy-card data-value="one" />';
echo "<ui-button label=\"Save\" />";
?>
<legacy-card />
PHP;

		$result = Block_Tag_Migration::migrate_source(
			$source,
			'templates/card.php',
			self::prefixes(),
			self::blocks(),
			array( 'legacy-card' => 'theme-components/card' )
		);

		$this->assertStringContainsString( '// Keep this example for review: <legacy-card />', $result['source'] );
		$this->assertStringContainsString( "'<bs:theme-components-card data-value=\"one\" />'", $result['source'] );
		$this->assertStringContainsString( '"<bs:bsui-button label=\\"Save\\" />"', $result['source'] );
		$this->assertStringEndsWith( '<bs:theme-components-card />', $result['source'] );
		$this->assertCount( 1, $result['manual'] );
		$this->assertSame( 'comment', $result['manual'][0]['reason'] );
	}

	/**
	 * Reports every case that cannot be changed safely without guessing.
	 *
	 * @return void
	 */
	public function test_reports_code_samples_unknown_dynamic_and_ambiguous_tags_without_guessing(): void {
		$source = <<<'HTML'
<!-- <brand-card /> -->
<pre><brand-card /></pre>
```html
<ui-input />
```
`<brand-card />`
<brand-unknown />
<legacy-choice />
<$dynamic />
const comparison = left < $right && total < ${limit};
<unrelated-widget />
HTML;

		$result = Block_Tag_Migration::migrate_source(
			$source,
			'README.md',
			self::prefixes(),
			self::blocks(),
			array(
				'legacy-choice' => array( 'theme-components/card', 'theme-components/section' ),
			)
		);

		$this->assertFalse( $result['changed'] );
		$this->assertCount( 1, $result['unknown'] );
		$this->assertSame( 'brand-unknown', $result['unknown'][0]['tag'] );
		$this->assertCount( 1, $result['ambiguous'] );
		$this->assertSame(
			array( 'theme-components/card', 'theme-components/section' ),
			$result['ambiguous'][0]['candidates']
		);
		$this->assertCount( 5, $result['manual'] );
		$this->assertSame(
			array( 'html-comment', 'code-sample', 'code-fence', 'inline-code', 'dynamic-tag' ),
			array_column( $result['manual'], 'reason' )
		);
		$this->assertStringContainsString( '<unrelated-widget />', $result['source'] );
	}

	/**
	 * Uses the same ordered namespace precedence as runtime prefix resolution.
	 *
	 * @return void
	 */
	public function test_ordered_namespaces_match_runtime_precedence(): void {
		$result = Block_Tag_Migration::migrate_source(
			'<brand-button />',
			'template.html',
			array( 'brand' => array( 'theme-components', 'bsui' ) ),
			array( 'theme-components/button', 'bsui/button' )
		);

		$this->assertSame( '<bs:theme-components-button />', $result['source'] );
		$this->assertSame( 'prefix:brand', $result['replacements'][0]['via'] );
	}

	/**
	 * Falls through to a nested prefix only when direct lookup fails.
	 *
	 * @return void
	 */
	public function test_nested_prefix_fallback_is_recorded(): void {
		$result = Block_Tag_Migration::migrate_source(
			'<brand-ui-input />',
			'template.html',
			self::prefixes(),
			self::blocks()
		);

		$this->assertSame( '<bs:bsui-input />', $result['source'] );
		$this->assertSame( 'nested-prefix:brand>prefix:ui', $result['replacements'][0]['via'] );
	}

	/**
	 * Leaves canonical tags unchanged on a second pass.
	 *
	 * @return void
	 */
	public function test_output_is_idempotent_and_canonical_tags_are_untouched(): void {
		$first  = Block_Tag_Migration::migrate_source(
			'<brand-card><bs:bsui-input /></brand-card>',
			'template.html',
			self::prefixes(),
			self::blocks()
		);
		$second = Block_Tag_Migration::migrate_source(
			$first['source'],
			'template.html',
			self::prefixes(),
			self::blocks()
		);

		$this->assertTrue( $first['changed'] );
		$this->assertFalse( $second['changed'] );
		$this->assertSame( $first['source'], $second['source'] );
		$this->assertSame( array(), $second['replacements'] );
	}

	/**
	 * Does not collect unrelated tags from large fixture payloads.
	 *
	 * @return void
	 */
	public function test_large_unrelated_markup_corpus_is_ignored(): void {
		$source = str_repeat(
			'<article><div class="fixture"><span>Content</span></div></article>',
			100000
		);

		$result = Block_Tag_Migration::migrate_source(
			$source,
			'fixtures/large.json',
			self::prefixes(),
			self::blocks()
		);

		$this->assertFalse( $result['changed'] );
		$this->assertSame( $source, $result['source'] );
		$this->assertSame( array(), $result['replacements'] );
		$this->assertSame( array(), $result['manual'] );
	}

	/**
	 * Keeps the standalone command read-only unless apply is explicit.
	 *
	 * @return void
	 */
	public function test_command_defaults_to_dry_run_and_writes_a_report(): void {
		$root     = sys_get_temp_dir() . '/blockstudio-tag-migration-' . uniqid();
		$template = $root . '/template.html';
		$report   = $root . '/report.json';

		mkdir( $root );
		file_put_contents( $template, '<brand-card />' );

		$command = implode(
			' ',
			array(
				escapeshellarg( PHP_BINARY ),
				escapeshellarg( dirname( __DIR__, 3 ) . '/bin/migrate-block-tags.php' ),
				'--root=' . escapeshellarg( $root ),
				'--prefix-map=' . escapeshellarg( '{"brand":["theme-components"]}' ),
				'--known-blocks=' . escapeshellarg( '["theme-components/card"]' ),
				'--extensions=html',
				'--report=' . escapeshellarg( $report ),
			)
		);

		try {
			// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.system_calls_exec
			exec( $command . ' 2>&1', $output, $status );

			$this->assertSame( 0, $status, implode( "\n", $output ) );
			$this->assertSame( '<brand-card />', file_get_contents( $template ) );
			$this->assertFileExists( $report );

			$decoded = json_decode( (string) file_get_contents( $report ), true );
			$this->assertSame( 'dry-run', $decoded['mode'] ?? null );
			$this->assertSame( 1, $decoded['summary']['files_with_changes'] ?? null );
			$this->assertSame( 1, $decoded['summary']['replacement_occurrences'] ?? null );
			$this->assertSame( 'bs:theme-components-card', $decoded['mappings'][0]['canonical'] ?? null );
		} finally {
			if ( is_file( $report ) ) {
				unlink( $report );
			}
			if ( is_file( $template ) ) {
				unlink( $template );
			}
			if ( is_dir( $root ) ) {
				rmdir( $root );
			}
		}
	}

	/**
	 * Rejects maps that could collide with canonical syntax.
	 *
	 * @return void
	 */
	public function test_rejects_reserved_prefixes_and_invalid_block_names(): void {
		$this->expectException( InvalidArgumentException::class );

		Block_Tag_Migration::migrate_source(
			'<bs-card />',
			'template.html',
			array( 'bs' => array( 'theme-components' ) ),
			array( 'not-a-block-name' )
		);
	}
}
