<?php
/**
 * Tailwind class.
 *
 * @package Blockstudio
 */

namespace Blockstudio;

use BlockstudioVendor\TailwindPHP\Tailwind as TailwindPHP;
use BlockstudioVendor\TailwindPHP\TailwindCompiler;

/**
 * Tailwind CSS integration for Blockstudio.
 *
 * When tailwind/enabled is true, every frontend request is compiled server-side
 * via TailwindPHP. CSS class candidates are extracted from the HTML and hashed
 * to create a file-based cache key. On cache hit, the compiled CSS is read and
 * injected. On cache miss, TailwindPHP compiles the CSS, writes it to the cache
 * directory, and injects it. The CDN script remains active in the editor for
 * live preview only.
 *
 * Cache Strategy:
 * - Extract CSS class candidates from HTML via TailwindPHP::extractCandidates()
 * - Sort candidates and hash with CSS config and plugin version to create cache key
 * - Cache files stored beneath the shared Blockstudio runtime cache root
 * - Dynamic HTML (nonces, timestamps) does NOT bust the cache
 * - Cold keys compile once across processes via Single_Flight: one request is
 *   elected builder and publishes atomically, concurrent requests wait briefly
 *   and serve the most recent cached CSS as last-good output for one request
 *   when the publish does not arrive
 *
 * Filter: blockstudio/tailwind/css
 * Customize the CSS input for Tailwind compilation:
 * ```php
 * add_filter('blockstudio/tailwind/css', function($css) {
 *     return $css . "\n@layer utilities { .my-class { color: red; } }";
 * });
 * ```
 *
 * @since 5.0.0
 */
class Tailwind {

	/**
	 * Wait budget in milliseconds for requests that lose the builder election.
	 *
	 * @var int
	 */
	private const WAIT_BUDGET_MS = 4000;

	/**
	 * Request generation in which this instance last compiled.
	 *
	 * @var int
	 */
	private int $compiled_generation = -1;

	/**
	 * Current batch request generation.
	 *
	 * @var int
	 */
	private static int $request_generation = 0;

	/**
	 * Warm compilers for this process, keyed by CSS input hash.
	 *
	 * @var array<string, TailwindCompiler>
	 */
	private static array $compilers = array();

	/**
	 * Whether a candidate compiles, per CSS input hash.
	 *
	 * @var array<string, array<string, bool>>
	 */
	private static array $validity = array();

	/**
	 * Scanned source candidates for this process, keyed by source list.
	 *
	 * @var array<string, array{list: string[], set: array<string, true>}|null>
	 */
	private static array $source_scopes = array();

	/**
	 * File extensions scanned for source candidates.
	 *
	 * @var string[]
	 */
	private const SOURCE_EXTENSIONS = array( 'php', 'tsx', 'ts', 'jsx', 'js', 'twig', 'html', 'vue', 'svelte' );

	/**
	 * Allow every long-lived Tailwind instance to compile the next request.
	 *
	 * @return void
	 */
	public static function reset_request_state(): void {
		++self::$request_generation;
	}

	/**
	 * Filter candidates to strings only.
	 *
	 * TailwindPHP uses candidates as array keys internally, which causes PHP
	 * to cast numeric strings (e.g. "0") to integers. This breaks
	 * parseCandidate() which expects string arguments.
	 *
	 * @param string[] $candidates Extracted candidates.
	 *
	 * @return string[] Filtered candidates with numeric values removed.
	 */
	private static function filter_candidates( array $candidates ): array {
		return array_values(
			array_filter(
				$candidates,
				fn( $c ) => ! is_numeric( $c )
			)
		);
	}

	/**
	 * Build content string from filtered candidates for TailwindPHP.
	 *
	 * TailwindPHP's extractCandidates() only recognizes classes inside HTML
	 * attributes, so we wrap them in a class attribute.
	 *
	 * @param string[] $candidates Filtered candidates.
	 *
	 * @return string HTML-like content for TailwindPHP.
	 */
	private static function candidates_to_content( array $candidates ): string {
		return '<div class="' . implode( ' ', $candidates ) . '"></div>';
	}

