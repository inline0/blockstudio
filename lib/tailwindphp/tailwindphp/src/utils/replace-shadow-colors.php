<?php

declare (strict_types=1);
namespace BlockstudioVendor\TailwindPHP\Utils;

use BlockstudioVendor\TailwindPHP\ValueParser;
/**
 * Shadow color replacement utilities.
 *
 * Port of: packages/tailwindcss/src/utils/replace-shadow-colors.ts
 *
 * @port-deviation:identity TypeScript tracks the single unknown node by object
 * identity (`node === unknown`); PHP compares the node arrays structurally,
 * which is equivalent because the identity path only runs when exactly one
 * unknown node exists.
 */
const SHADOW_KEYWORDS = ['inset', 'inherit', 'initial', 'revert', 'unset'];
const SHADOW_LENGTH_FUNCTIONS = ['calc', 'clamp', 'max', 'min', '--spacing'];
const SHADOW_COLOR_FUNCTIONS = ['color', 'color-mix', 'contrast-color', 'device-cmyk', 'hsl', 'hsla', 'hwb', 'lab', 'lch', 'light-dark', 'oklab', 'oklch', 'rgb', 'rgba', '--alpha'];
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
    $replaceAst = function (array $node) use ($replacement): array {
        $color = ValueParser\toCss([$node]);
        $updatedColor = $replacement($color);
        return ValueParser\parse($updatedColor);
    };
    $shadows = array_map(function ($shadow) use ($replacement, $replaceAst) {
        $shadow = trim($shadow);
        $ast = ValueParser\parse($shadow);
        $unknown = null;
        $unknowns = 0;
        $lengths = 0;
        $replaced = \false;
        ValueParser\walk($ast, function ($node) use (&$unknown, &$unknowns, &$lengths, &$replaced, $replaceAst) {
            switch ($node['kind']) {
                case 'word':
                    // Skip known keywords
                    if (in_array(strtolower($node['value']), SHADOW_KEYWORDS, \true)) {
                        return ValueParser\WalkAction::Continue;
                    }
                    // Must be a length
                    if (preg_match(SHADOW_LENGTH_PATTERN, strtolower($node['value']))) {
                        $lengths++;
                        return ValueParser\WalkAction::Continue;
                    }
                    // Must be a color
                    if (($node['value'][0] ?? '') === '#' || isNamedColor($node['value'])) {
                        $replaced = \true;
                        return ValueParser\WalkAction::ReplaceStop($replaceAst($node));
                    }
                    // We're not sure yet
                    $unknown = $node;
                    $unknowns++;
                    break;
                case 'function':
                    // Must be a color
                    if (in_array(strtolower($node['value']), SHADOW_COLOR_FUNCTIONS, \true)) {
                        $replaced = \true;
                        return ValueParser\WalkAction::ReplaceStop($replaceAst($node));
                    }
                    // Must be a length
                    if (in_array(strtolower($node['value']), SHADOW_LENGTH_FUNCTIONS, \true)) {
                        $lengths++;
                        return ValueParser\WalkAction::Skip;
                    }
                    // We're not sure yet
                    $unknown = $node;
                    $unknowns++;
                    // We're not interested in the arguments of the function
                    return ValueParser\WalkAction::Skip;
                case 'separator':
                    return ValueParser\WalkAction::Continue;
            }
            return ValueParser\WalkAction::Continue;
        });
        // We definitely found a color, nothing else to do
        if ($replaced) {
            return ValueParser\toCss($ast);
        }
        // If the x and y offsets were not detected, the shadow is either invalid
        // or using a variable to represent more than one field in the shadow
        // value, so we can't know what to replace.
        if ($lengths < 2) {
            return $shadow;
        }
        // If no color was found, assume the shadow is relying on the browser
        // default shadow color and append the replacement color.
        if ($unknowns === 0) {
            return $shadow . ' ' . $replacement('currentcolor');
        }
        // A single left-over, we assume that this is the color
        if ($unknowns === 1) {
            ValueParser\walk($ast, function ($node) use (&$replaced, $unknown, $replaceAst) {
                if ($node === $unknown) {
                    $replaced = \true;
                    return ValueParser\WalkAction::ReplaceStop($replaceAst($node));
                }
                // Keep the walk top-level only, no need to go into functions
                return ValueParser\WalkAction::Skip;
            });
        }
        return $replaced ? ValueParser\toCss($ast) : $shadow;
    }, segment($input, ','));
    return implode(', ', $shadows);
}
