<?php
/**
 * CLI class.
 *
 * WP-CLI commands for Blockstudio.
 *
 * @package Blockstudio
 */

namespace Blockstudio;

/**
 * Manage Blockstudio blocks, database, RPC, and cron from the command line.
 *
 * @since 7.1.0
 */
class Cli {

	/**
	 * Register all commands.
	 *
	 * @return void
	 */
	public static function register(): void {
		\WP_CLI::add_command( 'blockstudio blocks', array( __CLASS__, 'blocks' ) );
		\WP_CLI::add_command( 'blockstudio db', array( __CLASS__, 'db' ) );
		\WP_CLI::add_command( 'blockstudio rpc', array( __CLASS__, 'rpc' ) );
		\WP_CLI::add_command( 'blockstudio cron', array( __CLASS__, 'cron' ) );
		\WP_CLI::add_command( 'blockstudio settings', array( __CLASS__, 'settings' ) );
	}

	/**
	 * List all registered Blockstudio blocks.
	 *
	 * ## OPTIONS
	 *
	 * [--format=<format>]
	 * : Output format.
	 * ---
	 * default: table
	 * options:
	 *   - table
	 *   - json
	 *   - csv
	 * ---
	 *
	 * [--components]
	 * : Only show component blocks.
	 *
	 * ## EXAMPLES
	 *
	 *     wp blockstudio blocks list
	 *     wp blockstudio blocks list --format=json
	 *     wp blockstudio blocks list --components
	 *
	 * @subcommand list
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 *
	 * @return void
	 */
	public static function blocks( $args, $assoc_args ) {
		$subcommand = $args[0] ?? 'list';

		if ( 'list' !== $subcommand ) {
			\WP_CLI::error( "Unknown subcommand: $subcommand" );
			return;
		}

		$blocks          = Build::blocks();
		$format          = $assoc_args['format'] ?? 'table';
		$components_only = isset( $assoc_args['components'] );
		$rows            = array();

		foreach ( $blocks as $block ) {
			$is_component = ! empty( $block->blockstudio['component'] );

			if ( $components_only && ! $is_component ) {
				continue;
			}

			$rows[] = array(
				'name'      => $block->name,
				'title'     => $block->title ?? '',
				'component' => $is_component ? 'yes' : '',
				'category'  => $block->category ?? '',
			);
		}

		if ( empty( $rows ) ) {
			\WP_CLI::log( 'No blocks found.' );
			return;
		}

		\WP_CLI\Utils\format_items( $format, $rows, array( 'name', 'title', 'component', 'category' ) );
	}

