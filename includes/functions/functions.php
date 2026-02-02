<?php
/**
 * Blockstudio public API functions.
 *
 * @package Blockstudio
 */

use Blockstudio\Render;
use Blockstudio\Build;

/**
 * Render block.
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
 * Get block.
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
 * Render block.
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
				// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Reading local icon file.
				$data = json_decode( file_get_contents( $complete_path ), true );

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
