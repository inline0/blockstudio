<?php
/**
 * Canvas inventory and document data.
 *
 * @package Blockstudio
 */

namespace Blockstudio;

/**
 * Internal implementation for Canvas' public consumer-neutral data API.
 *
 * Existing registries remain authoritative. This class selects and normalizes
 * their records without creating a second registry or synchronizing content.
 *
 * @since 7.6.0
 */
final class Canvas_Data {

	/**
	 * Public canvas result schema version.
	 */
	public const SCHEMA_VERSION = 1;

	/**
	 * Supported inventory types in stable default order.
	 *
	 * @var array<int, string>
	 */
	private const TYPES = array( 'pages', 'blocks', 'patterns', 'templates', 'parts', 'ui' );

	/**
	 * Return a normalized inventory for all or explicitly selected content.
	 *
	 * Selection semantics are intentionally strict:
	 *
	 * - no recognized keys: load every type;
	 * - a recognized key with `true`, `null`, or `"*"`: load all of that type;
	 * - a recognized key with a list/string: load only matching records;
	 * - a recognized key with an empty list/string/`false`: load none;
	 * - omitted types in a targeted selection are never read.
	 *
	 * @param array $selection Type-keyed IDs, names, paths, or source paths.
	 * @param array $options   Optional ordering and consumer metadata.
	 *
	 * @return array Canvas inventory DTO.
	 */
	public static function inventory( array $selection = array(), array $options = array() ): array {
		$normalized = self::normalize_selection( $selection );
		$result     = self::empty_result( $normalized['public'] );

		foreach ( self::TYPES as $type ) {
			if ( ! array_key_exists( $type, $normalized['requested'] ) ) {
				continue;
			}

			try {
				$records = self::records( $type, $normalized['requested'][ $type ] );
				$loaded  = self::select_records(
					$type,
					$records,
					$normalized['requested'][ $type ],
					$result['deleted'][ $type ]
				);

				$result['inventory'][ $type ] = $loaded;
			} catch ( \Throwable $throwable ) {
				$result['errors'][] = self::issue(
					$type . '_inventory_failed',
					$throwable->getMessage(),
					$type
				);
			}
		}

		if ( array_key_exists( 'pages', $normalized['requested'] ) ) {
			foreach ( Pages::errors() as $error ) {
				$result['errors'][] = self::normalize_issue( $error, 'pages' );
			}
		}

		if ( array_key_exists( 'templates', $normalized['requested'] )
			|| array_key_exists( 'parts', $normalized['requested'] )
		) {
			$template_request = array_key_exists( 'templates', $normalized['requested'] )
				? $normalized['requested']['templates']
				: array();
			$part_request     = array_key_exists( 'parts', $normalized['requested'] )
				? $normalized['requested']['parts']
				: array();
			$site_errors      = null === $template_request || null === $part_request
				? Site_Templates::errors()
				: array_merge(
					array() === $template_request ? array() : Site_Templates::selection_errors( 'templates' ),
					array() === $part_request ? array() : Site_Templates::selection_errors( 'parts' )
				);

			foreach ( $site_errors as $error ) {
				$result['errors'][] = self::normalize_issue( $error, 'site-templates' );
			}
		}

		$result['order']   = self::order( $result['inventory'], $options );
		$result['sources'] = self::sources( $result['inventory'] );

		/**
		 * Filter the public canvas inventory DTO.
		 *
		 * @since 7.6.0
		 *
		 * @param array $result    Inventory DTO.
		 * @param array $selection Original selection.
		 * @param array $options   Options.
		 */
		$filtered = apply_filters( 'blockstudio/canvas/inventory', $result, $selection, $options );

		return is_array( $filtered ) ? $filtered : $result;
	}

