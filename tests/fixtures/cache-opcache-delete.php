<?php
/**
 * Isolated OPcache cleanup probe. The namespaced spy must not leak into PHPUnit.
 *
 * @package Blockstudio
 */

namespace Blockstudio;

function opcache_invalidate( string $file, bool $force ): bool {
	$result = \opcache_invalidate( $file, $force );
	$GLOBALS['cache_invalidations'][] = array( $file, $force, is_file( $file ), $result );
	return $result;
}

$mode      = $argv[1];
$directory = $argv[2];
$GLOBALS['cache_invalidations'] = array();

if ( in_array( $mode, array( 'runtime', 'build' ), true ) ) {
	$_SERVER['HTTP_HOST']   = 'localhost:8888';
	$_SERVER['REQUEST_URI'] = '/';
	require $argv[3];

	add_filter( 'blockstudio/cache/dir', static fn(): string => $directory );
	add_filter( 'blockstudio/cache/max_files_per_scope', static fn(): int => 1 );
	$directory = Runtime_Cache::directory( 'opcache-probe' );
} else {
	define( 'DAY_IN_SECONDS', 86400 );
	require __DIR__ . '/../../includes/classes/runtime-cache.php';
}

mkdir( $directory, 0777, true );
$file = $directory . '/retired.php';
$keep = $directory . '/current.php';
file_put_contents( $file, '<?php return "retired";' );
file_put_contents( $keep, '<?php return "current";' );
file_put_contents( $directory . '/data.json', '{}' );
$compiled = function_exists( 'opcache_compile_file' ) && \opcache_compile_file( $file );
$cached   = function_exists( 'opcache_is_script_cached' ) && \opcache_is_script_cached( $file );
$GLOBALS['cache_invalidations'] = array();

switch ( $mode ) {
	case 'runtime':
		Runtime_Cache::prune( 'opcache-probe', $keep );
		break;
	case 'build':
		( new \ReflectionMethod( Build_Cache::class, 'prune_scope' ) )->invoke( null, 'opcache-probe', $keep );
		break;
	case 'batch':
		( new \ReflectionMethod( Runtime_Cache::class, 'delete_tree_batch' ) )->invoke( null, $directory, 100 );
		break;
	default:
		( new \ReflectionMethod( Runtime_Cache::class, 'delete_tree' ) )->invoke( null, $directory );
}

echo json_encode(
	array(
		'compiled'      => $compiled,
		'cachedBefore'  => $cached,
		'cachedAfter'   => function_exists( 'opcache_is_script_cached' ) && \opcache_is_script_cached( $file ),
		'deleted'       => ! is_file( $file ),
		'invalidations' => $GLOBALS['cache_invalidations'],
	),
	JSON_THROW_ON_ERROR
);
