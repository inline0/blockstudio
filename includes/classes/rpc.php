<?php
/**
 * Rpc class.
 *
 * Provides an RPC system for blocks to define server-side functions
 * callable from the frontend via REST API.
 *
 * @package Blockstudio
 */

namespace Blockstudio;

use Closure;
use ReflectionMethod;
use ReflectionObject;

/**
 * Discovers, registers, and dispatches block functions defined in rpc.php files.
 *
 * @since 7.1.0
 */
class Rpc {

	/**
	 * Loaded function maps keyed by block name.
	 *
	 * @var array<string, array<string, array>>
	 */
	private static array $functions = array();

	/**
	 * Whether functions have been loaded.
	 *
	 * @var bool
	 */
	private static bool $loaded = false;

	/**
	 * Initialize the functions system.
	 *
	 * @return void
	 */
	public static function init(): void {
		add_action( 'rest_api_init', array( __CLASS__, 'register_endpoint' ) );
		add_filter( 'blockstudio/buffer/output', array( __CLASS__, 'inject_frontend_client' ), 2 );
		add_action( 'enqueue_block_editor_assets', array( __CLASS__, 'inject_editor_client' ) );
	}

	/**
	 * Get the bs.fn() client script body (without wrapping tags).
	 *
	 * @return string The JavaScript client code.
	 */
	private static function get_client_script(): string {
		$rest_url = esc_url_raw( rest_url( 'blockstudio/v1/fn/' ) );
		$nonce    = wp_create_nonce( 'wp_rest' );

		$bs_token = Csrf::generate();

		return 'window.bs=window.bs||{};'
			. 'bs._token="' . $bs_token . '";'
			. 'bs._block=function(){var s=document.currentScript;return s&&s.dataset.block||""};'
			. 'bs.fn=function(n,p,b){'
			. 'var block=b||bs._block();'
			. 'if(!block)throw new Error("bs.fn: block name required. Pass as third argument, or use an inline script (module scripts cannot auto-detect).");'
			. 'var u="' . $rest_url . '"+block+"/"+n;'
			. 'return fetch(u,{method:"POST",headers:{"Content-Type":"application/json","X-WP-Nonce":"' . $nonce . '","X-BS-Token":"' . $bs_token . '"},body:JSON.stringify({params:p||{}})}).then(function(r){return r.json()});'
			. '};';
	}

	/**
	 * Inject the bs.fn() client into the frontend output buffer.
	 *
	 * @param string $html The page HTML.
	 *
	 * @return string The HTML with client script injected.
	 */
	public static function inject_frontend_client( string $html ): string {
		if ( ! self::has_any_functions() || str_contains( $html, 'id="blockstudio-fn"' ) ) {
			return $html;
		}

		$script = '<script id="blockstudio-fn">' . self::get_client_script() . '</script>';
		$html   = str_replace( '</head>', $script . '</head>', $html );

		return $html;
	}

	/**
	 * Inject the bs.fn() client into the block editor.
	 *
	 * @return void
	 */
	public static function inject_editor_client(): void {
		if ( ! is_admin() || ! self::has_any_functions() ) {
			return;
		}

		wp_register_script( 'blockstudio-fn', false, array(), BLOCKSTUDIO_VERSION, false );
		wp_enqueue_script( 'blockstudio-fn' );
		wp_add_inline_script( 'blockstudio-fn', self::get_client_script() );
	}

	/**
	 * Check if any blocks have registered functions.
	 *
	 * @return bool Whether any functions are registered.
	 */
	public static function has_any(): bool {
		return self::has_any_functions();
	}

	/**
	 * Get the client script code for external use.
	 *
	 * @return string The JavaScript client code.
	 */
	public static function client_script(): string {
		return self::get_client_script();
	}

	/**
	 * Check if any RPC functions exist (internal).
	 *
	 * @return bool Whether any functions are registered.
	 */
	private static function has_any_functions(): bool {
		self::load_all();

		return ! empty( self::$functions );
	}

