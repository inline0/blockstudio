<?php
/**
 * Database class.
 *
 * Provides a data layer for blocks via db.php files. Supports custom table,
 * post meta, and JSONC file storage with auto-generated CRUD endpoints.
 *
 * @package Blockstudio
 */

namespace Blockstudio;

/**
 * Discovers db.php files, manages storage, and registers REST endpoints.
 *
 * @since 7.1.0
 */
class Database {

	/**
	 * Loaded database definitions keyed by "block_name:schema_name".
	 *
	 * @var array<string, array>
	 */
	private static array $schemas = array();

	/**
	 * Block paths keyed by block name for JSONC file resolution.
	 *
	 * @var array<string, string>
	 */
	private static array $block_paths = array();

	/**
	 * Realtime polling configs collected from schemas.
	 *
	 * @var array<int, array>
	 */
	private static array $realtime = array();

	/**
	 * Whether schemas have been loaded.
	 *
	 * @var bool
	 */
	private static bool $loaded = false;

	/**
	 * Initialize the database system.
	 *
	 * @return void
	 */
	public static function init(): void {
		add_action( 'rest_api_init', array( __CLASS__, 'register_endpoints' ) );
		add_filter( 'blockstudio/buffer/output', array( __CLASS__, 'inject_frontend_client' ), 3 );
		add_action( 'enqueue_block_editor_assets', array( __CLASS__, 'inject_editor_client' ) );
	}

	/**
	 * Get the bs.db() client script.
	 *
	 * @return string The JavaScript client code.
	 */
	private static function get_client_script(): string {
		$rest_url = esc_url_raw( rest_url( 'blockstudio/v1/db/' ) );
		$nonce    = wp_create_nonce( 'wp_rest' );
		$bs_token = Csrf::generate();

		$script = 'window.bs=window.bs||{};'
			. 'bs.db=function(b,s){'
			. 'var u="' . $rest_url . '"+b.replace("/","-")+"/"+(s||"default");'
			. 'var h={"Content-Type":"application/json","X-WP-Nonce":"' . $nonce . '","X-BS-Token":"' . $bs_token . '"};'
			. 'return{'
			. 'create:function(d){return fetch(u,{method:"POST",headers:h,body:JSON.stringify(d)}).then(function(r){return r.json()})},'
			. 'list:function(q){var p=q?"?"+new URLSearchParams(q):"";return fetch(u+p,{method:"GET",headers:h}).then(function(r){return r.json()})},'
			. 'get:function(id){return fetch(u+"/"+id,{method:"GET",headers:h}).then(function(r){return r.json()})},'
			. 'update:function(id,d){return fetch(u+"/"+id,{method:"PUT",headers:h,body:JSON.stringify(d)}).then(function(r){return r.json()})},'
			. 'delete:function(id){return fetch(u+"/"+id,{method:"DELETE",headers:h}).then(function(r){return r.json()})}'
			. '};};'

			// bs.cache: in-memory query cache with manual controls.
			. 'bs._qc={};'
			. 'bs.cache={'
			. 'get:function(k){var e=bs._qc[k];if(!e)return undefined;return e.data},'
			. 'set:function(k,d){bs._qc[k]={data:d,time:Date.now()}},'
			. 'invalidate:function(k){delete bs._qc[k]},'
			. 'clear:function(){bs._qc={}}'
			. '};'

			// bs.query: cached fetch with dedup and staleTime.
			. 'bs._qp={};'
			. 'bs.query=function(k,fn,o){'
			. 'o=o||{};'
			. 'var st=o.staleTime||0;'
			. 'var e=bs._qc[k];'
			. 'if(e&&st&&(Date.now()-e.time)<st)return Promise.resolve(e.data);'
			. 'if(bs._qp[k])return bs._qp[k];'
			. 'var p=fn().then(function(d){bs._qc[k]={data:d,time:Date.now()};delete bs._qp[k];return d},function(err){delete bs._qp[k];throw err});'
			. 'bs._qp[k]=p;'
			. 'return p;'
			. '};'

			// bs._sp: in-place property update on a reactive array item.
			. 'bs._sp=function(arr,id,p){for(var i=0;i<arr.length;i++){if(arr[i].id===id){var ks=Object.keys(p);for(var j=0;j<ks.length;j++){arr[i][ks[j]]=p[ks[j]]}return}}};'

			// bs.mutate: optimistic mutations with auto-rollback.
			. 'bs._tc=0;'
			. 'bs.mutate=function(o){'
			. 'var snap;'
			// Auto mode: state + key + action + optimistic.
			. 'if(o.state&&o.key){'
			. 'snap=o.state[o.key]?o.state[o.key].slice():[];'
			. 'var tid,prev;'
			. 'if(o.action==="create"&&o.optimistic){'
			. 'tid="__temp_"+ ++bs._tc;'
			. 'o.state[o.key]=o.state[o.key].concat([Object.assign({id:tid},o.optimistic)]);'
			. '}else if(o.action==="update"&&o.optimistic){'
			. 'prev={};var ks=Object.keys(o.optimistic);for(var j=0;j<ks.length;j++){var items=o.state[o.key];for(var i=0;i<items.length;i++){if(items[i].id===o.id){prev[ks[j]]=items[i][ks[j]];break}}}'
			. 'bs._sp(o.state[o.key],o.id,o.optimistic);'
			. '}else if(o.action==="delete"){'
			. 'o.state[o.key]=o.state[o.key].filter(function(t){return t.id!==o.id});'
			. '}'
			. 'return o.fn().then(function(r){'
			. 'if(o.action==="create"){if(tid){bs._sp(o.state[o.key],tid,r)}else{o.state[o.key]=o.state[o.key].concat([r])}}'
			. 'else if(o.action==="update"){bs._sp(o.state[o.key],o.id,r)}'
			. 'bs.cache.invalidate(o.key);'
			. 'return r;'
			. '},function(err){'
			. 'if(prev){bs._sp(o.state[o.key],o.id,prev)}'
			. 'else{o.state[o.key]=snap}'
			. 'throw err;'
			. '});'
			. '}'
			// Manual mode: before/onSuccess/onError.
			. 'if(o.before)snap=o.before();'
			. 'return o.fn().then(function(r){'
			. 'if(o.onSuccess)o.onSuccess(r,snap);'
			. 'if(o.invalidate)bs.cache.invalidate(o.invalidate);'
			. 'return r;'
			. '},function(err){'
			. 'if(o.onError)o.onError(err,snap);'
			. 'throw err;'
			. '});'
			. '};';

		// bs.realtime: automatic polling with hash comparison.
		self::load_all();

		if ( ! empty( self::$realtime ) ) {
			$script .= 'bs._rh={};'
				. 'bs._rt=function(c,u,h){'
				. 'function poll(){'
				. 'fetch(u+"?_hash=1",{headers:h}).then(function(r){return r.json()}).then(function(d){'
				. 'var hk=c.block+":"+c.key;'
				. 'if(bs._rh[hk]!==d.hash){'
				. 'bs._rh[hk]=d.hash;'
				. 'fetch(u,{headers:h}).then(function(r){return r.json()}).then(function(rows){'
				. 'bs.cache.set(c.key,rows);'
				. 'import("@wordpress/interactivity").then(function(m){try{m.store(c.block).state[c.key]=rows}catch(e){}}).catch(function(){})'
				. '})'
				. '}'
				. '}).catch(function(){})'
				. '}'
				. 'fetch(u+"?_hash=1",{headers:h}).then(function(r){return r.json()}).then(function(d){'
				. 'bs._rh[c.block+":"+c.key]=d.hash;'
				. 'var tid=setInterval(poll,c.interval);'
				. 'document.addEventListener("visibilitychange",function(){'
				. 'if(document.hidden){clearInterval(tid)}'
				. 'else{poll();tid=setInterval(poll,c.interval)}'
				. '})'
				. '}).catch(function(){})'
				. '};'
				. 'document.addEventListener("DOMContentLoaded",function(){'
				. 'var R=' . wp_json_encode( self::$realtime ) . ';'
				. 'var U="' . $rest_url . '";'
				. 'var n=(window.wpApiSettings&&wpApiSettings.nonce)||"' . $nonce . '";'
				. 'var H={"X-WP-Nonce":n,"X-BS-Token":"' . $bs_token . '"};'
				. 'R.forEach(function(c){'
				. 'if(!document.querySelector(\'[data-wp-interactive="\'+c.block+\'"]\'))return;'
				. 'bs._rt(c,U+c.slug+"/"+c.schema,H)'
				. '})'
				. '});';
		}

		return $script;
	}

