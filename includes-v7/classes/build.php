<?php
/**
 * Build class.
 *
 * @package Blockstudio
 */

namespace Blockstudio;

use BlockstudioVendor\ScssPhp\ScssPhp\Exception\SassException;
use Exception;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use WP_Block_Type;

/**
 * Handles building and registering blocks.
 *
 * @since 1.0.0
 */
class Build {

	/**
	 * Whether interactivity API has been rendered.
	 *
	 * @var bool
	 */
	private static bool $interactivity_api_rendered = false;

	/**
	 * Admin assets.
	 *
	 * @var array
	 */
	private static array $assets_admin = array();

	/**
	 * Block editor assets.
	 *
	 * @var array
	 */
	private static array $assets_block_editor = array();

	/**
	 * Global assets.
	 *
	 * @var array
	 */
	private static array $assets_global = array();

	/**
	 * Registered assets.
	 *
	 * @var array
	 */
	private static array $assets_register = array();

	/**
	 * Blade templates.
	 *
	 * @var array
	 */
	private static array $blade = array();

	/**
	 * Blocks.
	 *
	 * @var array
	 */
	private static array $blocks = array();

	/**
	 * Block overrides.
	 *
	 * @var array
	 */
	private static array $blocks_overrides = array();

	/**
	 * Block data.
	 *
	 * @var array
	 */
	private static array $data = array();

	/**
	 * Data overrides.
	 *
	 * @var array
	 */
	private static array $data_overrides = array();

	/**
	 * Extensions.
	 *
	 * @var array
	 */
	private static array $extensions = array();

	/**
	 * Files.
	 *
	 * @var array
	 */
	private static array $files = array();

	/**
	 * Instances.
	 *
	 * @var array
	 */
	private static array $instances = array();

	/**
	 * Overrides.
	 *
	 * @var array
	 */
	private static array $overrides = array();

	/**
	 * Paths.
	 *
	 * @var array
	 */
	private static array $paths = array();

	/**
	 * Whether Tailwind is active.
	 *
	 * @var bool
	 */
	private static bool $is_tailwind_active = false;

	/**
	 * Filter deep array everything but a given string.
	 *
	 * @since 3.1.1
	 *
	 * @param array  $array The array to filter (passed by reference).
	 * @param string $key   The key to filter by.
	 * @param mixed  $val   The value to filter by.
	 *
	 * @return void
	 */
	public static function filter_not_key( &$array, $key, $val ) {
		foreach ( $array as $k => $v ) {
			if ( isset( $v[ $key ] ) && $v[ $key ] === $val ) {
				unset( $array[ $k ] );
			} elseif ( is_array( $v ) ) {
				self::filter_not_key( $array[ $k ], $key, $val );
			}
			if ( empty( $array[ $k ] ) ) {
				unset( $array[ $k ] );
			}
		}
		foreach ( $array as $k => $v ) {
			if ( 'attributes' === $k ) {
				$array[ $k ] = array_values( $v );
			} elseif ( is_array( $v ) ) {
				self::filter_not_key( $array[ $k ], $key, $val );
			}
		}
	}

