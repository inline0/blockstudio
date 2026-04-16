<?php
/**
 * Database field definition.
 *
 * @package Blockstudio
 */

namespace Blockstudio\Db;

use Blockstudio\Definition;

// phpcs:disable WordPress.NamingConventions.ValidVariableName
/**
 * PHP-native database field definition.
 */
final readonly class Field implements Definition {

	/**
	 * Constructor.
	 *
	 * @param string                 $type      The field type.
	 * @param bool                   $required  Whether the field is required.
	 * @param mixed                  $default   The default value.
	 * @param array<int, mixed>|null $enum      Allowed enum values.
	 * @param string|null            $format    Optional string format.
	 * @param int|null               $minLength Minimum string length.
	 * @param int|null               $maxLength Maximum string length.
	 * @param callable|null          $validate  Custom validator.
	 * @param array<string, mixed>   $extra     Additional field options.
	 */
	public function __construct(
		private string $type,
		private bool $required = false,
		private mixed $default = null,
		private ?array $enum = null,
		private ?string $format = null,
		private ?int $minLength = null,
		private ?int $maxLength = null,
		private mixed $validate = null,
		private array $extra = array(),
	) {
	}

	/**
	 * Create a generic field definition.
	 *
	 * @param string                 $type      The field type.
	 * @param bool                   $required  Whether the field is required.
	 * @param mixed                  $default   The default value.
	 * @param array<int, mixed>|null $enum      Allowed enum values.
	 * @param string|null            $format    Optional string format.
	 * @param int|null               $minLength Minimum string length.
	 * @param int|null               $maxLength Maximum string length.
	 * @param callable|null          $validate  Custom validator.
	 * @param array<string, mixed>   $extra     Additional field options.
	 *
	 * @return self
	 */
	public static function make(
		string $type,
		bool $required = false,
		mixed $default = null,
		?array $enum = null,
		?string $format = null,
		?int $minLength = null,
		?int $maxLength = null,
		$validate = null,
		array $extra = array(),
	): self {
		return new self( $type, $required, $default, $enum, $format, $minLength, $maxLength, $validate, $extra );
	}

	/**
	 * Create a string field definition.
	 *
	 * @param bool                   $required  Whether the field is required.
	 * @param mixed                  $default   The default value.
	 * @param array<int, mixed>|null $enum      Allowed enum values.
	 * @param string|null            $format    Optional string format.
	 * @param int|null               $minLength Minimum string length.
	 * @param int|null               $maxLength Maximum string length.
	 * @param callable|null          $validate  Custom validator.
	 * @param array<string, mixed>   $extra     Additional field options.
	 *
	 * @return self
	 */
	public static function string(
		bool $required = false,
		mixed $default = null,
		?array $enum = null,
		?string $format = null,
		?int $minLength = null,
		?int $maxLength = null,
		$validate = null,
		array $extra = array(),
	): self {
		return new self( 'string', $required, $default, $enum, $format, $minLength, $maxLength, $validate, $extra );
	}

	/**
	 * Create an integer field definition.
	 *
	 * @param bool                 $required Whether the field is required.
	 * @param mixed                $default  The default value.
	 * @param callable|null        $validate Custom validator.
	 * @param array<string, mixed> $extra    Additional field options.
	 *
	 * @return self
	 */
	public static function integer(
		bool $required = false,
		mixed $default = null,
		$validate = null,
		array $extra = array(),
	): self {
		return new self( 'integer', $required, $default, null, null, null, null, $validate, $extra );
	}

	/**
	 * Create a number field definition.
	 *
	 * @param bool                 $required Whether the field is required.
	 * @param mixed                $default  The default value.
	 * @param callable|null        $validate Custom validator.
	 * @param array<string, mixed> $extra    Additional field options.
	 *
	 * @return self
	 */
	public static function number(
		bool $required = false,
		mixed $default = null,
		$validate = null,
		array $extra = array(),
	): self {
		return new self( 'number', $required, $default, null, null, null, null, $validate, $extra );
	}

	/**
	 * Create a boolean field definition.
	 *
	 * @param bool                 $required Whether the field is required.
	 * @param mixed                $default  The default value.
	 * @param callable|null        $validate Custom validator.
	 * @param array<string, mixed> $extra    Additional field options.
	 *
	 * @return self
	 */
	public static function boolean(
		bool $required = false,
		mixed $default = null,
		$validate = null,
		array $extra = array(),
	): self {
		return new self( 'boolean', $required, $default, null, null, null, null, $validate, $extra );
	}

	/**
	 * Create a text field definition.
	 *
	 * @param bool                 $required Whether the field is required.
	 * @param mixed                $default  The default value.
	 * @param callable|null        $validate Custom validator.
	 * @param array<string, mixed> $extra    Additional field options.
	 *
	 * @return self
	 */
	public static function text(
		bool $required = false,
		mixed $default = null,
		$validate = null,
		array $extra = array(),
	): self {
		return new self( 'text', $required, $default, null, null, null, null, $validate, $extra );
	}

	/**
	 * Convert the field definition into the legacy array format.
	 *
	 * @return array<string, mixed>
	 */
	public function to_array(): array {
		$definition = array_merge(
			array( 'type' => $this->type ),
			$this->extra
		);

		if ( $this->required ) {
			$definition['required'] = true;
		}

		if ( null !== $this->default ) {
			$definition['default'] = $this->default;
		}

		if ( null !== $this->enum ) {
			$definition['enum'] = $this->enum;
		}

		if ( null !== $this->format ) {
			$definition['format'] = $this->format;
		}

		if ( null !== $this->minLength ) {
			$definition['minLength'] = $this->minLength;
		}

		if ( null !== $this->maxLength ) {
			$definition['maxLength'] = $this->maxLength;
		}

		if ( null !== $this->validate ) {
			$definition['validate'] = $this->validate;
		}

		return $definition;
	}
}
// phpcs:enable WordPress.NamingConventions.ValidVariableName
