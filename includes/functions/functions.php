<?php
/**
 * Blockstudio public API functions.
 *
 * @package Blockstudio
 */

use Blockstudio\Db;
use Blockstudio\Render;
use Blockstudio\Build;
use Blockstudio\Pages;
use Blockstudio\Site_Templates;
use Blockstudio\Field_Type_Registry;
use Blockstudio\Utils;
use Blockstudio\Media;

/**
 * Render a Blockstudio block as frontend-resolved HTML.
 *
 * When called inside another block's editor preview, embedded blocks still
 * render through the frontend path so pseudo-components like RichText and
 * InnerBlocks are resolved before output.
 *
 * @since 2.1.2
 *
 * @param string|array $value Block name or configuration array.
 *
 * @return false|string|void Returns HTML string, false on failure, or void when echoing.
 */
function blockstudio_render_block( $value ) {
	return Render::block( $value );
}

/**
 * Get a Blockstudio block as frontend-resolved HTML.
 *
 * When called inside another block's editor preview, embedded blocks still
 * render through the frontend path so pseudo-components like RichText and
 * InnerBlocks are resolved before output.
 *
 * @since 2.1.2
 *
 * @param string|array $value Block name or configuration array.
 *
 * @return string|false The block content or false on failure.
 *
 * @phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- Public API function.
 */
function bs_block( $value ) {
	// phpcs:enable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound
	ob_start();
	Render::block( $value );
	$content = ob_get_contents();
	ob_end_clean();

	return $content;
}

/**
 * Render a Blockstudio block as frontend-resolved HTML.
 *
 * When called inside another block's editor preview, embedded blocks still
 * render through the frontend path so pseudo-components like RichText and
 * InnerBlocks are resolved before output.
 *
 * @since 2.1.2
 *
 * @param string|array $value Block name or configuration array.
 *
 * @return false|string|void Returns HTML string, false on failure, or void when echoing.
 *
 * @phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- Public API function.
 */
function bs_render_block( $value ) {
	// phpcs:enable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound
	return Render::block( $value );
}

/**
 * Render a database schema's field components as a form.
 *
 * @since 7.1.0
 *
 * @param string $block_name  The block name.
 * @param string $schema_name The schema name (default: "default").
 *
 * @return void
 *
 * @phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- Public API function.
 */
function bs_db_form( string $block_name, string $schema_name = 'default' ) {
	// phpcs:enable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound
	$db = Db::get( $block_name, $schema_name );

	if ( ! $db ) {
		return;
	}

	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Block render handles escaping.
	echo $db->form();
}

/**
 * Get icon.
 *
 * @since 2.1.2
 *
 * @param array $args Icon arguments with 'set', 'subSet', and 'icon' keys.
 *
 * @return string|false The icon SVG content or false on failure.
 *
 * @phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- Public API function.
 */
function bs_icon( $args ) {
	// phpcs:enable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound
	ob_start();
	bs_render_icon( $args );
	$content = ob_get_contents();
	ob_end_clean();

	return $content;
}

/**
 * Render icon.
 *
 * @since 2.1.2
 *
 * @param array $args Icon arguments with 'set', 'subSet', and 'icon' keys.
 *
 * @return void
 *
 * @phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- Public API function.
 */
function bs_render_icon( $args ) {
	// phpcs:enable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound
	$path            = BLOCKSTUDIO_DIR . '/includes/icons';
	$icon_version    = '1';
	$expiration_time = 30 * DAY_IN_SECONDS;

	$set     = $args['set'];
	$sub_set = isset( $args['subSet'] ) ? '-' . $args['subSet'] : '';
	$icon    = $args['icon'];

	$complete_path = "$path/$set$sub_set.json";

	$set_icon_transient_key =
		'blockstudio_' . $icon_version . '_icon_set_' . md5( "$set$sub_set" );

	$icon_transient_key =
		'blockstudio_' . $icon_version . '_icon_' . md5( "$set$sub_set$icon" );

	$icon_data = get_transient( $icon_transient_key );

	if ( false === $icon_data ) {
		$data = get_transient( $set_icon_transient_key );

		if ( false === $data ) {
			if ( file_exists( $complete_path ) ) {
				$data = Utils::read_json_file( $complete_path );

				set_transient( $set_icon_transient_key, $data, $expiration_time );
			}
		}

		if ( $data && isset( $data[ $icon . '.svg' ] ) ) {
			$icon_data = $data[ $icon . '.svg' ];
			set_transient( $icon_transient_key, $icon_data, $expiration_time );
		}
	}

	if ( $icon_data ) {
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- SVG icon data is trusted.
		echo $icon_data;
	}
}

