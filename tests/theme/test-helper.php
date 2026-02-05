<?php
/**
 * Plugin Name: Blockstudio Test Helper
 * Description: Test helper for Blockstudio unit and E2E tests
 * Version: 1.0.0
 *
 * @package Blockstudio
 */

// phpcs:disable Squiz.Commenting.InlineComment.InvalidEndChar -- Test file, not production code.
// phpcs:disable WordPress.NamingConventions.ValidVariableName -- Matches WordPress block API structure.
// phpcs:disable WordPress.NamingConventions.ValidFunctionName.FunctionNameInvalid -- Matches existing API patterns.
// phpcs:disable Generic.CodeAnalysis.EmptyStatement.DetectedIf -- Test helper patterns.
// phpcs:disable WordPress.PHP.YodaConditions.NotYoda -- Test file, readability preferred.
// phpcs:disable Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- Callback signatures.

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Load Composer autoloader.
if ( file_exists( __DIR__ . '/vendor/autoload.php' ) ) {
	require_once __DIR__ . '/vendor/autoload.php';
}

// Initialize Timber.
if ( class_exists( 'Timber\Timber' ) ) {
	Timber\Timber::init();
}

// Blade template rendering.
add_filter(
	'blockstudio/blocks/render',
	function ( $value, $block ) {
		$path = $block->blockstudio['data']['path'] ?? '';

		if ( ! str_ends_with( $path, '.blade.php' ) ) {
			return $value;
		}

		if ( ! class_exists( '\Jenssegers\Blade\Blade' ) ) {
			return 'Error: Blade class not found.';
		}

		$data       = $block->blockstudio['data'];
		$blade_data = $data['blade'] ?? array();
		$blade_path = $blade_data['path'] ?? '';

		if ( empty( $blade_path ) || empty( $blade_data['templates'] ) ) {
			return $value;
		}

		$blade = new \Jenssegers\Blade\Blade( $blade_path, sys_get_temp_dir() );

		$template_name = $blade_data['templates'][ $block->name ] ?? '';
		if ( empty( $template_name ) ) {
			return $value;
		}

		return $blade->render(
			$template_name,
			array(
				'a'          => $data['attributes'],
				'attributes' => $data['attributes'],
				'b'          => $data['block'],
				'block'      => $data['block'],
				'c'          => $data['context'],
				'context'    => $data['context'],
			)
		);
	},
	10,
	2
);

// Add theme support for block editor.
add_action(
	'after_setup_theme',
	function () {
		add_theme_support( 'editor-styles' );
		add_theme_support( 'wp-block-styles' );
	}
);

// Enqueue test classes CSS for E2E tests.
add_action(
	'enqueue_block_editor_assets',
	function () {
		wp_enqueue_style(
			'test-classes',
			get_stylesheet_directory_uri() . '/test-classes.css',
			array(),
			'1.0.0'
		);
	}
);

// Also enqueue on admin for Blockstudio to parse.
add_action(
	'admin_enqueue_scripts',
	function () {
		wp_enqueue_style(
			'test-classes',
			get_stylesheet_directory_uri() . '/test-classes.css',
			array(),
			'1.0.0'
		);
	}
);

/**
 * Custom populate function for populate-function.ts test.
 * Returns options for the valueLabel checkbox field.
 */
function blockstudioCustomSelect(): array {
	return array(
		array(
			'value' => 'option-1',
			'label' => 'Option 1',
		),
		array(
			'value' => 'option-2',
			'label' => 'Option 2',
		),
		array(
			'value' => 'option-3',
			'label' => 'Option 3',
		),
	);
}

/**
 * Configure Blockstudio to find test blocks in the theme directory.
 */
add_filter(
	'blockstudio/settings/blocks/paths',
	function ( $paths ) {
		$theme_blockstudio_path = get_stylesheet_directory() . '/blockstudio';

		if ( is_dir( $theme_blockstudio_path ) ) {
			$paths[] = $theme_blockstudio_path;
		}

		return $paths;
	},
	10,
	1
);

/**
 * Configure Blockstudio to find test pages in the theme directory.
 */
add_filter(
	'blockstudio/pages/paths',
	function ( $paths ) {
		$theme_pages_path = get_stylesheet_directory() . '/pages';

		if ( is_dir( $theme_pages_path ) ) {
			$paths[] = $theme_pages_path;
		}

		return $paths;
	},
	10,
	1
);

/**
 * Provide editor assets for classes autocomplete.
 */
add_filter(
	'blockstudio/editor/assets',
	function () {
		return array(
			'blockstudio-editor-test',
			'wp-block-library-theme',
			'this-does-not-exist',
		);
	}
);

/**
 * Provide editor markup.
 */
add_filter(
	'blockstudio/settings/editor/markup',
	function () {
		return '<style>body { font-family: sans-serif; }</style>';
	}
);

/**
 * Provide test conditions.
 */
add_filter(
	'blockstudio/blocks/conditions',
	function () {
		return array( 'test' => true );
	}
);

/**
 * Provide populate data for select fields, colors, and gradients.
 */
