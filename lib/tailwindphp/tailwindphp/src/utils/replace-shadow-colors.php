<?php

declare (strict_types=1);
namespace BlockstudioVendor\TailwindPHP\Utils;

/**
 * Shadow color replacement utilities.
 *
 * Port of: packages/tailwindcss/src/utils/replace-shadow-colors.ts
 *
 * @port-deviation:none This is a direct 1:1 port with no significant deviations.
 */
const SHADOW_KEYWORDS = ['inset', 'inherit', 'initial', 'revert', 'unset'];
const SHADOW_LENGTH_PATTERN = '/^-?(\d+|\.\d+)(.*?)$/';
/**
 * Replace shadow colors in a box-shadow value.
 *
 * @param string $input
 * @param callable(string): string $replacement
 * @return string
 */
function replaceShadowColors(string $input, callable $replacement): string
{
    $shadows = array_map(function ($shadow) use ($replacement) {
        $shadow = trim($shadow);
        $parts = array_filter(segment($shadow, ' '), fn($part) => trim($part) !== '');
        $color = null;
        $offsetX = null;
        $offsetY = null;
        foreach ($parts as $part) {
            if (in_array($part, SHADOW_KEYWORDS, \true)) {
                continue;
            } elseif (preg_match(SHADOW_LENGTH_PATTERN, $part)) {
                if ($offsetX === null) {
                    $offsetX = $part;
                } elseif ($offsetY === null) {
                    $offsetY = $part;
                }
            } elseif ($color === null) {
                $color = $part;
            }
        }
        // If the x and y offsets were not detected, the shadow is either invalid or
        // using a variable to represent more than one field in the shadow value, so
        // we can't know what to replace.
        if ($offsetX === null || $offsetY === null) {
            return $shadow;
        }
        $replacementColor = $replacement($color ?? 'currentcolor');
        if ($color !== null) {
            // If a color was found, replace the color.
            $pos = strpos($shadow, $color);
            if ($pos !== \false) {
                return substr_replace($shadow, $replacementColor, $pos, strlen($color));
            }
            return $shadow;
        }
        // If no color was found, assume the shadow is relying on the browser
        // default shadow color and append the replacement color.
        return "{$shadow} {$replacementColor}";
    }, segment($input, ','));
    return implode(', ', $shadows);
}