	/**
	 * Register the REST endpoint for function calls.
	 *
	 * @return void
	 */
	public static function register_endpoint(): void {
		register_rest_route(
			'blockstudio/v1',
			'/fn/(?P<block>[a-z0-9-]+/[a-z0-9-]+)/(?P<function>[a-z0-9_-]+)',
			array(
				'methods'             => \WP_REST_Server::ALLMETHODS,
				'callback'            => array( __CLASS__, 'handle_request' ),
				'permission_callback' => array( __CLASS__, 'check_permission' ),
				'args'                => array(
					'block'    => array(
						'required'          => true,
						'validate_callback' => function ( $param ) {
							return is_string( $param ) && preg_match( '/^[a-z0-9-]+\/[a-z0-9-]+$/', $param );
						},
					),
					'function' => array(
						'required'          => true,
						'validate_callback' => function ( $param ) {
							return is_string( $param ) && preg_match( '/^[a-z0-9_-]+$/', $param );
						},
					),
					'params'   => array(
						'default' => array(),
					),
				),
			)
		);
	}

	/**
	 * Check permission for a function call.
	 *
	 * Public functions are callable by anyone. All others require authentication.
	 *
	 * @param \WP_REST_Request $request The request.
	 *
	 * @return bool|\WP_Error True if allowed, WP_Error otherwise.
	 */
	public static function check_permission( $request ) {
		$block_name    = $request->get_param( 'block' );
		$function_name = $request->get_param( 'function' );

		self::load_all();

		if ( ! isset( self::$functions[ $block_name ][ $function_name ] ) ) {
			return true;
		}

		$fn = self::$functions[ $block_name ][ $function_name ];

		$allowed_methods = (array) ( $fn['methods'] ?? array( 'POST' ) );
		$allowed_methods = array_map( 'strtoupper', $allowed_methods );
		$request_method  = strtoupper( $request->get_method() );

		if ( ! in_array( $request_method, $allowed_methods, true ) ) {
			return new \WP_Error(
				'blockstudio_fn_method_not_allowed',
				__( 'Method not allowed.', 'blockstudio' ),
				array( 'status' => 405 )
			);
		}

		if ( 'open' === ( $fn['public'] ?? false ) ) {
			return true;
		}

		if ( ! empty( $fn['public'] ) && true === $fn['public'] ) {
			if ( is_user_logged_in() ) {
				return true;
			}
			if ( ! Csrf::verify_request( $request ) ) {
				return new \WP_Error(
					'blockstudio_fn_csrf',
					__( 'Invalid or missing CSRF token.', 'blockstudio' ),
					array( 'status' => 403 )
				);
			}
			return true;
		}

		if ( ! is_user_logged_in() ) {
			return new \WP_Error(
				'blockstudio_fn_unauthorized',
				__( 'Authentication required.', 'blockstudio' ),
				array( 'status' => 401 )
			);
		}

		if ( ! empty( $fn['capability'] ) ) {
			$caps    = (array) $fn['capability'];
			$has_cap = false;

			foreach ( $caps as $cap ) {
				if ( current_user_can( $cap ) ) {
					$has_cap = true;
					break;
				}
			}

			if ( ! $has_cap ) {
				return new \WP_Error(
					'blockstudio_fn_forbidden',
					__( 'Insufficient permissions.', 'blockstudio' ),
					array( 'status' => 403 )
				);
			}
		}

		return true;
	}