	/**
	 * Build attributes.
	 *
	 * @since 2.4.0
	 *
	 * @param array  $attrs         The attributes to build.
	 * @param array  $attributes    The attributes array (passed by reference).
	 * @param string $id            The ID prefix.
	 * @param bool   $from_group    Whether from a group.
	 * @param bool   $from_repeater Whether from a repeater.
	 * @param bool   $is_override   Whether an override.
	 * @param bool   $is_extend     Whether an extension.
	 *
	 * @return void
	 */
	public static function build_attributes(
		$attrs,
		&$attributes,
		string $id = '',
		bool $from_group = false,
		bool $from_repeater = false,
		bool $is_override = false,
		bool $is_extend = false
	) {
		$index = 0;
		foreach ( $attrs as $data ) {
			$data = array( 'attributes' => $data );

			foreach ( $data as $v ) {
				$i        = '' === $id ? '' : $id . '_';
				$field_id = $from_repeater ? $index : $i . ( $v['id'] ?? '' );
				++$index;

				if (
					isset( $v['type'] ) &&
					'message' !== $v['type'] &&
					( ( ! isset( $v['id'] ) &&
						( 'group' === $v['type'] || 'tabs' === $v['type'] ) ) ||
						isset( $v['id'] ) )
				) {
					$type = $v['type'];

					$is_multiple_options =
						'checkbox' === $type ||
						'token' === $type ||
						( 'select' === $type && ( $v['multiple'] ?? false ) );

					if ( 'tabs' === $type && ! $from_group && ! $from_repeater ) {
						foreach ( $v['tabs'] as $tab ) {
							self::build_attributes(
								array_values( $tab['attributes'] ),
								$attributes,
								'',
								false,
								false,
								$is_override
							);
						}
					}

					if (
						( 'group' === $type && ! $from_group ) ||
						'repeater' === $type
					) {
						if (
							isset( $v['attributes'] ) &&
							count( $v['attributes'] ) >= 1
						) {
							self::filter_not_key(
								$v['attributes'],
								'type',
								'group'
							);
						}

						if ( 'group' === $type ) {
							self::build_attributes(
								array_values( $v['attributes'] ),
								$attributes,
								$i . ( $v['id'] ?? '' ),
								true,
								false,
								$is_override,
								$is_extend
							);
						}

						if ( 'repeater' === $type ) {
							$attributes[ $field_id ] = array(
								'blockstudio' => true,
								'type'        => 'array',
								'field'       => $type,
								'attributes'  =>
									count( $v['attributes'] ?? array() ) >= 1
										? array_values(
											array_filter(
												$v['attributes'],
												fn( $val ) => 'group' !== $val['type']
											)
										)
										: array(),
							);

							if (
								count(
									$attributes[ $field_id ]['attributes'] ?? array()
								) >= 1
							) {
								self::build_attributes(
									$attributes[ $field_id ]['attributes'],
									$attributes[ $field_id ]['attributes'],
									'',
									false,
									true,
									$is_override,
									$is_extend
								);
							}
						}
					}

					if ( 'attributes' === $type ) {
						$attributes[ $field_id ] = array(
							'blockstudio' => true,
							'type'        => 'array',
							'field'       => $type,
						);
					}

					if (
						'code' === $type ||
						'date' === $type ||
						'datetime' === $type ||
						'text' === $type ||
						'textarea' === $type ||
						'unit' === $type ||
						'classes' === $type
					) {
						$attributes[ $field_id ] = array(
							'blockstudio' => true,
							'type'        => 'string',
							'field'       => $type,
						);

						if ( 'classes' === $type && ( $v['tailwind'] ?? false ) ) {
							self::$is_tailwind_active = true;
						}
					}

					if ( 'code' === $type ) {
						$attributes[ $field_id ]['language'] =
							$v['language'] ?? 'html';
						$attributes[ $field_id ]['asset']    = $v['asset'] ?? false;
					}

					if ( 'number' === $type || 'range' === $type ) {
						$attributes[ $field_id ] = array(
							'blockstudio' => true,
							'type'        => 'number',
							'field'       => $type,
						);
					}

					if ( 'toggle' === $type ) {
						$attributes[ $field_id ] = array(
							'blockstudio' => true,
							'type'        => 'boolean',
							'field'       => $type,
						);
					}

					if ( $is_multiple_options ) {
						$attributes[ $field_id ] = array(
							'blockstudio' => true,
							'type'        => 'array',
							'field'       => $type,
						);

						if ( 'select' === $type ) {
							$attributes[ $field_id ]['multiple'] = true;
						}
					}

					if (
						'color' === $type ||
						'gradient' === $type ||
						'icon' === $type ||
						'link' === $type ||
						'radio' === $type ||
						( 'select' === $type &&
							( ! isset( $v['multiple'] ) ||
								false === $v['multiple'] ) )
					) {
						$attributes[ $field_id ] = array(
							'blockstudio' => true,
							'type'        => 'object',
							'field'       => $type,
						);
					}

					if ( 'files' === $type ) {
						$attributes[ $field_id ] = array(
							'blockstudio' => true,
							'type'        => array( 'number', 'object', 'array' ),
							'field'       => $type,
							'multiple'    => $v['multiple'] ?? false,
							'returnSize'  => $v['returnSize'] ?? 'full',
						);
					}

					if (
						'select' === $type ||
						'radio' === $type ||
						'checkbox' === $type ||
						'color' === $type ||
						'gradient' === $type
					) {
						if (
							( $is_override && isset( $v['options'] ) ) ||
							! $is_override
						) {
							$options                            = $v['options'] ?? array();
							$attributes[ $field_id ]['options'] = $options;
						}
					}

					if (
						'select' === $type ||
						'radio' === $type ||
						'checkbox' === $type ||
						'color' === $type ||
						'gradient' === $type
					) {
						if (
							( $is_override && isset( $v['populate'] ) ) ||
							! $is_override
						) {
							$options =
								'select' === $type &&
								( $v['populate']['fetch'] ?? false )
									? array()
									: $v['options'] ?? array();
							$attributes[ $field_id ]['options'] = $options;
							$populate_type                      = $v['populate']['type'] ?? false;

							if (
								'query' === $populate_type ||
								'custom' === $populate_type ||
								'function' === $populate_type
							) {
								$options_addons        = Populate::init(
									$v['populate'],
									$v['default'] ?? false
								);
								$options_transformed   = array();
								$options_populate      = array();
								$options_populate_full = array();

								if ( 'query' === $v['populate']['type'] ) {
									$q                = $v['populate']['query'];
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

									foreach ( $options_addons as $opt ) {
										$val = $opt->{$return_map_value[ $q ]};

										$options_populate[]      = $val;
										$options_transformed[]   = array(
											'value' => $val,
											'label' =>
												$opt->{$v['populate'][
													'returnFormat'
												]['label'] ??
													$return_map_label[ $q ]},
										);
										$options_populate_full[ $val ] = $opt;
									}
								}

								if ( 'function' === $v['populate']['type'] ) {
									$val =
										$v['populate']['returnFormat'][
											'value'
										] ?? false;
									$label =
										$v['populate']['returnFormat'][
											'label'
										] ?? false;

									if ( ! $val && ! $label ) {
										$options_addons = array_values(
											$options_addons
										);
									}

									foreach ( $options_addons as $opt ) {
										$opt = (array) $opt;

										$val =
											$opt[ $val ] ??
											( $opt['value'] ??
												( array_values( $opt )[0] ??
													$opt ) );
										$options_populate[]    = $val;
										$options_transformed[] = array(
											'value' => $val,
											'label' =>
												$opt[ $label ] ??
												( $opt['label'] ?? $val ),
										);
									}
								}

								if ( count( $options_populate ) >= 1 ) {
									$attributes[ $field_id ][
										'optionsPopulate'
									] = $options_populate;
									$attributes[ $field_id ][
										'optionsPopulateFull'
									] = $options_populate_full;
								}

								$is_transform =
									'query' === $v['populate']['type'] ||
									'function' === $v['populate']['type'];

								$attributes[ $field_id ]['options'] =
									isset( $v['populate']['position'] ) &&
									'before' === $v['populate']['position']
										? array_merge(
											$is_transform
												? $options_transformed
												: $options_addons,
											$options
										)
										: array_merge(
											$options,
											$is_transform
												? $options_transformed
												: $options_addons
										);
							}
						}
					}

					if ( 'richtext' === $type || 'wysiwyg' === $type ) {
						$attributes[ $field_id ] = array(
							'blockstudio' => true,
							'type'        => 'string',
							'field'       => $type,
							'source'      => 'html',
						);
					}

					foreach ( array( 'default', 'fallback' ) as $item ) {
						if ( isset( $v[ $item ] ) ) {
							if (
								'code' === $type ||
								'date' === $type ||
								'datetime' === $type ||
								'files' === $type ||
								'icon' === $type ||
								'link' === $type ||
								'richtext' === $type ||
								'text' === $type ||
								'textarea' === $type ||
								'toggle' === $type ||
								'unit' === $type ||
								'wysiwyg' === $type ||
								'classes' === $type
							) {
								$attributes[ $field_id ][ $item ] = $v[ $item ];
							}
							if ( 'number' === $type || 'range' === $type ) {
								$attributes[ $field_id ][ $item ] =
									0 === $v[ $item ] ? '0' : $v[ $item ];
							}
							if ( 'color' === $type || 'gradient' === $type ) {
								foreach ( $v['options'] ?? array() as $value ) {
									if ( $value['value'] === $v[ $item ] ) {
										$attributes[ $field_id ][ $item ] = $value;
									}
								}
							}
							if (
								'checkbox' === $type ||
								'radio' === $type ||
								'select' === $type ||
								'token' === $type
							) {
								$default_select = array();

								foreach (
									is_array( $v[ $item ] )
										? $v[ $item ]
										: array( $v[ $item ] )
									as $value
								) {
									$option = fn( $val ) => Block::get_option_value(
										array(
											'options' =>
												$attributes[ $field_id ][
													'options'
												] ?? $v['options'],
										),
										$val,
										array(
											'value' => $value,
										)
									);

									$default_select[] = array(
										'value' => $option( 'value' ),
										'label' => $option( 'label' ),
									);
								}

								$attributes[ $field_id ][
									$item
								] = $is_multiple_options
									? $default_select
									: $default_select[0];
							}
						}
					}

					if ( isset( $v['returnFormat'] ) ) {
						$attributes[ $field_id ]['returnFormat'] =
							$v['returnFormat'] ?? 'value';
					}

					if ( isset( $v['populate'] ) ) {
						$attributes[ $field_id ]['populate'] = $v['populate'];
					}

					if ( 'tabs' !== $type && 'group' !== $type ) {
						$attributes[ $field_id ]['id'] = $i . ( $v['id'] ?? '' );
					}

					if ( $v['set'] ?? false ) {
						$attributes[ $field_id ]['set'] = $v['set'];
					}
				}
			}
		}
	}