add_filter(
	'blockstudio/blocks/attributes/populate',
	function ( $options ) {
		// Default populate options
		$options['default'] = array(
			array(
				'value' => 'custom-1',
				'label' => 'Custom 1',
			),
			array(
				'value' => 'custom-2',
				'label' => 'Custom 2',
			),
			array(
				'value' => 'custom-3',
				'label' => 'Custom 3',
			),
		);

		// Array populate options
		$options['array'] = array( 'custom-1', 'custom-2', 'custom-3' );

		// Colors
		$options['colors'] = array(
			array(
				'name'  => 'red',
				'value' => '#ff0000',
				'slug'  => 'red',
			),
			array(
				'name'  => 'green',
				'value' => '#00ff00',
				'slug'  => 'green',
			),
			array(
				'name'  => 'blue',
				'value' => '#0000ff',
				'slug'  => 'blue',
			),
		);

		// Gradients
		$options['gradients'] = array(
			array(
				'name'  => 'JShine',
				'value' => 'linear-gradient(135deg,#12c2e9 0%,#c471ed 50%,#f64f59 100%)',
				'slug'  => 'jshine',
			),
			array(
				'name'  => 'Moonlit Asteroid',
				'value' => 'linear-gradient(135deg,#0F2027 0%, #203A43 0%, #2c5364 100%)',
				'slug'  => 'moonlit-asteroid',
			),
			array(
				'name'  => 'Rastafarie',
				'value' => 'linear-gradient(135deg,#1E9600 0%, #FFF200 0%, #FF0000 100%)',
				'slug'  => 'rastafari',
			),
		);

		return $options;
	}
);

/**
 * Filter block attributes for lineNumbers.
 */
add_filter(
	'blockstudio/blocks/attributes',
	function ( $attribute, $block ) {
		if ( isset( $attribute['id'] ) && $attribute['id'] === 'lineNumbers' ) {
			$attribute['default']    = true;
			$attribute['conditions'] = array(
				array(
					array(
						'id'       => 'language',
						'operator' => '==',
						'value'    => 'css',
					),
				),
			);
		}
		return $attribute;
	},
	10,
	2
);

/**
 * Filter block attributes render for copyButton.
 */
add_filter(
	'blockstudio/blocks/attributes/render',
	function ( $value, $key, $block ) {
		if ( $key === 'copyButton' && ( $block['name'] ?? false ) === 'blockstudio-element/code' ) {
			$value = true;
		}
		return $value;
	},
	10,
	3
);

/**
 * Add SCSS import paths.
 */
add_filter(
	'blockstudio/assets/process/scss/importPaths',
	function ( $paths ) {
		$paths[] = get_template_directory() . '/misc/';
		return $paths;
	}
);

/**
 * Force block discovery after all plugins are loaded.
 */
add_action(
	'plugins_loaded',
	function () {
		// Trigger block discovery if Blockstudio is available
		if ( class_exists( 'Blockstudio\\Build' ) ) {
			// Blocks should auto-discover, but we can force a refresh if needed
		}
	},
	100
);

add_action(
	'init',
	function () {
		// Use blockstudio_theme instead of wp_theme to avoid conflict with WP core
		if ( ! taxonomy_exists( 'blockstudio_theme' ) ) {
			register_taxonomy(
				'blockstudio_theme',
				'post',
				array(
					'label'        => 'Blockstudio Theme',
					'public'       => true,
					'show_in_rest' => true,
				)
			);
		}

		if ( ! taxonomy_exists( 'blockstudio-project-status' ) ) {
			register_taxonomy(
				'blockstudio-project-status',
				'post',
				array(
					'label'        => 'Project Status',
					'public'       => true,
					'show_in_rest' => true,
				)
			);
		}

		if ( ! taxonomy_exists( 'edd_log_type' ) ) {
			register_taxonomy(
				'edd_log_type',
				'post',
				array(
					'label'        => 'EDD Log Type',
					'public'       => true,
					'show_in_rest' => true,
				)
			);
		}
	},
	5
);