	/**
	 * Manage database records.
	 *
	 * ## EXAMPLES
	 *
	 *     wp blockstudio db list my-theme/app subscribers
	 *     wp blockstudio db get my-theme/app subscribers 1
	 *     wp blockstudio db create my-theme/app subscribers --email=a@b.com --plan=pro
	 *     wp blockstudio db update my-theme/app subscribers 1 --plan=enterprise
	 *     wp blockstudio db delete my-theme/app subscribers 1
	 *     wp blockstudio db schemas
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 *
	 * @return void
	 */
	public static function db( $args, $assoc_args ) {
		$subcommand = $args[0] ?? '';

		if ( 'schemas' === $subcommand ) {
			self::db_schemas( $assoc_args );
			return;
		}

		$block  = $args[1] ?? '';
		$schema = $args[2] ?? 'default';

		if ( empty( $block ) ) {
			\WP_CLI::error( 'Block name required. Usage: wp blockstudio db <command> <block> [schema]' );
			return;
		}

		$db = Db::get( $block, $schema );

		if ( ! $db ) {
			\WP_CLI::error( "Schema not found: $block:$schema" );
			return;
		}

		switch ( $subcommand ) {
			case 'list':
				$format  = $assoc_args['format'] ?? 'table';
				$filters = array_diff_key( $assoc_args, array_flip( array( 'format', 'limit', 'offset' ) ) );
				$limit   = (int) ( $assoc_args['limit'] ?? 50 );
				$offset  = (int) ( $assoc_args['offset'] ?? 0 );
				$rows    = $db->list( $filters, $limit, $offset );

				if ( empty( $rows ) ) {
					\WP_CLI::log( 'No records found.' );
					return;
				}

				\WP_CLI\Utils\format_items( $format, $rows, array_keys( $rows[0] ) );
				break;

			case 'get':
				$id     = (int) ( $args[3] ?? 0 );
				$record = $db->get_record( $id );

				if ( ! $record ) {
					\WP_CLI::error( "Record $id not found." );
					return;
				}

				foreach ( $record as $key => $value ) {
					\WP_CLI::log( "$key: $value" );
				}
				break;

			case 'create':
				$data   = array_diff_key( $assoc_args, array_flip( array( 'format' ) ) );
				$result = $db->create( $data );

				if ( is_wp_error( $result ) ) {
					\WP_CLI::error( $result->get_error_message() );
					return;
				}

				\WP_CLI::success( 'Record created (ID: ' . $result['id'] . ').' );
				break;

			case 'update':
				$id     = (int) ( $args[3] ?? 0 );
				$data   = array_diff_key( $assoc_args, array_flip( array( 'format' ) ) );
				$result = $db->update( $id, $data );

				if ( is_wp_error( $result ) ) {
					\WP_CLI::error( $result->get_error_message() );
					return;
				}

				if ( ! $result ) {
					\WP_CLI::error( "Record $id not found." );
					return;
				}

				\WP_CLI::success( "Record $id updated." );
				break;

			case 'delete':
				$id      = (int) ( $args[3] ?? 0 );
				$deleted = $db->delete( $id );

				if ( ! $deleted ) {
					\WP_CLI::error( "Record $id not found." );
					return;
				}

				\WP_CLI::success( "Record $id deleted." );
				break;

			default:
				\WP_CLI::error( "Unknown subcommand: $subcommand. Use: list, get, create, update, delete, schemas" );
		}
	}

	/**
	 * List all registered database schemas.
	 *
	 * @param array $assoc_args Associative arguments.
	 *
	 * @return void
	 */
	private static function db_schemas( $assoc_args ) {
		$schemas = Database::get_all();
		$format  = $assoc_args['format'] ?? 'table';
		$rows    = array();

		foreach ( $schemas as $key => $schema ) {
			list( $block, $name ) = explode( ':', $key, 2 );
			$fields               = array_keys( $schema['fields'] ?? array() );

			$rows[] = array(
				'block'   => $block,
				'schema'  => $name,
				'storage' => $schema['storage'] ?? 'table',
				'fields'  => implode( ', ', $fields ),
			);
		}

		if ( empty( $rows ) ) {
			\WP_CLI::log( 'No schemas found.' );
			return;
		}

		\WP_CLI\Utils\format_items( $format, $rows, array( 'block', 'schema', 'storage', 'fields' ) );
	}

	/**
	 * Call an RPC function.
	 *
	 * ## EXAMPLES
	 *
	 *     wp blockstudio rpc call my-theme/app subscribe --email=a@b.com
	 *     wp blockstudio rpc list
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 *
	 * @return void
	 */
	public static function rpc( $args, $assoc_args ) {
		$subcommand = $args[0] ?? '';

		if ( 'list' === $subcommand ) {
			self::rpc_list( $assoc_args );
			return;
		}

		if ( 'call' !== $subcommand ) {
			\WP_CLI::error( "Unknown subcommand: $subcommand. Use: call, list" );
			return;
		}

		$block    = $args[1] ?? '';
		$function = $args[2] ?? '';

		if ( empty( $block ) || empty( $function ) ) {
			\WP_CLI::error( 'Usage: wp blockstudio rpc call <block> <function> [--param=value]' );
			return;
		}

		$params = array_diff_key( $assoc_args, array_flip( array( 'format' ) ) );
		$result = Rpc::call( $block, $function, $params );

		if ( is_wp_error( $result ) ) {
			\WP_CLI::error( $result->get_error_message() );
			return;
		}

		\WP_CLI::log( wp_json_encode( $result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) );
	}