	/**
	 * Get the warm compiler for a CSS input, creating it on first use.
	 *
	 * The compiler parses the design system once per process, so batch
	 * renders across many pages reuse it instead of paying the full engine
	 * pipeline per page.
	 *
	 * @param string $css_input The CSS input string.
	 *
	 * @return TailwindCompiler Warm compiler for the CSS input.
	 */
	private static function get_compiler( string $css_input ): TailwindCompiler {
		$key = md5( $css_input );

		if ( ! isset( self::$compilers[ $key ] ) ) {
			self::$compilers[ $key ] = TailwindPHP::compile(
				$css_input,
				array(
					'stateCacheFile' => self::get_state_cache_file( $css_input ),
				)
			);
		}

		return self::$compilers[ $key ];
	}

	/**
	 * Resolve the compiler construction-state cache file for a CSS input.
	 *
	 * Compiler construction re-parses the framework on every compile miss;
	 * TailwindPHP persists that construction state per CSS input so page-level
	 * cache misses only pay for compiling their candidate set. The engine
	 * validates the state file itself; the key only namespaces inputs.
	 *
	 * @param string $css_input The CSS input string.
	 *
	 * @return string State cache file path.
	 */
	private static function get_state_cache_file( string $css_input ): string {
		return Build_Cache::get_cache_dir( 'tailwind-state' ) . '/' . md5( self::get_engine_version() . $css_input ) . '.php';
	}

	/**
	 * Compile candidates against a CSS input via the warm compiler.
	 *
	 * Falls back to a cold TailwindPHP::generate run when the warm compiler
	 * cannot be created; both paths produce byte-identical output.
	 *
	 * @param string[] $candidates Filtered, sorted candidates.
	 * @param string   $css_input  The CSS input string.
	 *
	 * @return string Minified CSS.
	 */
	private static function build_css( array $candidates, string $css_input ): string {
		try {
			return self::get_compiler( $css_input )->cssExact( $candidates, true );
		} catch ( \Throwable ) {
			return (string) TailwindPHP::generate(
				array(
					'content' => self::candidates_to_content( $candidates ),
					'css'     => $css_input,
					'minify'  => true,
				)
			);
		}
	}

	/**
	 * Constructor.
	 */
	public function __construct() {
		if ( ! Settings::get_bool( 'tailwind/enabled', false ) ) {
			return;
		}

		add_filter( 'blockstudio/buffer/output', array( $this, 'compile' ), 999999 );
		add_filter( 'block_editor_settings_all', array( $this, 'inject_editor_styles' ), PHP_INT_MAX );
	}

	/**
	 * Load the TailwindPHP autoloader if not already loaded.
	 *
	 * @return void
	 */
	private static function load_autoloader(): void {
		static $loaded = false;

		if ( ! $loaded ) {
			$loaded = true;
			require_once BLOCKSTUDIO_DIR . '/lib/tailwindphp-autoload.php';
		}
	}

	/**
	 * Widen a page's candidates to the candidates of its sources.
	 *
	 * When the blockstudio/tailwind/sources filter names source files or
	 * directories, every page links one stylesheet built from the candidates
	 * found in those sources. A page whose utilities are not all among them
	 * links that set plus its own extra utilities, so its stylesheet always
	 * covers the page.
	 *
	 * @param string[] $candidates Sorted page candidates.
	 * @param string   $css_input  The CSS input string.
	 *
	 * @return string[] Sorted candidates the page stylesheet compiles.
	 */
	private static function scoped_candidates( array $candidates, string $css_input ): array {
		$scope = self::source_candidates();
		if ( null === $scope ) {
			return $candidates;
		}

		$unknown = array();
		foreach ( $candidates as $candidate ) {
			if ( ! isset( $scope['set'][ $candidate ] ) ) {
				$unknown[] = $candidate;
			}
		}

		$extra = self::valid_candidates( $unknown, $css_input );
		if ( array() === $extra ) {
			return $scope['list'];
		}

		$merged = array_merge( $scope['list'], $extra );
		sort( $merged );

		return $merged;
	}