	/**
	 * Inject the bs.db() client into the frontend output buffer.
	 *
	 * @param string $html The page HTML.
	 *
	 * @return string The HTML with client script injected.
	 */
	public static function inject_frontend_client( string $html ): string {
		if ( ! self::has_any_schemas() || str_contains( $html, 'id="blockstudio-db"' ) ) {
			return $html;
		}

		$script = '<script id="blockstudio-db">' . self::get_client_script() . '</script>';
		$html   = str_replace( '</head>', $script . '</head>', $html );

		return $html;
	}

	/**
	 * Inject the bs.db() client into the block editor.
	 *
	 * @return void
	 */
	public static function inject_editor_client(): void {
		if ( ! is_admin() || ! self::has_any_schemas() ) {
			return;
		}

		wp_register_script( 'blockstudio-db', false, array(), BLOCKSTUDIO_VERSION, false );
		wp_enqueue_script( 'blockstudio-db' );
		wp_add_inline_script( 'blockstudio-db', self::get_client_script() );
	}

	/**
	 * Check if any blocks have database schemas.
	 *
	 * @return bool Whether any schemas are registered.
	 */
	public static function has_schemas(): bool {
		return self::has_any_schemas();
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
	 * Check if any database schemas exist (internal).
	 *
	 * @return bool Whether any schemas are registered.
	 */
	private static function has_any_schemas(): bool {
		self::load_all();

		return ! empty( self::$schemas );
	}

	/**
	 * Collect realtime config from a schema definition.
	 *
	 * @param array  $schema      The schema definition.
	 * @param string $block_name  The block name.
	 * @param string $schema_name The schema name.
	 *
	 * @return void
	 */
	private static function collect_realtime( array $schema, string $block_name, string $schema_name ): void {
		$rt = $schema['realtime'] ?? false;

		if ( ! $rt ) {
			return;
		}

		if ( true === $rt ) {
			$rt = array();
		}

		self::$realtime[] = array(
			'block'    => $block_name,
			'slug'     => str_replace( '/', '-', $block_name ),
			'schema'   => $schema_name,
			'key'      => $rt['key'] ?? $schema_name,
			'interval' => $rt['interval'] ?? 3000,
		);
	}


	/**
	 * Register REST endpoints for all database schemas.
	 *
	 * @return void
	 */
	public static function register_endpoints(): void {
		self::load_all();

		foreach ( self::$schemas as $key => $schema ) {
			list( $block_name, $schema_name ) = self::parse_key( $key );

			self::ensure_storage( $key, $schema );

			$block_slug = str_replace( '/', '-', $block_name );
			$route_base = '/db/' . $block_slug . '/' . $schema_name;

			register_rest_route(
				'blockstudio/v1',
				$route_base,
				array(
					array(
						'methods'             => 'GET',
						'callback'            => function ( $request ) use ( $key ) {
							return self::handle_list( $key, $request );
						},
						'permission_callback' => function ( $request ) use ( $schema ) {
							return self::check_capability( $schema, 'read', $request );
						},
					),
					array(
						'methods'             => 'POST',
						'callback'            => function ( $request ) use ( $key, $schema ) {
							return self::handle_create( $key, $schema, $request );
						},
						'permission_callback' => function ( $request ) use ( $schema ) {
							return self::check_capability( $schema, 'create', $request );
						},
					),
				)
			);

			register_rest_route(
				'blockstudio/v1',
				$route_base . '/(?P<id>[\d]+)',
				array(
					array(
						'methods'             => 'GET',
						'callback'            => function ( $request ) use ( $key ) {
							return self::handle_get( $key, $request );
						},
						'permission_callback' => function ( $request ) use ( $schema ) {
							return self::check_capability( $schema, 'read', $request );
						},
					),
					array(
						'methods'             => 'PUT',
						'callback'            => function ( $request ) use ( $key, $schema ) {
							return self::handle_update( $key, $schema, $request );
						},
						'permission_callback' => function ( $request ) use ( $schema ) {
							return self::check_capability( $schema, 'update', $request );
						},
					),
					array(
						'methods'             => 'DELETE',
						'callback'            => function ( $request ) use ( $key ) {
							return self::handle_delete( $key, $request );
						},
						'permission_callback' => function ( $request ) use ( $schema ) {
							return self::check_capability( $schema, 'delete', $request );
						},
					),
				)
			);
		}
	}

	/**
	 * Parse a compound key into block name and schema name.
	 *
	 * @param string $key The compound key "block_name:schema_name".
	 *
	 * @return array{0: string, 1: string} Block name and schema name.
	 */
	private static function parse_key( string $key ): array {
		$parts = explode( ':', $key, 2 );
		return array( $parts[0], $parts[1] ?? 'default' );
	}

	/**
	 * Check capability for a CRUD operation.
	 *
	 * Access levels:
	 *   true    - Public with CSRF protection (nonce required).
	 *   'open'  - Truly public, no nonce, no auth (webhooks, external APIs).
	 *   string  - WordPress capability check.
	 *   array   - Any of the listed capabilities.
	 *   null    - Requires authentication (logged-in user).
	 *
	 * @param array            $schema    The database schema.
	 * @param string           $operation The operation (create, read, update, delete).
	 * @param \WP_REST_Request $request   The REST request.
	 *
	 * @return bool|\WP_Error Whether the user has permission.
	 */
	private static function check_capability( array $schema, string $operation, $request = null ) {
		$capabilities = $schema['capability'] ?? array();
		$cap          = $capabilities[ $operation ] ?? null;

		if ( 'open' === $cap ) {
			return true;
		}

		if ( true === $cap ) {
			if ( is_user_logged_in() ) {
				return true;
			}
			if ( $request && ! Csrf::verify_request( $request ) ) {
				return new \WP_Error(
					'blockstudio_db_csrf',
					__( 'Invalid or missing CSRF token.', 'blockstudio' ),
					array( 'status' => 403 )
				);
			}
			return true;
		}

		if ( ! is_user_logged_in() ) {
			return new \WP_Error(
				'blockstudio_db_unauthorized',
				__( 'Authentication required.', 'blockstudio' ),
				array( 'status' => 401 )
			);
		}

		if ( null === $cap ) {
			return true;
		}

		$caps = (array) $cap;

		foreach ( $caps as $c ) {
			if ( current_user_can( $c ) ) {
				return true;
			}
		}

		return new \WP_Error(
			'blockstudio_db_forbidden',
			__( 'Insufficient permissions.', 'blockstudio' ),
			array( 'status' => 403 )
		);
	}

	/**
	 * Validate data against a schema's field definitions.
	 *
	 * @param array $data    The data to validate.
	 * @param array $schema  The database schema.
	 * @param bool  $partial Whether this is a partial update.
	 *
	 * @return true|\WP_Error True if valid, WP_Error otherwise.
	 */
	private static function validate( array $data, array $schema, bool $partial = false ) {
		$fields       = $schema['fields'] ?? array();
		$field_errors = array();

		if ( ! $partial ) {
			foreach ( $fields as $name => $def ) {
				if ( ! empty( $def['required'] ) && ! isset( $data[ $name ] ) ) {
					$field_errors[ $name ][] = 'Required.';
				}
			}
		}

		foreach ( $data as $name => $value ) {
			if ( ! isset( $fields[ $name ] ) ) {
				continue;
			}

			$def  = $fields[ $name ];
			$type = $def['type'] ?? 'string';

			if ( 'string' === $type && ! is_string( $value ) && ! is_numeric( $value ) ) {
				$field_errors[ $name ][] = 'Must be a string.';
				continue;
			}
			if ( 'integer' === $type && ! is_numeric( $value ) ) {
				$field_errors[ $name ][] = 'Must be an integer.';
				continue;
			}
			if ( 'number' === $type && ! is_numeric( $value ) ) {
				$field_errors[ $name ][] = 'Must be a number.';
				continue;
			}
			if ( 'boolean' === $type && ! is_bool( $value ) ) {
				$field_errors[ $name ][] = 'Must be a boolean.';
				continue;
			}
			if ( isset( $def['enum'] ) && ! in_array( $value, $def['enum'], true ) ) {
				$field_errors[ $name ][] = 'Must be one of: ' . implode( ', ', $def['enum'] ) . '.';
			}
			if ( isset( $def['maxLength'] ) && is_string( $value ) && strlen( $value ) > $def['maxLength'] ) {
				$field_errors[ $name ][] = 'Exceeds maximum length of ' . $def['maxLength'] . '.';
			}
			if ( isset( $def['minLength'] ) && is_string( $value ) && strlen( $value ) < $def['minLength'] ) {
				$field_errors[ $name ][] = 'Must be at least ' . $def['minLength'] . ' characters.';
			}
			if ( 'email' === ( $def['format'] ?? '' ) && ! is_email( $value ) ) {
				$field_errors[ $name ][] = 'Must be a valid email address.';
			}
			if ( 'url' === ( $def['format'] ?? '' ) && ! filter_var( $value, FILTER_VALIDATE_URL ) ) {
				$field_errors[ $name ][] = 'Must be a valid URL.';
			}

			if ( isset( $def['validate'] ) && is_callable( $def['validate'] ) ) {
				$custom = call_user_func( $def['validate'], $value, $data );

				if ( true !== $custom && null !== $custom ) {
					$field_errors[ $name ][] = is_string( $custom ) ? $custom : 'Validation failed.';
				}
			}
		}

		if ( ! empty( $field_errors ) ) {
			return new \WP_Error(
				'blockstudio_db_validation',
				__( 'Validation failed.', 'blockstudio' ),
				array(
					'status' => 400,
					'errors' => $field_errors,
				)
			);
		}

		return true;
	}

	/**
	 * Sanitize a single value based on its field definition.
	 *
	 * @param mixed $value The value to sanitize.
	 * @param array $def   The field definition.
	 *
	 * @return mixed The sanitized value.
	 */
	private static function sanitize_value( $value, array $def ) {
		$type = $def['type'] ?? 'string';

		switch ( $type ) {
			case 'integer':
				return (int) $value;
			case 'number':
				return (float) $value;
			case 'boolean':
				return (bool) $value;
			default:
				if ( 'email' === ( $def['format'] ?? '' ) ) {
					return sanitize_email( $value );
				}
				if ( 'url' === ( $def['format'] ?? '' ) ) {
					return esc_url_raw( $value );
				}
				return sanitize_text_field( $value );
		}
	}

	/**
	 * Sanitize data for storage.
	 *
	 * @param array $data   The raw data.
	 * @param array $schema The schema.
	 *
	 * @return array The sanitized data (only known fields).
	 */
	private static function sanitize_data( array $data, array $schema ): array {
		$fields    = $schema['fields'] ?? array();
		$sanitized = array();

		foreach ( $data as $name => $value ) {
			if ( ! isset( $fields[ $name ] ) ) {
				continue;
			}
			$sanitized[ $name ] = self::sanitize_value( $value, $fields[ $name ] );
		}

		return $sanitized;
	}

	// Storage routing.

	/**
	 * Get the storage type for a schema key.
	 *
	 * @param string $key The schema key.
	 *
	 * @return string The storage type (table, meta, jsonc, sqlite).
	 */
	private static function storage_type( string $key ): string {
		return self::$schemas[ $key ]['storage'] ?? 'table';
	}

	/**
	 * Ensure storage exists for a schema.
	 *
	 * @param string $key    The schema key.
	 * @param array  $schema The schema definition.
	 *
	 * @return void
	 */
	private static function ensure_storage( string $key, array $schema ): void {
		$storage = $schema['storage'] ?? 'table';

		if ( 'sqlite' === $storage ) {
			self::sqlite_ensure( $key, $schema );
			return;
		}

		if ( 'table' !== $storage ) {
			return;
		}

		global $wpdb;

		$table   = self::table_name( $key );
		$fields  = $schema['fields'] ?? array();
		$columns = array();

		foreach ( $fields as $name => $def ) {
			$col_type  = self::field_to_column_type( $def );
			$col_name  = sanitize_key( $name );
			$columns[] = "$col_name $col_type";
		}

		$columns_sql = implode( ",\n\t\t\t", $columns );
		$charset     = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE $table (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			$columns_sql,
			created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
			updated_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
			PRIMARY KEY  (id)
		) $charset;";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

	/**
	 * Map a field type to a MySQL column type.
	 *
	 * @param array $def The field definition.
	 *
	 * @return string The MySQL column type.
	 */
	private static function field_to_column_type( array $def ): string {
		$type       = $def['type'] ?? 'string';
		$max_length = $def['maxLength'] ?? 255;

		switch ( $type ) {
			case 'integer':
				return 'bigint(20) DEFAULT 0';
			case 'number':
				return 'decimal(20,6) DEFAULT 0';
			case 'boolean':
				return 'tinyint(1) DEFAULT 0';
			case 'text':
				return 'longtext';
			default:
				return "varchar($max_length) DEFAULT ''";
		}
	}

	/**
	 * Get the table name for a schema key.
	 *
	 * @param string $key The schema key.
	 *
	 * @return string The full table name with prefix.
	 */
	private static function table_name( string $key ): string {
		global $wpdb;
		$safe = str_replace( array( '/', '-', ':' ), '_', $key );
		return $wpdb->prefix . 'bs_' . $safe;
	}

	/**
	 * Get the meta key for a schema key.
	 *
	 * @param string $key The schema key.
	 *
	 * @return string The meta key.
	 */
	private static function meta_key( string $key ): string {
		return '_bs_db_' . str_replace( array( '/', '-', ':' ), '_', $key );
	}

	/**
	 * Get the JSONC file path for a schema key.
	 *
	 * @param string $key The schema key.
	 *
	 * @return string The file path.
	 */
	private static function jsonc_path( string $key ): string {
		list( $block_name, $schema_name ) = self::parse_key( $key );
		$block_path                       = self::$block_paths[ $block_name ] ?? '';

		return dirname( $block_path ) . '/db/' . $schema_name . '.jsonc';
	}

	// REST handlers.

	/**
	 * Handle a list request.
	 *
	 * @param string           $key     The schema key.
	 * @param \WP_REST_Request $request The request.
	 *
	 * @return \WP_REST_Response The response.
	 */
	private static function handle_list( string $key, $request ) {
		$schema    = self::$schemas[ $key ];
		$storage   = self::storage_type( $key );
		$params    = $request->get_query_params();
		$limit     = min( (int) ( $params['limit'] ?? 50 ), 100 );
		$offset    = (int) ( $params['offset'] ?? 0 );
		$hash_only = ! empty( $params['_hash'] );

		unset( $params['limit'], $params['offset'], $params['_hash'] );

		$rows = self::storage_list( $key, $storage, $schema, $params, $limit, $offset );

		if ( $hash_only ) {
			return rest_ensure_response(
				array(
					'hash'  => md5( wp_json_encode( $rows ) ),
					'count' => count( $rows ),
				)
			);
		}

		return rest_ensure_response( $rows );
	}

	/**
	 * Handle a get request.
	 *
	 * @param string           $key     The schema key.
	 * @param \WP_REST_Request $request The request.
	 *
	 * @return \WP_REST_Response|\WP_Error The response.
	 */
	private static function handle_get( string $key, $request ) {
		$storage = self::storage_type( $key );
		$id      = (int) $request->get_param( 'id' );
		$row     = self::storage_get( $key, $storage, $id );

		if ( ! $row ) {
			return new \WP_Error( 'blockstudio_db_not_found', __( 'Record not found.', 'blockstudio' ), array( 'status' => 404 ) );
		}

		return rest_ensure_response( $row );
	}

	/**
	 * Handle a create request.
	 *
	 * @param string           $key     The schema key.
	 * @param array            $schema  The schema.
	 * @param \WP_REST_Request $request The request.
	 *
	 * @return \WP_REST_Response|\WP_Error The response.
	 */
	private static function handle_create( string $key, array $schema, $request ) {
		$data  = $request->get_json_params();
		$valid = self::validate( $data, $schema );

		if ( is_wp_error( $valid ) ) {
			return $valid;
		}

		$data    = self::sanitize_data( $data, $schema );
		$storage = self::storage_type( $key );
		$result  = self::storage_create( $key, $storage, $data );

		return rest_ensure_response( $result );
	}

	/**
	 * Handle an update request.
	 *
	 * @param string           $key     The schema key.
	 * @param array            $schema  The schema.
	 * @param \WP_REST_Request $request The request.
	 *
	 * @return \WP_REST_Response|\WP_Error The response.
	 */
	private static function handle_update( string $key, array $schema, $request ) {
		$id    = (int) $request->get_param( 'id' );
		$data  = $request->get_json_params();
		$valid = self::validate( $data, $schema, true );

		if ( is_wp_error( $valid ) ) {
			return $valid;
		}

		$data    = self::sanitize_data( $data, $schema );
		$storage = self::storage_type( $key );
		$result  = self::storage_update( $key, $storage, $id, $data );

		if ( ! $result ) {
			return new \WP_Error( 'blockstudio_db_not_found', __( 'Record not found.', 'blockstudio' ), array( 'status' => 404 ) );
		}

		return rest_ensure_response( $result );
	}

	/**
	 * Handle a delete request.
	 *
	 * @param string           $key     The schema key.
	 * @param \WP_REST_Request $request The request.
	 *
	 * @return \WP_REST_Response|\WP_Error The response.
	 */
	private static function handle_delete( string $key, $request ) {
		$storage = self::storage_type( $key );
		$id      = (int) $request->get_param( 'id' );
		$deleted = self::storage_delete( $key, $storage, $id );

		if ( ! $deleted ) {
			return new \WP_Error( 'blockstudio_db_not_found', __( 'Record not found.', 'blockstudio' ), array( 'status' => 404 ) );
		}

		return rest_ensure_response(
			array(
				'deleted' => true,
				'id'      => $id,
			)
		);
	}

	/**
	 * Check if a schema is user-scoped.
	 *
	 * @param string $key The schema key.
	 *
	 * @return bool Whether the schema is user-scoped.
	 */
	private static function is_user_scoped( string $key ): bool {
		return ! empty( self::$schemas[ $key ]['userScoped'] );
	}

	/**
	 * Check user-scoped ownership of a record.
	 *
	 * @param string     $key    The schema key.
	 * @param array|null $record The record to check.
	 *
	 * @return bool Whether the current user owns the record.
	 */
	private static function user_owns_record( string $key, $record ): bool {
		if ( ! self::is_user_scoped( $key ) || ! $record ) {
			return true;
		}

		return (int) ( $record['user_id'] ?? 0 ) === get_current_user_id();
	}

	// Storage dispatch.

	/**
	 * List records from any storage backend.
	 *
	 * @param string $key     The schema key.
	 * @param string $storage The storage type.
	 * @param array  $schema  The schema.
	 * @param array  $filters Field equality filters.
	 * @param int    $limit   Maximum rows.
	 * @param int    $offset  Row offset.
	 *
	 * @return array The records.
	 */
	private static function storage_list( string $key, string $storage, array $schema, array $filters, int $limit, int $offset ): array {
		if ( self::is_user_scoped( $key ) ) {
			$filters['user_id'] = (string) get_current_user_id();
		}

		switch ( $storage ) {
			case 'meta':
				return self::meta_list( $key, $schema, $filters, $limit, $offset );
			case 'jsonc':
				return self::jsonc_list( $key, $filters, $limit, $offset );
			case 'sqlite':
				return self::sqlite_list( $key, $schema, $filters, $limit, $offset );
			default:
				return self::table_list( $key, $schema, $filters, $limit, $offset );
		}
	}

	/**
	 * Get a single record from any storage backend.
	 *
	 * @param string $key     The schema key.
	 * @param string $storage The storage type.
	 * @param int    $id      The record ID.
	 *
	 * @return array|null The record or null.
	 */
	private static function storage_get( string $key, string $storage, int $id ) {
		switch ( $storage ) {
			case 'meta':
				$record = self::meta_get( $key, $id );
				break;
			case 'jsonc':
				$record = self::jsonc_get( $key, $id );
				break;
			case 'sqlite':
				$record = self::sqlite_get( $key, $id );
				break;
			default:
				$record = self::table_get( $key, $id );
		}

		if ( ! self::user_owns_record( $key, $record ) ) {
			return null;
		}

		return $record;
	}

	/**
	 * Create a record in any storage backend.
	 *
	 * @param string $key     The schema key.
	 * @param string $storage The storage type.
	 * @param array  $data    The sanitized data.
	 *
	 * @return array The created record.
	 */
	private static function storage_create( string $key, string $storage, array $data ): array {
		if ( self::is_user_scoped( $key ) ) {
			$data['user_id'] = get_current_user_id();
		}

		list( $block, $schema ) = self::parse_key( $key );

		do_action(
			'blockstudio/db/before_create',
			array(
				'block'   => $block,
				'schema'  => $schema,
				'data'    => $data,
				'storage' => $storage,
			)
		);

		switch ( $storage ) {
			case 'meta':
				$record = self::meta_create( $key, $data );
				break;
			case 'jsonc':
				$record = self::jsonc_create( $key, $data );
				break;
			case 'sqlite':
				$record = self::sqlite_create( $key, $data );
				break;
			default:
				$record = self::table_create( $key, $data );
		}

		do_action(
			'blockstudio/db/after_create',
			array(
				'block'   => $block,
				'schema'  => $schema,
				'record'  => $record,
				'storage' => $storage,
			)
		);

		return $record;
	}

	/**
	 * Update a record in any storage backend.
	 *
	 * @param string $key     The schema key.
	 * @param string $storage The storage type.
	 * @param int    $id      The record ID.
	 * @param array  $data    The sanitized data.
	 *
	 * @return array|null The updated record or null.
	 */
	private static function storage_update( string $key, string $storage, int $id, array $data ) {
		if ( self::is_user_scoped( $key ) ) {
			$existing = self::storage_get( $key, $storage, $id );
			if ( ! $existing ) {
				return null;
			}
			unset( $data['user_id'] );
		}

		list( $block, $schema ) = self::parse_key( $key );

		do_action(
			'blockstudio/db/before_update',
			array(
				'block'   => $block,
				'schema'  => $schema,
				'id'      => $id,
				'data'    => $data,
				'storage' => $storage,
			)
		);

		switch ( $storage ) {
			case 'meta':
				$record = self::meta_update( $key, $id, $data );
				break;
			case 'jsonc':
				$record = self::jsonc_update( $key, $id, $data );
				break;
			case 'sqlite':
				$record = self::sqlite_update( $key, $id, $data );
				break;
			default:
				$record = self::table_update( $key, $id, $data );
		}

		if ( $record ) {
			do_action(
				'blockstudio/db/after_update',
				array(
					'block'   => $block,
					'schema'  => $schema,
					'id'      => $id,
					'record'  => $record,
					'storage' => $storage,
				)
			);
		}

		return $record;
	}

	/**
	 * Delete a record from any storage backend.
	 *
	 * @param string $key     The schema key.
	 * @param string $storage The storage type.
	 * @param int    $id      The record ID.
	 *
	 * @return bool Whether the record was deleted.
	 */
	private static function storage_delete( string $key, string $storage, int $id ): bool {
		if ( self::is_user_scoped( $key ) ) {
			$existing = self::storage_get( $key, $storage, $id );
			if ( ! $existing ) {
				return false;
			}
		}

		list( $block, $schema ) = self::parse_key( $key );

		do_action(
			'blockstudio/db/before_delete',
			array(
				'block'   => $block,
				'schema'  => $schema,
				'id'      => $id,
				'storage' => $storage,
			)
		);

		switch ( $storage ) {
			case 'meta':
				$deleted = self::meta_delete( $key, $id );
				break;
			case 'jsonc':
				$deleted = self::jsonc_delete( $key, $id );
				break;
			case 'sqlite':
				$deleted = self::sqlite_delete( $key, $id );
				break;
			default:
				$deleted = self::table_delete( $key, $id );
		}

		if ( $deleted ) {
			do_action(
				'blockstudio/db/after_delete',
				array(
					'block'   => $block,
					'schema'  => $schema,
					'id'      => $id,
					'storage' => $storage,
				)
			);
		}

		return $deleted;
	}

	// Table storage.

	/**
	 * List rows from a custom table.
	 *
	 * @param string $key     The schema key.
	 * @param array  $schema  The schema.
	 * @param array  $filters Field equality filters.
	 * @param int    $limit   Maximum rows.
	 * @param int    $offset  Row offset.
	 *
	 * @return array The rows.
	 */
	private static function table_list( string $key, array $schema, array $filters, int $limit, int $offset ): array {
		global $wpdb;

		$table  = self::table_name( $key );
		$fields = array_keys( $schema['fields'] ?? array() );
		$where  = array();
		$values = array();

		foreach ( $filters as $k => $val ) {
			if ( in_array( $k, $fields, true ) ) {
				$where[]  = sanitize_key( $k ) . ' = %s';
				$values[] = $val;
			}
		}

		$sql = "SELECT * FROM $table"; // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		if ( ! empty( $where ) ) {
			$sql .= ' WHERE ' . implode( ' AND ', $where );
		}

		$sql     .= ' ORDER BY id DESC LIMIT %d OFFSET %d';
		$values[] = $limit;
		$values[] = $offset;

		return $wpdb->get_results( $wpdb->prepare( $sql, $values ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared
	}

	/**
	 * Get a single row from a custom table.
	 *
	 * @param string $key The schema key.
	 * @param int    $id  The row ID.
	 *
	 * @return array|null The row or null.
	 */
	private static function table_get( string $key, int $id ) {
		global $wpdb;
		$table = self::table_name( $key );

		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE id = %d", $id ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	/**
	 * Insert a row into a custom table.
	 *
	 * @param string $key  The schema key.
	 * @param array  $data The sanitized data.
	 *
	 * @return array The created row.
	 */
	private static function table_create( string $key, array $data ): array {
		global $wpdb;
		$table              = self::table_name( $key );
		$now                = current_time( 'mysql', true );
		$data['created_at'] = $now;
		$data['updated_at'] = $now;

		$wpdb->insert( $table, $data ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery

		return self::table_get( $key, (int) $wpdb->insert_id );
	}

	/**
	 * Update a row in a custom table.
	 *
	 * @param string $key  The schema key.
	 * @param int    $id   The row ID.
	 * @param array  $data The sanitized data.
	 *
	 * @return array|null The updated row or null.
	 */
	private static function table_update( string $key, int $id, array $data ) {
		global $wpdb;
		$table              = self::table_name( $key );
		$data['updated_at'] = current_time( 'mysql', true );

		$wpdb->update( $table, $data, array( 'id' => $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching

		return self::table_get( $key, $id );
	}

	/**
	 * Delete a row from a custom table.
	 *
	 * @param string $key The schema key.
	 * @param int    $id  The row ID.
	 *
	 * @return bool Whether the row was deleted.
	 */
	private static function table_delete( string $key, int $id ): bool {
		global $wpdb;
		$table = self::table_name( $key );

		return (bool) $wpdb->delete( $table, array( 'id' => $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
	}

	// Meta storage.

	/**
	 * List entries from post meta.
	 *
	 * @param string $key     The schema key.
	 * @param array  $schema  The schema.
	 * @param array  $filters Field equality filters.
	 * @param int    $limit   Maximum entries.
	 * @param int    $offset  Entry offset.
	 *
	 * @return array The entries.
	 */
	private static function meta_list( string $key, array $schema, array $filters, int $limit, int $offset ): array {
		$entries = self::meta_all( $key );

		if ( ! empty( $filters ) ) {
			$entries = array_filter(
				$entries,
				function ( $entry ) use ( $filters ) {
					foreach ( $filters as $k => $val ) {
						if ( ( $entry[ $k ] ?? null ) !== $val ) {
							return false;
						}
					}
					return true;
				}
			);
		}

		return array_values( array_slice( $entries, $offset, $limit ) );
	}

	/**
	 * Get a single entry from post meta.
	 *
	 * @param string $key The schema key.
	 * @param int    $id  The entry ID.
	 *
	 * @return array|null The entry or null.
	 */
	private static function meta_get( string $key, int $id ) {
		foreach ( self::meta_all( $key ) as $entry ) {
			if ( ( $entry['id'] ?? 0 ) === $id ) {
				return $entry;
			}
		}
		return null;
	}

	/**
	 * Create an entry in post meta.
	 *
	 * @param string $key  The schema key.
	 * @param array  $data The sanitized data.
	 *
	 * @return array The created entry.
	 */
	private static function meta_create( string $key, array $data ): array {
		$schema   = self::$schemas[ $key ];
		$meta_key = self::meta_key( $key );
		$post_id  = $schema['postId'] ?? 0;
		$entries  = self::meta_all( $key );

		$max_id = 0;
		foreach ( $entries as $entry ) {
			$max_id = max( $max_id, $entry['id'] ?? 0 );
		}

		$data['id']         = $max_id + 1;
		$data['created_at'] = current_time( 'mysql', true );
		$data['updated_at'] = current_time( 'mysql', true );
		$entries[]          = $data;

		update_post_meta( $post_id, $meta_key, $entries );

		return $data;
	}

	/**
	 * Update an entry in post meta.
	 *
	 * @param string $key  The schema key.
	 * @param int    $id   The entry ID.
	 * @param array  $data The sanitized data.
	 *
	 * @return array|null The updated entry or null.
	 */
	private static function meta_update( string $key, int $id, array $data ) {
		$schema   = self::$schemas[ $key ];
		$meta_key = self::meta_key( $key );
		$post_id  = $schema['postId'] ?? 0;
		$entries  = self::meta_all( $key );
		$found    = false;

		foreach ( $entries as &$entry ) {
			if ( ( $entry['id'] ?? 0 ) === $id ) {
				$entry               = array_merge( $entry, $data );
				$entry['updated_at'] = current_time( 'mysql', true );
				$found               = $entry;
				break;
			}
		}
		unset( $entry );

		if ( ! $found ) {
			return null;
		}

		update_post_meta( $post_id, $meta_key, $entries );

		return $found;
	}

	/**
	 * Delete an entry from post meta.
	 *
	 * @param string $key The schema key.
	 * @param int    $id  The entry ID.
	 *
	 * @return bool Whether the entry was deleted.
	 */
	private static function meta_delete( string $key, int $id ): bool {
		$schema   = self::$schemas[ $key ];
		$meta_key = self::meta_key( $key );
		$post_id  = $schema['postId'] ?? 0;
		$entries  = self::meta_all( $key );
		$count    = count( $entries );

		$entries = array_values(
			array_filter(
				$entries,
				fn( $entry ) => ( $entry['id'] ?? 0 ) !== $id
			)
		);

		if ( count( $entries ) === $count ) {
			return false;
		}

		update_post_meta( $post_id, $meta_key, $entries );

		return true;
	}

	/**
	 * Get all meta entries for a schema.
	 *
	 * @param string $key The schema key.
	 *
	 * @return array All entries.
	 */
	private static function meta_all( string $key ): array {
		$schema   = self::$schemas[ $key ];
		$meta_key = self::meta_key( $key );
		$post_id  = $schema['postId'] ?? 0;
		$entries  = get_post_meta( $post_id, $meta_key, true );

		return is_array( $entries ) ? $entries : array();
	}

	// JSONC file storage.

	/**
	 * Read all records from a JSONC file.
	 *
	 * @param string $key The schema key.
	 *
	 * @return array All records with IDs.
	 */
	private static function jsonc_read( string $key ): array {
		$path = self::jsonc_path( $key );

		if ( ! file_exists( $path ) ) {
			return array();
		}

		$content = file_get_contents( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		$lines   = explode( "\n", $content );
		$records = array();
		$id      = 0;

		foreach ( $lines as $line ) {
			$line = trim( $line );

			if ( '' === $line || str_starts_with( $line, '//' ) ) {
				continue;
			}

			$record = json_decode( $line, true );

			if ( is_array( $record ) ) {
				++$id;
				$record['id'] = $record['id'] ?? $id;
				$id           = max( $id, $record['id'] );
				$records[]    = $record;
			}
		}

		return $records;
	}

	/**
	 * Write all records to a JSONC file.
	 *
	 * @param string $key     The schema key.
	 * @param array  $records The records to write.
	 *
	 * @return void
	 */
	private static function jsonc_write( string $key, array $records ): void {
		$path = self::jsonc_path( $key );
		$dir  = dirname( $path );

		if ( ! is_dir( $dir ) ) {
			wp_mkdir_p( $dir );
		}

		$lines = array();

		foreach ( $records as $record ) {
			$lines[] = wp_json_encode( $record, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
		}

		file_put_contents( $path, implode( "\n", $lines ) . "\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
	}

	/**
	 * List records from JSONC file.
	 *
	 * @param string $key     The schema key.
	 * @param array  $filters Field equality filters.
	 * @param int    $limit   Maximum records.
	 * @param int    $offset  Record offset.
	 *
	 * @return array The records.
	 */
	private static function jsonc_list( string $key, array $filters, int $limit, int $offset ): array {
		$records = self::jsonc_read( $key );

		if ( ! empty( $filters ) ) {
			$records = array_filter(
				$records,
				function ( $record ) use ( $filters ) {
					foreach ( $filters as $k => $val ) {
						if ( ( $record[ $k ] ?? null ) != $val ) { // phpcs:ignore Universal.Operators.StrictComparisons.LooseNotEqual -- Intentional loose comparison for query param strings.
							return false;
						}
					}
					return true;
				}
			);
		}

		return array_values( array_slice( $records, $offset, $limit ) );
	}

	/**
	 * Get a single record from JSONC file.
	 *
	 * @param string $key The schema key.
	 * @param int    $id  The record ID.
	 *
	 * @return array|null The record or null.
	 */
	private static function jsonc_get( string $key, int $id ) {
		foreach ( self::jsonc_read( $key ) as $record ) {
			if ( ( $record['id'] ?? 0 ) === $id ) {
				return $record;
			}
		}
		return null;
	}

	/**
	 * Create a record in JSONC file.
	 *
	 * @param string $key  The schema key.
	 * @param array  $data The sanitized data.
	 *
	 * @return array The created record.
	 */
	private static function jsonc_create( string $key, array $data ): array {
		$records = self::jsonc_read( $key );

		$max_id = 0;
		foreach ( $records as $record ) {
			$max_id = max( $max_id, $record['id'] ?? 0 );
		}

		$data['id']         = $max_id + 1;
		$data['created_at'] = current_time( 'mysql', true );
		$data['updated_at'] = current_time( 'mysql', true );
		$records[]          = $data;

		self::jsonc_write( $key, $records );

		return $data;
	}

	/**
	 * Update a record in JSONC file.
	 *
	 * @param string $key  The schema key.
	 * @param int    $id   The record ID.
	 * @param array  $data The sanitized data.
	 *
	 * @return array|null The updated record or null.
	 */
	private static function jsonc_update( string $key, int $id, array $data ) {
		$records = self::jsonc_read( $key );
		$found   = false;

		foreach ( $records as &$record ) {
			if ( ( $record['id'] ?? 0 ) === $id ) {
				$record               = array_merge( $record, $data );
				$record['updated_at'] = current_time( 'mysql', true );
				$found                = $record;
				break;
			}
		}
		unset( $record );

		if ( ! $found ) {
			return null;
		}

		self::jsonc_write( $key, $records );

		return $found;
	}

	/**
	 * Delete a record from JSONC file.
	 *
	 * @param string $key The schema key.
	 * @param int    $id  The record ID.
	 *
	 * @return bool Whether the record was deleted.
	 */
	private static function jsonc_delete( string $key, int $id ): bool {
		$records = self::jsonc_read( $key );
		$count   = count( $records );

		$records = array_values(
			array_filter(
				$records,
				fn( $record ) => ( $record['id'] ?? 0 ) !== $id
			)
		);

		if ( count( $records ) === $count ) {
			return false;
		}

		self::jsonc_write( $key, $records );

		return true;
	}

	// SQLite storage.

	/**
	 * SQLite PDO connections keyed by file path.
	 *
	 * @var array<string, \PDO>
	 */
	private static array $sqlite_connections = array();

	/**
	 * Get the SQLite database file path for a schema key.
	 *
	 * @param string $key The schema key.
	 *
	 * @return string The file path.
	 */
	private static function sqlite_path( string $key ): string {
		list( $block_name, $schema_name ) = self::parse_key( $key );
		$block_path                       = self::$block_paths[ $block_name ] ?? '';

		return dirname( $block_path ) . '/db/' . $schema_name . '.sqlite';
	}

	/**
	 * Get or create a SQLite PDO connection.
	 *
	 * @param string $key The schema key.
	 *
	 * @return \PDO The PDO instance.
	 */
	private static function sqlite_pdo( string $key ): \PDO {
		$path = self::sqlite_path( $key );

		if ( isset( self::$sqlite_connections[ $path ] ) ) {
			return self::$sqlite_connections[ $path ];
		}

		$dir = dirname( $path );
		if ( ! is_dir( $dir ) ) {
			wp_mkdir_p( $dir );
		}

		// phpcs:disable WordPress.DB.RestrictedClasses.mysql__PDO -- SQLite via PDO, not MySQL.
		$pdo = new \PDO( 'sqlite:' . $path );
		$pdo->setAttribute( \PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION );
		$pdo->setAttribute( \PDO::ATTR_DEFAULT_FETCH_MODE, \PDO::FETCH_ASSOC );
		$pdo->exec( 'PRAGMA journal_mode=WAL' );
		// phpcs:enable WordPress.DB.RestrictedClasses.mysql__PDO

		self::$sqlite_connections[ $path ] = $pdo;

		return $pdo;
	}

	/**
	 * Map a field type to a SQLite column type.
	 *
	 * @param array $def The field definition.
	 *
	 * @return string The SQLite column type.
	 */
	private static function sqlite_column_type( array $def ): string {
		$type = $def['type'] ?? 'string';

		switch ( $type ) {
			case 'integer':
				return 'INTEGER DEFAULT 0';
			case 'number':
				return 'REAL DEFAULT 0';
			case 'boolean':
				return 'INTEGER DEFAULT 0';
			default:
				return "TEXT DEFAULT ''";
		}
	}

	/**
	 * Ensure the SQLite table exists for a schema.
	 *
	 * @param string $key    The schema key.
	 * @param array  $schema The schema definition.
	 *
	 * @return void
	 */
	private static function sqlite_ensure( string $key, array $schema ): void {
		$pdo     = self::sqlite_pdo( $key );
		$fields  = $schema['fields'] ?? array();
		$columns = array();

		foreach ( $fields as $name => $def ) {
			$col_type  = self::sqlite_column_type( $def );
			$col_name  = preg_replace( '/[^a-z0-9_]/', '', $name );
			$columns[] = "$col_name $col_type";
		}

		$columns_sql = implode( ', ', $columns );

		$pdo->exec(
			"CREATE TABLE IF NOT EXISTS data (
				id INTEGER PRIMARY KEY AUTOINCREMENT,
				$columns_sql,
				created_at TEXT DEFAULT (datetime('now')),
				updated_at TEXT DEFAULT (datetime('now'))
			)"
		);

		// Add missing columns (like dbDelta for SQLite).
		$existing = array();
		$info     = $pdo->query( 'PRAGMA table_info(data)' );

		foreach ( $info as $col ) {
			$existing[] = $col['name'];
		}

		foreach ( $fields as $name => $def ) {
			$col_name = preg_replace( '/[^a-z0-9_]/', '', $name );

			if ( ! in_array( $col_name, $existing, true ) ) {
				$col_type = self::sqlite_column_type( $def );
				$pdo->exec( "ALTER TABLE data ADD COLUMN $col_name $col_type" );
			}
		}
	}

	/**
	 * List rows from SQLite.
	 *
	 * @param string $key     The schema key.
	 * @param array  $schema  The schema.
	 * @param array  $filters Field equality filters.
	 * @param int    $limit   Maximum rows.
	 * @param int    $offset  Row offset.
	 *
	 * @return array The rows.
	 */
	private static function sqlite_list( string $key, array $schema, array $filters, int $limit, int $offset ): array {
		$pdo    = self::sqlite_pdo( $key );
		$fields = array_keys( $schema['fields'] ?? array() );
		$where  = array();
		$values = array();

		foreach ( $filters as $k => $val ) {
			if ( in_array( $k, $fields, true ) ) {
				$where[]  = "$k = ?";
				$values[] = $val;
			}
		}

		$sql = 'SELECT * FROM data';

		if ( ! empty( $where ) ) {
			$sql .= ' WHERE ' . implode( ' AND ', $where );
		}

		$sql     .= ' ORDER BY id DESC LIMIT ? OFFSET ?';
		$values[] = $limit;
		$values[] = $offset;

		$stmt = $pdo->prepare( $sql );
		$stmt->execute( $values );

		return $stmt->fetchAll();
	}

	/**
	 * Get a single row from SQLite.
	 *
	 * @param string $key The schema key.
	 * @param int    $id  The row ID.
	 *
	 * @return array|null The row or null.
	 */
	private static function sqlite_get( string $key, int $id ) {
		$pdo  = self::sqlite_pdo( $key );
		$stmt = $pdo->prepare( 'SELECT * FROM data WHERE id = ?' );
		$stmt->execute( array( $id ) );
		$row = $stmt->fetch();

		return false !== $row ? $row : null;
	}

	/**
	 * Insert a row into SQLite.
	 *
	 * @param string $key  The schema key.
	 * @param array  $data The sanitized data.
	 *
	 * @return array The created row.
	 */
	private static function sqlite_create( string $key, array $data ): array {
		$pdo = self::sqlite_pdo( $key );
		$now = gmdate( 'Y-m-d H:i:s' );

		$data['created_at'] = $now;
		$data['updated_at'] = $now;

		$columns      = implode( ', ', array_keys( $data ) );
		$placeholders = implode( ', ', array_fill( 0, count( $data ), '?' ) );

		$stmt = $pdo->prepare( "INSERT INTO data ($columns) VALUES ($placeholders)" );
		$stmt->execute( array_values( $data ) );

		return self::sqlite_get( $key, (int) $pdo->lastInsertId() );
	}

	/**
	 * Update a row in SQLite.
	 *
	 * @param string $key  The schema key.
	 * @param int    $id   The row ID.
	 * @param array  $data The sanitized data.
	 *
	 * @return array|null The updated row or null.
	 */
	private static function sqlite_update( string $key, int $id, array $data ) {
		$pdo                = self::sqlite_pdo( $key );
		$data['updated_at'] = gmdate( 'Y-m-d H:i:s' );

		$sets   = array();
		$values = array();

		foreach ( $data as $col => $val ) {
			$sets[]   = "$col = ?";
			$values[] = $val;
		}

		$values[] = $id;
		$sql      = 'UPDATE data SET ' . implode( ', ', $sets ) . ' WHERE id = ?';
		$stmt     = $pdo->prepare( $sql );
		$stmt->execute( $values );

		return self::sqlite_get( $key, $id );
	}

	/**
	 * Delete a row from SQLite.
	 *
	 * @param string $key The schema key.
	 * @param int    $id  The row ID.
	 *
	 * @return bool Whether the row was deleted.
	 */
	private static function sqlite_delete( string $key, int $id ): bool {
		$pdo  = self::sqlite_pdo( $key );
		$stmt = $pdo->prepare( 'DELETE FROM data WHERE id = ?' );
		$stmt->execute( array( $id ) );

		return $stmt->rowCount() > 0;
	}

	// Discovery.

	/**
	 * Load all db.php definitions from discovered blocks.
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
			self::load_block_schema( $block_name, $block_data );
		}

		self::$schemas = apply_filters( 'blockstudio/database', self::$schemas );
	}

	/**
	 * Load db.php schema(s) for a single block.
	 *
	 * Supports single schema (has 'fields' key) and multiple schemas
	 * (associative array of named schemas).
	 *
	 * @param string $block_name The block name.
	 * @param array  $block_data The block data from registry.
	 *
	 * @return void
	 */
	private static function load_block_schema( string $block_name, array $block_data ): void {
		$files_paths = $block_data['filesPaths'] ?? array();
		$db_path     = false;

		foreach ( $files_paths as $path ) {
			if ( str_ends_with( $path, '/db.php' ) ) {
				$db_path = $path;
				break;
			}
		}

		if ( ! $db_path || ! file_exists( $db_path ) ) {
			return;
		}

		self::$block_paths[ $block_name ] = $block_data['path'] ?? '';

		$definition = include $db_path;

		if ( ! is_array( $definition ) ) {
			return;
		}

		if ( isset( $definition['fields'] ) ) {
			self::inject_user_scoped_field( $definition );
			self::$schemas[ $block_name . ':default' ] = $definition;
			self::register_inline_hooks( $definition, $block_name, 'default' );
			self::collect_realtime( $definition, $block_name, 'default' );
			return;
		}

		foreach ( $definition as $schema_name => $schema ) {
			if ( is_array( $schema ) && isset( $schema['fields'] ) ) {
				self::inject_user_scoped_field( $schema );
				self::$schemas[ $block_name . ':' . $schema_name ] = $schema;
				self::register_inline_hooks( $schema, $block_name, $schema_name );
				self::collect_realtime( $schema, $block_name, $schema_name );
			}
		}
	}

	/**
	 * Inject user_id field into user-scoped schemas.
	 *
	 * @param array $schema The schema definition (passed by reference).
	 *
	 * @return void
	 */
	private static function inject_user_scoped_field( array &$schema ): void {
		if ( empty( $schema['userScoped'] ) ) {
			return;
		}

		if ( ! isset( $schema['fields']['user_id'] ) ) {
			$schema['fields']['user_id'] = array(
				'type'    => 'integer',
				'default' => 0,
			);
		}
	}

	/**
	 * Register inline hooks defined in a schema's 'hooks' key.
	 *
	 * @param array  $schema      The schema definition.
	 * @param string $block_name  The block name.
	 * @param string $schema_name The schema name.
	 *
	 * @return void
	 */
	private static function register_inline_hooks( array $schema, string $block_name, string $schema_name ): void {
		$hooks = $schema['hooks'] ?? array();

		foreach ( $hooks as $hook_name => $callback ) {
			if ( ! is_callable( $callback ) ) {
				continue;
			}

			add_action(
				'blockstudio/db/' . $hook_name,
				function ( $params ) use ( $callback, $block_name, $schema_name ) {
					if ( ( $params['block'] ?? '' ) === $block_name && ( $params['schema'] ?? '' ) === $schema_name ) {
						call_user_func( $callback, $params );
					}
				}
			);
		}
	}

	/**
	 * Get all registered schemas.
	 *
	 * @return array<string, array> Schemas keyed by "block_name:schema_name".
	 */
	public static function get_all(): array {
		self::load_all();

		return self::$schemas;
	}

	/**
	 * Execute a storage operation directly (used by the Db PHP API).
	 *
	 * @param string $operation The operation (create, list, get, update, delete).
	 * @param string $key       The schema key.
	 * @param array  $args      Operation-specific arguments.
	 *
	 * @return mixed The result.
	 */
	public static function execute( string $operation, string $key, array $args = array() ) {
		self::load_all();

		if ( ! isset( self::$schemas[ $key ] ) ) {
			return 'get' === $operation ? null : ( 'list' === $operation ? array() : false );
		}

		$schema  = self::$schemas[ $key ];
		$storage = $schema['storage'] ?? 'table';

		switch ( $operation ) {
			case 'create':
				$valid = self::validate( $args['data'] ?? array(), $schema );
				if ( is_wp_error( $valid ) ) {
					return $valid;
				}
				$data = self::sanitize_data( $args['data'] ?? array(), $schema );
				return self::storage_create( $key, $storage, $data );

			case 'list':
				return self::storage_list(
					$key,
					$storage,
					$schema,
					$args['filters'] ?? array(),
					$args['limit'] ?? 50,
					$args['offset'] ?? 0
				);

			case 'get':
				return self::storage_get( $key, $storage, $args['id'] ?? 0 );

			case 'update':
				$valid = self::validate( $args['data'] ?? array(), $schema, true );
				if ( is_wp_error( $valid ) ) {
					return $valid;
				}
				$data = self::sanitize_data( $args['data'] ?? array(), $schema );
				return self::storage_update( $key, $storage, $args['id'] ?? 0, $data );

			case 'delete':
				return self::storage_delete( $key, $storage, $args['id'] ?? 0 );

			default:
				return false;
		}
	}
}
