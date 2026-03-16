<?php
/**
 * Db class.
 *
 * Public PHP API for interacting with block database schemas.
 *
 * @package Blockstudio
 */

namespace Blockstudio;

/**
 * Provides a fluent PHP interface for block database operations.
 *
 * Usage:
 *   $db = Db::get('mytheme/block', 'subscribers');
 *   $db->create(['email' => 'a@b.com']);
 *   $db->list(['plan' => 'pro']);
 *   $db->get(1);
 *   $db->update(1, ['plan' => 'enterprise']);
 *   $db->delete(1);
 *
 * @since 7.1.0
 */
class Db {

	/**
	 * The compound schema key.
	 *
	 * @var string
	 */
	private string $key;

	/**
	 * Constructor.
	 *
	 * @param string $key The compound schema key.
	 */
	private function __construct( string $key ) {
		$this->key = $key;
	}

	/**
	 * Get a database instance for a block schema.
	 *
	 * @param string $block_name  The block name (e.g. "mytheme/block").
	 * @param string $schema_name The schema name (default: "default").
	 *
	 * @return self|null The Db instance or null if schema not found.
	 */
	public static function get( string $block_name, string $schema_name = 'default' ): ?self {
		$schemas = Database::get_all();
		$key     = $block_name . ':' . $schema_name;

		if ( ! isset( $schemas[ $key ] ) ) {
			return null;
		}

		return new self( $key );
	}

	/**
	 * Create a record.
	 *
	 * @param array $data The record data.
	 *
	 * @return array|\WP_Error The created record or validation error.
	 */
	public function create( array $data ) {
		return Database::execute( 'create', $this->key, array( 'data' => $data ) );
	}

	/**
	 * List records.
	 *
	 * @param array $filters Optional field equality filters.
	 * @param int   $limit   Maximum records (default 50, max 100).
	 * @param int   $offset  Record offset.
	 *
	 * @return array The records.
	 */
	public function list( array $filters = array(), int $limit = 50, int $offset = 0 ): array {
		$result = Database::execute(
			'list',
			$this->key,
			array(
				'filters' => $filters,
				'limit'   => min( $limit, 100 ),
				'offset'  => $offset,
			)
		);

		return is_array( $result ) ? $result : array();
	}

	/**
	 * Get a single record by ID.
	 *
	 * @param int $id The record ID.
	 *
	 * @return array|null The record or null.
	 */
	public function get_record( int $id ) {
		return Database::execute( 'get', $this->key, array( 'id' => $id ) );
	}

	/**
	 * Update a record.
	 *
	 * @param int   $id   The record ID.
	 * @param array $data The fields to update.
	 *
	 * @return array|\WP_Error|null The updated record, null if not found, or validation error.
	 */
	public function update( int $id, array $data ) {
		return Database::execute(
			'update',
			$this->key,
			array(
				'id'   => $id,
				'data' => $data,
			)
		);
	}

	/**
	 * Delete a record.
	 *
	 * @param int $id The record ID.
	 *
	 * @return bool Whether the record was deleted.
	 */
	public function delete( int $id ): bool {
		return (bool) Database::execute( 'delete', $this->key, array( 'id' => $id ) );
	}

	/**
	 * Render all field components as a form.
	 *
	 * Loops through fields that have a 'component' key and renders each
	 * one using bs_block(). Returns the concatenated HTML.
	 *
	 * @return string The rendered form HTML.
	 */
	public function form(): string {
		$schemas = Database::get_all();
		$schema  = $schemas[ $this->key ] ?? null;

		if ( ! $schema ) {
			return '';
		}

		$fields = $schema['fields'] ?? array();
		$output = '';

		foreach ( $fields as $field_name => $def ) {
			if ( ! isset( $def['component'] ) ) {
				continue;
			}

			$component = $def['component'];

			if ( is_string( $component ) ) {
				$component = array( 'name' => $component );
			}

			$component['attributes']               = $component['attributes'] ?? array();
			$component['attributes']['_fieldName'] = $field_name;
			$component['attributes']['_fieldType'] = $def['type'] ?? 'string';
			$component['attributes']['_required']  = ! empty( $def['required'] );
			$component['attributes']['_enum']      = $def['enum'] ?? array();
			$component['attributes']['_format']    = $def['format'] ?? '';
			$component['attributes']['_maxLength'] = $def['maxLength'] ?? null;
			$component['attributes']['_minLength'] = $def['minLength'] ?? null;

			$output .= self::render_block_tree( $component );
		}

		return $output;
	}

	/**
	 * Render a block tree recursively.
	 *
	 * Accepts the same format as WordPress serialized blocks:
	 * name, attributes, innerBlocks. Renders inner blocks first
	 * and passes the result as $content to the parent.
	 *
	 * @param array $block The block definition.
	 *
	 * @return string The rendered HTML.
	 */
	public static function render_block_tree( array $block ): string {
		$inner_blocks = $block['innerBlocks'] ?? array();
		$content      = '';

		foreach ( $inner_blocks as $inner ) {
			$content .= self::render_block_tree( $inner );
		}

		return bs_block(
			array(
				'name'    => $block['name'] ?? '',
				'data'    => $block['attributes'] ?? array(),
				'content' => $content,
			)
		);
	}
}
