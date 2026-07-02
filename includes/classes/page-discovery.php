<?php
/**
 * Page Discovery class.
 *
 * @package Blockstudio
 */

namespace Blockstudio;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Throwable;

/**
 * Discovers Blockstudio pages by scanning filesystem directories.
 *
 * This class supports the original page.json/index.php pages and collection
 * roots with pages.json manifests, markdown sources, and trusted loader.php
 * files.
 *
 * @since 7.0.0
 */
class Page_Discovery {

	/**
	 * Discovered pages.
	 *
	 * @var array<string, array>
	 */
	private array $pages = array();

	/**
	 * Discovered collections.
	 *
	 * @var array<string, array>
	 */
	private array $collections = array();

	/**
	 * Discovery errors.
	 *
	 * @var array<int, array>
	 */
	private array $errors = array();

	/**
	 * Page lookup by collection/path.
	 *
	 * @var array<string, array<string, string>>
	 */
	private array $path_index = array();

	/**
	 * Base path of the active discovery run, used to bound layout lookups.
	 *
	 * @var string
	 */
	private string $discover_base = '';

	/**
	 * Discover pages in a directory path.
	 *
	 * @param string $base_path Absolute path to scan for pages.
	 *
	 * @return array<string, array> Array of discovered page definitions.
	 */
	public function discover( string $base_path ): array {
		$this->pages       = array();
		$this->collections = array();
		$this->errors      = array();
		$this->path_index  = array();

		if ( ! is_dir( $base_path ) ) {
			return $this->pages;
		}

		$base_path           = self::normalize_filesystem_path( $base_path );
		$this->discover_base = $base_path;
		$collection_roots    = array();
		$claimed_roots       = array();
		$manifest_paths      = self::find_manifest_paths( $base_path );

		foreach ( $manifest_paths as $manifest_path ) {
			$root = self::normalize_filesystem_path( dirname( $manifest_path ) );

			if ( self::is_inside_any_path( $root, $claimed_roots ) ) {
				continue;
			}

			$manifest = Utils::read_json_file( $manifest_path );

			if ( ! is_array( $manifest ) ) {
				$this->add_error( 'invalid_manifest', 'Invalid pages.json manifest.', array( 'path' => $manifest_path ) );
				continue;
			}

			$collection = self::normalize_collection_manifest( $manifest, $manifest_path, $base_path );

			if ( null === $collection ) {
				$this->add_error( 'invalid_collection', 'Collection manifest has an invalid collection slug or post type.', array( 'path' => $manifest_path ) );
				continue;
			}

			if ( isset( $this->collections[ $collection['slug'] ] ) ) {
				$this->add_error(
					'duplicate_collection',
					'Duplicate collection slug.',
					array(
						'collection' => $collection['slug'],
						'path'       => $manifest_path,
					)
				);
				continue;
			}

			$this->collections[ $collection['slug'] ] = $collection;
			$collection_roots[]                       = $collection['root'];
			$claimed_roots[]                          = $collection['root'];

			$this->discover_collection( $collection );
		}

		$this->discover_legacy_pages( $base_path, $collection_roots );
		$this->add_generated_container_pages();
		$this->assign_relationships();
		$this->sort_pages_for_sync();

		return $this->pages;
	}

	/**
	 * Discover collection manifests without loading page sources.
	 *
	 * @param string $base_path Absolute path to scan.
	 *
	 * @return array<string, array> Collection data indexed by collection slug.
	 */
	public static function discover_manifests( string $base_path ): array {
		$collections = array();

		if ( ! is_dir( $base_path ) ) {
			return $collections;
		}

		$base_path      = self::normalize_filesystem_path( $base_path );
		$manifest_paths = self::find_manifest_paths( $base_path );
		$claimed_roots  = array();

		foreach ( $manifest_paths as $manifest_path ) {
			$root = self::normalize_filesystem_path( dirname( $manifest_path ) );

			if ( self::is_inside_any_path( $root, $claimed_roots ) ) {
				continue;
			}

			$manifest = Utils::read_json_file( $manifest_path );

			if ( ! is_array( $manifest ) ) {
				continue;
			}

			$collection = self::normalize_collection_manifest( $manifest, $manifest_path, $base_path );

			if ( null === $collection ) {
				continue;
			}

			$collections[ $collection['slug'] ] = $collection;
			$claimed_roots[]                    = $collection['root'];
		}

		return $collections;
	}

	/**
	 * Get discovered collections.
	 *
	 * @return array<string, array> Collections indexed by slug.
	 */
	public function get_collections(): array {
		return $this->collections;
	}

	/**
	 * Get discovery errors.
	 *
	 * @return array<int, array> Discovery errors.
	 */
	public function get_errors(): array {
		return $this->errors;
	}

	/**
	 * Build a registry key for a page.
	 *
	 * @param string|null $collection Collection slug.
	 * @param string      $name       Page name.
	 *
	 * @return string Registry key.
	 */
	public static function page_key( ?string $collection, string $name ): string {
		return $collection ? $collection . ':' . $name : $name;
	}

	/**
	 * Normalize a logical page path.
	 *
	 * @param mixed $path Path value.
	 *
	 * @return string|null Normalized path, "." for collection root, or null when unsafe.
	 */
	public static function normalize_logical_path( mixed $path ): ?string {
		$path = is_scalar( $path ) ? (string) $path : '';
		$path = trim( str_replace( '\\', '/', $path ) );

		if ( '' === $path || '.' === $path ) {
			return '.';
		}

		if (
			str_starts_with( $path, '/' ) ||
			str_contains( $path, '?' ) ||
			str_contains( $path, '#' ) ||
			preg_match( '/^[A-Za-z][A-Za-z0-9+.-]*:/', $path )
		) {
			return null;
		}

		$path     = trim( $path, '/' );
		$segments = array();

		foreach ( explode( '/', $path ) as $segment ) {
			$segment = trim( $segment );

			if ( '' === $segment || '.' === $segment || '..' === $segment ) {
				return null;
			}

			$segment = sanitize_title( $segment );

			if ( '' === $segment || '.' === $segment || '..' === $segment ) {
				return null;
			}

			$segments[] = $segment;
		}

		return implode( '/', $segments );
	}