	/**
	 * Render exact documents for an inventory selection.
	 *
	 * @param array $selection Type-keyed IDs, names, paths, or source paths.
	 * @param array $options   Inventory and document options.
	 *
	 * @return array Canvas document DTO.
	 */
	public static function documents( array $selection = array(), array $options = array() ): array {
		$result              = self::inventory( $selection, $options );
		$result['documents'] = array_fill_keys( self::TYPES, array() );

		foreach ( $result['order'] as $ordered ) {
			$type = is_string( $ordered['type'] ?? null ) ? $ordered['type'] : '';
			$id   = is_string( $ordered['id'] ?? null ) ? $ordered['id'] : '';
			$item = self::find_item( $result['inventory'][ $type ] ?? array(), $id );

			if ( null === $item ) {
				continue;
			}

			try {
				Batch_Render::reset();
				$document = self::render_item( $type, $item, $options );

				$result['documents'][ $type ][] = array(
					'id'       => $id,
					'type'     => $type,
					'document' => $document,
				);

				foreach ( $document['warnings'] ?? array() as $warning ) {
					$result['warnings'][] = self::normalize_issue( $warning, $type, $id );
				}

				foreach ( $document['errors'] ?? array() as $error ) {
					$result['errors'][] = self::normalize_issue( $error, $type, $id );
				}
			} catch ( \Throwable $throwable ) {
				$result['errors'][] = self::issue(
					$type . '_render_failed',
					$throwable->getMessage(),
					$type,
					$id
				);
			}
		}

		/**
		 * Filter the public canvas document DTO.
		 *
		 * @since 7.6.0
		 *
		 * @param array $result    Document DTO.
		 * @param array $selection Original selection.
		 * @param array $options   Options.
		 */
		$filtered = apply_filters( 'blockstudio/canvas/documents', $result, $selection, $options );

		return is_array( $filtered ) ? $filtered : $result;
	}

	/**
	 * Normalize selection input.
	 *
	 * @param array $selection Selection.
	 *
	 * @return array{requested:array<string, array<int, string>|null>,public:array}
	 */
	private static function normalize_selection( array $selection ): array {
		$targeted  = false;
		$requested = array();
		$public    = array( 'targeted' => false );

		foreach ( self::TYPES as $type ) {
			if ( array_key_exists( $type, $selection ) ) {
				$targeted = true;
			}
		}

		foreach ( self::TYPES as $type ) {
			if ( $targeted && ! array_key_exists( $type, $selection ) ) {
				$public[ $type ] = array();
				continue;
			}

			$value = $targeted ? $selection[ $type ] : null;

			if ( null === $value || true === $value || '*' === $value ) {
				$requested[ $type ] = null;
				$public[ $type ]    = null;
				continue;
			}

			if ( false === $value ) {
				$value = array();
			} elseif ( is_string( $value ) ) {
				$value = explode( ',', $value );
			}

			if ( ! is_array( $value ) ) {
				$value = array();
			}

			$ids = array_values(
				array_unique(
					array_filter(
						array_map(
							static fn( mixed $id ): string => is_scalar( $id ) ? trim( (string) $id ) : '',
							$value
						),
						static fn( string $id ): bool => '' !== $id
					)
				)
			);

			$requested[ $type ] = $ids;
			$public[ $type ]    = $ids;
		}

		$public['targeted'] = $targeted;

		return array(
			'requested' => $requested,
			'public'    => $public,
		);
	}

	/**
	 * Create a stable empty result.
	 *
	 * @param array $selection Public selection.
	 *
	 * @return array Canvas result.
	 */
	private static function empty_result( array $selection ): array {
		$inventory = array_fill_keys( self::TYPES, array() );

		return array(
			'schemaVersion' => self::SCHEMA_VERSION,
			'selection'     => $selection,
			'inventory'     => $inventory,
			'order'         => array(),
			'sources'       => array_fill_keys( self::TYPES, array() ),
			'warnings'      => array(),
			'errors'        => array(),
			'deleted'       => array_fill_keys( self::TYPES, array() ),
		);
	}

	/**
	 * Read canonical records for one type.
	 *
	 * @param string                  $type      Type.
	 * @param array<int, string>|null $requested Requested identifiers, null for all.
	 *
	 * @return array<int, array> Normalized records.
	 */
	private static function records( string $type, ?array $requested ): array {
		if ( array() === $requested ) {
			return array();
		}

		return match ( $type ) {
			'blocks'    => self::block_records( $requested ),
			'pages'     => self::page_records( $requested ),
			'patterns'  => self::pattern_records( $requested ),
			'templates' => self::site_template_records( false, $requested ),
			'parts'     => self::site_template_records( true, $requested ),
			'ui'        => self::ui_records( $requested ),
			default     => array(),
		};
	}