	/**
	 * Keep the candidates that produce CSS.
	 *
	 * @param string[] $candidates Candidates outside the source set.
	 * @param string   $css_input  The CSS input string.
	 *
	 * @return string[] Candidates the design system compiles.
	 */
	private static function valid_candidates( array $candidates, string $css_input ): array {
		if ( array() === $candidates ) {
			return array();
		}

		$key = md5( $css_input );
		if ( ! isset( self::$validity[ $key ] ) ) {
			self::$validity[ $key ] = array();
		}

		$pending = array();
		foreach ( $candidates as $candidate ) {
			if ( ! isset( self::$validity[ $key ][ $candidate ] ) ) {
				$pending[] = $candidate;
			}
		}

		if ( array() !== $pending ) {
			$css = array();
			try {
				$design = self::get_compiler( $css_input )->getDesignSystem();
				if ( $design instanceof \BlockstudioVendor\TailwindPHP\DesignSystem\DesignSystemInterface ) {
					$css = $design->candidatesToCss( $pending );
				}
			} catch ( \Throwable ) {
				$css = array();
			}
			foreach ( $pending as $index => $candidate ) {
				$value                                = $css[ $index ] ?? null;
				self::$validity[ $key ][ $candidate ] = array() === $css || ( is_string( $value ) && '' !== $value );
			}
		}

		$valid = array();
		foreach ( $candidates as $candidate ) {
			if ( self::$validity[ $key ][ $candidate ] ) {
				$valid[] = $candidate;
			}
		}

		return $valid;
	}

