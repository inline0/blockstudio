<?php
/**
 * Shared record store behaviour.
 *
 * @package Blockstudio
 */

namespace Blockstudio\Db;

/**
 * Base for the storage backends a db.php schema can select.
 */
abstract class Record_Store implements Record_Store_Interface {

	/**
	 * Schema key, in `block:schema` form.
	 *
	 * @var string
	 */
	protected string $key;

	/**
	 * Schema definition.
	 *
	 * @var array<string, mixed>
	 */
	protected array $schema;

	/**
	 * Path of the block file the schema was declared in.
	 *
	 * File-backed stores write beside it, so they need it resolved for them:
	 * the block registry is not their concern.
	 *
	 * @var string
	 */
	protected string $block_path;

	/**
	 * Constructor.
	 *
	 * @param string               $key        Schema key.
	 * @param array<string, mixed> $schema     Schema definition.
	 * @param string               $block_path Declaring block file path.
	 */
	public function __construct( string $key, array $schema = array(), string $block_path = '' ) {
		$this->key        = $key;
		$this->schema     = $schema;
		$this->block_path = $block_path;
	}

	/**
	 * Directory a file-backed store writes into.
	 *
	 * @return string
	 */
	protected function storage_directory(): string {
		return dirname( $this->block_path ) . '/db';
	}

	/**
	 * Schema name, the part of the key after the block name.
	 *
	 * @return string
	 */
	protected function schema_name(): string {
		$parts = explode( ':', $this->key, 2 );

		return $parts[1] ?? 'default';
	}

	/**
	 * Query records and report the total matching them.
	 *
	 * @param array<string, mixed> $filters Equality filters.
	 * @param int                  $limit   Maximum rows.
	 * @param int                  $offset  Row offset.
	 *
	 * @return array{items: array<int, array<string, mixed>>, total: int}
	 */
	public function paginate( array $filters = array(), int $limit = 50, int $offset = 0 ): array {
		return array(
			'items' => $this->query( $filters, $limit, $offset ),
			'total' => $this->count( $filters ),
		);
	}

	/**
	 * Field names the schema declares.
	 *
	 * @return array<int, string>
	 */
	protected function field_names(): array {
		return array_keys( $this->schema['fields'] ?? array() );
	}

	/**
	 * Whether a filter key may be used against this schema.
	 *
	 * `user_id` is always allowed because user scoping injects it.
	 *
	 * @param string $name Filter name.
	 *
	 * @return bool
	 */
	protected function is_filterable( string $name ): bool {
		return 'user_id' === $name || in_array( $name, $this->field_names(), true );
	}
}