	/**
	 * Handle a function call request.
	 *
	 * @param \WP_REST_Request $request The request.
	 *
	 * @return \WP_REST_Response|\WP_Error The function result.
	 */
	public static function handle_request( $request ) {
		$block_name    = $request->get_param( 'block' );
		$function_name = $request->get_param( 'function' );
		$params        = $request->get_param( 'params' ) ?? array();

		self::load_all();

		if ( ! isset( self::$functions[ $block_name ] ) ) {
			return new \WP_Error(
				'blockstudio_fn_block_not_found',
				__( 'Block not found.', 'blockstudio' ),
				array( 'status' => 404 )
			);
		}

		if ( ! isset( self::$functions[ $block_name ][ $function_name ] ) ) {
			return new \WP_Error(
				'blockstudio_fn_not_found',
				__( 'Function not found.', 'blockstudio' ),
				array( 'status' => 404 )
			);
		}

		$fn       = self::$functions[ $block_name ][ $function_name ];
		$callable = $fn['callback'];

		if ( ! is_callable( $callable ) ) {
			return new \WP_Error(
				'blockstudio_fn_not_callable',
				__( 'Function is not callable.', 'blockstudio' ),
				array( 'status' => 500 )
			);
		}

		do_action(
			'blockstudio/rpc/before_call',
			array(
				'block'    => $block_name,
				'function' => $function_name,
				'params'   => $params,
			)
		);

		$result = call_user_func( $callable, $params );

		do_action(
			'blockstudio/rpc/after_call',
			array(
				'block'    => $block_name,
				'function' => $function_name,
				'params'   => $params,
				'result'   => $result,
			)
		);

		return rest_ensure_response( $result );
	}

	/**
	 * Call a block function from PHP.
	 *
	 * @param string $block_name    Full block name (e.g. blockstudio/hero).
	 * @param string $function_name The function to call.
	 * @param array  $params        Parameters to pass.
	 *
	 * @return mixed The function result, or WP_Error on failure.
	 */
	public static function call( string $block_name, string $function_name, array $params = array() ) {
		self::load_all();

		if ( ! isset( self::$functions[ $block_name ][ $function_name ] ) ) {
			return new \WP_Error(
				'blockstudio_fn_not_found',
				__( 'Function not found.', 'blockstudio' ),
				array( 'status' => 404 )
			);
		}

		$fn        = self::$functions[ $block_name ][ $function_name ];
		$hook_args = array(
			'block'    => $block_name,
			'function' => $function_name,
			'params'   => $params,
		);

		do_action( 'blockstudio/rpc/before_call', $hook_args );

		$result = call_user_func( $fn['callback'], $params );

		$hook_args['result'] = $result;
		do_action( 'blockstudio/rpc/after_call', $hook_args );

		return $result;
	}

	/**
	 * Load all block functions from discovered blocks.
	 *
	 * @return void
	 */
	private static function load_all(): void {
		if ( self::$loaded ) {
			return;
		}

		self::$loaded = true;

		$registry = Block_Registry::instance();
		$data     = $registry->get_data();

		foreach ( $data as $block_name => $block_data ) {
			self::load_block_functions( $block_name, $block_data );
		}

		self::$functions = apply_filters( 'blockstudio/rpc', self::$functions );
	}

	/**
	 * Load functions for a single block.
	 *
	 * @param string $block_name The block name.
	 * @param array  $block_data The block data from registry.
	 *
	 * @return void
	 */
	private static function load_block_functions( string $block_name, array $block_data ): void {
		$files_paths = $block_data['filesPaths'] ?? array();
		$fn_path     = false;

		foreach ( $files_paths as $path ) {
			if ( str_ends_with( $path, '/rpc.php' ) ) {
				$fn_path = $path;
				break;
			}
		}

		if ( ! $fn_path || ! file_exists( $fn_path ) ) {
			return;
		}

		$definitions = include $fn_path;

		if ( is_object( $definitions ) ) {
			self::load_object_functions( $block_name, $definitions );
			return;
		}

		if ( ! is_array( $definitions ) ) {
			return;
		}

		foreach ( $definitions as $name => $definition ) {
			$normalized = self::normalize_function_definition( $definition );

			if ( null !== $normalized ) {
				self::$functions[ $block_name ][ $name ] = $normalized;
			}
		}
	}

