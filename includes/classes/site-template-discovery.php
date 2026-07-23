<?php
/**
 * Site template discovery class.
 *
 * @package Blockstudio
 */

namespace Blockstudio;

/**
 * Discovers file-backed Site Editor templates and template parts.
 *
 * @since 7.5.0
 */
final class Site_Template_Discovery {

	/**
	 * Discovered templates.
	 *
	 * @var array<string, array>
	 */
	private array $templates = array();

	/**
	 * Discovered template parts.
	 *
	 * @var array<string, array>
	 */
	private array $parts = array();

	/**
	 * Discovery errors.
	 *
	 * @var array<int, array>
	 */
	private array $errors = array();

	/**
	 * Directories scanned during discovery.
	 *
	 * @var array<int, string>
	 */
	private array $scanned_dirs = array();

	/**
	 * Discover templates and template parts.
	 *
	 * @param array $template_paths Template root paths.
	 * @param array $part_paths     Template part root paths.
	 *
	 * @return array{templates: array<string, array>, parts: array<string, array>}
	 */
	public function discover( array $template_paths, array $part_paths ): array {
		$this->templates    = array();
		$this->parts        = array();
		$this->errors       = array();
		$this->scanned_dirs = array();

		$this->discover_type( $template_paths, 'wp_template', 'template.json' );
		$this->discover_type( $part_paths, 'wp_template_part', 'part.json' );

		return array(
			'templates' => $this->templates,
			'parts'     => $this->parts,
		);
	}

	/**
	 * Get discovery errors.
	 *
	 * @return array<int, array> Errors.
	 */
	public function get_errors(): array {
		return $this->errors;
	}

	/**
	 * Get scanned directories.
	 *
	 * @return array<int, string> Directories.
	 */
	public function get_scanned_dirs(): array {
		return array_values( array_unique( $this->scanned_dirs ) );
	}

	/**
	 * Discover one Site Editor template type.
	 *
	 * @param array  $paths    Root paths.
	 * @param string $type     Template type.
	 * @param string $manifest Manifest file name.
	 *
	 * @return void
	 */
	private function discover_type( array $paths, string $type, string $manifest ): void {
		$context = 'wp_template' === $type ? 'site-templates' : 'site-template-parts';
		$sources = $this->normalize_sources( $paths, $context );

		foreach ( $sources as $source ) {
			$this->scanned_dirs = array_merge( $this->scanned_dirs, $source->watch_paths() );

			foreach ( $source->entries() as $entry ) {
				$file_path    = self::normalize_path( $entry->physical_path() );
				$logical_path = $entry->logical_path();

				if ( basename( $logical_path ) !== $manifest ) {
					continue;
				}

				$data = $this->process_manifest( $file_path, $logical_path, $source, $type );

				if ( null === $data ) {
					continue;
				}

				$slug = $data['slug'];

				if ( 'wp_template' === $type ) {
					if ( isset( $this->templates[ $slug ] ) ) {
						$this->add_error( 'duplicate_template', 'Duplicate Site Editor template slug.', $data );
						continue;
					}

					$this->templates[ $slug ] = $data;
				} else {
					if ( isset( $this->parts[ $slug ] ) ) {
						$this->add_error( 'duplicate_part', 'Duplicate Site Editor template part slug.', $data );
						continue;
					}

					$this->parts[ $slug ] = $data;
				}
			}
		}
	}