/**
 * Get attributes.
 *
 * @since 5.5.0
 *
 * @param mixed $data    The data to convert to attributes.
 * @param array $allowed Allowed attribute names.
 *
 * @return string The attributes string.
 *
 * @phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- Public API function.
 */
function bs_attributes( $data, array $allowed = array() ): string {
	// phpcs:enable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound
	return Blockstudio\Utils::attributes( $data, $allowed );
}

/**
 * Render attributes.
 *
 * @since 4.2.0
 *
 * @param mixed $data    The data to convert to attributes.
 * @param array $allowed Allowed attribute names.
 *
 * @return void
 *
 * @phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- Public API function.
 */
function bs_render_attributes( $data, array $allowed = array() ) {
	// phpcs:enable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Utils::attributes handles escaping.
	echo Blockstudio\Utils::attributes( $data, $allowed );
}

/**
 * Get variables.
 *
 * @since 5.5.0
 *
 * @param mixed $data    The data to convert to CSS variables.
 * @param array $allowed Allowed variable names.
 *
 * @return string The CSS variables string.
 *
 * @phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- Public API function.
 */
function bs_variables( $data, array $allowed = array() ): string {
	// phpcs:enable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound
	return Blockstudio\Utils::attributes( $data, $allowed, true );
}

/**
 * Render variables.
 *
 * @since 4.2.0
 *
 * @param mixed $data    The data to convert to CSS variables.
 * @param array $allowed Allowed variable names.
 *
 * @return void
 *
 * @phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- Public API function.
 */
function bs_render_variables( $data, array $allowed = array() ) {
	// phpcs:enable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Utils::attributes handles escaping.
	echo Blockstudio\Utils::attributes( $data, $allowed, true );
}

/**
 * Register a custom Blockstudio field type.
 *
 * @since 7.5.0
 *
 * @param string $name       Namespaced field type name, for example "acme/dimensions".
 * @param array  $definition Field type definition.
 *
 * @return bool Whether registration succeeded.
 *
 * @phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- Public API function.
 */
function bs_register_field_type( string $name, array $definition ): bool {
	// phpcs:enable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound
	return Field_Type_Registry::instance()->register( $name, $definition );
}

/**
 * Unregister a custom Blockstudio field type.
 *
 * @since 7.5.0
 *
 * @param string $name Namespaced field type name.
 *
 * @return bool Whether the field type existed.
 *
 * @phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- Public API function.
 */
function bs_unregister_field_type( string $name ): bool {
	// phpcs:enable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound
	return Field_Type_Registry::instance()->unregister( $name );
}

/**
 * Get data attributes.
 *
 * @since 5.6.0
 *
 * @param mixed $data The data to convert to data attributes.
 *
 * @return string The data attributes string.
 *
 * @phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- Public API function.
 */
function bs_data_attributes( $data ): string {
	// phpcs:enable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound
	return Blockstudio\Utils::data_attributes( $data );
}

/**
 * Get render data attributes.
 *
 * @since 5.6.0
 *
 * @param mixed $data The data to convert to data attributes.
 *
 * @return void
 *
 * @phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- Public API function.
 */
function bs_render_data_attributes( $data ) {
	// phpcs:enable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Utils::data_attributes handles escaping.
	echo Blockstudio\Utils::data_attributes( $data );
}

/**
 * Get group.
 *
 * @since 2.6.0
 *
 * @param array  $attributes The block attributes.
 * @param string $name       The group name.
 *
 * @return array The group data.
 *
 * @phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- Public API function.
 */
function bs_get_group( $attributes, $name ): array {
	// phpcs:enable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound
	return Blockstudio\Field::group( $attributes, $name );
}

/**
 * Get scoped ID.
 *
 * @since 2.7.0
 *
 * @param string $name The block name.
 *
 * @return string The scoped class name.
 *
 * @phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- Public API function.
 */