	/**
	 * Normalize registered blocks.
	 *
	 * @param array<int, string>|null $requested Requested identifiers, null for all.
	 *
	 * @return array<int, array> Records.
	 */
	private static function block_records( ?array $requested ): array {
		$data    = Build::data();
		$blocks  = Build::blocks();
		$names   = array_values( array_unique( array_merge( array_keys( $data ), array_keys( $blocks ) ) ) );
		$records = array();

		foreach ( $names as $name ) {
			if ( ! is_string( $name ) || ! preg_match( '#^[a-z0-9-]+/[a-z0-9-]+$#', $name ) ) {
				continue;
			}

			$native = $blocks[ $name ] ?? null;
			$record = is_array( $data[ $name ] ?? null ) ? $data[ $name ] : array();
			$path   = self::block_path( $record, $native );

			if ( Ui::is_bundled_block( '' !== $path ? array( 'path' => $path ) : $native ) ) {
				continue;
			}

			$definitions = self::block_definitions( $record, $native );
			$attributes  = self::default_attributes( $definitions );
			$title       = self::object_string( $native, 'title' );

			if ( '' === $title ) {
				$title = is_scalar( $record['title'] ?? null )
					? (string) $record['title']
					: self::humanize( substr( $name, strpos( $name, '/' ) + 1 ) );
			}

			$normalized = array(
				'id'          => $name,
				'type'        => 'blocks',
				'name'        => $name,
				'title'       => $title,
				'source'      => self::record_string( $record, 'source_path', $path ),
				'path'        => $path,
				'provenance'  => self::record_string( $record, 'instance', 'blockstudio' ),
				'content'     => '',
				'declaration' => array(
					'name'       => $name,
					'attributes' => $attributes,
					'content'    => '',
					'children'   => array(),
				),
			);

			if ( self::record_is_requested( $normalized, $requested ) ) {
				$records[] = $normalized;
			}
		}

		return $records;
	}

	/**
	 * Normalize registered pages.
	 *
	 * @param array<int, string>|null $requested Requested identifiers, null for all.
	 *
	 * @return array<int, array> Records.
	 */
	private static function page_records( ?array $requested ): array {
		$records = array();

		foreach ( Pages::pages() as $key => $page ) {
			if ( ! is_array( $page ) ) {
				continue;
			}

			$id     = self::record_id( $page, $key );
			$source = self::record_string( $page, 'source_path', self::record_string( $page, 'path' ) );

			$normalized = array(
				'id'          => $id,
				'type'        => 'pages',
				'name'        => self::record_string( $page, 'name', $id ),
				'title'       => self::record_string( $page, 'title', self::humanize( $id ) ),
				'slug'        => self::record_string( $page, 'slug' ),
				'source'      => $source,
				'path'        => self::record_string( $page, 'path', $source ),
				'provenance'  => self::record_string( $page, 'collection', 'blockstudio-page' ),
				'contentType' => self::record_string( $page, 'contentType', 'blocks' ),
				'content'     => '',
				'layoutPath'  => self::record_string( $page, 'layout_path' ),
				'page'        => $page,
			);

			if ( ! self::record_is_requested( $normalized, $requested ) ) {
				continue;
			}

			$normalized['content'] = self::page_content( $page );
			$records[]             = $normalized;
		}

		return $records;
	}

	/**
	 * Normalize registered patterns.
	 *
	 * @param array<int, string>|null $requested Requested identifiers, null for all.
	 *
	 * @return array<int, array> Records.
	 */
	private static function pattern_records( ?array $requested ): array {
		$records = array();

		foreach ( Patterns::patterns() as $key => $pattern ) {
			if ( ! is_array( $pattern ) ) {
				continue;
			}

			$id = self::record_id( $pattern, $key );

			$normalized = array(
				'id'         => $id,
				'type'       => 'patterns',
				'name'       => self::record_string( $pattern, 'name', $id ),
				'title'      => self::record_string( $pattern, 'title', self::humanize( $id ) ),
				'source'     => self::record_string( $pattern, 'source_path' ),
				'path'       => self::record_string( $pattern, 'template_path' ),
				'provenance' => 'blockstudio-pattern',
				'content'    => '',
			);

			if ( ! self::record_is_requested( $normalized, $requested ) ) {
				continue;
			}

			$normalized['content'] = self::compiled_template_content( $pattern );
			$records[]             = $normalized;
		}

		return $records;
	}