	/**
	 * Filter attributes.
	 *
	 * @since 4.0.3
	 *
	 * @param array $block      The block data.
	 * @param array $attrs      The attributes to filter.
	 * @param array $attributes The filtered attributes (passed by reference).
	 *
	 * @return void
	 */
	public static function filter_attributes( $block, $attrs, &$attributes ) {
		foreach ( $attrs as $k => $v ) {
			$attributes[ $k ] = apply_filters(
				'blockstudio/blocks/attributes',
				$v,
				$block
			);

			$type = $attributes[ $k ]['type'] ?? false;

			if ( 'group' === $type || 'repeater' === $type ) {
				self::filter_attributes(
					$block,
					$attributes[ $k ]['attributes'],
					$attributes[ $k ]['attributes']
				);
			}
		}
	}

	/**
	 * Build attributes IDs.
	 *
	 * @since 3.1.0
	 *
	 * @param array $attributes The attributes (passed by reference).
	 *
	 * @return void
	 */
	public static function build_attribute_ids( &$attributes ) {
		foreach ( $attributes as &$b ) {
			if ( isset( $b['type'] ) && isset( $b['id'] ) ) {
				if ( 'group' === $b['type'] ) {
					foreach ( $b['attributes'] as &$d ) {
						$id        = $d['id'];
						$d['id']   = $b['id'] . '_' . $id;

						if ( isset( $d['attributes'] ) ) {
							self::build_attribute_ids( $d['attributes'] );
						}
					}
				}
			}
		}
	}