add_action(
	'rest_api_init',
	function () {
		register_rest_route(
			'blockstudio-test/v1',
			'/snapshot',
			array(
				'methods'             => 'GET',
				'callback'            => function () {
					try {
						if ( ! class_exists( 'Blockstudio\\Build' ) ) {
							return new WP_Error( 'not_loaded', 'Blockstudio Build class not loaded', array( 'status' => 500 ) );
						}

						// Detect v6 vs v7 by checking method names
						$is_v7 = method_exists( 'Blockstudio\\Build', 'assets_admin' );

						if ( $is_v7 ) {
							return array(
								'blocks'            => \Blockstudio\Build::blocks(),
								'data'              => \Blockstudio\Build::data(),
								'extensions'        => \Blockstudio\Build::extensions(),
								'files'             => \Blockstudio\Build::files(),
								'assetsAdmin'       => \Blockstudio\Build::assets_admin(),
								'assetsBlockEditor' => \Blockstudio\Build::assets_block_editor(),
								'assetsGlobal'      => \Blockstudio\Build::assets_global(),
								'paths'             => \Blockstudio\Build::paths(),
								'overrides'         => \Blockstudio\Build::overrides(),
								'assets'            => \Blockstudio\Build::assets(),
								'blade'             => \Blockstudio\Build::blade(),
							);
						} else {
							// v6 uses camelCase
							return array(
								'blocks'            => \Blockstudio\Build::blocks(),
								'data'              => \Blockstudio\Build::data(),
								'extensions'        => \Blockstudio\Build::extensions(),
								'files'             => \Blockstudio\Build::files(),
								'assetsAdmin'       => \Blockstudio\Build::assetsAdmin(),
								'assetsBlockEditor' => \Blockstudio\Build::assetsBlockEditor(),
								'assetsGlobal'      => \Blockstudio\Build::assetsGlobal(),
								'paths'             => \Blockstudio\Build::paths(),
								'overrides'         => \Blockstudio\Build::overrides(),
								'assets'            => \Blockstudio\Build::assets(),
								'blade'             => \Blockstudio\Build::blade(),
							);
						}
					} catch ( \Throwable $e ) {
						return new WP_Error( 'php_error', $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine(), array( 'status' => 500 ) );
					}
				},
				'permission_callback' => '__return_true',
			)
		);

		// Get all blocks from Build class
		register_rest_route(
			'blockstudio-test/v1',
			'/build/all',
			array(
				'methods'             => 'GET',
				'callback'            => function () {
					if ( ! class_exists( 'Blockstudio\\Build' ) ) {
						return new WP_Error( 'not_loaded', 'Blockstudio Build class not loaded', array( 'status' => 500 ) );
					}
					return \Blockstudio\Build::blocks();
				},
				'permission_callback' => '__return_true',
			)
		);

		// Get Build class data for a specific block by name
		register_rest_route(
			'blockstudio-test/v1',
			'/build/(?P<block>.+)',
			array(
				'methods'             => 'GET',
				'callback'            => function ( $request ) {
					if ( ! class_exists( 'Blockstudio\\Build' ) ) {
						return new WP_Error( 'not_loaded', 'Blockstudio Build class not loaded', array( 'status' => 500 ) );
					}

					$blockName = $request->get_param( 'block' );
					$blocks    = \Blockstudio\Build::getBlocks();

					// Find matching block by name
					foreach ( $blocks as $path => $data ) {
						if ( ( $data['name'] ?? '' ) === $blockName ) {
							return array(
								'path'       => $path,
								'name'       => $data['name'] ?? null,
								'title'      => $data['title'] ?? null,
								'category'   => $data['category'] ?? null,
								'fields'     => $data['fields'] ?? array(),
								'attributes' => $data['attributes'] ?? array(),
								'assets'     => $data['assets'] ?? array(),
								'meta'       => $data['meta'] ?? array(),
								'render'     => $data['render'] ?? null,
								'supports'   => $data['supports'] ?? array(),
							);
						}
					}

					return new WP_Error( 'not_found', "Block '{$blockName}' not found", array( 'status' => 404 ) );
				},
				'permission_callback' => '__return_true',
			)
		);

		// Get all registered Blockstudio blocks from WP_Block_Type_Registry
		register_rest_route(
			'blockstudio-test/v1',
			'/registered',
			array(
				'methods'             => 'GET',
				'callback'            => function () {
					$registry = WP_Block_Type_Registry::get_instance();
					$all      = $registry->get_all_registered();
					$blocks   = array();

					foreach ( $all as $name => $block ) {
						if ( str_starts_with( $name, 'blockstudio/' ) ) {
							$blocks[ $name ] = array(
								'attributes' => $block->attributes ?? array(),
								'supports'   => $block->supports ?? array(),
								'category'   => $block->category ?? null,
								'title'      => $block->title ?? null,
							);
						}
					}

					return $blocks;
				},
				'permission_callback' => '__return_true',
			)
		);

		// Render a block
		register_rest_route(
			'blockstudio-test/v1',
			'/render',
			array(
				'methods'             => 'POST',
				'callback'            => function ( $request ) {
					$blockName = $request->get_param( 'blockName' );
					$attrs     = $request->get_param( 'attrs' ) ?? array();
					$innerHTML = $request->get_param( 'innerHTML' ) ?? '';

					$html = render_block(
						array(
							'blockName'   => $blockName,
							'attrs'       => $attrs,
							'innerHTML'   => $innerHTML,
							'innerBlocks' => array(),
						)
					);

					return array(
						'html' => $html,
					);
				},
				'permission_callback' => '__return_true',
			)
		);

		// Get assets for a block (CSS/JS files)
		register_rest_route(
			'blockstudio-test/v1',
			'/assets/(?P<block>.+)',
			array(
				'methods'             => 'GET',
				'callback'            => function ( $request ) {
					if ( ! class_exists( 'Blockstudio\\Build' ) ) {
						return new WP_Error( 'not_loaded', 'Blockstudio Build class not loaded', array( 'status' => 500 ) );
					}

					$blockName = $request->get_param( 'block' );
					$blocks    = \Blockstudio\Build::getBlocks();

					foreach ( $blocks as $path => $data ) {
						if ( ( $data['name'] ?? '' ) === $blockName ) {
							return array(
								'assets' => $data['assets'] ?? array(),
								'path'   => $path,
							);
						}
					}

					return new WP_Error( 'not_found', "Block '{$blockName}' not found", array( 'status' => 404 ) );
				},
				'permission_callback' => '__return_true',
			)
		);

		// Health check endpoint
		register_rest_route(
			'blockstudio-test/v1',
			'/health',
			array(
				'methods'             => 'GET',
				'callback'            => function () {
					return array(
						'status'             => 'ok',
						'blockstudio_loaded' => class_exists( 'Blockstudio\\Build' ),
						'php_version'        => PHP_VERSION,
						'wp_version'         => get_bloginfo( 'version' ),
					);
				},
				'permission_callback' => '__return_true',
			)
		);

		register_rest_route(
			'blockstudio-test/v1',
			'/compiled-assets',
			array(
				'methods'             => 'GET',
				'callback'            => function () {
					$theme_blockstudio_path = get_stylesheet_directory() . '/blockstudio';
					$compiled               = array();

					if ( ! is_dir( $theme_blockstudio_path ) ) {
						return new WP_Error( 'not_found', 'Blockstudio theme directory not found', array( 'status' => 404 ) );
					}

					// Find all _dist directories recursively
					$iterator = new RecursiveIteratorIterator(
						new RecursiveDirectoryIterator( $theme_blockstudio_path, RecursiveDirectoryIterator::SKIP_DOTS ),
						RecursiveIteratorIterator::SELF_FIRST
					);

					foreach ( $iterator as $file ) {
						if ( $file->isFile() ) {
							$path         = $file->getPathname();
							$relativePath = str_replace( $theme_blockstudio_path . '/', '', $path );

							// Only include files in _dist directories
							if ( strpos( $relativePath, '_dist/' ) !== false ) {
								$extension = $file->getExtension();

								// Only include CSS and JS files
								if ( in_array( $extension, array( 'css', 'js' ), true ) ) {
									// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Reading local compiled asset.
									$content = file_get_contents( $path );

									// Normalize the key by removing hashes/timestamps from filename
									// style-8c61297c7ad6a7f39af80a70d8992118.css -> style.css
									// script-1746475334.js -> script.js
									$normalizedFilename = preg_replace( '/-[a-f0-9]{32}\./', '.', $file->getBasename() );
									$normalizedFilename = preg_replace( '/-\d{10,}\./', '.', $normalizedFilename );

									$normalizedKey = dirname( $relativePath ) . '/' . $normalizedFilename;

									$compiled[ $normalizedKey ] = array(
										'content'   => $content,
										'size'      => strlen( $content ),
										'extension' => $extension,
									);
								}
							}
						}
					}

					ksort( $compiled );
					return $compiled;
				},
				'permission_callback' => '__return_true',
			)
		);

		register_rest_route(
			'blockstudio-test/v1',
			'/e2e/setup',
			array(
				'methods'             => 'POST',
				'callback'            => function () {
					global $wpdb;

					$all_posts = get_posts(
						array(
							'post_type'   => array( 'post', 'page', 'attachment', 'wp_block' ),
							'post_status' => 'any',
							'numberposts' => -1,
							'fields'      => 'ids',
						)
					);
					foreach ( $all_posts as $post_id ) {
						wp_delete_post( $post_id, true );
					}

					switch_theme( 'theme' );

					global $wp_rewrite;
					$wp_rewrite->set_permalink_structure( '/%postname%/' );
					$wp_rewrite->flush_rules( true );

					$created = array(
						'posts' => array(),
						'media' => array(),
						'users' => array(),
						'terms' => array(),
						'theme' => 'theme',
					);

					// Helper to create post with specific ID
					$create_post_with_id = function ( $id, $title, $name, $type = 'post' ) use ( $wpdb, &$created ) {
						if ( ! get_post( $id ) ) {
							$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
								$wpdb->posts,
								array(
									'ID'                => $id,
									'post_author'       => 1,
									'post_date'         => '2022-07-09 06:36:30',
									'post_date_gmt'     => '2022-07-09 06:36:30',
									'post_content'      => '',
									'post_title'        => $title,
									'post_excerpt'      => '',
									'post_status'       => 'publish',
									'comment_status'    => 'closed',
									'ping_status'       => 'closed',
									'post_password'     => '',
									'post_name'         => $name,
									'post_modified'     => '2023-06-17 13:57:45',
									'post_modified_gmt' => '2023-06-17 13:57:45',
									'post_type'         => $type,
									'guid'              => home_url( "/?p=$id" ),
								)
							);
							$created['posts'][] = $id;
							return true;
						}
						return false;
					};

					// Helper to create dummy image file
					$create_dummy_image = function ( $filename, $width = 100, $height = 100 ) {
						$upload_dir = wp_upload_dir();
						$file_path  = $upload_dir['path'] . '/' . $filename;

						// Create directory if needed
						if ( ! file_exists( $upload_dir['path'] ) ) {
							wp_mkdir_p( $upload_dir['path'] );
						}

						// Create a simple PNG image
						$image    = imagecreatetruecolor( $width, $height );
						$bg_color = imagecolorallocate( $image, wp_rand( 0, 255 ), wp_rand( 0, 255 ), wp_rand( 0, 255 ) );
						imagefill( $image, 0, 0, $bg_color );
						imagepng( $image, $file_path );
						imagedestroy( $image );

						return array(
							'path'   => $file_path,
							'url'    => $upload_dir['url'] . '/' . $filename,
							'subdir' => $upload_dir['subdir'],
						);
					};

					// Helper to create dummy video file (just a small binary file)
					$create_dummy_video = function ( $filename ) {
						$upload_dir = wp_upload_dir();
						$file_path  = $upload_dir['path'] . '/' . $filename;

						if ( ! file_exists( $upload_dir['path'] ) ) {
							wp_mkdir_p( $upload_dir['path'] );
						}

						// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Test helper creating dummy video file.
						file_put_contents( $file_path, str_repeat( "\x00", 1000 ) );

						return array(
							'path'   => $file_path,
							'url'    => $upload_dir['url'] . '/' . $filename,
							'subdir' => $upload_dir['subdir'],
						);
					};

					// Helper to create attachment with specific ID
					$create_attachment_with_id = function ( $id, $filename, $mime_type, $file_info ) use ( $wpdb, &$created ) {
						if ( ! get_post( $id ) ) {
							$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
								$wpdb->posts,
								array(
									'ID'                => $id,
									'post_author'       => 1,
									'post_date'         => '2022-12-01 00:00:00',
									'post_date_gmt'     => '2022-12-01 00:00:00',
									'post_content'      => '',
									'post_title'        => pathinfo( $filename, PATHINFO_FILENAME ),
									'post_excerpt'      => '',
									'post_status'       => 'inherit',
									'comment_status'    => 'open',
									'ping_status'       => 'closed',
									'post_password'     => '',
									'post_name'         => sanitize_title( pathinfo( $filename, PATHINFO_FILENAME ) ),
									'post_modified'     => '2022-12-01 00:00:00',
									'post_modified_gmt' => '2022-12-01 00:00:00',
									'post_type'         => 'attachment',
									'post_mime_type'    => $mime_type,
									'guid'              => $file_info['url'],
								)
							);

							// Set _wp_attached_file meta
							update_post_meta( $id, '_wp_attached_file', ltrim( $file_info['subdir'], '/' ) . '/' . $filename );

							// Set attachment metadata for images
							if ( strpos( $mime_type, 'image' ) !== false ) {
								$metadata = array(
									'width'  => 100,
									'height' => 100,
									'file'   => ltrim( $file_info['subdir'], '/' ) . '/' . $filename,
									'sizes'  => array(
										'thumbnail' => array(
											'file'      => $filename,
											'width'     => 100,
											'height'    => 100,
											'mime-type' => $mime_type,
										),
									),
								);
								update_post_meta( $id, '_wp_attachment_metadata', $metadata );
							}

							$created['media'][] = $id;
							return true;
						}
						return false;
					};

					// Post 1386 - "Native" (used in text.ts for populate)
					$create_post_with_id( 1386, 'Native', 'native' );

					// Post 1388 - "Native Render" (used in select-fetch and other tests)
					$create_post_with_id( 1388, 'Native Render', 'native-render' );

					// Post 1483 - "Native Single" - the main test post for editing
					$create_post_with_id( 1483, 'Native Single', 'native-single' );

					// Post 560 - Used in select/fetch.ts test (searchable as "test")
					$create_post_with_id( 560, 'Test', 'test-select-fetch' );

					// Post 533 - Used in select/fetch.ts test
					$create_post_with_id( 533, 'Et adipisci quia aut', 'et-adipisci-quia-aut' );

					// Additional posts for select/fetch.ts (searching for "e" should return 9 results)
					// Posts: Native(1386), Native Render(1388), Test(560), Et adipisci(533) = 4 posts with "e"
					// Need 5 more to get 9 total
					$create_post_with_id( 534, 'Example One', 'example-one' );
					$create_post_with_id( 535, 'Reference', 'reference' );
					$create_post_with_id( 536, 'Guide', 'guide' );
					$create_post_with_id( 537, 'Release', 'release' );
					$create_post_with_id( 538, 'Feature', 'feature' );

					// Post 1099 - "Reusable" for text.ts populate tests (matches extension default)
					$create_post_with_id( 1099, 'Reusable', 'reusable' );

					// Pattern 2643 - contains type-text block
					$pattern_2643_content = '<!-- wp:blockstudio/type-text /--><!-- wp:blockstudio/type-textarea /-->';
					$existing_2643        = get_post( 2643 );
					if ( ! $existing_2643 || $existing_2643->post_type !== 'wp_block' ) {
						$wpdb->delete( $wpdb->posts, array( 'ID' => 2643 ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
						$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
							$wpdb->posts,
							array(
								'ID'                => 2643,
								'post_author'       => 1,
								'post_date'         => current_time( 'mysql' ),
								'post_date_gmt'     => current_time( 'mysql', 1 ),
								'post_content'      => $pattern_2643_content,
								'post_title'        => 'Test Pattern 1',
								'post_status'       => 'publish',
								'post_name'         => 'test-pattern-1',
								'post_type'         => 'wp_block',
								'post_modified'     => current_time( 'mysql' ),
								'post_modified_gmt' => current_time( 'mysql', 1 ),
							)
						);
						$created['patterns'][] = 2643;
					}

					// Pattern 2644 - contains type-text block
					$pattern_2644_content = '<!-- wp:blockstudio/type-text /--><!-- wp:blockstudio/type-textarea /-->';
					$existing_2644        = get_post( 2644 );
					if ( ! $existing_2644 || $existing_2644->post_type !== 'wp_block' ) {
						$wpdb->delete( $wpdb->posts, array( 'ID' => 2644 ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
						$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
							$wpdb->posts,
							array(
								'ID'                => 2644,
								'post_author'       => 1,
								'post_date'         => current_time( 'mysql' ),
								'post_date_gmt'     => current_time( 'mysql', 1 ),
								'post_content'      => $pattern_2644_content,
								'post_title'        => 'Test Pattern 2',
								'post_status'       => 'publish',
								'post_name'         => 'test-pattern-2',
								'post_type'         => 'wp_block',
								'post_modified'     => current_time( 'mysql' ),
								'post_modified_gmt' => current_time( 'mysql', 1 ),
							)
						);
						$created['patterns'][] = 2644;
					}

					// Note: Removed "Sample Post" loop - those posts interfered with select-fetch test
					// which expects exactly 9 results when searching for "e"

					// Attachment 8 - gutenbergEdit.mp4 (video)
					$video_file = $create_dummy_video( 'gutenbergEdit.mp4' );
					$create_attachment_with_id( 8, 'gutenbergEdit.mp4', 'video/mp4', $video_file );

					// Attachment 1604 - blockstudioEDDRetina.png (image)
					$image1_file = $create_dummy_image( 'blockstudioEDDRetina.png', 200, 200 );
					$create_attachment_with_id( 1604, 'blockstudioEDDRetina.png', 'image/png', $image1_file );

					// Attachment 1605 - blockstudioSEO.png (image)
					$image2_file = $create_dummy_image( 'blockstudioSEO.png', 200, 200 );
					$create_attachment_with_id( 1605, 'blockstudioSEO.png', 'image/png', $image2_file );

					// Attachment 3081 - test image for attributes.ts and tailwind/container.ts
					$image3_file = $create_dummy_image( 'test-image-3081.png', 200, 200 );
					$create_attachment_with_id( 3081, 'test-image-3081.png', 'image/png', $image3_file );

					// Helper to create user with specific ID
					$create_user_with_id = function ( $id, $login, $email, $display_name ) use ( $wpdb, &$created ) {
						if ( ! get_user_by( 'id', $id ) ) {
							$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
								$wpdb->users,
								array(
									'ID'              => $id,
									'user_login'      => $login,
									'user_pass'       => wp_hash_password( 'testpass' ),
									'user_nicename'   => $login,
									'user_email'      => $email,
									'user_registered' => '2020-12-02 18:41:10',
									'display_name'    => $display_name,
								)
							);
							// Add user meta for capabilities
							update_user_meta( $id, 'wp_capabilities', array( 'subscriber' => true ) );
							update_user_meta( $id, 'wp_user_level', 0 );
							$created['users'][] = $id;
							return true;
						}
						return false;
					};

					// User 644 - Nathan Baldwin (465media) - for options tests
					$create_user_with_id( 644, '465media', 'nbaldwin@465-media.com', 'Nathan Baldwin' );

					// User 446 - Taylor Drayson - for select-multiple user tests
					$create_user_with_id( 446, 'tdrayson', 'taylor@thecreativetinker.com', 'Taylor Drayson' );

					// User 704 - Aaron Kessler - for user populate tests
					$create_user_with_id( 704, 'aaronkessler.de', 'mail@aaronkessler.de', 'Aaron Kessler' );

					// User 795 - Vasilii Leitman - for select-multiple user tests
					$create_user_with_id( 795, '1wpdev', 'help@1wp.dev', 'Vasilii Leitman' );

					if ( ! taxonomy_exists( 'blockstudio_theme' ) ) {
						register_taxonomy(
							'blockstudio_theme',
							'post',
							array(
								'label'        => 'WP Theme',
								'public'       => true,
								'show_in_rest' => true,
							)
						);
					}

					if ( ! taxonomy_exists( 'blockstudio-project-status' ) ) {
						register_taxonomy(
							'blockstudio-project-status',
							'post',
							array(
								'label'        => 'Project Status',
								'public'       => true,
								'show_in_rest' => true,
							)
						);
					}

					if ( ! taxonomy_exists( 'edd_log_type' ) ) {
						register_taxonomy(
							'edd_log_type',
							'post',
							array(
								'label'        => 'EDD Log Type',
								'public'       => true,
								'show_in_rest' => true,
							)
						);
					}

					// Helper to create term - uses WP API and accepts any ID
					$create_term = function ( $name, $slug, $taxonomy ) use ( &$created ) {
						// Check if taxonomy exists
						if ( ! taxonomy_exists( $taxonomy ) ) {
							return false;
						}

						// Check if term already exists
						$existing = get_term_by( 'slug', $slug, $taxonomy );
						if ( $existing ) {
							return $existing->term_id;
						}

						// Create the term using WordPress API
						$result = wp_insert_term( $name, $taxonomy, array( 'slug' => $slug ) );

						if ( is_wp_error( $result ) ) {
							return false;
						}

						$created['terms'][] = $result['term_id'];
						return $result['term_id'];
					};

					// Create terms for options tests - IDs will be auto-assigned
					$term_blockstudio_child = $create_term( 'blockstudio-child', 'blockstudio-child', 'blockstudio_theme' );
					$term_fabrikat          = $create_term( 'fabrikat', 'fabrikat', 'blockstudio_theme' );
					$term_backlog           = $create_term( 'Backlog', 'backlog', 'blockstudio-project-status' );
					$term_file_download     = $create_term( 'file_download', 'file_download', 'edd_log_type' );

					// Store created term IDs for reference in tests
					update_option(
						'blockstudio_e2e_terms',
						array(
							'blockstudio-child' => $term_blockstudio_child,
							'fabrikat'          => $term_fabrikat,
							'backlog'           => $term_backlog,
							'file_download'     => $term_file_download,
						)
					);

					// Create additional categories and tags for general tests
					$cat = wp_insert_term( 'Test Category', 'category' );
					if ( ! is_wp_error( $cat ) ) {
						$created['terms'][] = $cat['term_id'];
					}

					$tag = wp_insert_term( 'Test Tag', 'post_tag' );
					if ( ! is_wp_error( $tag ) ) {
						$created['terms'][] = $tag['term_id'];
					}

					// Clean caches
					wp_cache_flush();

					// Clear Blockstudio transients to ensure fresh asset capture
					delete_transient( 'blockstudio_editor_all_assets' );
					delete_transient( 'blockstudio_editor_captured_frontend_scripts' );
					delete_transient( 'blockstudio_editor_captured_frontend_styles' );
					delete_transient( 'blockstudio_editor_expected_capture_assets_id' );

					return array(
						'success' => true,
						'created' => $created,
						'message' => 'E2E test data created successfully',
					);
				},
				'permission_callback' => '__return_true',
			)
		);

		// Trigger page sync (uses keyed merge when available)
		register_rest_route(
			'blockstudio-test/v1',
			'/pages/trigger-sync',
			array(
				'methods'             => 'POST',
				'callback'            => function ( $request ) {
					$page_name = $request->get_param( 'page_name' );

					if ( empty( $page_name ) ) {
						return new WP_Error( 'missing_param', 'page_name is required', array( 'status' => 400 ) );
					}

					if ( ! class_exists( 'Blockstudio\\Page_Registry' ) || ! class_exists( 'Blockstudio\\Page_Sync' ) ) {
						return new WP_Error( 'not_loaded', 'Blockstudio page classes not loaded', array( 'status' => 500 ) );
					}

					$registry  = \Blockstudio\Page_Registry::instance();
					$page_data = $registry->get_page( $page_name );

					// If page not in registry, run discovery to find it
					if ( ! $page_data && class_exists( 'Blockstudio\\Page_Discovery' ) ) {
						$discovery = new \Blockstudio\Page_Discovery();
						$paths     = \Blockstudio\Pages::get_paths();
						$paths     = apply_filters( 'blockstudio/pages/paths', $paths );

						foreach ( $paths as $path ) {
							if ( ! is_dir( $path ) ) {
								continue;
							}
							$registry->add_path( $path );
							$pages = $discovery->discover( $path );
							foreach ( $pages as $name => $data ) {
								$registry->register( $name, $data );
							}
						}

						$page_data = $registry->get_page( $page_name );
					}

					if ( ! $page_data ) {
						return new WP_Error( 'not_found', "Page '{$page_name}' not found in registry", array( 'status' => 404 ) );
					}

					$template_content = $request->get_param( 'template_content' );

					if ( ! empty( $template_content ) ) {
						// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Test helper writing template.
						file_put_contents( $page_data['template_path'], $template_content );
					}

					touch( $page_data['template_path'] ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_touch -- Updating mtime to trigger sync.
					clearstatcache( true, $page_data['template_path'] );

					// Reset stored mtime to force sync to detect the change
					$posts = get_posts(
						array(
							'meta_key'       => '_blockstudio_page_source', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
							'meta_value'     => $page_data['source_path'], // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
							'post_type'      => $page_data['postType'],
							'posts_per_page' => 1,
							'post_status'    => 'any',
						)
					);

					if ( ! empty( $posts ) ) {
						update_post_meta( $posts[0]->ID, '_blockstudio_page_mtime', 0 );
					}

					$sync    = new \Blockstudio\Page_Sync();
					$post_id = $sync->sync( $page_data );

					if ( is_wp_error( $post_id ) ) {
						return $post_id;
					}

					$post = get_post( $post_id );

					return array(
						'post_id'      => $post_id,
						'post_content' => $post ? $post->post_content : '',
					);
				},
				'permission_callback' => '__return_true',
			)
		);

		// Force sync (bypasses keyed merging)
		register_rest_route(
			'blockstudio-test/v1',
			'/pages/force-sync',
			array(
				'methods'             => 'POST',
				'callback'            => function ( $request ) {
					$page_name = $request->get_param( 'page_name' );

					if ( empty( $page_name ) ) {
						return new WP_Error( 'missing_param', 'page_name is required', array( 'status' => 400 ) );
					}

					if ( ! class_exists( 'Blockstudio\\Page_Registry' ) || ! class_exists( 'Blockstudio\\Page_Sync' ) ) {
						return new WP_Error( 'not_loaded', 'Blockstudio page classes not loaded', array( 'status' => 500 ) );
					}

					$registry  = \Blockstudio\Page_Registry::instance();
					$page_data = $registry->get_page( $page_name );

					// If page not in registry, run discovery to find it
					if ( ! $page_data && class_exists( 'Blockstudio\\Page_Discovery' ) ) {
						$discovery = new \Blockstudio\Page_Discovery();
						$paths     = \Blockstudio\Pages::get_paths();
						$paths     = apply_filters( 'blockstudio/pages/paths', $paths );

						foreach ( $paths as $path ) {
							if ( ! is_dir( $path ) ) {
								continue;
							}
							$registry->add_path( $path );
							$pages = $discovery->discover( $path );
							foreach ( $pages as $name => $data ) {
								$registry->register( $name, $data );
							}
						}

						$page_data = $registry->get_page( $page_name );
					}

					if ( ! $page_data ) {
						return new WP_Error( 'not_found', "Page '{$page_name}' not found in registry", array( 'status' => 404 ) );
					}

					$template_content = $request->get_param( 'template_content' );

					if ( ! empty( $template_content ) ) {
						// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Test helper writing template.
						file_put_contents( $page_data['template_path'], $template_content );
					}

					touch( $page_data['template_path'] ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_touch -- Updating mtime to trigger sync.
					clearstatcache( true, $page_data['template_path'] );

					$sync    = new \Blockstudio\Page_Sync();
					$post_id = $sync->force_sync( $page_data );

					if ( is_wp_error( $post_id ) ) {
						return $post_id;
					}

					$post = get_post( $post_id );

					return array(
						'post_id'      => $post_id,
						'post_content' => $post ? $post->post_content : '',
					);
				},
				'permission_callback' => '__return_true',
			)
		);

		// Get page content by post ID
		register_rest_route(
			'blockstudio-test/v1',
			'/pages/content/(?P<id>\d+)',
			array(
				'methods'             => 'GET',
				'callback'            => function ( $request ) {
					$post_id = (int) $request->get_param( 'id' );
					$post    = get_post( $post_id );

					if ( ! $post ) {
						return new WP_Error( 'not_found', 'Post not found', array( 'status' => 404 ) );
					}

					return array(
						'post_id'      => $post->ID,
						'post_content' => $post->post_content,
						'post_status'  => $post->post_status,
					);
				},
				'permission_callback' => '__return_true',
			)
		);

		// Update page content by post ID
		register_rest_route(
			'blockstudio-test/v1',
			'/pages/content/(?P<id>\d+)',
			array(
				'methods'             => 'POST',
				'callback'            => function ( $request ) {
					$post_id = (int) $request->get_param( 'id' );
					$content = $request->get_param( 'content' );

					if ( null === $content ) {
						return new WP_Error( 'missing_param', 'content is required', array( 'status' => 400 ) );
					}

					$result = wp_update_post(
						array(
							'ID'           => $post_id,
							'post_content' => $content,
						),
						true
					);

					if ( is_wp_error( $result ) ) {
						return $result;
					}

					return array(
						'post_id'      => $post_id,
						'post_content' => get_post( $post_id )->post_content,
					);
				},
				'permission_callback' => '__return_true',
			)
		);

		// Get E2E test data IDs (for tests to use dynamic IDs instead of hardcoded)
		register_rest_route(
			'blockstudio-test/v1',
			'/e2e/data',
			array(
				'methods'             => 'GET',
				'callback'            => function () {
					// Get first few posts
					$posts = get_posts(
						array(
							'numberposts' => 5,
							'post_status' => 'publish',
						)
					);

					// Get first few terms
					$categories = get_terms(
						array(
							'taxonomy'   => 'category',
							'number'     => 5,
							'hide_empty' => false,
						)
					);

					$tags = get_terms(
						array(
							'taxonomy'   => 'post_tag',
							'number'     => 5,
							'hide_empty' => false,
						)
					);

					// Get users
					$users = get_users(
						array(
							'number' => 5,
						)
					);

					return array(
						'posts'      => array_map(
							fn( $p ) => array(
								'id'    => $p->ID,
								'title' => $p->post_title,
							),
							$posts
						),
						'categories' => array_map(
							fn( $t ) => array(
								'id'   => $t->term_id,
								'name' => $t->name,
							),
							is_array( $categories ) ? $categories : array()
						),
						'tags'       => array_map(
							fn( $t ) => array(
								'id'   => $t->term_id,
								'name' => $t->name,
							),
							is_array( $tags ) ? $tags : array()
						),
						'users'      => array_map(
							fn( $u ) => array(
								'id'   => $u->ID,
								'name' => $u->display_name,
							),
							$users
						),
					);
				},
				'permission_callback' => '__return_true',
			)
		);
	}
);