	/**
	 * Normalize file-backed Site Editor templates or parts.
	 *
	 * @param bool                    $parts     Whether to return parts.
	 * @param array<int, string>|null $requested Requested identifiers, null for all.
	 *
	 * @return array<int, array> Records.
	 */
	private static function site_template_records( bool $parts, ?array $requested ): array {
		$records = $parts ? Site_Templates::parts( $requested ) : Site_Templates::templates( $requested );
		$type    = $parts ? 'parts' : 'templates';
		$result  = array();

		foreach ( $records as $key => $record ) {
			if ( ! is_array( $record ) ) {
				continue;
			}

			$id         = self::record_id( $record, $key );
			$normalized = array(
				'id'         => $id,
				'type'       => $type,
				'name'       => self::record_string( $record, 'slug', $id ),
				'title'      => self::record_string( $record, 'title', self::humanize( $id ) ),
				'source'     => self::record_string( $record, 'source_path' ),
				'path'       => self::record_string( $record, 'path' ),
				'provenance' => 'blockstudio-site-template',
				'content'    => self::record_string( $record, 'content' ),
				'area'       => self::record_string( $record, 'area' ),
			);

			if ( self::record_is_requested( $normalized, $requested ) ) {
				$result[] = $normalized;
			}
		}

		return $result;
	}

	/**
	 * Normalize bundled UI examples.
	 *
	 * @param array<int, string>|null $requested Requested identifiers, null for all.
	 *
	 * @return array<int, array> Records.
	 */
	private static function ui_records( ?array $requested ): array {
		$records = array();

		foreach ( Ui::examples() as $example ) {
			if ( ! is_array( $example ) ) {
				continue;
			}

			$id = self::record_string( $example, 'id' );

			if ( '' === $id ) {
				continue;
			}

			$normalized = array(
				'id'           => $id,
				'type'         => 'ui',
				'name'         => self::record_string( $example, 'rootName' ),
				'title'        => self::record_string( $example, 'title', self::humanize( $id ) ),
				'source'       => self::record_string( $example, 'family' ),
				'path'         => '',
				'provenance'   => self::record_string( $example, 'provenance', 'blockstudio-ui' ),
				'content'      => '',
				'declaration'  => is_array( $example['declaration'] ?? null ) ? $example['declaration'] : array(),
				'dependencies' => is_array( $example['dependencies'] ?? null ) ? array_values( $example['dependencies'] ) : array(),
			);

			if ( self::record_is_requested( $normalized, $requested ) ) {
				$records[] = $normalized;
			}
		}

		return $records;
	}

	/**
	 * Select normalized records and report missing explicit IDs.
	 *
	 * @param string                  $type      Type.
	 * @param array<int, array>       $records   Records.
	 * @param array<int, string>|null $requested Requested identifiers, null for all.
	 * @param array<int, string>      $deleted   Missing identifiers.
	 *
	 * @return array<int, array> Selected records.
	 */
	private static function select_records( string $type, array $records, ?array $requested, array &$deleted ): array {
		$selected = array();
		$matched  = array();

		foreach ( $records as $record ) {
			$candidates = self::record_candidates( $record );
			$include    = null === $requested;

			if ( ! $include ) {
				foreach ( $requested as $id ) {
					if ( in_array( $id, $candidates, true ) ) {
						$include        = true;
						$matched[ $id ] = true;
					}
				}
			}

			if ( ! $include ) {
				continue;
			}

			$selected[] = $record;

			/**
			 * Fires exactly once for every selected inventory record.
			 *
			 * Instrumentation consumers may use this to prove a targeted
			 * request did not load an unrelated record.
			 *
			 * @since 7.6.0
			 *
			 * @param string $type   Inventory type.
			 * @param string $id     Canonical record ID.
			 * @param array  $record Normalized record.
			 */
			do_action( 'blockstudio/canvas/item_loaded', $type, (string) $record['id'], $record );
		}

		$deleted = null === $requested
			? array()
			: array_values(
				array_filter(
					$requested,
					static fn( string $id ): bool => ! isset( $matched[ $id ] )
				)
			);

		return $selected;
	}

