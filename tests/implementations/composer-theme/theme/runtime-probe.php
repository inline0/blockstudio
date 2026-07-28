<?php
/**
 * Composer-theme probe for the generic Blockstudio runtime.
 *
 * @package Blockstudio
 */

use Blockstudio\Link_Preload;
use Blockstudio\Media;
use Blockstudio\Media_Metadata;
use Blockstudio\Media_Metadata_Builder;
use Blockstudio\Pages;
use Blockstudio\Patterns;
use Blockstudio\Performance_Measurement;
use Blockstudio\Runtime_Settings;
use Blockstudio\Settings;
use Blockstudio\Theme_Defaults;
use Blockstudio\WordPress_Optimizations;

/**
 * Fail the implementation probe with a useful message.
 *
 * @param bool   $condition Condition.
 * @param string $message   Failure message.
 *
 * @return void
 * @throws RuntimeException When the assertion fails.
 */
function blockstudio_runtime_probe_assert( bool $condition, string $message ): void {
	if ( ! $condition ) {
		throw new RuntimeException( $message );
	}
}

$runtime    = Runtime_Settings::current();
$theme_root = get_stylesheet_directory();

blockstudio_runtime_probe_assert( array() === Settings::errors(), 'blockstudio.json must load without errors.' );
blockstudio_runtime_probe_assert( 'speed' === Settings::get_string( 'performance/profile' ), 'Typed settings must read the runtime profile.' );
blockstudio_runtime_probe_assert( 'speed' === $runtime->value( 'profile' ), 'The speed profile must resolve.' );
blockstudio_runtime_probe_assert( $runtime->enabled( 'wordpress/headNoise' ), 'The speed profile must enable head cleanup.' );
blockstudio_runtime_probe_assert( ! $runtime->enabled( 'wordpress/frontendAssets' ), 'Explicit settings must override profile values.' );
blockstudio_runtime_probe_assert( 'intent' === $runtime->value( 'preload/links' ), 'The speed profile must enable intent preload.' );
blockstudio_runtime_probe_assert( $runtime->enabled( 'media/lazy' ), 'The speed profile must enable lazy media.' );
blockstudio_runtime_probe_assert( $runtime->enabled( 'measurement/enabled' ), 'Explicit measurement settings must resolve.' );

$builder = new Media_Metadata_Builder();
$builder->write( $theme_root, false );
$metadata = Media_Metadata::theme_asset( $theme_root, 'assets/probe.svg' );
blockstudio_runtime_probe_assert( 48 === (int) ( $metadata['width'] ?? 0 ), 'Media metadata must include SVG width.' );
blockstudio_runtime_probe_assert( 32 === (int) ( $metadata['height'] ?? 0 ), 'Media metadata must include SVG height.' );

$image = bs_media_image(
	array(
		'src' => 'assets/probe.svg',
		'alt' => 'Runtime probe',
	)
);
blockstudio_runtime_probe_assert( str_contains( $image, 'data-blockstudio-media' ), 'Media helper must use the generic lazy runtime.' );
blockstudio_runtime_probe_assert( str_contains( $image, 'width="48"' ), 'Media helper must apply manifest dimensions.' );

blockstudio_runtime_probe_assert( 'text-sm px-4' === bs_tw_merge( 'px-2 text-sm', 'px-4' ), 'Tailwind merge helper must use the bundled engine.' );
$variants = bs_tw_variants(
	array(
		'base'     => 'button',
		'variants' => array(
			'size' => array(
				'sm' => 'h-8',
				'lg' => 'h-12',
			),
		),
	)
);
blockstudio_runtime_probe_assert( 'button h-12' === $variants( array( 'size' => 'lg' ) ), 'Tailwind variants helper must compose variants.' );

blockstudio_runtime_probe_assert( current_theme_supports( 'title-tag' ), 'Configured theme defaults must enable title-tag support.' );
blockstudio_runtime_probe_assert(
	false !== has_filter( 'site_transient_update_themes', array( Theme_Defaults::class, 'suppress_directory_updates' ) ),
	'Configured theme update suppression must register.'
);
blockstudio_runtime_probe_assert(
	false !== has_action( 'wp_enqueue_scripts', array( Media::class, 'enqueue' ) ),
	'Lazy media assets must register.'
);
blockstudio_runtime_probe_assert(
	false !== has_action( 'wp_enqueue_scripts', array( Link_Preload::class, 'enqueue' ) ),
	'Intent preload assets must register.'
);
blockstudio_runtime_probe_assert( Performance_Measurement::enabled(), 'Measurement API must report its configured state.' );
blockstudio_runtime_probe_assert(
	false !== has_filter( 'xmlrpc_enabled', array( WordPress_Optimizations::class, 'return_false' ) ),
	'Generic WordPress optimizations must register for the speed profile.'
);
blockstudio_runtime_probe_assert( is_callable( array( Pages::class, 'force_sync_all' ) ), 'Existing Pages API must remain the page runtime.' );
blockstudio_runtime_probe_assert( method_exists( Patterns::class, 'init' ), 'Existing Patterns API must remain the pattern runtime.' );

echo 'blockstudio-runtime-ok';
