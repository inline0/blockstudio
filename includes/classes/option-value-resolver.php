<?php
/**
 * Option Value Resolver class.
 *
 * @package Blockstudio
 */

namespace Blockstudio;

/**
 * Resolves option values from field configurations.
 *
 * This class extracts option value resolution logic from the Block class
 * to help break the circular dependency between Build and Block.
 *
 * @since 7.0.0
 */
class Option_Value_Resolver {

	/**
	 * Get option value from field data.
	 *
	 * @param array  $data          The field data containing options.
	 * @param string $return_format The return format ('value', 'label', 'both').
	 * @param mixed  $value         The current value.
	 * @param array  $populate      The populate settings.
	 *
	 * @return mixed The resolved option value.
	 */
	public static function get_option_value(
		array $data,
		string $return_format,
		mixed $value,
		array $populate = array()
	): mixed {
		$fetch       = $populate['fetch'] ?? false;
		$options_map = array();
		$options     = $fetch ? array( $value ) : ( $data['options'] ?? array() );

		foreach ( $options as $option ) {
			$option_value  = $option['value'] ?? false;
			$query_options = array( 'posts', 'users', 'terms' );

			if (
				isset( $populate['type'] ) &&
				'query' === $populate['type'] &&
				isset( $populate['query'] ) &&
				( ( in_array( $populate['query'], $query_options, true ) &&
					in_array( $option_value, $data['optionsPopulate'] ?? array(), true ) ) ||
					$fetch )
			) {
				$is_object = self::should_return_object( $populate );

				if ( $is_object ) {
					$option_value = self::get_query_object(
						$populate['query'],
						$option_value
					);
				}
			}

			if ( isset( $option['value'] ) ) {
				$options_map[ $option['value'] ] = array(
					'value' => $option_value,
					'label' => $option['label'] ?? $option_value,
				);
			} elseif ( ! $fetch ) {
				$options_map[ $option ] = array(
					'value' => $option,
					'label' => $option,
				);
			}
		}

		return self::extract_value( $options_map, $value, $return_format );
	}

	/**
	 * Determine if the option should return an object.
	 *
	 * @param array $populate The populate settings.
	 *
	 * @return bool Whether to return an object.
	 */
	private static function should_return_object( array $populate ): bool {
		return ( isset( $populate['returnFormat']['value'] ) &&
			'id' !== $populate['returnFormat']['value'] ) ||
			! isset( $populate['returnFormat']['value'] );
	}

	/**
	 * Get the query object for a value.
	 *
	 * @param string    $query_type The query type ('posts', 'users', 'terms').
	 * @param int|mixed $value      The value/ID.
	 *
	 * @return mixed The query object or original value.
	 */
	private static function get_query_object( string $query_type, mixed $value ): mixed {
		$query_function_map = array(
			'posts' => 'get_post',
			'users' => 'get_user_by',
			'terms' => 'get_term',
		);

		if ( 'users' === $query_type ) {
			return get_user_by( 'id', $value );
		}

		if ( isset( $query_function_map[ $query_type ] ) ) {
			return call_user_func( $query_function_map[ $query_type ], $value );
		}

		return $value;
	}

	/**
	 * Extract the final value based on return format.
	 *
	 * @param array  $options_map   The mapped options.
	 * @param mixed  $value         The current value.
	 * @param string $return_format The return format.
	 *
	 * @return mixed The extracted value.
	 */
	private static function extract_value(
		array $options_map,
		mixed $value,
		string $return_format
	): mixed {
		try {
			$key = $value['value'] ?? $value;

			if ( 'label' === $return_format ) {
				return $options_map[ $key ]['label'] ?? false;
			}

			if ( 'both' === $return_format ) {
				return $options_map[ $key ] ?? false;
			}

			return $options_map[ $key ]['value'] ?? false;
		} catch ( \Throwable $e ) {
			return false;
		}
	}

	/**
	 * Resolve nested value from data using a dot-notation path.
	 *
	 * @param array  $data The data array.
	 * @param string $path The path (e.g., 'parent.child.value').
	 *
	 * @return mixed The resolved value or null.
	 */
	public static function resolve_nested_value( array $data, string $path ): mixed {
		$keys = explode( '.', $path );
		$temp = $data;

		foreach ( $keys as $key ) {
			if ( ! is_array( $temp ) || ! isset( $temp[ $key ] ) ) {
				return null;
			}
			$temp = $temp[ $key ];
		}

		return $temp;
	}

	/**
	 * Build options map from populate data.
	 *
	 * @param array      $populate_data The populate configuration.
	 * @param mixed      $default       The default value.
	 * @param array|null $options       Existing options.
	 *
	 * @return array The populated options.
	 */
	public static function build_options_from_populate(
		array $populate_data,
		mixed $default = false,
		?array $options = null
	): array {
		$options        = $options ?? array();
		$populate_type  = $populate_data['type'] ?? false;
		$options_addons = Populate::init( $populate_data, $default );

		if ( 'query' === $populate_type || 'function' === $populate_type ) {
			$options_transformed = self::transform_query_options(
				$populate_data,
				$options_addons
			);

			$position = $populate_data['position'] ?? 'after';

			return 'before' === $position
				? array_merge( $options_transformed, $options )
				: array_merge( $options, $options_transformed );
		}

		if ( 'custom' === $populate_type ) {
			return array_merge( $options, $options_addons );
		}

		return $options;
	}

	/**
	 * Transform query options to standard format.
	 *
	 * @param array $populate_data  The populate configuration.
	 * @param array $options_addons The raw options.
	 *
	 * @return array The transformed options.
	 */
	private static function transform_query_options(
		array $populate_data,
		array $options_addons
	): array {
		$transformed = array();
		$query_type  = $populate_data['query'] ?? null;

		$return_map_value = array(
			'posts' => 'ID',
			'users' => 'ID',
			'terms' => 'term_id',
		);

		$return_map_label = array(
			'posts' => 'post_title',
			'users' => 'display_name',
			'terms' => 'name',
		);

		if ( 'query' === $populate_data['type'] && $query_type ) {
			foreach ( $options_addons as $opt ) {
				$value_key = $return_map_value[ $query_type ] ?? 'ID';
				$label_key = $populate_data['returnFormat']['label']
					?? ( $return_map_label[ $query_type ] ?? 'name' );

				$transformed[] = array(
					'value' => $opt->{$value_key} ?? null,
					'label' => $opt->{$label_key} ?? null,
				);
			}
		} elseif ( 'function' === $populate_data['type'] ) {
			$value_key = $populate_data['returnFormat']['value'] ?? false;
			$label_key = $populate_data['returnFormat']['label'] ?? false;

			foreach ( $options_addons as $opt ) {
				$opt = (array) $opt;

				$val = $opt[ $value_key ]
					?? ( $opt['value'] ?? ( array_values( $opt )[0] ?? $opt ) );

				$transformed[] = array(
					'value' => $val,
					'label' => $opt[ $label_key ] ?? ( $opt['label'] ?? $val ),
				);
			}
		}

		return $transformed;
	}
}