	/**
	 * Render one selected item.
	 *
	 * @param string $type    Type.
	 * @param array  $item    Item.
	 * @param array  $options Options.
	 *
	 * @return array Render document.
	 */
	private static function render_item( string $type, array $item, array $options ): array {
		$document_options          = is_array( $options['document'] ?? null )
			? $options['document']
			: array();
		$document_options['title'] = $document_options['title'] ?? $item['title'];

		if ( in_array( $type, array( 'blocks', 'ui' ), true ) ) {
			return Render::document( $item['declaration'], $document_options );
		}

		$content = Render::content( self::record_string( $item, 'content' ) );

		if ( 'pages' === $type ) {
			$page    = is_array( $item['page'] ?? null ) ? $item['page'] : array();
			$content = Pages::render_layout(
				$content,
				$page,
				self::record_string( $item, 'layoutPath' )
			);
		}

		return Render::document_from_html(
			$content,
			self::block_names_from_content( self::record_string( $item, 'content' ) ),
			$document_options
		);
	}

	/**
	 * Build stable cross-type order.
	 *
	 * @param array $inventory Inventory.
	 * @param array $options   Options.
	 *
	 * @return array<int, array{type:string,id:string}> Order.
	 */
	private static function order( array $inventory, array $options ): array {
		$preferred = $options['order'] ?? Settings::get( 'canvas/order' );

		if ( ! is_array( $preferred ) ) {
			$preferred = Settings::get( 'dev/canvas/order' );
		}

		$preferred = is_array( $preferred )
			? array_values(
				array_filter(
					array_map(
						static fn( mixed $value ): string => is_scalar( $value ) ? trim( (string) $value ) : '',
						$preferred
					)
				)
			)
			: array();
		$positions = array_flip( $preferred );
		$rows      = array();
		$sequence  = 0;

		foreach ( self::TYPES as $type ) {
			foreach ( $inventory[ $type ] ?? array() as $item ) {
				$candidates = self::record_candidates( $item );
				$position   = PHP_INT_MAX;

				foreach ( $candidates as $candidate ) {
					if ( isset( $positions[ $candidate ] ) ) {
						$position = min( $position, (int) $positions[ $candidate ] );
					}
				}

				$rows[] = array(
					'type'      => $type,
					'id'        => (string) $item['id'],
					'position'  => $position,
					'sequence'  => $sequence++,
					'titleSort' => strtolower( self::record_string( $item, 'title', (string) $item['id'] ) ),
				);
			}
		}

		usort(
			$rows,
			static function ( array $left, array $right ): int {
				if ( $left['position'] !== $right['position'] ) {
					if ( PHP_INT_MAX === $left['position'] ) {
						return 1;
					}

					if ( PHP_INT_MAX === $right['position'] ) {
						return -1;
					}

					return $left['position'] <=> $right['position'];
				}

				if ( PHP_INT_MAX !== $left['position'] ) {
					return $left['sequence'] <=> $right['sequence'];
				}

				$title_order = strcmp( $left['titleSort'], $right['titleSort'] );

				return 0 !== $title_order
					? $title_order
					: ( $left['sequence'] <=> $right['sequence'] );
			}
		);

		return array_map(
			static fn( array $row ): array => array(
				'type' => $row['type'],
				'id'   => $row['id'],
			),
			$rows
		);
	}

	/**
	 * Return selected source paths by type.
	 *
	 * @param array $inventory Inventory.
	 *
	 * @return array<string, array<int, string>> Sources.
	 */
	private static function sources( array $inventory ): array {
		$sources = array_fill_keys( self::TYPES, array() );

		foreach ( self::TYPES as $type ) {
			foreach ( $inventory[ $type ] ?? array() as $item ) {
				$source = self::record_string( $item, 'source' );

				if ( '' !== $source && ! in_array( $source, $sources[ $type ], true ) ) {
					$sources[ $type ][] = $source;
				}
			}
		}

		return $sources;
	}

	/**
	 * Find one normalized item.
	 *
	 * @param array<int, array> $items Items.
	 * @param string            $id    ID.
	 *
	 * @return array|null Item.
	 */
	private static function find_item( array $items, string $id ): ?array {
		foreach ( $items as $item ) {
			if ( ( $item['id'] ?? null ) === $id ) {
				return $item;
			}
		}

		return null;
	}