	/**
	 * Process one manifest.
	 *
	 * @param string           $manifest_path Manifest path.
	 * @param string           $logical_path Logical manifest path.
	 * @param Discovery_Source $source Discovery source.
	 * @param string           $type Template type.
	 *
	 * @return array|null Template data.
	 */
	private function process_manifest( string $manifest_path, string $logical_path, Discovery_Source $source, string $type ): ?array {
		$directory   = self::normalize_path( dirname( $manifest_path ) );
		$logical_dir = dirname( $logical_path );
		$logical_dir = '.' === $logical_dir ? '' : $logical_dir;
		$manifest    = Utils::read_json_file( $manifest_path );

		if ( ! is_array( $manifest ) ) {
			$this->add_error(
				'invalid_manifest',
				'Invalid Site Editor template manifest.',
				array(
					'path' => $manifest_path,
					'type' => $type,
				)
			);
			return null;
		}

		$folder_slug = basename( $directory );
		$slug        = self::normalize_slug( $manifest['slug'] ?? $manifest['name'] ?? $folder_slug );

		if ( null === $slug ) {
			$this->add_error(
				'invalid_slug',
				'Invalid Site Editor template slug.',
				array(
					'path' => $manifest_path,
					'type' => $type,
				)
			);
			return null;
		}

		$source_path = $this->resolve_source_path( $logical_dir, $directory, $manifest, $source );

		if ( null === $source_path ) {
			$this->add_error(
				'missing_source',
				'Missing Site Editor template source file.',
				array(
					'path' => $manifest_path,
					'type' => $type,
					'slug' => $slug,
				)
			);
			return null;
		}

		$known = array(
			'slug',
			'name',
			'title',
			'description',
			'postTypes',
			'post_types',
			'source',
			'template',
			'file',
			'status',
			'sync',
			'area',
			'meta',
		);
		$meta  = is_array( $manifest['meta'] ?? null ) ? $manifest['meta'] : array();

		foreach ( $manifest as $key => $value ) {
			if ( ! in_array( $key, $known, true ) ) {
				$meta[ $key ] = $value;
			}
		}

		$data = array(
			'slug'          => $slug,
			'name'          => $slug,
			'title'         => is_scalar( $manifest['title'] ?? null ) ? (string) $manifest['title'] : self::title_from_slug( $slug ),
			'description'   => is_scalar( $manifest['description'] ?? null ) ? (string) $manifest['description'] : '',
			'type'          => $type,
			'status'        => is_scalar( $manifest['status'] ?? null ) ? sanitize_key( (string) $manifest['status'] ) : 'publish',
			'manifest_path' => $manifest_path,
			'source_path'   => $source_path,
			'directory'     => self::normalize_path( dirname( $source_path ) ),
			'root_path'     => $source->root(),
			'logical_path'  => $logical_path,
			'source_id'     => $source->id(),
			'provenance'    => $source->resolve( $logical_path )?->provenance() ?? array(),
			'source_type'   => self::source_type_from_path( $source_path ),
			'is_twig'       => str_ends_with( $source_path, '.twig' ),
			'is_blade'      => str_ends_with( $source_path, '.blade.php' ),
			'modified'      => self::latest_modified_time( array( $manifest_path, $source_path ) ),
			'meta'          => $meta,
		);

		if ( 'wp_template' === $type ) {
			$data['postTypes'] = $this->normalize_post_types( $manifest['postTypes'] ?? $manifest['post_types'] ?? array() );
			$data['sync']      = (bool) ( $manifest['sync'] ?? false );
		} else {
			$data['area'] = $this->normalize_area( $manifest['area'] ?? '' );
		}

		return $data;
	}

	/**
	 * Resolve the source file for a manifest.
	 *
	 * @param string           $logical_directory Logical manifest directory.
	 * @param string           $directory Physical manifest directory.
	 * @param array            $manifest Manifest data.
	 * @param Discovery_Source $source Discovery source.
	 *
	 * @return string|null Source path.
	 */
	private function resolve_source_path( string $logical_directory, string $directory, array $manifest, Discovery_Source $source ): ?string {
		$explicit = $manifest['source'] ?? $manifest['template'] ?? $manifest['file'] ?? null;

		if ( is_scalar( $explicit ) && '' !== (string) $explicit ) {
			return $this->resolve_relative_source( $logical_directory, (string) $explicit, $source );
		}

		$filenames  = array( 'index.php', 'index.blade.php', 'index.twig', 'index.html' );
		$candidates = array();

		foreach ( $filenames as $filename ) {
			$logical = '' === $logical_directory ? $filename : $logical_directory . '/' . $filename;
			$entry   = $source->resolve( $logical );

			if ( $entry ) {
				$candidates[] = $entry->physical_path();
			}
		}

		/**
		 * Filter source candidates for Site Editor templates and template parts.
		 *
		 * @param array  $candidates Candidate source paths.
		 * @param string $directory  Manifest directory.
		 * @param array  $manifest   Manifest data.
		 */
		$candidates = apply_filters( 'blockstudio/site_templates/template_candidates', $candidates, $directory, $manifest );

		return Utils::first_existing_path( $candidates );
	}

	/**
	 * Resolve a relative source path inside the manifest directory.
	 *
	 * @param string           $directory Logical manifest directory.
	 * @param string           $source_path Source value.
	 * @param Discovery_Source $source Discovery source.
	 *
	 * @return string|null Source path.
	 */
	private function resolve_relative_source( string $directory, string $source_path, Discovery_Source $source ): ?string {
		$source_path = trim( str_replace( '\\', '/', $source_path ) );

		$segments = explode( '/', $source_path );

		if (
			'' === $source_path ||
			str_starts_with( $source_path, '/' ) ||
			str_contains( $source_path, '?' ) ||
			str_contains( $source_path, '#' ) ||
			in_array( '..', $segments, true ) ||
			preg_match( '/^[A-Za-z][A-Za-z0-9+.-]*:/', $source_path )
		) {
			return null;
		}

		$logical = Discovery_Sources::normalize_logical_path(
			implode( '/', array_filter( array( $directory, $source_path ), 'strlen' ) )
		);
		$entry   = $source->resolve( $logical );

		return $entry?->physical_path();
	}