	/**
	 * Resolve the candidates of the sources the current site names.
	 *
	 * The scan is cached on disk under a signature of every source file's
	 * path, size and modification time, so a request pays a directory walk
	 * and one small read once the sources were scanned.
	 *
	 * @return array{list: string[], set: array<string, true>}|null Candidates, or null without sources.
	 */
	private static function source_candidates(): ?array {
		/**
		 * Filter the source files and directories whose candidates every
		 * page stylesheet includes.
		 *
		 * @param string[] $sources Absolute file or directory paths.
		 */
		$sources = apply_filters( 'blockstudio/tailwind/sources', array() );
		if ( ! is_array( $sources ) ) {
			return null;
		}
		$sources = array_values( array_filter( $sources, static fn( $source ): bool => is_string( $source ) && '' !== $source ) );
		if ( array() === $sources ) {
			return null;
		}
		sort( $sources );

		$memo = md5( implode( "\n", $sources ) );
		if ( array_key_exists( $memo, self::$source_scopes ) ) {
			return self::$source_scopes[ $memo ];
		}

		$files = self::source_files( $sources );
		if ( array() === $files ) {
			self::$source_scopes[ $memo ] = null;

			return null;
		}

		$signature = hash( 'xxh128', self::get_engine_version() . "\n" . implode( "\n", $files ) );
		$path      = Build_Cache::get_cache_dir( 'tailwind-sources' ) . '/' . $signature . '.json';
		$list      = self::read_source_candidates( $path );

		if ( null === $list ) {
			$found = array();
			foreach ( array_keys( $files ) as $file ) {
				$source = @file_get_contents( $file ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- A source removed during the scan only drops its candidates.
				if ( ! is_string( $source ) ) {
					continue;
				}
				foreach ( TailwindPHP::extractCandidatesFromStrings( $source ) as $candidate ) {
					$found[ $candidate ] = true;
				}
			}
			$list = self::filter_candidates( array_map( 'strval', array_keys( $found ) ) );
			sort( $list );
			self::write_source_candidates( $path, $list );
		}

		self::$source_scopes[ $memo ] = array(
			'list' => $list,
			'set'  => array_fill_keys( $list, true ),
		);

		return self::$source_scopes[ $memo ];
	}

	/**
	 * List the source files with their size and modification time.
	 *
	 * @param string[] $sources Absolute file or directory paths.
	 *
	 * @return array<string, string> Signature line per file path.
	 */
	private static function source_files( array $sources ): array {
		$files = array();
		foreach ( $sources as $source ) {
			if ( is_file( $source ) ) {
				$files[ $source ] = $source . '|' . (string) filesize( $source ) . '|' . (string) filemtime( $source );
				continue;
			}
			if ( ! is_dir( $source ) ) {
				continue;
			}
			$iterator = new \RecursiveIteratorIterator(
				new \RecursiveCallbackFilterIterator(
					new \RecursiveDirectoryIterator( $source, \FilesystemIterator::SKIP_DOTS ),
					static fn( \SplFileInfo $entry ): bool => ! $entry->isDir() || ( 'node_modules' !== $entry->getFilename() && ! str_starts_with( $entry->getFilename(), '.' ) )
				)
			);
			foreach ( $iterator as $entry ) {
				if ( ! $entry instanceof \SplFileInfo || ! $entry->isFile() || ! in_array( strtolower( $entry->getExtension() ), self::SOURCE_EXTENSIONS, true ) ) {
					continue;
				}
				$file           = $entry->getPathname();
				$files[ $file ] = $file . '|' . (string) $entry->getSize() . '|' . (string) $entry->getMTime();
			}
		}
		ksort( $files, SORT_STRING );

		return $files;
	}

	/**
	 * Read a cached source scan.
	 *
	 * @param string $path Cache file.
	 *
	 * @return string[]|null Candidates, or null when absent or unreadable.
	 */
	private static function read_source_candidates( string $path ): ?array {
		if ( ! is_file( $path ) ) {
			return null;
		}
		$decoded = json_decode( (string) @file_get_contents( $path ), true ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- A concurrent prune can remove the file after the check.
		if ( ! is_array( $decoded ) ) {
			return null;
		}

		return array_values( array_filter( $decoded, 'is_string' ) );
	}

	/**
	 * Publish a source scan atomically.
	 *
	 * @param string   $path       Cache file.
	 * @param string[] $candidates Candidates.
	 *
	 * @return void
	 */
	private static function write_source_candidates( string $path, array $candidates ): void {
		$encoded = wp_json_encode( $candidates, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
		if ( ! is_string( $encoded ) ) {
			return;
		}
		$directory = dirname( $path );
		if ( ! is_dir( $directory ) && ! wp_mkdir_p( $directory ) ) {
			return;
		}
		$temporary = $directory . '/.tmp-' . bin2hex( random_bytes( 6 ) );
		if ( false === @file_put_contents( $temporary, $encoded ) ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- A full disk leaves the scan uncached.
			return;
		}
		if ( ! @rename( $temporary, $path ) ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.WP.AlternativeFunctions.rename_rename -- A concurrent publish of the same scan wins.
			@unlink( $temporary ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.WP.AlternativeFunctions.unlink_unlink -- Cleanup of a temporary file.
		}
	}

	/**
	 * Build the warm compiler for the current CSS input ahead of a batch.
	 *
	 * Forked batch renders inherit the parent's memory, so a compiler built
	 * once in the parent serves every child without a state file read.
	 *
	 * @return void
	 */
	public static function warm(): void {
		if ( ! Settings::get_bool( 'tailwind/enabled', false ) ) {
			return;
		}

		self::load_autoloader();

		try {
			self::get_compiler( self::build_css_input() );
			self::source_candidates();
		} catch ( \Throwable ) {
			return;
		}
	}

	/**
	 * Compile Tailwind CSS from the buffered HTML output.
	 *
	 * Extracts CSS class candidates, checks the file cache, compiles on miss,
	 * and injects the result as an inline style tag before </head>. On a cold
	 * key one request compiles while concurrent requests wait briefly for the
	 * published file; a request whose wait budget expires injects the most
	 * recent cached CSS as last-good output and the next request hits the
	 * cache.
	 *
	 * @param string $html The full page HTML.
	 *
	 * @return string The modified HTML with injected Tailwind CSS.
	 */
	public function compile( string $html ): string {
		if ( ! Settings::get( 'tailwind/enabled' ) || self::$request_generation === $this->compiled_generation ) {
			return $html;
		}

		Perf::start( 'tailwind' );
		$this->compiled_generation = self::$request_generation;

		self::load_autoloader();

		$css_input  = self::build_css_input();
		$candidates = self::filter_candidates( TailwindPHP::extractCandidates( $html ) );
		sort( $candidates );
		$candidates = self::scoped_candidates( $candidates, $css_input );
		$cache_key  = md5( Runtime_Context::hash( 'tailwind', array( 'blocks', 'pages' ) ) . self::get_engine_version() . implode( ',', $candidates ) . $css_input );
		$cache_path = self::get_cache_dir() . '/' . $cache_key . '.css';

		$compiled = Single_Flight::read_or_build(
			$cache_path,
			static function () use ( $candidates, $css_input ): string {
				return self::build_css( $candidates, $css_input );
			},
			self::WAIT_BUDGET_MS,
			static function () use ( $cache_path ): void {
				self::prune_cache( $cache_path );
			}
		);

		$compiled_path = is_file( $cache_path ) ? $cache_path : '';

		if ( null === $compiled ) {
			$last_good     = self::read_last_good_css();
			$compiled      = $last_good['css'] ?? null;
			$compiled_path = $last_good['path'] ?? '';
		}

		if ( empty( $compiled ) ) {
			return $html;
		}

		Perf::stop( 'tailwind', 'Tailwind' );

		return str_replace(
			'</head>',
			self::stylesheet_markup( $compiled, $compiled_path ) . "\n</head>",
			$html
		);
	}

	/**
	 * Compile Tailwind CSS for the editor from all block template files.
	 *
	 * Extracts candidates from registered block templates, compiles via
	 * TailwindPHP with file-based caching, and returns the CSS string.
	 *
	 * @return string Compiled CSS, or empty string if Tailwind is disabled or no candidates found.
	 */
	public static function compile_editor_css(): string {
		if ( ! Settings::get( 'tailwind/enabled' ) ) {
			return '';
		}

		self::load_autoloader();

		$candidates = array();

		foreach ( Block_Registry::instance()->get_data() as $block ) {
			foreach ( $block['filesPaths'] ?? array() as $path ) {
				if ( is_file( $path ) ) {
					// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Reading block template files for Tailwind candidate extraction.
					$raw = file_get_contents( $path );

					// Match candidate tokens including arbitrary value brackets like [calc(50%-10px)].
					preg_match_all( '/[!@-]?[a-zA-Z_][\w:.\/-]*(?:\[[^\]]*\])?/', $raw, $matches );
					$candidates = array_merge( $candidates, $matches[0] );
				}
			}
		}

		foreach ( Page_Registry::instance()->get_pages() as $page ) {
			$path = $page['template_path'] ?? '';

			if ( is_string( $path ) && is_file( $path ) ) {
				// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Reading page template files for Tailwind candidate extraction.
				$raw = file_get_contents( $path );

				// Match candidate tokens including arbitrary value brackets like [calc(50%-10px)].
				preg_match_all( '/[!@-]?[a-zA-Z_][\w:.\/-]*(?:\[[^\]]*\])?/', $raw, $matches );
				$candidates = array_merge( $candidates, $matches[0] );
			}
		}

		$candidates = array_unique( $candidates );
		$candidates = self::filter_candidates( $candidates );
		sort( $candidates );

		$css_input  = self::build_css_input();
		$cache_key  = md5( Runtime_Context::hash( 'tailwind', array( 'blocks', 'pages' ) ) . 'editor:' . self::get_engine_version() . implode( ',', $candidates ) . $css_input );
		$cache_path = self::get_cache_dir() . '/' . $cache_key . '.css';

		$compiled = Single_Flight::read_or_build(
			$cache_path,
			static function () use ( $candidates, $css_input ): string {
				return self::build_css( $candidates, $css_input );
			},
			self::WAIT_BUDGET_MS,
			static function () use ( $cache_path ): void {
				self::prune_cache( $cache_path );
			}
		);

		return $compiled ?? '';
	}

	/**
	 * Get the engine version component of Tailwind cache keys.
	 *
	 * A plugin update ships a new TailwindPHP engine, so keying on the plugin
	 * version busts compiled CSS from previous engines.
	 *
	 * @return string Engine version.
	 */
	private static function get_engine_version(): string {
		return defined( 'BLOCKSTUDIO_VERSION' ) ? BLOCKSTUDIO_VERSION : '';
	}

	/**
	 * Read the most recently published cache file as last-good output.
	 *
	 * A request that loses the builder election and exhausts its wait budget
	 * serves the newest cached CSS for one request instead of unstyled HTML.
	 * The near-match is not published under the cold key, so a later request
	 * still compiles the exact CSS.
	 *
	 * @return array{css:string,path:string}|null Most recent cached CSS, or null when none exists.
	 */
	private static function read_last_good_css(): ?array {
		$files = glob( self::get_cache_dir() . '/*.css' );

		if ( ! is_array( $files ) || empty( $files ) ) {
			return null;
		}

		usort(
			$files,
			static fn( string $a, string $b ): int => (int) ( @filemtime( $b ) ) <=> (int) ( @filemtime( $a ) ) // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- Concurrent cleanups can remove files between listing and stat.
		);

		foreach ( $files as $file ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Reading cached CSS file.
			$content = is_file( $file ) ? file_get_contents( $file ) : false;

			if ( false !== $content && '' !== $content ) {
				return array(
					'css'  => $content,
					'path' => $file,
				);
			}
		}

		return null;
	}

	/**
	 * Build the frontend stylesheet markup for compiled CSS.
	 *
	 * Inline output remains the compatibility default. Link output is used only
	 * when the content-hashed cache file exists and resolves to a public URL.
	 * Absolute private cache roots can provide that URL through the existing
	 * blockstudio/files/url filter; otherwise output safely stays inline.
	 *
	 * @param string $compiled Compiled CSS.
	 * @param string $path     Published cache file path.
	 *
	 * @return string Stylesheet markup.
	 */
	private static function stylesheet_markup( string $compiled, string $path ): string {
		if ( 'link' === Settings::get_string( 'tailwind/output', 'inline' ) && '' !== $path && is_file( $path ) ) {
			$url = self::cache_url( $path );

			if ( '' !== $url ) {
				// phpcs:ignore WordPress.WP.EnqueuedResources.NonEnqueuedStylesheet -- Intentional content-hashed runtime stylesheet output.
				return '<link id="blockstudio-tailwind" rel="stylesheet" href="' . esc_url( $url ) . '">';
			}
		}

		return '<style id="blockstudio-tailwind">' . $compiled . '</style>';
	}

	/**
	 * Resolve the cache file a document's Tailwind stylesheet link points at.
	 *
	 * Inverse of cache_url() for the default content-directory mapping. A link
	 * rewritten onto a CDN or a private root cannot be mapped back and returns
	 * an empty string, which callers read as "nothing to verify".
	 *
	 * @param string $html Document markup.
	 *
	 * @return string Absolute cache path, or an empty string.
	 */
	public static function linked_cache_path( string $html ): string {
		if ( ! str_contains( $html, 'id="blockstudio-tailwind"' ) ) {
			return '';
		}

		if ( 1 !== preg_match( '#<link\b[^>]*\bid="blockstudio-tailwind"[^>]*>#i', $html, $tag ) ) {
			return '';
		}

		if ( 1 !== preg_match( '#\bhref="([^"]*)"#i', $tag[0], $href ) ) {
			return '';
		}

		return self::cache_path_for_url( html_entity_decode( $href[1], ENT_QUOTES, 'UTF-8' ) );
	}

	/**
	 * Map one public cache URL back to its file path.
	 *
	 * @param string $url Stylesheet URL.
	 *
	 * @return string Absolute cache path, or an empty string.
	 */
	public static function cache_path_for_url( string $url ): string {
		if ( 1 !== preg_match( '#/tailwind/[a-f0-9]{32}\.css$#', $url ) ) {
			return '';
		}

		$content_url = rtrim( (string) content_url(), '/' );
		$content_dir = rtrim( wp_normalize_path( (string) WP_CONTENT_DIR ), '/' );

		if ( '' === $content_url || ! str_starts_with( $url, $content_url . '/' ) ) {
			return '';
		}

		return $content_dir . substr( $url, strlen( $content_url ) );
	}

	/**
	 * Check whether a document still resolves its Tailwind stylesheet.
	 *
	 * A cached document outlives the content-hashed file it links, and the
	 * cached path never reaches the compiler, so a pruned or otherwise lost
	 * cache file would serve an unstyled page for as long as the document
	 * stays fresh. Callers treat an unresolved link as a cache miss and let a
	 * live render republish both.
	 *
	 * @param string $html Document markup.
	 *
	 * @return bool Whether the document is safe to serve.
	 */
	public static function document_stylesheet_resolves( string $html ): bool {
		$path = self::linked_cache_path( $html );

		return '' === $path || is_file( $path );
	}

	/**
	 * Resolve the public URL for one Tailwind cache file.
	 *
	 * @param string $path Cache file path.
	 *
	 * @return string Public URL, or an empty string for a private cache root.
	 */
	private static function cache_url( string $path ): string {
		$path        = wp_normalize_path( $path );
		$content_dir = rtrim( wp_normalize_path( WP_CONTENT_DIR ), '/' );
		$url         = '';

		if ( str_starts_with( $path, $content_dir . '/' ) ) {
			$url = rtrim( content_url(), '/' ) . substr( $path, strlen( $content_dir ) );
		}

		return Runtime_Context::file_url( $url, $path, 'tailwind' );
	}

	/**
	 * Delete crash-orphaned temp files and long-idle lock files.
	 *
	 * Cache keys churn with content changes, so lock and temp files would
	 * otherwise accumulate one inode per historical key. Both are swept only
	 * after an hour of idleness: an active builder's temp file is seconds
	 * old, and sweeping a lock file that is re-contended in the same instant
	 * costs at worst one duplicate build with an atomic publish, never a
	 * torn cache file.
	 *
	 * @param string $cache_dir Cache directory.
	 *
	 * @return void
	 */
	private static function sweep_stale_files( string $cache_dir ): void {
		$locks = glob( $cache_dir . '/*.css.lock' );
		$temps = glob( $cache_dir . '/*.css.tmp-*' );
		$stale = array_merge(
			is_array( $locks ) ? $locks : array(),
			is_array( $temps ) ? $temps : array()
		);

		foreach ( $stale as $file ) {
			if ( ! is_file( $file ) ) {
				continue;
			}

			$mtime = (int) ( @filemtime( $file ) ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- Concurrent cleanups can remove the file between listing and stat.

			if ( $mtime > 0 && time() - $mtime > HOUR_IN_SECONDS ) {
				@unlink( $file ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.WP.AlternativeFunctions.unlink_unlink -- Internal cache files must not invoke media-deletion filters.
			}
		}
	}

	/**
	 * Prune stale Tailwind cache files.
	 *
	 * @param string $keep_file File that must not be pruned.
	 *
	 * @return void
	 */
	private static function prune_cache( string $keep_file ): void {
		$cache_dir = self::get_cache_dir();
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- A native handle is required for cross-process flock().
		$lock = fopen( $cache_dir . '/.prune.lock', 'c' );

		if ( false === $lock || ! flock( $lock, LOCK_EX ) ) {
			if ( is_resource( $lock ) ) {
				// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- Closing the native flock() handle.
				fclose( $lock );
			}
			return;
		}

		try {
			self::sweep_stale_files( $cache_dir );

			$files = glob( $cache_dir . '/*.css' );

			if ( ! is_array( $files ) || empty( $files ) ) {
				return;
			}

			$max_files = max( 1, (int) apply_filters( 'blockstudio/tailwind/cache_max_files', 1000 ) );
			$max_age   = max( HOUR_IN_SECONDS, (int) apply_filters( 'blockstudio/tailwind/cache_max_age', MONTH_IN_SECONDS ) );
			$files     = array_values(
				array_filter(
					$files,
					static fn( string $file ): bool => $file !== $keep_file && is_file( $file )
				)
			);

			foreach ( $files as $index => $file ) {
				$mtime = (int) ( @filemtime( $file ) ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- Concurrent cleanups can remove the file between listing and stat.

				if ( $mtime > 0 && time() - $mtime > $max_age ) {
					@unlink( $file ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.WP.AlternativeFunctions.unlink_unlink -- Internal cache files must not invoke media-deletion filters.
					unset( $files[ $index ] );
				}
			}

			$files = array_values( $files );
			usort(
				$files,
				static fn( string $a, string $b ): int => (int) ( @filemtime( $b ) ) <=> (int) ( @filemtime( $a ) ) // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- Concurrent cleanups can remove files between listing and stat.
			);

			foreach ( array_slice( $files, max( 0, $max_files - 1 ) ) as $file ) {
				@unlink( $file ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.WP.AlternativeFunctions.unlink_unlink -- Internal cache files must not invoke media-deletion filters.
			}
		} finally {
			flock( $lock, LOCK_UN );
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- Closing the native flock() handle.
			fclose( $lock );
		}
	}

	/**
	 * Inject compiled Tailwind CSS into the block editor.
	 *
	 * Compiles the CSS config (e.g. @apply rules) so custom classes like
	 * .container work in the editor iframe alongside the CDN.
	 *
	 * @param array $settings Editor settings.
	 *
	 * @return array Modified editor settings.
	 */
	public function inject_editor_styles( array $settings ): array {
		if ( ! Settings::get( 'tailwind/enabled' ) || ! isset( $settings['__unstableResolvedAssets'] ) ) {
			return $settings;
		}

		$compiled = self::compile_editor_css();

		if ( ! empty( $compiled ) ) {
			$settings['__unstableResolvedAssets']['styles'] .= '<style id="blockstudio-tailwind-editor">' . $compiled . '</style>';
		}

		return $settings;
	}

	/**
	 * Build the CSS input string for compilation.
	 *
	 * @return string The CSS input with imports and config.
	 */
	private static function build_css_input(): string {
		$css = '@import "tailwindcss";';

		$config = Settings::get( 'tailwind/config' );

		if ( ! empty( $config ) && is_string( $config ) ) {
			$css .= "\n" . $config;
		}

		/**
		 * Filter the CSS input for Tailwind compilation.
		 *
		 * @param string $css The CSS input string.
		 */
		$css = apply_filters( 'blockstudio/tailwind/css', $css );

		return $css;
	}

	/**
	 * Get the cache directory path.
	 *
	 * @return string The cache directory path.
	 */
	public static function get_cache_dir(): string {
		return Runtime_Cache::directory( 'tailwind' );
	}

	/**
	 * Get Tailwind CDN URL.
	 *
	 * @return string The CDN URL.
	 */
	public static function get_cdn_url(): string {
		if ( ! Settings::get( 'tailwind/enabled' ) ) {
			return '';
		}

		$path = BLOCKSTUDIO_DIR . '/includes/admin/assets/tailwind/cdn.js';

		return Files::get_relative_url( $path );
	}
}