	/**
	 * List all registered RPC functions.
	 *
	 * @param array $assoc_args Associative arguments.
	 *
	 * @return void
	 */
	private static function rpc_list( $assoc_args ) {
		$all    = Rpc::get_all();
		$format = $assoc_args['format'] ?? 'table';
		$rows   = array();

		foreach ( $all as $block => $functions ) {
			foreach ( $functions as $name => $fn ) {
				$rows[] = array(
					'block'      => $block,
					'function'   => $name,
					'public'     => ! empty( $fn['public'] ) ? 'yes' : '',
					'capability' => is_array( $fn['capability'] ?? null )
						? implode( ', ', $fn['capability'] )
						: ( $fn['capability'] ?? '' ),
					'methods'    => implode( ', ', $fn['methods'] ?? array( 'POST' ) ),
				);
			}
		}

		if ( empty( $rows ) ) {
			\WP_CLI::log( 'No RPC functions found.' );
			return;
		}

		\WP_CLI\Utils\format_items( $format, $rows, array( 'block', 'function', 'public', 'capability', 'methods' ) );
	}

	/**
	 * Manage cron jobs.
	 *
	 * ## EXAMPLES
	 *
	 *     wp blockstudio cron list
	 *     wp blockstudio cron run my-theme/app cleanup
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 *
	 * @return void
	 */
	public static function cron( $args, $assoc_args ) {
		$subcommand = $args[0] ?? 'list';

		if ( 'list' === $subcommand ) {
			self::cron_list( $assoc_args );
			return;
		}

		if ( 'run' !== $subcommand ) {
			\WP_CLI::error( "Unknown subcommand: $subcommand. Use: list, run" );
			return;
		}

		$block = $args[1] ?? '';
		$job   = $args[2] ?? '';

		if ( empty( $block ) || empty( $job ) ) {
			\WP_CLI::error( 'Usage: wp blockstudio cron run <block> <job>' );
			return;
		}

		$all = Cron::get_all();

		if ( ! isset( $all[ $block ][ $job ] ) ) {
			\WP_CLI::error( "Job not found: $block/$job" );
			return;
		}

		$callback = $all[ $block ][ $job ]['callback'];

		if ( ! is_callable( $callback ) ) {
			\WP_CLI::error( 'Job callback is not callable.' );
			return;
		}

		\WP_CLI::log( "Running $block/$job..." );
		call_user_func( $callback );
		\WP_CLI::success( 'Done.' );
	}

	/**
	 * List all registered cron jobs.
	 *
	 * @param array $assoc_args Associative arguments.
	 *
	 * @return void
	 */
	private static function cron_list( $assoc_args ) {
		$all    = Cron::get_all();
		$format = $assoc_args['format'] ?? 'table';
		$rows   = array();

		foreach ( $all as $block => $jobs ) {
			foreach ( $jobs as $name => $job ) {
				$hook = 'blockstudio_cron_' . str_replace( array( '/', '-' ), '_', $block ) . '_' . $name;
				$next = wp_next_scheduled( $hook );

				$rows[] = array(
					'block'    => $block,
					'job'      => $name,
					'schedule' => $job['schedule'] ?? 'daily',
					'next_run' => $next ? gmdate( 'Y-m-d H:i:s', $next ) : 'not scheduled',
				);
			}
		}

		if ( empty( $rows ) ) {
			\WP_CLI::log( 'No cron jobs found.' );
			return;
		}

		\WP_CLI\Utils\format_items( $format, $rows, array( 'block', 'job', 'schedule', 'next_run' ) );
	}

	/**
	 * Show Blockstudio settings.
	 *
	 * ## EXAMPLES
	 *
	 *     wp blockstudio settings list
	 *     wp blockstudio settings get tailwind/enabled
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 *
	 * @return void
	 */
	public static function settings( $args, $assoc_args ) {
		$subcommand = $args[0] ?? 'list';

		if ( 'get' === $subcommand ) {
			$key   = $args[1] ?? '';
			$value = Settings::get( $key );
			\WP_CLI::log( is_scalar( $value ) ? (string) $value : wp_json_encode( $value, JSON_PRETTY_PRINT ) );
			return;
		}

		if ( 'list' !== $subcommand ) {
			\WP_CLI::error( "Unknown subcommand: $subcommand. Use: list, get" );
			return;
		}

		$all = Settings::get_all();
		\WP_CLI::log( wp_json_encode( $all, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) );
	}
}
