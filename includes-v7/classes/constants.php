<?php
/**
 * Constants class.
 *
 * @package Blockstudio
 */

namespace Blockstudio;

/**
 * Centralized configuration constants for Blockstudio.
 *
 * This class provides a single location for all magic strings and configuration
 * values used throughout the plugin. Using constants instead of hardcoded strings:
 *
 * - Prevents typos and inconsistencies
 * - Makes refactoring easier (change in one place)
 * - Provides IDE autocompletion
 * - Documents available configuration options
 *
 * Categories:
 * - Block namespace and API version
 * - File and folder names (block.json, index.php, _dist, etc.)
 * - Asset prefixes and suffixes (admin-, -editor, -inline, etc.)
 * - Settings paths for configuration lookup
 * - Hook and filter names
 * - Character replacements for ID sanitization
 *
 * @since 7.0.0
 */
final class Constants {

	/**
	 * Block namespace prefix.
	 *
	 * @var string
	 */
	public const BLOCK_NAMESPACE = 'blockstudio';

	/**
	 * Characters to replace in attribute IDs.
	 *
	 * @var array<string>
	 */
	public const ATTR_CHAR_REPLACEMENTS = array(
		'{',
		'}',
		'[',
		']',
		'"',
		'/',
		' ',
		':',
		',',
		'\\',
	);

	/**
	 * Group separator for nested attributes.
	 *
	 * @var string
	 */
	public const ATTR_GROUP_SEPARATOR = '_';

	/**
	 * Settings path for asset enqueue.
	 *
	 * @var string
	 */
	public const SETTINGS_PATH_ENQUEUE = 'assets/enqueue';

	/**
	 * Settings path for SCSS processing.
	 *
	 * @var string
	 */
	public const SETTINGS_PATH_SCSS = 'assets/process/scssFiles';

	/**
	 * Settings path for CSS minification.
	 *
	 * @var string
	 */
	public const SETTINGS_PATH_MINIFY_CSS = 'assets/minify/css';

	/**
	 * Settings path for JS minification.
	 *
	 * @var string
	 */
	public const SETTINGS_PATH_MINIFY_JS = 'assets/minify/js';

	/**
	 * Distribution folder name.
	 *
	 * @var string
	 */
	public const DIST_FOLDER = '_dist';

	/**
	 * Modules folder name.
	 *
	 * @var string
	 */
	public const MODULES_FOLDER = 'modules';

	/**
	 * Block JSON filename.
	 *
	 * @var string
	 */
	public const BLOCK_JSON_FILENAME = 'block.json';

	/**
	 * PHP template filename.
	 *
	 * @var string
	 */
	public const INDEX_PHP_FILENAME = 'index.php';

	/**
	 * Blade template filename.
	 *
	 * @var string
	 */
	public const INDEX_BLADE_FILENAME = 'index.blade.php';

	/**
	 * Twig template filename.
	 *
	 * @var string
	 */
	public const INDEX_TWIG_FILENAME = 'index.twig';

	/**
	 * Template file extensions.
	 *
	 * @var array<string>
	 */
	public const TEMPLATE_EXTENSIONS = array(
		'.php',
		'.blade.php',
		'.twig',
	);

	/**
	 * Asset file extensions for CSS.
	 *
	 * @var array<string>
	 */
	public const CSS_EXTENSIONS = array(
		'.css',
		'.scss',
	);

	/**
	 * Asset file extensions for JS.
	 *
	 * @var array<string>
	 */
	public const JS_EXTENSIONS = array(
		'.js',
	);

	/**
	 * Admin asset prefix.
	 *
	 * @var string
	 */
	public const ASSET_PREFIX_ADMIN = 'admin';

	/**
	 * Block editor asset prefix.
	 *
	 * @var string
	 */
	public const ASSET_PREFIX_BLOCK_EDITOR = 'block-editor';

	/**
	 * Global asset prefix.
	 *
	 * @var string
	 */
	public const ASSET_PREFIX_GLOBAL = 'global';

	/**
	 * Editor asset suffix.
	 *
	 * @var string
	 */
	public const ASSET_SUFFIX_EDITOR = '-editor';

	/**
	 * Inline asset suffix.
	 *
	 * @var string
	 */
	public const ASSET_SUFFIX_INLINE = '-inline';

	/**
	 * Scoped asset suffix.
	 *
	 * @var string
	 */
	public const ASSET_SUFFIX_SCOPED = '-scoped';

	/**
	 * Hook name for initialization.
	 *
	 * @var string
	 */
	public const HOOK_INIT = 'blockstudio_init';

	/**
	 * Hook name for before initialization.
	 *
	 * @var string
	 */
	public const HOOK_INIT_BEFORE = 'blockstudio/init/before';

	/**
	 * Hook name for after initialization.
	 *
	 * @var string
	 */
	public const HOOK_INIT_AFTER = 'blockstudio/init';

	/**
	 * Filter name for block attributes.
	 *
	 * @var string
	 */
	public const FILTER_BLOCK_ATTRIBUTES = 'blockstudio/blocks/attributes';

	/**
	 * Filter name for asset enable.
	 *
	 * @var string
	 */
	public const FILTER_ASSETS_ENABLE = 'blockstudio/assets/enable';

	/**
	 * Default API version for blocks.
	 *
	 * @var int
	 */
	public const BLOCK_API_VERSION = 3;

	/**
	 * Hash ID length for generated IDs.
	 *
	 * @var int
	 */
	public const HASH_ID_LENGTH = 12;
}