	/**
	 * Merge attributes.
	 *
	 * @since 5.3.0
	 *
	 * @param array $original_attributes The original attributes (passed by reference).
	 * @param array $override_attributes The override attributes.
	 *
	 * @return void
	 */
	public static function merge_attributes(
		&$original_attributes,
		$override_attributes
	) {
		$merge_attribute_by_key_or_id = function (
			$key_or_id,
			&$attributes,
			$override
		) use ( &$merge_attribute_by_key_or_id ) {
			foreach ( $attributes as &$attribute ) {
				if (
					( isset( $attribute['key'] ) &&
						$attribute['key'] === $key_or_id ) ||
					( isset( $attribute['id'] ) && $attribute['id'] === $key_or_id )
				) {
					foreach ( $override as $key => $value ) {
						if ( 'attributes' !== $key && 'tabs' !== $key ) {
							$attribute[ $key ] = $value;
						} elseif ( isset( $attribute[ $key ] ) && is_array( $value ) ) {
							self::merge_attributes( $attribute[ $key ], $value );
						}
					}

					return true;
				}

				foreach ( array( 'attributes', 'tabs' ) as $nested_key ) {
					if (
						isset( $attribute[ $nested_key ] ) &&
						is_array( $attribute[ $nested_key ] )
					) {
						if (
							$merge_attribute_by_key_or_id(
								$key_or_id,
								$attribute[ $nested_key ],
								$override
							)
						) {
							return true;
						}
					}
				}
			}

			return false;
		};

		foreach ( $override_attributes as $override_attribute ) {
			$key_or_id =
				$override_attribute['key'] ?? ( $override_attribute['id'] ?? null );
			if ( null !== $key_or_id ) {
				if (
					! $merge_attribute_by_key_or_id(
						$key_or_id,
						$original_attributes,
						$override_attribute
					)
				) {
					$original_attributes[] = $override_attribute;
				}
			} else {
				$original_attributes[] = $override_attribute;
			}
		}
	}

	/**
	 * Get WordPress root folder name.
	 *
	 * @since 5.0.0
	 *
	 * @param string $path The path.
	 *
	 * @return string The instance name.
	 */
	public static function get_instance_name( $path ): string {
		return wp_normalize_path(
			trim( explode( Files::get_root_folder(), $path )[1], '/\\' )
		);
	}

	/**
	 * Get WordPress root folder name.
	 *
	 * @since 2.3.3
	 *
	 * @param string $path   The path.
	 * @param string $filter The filter.
	 *
	 * @return string The build directory.
	 */
	public static function get_build_dir(
		string $path = '/blockstudio',
		string $filter = 'path'
	): string {
		$theme = is_child_theme()
			? get_stylesheet_directory()
			: get_template_directory();

		return has_filter( 'blockstudio/' . $filter )
			? apply_filters( 'blockstudio/' . $filter, '' )
			: $theme . $path;
	}

