<?php

namespace Blockstudio;

/**
 * Utils class.
 *
 * @date   07/04/2023
 * @since  4.2.0
 */
class Utils
{
    /**
     * Render attributes.
     *
     * @date   07/04/2023
     * @since  4.2.0
     *
     * @param  $data
     * @param  array  $allowed
     * @param  bool   $variables
     *
     * @return string
     */
    public static function attributes(
        $data,
        $allowed = [],
        $variables = false
    ): string {
        $attributes = '';

        foreach ($data as $key => $value) {
            if (
                (count($allowed) >= 1 && !in_array($key, $allowed)) ||
                empty($value)
            ) {
                continue;
            }
            $key = preg_replace('/([a-z])([A-Z])/', '$1_$2', $key);
            $key = strtolower($key);
            $key = str_replace('_', '-', $key);
            $value =
                $value['value'] ??
                (is_array($value) ? esc_attr(json_encode($value)) : $value);

            if (!$variables) {
                $attributes .= 'data-' . $key . '="' . $value . '" ';
            } elseif (!is_array($value)) {
                $attributes .= '--' . $key . ': ' . $value . ';';
            }
        }

        return $attributes;
    }

    /**
     * Render data attributes.
     *
     * @date   15/08/2024
     * @since  5.6.0
     *
     * @param  $dataAttributes
     *
     * @return string
     */
    public static function dataAttributes($dataAttributes): string
    {
        $attributes = '';
        foreach ($dataAttributes ?? [] as $data) {
            $attr = $data['attribute'];
            $value = $data['value'];

            if (isset($data['data']['media']) && $attr === 'src') {
                $srcset = wp_get_attachment_image_srcset(
                    $data['data']['media']
                );
                $src = wp_get_attachment_image_url($data['data']['media']);
                $attributes .= " src='$src'";
                $attributes .= " srcset='$srcset'";
            } else {
                $attributes .= " $attr='$value'";
            }
        }

        return $attributes;
    }

    /**
     * Console log.
     *
     * @date   05/05/2025
     * @since  6.0.0
     *
     * @param  $data
     *
     * @return void
     */
    public static function consoleLog($data): void
    {
        echo '<script>';
        echo 'console.log(' . json_encode($data) . ')';
        echo '</script>';
    }
}