	/**
	 * Get selection candidates for one record.
	 *
	 * @param array $record Record.
	 *
	 * @return array<int, string> Candidates.
	 */
	private static function record_candidates( array $record ): array {
		$candidates = array();

		foreach ( array( 'id', 'name', 'slug', 'source', 'path' ) as $key ) {
			$value = $record[ $key ] ?? null;

			if ( is_scalar( $value ) && '' !== trim( (string) $value ) ) {
				$candidates[] = trim( (string) $value );
			}
		}

		return array_values( array_unique( $candidates ) );
	}

	/**
	 * Determine whether a normalized record belongs to an exact selection.
	 *
	 * This check runs before content compilation or rendering so an unrelated
	 * registered record cannot wake expensive work for a targeted request.
	 *
	 * @param array                   $record    Normalized record metadata.
	 * @param array<int, string>|null $requested Requested identifiers, null for all.
	 *
	 * @return bool Whether the record is selected.
	 */
	private static function record_is_requested( array $record, ?array $requested ): bool {
		if ( null === $requested ) {
			return true;
		}

		foreach ( $requested as $id ) {
			if ( in_array( $id, self::record_candidates( $record ), true ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Resolve a stable record ID.
	 *
	 * @param array $record Record.
	 * @param mixed $key    Registry key.
	 *
	 * @return string ID.
	 */
	private static function record_id( array $record, mixed $key ): string {
		foreach ( array( 'key', 'id', 'name', 'slug' ) as $field ) {
			if ( is_scalar( $record[ $field ] ?? null ) && '' !== trim( (string) $record[ $field ] ) ) {
				return trim( (string) $record[ $field ] );
			}
		}

		return is_scalar( $key ) ? trim( (string) $key ) : '';
	}

	/**
	 * Read a string record value.
	 *
	 * @param array  $record  Record.
	 * @param string $key     Key.
	 * @param string $default Default.
	 *
	 * @return string Value.
	 */
	private static function record_string( array $record, string $key, string $default = '' ): string {
		return is_scalar( $record[ $key ] ?? null ) ? (string) $record[ $key ] : $default;
	}

	/**
	 * Read a string object property.
	 *
	 * @param mixed  $object Object.
	 * @param string $key    Property.
	 *
	 * @return string Value.
	 */
	private static function object_string( mixed $object, string $key ): string {
		return is_object( $object ) && isset( $object->{$key} ) && is_scalar( $object->{$key} )
			? (string) $object->{$key}
			: '';
	}

	/**
	 * Resolve block source path.
	 *
	 * @param array $record Block data.
	 * @param mixed $native Native block.
	 *
	 * @return string Path.
	 */
	private static function block_path( array $record, mixed $native ): string {
		$path = self::record_string( $record, 'path' );

		if ( '' === $path && is_object( $native ) && isset( $native->blockstudio['data']['path'] ) ) {
			$path = is_string( $native->blockstudio['data']['path'] )
				? $native->blockstudio['data']['path']
				: '';
		}

		return wp_normalize_path( $path );
	}

	/**
	 * Resolve block attribute definitions.
	 *
	 * @param array $record Block data.
	 * @param mixed $native Native block.
	 *
	 * @return array Definitions.
	 */
	private static function block_definitions( array $record, mixed $native ): array {
		if ( is_array( $record['attributes'] ?? null ) ) {
			return $record['attributes'];
		}

		return is_object( $native )
			&& isset( $native->blockstudio['attributes'] )
			&& is_array( $native->blockstudio['attributes'] )
				? $native->blockstudio['attributes']
				: array();
	}

	/**
	 * Resolve defaults from attribute definitions.
	 *
	 * @param array $definitions Definitions.
	 *
	 * @return array<string, mixed> Defaults.
	 */
	private static function default_attributes( array $definitions ): array {
		$defaults = array();

		foreach ( $definitions as $key => $definition ) {
			if ( ! is_array( $definition ) || ! array_key_exists( 'default', $definition ) ) {
				continue;
			}

			$id = is_string( $definition['id'] ?? null )
				? $definition['id']
				: ( is_string( $key ) ? $key : '' );

			if ( '' !== $id ) {
				$defaults[ $id ] = $definition['default'];
			}
		}

		return $defaults;
	}

	/**
	 * Return selected page content without synchronizing it.
	 *
	 * @param array $page Page.
	 *
	 * @return string Content.
	 */
	private static function page_content( array $page ): string {
		$post_id = isset( $page['post_id'] ) && is_numeric( $page['post_id'] )
			? (int) $page['post_id']
			: 0;

		if ( $post_id > 0 ) {
			$content = get_post_field( 'post_content', $post_id );

			if ( is_string( $content ) ) {
				return $content;
			}
		}

		$content = self::record_string( $page, 'content' );

		if ( ! empty( $page['is_markdown'] ) || 'markdown' === ( $page['contentType'] ?? null ) ) {
			$parts = Page_Markdown::split_frontmatter( $content );
			$body  = is_string( $parts['body'] ?? null ) ? $parts['body'] : $content;
			$html  = Page_Markdown::to_html( $body );

			if ( ! empty( $page['sanitize_content'] ) ) {
				$html = Page_Markdown::sanitize_docs_html( $html );
			}

			return is_string( $html ) ? $html : $content;
		}

		if ( '' !== $content ) {
			return $content;
		}

		return self::compiled_template_content( $page );
	}

	/**
	 * Compile one selected PHP/Blade/Twig/HTML source to block content.
	 *
	 * @param array $record Source record.
	 *
	 * @return string Content.
	 */
	private static function compiled_template_content( array $record ): string {
		$path = self::record_string( $record, 'template_path' );

		if ( '' === $path || ! is_file( $path ) ) {
			return self::record_string( $record, 'content' );
		}

		/**
		 * Fires immediately before one selected canvas source is compiled.
		 *
		 * @since 7.6.0
		 *
		 * @param string $path   Selected source path.
		 * @param array  $record Canonical source record.
		 */
		do_action( 'blockstudio/canvas/source_compiled', $path, $record );

		$directory = self::record_string( $record, 'directory' );
		$template  = Template_Compiler::compile(
			$path,
			'' !== $directory ? $directory : null
		);

		if ( ! is_string( $template ) ) {
			return '';
		}

		return Html_Parser::from_settings()->parse( $template );
	}

	/**
	 * Extract canonical block names from serialized content.
	 *
	 * @param string $content Content.
	 *
	 * @return array<int, string> Names.
	 */
	private static function block_names_from_content( string $content ): array {
		if ( ! preg_match_all( '#<!--\s*wp:([a-z0-9-]+/[a-z0-9-]+)#i', $content, $matches ) ) {
			return array();
		}

		return array_values( array_unique( array_map( 'strtolower', $matches[1] ) ) );
	}

	/**
	 * Normalize registry errors and render warnings.
	 *
	 * @param mixed  $value  Issue value.
	 * @param string $source Source.
	 * @param string $id     Optional item ID.
	 *
	 * @return array Issue.
	 */
	private static function normalize_issue( mixed $value, string $source, string $id = '' ): array {
		if ( is_array( $value ) ) {
			$code    = is_scalar( $value['code'] ?? null ) ? (string) $value['code'] : $source . '_issue';
			$message = is_scalar( $value['message'] ?? null ) ? (string) $value['message'] : wp_json_encode( $value );
		} elseif ( $value instanceof \Throwable ) {
			$code    = $source . '_issue';
			$message = $value->getMessage();
		} else {
			$code    = $source . '_issue';
			$message = is_scalar( $value ) ? (string) $value : 'Unknown issue.';
		}

		return self::issue( $code, $message, $source, $id );
	}

	/**
	 * Build one stable issue.
	 *
	 * @param string $code    Code.
	 * @param string $message Message.
	 * @param string $source  Source.
	 * @param string $id      Optional item ID.
	 *
	 * @return array Issue.
	 */
	private static function issue( string $code, string $message, string $source, string $id = '' ): array {
		$issue = array(
			'code'    => $code,
			'message' => $message,
			'source'  => $source,
		);

		if ( '' !== $id ) {
			$issue['id'] = $id;
		}

		return $issue;
	}

	/**
	 * Humanize a slug for fallback display.
	 *
	 * @param string $slug Slug.
	 *
	 * @return string Label.
	 */
	private static function humanize( string $slug ): string {
		return ucwords( str_replace( array( '-', '_', '/', ':' ), ' ', $slug ) );
	}
}
