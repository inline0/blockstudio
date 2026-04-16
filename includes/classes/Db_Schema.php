<?php
/**
 * Database schema definition.
 *
 * @package Blockstudio
 */

namespace Blockstudio;

// phpcs:disable WordPress.NamingConventions.ValidVariableName
/**
 * PHP-native database schema definition.
 */
final readonly class Db_Schema implements Definition_Interface {

	/**
	 * Constructor.
	 *
	 * @param array<string, array<string, mixed>|Definition_Interface> $fields     Schema fields.
	 * @param string|Db_Storage                                        $storage    Storage backend.
	 * @param array<string, mixed>                                     $capability Capability map.
	 * @param bool|array<string, mixed>                                $realtime   Realtime config.
	 * @param bool                                                     $userScoped Whether the schema is user-scoped.
	 * @param int|null                                                 $postId     Post ID for meta storage.
	 * @param array<string, callable>                                  $hooks      Inline hooks.
	 * @param array<string, mixed>                                     $extra      Additional schema options.
	 */
	public function __construct(
		private array $fields,
		private string|Db_Storage $storage = 'table',
		private array $capability = array(),
		private bool|array $realtime = false,
		private bool $userScoped = false,
		private ?int $postId = null,
		private array $hooks = array(),
		private array $extra = array(),
	) {
	}

	/**
	 * Create a schema definition.
	 *
	 * @param array<string, array<string, mixed>|Definition_Interface> $fields     Schema fields.
	 * @param string|Db_Storage                                        $storage    Storage backend.
	 * @param array<string, mixed>                                     $capability Capability map.
	 * @param bool|array<string, mixed>                                $realtime   Realtime config.
	 * @param bool                                                     $userScoped Whether the schema is user-scoped.
	 * @param int|null                                                 $postId     Post ID for meta storage.
	 * @param array<string, callable>                                  $hooks      Inline hooks.
	 * @param array<string, mixed>                                     $extra      Additional schema options.
	 *
	 * @return self
	 */
	public static function make(
		array $fields,
		string|Db_Storage $storage = 'table',
		array $capability = array(),
		bool|array $realtime = false,
		bool $userScoped = false,
		?int $postId = null,
		array $hooks = array(),
		array $extra = array(),
	): self {
		return new self( $fields, $storage, $capability, $realtime, $userScoped, $postId, $hooks, $extra );
	}

	/**
	 * Convert the schema definition into the legacy array format.
	 *
	 * @return array<string, mixed>
	 */
	public function to_array(): array {
		$fields = array();

		foreach ( $this->fields as $name => $field ) {
			if ( $field instanceof Definition_Interface ) {
				$fields[ $name ] = $field->to_array();
			} elseif ( is_array( $field ) ) {
				$fields[ $name ] = $field;
			}
		}

		$definition = array_merge(
			array(
				'storage' => $this->storage instanceof Db_Storage ? $this->storage->value : $this->storage,
				'fields'  => $fields,
			),
			$this->extra
		);

		if ( ! empty( $this->capability ) ) {
			$definition['capability'] = $this->capability;
		}

		if ( false !== $this->realtime ) {
			$definition['realtime'] = $this->realtime;
		}

		if ( $this->userScoped ) {
			$definition['userScoped'] = true;
		}

		if ( null !== $this->postId ) {
			$definition['postId'] = $this->postId;
		}

		if ( ! empty( $this->hooks ) ) {
			$definition['hooks'] = $this->hooks;
		}

		return $definition;
	}
}
// phpcs:enable WordPress.NamingConventions.ValidVariableName
