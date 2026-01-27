<?php

use Blockstudio\Render;
use Blockstudio\Build;

/**
 * Render block.
 *
 * @date   10/02/2022
 * @since  2.1.2
 */
function blockstudio_render_block($value)
{
    return Render::block($value);
}

/**
 * Get block.
 *
 * @date   10/02/2022
 * @since  2.1.2
 */
function bs_block($value)
{
    ob_start();
    Render::block($value);
    $content = ob_get_contents();
    ob_end_clean();

    return $content;
}

/**
 * Render block.
 *
 * @date   10/02/2022
 * @since  2.1.2
 */
function bs_render_block($value)
{
    return Render::block($value);
}

/**
 * Get icon.
 *
 * @date   10/02/2022
 * @since  2.1.2
 */
function bs_icon($args)
{
    ob_start();
    bs_render_icon($args);
    $content = ob_get_contents();
    ob_end_clean();

    return $content;
}

/**
 * Render icon.
 *
 * @date   10/02/2022
 * @since  2.1.2
 */
function bs_render_icon($args)
{
    $path = BLOCKSTUDIO_DIR . '/includes/icons';
    $iconVersion = '1';
    $expirationTime = 30 * DAY_IN_SECONDS;

    $set = $args['set'];
    $subSet = isset($args['subSet']) ? '-' . $args['subSet'] : '';
    $icon = $args['icon'];

    $completePath = "$path/$set$subSet.json";

    $setIconTransientKey =
        'blockstudio_' . $iconVersion . '_icon_set_' . md5("$set$subSet");

    $iconTransientKey =
        'blockstudio_' . $iconVersion . '_icon_' . md5("$set$subSet$icon");

    $iconData = get_transient($iconTransientKey);

    if (false === $iconData) {
        $data = get_transient($setIconTransientKey);

        if (false === $data) {
            if (file_exists($completePath)) {
                $data = json_decode(file_get_contents($completePath), true);

                set_transient($setIconTransientKey, $data, $expirationTime);
            }
        }

        if ($data && isset($data[$icon . '.svg'])) {
            $iconData = $data[$icon . '.svg'];
            set_transient($iconTransientKey, $iconData, $expirationTime);
        }
    }

    if ($iconData) {
        echo $iconData;
    }
}

/**
 * Get attributes.
 *
 * @date   11/04/2024
 * @since  5.5.0
 *
 * @param  $data
 * @param  array  $allowed
 *
 * @return string
 */
function bs_attributes($data, array $allowed = []): string
{
    return Blockstudio\Utils::attributes($data, $allowed);
}

/**
 * Render attributes.
 *
 * @date   07/04/2023
 * @since  4.2.0
 *
 * @param  $data
 * @param  array  $allowed
 */
function bs_render_attributes($data, array $allowed = [])
{
    echo Blockstudio\Utils::attributes($data, $allowed);
}

/**
 * Get variables.
 *
 * @date   11/04/2024
 * @since  5.5.0
 *
 * @param  $data
 * @param  array  $allowed
 *
 * @return string
 */

function bs_variables($data, array $allowed = []): string
{
    return Blockstudio\Utils::attributes($data, $allowed, true);
}

/**
 * Render variables.
 *
 * @date   07/04/2023
 * @since  4.2.0
 *
 * @param  $data
 * @param  array  $allowed
 */
function bs_render_variables($data, array $allowed = [])
{
    echo Blockstudio\Utils::attributes($data, $allowed, true);
}

/**
 * Get data attributes.
 *
 * @date   15/08/2024
 * @since  5.6.0
 *
 * @param  $data
 *
 * @return string
 */

function bs_data_attributes($data): string
{
    return Blockstudio\Utils::dataAttributes($data);
}

/**
 * Get render data attributes.
 *
 * @date   15/08/2024
 * @since  5.6.0
 *
 * @param  $data
 */

function bs_render_data_attributes($data)
{
    echo Blockstudio\Utils::dataAttributes($data);
}

/**
 * Get group.
 *
 * @date   21/08/2022
 * @since  2.6.0
 */
function bs_get_group($attributes, $name): array
{
    return Blockstudio\Field::group($attributes, $name);
}

/**
 * Get scoped ID.
 *
 * @date   28/08/2022
 * @since  2.7.0
 */
function bs_get_scoped_class($name): string
{
    $blocks = Build::data();

    return isset($blocks[$name]) ? $blocks[$name]['scopedClass'] : '';
}

/**
 * Build configurator block.
 *
 * @date   11/12/2022
 * @since  3.2.0
 */
function bs_build_configurator_block($attr): string
{
    $decoded = json_decode($attr, true);

    $attributes = [];
    Build::buildAttributes($decoded['blockstudio']['attributes'], $attributes);

    $block = $decoded;
    $block['attributes'] = $attributes;

    return json_encode($block);
}