function bs_get_scoped_class( $name ): string {
	// phpcs:enable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound
	$blocks = Build::data();

	return isset( $blocks[ $name ] ) ? $blocks[ $name ]['scopedClass'] : '';
}

/**
 * Get registered file-based pages.
 *
 * @since 7.3.4
 *
 * @param string|null $collection Optional collection slug.
 *
 * @return array Registered pages.
 */
function blockstudio_pages( ?string $collection = null ): array {
	return Pages::pages( $collection );
}

/**
 * Get a nested file-based page tree.
 *
 * @since 7.3.4
 *
 * @param string|null $collection Optional collection slug.
 *
 * @return array Page tree.
 */
function blockstudio_page_tree( ?string $collection = null ): array {
	return Pages::tree( $collection );
}

/**
 * Get direct child pages.
 *
 * @since 7.3.4
 *
 * @param string      $name       Page name or registry key.
 * @param string|null $collection Optional collection slug.
 *
 * @return array Child pages.
 */
function blockstudio_page_children( string $name, ?string $collection = null ): array {
	return Pages::children( $name, $collection );
}

/**
 * Get collection metadata.
 *
 * @since 7.3.4
 *
 * @param string $collection Collection slug.
 *
 * @return array|null Collection data.
 */
function blockstudio_page_collection( string $collection ): ?array {
	return Pages::collection( $collection );
}

/**
 * Get the current layout outlet content.
 *
 * @since 7.3.4
 *
 * @return string Current page content.
 */
function blockstudio_page_content(): string {
	return Pages::page_content();
}

/**
 * Get the current Blockstudio page data.
 *
 * @since 7.3.4
 *
 * @return array|null Current page data.
 */
function blockstudio_current_page(): ?array {
	return Pages::current_page();
}

/**
 * Get file-backed Site Editor templates.
 *
 * @since 7.5.0
 *
 * @return array Registered templates.
 */
function blockstudio_site_templates(): array {
	return Site_Templates::templates();
}

/**
 * Get file-backed Site Editor template parts.
 *
 * @since 7.5.0
 *
 * @return array Registered template parts.
 */
function blockstudio_site_template_parts(): array {
	return Site_Templates::parts();
}

/**
 * Get a file-backed Site Editor template.
 *
 * @since 7.5.0
 *
 * @param string $slug Template slug.
 *
 * @return array|null Template data.
 */
function blockstudio_site_template( string $slug ): ?array {
	return Site_Templates::get_template( $slug );
}

/**
 * Get a file-backed Site Editor template part.
 *
 * @since 7.5.0
 *
 * @param string $slug Template part slug.
 *
 * @return array|null Template part data.
 */
function blockstudio_site_template_part( string $slug ): ?array {
	return Site_Templates::get_part( $slug );
}

/**
 * Merge Tailwind class values with Blockstudio's bundled TailwindPHP runtime.
 *
 * @since 7.6.0
 *
 * @param mixed ...$classes Class strings, arrays, and conditional values.
 *
 * @return string Merged class string.
 *
 * @phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- Public API function.
 */
function bs_tw_merge( mixed ...$classes ): string {
	// phpcs:enable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound
	blockstudio_load_tailwindphp();

	return \BlockstudioVendor\TailwindPHP\merge( ...$classes );
}

/**
 * Create a CVA-style Tailwind variant composer.
 *
 * @since 7.6.0
 *
 * @param array<string, mixed> $config Variant configuration.
 *
 * @return callable Variant composer.
 *
 * @phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- Public API function.
 */
function bs_tw_variants( array $config ): callable {
	// phpcs:enable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound
	blockstudio_load_tailwindphp();

	return \BlockstudioVendor\TailwindPHP\variants( $config );
}

/**
 * Load the bundled TailwindPHP function API once.
 *
 * @since 7.6.0
 *
 * @return void
 */
function blockstudio_load_tailwindphp(): void {
	static $loaded = false;

	if ( $loaded ) {
		return;
	}

	$loaded = true;
	require_once BLOCKSTUDIO_DIR . '/lib/tailwindphp-autoload.php';
}

/**
 * Render a stable Blockstudio image.
 *
 * @since 7.6.0
 *
 * @param array<string, mixed> $args Image arguments.
 *
 * @return string Image markup.
 *
 * @phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- Public API function.
 */
function bs_media_image( array $args ): string {
	// phpcs:enable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound
	return Media::image( $args );
}