	/**
	 * Build.
	 *
	 * @since 1.0.0
	 *
	 * @param bool|string|array $args The arguments.
	 *
	 * @return void
	 * @throws SassException When SCSS compilation fails.
	 */
	public static function init( $args = false ) {
		$editor  = $args['editor'] ?? false;
		$library = $args['library'] ?? false;
		if ( is_array( $args ) ) {
			$p    = $args;
			$args = $p['dir'] ?? false;
		}
		$arr               = array();
		$path              = false === $args ? self::get_build_dir() : $args;
		$store             = array();
		$instance          = '';
		$empty_dist_folders = array();

		if ( is_dir( $path ) ) {
			self::$instances[] = array(
				'path'    => $path,
				'library' => $library,
			);

			$path     = wp_normalize_path( $path );
			$instance = self::get_instance_name( $path );

			if ( ! $library && ! Settings::get( 'editor/library' ) ) {
				self::$paths[] = array(
					'instance' => $instance,
					'path'     => $path,
				);
			}

			do_action( 'blockstudio/init/before' );
			do_action( "blockstudio/init/before/$instance" );

			$files = new RecursiveDirectoryIterator( $path );

			self::$blade[ $instance ]['path'] = $path;

			$check_key = function ( $contents, $flag ) {
				try {
					$data = json_decode( $contents, true );

					return isset( $data['blockstudio'][ $flag ] ) &&
						$data['blockstudio'][ $flag ];
				} catch ( Exception $e ) {
					return false;
				}
			};

			foreach (
				new RecursiveIteratorIterator( $files )
				as $filename => $file
			) {
				$filename  = wp_normalize_path( $filename );
				$file      = wp_normalize_path( $file );
				$file_dir  = dirname( $file );
				$path_info = pathinfo( $file );
				$block_json = array();
				$contents  = is_file( $file ) ? file_get_contents( $file ) : '{}'; // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents

				$is_blockstudio =
					'block.json' === pathinfo( $file )['basename'] &&
					isset( json_decode( $contents, true )['blockstudio'] );
				$is_extend      = $check_key( $contents, 'extend' );
				$is_block       =
					$is_blockstudio &&
					Files::get_render_template( $file ) &&
					! $is_extend;
				$is_override    =
					$is_blockstudio && $check_key( $contents, 'override' );
				$is_init        =
					'php' === ( $path_info['extension'] ?? '' ) &&
					str_starts_with( $path_info['basename'], 'init' );
				$is_dir         =
					is_dir( $file ) &&
					! file_exists( $file . '/block.json' ) &&
					! Files::get_render_template( $file );
				$is_php         = ! str_ends_with( $file, '.twig' );
				$is_blade       = str_ends_with( $file, '.blade.php' );

				if (
					str_starts_with( $path_info['basename'], '.' ) &&
					( ! $editor || '.' !== $path_info['basename'] )
				) {
					continue;
				}

				if (
					$is_block ||
					$is_blade ||
					$is_init ||
					$is_dir ||
					$is_override ||
					$is_extend ||
					$editor
				) {
					if ( ! is_dir( $file ) ) {
						$block_json = json_decode(
							file_get_contents( // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
								str_ends_with( $file, 'block.json' )
									? $file
									: str_replace(
										array(
											'index.blade.php',
											'index.php',
											'index.twig',
										),
										'block.json',
										$file
									)
							),
							true
						);
					}
					$block_json_path = str_ends_with( $file, 'block.json' )
						? $file
						: str_replace(
							'block.json',
							$is_blade
								? 'index.blade.php'
								: ( $is_php
									? 'index.php'
									: 'index.twig' ),
							$file
						);

					$name = $editor
						? $file
						: ( $is_override
							? $block_json['name'] . '-override'
							: $block_json['name'] ?? $file );

					if ( $is_blade ) {
						$relative_path = str_replace(
							array( $path, '.blade.php' ),
							'',
							$filename
						);
						$relative_path = str_replace(
							DIRECTORY_SEPARATOR,
							'.',
							$relative_path
						);

						self::$blade[ $instance ]['templates'][ $name ] = ltrim(
							$relative_path,
							'.'
						);
						self::$blade[ $instance ]['path'] = $path;

						continue;
					}

					if ( is_array( $name ) ) {
						$name = wp_json_encode( $name );
					}
					$name_file = false;

					if ( $editor && file_exists( $file_dir . '/block.json' ) ) {
						$name_file =
							json_decode(
								file_get_contents( $file_dir . '/block.json' ), // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
								true
							)['name'] ?? false;
						if ( ! $name_file ) {
							$block_json = array(
								'name' => 'test/test',
							);
						}
					}

					$level_explode = explode( Files::get_root_folder(), $path );
					$level         = explode(
						$level_explode[ count( $level_explode ) - 1 ],
						$filename
					)[1];

					$block_arr_files = array_values(
						array_filter(
							scandir( $file_dir ),
							fn( $item ) => ! is_dir( $file_dir . '/' . $item ) &&
								'.' !== $item[0]
						)
					);
					$block_arr_files_paths = array_map(
						fn( $item ) => $file_dir . '/' . $item,
						$block_arr_files
					);
					$block_arr_folders = array_values(
						array_map(
							'basename',
							array_filter( glob( dirname( $file ) . '/*' ), 'is_dir' )
						)
					);
					$block_arr_structure_array = explode(
						'/',
						str_replace(
							array(
								'/' . pathinfo( $file )['basename'],
								pathinfo( $file )['basename'],
							),
							'',
							$level
						)
					);
					$block_arr_structure_explode = explode(
						Files::get_root_folder() . '/',
						$path
					);
					$block_arr_structure = ltrim(
						explode(
							$block_arr_structure_explode[
								count( $block_arr_structure_explode ) - 1
							],
							$block_json_path
						)[1],
						'/'
					);

					if ( $is_init ) {
						$block_json = array(
							'name' => 'init-' . str_replace( '/', '-', $file ),
						);
					}

					$data = array(
						'directory'     => $is_dir,
						'example'       => $block_json['example'] ?? false,
						'extend'        => $is_extend,
						'file'          => pathinfo( $block_json_path ),
						'files'         => $block_arr_files,
						'filesPaths'    => $block_arr_files_paths,
						'folders'       => $block_arr_folders,
						'init'          => $is_init,
						'instance'      => $instance,
						'instancePath'  => $path,
						'level'         => substr_count( $level, '/' ),
						'library'       => $library,
						'name'          => false !== $name_file ? $name_file : $name,
						'path'          => $block_json_path,
						'previewAssets' =>
							$block_json['blockstudio']['editor']['assets'] ?? array(),
						'scopedClass'   => 'bs-' . md5( $name ),
						'structure'     => $block_arr_structure,
						'structureArray' => $block_arr_structure_array,
						'twig'          => ! $is_php,
					);
					if ( $data['name'] === $data['path'] ) {
						$data['nameAlt'] = Block::id( $data, $data );
					}

					if ( $editor && '.' !== $path_info['basename'] ) {
						$data['value'] = file_get_contents( $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
					}

					$store[ $name ] = $data;
					if ( $is_override && $editor ) {
						self::$data_overrides[
							false !== $name_file ? $name_file : $name
						] = $data;
					}

					if ( Settings::get( 'assets/enqueue' ) || $editor ) {
						$assets = array_filter(
							$block_arr_files,
							fn( $e ) => Assets::is_css( $e ) ||
								str_ends_with( $e, '.js' )
						);
						$processed_assets = array();

						foreach ( $assets as $asset ) {
							$is_css = Assets::is_css( $asset );

							$asset_fn = fn( $relative ) => $relative
								? Files::get_relative_url( $file_dir . '/' . $asset )
								: $file_dir . '/' . $asset;

							$asset_file = pathinfo( $asset_fn( false ) );
							$asset_path = $asset_fn( false );
							$asset_url  = $asset_fn( true );

							if (
								false === apply_filters(
									'blockstudio/assets/enable',
									true,
									array(
										'file' => $asset_file,
										'path' => $asset_path,
										'url'  => $asset_url,
										'type' => $is_css ? 'css' : 'js',
									)
								)
							) {
								continue;
							}

							if ( ! $editor ) {
								$processed_asset = Assets::process(
									$asset_path,
									$data['scopedClass']
								);

								if ( is_array( $processed_asset ) ) {
									$processed_assets = array_merge(
										$processed_assets,
										$processed_asset
									);
								} else {
									$processed_assets[] = $processed_asset;
								}
							}

							$id = strtolower(
								preg_replace( '/(?<!^)[A-Z]/', '-$0', $asset )
							);

							if (
								str_starts_with(
									$asset_file['basename'],
									'admin'
								) &&
								! $editor
							) {
								self::$assets_admin[
									sanitize_title( $asset_path )
								] = array(
									'path' => $asset_path,
									'key'  => filemtime( $asset_fn( false ) ),
								);
							}

							if (
								str_starts_with(
									$asset_file['basename'],
									'block-editor'
								) &&
								! $editor
							) {
								self::$assets_block_editor[
									sanitize_title( $asset_path )
								] = array(
									'path' => $asset_path,
									'key'  => filemtime( $asset_fn( false ) ),
								);
							}

							if (
								str_starts_with(
									$asset_file['basename'],
									'global'
								) &&
								! $editor
							) {
								self::$assets_global[
									sanitize_title( $asset_path )
								] = $asset_url;
							}

							$handle    = Assets::get_id(
								$id,
								$store[ $name ] ?? array( 'name' => $name )
							);
							$is_editor =
								str_ends_with( $asset, '-editor.css' ) ||
								str_ends_with( $asset, '-editor.scss' ) ||
								str_ends_with( $asset, '-editor.js' );

							$store[ $name ]['assets'][ $id ] = array(
								'type'     =>
									str_ends_with( $asset, '-inline.css' ) ||
									str_ends_with( $asset, '-inline.scss' ) ||
									str_ends_with( $asset, '-inline.js' ) ||
									str_ends_with( $asset, '-scoped.css' ) ||
									str_ends_with( $asset, '-scoped.scss' )
										? 'inline'
										: 'external',
								'path'     => $asset_path,
								'url'      => $asset_url,
								'editor'   => $is_editor,
								'instance' => $instance,
								'file'     => $asset_file,
							);

							if ( ! $editor ) {
								if ( $is_css ) {
									self::$assets_register['style'][ $handle ] = array(
										'path'  => $asset_fn( true ),
										'mtime' => filemtime( $asset_fn( false ) ),
									);
								} else {
									self::$assets_register['script'][ $handle ] = array(
										'path'  => $asset_fn( true ),
										'mtime' => filemtime( $asset_fn( false ) ),
									);
								}
							}
						}

						$dist_folder          = $file_dir . '/_dist';
						$all_processed_assets = Files::get_files_recursively_and_delete_empty_folders(
							$dist_folder
						);

						if ( ! $editor ) {
							foreach ( $all_processed_assets as $file_path ) {
								if (
									! in_array( $file_path, $processed_assets, true ) &&
									file_exists( $file_path )
								) {
									unlink( $file_path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink
								}
							}

							foreach ( $all_processed_assets as $file_path ) {
								$directory = dirname( $file_path );
								if (
									false !== glob( $directory . '/*' ) &&
									0 !== count( glob( $directory . '/*' ) )
								) {
									continue;
								}

								if ( is_dir( $directory ) ) {
									rmdir( $directory ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir
								}
							}

							if ( Files::is_directory_empty( $dist_folder ) ) {
								$empty_dist_folders[] = $dist_folder;
							}
						}
					}

					if ( ( $is_block || $is_override || $is_extend ) && ! $editor ) {
						$native_path =
							$is_override && ! $is_block
								? $file
								: Files::get_render_template( $file );

						$attributes          = array();
						$filtered_attributes = array();
						if ( isset( $block_json['blockstudio']['attributes'] ) ) {
							if ( ! $is_override ) {
								self::filter_attributes(
									$block_json,
									$block_json['blockstudio']['attributes'],
									$filtered_attributes
								);
							}

							self::build_attributes(
								$block_json['blockstudio']['attributes'],
								$attributes,
								'',
								false,
								false,
								false,
								$is_extend
							);
						}

						$attributes['blockstudio'] = array(
							'type'    => 'object',
							'default' => array(
								'name' => $block_json['name'],
							),
						);

						$attributes['anchor'] = $is_extend
							? array(
								'type'      => 'string',
								'source'    => 'attribute',
								'attribute' => 'id',
								'selector'  => '*',
							)
							: array(
								'type' => 'string',
							);

						$attributes['className'] = array(
							'type' => 'string',
						);

						$block              = new WP_Block_Type(
							$block_json['name'],
							$block_json
						);
						$block->api_version = 3;
						$block->render_callback = array(
							'Blockstudio\Block',
							'render',
						);
						$block->attributes  = array_merge(
							$block_json['attributes'] ?? array(),
							$attributes
						);
						$block->uses_context = array_merge(
							array( 'postId', 'postType' ),
							$block_json['usesContext'] ?? array()
						);
						$block->provides_context = array_merge(
							array(
								$name => 'blockstudio',
							),
							$block_json['providesContext'] ?? array()
						);
						$block->path = $native_path;

						if ( isset( $block_json['variations'] ) ) {
							$variations = array();
							foreach ( $block_json['variations'] as $variation ) {
								$variations[] =
									array(
										'attributes' => array(
											'blockstudio' => array(
												'attributes' =>
													$variation['attributes'],
											),
										),
									) + $variation;
							}

							$block->variations = $variations;
						}

						$disable_loading =
							$block_json['blockstudio']['blockEditor'][
								'disableLoading'
							] ??
							( Settings::get( 'blockEditor/disableLoading' ) ??
								false );

						$block->blockstudio = array(
							'attributes'  => $filtered_attributes,
							'blockEditor' => array(
								'disableLoading' => $disable_loading,
							),
							'conditions'  => isset(
								$block->blockstudio['conditions']
							)
								? $block->blockstudio['conditions']
								: true,
							'editor'      => isset( $block->blockstudio['editor'] )
								? $block->blockstudio['editor']
								: false,
							'extend'      => isset( $block->blockstudio['extend'] )
								? $block->blockstudio['extend']
								: false,
							'group'       => isset( $block->blockstudio['group'] )
								? $block->blockstudio['group']
								: false,
							'icon'        => isset( $block->blockstudio['icon'] )
								? $block->blockstudio['icon']
								: null,
							'refreshOn'   => isset(
								$block->blockstudio['refreshOn']
							)
								? $block->blockstudio['refreshOn']
								: false,
							'transforms'  => isset(
								$block->blockstudio['transforms']
							)
								? $block->blockstudio['transforms']
								: false,
							'variations'  => $block->variations ?? false,
						);

						if ( $is_override ) {
							self::$overrides[ $block_json['name'] ] = json_decode(
								$contents,
								true
							);
							self::$blocks_overrides[ $block_json['name'] ] = $block;
						} elseif ( ! $is_extend ) {
							self::$blocks[ $block_json['name'] ] = $block;
						} else {
							self::$extensions[] = $block;
						}
					}
				}
			}
		}

		if ( $editor ) {
			self::$files = array_merge( self::$files, $store );
			foreach ( self::$data_overrides as $override ) {
				foreach ( $override['filesPaths'] as $path ) {
					self::$files[ $path ]['assets'] = array_merge(
						self::$data[ $override['name'] ]['assets'] ?? array(),
						$override['assets'] ?? array()
					);
				}
			}

			return;
		}

		self::$data = array_merge( self::$data, $store );

		foreach ( self::$data as $file ) {
			if ( $file['init'] ) {
				include_once $file['path'];
			}
		}

		foreach ( self::$blocks as $block ) {
			if ( self::$overrides[ $block->name ] ?? false ) {
				foreach ( self::$overrides[ $block->name ] as $key => $value ) {
					if ( 'blockstudio' === $key ) {
						$override_attributes = $value['attributes'] ?? array();
						self::merge_attributes(
							$block->blockstudio['attributes'],
							$override_attributes
						);

						$override_built_attributes = array();
						self::build_attributes(
							$override_attributes,
							$override_built_attributes,
							'',
							false,
							false,
							true
						);
						self::$blocks_overrides[
							$block->name
						]->attributes = $override_built_attributes;
						self::merge_attributes(
							$block->attributes,
							$override_built_attributes
						);

						$mapped_attributes = array();
						foreach ( $block->attributes as $name => $attribute ) {
							if ( isset( $attribute['id'] ) ) {
								$mapped_attributes[
									$attribute['id']
								] = $attribute;
							} else {
								$mapped_attributes[ $name ] = $attribute;
							}
						}
						$block->attributes = $mapped_attributes;

						continue;
					}

					$block->{$key} = $value;
				}

				self::$data[ $block->name ]['assets'] = array_merge(
					self::$data[ $block->name ]['assets'] ?? array(),
					self::$data[ $block->name . '-override' ]['assets'] ?? array()
				);
			}
		}

		foreach ( $empty_dist_folders as $folder ) {
			if ( is_dir( $folder ) ) {
				rmdir( $folder ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir
			}
		}

		do_action( 'blockstudio/init' );
		do_action( "blockstudio/init/$instance" );
	}

	/**
	 * Convert a path to array.
	 *
	 * @since 2.3.0
	 *
	 * @param array  $array     The array (passed by reference).
	 * @param string $path      The path.
	 * @param mixed  $value     The value.
	 * @param string $delimiter The delimiter.
	 *
	 * @return mixed The backup value.
	 */
	public static function path_to_array(
		&$array,
		$path,
		$value,
		string $delimiter = '/'
	) {
		$path_parts = explode( $delimiter, $path );

		$current = &$array;
		foreach ( $path_parts as $key ) {
			$current = &$current[ $key ];
		}

		$backup  = $current;
		$current = $value;

		return $backup;
	}

	/**
	 * Recursive sort files.
	 *
	 * @since 5.0.0
	 *
	 * @param array $arr The array (passed by reference).
	 *
	 * @return void
	 */
	public static function recursive_sort( &$arr ) {
		foreach ( $arr as &$value ) {
			if ( is_array( $value ) && array_key_exists( '.', $value ) ) {
				self::recursive_sort( $value );
			}
		}

		uksort(
			$arr,
			function ( $a, $b ) use ( &$arr ) {
				$a_is_dir =
					isset( $arr[ $a ] ) &&
					is_array( $arr[ $a ] ) &&
					array_key_exists( '.', $arr[ $a ] );
				$b_is_dir =
					isset( $arr[ $b ] ) &&
					is_array( $arr[ $b ] ) &&
					array_key_exists( '.', $arr[ $b ] );

				if ( $a_is_dir && ! $b_is_dir ) {
					return -1;
				} elseif ( ! $a_is_dir && $b_is_dir ) {
					return 1;
				} else {
					return $a <=> $b;
				}
			}
		);
	}

	/**
	 * Get sorted blocks data for the editor.
	 *
	 * @since 2.3.0
	 *
	 * @return array The sorted data.
	 * @throws SassException When SCSS compilation fails.
	 */
	public static function data_sorted(): array {
		self::files();
		$sorted = array();

		foreach ( self::$files as $d ) {
			if ( $d['library'] && ! Settings::get( 'editor/library' ) ) {
				continue;
			}

			$sorted[ $d['instance'] ]['instance'] = $d['instance'];
			$sorted[ $d['instance'] ]['library']  = $d['library'];
			$sorted[ $d['instance'] ]['path']     = $d['instancePath'];

			self::path_to_array(
				$sorted[ $d['instance'] ]['children'],
				$d['structure'],
				$d
			);

			self::recursive_sort( $sorted[ $d['instance'] ]['children'] );
		}

		ksort( $sorted );

		return $sorted;
	}

	/**
	 * Get native blocks data.
	 *
	 * @since 3.0.0
	 *
	 * @return array The blocks.
	 */
	public static function blocks(): array {
		return self::$blocks;
	}

	/**
	 * Get blocks data.
	 *
	 * @since 2.3.0
	 *
	 * @return array The data.
	 */
	public static function data(): array {
		return self::$data;
	}

	/**
	 * Get extends data.
	 *
	 * @since 5.3.3
	 *
	 * @return array The extensions.
	 */
	public static function extensions(): array {
		return self::$extensions;
	}

	/**
	 * Get all block files.
	 *
	 * @since 2.3.0
	 *
	 * @return array The files.
	 * @throws SassException When SCSS compilation fails.
	 */
	public static function files(): array {
		foreach ( self::$instances as $instance ) {
			self::init(
				array(
					'dir'     => $instance['path'],
					'library' => $instance['library'],
					'editor'  => true,
				)
			);
		}

		return self::$files;
	}

	/**
	 * Get admin assets.
	 *
	 * @since 5.5.0
	 *
	 * @return array The admin assets.
	 */
	public static function assets_admin(): array {
		return self::$assets_admin;
	}

	/**
	 * Get block editor assets.
	 *
	 * @since 5.5.0
	 *
	 * @return array The block editor assets.
	 */
	public static function assets_block_editor(): array {
		return self::$assets_block_editor;
	}

	/**
	 * Get global assets.
	 *
	 * @since 5.0.0
	 *
	 * @return array The global assets.
	 */
	public static function assets_global(): array {
		return self::$assets_global;
	}

	/**
	 * Get instance paths.
	 *
	 * @since 2.5.0
	 *
	 * @return array The paths.
	 */
	public static function paths(): array {
		return self::$paths;
	}

	/**
	 * Get overrides.
	 *
	 * @since 5.3.0
	 *
	 * @return array The overrides.
	 */
	public static function overrides(): array {
		return self::$blocks_overrides;
	}

	/**
	 * Get assets data.
	 *
	 * @since 5.5.7
	 *
	 * @return array The assets.
	 */
	public static function assets(): array {
		return self::$assets_register;
	}

	/**
	 * Blade templates.
	 *
	 * @since 5.6.0
	 *
	 * @return array The blade templates.
	 */
	public static function blade(): array {
		return self::$blade;
	}

	/**
	 * Check if Tailwind is active.
	 *
	 * @since 5.6.0
	 *
	 * @return bool Whether Tailwind is active.
	 */
	public static function is_tailwind_active(): bool {
		return self::$is_tailwind_active;
	}
}