	/**
	 * Get a human-readable title from a slug/path.
	 *
	 * @param string $value Slug, path, or name.
	 *
	 * @return string Title.
	 */
	public static function title_from_value( string $value ): string {
		$value = '.' === $value ? 'home' : basename( str_replace( '/', '-', $value ) );

		return ucwords( str_replace( array( '-', '_' ), ' ', $value ) );
	}

	/**
	 * Discover pages in one collection root.
	 *
	 * @param array $collection Collection data.
	 *
	 * @return void
	 */
	private function discover_collection( array $collection ): void {
		$root        = $collection['root'];
		$loader_path = $root . '/loader.php';

		if ( file_exists( $loader_path ) ) {
			$this->process_loader( $loader_path, $collection );
		}

		$iterator = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator( $root, RecursiveDirectoryIterator::SKIP_DOTS )
		);

		foreach ( $iterator as $file ) {
			if ( ! $file->isFile() ) {
				continue;
			}

			$file_path = self::normalize_filesystem_path( $file->getPathname() );
			$basename  = $file->getBasename();

			if ( in_array( $basename, array( 'pages.json', 'loader.php', 'layout.php' ), true ) ) {
				continue;
			}

			if ( 'page.json' === $basename ) {
				$page_data = $this->process_page_json( $file_path, $root, $collection );

				if ( $page_data ) {
					$this->register_page_data( $page_data );
				}

				continue;
			}

			if ( 'md' === strtolower( $file->getExtension() ) ) {
				if ( 'index.md' === $basename && file_exists( dirname( $file_path ) . '/page.json' ) ) {
					continue;
				}

				$page_data = $this->process_markdown_file( $file_path, $root, $collection, false );

				if ( $page_data ) {
					$this->register_page_data( $page_data );
				}
			}
		}
	}

	/**
	 * Discover original non-collection page.json pages.
	 *
	 * @param string $base_path        Base path.
	 * @param array  $collection_roots Collection roots to skip.
	 *
	 * @return void
	 */
	private function discover_legacy_pages( string $base_path, array $collection_roots ): void {
		$iterator = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator( $base_path, RecursiveDirectoryIterator::SKIP_DOTS )
		);

		foreach ( $iterator as $file ) {
			if ( ! $file->isFile() ) {
				continue;
			}

			$file_path = self::normalize_filesystem_path( $file->getPathname() );

			if ( self::is_inside_any_path( $file_path, $collection_roots ) ) {
				continue;
			}

			$basename = $file->getBasename();

			if ( 'page.json' === $basename ) {
				$page_data = $this->process_page_json( $file_path, $base_path, null );

				if ( $page_data ) {
					$this->register_page_data( $page_data );
				}

				continue;
			}

			if ( 'index.md' === $basename && ! file_exists( dirname( $file_path ) . '/page.json' ) ) {
				$page_data = $this->process_markdown_file( $file_path, $base_path, null, true );

				if ( $page_data ) {
					$this->register_page_data( $page_data );
				}
			}
		}
	}

	/**
	 * Process a page.json file.
	 *
	 * @param string     $json_path  Path to the page.json file.
	 * @param string     $base_path  Base path for the page source.
	 * @param array|null $collection Collection data.
	 * @param array      $extra_source_mtime_paths Additional fingerprint source paths.
	 * @param array      $loader_context Loader path context.
	 *
	 * @return array|null The page data or null if invalid.
	 */
	private function process_page_json( string $json_path, string $base_path, ?array $collection, array $extra_source_mtime_paths = array(), array $loader_context = array() ): ?array {
		$directory = self::normalize_filesystem_path( dirname( $json_path ) );
		$page_json = Utils::read_json_file( $json_path );

		if ( ! is_array( $page_json ) ) {
			$this->add_error( 'invalid_page_json', 'Invalid page.json file.', array( 'path' => $json_path ) );
			return null;
		}

		$template_path = $this->find_template( $directory );
		$content_type  = $this->detect_content_type( $template_path, $page_json );

		if ( ! $template_path && ! isset( $page_json['markdown'], $page_json['html'] ) ) {
			$this->add_error( 'missing_template', 'Page source has no supported template or content.', array( 'path' => $json_path ) );
			return null;
		}

		if ( $template_path && 'markdown' === $content_type ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Reading local markdown file.
			$markdown_content = file_get_contents( $template_path );

			if ( false !== $markdown_content ) {
				$parts = Page_Markdown::split_frontmatter( $markdown_content );

				if ( ! empty( $parts['data'] ) ) {
					$page_json = array_merge( $page_json, $parts['data'] );
				}
			}
		}

		$relative_dir = self::relative_path( $base_path, $directory );
		$raw_path     = $page_json['path'] ?? ( $collection ? ( '' === $relative_dir ? '.' : $relative_dir ) : ( $page_json['slug'] ?? $relative_dir ) );
		$path         = self::normalize_logical_path( $raw_path );

		if ( null === $path ) {
			$this->add_error(
				'invalid_path',
				'Page has an unsafe logical path.',
				array(
					'path'  => $json_path,
					'value' => $raw_path,
				)
			);
			return null;
		}

		$path = $this->loader_context_prefixed_path( $path, $loader_context );

		$preserve_name = empty( $loader_context['prefix'] ) || ! empty( $loader_context['preserveNames'] );
		$name          = $preserve_name && isset( $page_json['name'] ) && is_scalar( $page_json['name'] )
			? sanitize_key( (string) $page_json['name'] )
			: self::name_from_path( $path, $collection['slug'] ?? null );

		if ( '' === $name ) {
			$this->add_error( 'missing_name', 'Page has no valid name.', array( 'path' => $json_path ) );
			return null;
		}

		$defaults = $this->collection_page_defaults( $collection );

		$page_data = wp_parse_args( $page_json, $defaults );
		$page_data = $this->merge_loader_context_meta( $page_data, $loader_context );
		$page_data = $this->normalize_page_data(
			$page_data,
			array(
				'name'               => $name,
				'path'               => $path,
				'json_path'          => $json_path,
				'template_path'      => $template_path,
				'content_path'       => $template_path,
				'contentType'        => $content_type,
				'directory'          => $directory,
				'source_path'        => $collection ? $this->collection_source_path( $collection, self::relative_path( $base_path, '' !== $relative_dir ? $directory : $json_path ), $loader_context ) : $relative_dir,
				'collection_data'    => $collection,
				'source_mtime_paths' => array_values(
					array_filter(
						array_merge(
							array( $collection['manifest_path'] ?? null, $json_path, $template_path ),
							$extra_source_mtime_paths
						)
					)
				),
			),
			$page_json,
			$collection
		);

		if ( ! empty( $page_json['markdown'] ) && is_string( $page_json['markdown'] ) ) {
			$page_data['inline_content']   = $page_json['markdown'];
			$page_data['contentType']      = 'markdown';
			$page_data['sanitize_content'] = true;
		} elseif ( ! empty( $page_json['html'] ) && is_string( $page_json['html'] ) ) {
			$page_data['inline_content']   = $page_json['html'];
			$page_data['contentType']      = 'html';
			$page_data['sanitize_content'] = true;
		}

		return $page_data;
	}

	/**
	 * Process a markdown source file.
	 *
	 * @param string     $markdown_path       Markdown file path.
	 * @param string     $base_path           Base path.
	 * @param array|null $collection          Collection data.
	 * @param bool       $require_frontmatter Whether standalone legacy markdown needs frontmatter.
	 * @param array      $extra_source_mtime_paths Additional fingerprint source paths.
	 * @param array      $loader_context Loader path context.
	 *
	 * @return array|null Page data.
	 */
	private function process_markdown_file( string $markdown_path, string $base_path, ?array $collection, bool $require_frontmatter, array $extra_source_mtime_paths = array(), array $loader_context = array() ): ?array {
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Reading local markdown file.
		$contents = file_get_contents( $markdown_path );

		if ( false === $contents ) {
			return null;
		}

		$parts = Page_Markdown::split_frontmatter( $contents );

		if ( $require_frontmatter && empty( $parts['frontmatter'] ) ) {
			return null;
		}

		$frontmatter  = is_array( $parts['data'] ) ? $parts['data'] : array();
		$directory    = self::normalize_filesystem_path( dirname( $markdown_path ) );
		$relative     = self::relative_path( $base_path, $markdown_path );
		$relative_dir = self::relative_path( $base_path, $directory );
		$is_index     = 'index.md' === basename( $markdown_path );
		$default_path = $is_index ? ( '' === $relative_dir ? '.' : $relative_dir ) : preg_replace( '/\.md$/i', '', $relative );
		$raw_path     = $frontmatter['path'] ?? $default_path;
		$path         = self::normalize_logical_path( $raw_path );

		if ( null === $path ) {
			$this->add_error(
				'invalid_path',
				'Markdown page has an unsafe logical path.',
				array(
					'path'  => $markdown_path,
					'value' => $raw_path,
				)
			);
			return null;
		}

		$path = $this->loader_context_prefixed_path( $path, $loader_context );

		$preserve_name = empty( $loader_context['prefix'] ) || ! empty( $loader_context['preserveNames'] );
		$name          = $preserve_name && isset( $frontmatter['name'] ) && is_scalar( $frontmatter['name'] )
			? sanitize_key( (string) $frontmatter['name'] )
			: self::name_from_path( $path, $collection['slug'] ?? null );

		if ( '' === $name ) {
			return null;
		}

		$defaults = $this->collection_page_defaults( $collection );

		$page_data = wp_parse_args( $frontmatter, $defaults );
		$page_data = $this->merge_loader_context_meta( $page_data, $loader_context );

		return $this->normalize_page_data(
			$page_data,
			array(
				'name'               => $name,
				'path'               => $path,
				'json_path'          => null,
				'template_path'      => $markdown_path,
				'content_path'       => $markdown_path,
				'contentType'        => 'markdown',
				'directory'          => $directory,
				'source_path'        => $collection ? $this->collection_source_path( $collection, $relative, $loader_context ) : ( '' === $relative_dir ? $name : $relative_dir ),
				'collection_data'    => $collection,
				'source_mtime_paths' => array_values(
					array_filter(
						array_merge(
							array( $collection['manifest_path'] ?? null, $markdown_path ),
							$extra_source_mtime_paths
						)
					)
				),
			),
			$frontmatter,
			$collection
		);
	}

	/**
	 * Process a trusted local loader.php file.
	 *
	 * @param string $loader_path Loader path.
	 * @param array  $collection  Collection data.
	 *
	 * @return void
	 */
	private function process_loader( string $loader_path, array $collection ): void {
		try {
			$loaded = include $loader_path;
		} catch ( Throwable $throwable ) {
			$this->add_error(
				'loader_exception',
				'Collection loader failed.',
				array(
					'path'    => $loader_path,
					'message' => $throwable->getMessage(),
				)
			);
			return;
		}

		if ( ! is_array( $loaded ) ) {
			$this->add_error( 'invalid_loader', 'Collection loader must return an array.', array( 'path' => $loader_path ) );
			return;
		}

		$loader_meta  = isset( $loaded['meta'] ) && is_array( $loaded['meta'] ) ? $loaded['meta'] : array();
		$loader_paths = isset( $loaded['paths'] ) && is_array( $loaded['paths'] ) ? $loaded['paths'] : array();
		$pages        = isset( $loaded['pages'] ) && is_array( $loaded['pages'] ) ? $loaded['pages'] : ( array_is_list( $loaded ) ? $loaded : array() );

		foreach ( $pages as $index => $loader_page ) {
			$page_data = $this->process_loader_page( $loader_page, $index, $loader_path, $collection, $loader_meta, $loader_paths );

			if ( $page_data ) {
				$this->register_page_data( $page_data );
			}
		}

		$this->process_loader_paths( $loader_paths, $loader_path, $collection );
	}

	/**
	 * Process one loader page.
	 *
	 * @param mixed  $loader_page Loader entry.
	 * @param mixed  $index       Loader index.
	 * @param string $loader_path Loader path.
	 * @param array  $collection  Collection data.
	 * @param array  $loader_meta Loader wrapper meta.
	 * @param array  $loader_paths Loader wrapper paths.
	 *
	 * @return array|null Page data.
	 */
	private function process_loader_page( mixed $loader_page, mixed $index, string $loader_path, array $collection, array $loader_meta, array $loader_paths ): ?array {
		if ( ! is_array( $loader_page ) ) {
			$this->add_error(
				'invalid_loader_page',
				'Loader page must be an array.',
				array(
					'path'  => $loader_path,
					'index' => $index,
				)
			);
			return null;
		}

		$path = self::normalize_logical_path( $loader_page['path'] ?? $loader_page['slug'] ?? $index );

		if ( null === $path ) {
			$this->add_error(
				'invalid_loader_path',
				'Loader page has an unsafe logical path.',
				array(
					'path'  => $loader_path,
					'index' => $index,
				)
			);
			return null;
		}

		$name = isset( $loader_page['name'] ) && is_scalar( $loader_page['name'] )
			? sanitize_key( (string) $loader_page['name'] )
			: self::name_from_path( $path, $collection['slug'] );

		if ( '' === $name ) {
			return null;
		}

		$template_path = null;
		$content_type  = null;
		$inline        = null;

		if ( isset( $loader_page['markdown'] ) && is_string( $loader_page['markdown'] ) ) {
			$content_type = 'markdown';
			$inline       = $loader_page['markdown'];
		} elseif ( isset( $loader_page['html'] ) && is_string( $loader_page['html'] ) ) {
			$content_type = 'html';
			$inline       = $loader_page['html'];
		} elseif ( isset( $loader_page['content'] ) && is_string( $loader_page['content'] ) ) {
			$detected = $this->detect_content_type( null, $loader_page );

			if ( in_array( $detected, array( 'markdown', 'html' ), true ) ) {
				$content_type = $detected;
				$inline       = $loader_page['content'];
			}
		} else {
			$file = $loader_page['file'] ?? $loader_page['template'] ?? null;

			if ( is_scalar( $file ) ) {
				$template_path = $this->resolve_loader_path( (string) $file, $collection['root'], $loader_page );
				$content_type  = $this->detect_content_type( $template_path, $loader_page );
			}
		}

		if ( null === $content_type ) {
			$this->add_error(
				'invalid_loader_content',
				'Loader page has no supported content.',
				array(
					'path'  => $loader_path,
					'index' => $index,
				)
			);
			return null;
		}

		$defaults  = $this->collection_page_defaults( $collection );
		$page_data = wp_parse_args( $loader_page, $defaults );
		$meta      = array_merge(
			$loader_meta,
			! empty( $loader_paths ) ? array( 'paths' => $loader_paths ) : array(),
			isset( $loader_page['meta'] ) && is_array( $loader_page['meta'] ) ? $loader_page['meta'] : array(),
			$this->unknown_meta( $loader_page )
		);

		return $this->normalize_page_data(
			$page_data,
			array(
				'name'               => $name,
				'path'               => $path,
				'json_path'          => null,
				'template_path'      => $template_path,
				'content_path'       => $template_path,
				'contentType'        => $content_type,
				'directory'          => $template_path ? dirname( $template_path ) : $collection['root'],
				'source_path'        => $collection['slug'] . '/loader.php:' . $name,
				'collection_data'    => $collection,
				'content'            => $inline,
				'inline_content'     => $inline,
				'generated'          => $loader_page['generated'] ?? true,
				'sanitize_content'   => true,
				'meta'               => $meta,
				'source_mtime_paths' => array_filter(
					array( $collection['manifest_path'] ?? null, $loader_path, $template_path )
				),
			),
			$loader_page,
			$collection
		);
	}

	/**
	 * Discover local page directories returned by a collection loader.
	 *
	 * @param array  $paths       Loader paths.
	 * @param string $loader_path Loader file path.
	 * @param array  $collection  Collection data.
	 *
	 * @return void
	 */
	private function process_loader_paths( array $paths, string $loader_path, array $collection ): void {
		foreach ( $paths as $path ) {
			$path_config = $this->normalize_loader_path_config( $path, $loader_path );

			if ( null === $path_config ) {
				$this->add_error( 'invalid_loader_path', 'Loader path must be a local filesystem path.', array( 'path' => $loader_path ) );
				continue;
			}

			$resolved = $this->resolve_loader_path(
				$path_config['path'],
				$collection['root'],
				array(
					'loader_path' => $loader_path,
					'path_type'   => 'discovery',
					'path_config' => $path_config,
				)
			);

			if ( null === $resolved || ! is_dir( $resolved ) ) {
				$this->add_error(
					'invalid_loader_path',
					'Loader path must resolve to an allowed local directory.',
					array(
						'path'  => $loader_path,
						'value' => $path_config['path'],
					)
				);
				continue;
			}

			if ( self::is_same_or_inside_path( $resolved, $collection['root'] ) ) {
				continue;
			}

			$this->discover_loader_path( $resolved, $collection, array( $loader_path ), $path_config );
		}
	}

	/**
	 * Discover page sources in one loader-provided path.
	 *
	 * @param string $root                     Discovery root.
	 * @param array  $collection               Collection data.
	 * @param array  $extra_source_mtime_paths Additional fingerprint sources.
	 * @param array  $loader_context           Loader path context.
	 *
	 * @return void
	 */
	private function discover_loader_path( string $root, array $collection, array $extra_source_mtime_paths, array $loader_context = array() ): void {
		$iterator = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator( $root, RecursiveDirectoryIterator::SKIP_DOTS )
		);

		foreach ( $iterator as $file ) {
			if ( ! $file->isFile() ) {
				continue;
			}

			$file_path = self::normalize_filesystem_path( $file->getPathname() );
			$basename  = $file->getBasename();

			if ( in_array( $basename, array( 'pages.json', 'loader.php', 'layout.php' ), true ) ) {
				continue;
			}

			if ( 'page.json' === $basename ) {
				$page_data = $this->process_page_json( $file_path, $root, $collection, $extra_source_mtime_paths, $loader_context );

				if ( $page_data ) {
					$this->register_page_data( $page_data );
				}

				continue;
			}

			if ( 'md' === strtolower( $file->getExtension() ) ) {
				if ( 'index.md' === $basename && file_exists( dirname( $file_path ) . '/page.json' ) ) {
					continue;
				}

				$page_data = $this->process_markdown_file( $file_path, $root, $collection, false, $extra_source_mtime_paths, $loader_context );

				if ( $page_data ) {
					$this->register_page_data( $page_data );
				}
			}
		}
	}

	/**
	 * Normalize one loader path entry.
	 *
	 * Scalar entries keep the historical behavior. Array entries may provide:
	 * - path: local path to discover.
	 * - prefix/pathPrefix: logical collection path prefix for discovered pages.
	 * - preserveNames: keep source names instead of deriving names from prefixed paths.
	 * - meta: metadata merged into each discovered page.
	 *
	 * @param mixed  $entry       Loader path entry.
	 * @param string $loader_path Loader file path.
	 *
	 * @return array|null Normalized config or null when invalid.
	 */
	private function normalize_loader_path_config( mixed $entry, string $loader_path ): ?array {
		if ( is_scalar( $entry ) ) {
			return array(
				'path'          => (string) $entry,
				'prefix'        => '',
				'preserveNames' => true,
				'meta'          => array(),
			);
		}

		if ( ! is_array( $entry ) ) {
			return null;
		}

		$path = $entry['path'] ?? $entry['root'] ?? $entry['source'] ?? null;
		if ( ! is_scalar( $path ) || '' === trim( (string) $path ) ) {
			return null;
		}

		$prefix_value = $entry['prefix'] ?? $entry['pathPrefix'] ?? $entry['mount'] ?? '';
		$prefix       = '';
		if ( is_scalar( $prefix_value ) && '' !== trim( (string) $prefix_value ) ) {
			$prefix = self::normalize_logical_path( $prefix_value );

			if ( null === $prefix ) {
				$this->add_error(
					'invalid_loader_path_prefix',
					'Loader path prefix must be a safe logical path.',
					array(
						'path'  => $loader_path,
						'value' => (string) $prefix_value,
					)
				);
				return null;
			}

			if ( '.' === $prefix ) {
				$prefix = '';
			}
		}

		$preserve_names = array_key_exists( 'preserveNames', $entry )
			? (bool) $entry['preserveNames']
			: ( array_key_exists( 'preserve_names', $entry ) ? (bool) $entry['preserve_names'] : '' === $prefix );

		return array(
			'path'          => (string) $path,
			'prefix'        => $prefix,
			'preserveNames' => $preserve_names,
			'meta'          => isset( $entry['meta'] ) && is_array( $entry['meta'] ) ? $entry['meta'] : array(),
		);
	}

	/**
	 * Prefix a page's logical collection path for mounted loader directories.
	 *
	 * @param string $path           Logical page path.
	 * @param array  $loader_context Loader path context.
	 *
	 * @return string Prefixed logical path.
	 */
	private function loader_context_prefixed_path( string $path, array $loader_context ): string {
		$prefix = isset( $loader_context['prefix'] ) && is_string( $loader_context['prefix'] )
			? $loader_context['prefix']
			: '';

		if ( '' === $prefix ) {
			return $path;
		}

		return '.' === $path ? $prefix : $prefix . '/' . $path;
	}

	/**
	 * Merge loader path metadata into discovered page data.
	 *
	 * @param array $page_data      Page data.
	 * @param array $loader_context Loader path context.
	 *
	 * @return array Page data with loader metadata.
	 */
	private function merge_loader_context_meta( array $page_data, array $loader_context ): array {
		$context_meta = isset( $loader_context['meta'] ) && is_array( $loader_context['meta'] )
			? $loader_context['meta']
			: array();

		if ( empty( $context_meta ) ) {
			return $page_data;
		}

		$page_meta         = isset( $page_data['meta'] ) && is_array( $page_data['meta'] ) ? $page_data['meta'] : array();
		$page_data['meta'] = array_merge( $context_meta, $page_meta );

		return $page_data;
	}

	/**
	 * Build a stable collection source path for external loader sources.
	 *
	 * @param array  $collection     Collection data.
	 * @param string $relative       Relative source path.
	 * @param array  $loader_context Loader path context.
	 *
	 * @return string Source identity path.
	 */
	private function collection_source_path( array $collection, string $relative, array $loader_context ): string {
		$parts = array( (string) $collection['slug'] );

		if ( isset( $loader_context['prefix'] ) && is_string( $loader_context['prefix'] ) && '' !== $loader_context['prefix'] ) {
			$parts[] = $loader_context['prefix'];
		}

		$relative = trim( wp_normalize_path( $relative ), '/' );
		if ( '' !== $relative ) {
			$parts[] = $relative;
		}

		return implode( '/', $parts );
	}

	/**
	 * Normalize final page data.
	 *
	 * @param array      $page_data  Page data.
	 * @param array      $overrides  Forced values.
	 * @param array      $raw_data   Raw source data.
	 * @param array|null $collection Collection data.
	 *
	 * @return array Page data.
	 */
	private function normalize_page_data( array $page_data, array $overrides, array $raw_data, ?array $collection ): array {
		$page_data = array_merge( $page_data, $overrides );

		$collection_slug = $collection['slug'] ?? null;
		$name            = (string) $page_data['name'];
		$path            = (string) $page_data['path'];

		if ( empty( $page_data['slug'] ) ) {
			if ( null !== $collection && ! $this->collection_uses_hierarchical_post_type( $collection ) && false !== strpos( $path, '/' ) ) {
				$page_data['slug'] = str_replace( '/', '-', $path );
			} else {
				$page_data['slug'] = '.' === $path ? ( $collection_slug ?? $name ) : basename( $path );
			}
		}

		$page_data['slug'] = sanitize_title( (string) $page_data['slug'] );

		if ( empty( $page_data['title'] ) ) {
			$page_data['title'] = self::title_from_value( '.' === $path ? $name : $path );
		}

		$page_data['postType']    = sanitize_key( (string) ( $page_data['postType'] ?? 'page' ) );
		$page_data['postStatus']  = sanitize_key( (string) ( $page_data['postStatus'] ?? 'draft' ) );
		$page_data['collection']  = $collection_slug;
		$page_data['key']         = self::page_key( $collection_slug, $name );
		$page_data['is_twig']     = 'twig' === $page_data['contentType'];
		$page_data['is_blade']    = 'blade' === $page_data['contentType'];
		$page_data['is_markdown'] = 'markdown' === $page_data['contentType'];
		$page_data['generated']   = (bool) ( $page_data['generated'] ?? false );
		if ( null !== $collection ) {
			$page_data['layout_path'] = $collection['layout_path'] ?? null;
		} else {
			$directory                = isset( $page_data['directory'] ) && is_string( $page_data['directory'] ) ? $page_data['directory'] : '';
			$page_data['layout_path'] = '' !== $directory ? $this->find_nearest_layout( $directory ) : null;
		}
		$page_data['paths'] = array(
			'base'       => $collection['base_path'] ?? null,
			'collection' => $collection['root'] ?? null,
			'source'     => $page_data['template_path'] ?? $page_data['json_path'] ?? null,
			'layout'     => $page_data['layout_path'],
		);

		$page_data['meta'] = array_merge(
			isset( $page_data['meta'] ) && is_array( $page_data['meta'] ) ? $page_data['meta'] : array(),
			$this->unknown_meta( $raw_data )
		);

		return $page_data;
	}

	/**
	 * Register page data with duplicate checks.
	 *
	 * @param array $page_data Page data.
	 *
	 * @return void
	 */
	private function register_page_data( array $page_data ): void {
		$key        = $page_data['key'];
		$collection = $page_data['collection'] ?? '';
		$path       = $page_data['path'] ?? '';

		if ( isset( $this->pages[ $key ] ) ) {
			$this->add_error(
				'duplicate_name',
				'Duplicate page name.',
				array(
					'name' => $page_data['name'],
					'key'  => $key,
				)
			);
			return;
		}

		if ( '' !== (string) $path ) {
			if ( isset( $this->path_index[ $collection ][ $path ] ) ) {
				if ( '' !== (string) $collection ) {
					$this->add_error(
						'duplicate_path',
						'Duplicate collection page path.',
						array(
							'collection' => $collection,
							'path'       => $path,
						)
					);
					return;
				}
			} else {
				$this->path_index[ $collection ][ $path ] = $key;
			}
		}

		$this->pages[ $key ] = $page_data;
	}

	/**
	 * Add generated container pages for missing intermediate path segments.
	 *
	 * @return void
	 */
	private function add_generated_container_pages(): void {
		$pages = $this->pages;

		foreach ( $pages as $page ) {
			$collection = $page['collection'] ?? null;
			$path       = $page['path'] ?? '.';

			if ( ! $collection || '.' === $path || false === strpos( $path, '/' ) ) {
				continue;
			}

			$collection_data = $this->collections[ $collection ] ?? array();

			if ( ! $this->collection_uses_hierarchical_post_type( $collection_data ) ) {
				continue;
			}

			$segments   = explode( '/', $path );
			$current    = array();
			$last_index = count( $segments ) - 1;

			for ( $i = 0; $i < $last_index; ++$i ) {
				$current[]      = $segments[ $i ];
				$container_path = implode( '/', $current );

				if ( isset( $this->path_index[ $collection ][ $container_path ] ) ) {
					continue;
				}

				$name      = $this->unique_generated_name( $collection, $container_path );
				$page_data = wp_parse_args(
					array(
						'name'               => $name,
						'title'              => self::title_from_value( $container_path ),
						'slug'               => basename( $container_path ),
						'path'               => $container_path,
						'postType'           => $page['postType'],
						'postStatus'         => $page['postStatus'],
						'templateLock'       => $page['templateLock'],
						'sync'               => true,
						'contentType'        => 'generated',
						'inline_content'     => '',
						'generated'          => true,
						'sanitize_content'   => true,
						'source_path'        => $collection . '/__generated/' . $container_path,
						'source_mtime_paths' => array(),
						'collection_data'    => $collection_data,
					),
					$this->default_page_values()
				);

				$this->register_page_data(
					$this->normalize_page_data(
						$page_data,
						array(
							'json_path'     => null,
							'template_path' => null,
							'content_path'  => null,
							'directory'     => $collection_data['root'] ?? null,
						),
						$page_data,
						$collection_data
					)
				);
			}
		}
	}

	/**
	 * Assign parent/children metadata.
	 *
	 * @return void
	 */
	private function assign_relationships(): void {
		foreach ( $this->pages as $key => $page ) {
			$this->pages[ $key ]['children'] = array();
		}

		foreach ( $this->pages as $key => $page ) {
			$collection = (string) ( $page['collection'] ?? '' );
			$path       = $page['path'] ?? '.';

			if ( '.' === $path ) {
				continue;
			}

			$parent_path = null;

			if ( false !== strpos( $path, '/' ) ) {
				$parent_path = dirname( $path );
			} elseif ( '' !== $collection && isset( $this->path_index[ $collection ]['.'] ) ) {
				$parent_path = '.';
			}

			if ( null === $parent_path || ! isset( $this->path_index[ $collection ][ $parent_path ] ) ) {
				continue;
			}

			$parent_key = $this->path_index[ $collection ][ $parent_path ];

			$this->pages[ $key ]['parent_key']        = $parent_key;
			$this->pages[ $key ]['parent_name']       = $this->pages[ $parent_key ]['name'];
			$this->pages[ $key ]['parent_path']       = $parent_path;
			$this->pages[ $parent_key ]['children'][] = $key;
		}
	}

	/**
	 * Sort pages topologically enough for parent-first sync.
	 *
	 * @return void
	 */
	private function sort_pages_for_sync(): void {
		uasort(
			$this->pages,
			function ( array $a, array $b ): int {
				$a_collection = (string) ( $a['collection'] ?? '' );
				$b_collection = (string) ( $b['collection'] ?? '' );

				if ( $a_collection !== $b_collection ) {
					return $a_collection <=> $b_collection;
				}

				$a_depth = $this->path_depth( (string) ( $a['path'] ?? '.' ) );
				$b_depth = $this->path_depth( (string) ( $b['path'] ?? '.' ) );

				if ( $a_depth !== $b_depth ) {
					return $a_depth <=> $b_depth;
				}

				return (string) $a['key'] <=> (string) $b['key'];
			}
		);
	}

	/**
	 * Find the template file for a page.
	 *
	 * @param string $directory The page directory.
	 *
	 * @return string|null The template path or null if not found.
	 */
	private function find_template( string $directory ): ?string {
		$templates = Utils::index_source_candidates( $directory, array( 'index.md' ) );

		/**
		 * Filter candidate template paths for file-based pages.
		 *
		 * @param array  $templates Candidate template paths.
		 * @param string $directory Page source directory.
		 */
		$templates = apply_filters( 'blockstudio/pages/template_candidates', $templates, $directory );

		return Utils::first_existing_path( $templates );
	}

	/**
	 * Detect content type from path or data.
	 *
	 * @param string|null $path Source path.
	 * @param array       $data Source data.
	 *
	 * @return string|null Content type.
	 */
	private function detect_content_type( ?string $path, array $data = array() ): ?string {
		if ( isset( $data['contentType'] ) && is_scalar( $data['contentType'] ) ) {
			$type = sanitize_key( (string) $data['contentType'] );

			if ( in_array( $type, array( 'php', 'blade', 'twig', 'markdown', 'html', 'generated' ), true ) ) {
				return $type;
			}
		}

		if ( isset( $data['markdown'] ) ) {
			return 'markdown';
		}

		if ( isset( $data['html'] ) ) {
			return 'html';
		}

		if ( ! $path ) {
			return null;
		}

		if ( str_ends_with( $path, '.blade.php' ) ) {
			return 'blade';
		}

		$extension = strtolower( pathinfo( $path, PATHINFO_EXTENSION ) );

		return match ( $extension ) {
			'php' => 'php',
			'twig' => 'twig',
			'md', 'markdown' => 'markdown',
			'html', 'htm' => 'html',
			default => null,
		};
	}

	/**
	 * Resolve a loader file path and keep it inside the collection root by default.
	 *
	 * @param string $path            Requested path.
	 * @param string $collection_root Collection root.
	 * @param array  $loader_page     Loader page data.
	 *
	 * @return string|null Resolved path.
	 */
	private function resolve_loader_path( string $path, string $collection_root, array $loader_page ): ?string {
		$candidate = str_starts_with( $path, '/' ) ? $path : $collection_root . '/' . ltrim( $path, '/' );
		$real      = realpath( $candidate );

		if ( false === $real ) {
			return null;
		}

		$real            = self::normalize_filesystem_path( $real );
		$collection_root = self::normalize_filesystem_path( $collection_root );
		$allowed         = self::is_same_or_inside_path( $real, $collection_root );

		/**
		 * Filter whether a loader source path outside its collection root is allowed.
		 *
		 * @param bool   $allowed         Whether the path is allowed.
		 * @param string $real            Resolved path.
		 * @param string $collection_root Collection root.
		 * @param array  $loader_page     Loader page data.
		 */
		$allowed = (bool) apply_filters( 'blockstudio/pages/allow_external_loader_path', $allowed, $real, $collection_root, $loader_page );

		return $allowed ? $real : null;
	}

	/**
	 * Default page values.
	 *
	 * @return array Defaults.
	 */
	private function default_page_values(): array {
		return array(
			'name'         => '',
			'title'        => '',
			'slug'         => '',
			'postType'     => 'page',
			'postStatus'   => 'draft',
			'postId'       => null,
			'templateLock' => 'all',
			'templateFor'  => null,
			'sync'         => true,
		);
	}

	/**
	 * Default values for pages inside a collection.
	 *
	 * @param array|null $collection Collection data.
	 *
	 * @return array Defaults.
	 */
	private function collection_page_defaults( ?array $collection ): array {
		$defaults = $this->default_page_values();

		if ( ! empty( $collection['postType'] ) && is_scalar( $collection['postType'] ) ) {
			$defaults['postType'] = (string) $collection['postType'];
		}

		return array_merge(
			$defaults,
			is_array( $collection['defaults'] ?? null ) ? $collection['defaults'] : array()
		);
	}

	/**
	 * Determine whether a collection syncs into a hierarchical post type.
	 *
	 * @param array $collection Collection data.
	 *
	 * @return bool Whether the collection post type is hierarchical.
	 */
	private function collection_uses_hierarchical_post_type( array $collection ): bool {
		if ( array_key_exists( 'hierarchical', $collection['postTypeArgs'] ?? array() ) ) {
			return (bool) $collection['postTypeArgs']['hierarchical'];
		}

		$post_type = (string) ( $collection['postType'] ?? 'page' );

		if ( post_type_exists( $post_type ) ) {
			return is_post_type_hierarchical( $post_type );
		}

		return true;
	}

	/**
	 * Extract unknown source keys into page meta.
	 *
	 * @param array $data Source data.
	 *
	 * @return array Meta values.
	 */
	private function unknown_meta( array $data ): array {
		$known = array(
			'blockEditingMode',
			'collection',
			'content',
			'contentSource',
			'contentType',
			'defaults',
			'file',
			'generated',
			'html',
			'markdown',
			'meta',
			'name',
			'order',
			'path',
			'postId',
			'postStatus',
			'postType',
			'postTypeArgs',
			'slug',
			'source',
			'source_fingerprint',
			'sync',
			'template',
			'templateFor',
			'templateLock',
			'title',
			'trusted',
		);

		$meta = array();

		foreach ( $data as $key => $value ) {
			if ( ! in_array( $key, $known, true ) ) {
				$meta[ $key ] = $value;
			}
		}

		return $meta;
	}

	/**
	 * Add a discovery error.
	 *
	 * @param string $code    Error code.
	 * @param string $message Error message.
	 * @param array  $context Error context.
	 *
	 * @return void
	 */
	private function add_error( string $code, string $message, array $context = array() ): void {
		$this->errors[] = array(
			'code'    => $code,
			'message' => $message,
			'context' => $context,
		);
	}

	/**
	 * Normalize collection manifest data.
	 *
	 * @param array  $manifest      Raw manifest.
	 * @param string $manifest_path Manifest path.
	 * @param string $base_path     Registered base path.
	 *
	 * @return array|null Collection data.
	 */
	private static function normalize_collection_manifest( array $manifest, string $manifest_path, string $base_path ): ?array {
		$root = self::normalize_filesystem_path( dirname( $manifest_path ) );
		$slug = $manifest['collection'] ?? $manifest['slug'] ?? $manifest['name'] ?? basename( $root );

		if ( ! is_scalar( $slug ) ) {
			return null;
		}

		$slug = sanitize_key( (string) $slug );

		if ( '' === $slug ) {
			return null;
		}

		$post_type = isset( $manifest['postType'] ) && is_scalar( $manifest['postType'] )
			? sanitize_key( (string) $manifest['postType'] )
			: 'page';

		if ( '' === $post_type || strlen( $post_type ) > 20 ) {
			return null;
		}

		$title = isset( $manifest['title'] ) && is_scalar( $manifest['title'] )
			? (string) $manifest['title']
			: self::title_from_value( $slug );

		$known = array( 'collection', 'defaults', 'meta', 'name', 'order', 'postType', 'postTypeArgs', 'slug', 'source', 'title' );
		$meta  = isset( $manifest['meta'] ) && is_array( $manifest['meta'] ) ? $manifest['meta'] : array();

		foreach ( $manifest as $key => $value ) {
			if ( ! in_array( $key, $known, true ) ) {
				$meta[ $key ] = $value;
			}
		}

		return array(
			'slug'          => $slug,
			'title'         => $title,
			'root'          => $root,
			'base_path'     => self::normalize_filesystem_path( $base_path ),
			'manifest_path' => self::normalize_filesystem_path( $manifest_path ),
			'postType'      => $post_type,
			'postTypeArgs'  => isset( $manifest['postTypeArgs'] ) && is_array( $manifest['postTypeArgs'] ) ? $manifest['postTypeArgs'] : array(),
			'defaults'      => isset( $manifest['defaults'] ) && is_array( $manifest['defaults'] ) ? $manifest['defaults'] : array(),
			'source'        => isset( $manifest['source'] ) && is_array( $manifest['source'] ) ? $manifest['source'] : array(),
			'order'         => isset( $manifest['order'] ) && is_numeric( $manifest['order'] ) ? (int) $manifest['order'] : null,
			'meta'          => $meta,
			'layout_path'   => file_exists( $root . '/layout.php' ) ? $root . '/layout.php' : null,
		);
	}

	/**
	 * Find pages.json manifest paths.
	 *
	 * @param string $base_path Base path.
	 *
	 * @return array<int, string> Manifest paths.
	 */
	private static function find_manifest_paths( string $base_path ): array {
		$paths = array();

		if ( file_exists( $base_path . '/pages.json' ) ) {
			$paths[] = $base_path . '/pages.json';
		}

		$iterator = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator( $base_path, RecursiveDirectoryIterator::SKIP_DOTS )
		);

		foreach ( $iterator as $file ) {
			if ( $file->isFile() && 'pages.json' === $file->getBasename() ) {
				$paths[] = self::normalize_filesystem_path( $file->getPathname() );
			}
		}

		$paths = array_values( array_unique( $paths ) );

		usort(
			$paths,
			function ( string $a, string $b ): int {
				return strlen( $a ) <=> strlen( $b );
			}
		);

		return $paths;
	}

	/**
	 * Build a generated page name and avoid key collisions.
	 *
	 * @param string $collection Collection slug.
	 * @param string $path       Logical path.
	 *
	 * @return string Name.
	 */
	private function unique_generated_name( string $collection, string $path ): string {
		$base = sanitize_key( $collection . '-' . str_replace( '/', '-', $path ) );
		$name = $base;
		$i    = 2;

		while ( isset( $this->pages[ self::page_key( $collection, $name ) ] ) ) {
			$name = $base . '-' . $i;
			++$i;
		}

		return $name;
	}

	/**
	 * Build a page name from a logical path.
	 *
	 * @param string      $path       Logical path.
	 * @param string|null $collection Collection slug.
	 *
	 * @return string Name.
	 */
	private static function name_from_path( string $path, ?string $collection ): string {
		if ( '.' === $path ) {
			return sanitize_key( $collection ? $collection . '-home' : 'home' );
		}

		$name = str_replace( '/', '-', $path );

		return sanitize_key( $collection ? $collection . '-' . $name : $name );
	}

	/**
	 * Get path depth.
	 *
	 * @param string $path Logical path.
	 *
	 * @return int Depth.
	 */
	private function path_depth( string $path ): int {
		if ( '.' === $path ) {
			return 0;
		}

		return substr_count( $path, '/' ) + 1;
	}

	/**
	 * Normalize filesystem path.
	 *
	 * @param string $path Filesystem path.
	 *
	 * @return string Normalized path without trailing slash.
	 */
	private static function normalize_filesystem_path( string $path ): string {
		return untrailingslashit( wp_normalize_path( $path ) );
	}

	/**
	 * Find the nearest layout.php for a non-collection page by walking up to the discovery base.
	 *
	 * @param string $directory Page directory.
	 *
	 * @return string|null Layout path or null.
	 */
	private function find_nearest_layout( string $directory ): ?string {
		$current = self::normalize_filesystem_path( $directory );
		$base    = $this->discover_base;

		if ( '' === $base || ! self::is_inside_any_path( $current, array( $base ) ) ) {
			$candidate = $current . '/layout.php';

			return file_exists( $candidate ) ? $candidate : null;
		}

		while ( true ) {
			$candidate = $current . '/layout.php';

			if ( file_exists( $candidate ) ) {
				return $candidate;
			}

			if ( $current === $base ) {
				return null;
			}

			$current = self::normalize_filesystem_path( dirname( $current ) );
		}
	}

	/**
	 * Build relative path.
	 *
	 * @param string $base Base path.
	 * @param string $path Full path.
	 *
	 * @return string Relative path.
	 */
	private static function relative_path( string $base, string $path ): string {
		$base = self::normalize_filesystem_path( $base );
		$path = self::normalize_filesystem_path( $path );

		if ( $base === $path ) {
			return '';
		}

		if ( str_starts_with( $path, $base . '/' ) ) {
			return substr( $path, strlen( $base ) + 1 );
		}

		return $path;
	}

	/**
	 * Check whether path is inside any root.
	 *
	 * @param string $path  Path.
	 * @param array  $roots Root paths.
	 *
	 * @return bool Whether path is inside.
	 */
	private static function is_inside_any_path( string $path, array $roots ): bool {
		foreach ( $roots as $root ) {
			if ( self::is_same_or_inside_path( $path, $root ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Check whether a path is the root or inside the root.
	 *
	 * @param string $path Path.
	 * @param string $root Root path.
	 *
	 * @return bool Whether path is inside root.
	 */
	private static function is_same_or_inside_path( string $path, string $root ): bool {
		$path = self::normalize_filesystem_path( $path );
		$root = self::normalize_filesystem_path( $root );

		return $path === $root || str_starts_with( $path, $root . '/' );
	}
}
