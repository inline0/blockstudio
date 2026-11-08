<?php

declare (strict_types=1);
namespace BlockstudioVendor\TailwindPHP\Minifier;

use BlockstudioVendor\TailwindPHP\LightningCss\LightningCss;
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
        $css = self::minifyDeclarations($css);
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
        $css = preg_replace('/\s+/', ' ', $css);
        $out = '';
        $depth = 0;
        $len = strlen($css);
        for ($i = 0; $i < $len; $i++) {
            $run = strcspn($css, '() ', $i);
            if ($run > 0) {
                $out .= substr($css, $i, $run);
                $i += $run;
                if ($i >= $len) {
                    break;
                }
            }
            $ch = $css[$i];
            if ($ch === '(') {
                $depth++;
                $out .= $ch;
                continue;
            }
            if ($ch === ')') {
                $depth = max(0, $depth - 1);
                $out .= $ch;
                continue;
            }
            if ($ch === ' ') {
                $prev = $out !== '' ? substr($out, -1) : '';
                $next = $i + 1 < $len ? $css[$i + 1] : '';
                $stripAfter = '{};:(';
                $stripBefore = '{};:,)';
                if ($depth === 0) {
                    $stripAfter .= ',';
                }
                if (str_contains($stripAfter, $prev) || str_contains($stripBefore, $next)) {
                    continue;
                }
                // Only strip around selector combinators at depth 0
                if ($depth === 0) {
                    $combinators = '>~+';
                    if (str_contains($combinators, $prev) || str_contains($combinators, $next)) {
                        continue;
                    }
                }
            }
            $out .= $ch;
        }
        return str_replace(';}', '}', $out);
    }
    private static function minifyDeclarations(string $css): string
    {
        $length = strlen($css);
        $out = '';
        $copied = 0;
        $depth = 0;
        $parens = 0;
        $segmentStart = 0;
        $colon = -1;
        $i = 0;
        while ($i < $length) {
            $i += strcspn($css, "{};:()\"'\\", $i);
            if ($i >= $length) {
                break;
            }
            $ch = $css[$i];
            if ($ch === '\\') {
                $i += 2;
                continue;
            }
            if ($ch === '"' || $ch === "'") {
                $quote = $ch;
                $i++;
                while ($i < $length) {
                    $i += strcspn($css, $quote . '\\', $i);
                    if ($i < $length && $css[$i] === '\\') {
                        $i += 2;
                        continue;
                    }
                    $i++;
                    break;
                }
                continue;
            }
            if ($ch === '(') {
                $parens++;
                $i++;
                continue;
            }
            if ($ch === ')') {
                if ($parens > 0) {
                    $parens--;
                }
                $i++;
                continue;
            }
            if ($ch === ':') {
                if ($parens === 0 && $colon < 0) {
                    $colon = $i;
                }
                $i++;
                continue;
            }
            if ($ch === '{') {
                if ($parens === 0) {
                    $depth++;
                    $segmentStart = $i + 1;
                    $colon = -1;
                }
                $i++;
                continue;
            }
            if ($ch === ';' && $parens > 0) {
                $i++;
                continue;
            }
            $valueLength = $i - $colon - 1;
            if ($depth > 0 && $colon > $segmentStart && ($valueLength <= 20 || strcspn($css, '#0', $colon + 1, $valueLength) < $valueLength)) {
                $property = trim(substr($css, $segmentStart, $colon - $segmentStart));
                if ($property !== '' && $property[0] !== '@') {
                    $value = substr($css, $colon + 1, $i - $colon - 1);
                    $minified = self::minifyDeclarationValue($property, $value);
                    if ($minified !== $value) {
                        $out .= substr($css, $copied, $colon + 1 - $copied) . $minified;
                        $copied = $i;
                    }
                }
            }
            if ($ch === '}') {
                $depth = max(0, $depth - 1);
                $parens = 0;
            }
            $segmentStart = $i + 1;
            $colon = -1;
            $i++;
        }
        if ($copied === 0) {
            return $css;
        }
        return $out . substr($css, $copied);
    }
    private static function minifyDeclarationValue(string $property, string $value): string
    {
        $important = '';
        if (str_contains($value, '!') && preg_match('/\s*!\s*important\s*$/i', $value, $match, \PREG_OFFSET_CAPTURE)) {
            $important = $match[0][0];
            $value = substr($value, 0, $match[0][1]);
        }
        return LightningCss::minifyValue($value, strtolower($property)) . $important;
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
