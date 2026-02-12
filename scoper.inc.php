<?php
/**
 * PHP-Scoper configuration.
 *
 * @package Blockstudio
 */

declare(strict_types=1);

use Isolated\Symfony\Component\Finder\Finder;

return array(
	'prefix'   => 'BlockstudioVendor',

	'finders'  => array(
		Finder::create()
			->files()
			->in( 'vendor/scssphp' ),
		Finder::create()
			->files()
			->in( 'vendor/matthiasmullie' ),
		Finder::create()
			->files()
			->in( 'vendor/league' ),
		Finder::create()
			->files()
			->in( 'vendor/symfony' ),
		Finder::create()
			->files()
			->in( 'vendor/tailwindphp' ),
		Finder::create()
			->files()
			->in( 'vendor/yahnis-elsts' ),
	),

	'patchers' => array(
		function ( string $file_path, string $prefix, string $content ): string {
			$content = str_replace(
				'implements \\ScssPhp\\ScssPhp\\',
				'implements \\' . $prefix . '\\ScssPhp\\ScssPhp\\',
				$content
			);

			return $content;
		},
	),
);