	/**
	 * Load attribute-based functions from an object definition.
	 *
	 * @param string $block_name  The block name.
	 * @param object $definitions The returned object definition.
	 *
	 * @return void
	 */
	private static function load_object_functions( string $block_name, object $definitions ): void {
		$reflection = new ReflectionObject( $definitions );

		foreach ( $reflection->getMethods( ReflectionMethod::IS_PUBLIC ) as $method ) {
			if ( $method->isConstructor() || $method->isDestructor() || $method->isStatic() ) {
				continue;
			}

			$attributes = $method->getAttributes( Rpc_Definition::class );

			if ( empty( $attributes ) ) {
				continue;
			}

			/**
			 * Attribute instances are guaranteed by Reflection.
			 *
			 * @var Rpc_Definition $attribute
			 */
			$attribute = $attributes[0]->newInstance();
			$name      = null !== $attribute->name && '' !== $attribute->name
				? $attribute->name
				: self::normalize_function_name( $method->getName() );

			self::$functions[ $block_name ][ $name ] = array(
				'callback'   => Closure::fromCallable( array( $definitions, $method->getName() ) ),
				'public'     => self::normalize_access( $attribute->access ),
				'capability' => $attribute->capability,
				'methods'    => self::normalize_methods( $attribute->methods ),
			);
		}
	}

	/**
	 * Normalize a function name for attribute-based definitions.
	 *
	 * @param string $name The PHP method name.
	 *
	 * @return string
	 */
	private static function normalize_function_name( string $name ): string {
		$normalized = preg_replace( '/(?<!^)[A-Z]/', '_$0', $name );

		if ( ! is_string( $normalized ) || '' === $normalized ) {
			return strtolower( $name );
		}

		return strtolower( $normalized );
	}

	/**
	 * Normalize a legacy or PHP-native function definition.
	 *
	 * @param mixed $definition The function definition.
	 *
	 * @return array<string, mixed>|null
	 */
	private static function normalize_function_definition( mixed $definition ): ?array {
		if ( is_callable( $definition ) ) {
			return array(
				'callback'   => $definition,
				'public'     => false,
				'capability' => null,
				'methods'    => array( 'POST' ),
			);
		}

		if ( ! is_array( $definition ) || empty( $definition['callback'] ) || ! is_callable( $definition['callback'] ) ) {
			return null;
		}

		return array(
			'callback'   => $definition['callback'],
			'public'     => self::normalize_access( $definition['public'] ?? false ),
			'capability' => $definition['capability'] ?? null,
			'methods'    => self::normalize_methods( $definition['methods'] ?? array( 'POST' ) ),
		);
	}

	/**
	 * Normalize access values to the legacy runtime format.
	 *
	 * @param mixed $access The access value.
	 *
	 * @return bool|string
	 */
	private static function normalize_access( mixed $access ): bool|string {
		if ( $access instanceof Rpc_Access ) {
			return match ( $access ) {
				Rpc_Access::Open => 'open',
				Rpc_Access::Session => true,
				Rpc_Access::Authenticated => false,
			};
		}

		if ( 'session' === $access ) {
			return true;
		}

		if ( 'open' === $access ) {
			return 'open';
		}

		return true === $access;
	}

	/**
	 * Normalize HTTP methods to uppercase strings.
	 *
	 * @param mixed $methods Raw method list.
	 *
	 * @return array<int, string>
	 */
	private static function normalize_methods( mixed $methods ): array {
		$methods    = (array) $methods;
		$normalized = array();

		foreach ( $methods as $method ) {
			if ( $method instanceof Http_Method ) {
				$normalized[] = $method->value;
				continue;
			}

			if ( is_string( $method ) && '' !== $method ) {
				$normalized[] = strtoupper( $method );
			}
		}

		return empty( $normalized ) ? array( 'POST' ) : array_values( array_unique( $normalized ) );
	}

	/**
	 * Get all registered functions.
	 *
	 * @return array<string, array<string, array>> Functions keyed by block name.
	 */
	public static function get_all(): array {
		self::load_all();

		return self::$functions;
	}

	/**
	 * Check if a block has any registered functions.
	 *
	 * @param string $block_name The block name.
	 *
	 * @return bool Whether the block has functions.
	 */
	public static function has_functions( string $block_name ): bool {
		self::load_all();

		return ! empty( self::$functions[ $block_name ] );
	}
}
