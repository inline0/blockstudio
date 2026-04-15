<?php

declare (strict_types=1);
namespace BlockstudioVendor\TailwindPHP\Minifier;

/**
 * CSS Minifier - Reduces CSS file size while preserving functionality.
 *
 * Optimizations:
 * - Remove comments
 * - Remove unnecessary whitespace
 * - Shorten hex colors (#ffffff → #fff)
 * - Remove units from zero values (0px → 0)
 * - Shorten font-weight keywords (normal → 400, bold → 700)
 * - Remove empty rules
 *
 * Does NOT:
 * - Merge duplicate selectors (makes debugging harder)
 * - Combine shorthand properties (can affect cascade)
 */
class CssMinifier
{
    /**
     * Minify a CSS string.
     *
     * @param string $css The CSS to minify
     * @return string Minified CSS
     */
    public static function minify(string $css): string
    {
        $css = self::removeComments($css);
        $css = self::removeWhitespace($css);
        $css = self::shortenHexColors($css);
        $css = self::removeZeroUnits($css);
        $css = self::shortenFontWeight($css);
        $css = self::removeEmptyRules($css);
        return trim($css);
    }
    /**
     * Remove CSS comments.
     */
    private static function removeComments(string $css): string
    {
        return preg_replace('/\/\*[\s\S]*?\*\//', '', $css);
    }
    /**
     * Remove unnecessary whitespace.
     */
    private static function removeWhitespace(string $css): string
    {
        // Collapse multiple whitespace to single space
        $css = preg_replace('/\s+/', ' ', $css);
        // Remove space around special characters
        $css = preg_replace('/\s*([{};:,>~+])\s*/', '$1', $css);
        // Remove space after ( and before )
        $css = preg_replace('/\(\s+/', '(', $css);
        $css = preg_replace('/\s+\)/', ')', $css);
        // Remove trailing semicolons before closing braces
        $css = str_replace(';}', '}', $css);
        return $css;
    }
    /**
     * Shorten 6-digit hex colors to 3-digit where possible.
     * #ffffff → #fff, #aabbcc → #abc
     */
    private static function shortenHexColors(string $css): string
    {
        return preg_replace_callback('/#([0-9a-fA-F])\1([0-9a-fA-F])\2([0-9a-fA-F])\3\b/', fn($m) => '#' . strtolower($m[1] . $m[2] . $m[3]), $css);
    }
    /**
     * Remove units from zero values.
     * 0px → 0, 0rem → 0, 0em → 0
     *
     * Exceptions: 0s and 0ms (time values need units in animations)
     */
    private static function removeZeroUnits(string $css): string
    {
        // Match 0 followed by a unit, but not 0s or 0ms (time units)
        // Also preserve 0% in some contexts (gradients, etc.)
        return preg_replace('/\b0(px|rem|em|ex|ch|vw|vh|vmin|vmax|cm|mm|in|pt|pc)\b/', '0', $css);
    }
    /**
     * Shorten font-weight keywords to numeric values.
     * normal → 400, bold → 700
     */
    private static function shortenFontWeight(string $css): string
    {
        $css = preg_replace('/font-weight:normal\b/', 'font-weight:400', $css);
        $css = preg_replace('/font-weight:bold\b/', 'font-weight:700', $css);
        return $css;
    }
    /**
     * Remove empty rules.
     * .foo {} → (removed)
     */
    private static function removeEmptyRules(string $css): string
    {
        // Remove rules with empty declaration blocks
        // Match selector(s) followed by empty braces
        return preg_replace('/[^{}]+\{\s*\}/', '', $css);
    }
}