	/**
	 * Normalize physical paths and source objects into discovery sources.
	 *
	 * @param array  $values Paths or sources.
	 * @param string $context Discovery context.
	 *
	 * @return array<int, Discovery_Source> Discovery sources.
	 */
	private function normalize_sources( array $values, string $context ): array {
		$sources = array();
		$paths   = array();

		foreach ( $values as $value ) {
			if ( $value instanceof Discovery_Source ) {
				$sources[] = $value;
			} elseif ( is_string( $value ) && '' !== $value ) {
				$paths[] = $value;
			}
		}

		return empty( $paths )
			? $sources
			: array_merge( $sources, Discovery_Sources::for_paths( $context, $paths ) );
	}

	/**
	 * Normalize a template slug.
	 *
	 * @param mixed $value Slug value.
	 *
	 * @return string|null Slug.
	 */
	public static function normalize_slug( mixed $value ): ?string {
		if ( ! is_scalar( $value ) ) {
			return null;
		}

		$slug = strtolower( trim( (string) $value ) );

		if ( '' === $slug || str_contains( $slug, '/' ) || str_contains( $slug, '\\' ) || str_contains( $slug, '..' ) ) {
			return null;
		}

		$slug = sanitize_title_with_dashes( $slug );

		return preg_match( '/^[a-z0-9][a-z0-9_-]*$/', $slug ) ? $slug : null;
	}

	/**
	 * Build a title from a slug-like value.
	 *
	 * @param mixed $value Value.
	 *
	 * @return string Title.
	 */
	public static function title_from_slug( mixed $value ): string {
		if ( is_scalar( $value ) && '' !== trim( (string) $value ) ) {
			$value = (string) $value;
		} else {
			$value = '';
		}

		$value = str_replace( array( '-', '_' ), ' ', $value );

		return ucwords( $value );
	}

	/**
	 * Normalize a filesystem path.
	 *
	 * @param string $path Path.
	 *
	 * @return string Normalized path.
	 */
	public static function normalize_path( string $path ): string {
		return untrailingslashit( wp_normalize_path( $path ) );
	}

	/**
	 * Infer a source type from a path.
	 *
	 * @param string $path Source path.
	 *
	 * @return string Source type.
	 */
	public static function source_type_from_path( string $path ): string {
		if ( str_ends_with( $path, '.blade.php' ) ) {
			return 'blade';
		}

		if ( str_ends_with( $path, '.twig' ) ) {
			return 'twig';
		}

		if ( str_ends_with( $path, '.html' ) ) {
			return 'html';
		}

		return 'php';
	}

	/**
	 * Normalize post type values.
	 *
	 * @param mixed $value Post type value.
	 *
	 * @return array<int, string> Post types.
	 */
	private function normalize_post_types( mixed $value ): array {
		if ( is_string( $value ) && '' !== $value ) {
			$value = array( $value );
		}

		if ( ! is_array( $value ) ) {
			return array();
		}

		return array_values(
			array_unique(
				array_filter(
					array_map(
						static fn( $post_type ): string => is_scalar( $post_type ) ? sanitize_key( (string) $post_type ) : '',
						$value
					)
				)
			)
		);
	}

	/**
	 * Normalize a template part area.
	 *
	 * @param mixed $value Area value.
	 *
	 * @return string Area.
	 */
	private function normalize_area( mixed $value ): string {
		$area = is_scalar( $value ) ? sanitize_key( (string) $value ) : '';

		if ( '' === $area ) {
			$area = defined( 'WP_TEMPLATE_PART_AREA_UNCATEGORIZED' ) ? WP_TEMPLATE_PART_AREA_UNCATEGORIZED : 'uncategorized';
		}

		if ( function_exists( '_filter_block_template_part_area' ) ) {
			$area = _filter_block_template_part_area( $area );
		}

		return $area;
	}

	/**
	 * Get the latest modification time for a list of files.
	 *
	 * @param array $files Files.
	 *
	 * @return string|null Modified timestamp.
	 */
	private static function latest_modified_time( array $files ): ?string {
		$latest = 0;

		foreach ( $files as $file ) {
			if ( is_string( $file ) && is_file( $file ) ) {
				$latest = max( $latest, (int) filemtime( $file ) );
			}
		}

		return $latest > 0 ? gmdate( 'Y-m-d H:i:s', $latest ) : null;
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
}
