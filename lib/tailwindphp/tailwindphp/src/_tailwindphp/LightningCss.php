<?php

declare (strict_types=1);
namespace BlockstudioVendor\TailwindPHP\LightningCss;

use BlockstudioVendor\TailwindPHP\ValueParser;
/**
 * CSS Optimizer - PHP implementation of lightningcss transformations.
 *
 * @port-deviation:replacement This is NOT part of the TailwindCSS port.
 * It's a PHP implementation of the CSS optimizations that lightningcss
 * (Rust library) performs in the original Tailwind.
 *
 * lightningcss is a fast CSS parser, transformer, and minifier written in Rust.
 * TailwindCSS uses it to post-process generated CSS. Since we can't use the
 * Rust library directly in PHP, we implement the relevant transformations here.
 *
 * @see https://lightningcss.dev/
 */
class LightningCss
{
    /**
     * Optimize a complete CSS string.
     *
     * @param string $css The CSS to optimize
     * @return string Optimized CSS
     */
    public static function optimize(string $css): string
    {
        // For now, we optimize at the value level during generation.
        // Full CSS string optimization can be added here later if needed.
        return $css;
    }
    /**
     * Optimize a CSS property value.
     *
     * @param string $value The CSS value to optimize
     * @param string $property The CSS property name (for context-aware optimization)
     * @return string Optimized value
     */
    public static function optimizeValue(string $value, string $property = ''): string
    {
        // Check if this is a CSS custom property declaration
        $isCustomProperty = str_starts_with($property, '--');
        $value = self::normalizeWhitespace($value);
        $value = self::simplifyCalcExpressions($value, $isCustomProperty);
        $value = self::normalizeTimeValues($value);
        $value = self::normalizeOpacityPercentages($value, $property);
        $value = self::normalizeColors($value, $isCustomProperty);
        $value = self::evaluateColorMix($value);
        // Evaluate color-mix AFTER normalizeColors converts hex to named
        $value = self::normalizeOklabLightness($value);
        // Lightning CSS parses nonzero unitless outline and SVG stroke widths as pixels.
        if (in_array($property, ['outline-width', 'stroke-width'], \true) && preg_match('/^\+?(?:\d*\.)?\d+(?:e[+-]?\d+)?$/i', $value) && (float) $value > 0) {
            $value .= 'px';
        }
        $value = self::normalizeVarFallbackSpacing($value);
        $value = self::normalizeLeadingZeros($value);
        $value = self::normalizeGridValues($value, $property);
        $value = self::normalizeTransformFunctions($value, $property);
        $value = self::normalizeAnimationValue($value, $property);
        $value = self::normalizeUrlQuoting($value);
        $value = self::normalizeAngleUnits($value, $isCustomProperty);
        return $value;
    }
    /**
     * Format var() fallbacks without changing quoted strings or nested values.
     */
    public static function normalizeVarFallbackSpacing(string $value): string
    {
        if (!str_contains($value, 'var(') || !str_contains($value, ',')) {
            return $value;
        }
        $nodes = ValueParser\parse($value);
        $normalize = function (array &$nodes) use (&$normalize): void {
            foreach ($nodes as &$node) {
                if ($node['kind'] !== 'function') {
                    continue;
                }
                if ($node['value'] === 'var') {
                    foreach ($node['nodes'] as &$child) {
                        if ($child['kind'] === 'separator' && str_contains($child['value'], ',')) {
                            $child['value'] = preg_replace('/,\s*/', ', ', $child['value']);
                            break;
                        }
                    }
                    unset($child);
                }
                $normalize($node['nodes']);
            }
            unset($node);
        };
        $normalize($nodes);
        return ValueParser\toCss($nodes);
    }
    /**
     * Canonicalize angle units to degrees in registered custom property values.
     *
     * lightningcss parses custom properties that carry an @property syntax
     * (e.g. `<angle>` for --tw-mask-linear-position) and serializes angles in
     * degrees with 6 significant digits: `3rad` -> `171.887deg`.
     *
     * @param string $value The CSS value
     * @param bool $isCustomProperty Whether the declaration is a custom property
     * @return string Normalized value
     */
    public static function normalizeAngleUnits(string $value, bool $isCustomProperty = \false): string
    {
        if (!$isCustomProperty) {
            return $value;
        }
        if (!preg_match('/^([-+]?(?:\d*\.)?\d+)(rad|grad|turn)$/i', trim($value), $m)) {
            return $value;
        }
        $number = (float) $m[1];
        $degrees = match (strtolower($m[2])) {
            'rad' => $number * 180 / \M_PI,
            'grad' => $number * 0.9,
            'turn' => $number * 360,
            default => null,
        };
        if ($degrees === null) {
            return $value;
        }
        // 6 significant digits, trailing zeros trimmed (%.6g)
        return sprintf('%.6g', $degrees) . 'deg';
    }
    /**
     * Minify a CSS declaration value for compact serialization.
     *
     * Applies the value-level wins the string CssMinifier used to apply over
     * the full output: shorten 6-digit hex colors to 3 digits, drop units
     * from zero lengths (time units are preserved), and shorten font-weight
     * keywords to their numeric values. Unlike optimizeValue(), this is only
     * used for minified output, so pretty output stays byte-identical.
     *
     * @param string $value The CSS value to minify
     * @param string $property The CSS property name (for font-weight handling)
     * @return string Minified value
     */
    public static function minifyValue(string $value, string $property = ''): string
    {
        if ($property === 'font-weight' || str_ends_with($property, '-font-weight')) {
            if ($value === 'normal') {
                return '400';
            }
            if ($value === 'bold') {
                return '700';
            }
        }
        if (str_contains($value, '#')) {
            $value = preg_replace_callback('/#([0-9a-fA-F])\1([0-9a-fA-F])\2([0-9a-fA-F])\3\b/', fn($m) => '#' . strtolower($m[1] . $m[2] . $m[3]), $value);
        }
        if (str_contains($value, '0')) {
            $value = preg_replace('/\b0(px|rem|em|ex|ch|vw|vh|vmin|vmax|cm|mm|in|pt|pc)\b/', '0', $value);
        }
        return $value;
    }
    /**
     * Normalize URL quoting.
     *
     * LightningCSS adds quotes around URL values if not already quoted.
     * e.g., url(./file.jpg) -> url("./file.jpg")
     *
     * @param string $value The CSS value
     * @return string Normalized value
     */
    public static function normalizeUrlQuoting(string $value): string
    {
        // Match url() functions with unquoted values
        return preg_replace_callback('/url\(\s*([^"\')][^\)]*?)\s*\)/', function ($match) {
            $url = trim($match[1]);
            // Don't quote data URIs, variable references, or already quoted
            if (str_starts_with($url, 'data:') || str_starts_with($url, 'var(') || str_starts_with($url, '"') || str_starts_with($url, "'")) {
                return $match[0];
            }
            return 'url("' . $url . '")';
        }, $value);
    }
    /**
     * Normalize animation value to put the animation name last.
     *
     * LightningCSS reorders animation values so the name comes at the end.
     * e.g., "used 1s infinite" -> "1s infinite used"
     *
     * @param string $value The CSS value
     * @param string $property The CSS property name
     * @return string Normalized value
     */
    public static function normalizeAnimationValue(string $value, string $property = ''): string
    {
        // Only apply to animation property
        if ($property !== 'animation') {
            return $value;
        }
        // Skip if it contains var() - can't reliably parse
        if (str_contains($value, 'var(')) {
            return $value;
        }
        // Handle multiple animations (comma-separated)
        $animations = preg_split('/,\s*/', $value);
        $result = [];
        foreach ($animations as $animation) {
            $parts = preg_split('/\s+/', trim($animation));
            if (count($parts) <= 1) {
                $result[] = $animation;
                continue;
            }
            // Find the animation name (not a time, keyword, or number)
            $keywords = ['none', 'normal', 'reverse', 'alternate', 'alternate-reverse', 'running', 'paused', 'forwards', 'backwards', 'both', 'infinite', 'linear', 'ease', 'ease-in', 'ease-out', 'ease-in-out', 'step-start', 'step-end'];
            $nameIndex = -1;
            foreach ($parts as $i => $part) {
                // Skip times (ends with s or ms)
                if (preg_match('/^[\d.]+m?s$/', $part)) {
                    continue;
                }
                // Skip iteration count (number or 'infinite')
                if (is_numeric($part) || $part === 'infinite') {
                    continue;
                }
                // Skip known keywords
                if (in_array(strtolower($part), $keywords)) {
                    continue;
                }
                // Skip cubic-bezier() or steps()
                if (preg_match('/^(cubic-bezier|steps)\(/', $part)) {
                    continue;
                }
                // This is likely the animation name
                $nameIndex = $i;
                break;
            }
            if ($nameIndex >= 0 && $nameIndex < count($parts) - 1) {
                // Move name to the end (if not already there)
                $name = $parts[$nameIndex];
                array_splice($parts, $nameIndex, 1);
                $parts[] = $name;
            }
            $result[] = implode(' ', $parts);
        }
        return implode(', ', $result);
    }
    /**
     * Normalize whitespace in CSS values.
     *
     * lightningcss collapses multiple whitespace characters (including newlines)
     * into single spaces, and removes spaces after ( except for var() with empty fallbacks.
     *
     * @param string $value The CSS value
     * @return string Normalized value
     */
    public static function normalizeWhitespace(string $value): string
    {
        // Collapse multiple whitespace (including newlines) to single space
        $value = preg_replace('/\s+/', ' ', trim($value));
        // Remove space after (
        $value = preg_replace('/\(\s+/', '(', $value);
        // Remove space before ) BUT preserve ", )" (empty var() fallback)
        // First protect the ", )" pattern with a placeholder
        $value = str_replace(', )', ",\x00)", $value);
        // Now remove other spaces before )
        $value = preg_replace('/\s+\)/', ')', $value);
        // Restore the protected pattern
        $value = str_replace(",\x00)", ', )', $value);
        return $value;
    }
    /**
     * Normalize time values: ms to s.
     *
     * lightningcss converts milliseconds to seconds in a compact format:
     * - 500ms -> .5s
     * - 1000ms -> 1s
     * - 1500ms -> 1.5s
     *
     * @param string $value The CSS value
     * @return string Normalized value
     */
    public static function normalizeTimeValues(string $value): string
    {
        return preg_replace_callback('/(\d+)ms\b/', function ($m) {
            $ms = (int) $m[1];
            $seconds = $ms / 1000;
            // Format without trailing zeros
            $formatted = rtrim(rtrim(number_format($seconds, 3, '.', ''), '0'), '.');
            // If empty after removing zeros, it's 0
            if ($formatted === '' || $formatted === '0') {
                return '0s';
            }
            // Add leading dot if < 1 and no leading zero (e.g., 0.5 -> .5)
            if (strpos($formatted, '0.') === 0) {
                $formatted = substr($formatted, 1);
            }
            return $formatted . 's';
        }, $value);
    }
    /**
     * Normalize opacity percentage values to decimals.
     *
     * lightningcss converts:
     * - opacity: 0% -> opacity: 0
     * - opacity: 100% -> opacity: 1
     * - opacity: 50% -> opacity: .5
     *
     * @param string $value The CSS value
     * @param string $property The CSS property name
     * @return string Normalized value
     */
    public static function normalizeOpacityPercentages(string $value, string $property = ''): string
    {
        // Only apply to opacity property
        if ($property !== 'opacity') {
            return $value;
        }
        // Match percentage values
        if (preg_match('/^(\d+(?:\.\d+)?)%$/', trim($value), $m)) {
            $percent = (float) $m[1];
            $decimal = $percent / 100;
            // Format: 0 -> 0, 1 -> 1, 0.5 -> .5
            if ($decimal == 0) {
                return '0';
            }
            if ($decimal == 1) {
                return '1';
            }
            $formatted = rtrim(rtrim(number_format($decimal, 6, '.', ''), '0'), '.');
            // Remove leading zero: 0.5 -> .5
            if (strpos($formatted, '0.') === 0) {
                $formatted = substr($formatted, 1);
            }
            return $formatted;
        }
        return $value;
    }
    /**
     * Normalize colors to shortest representation.
     *
     * lightningcss converts colors to their shortest form:
     * - #f00 -> red (3 chars vs 4)
     * - #ff0 -> #ff0 (yellow is same length)
     * - blue -> #00f (same length, but hex preferred)
     *
     * Note: Only converts colors that are standalone values, not part of
     * CSS variable names (e.g., won't convert "blue" in "--color-blue-500")
     * or inside var() references.
     *
     * @param string $value The CSS value
     * @return string Normalized value
     */
    public static function normalizeColors(string $value, bool $isCustomProperty = \false): string
    {
        // Map hex to shorter color names
        static $hexToName = ['#f00' => 'red', '#ff0000' => 'red'];
        // Map names to hex when hex is same length or shorter
        static $nameToHex = ['black' => '#000', 'white' => '#fff', 'blue' => '#00f', 'lime' => '#0f0', 'aqua' => '#0ff', 'cyan' => '#0ff', 'fuchsia' => '#f0f', 'magenta' => '#f0f', 'yellow' => '#ff0'];
        // Don't convert colors inside var() references or CSS variable names
        if (str_contains($value, 'var(') || str_starts_with($value, '--')) {
            return $value;
        }
        // Convert hex to names where names are shorter (always do this)
        foreach ($hexToName as $hex => $name) {
            // Use negative lookbehind for - to avoid matching in variable names
            $value = preg_replace('/(?<!-)' . preg_quote($hex, '/') . '\b/i', $name, $value);
        }
        // Convert names to hex where hex is shorter or same length
        // Skip this for custom properties to preserve color keywords like 'yellow'
        if (!$isCustomProperty) {
            foreach ($nameToHex as $name => $hex) {
                // Negative lookbehind for - to avoid matching in variable names like --color-blue-500
                $value = preg_replace('/(?<!-)\b' . $name . '\b/i', $hex, $value);
            }
        }
        return $value;
    }
    /**
     * Simplify calc() expressions where possible.
     *
     * lightningcss simplifies calc expressions like:
     * - calc(45deg * -1) -> -45deg
     * - calc(90deg * -1) -> -90deg
     *
     * Only applies to angle units (deg, rad, grad, turn).
     * Length units (px, rem, em) stay as calc() for properties like outline-offset.
     * Expressions with var() must stay as calc().
     *
     * @param string $value The CSS value
     * @return string Simplified value
     */
    public static function simplifyCalcExpressions(string $value, bool $isCustomProperty = \false): string
    {
        // Match: calc(NUMBER UNIT * -1) for angle units only
        if (preg_match('/^calc\(([+-]?\d*\.?\d+)(deg|rad|grad|turn)\s*\*\s*-1\)$/', $value, $m)) {
            $num = $m[1];
            $unit = $m[2];
            // If number is already negative, make it positive
            if (str_starts_with($num, '-')) {
                return substr($num, 1) . $unit;
            }
            return '-' . $num . $unit;
        }
        // Match: calc(NUMBER UNIT * INTEGER) - simplify simple multiplication
        // e.g., calc(.25rem * 4) -> 1rem
        // e.g., calc(0.25rem * 4) -> 1rem
        if (preg_match('/^calc\(([+-]?\d*\.?\d+)(rem|em|px|%|vh|vw|vmin|vmax|ch|ex)\s*\*\s*(\d+)\)$/', $value, $m)) {
            $num = floatval($m[1]);
            $unit = $m[2];
            $multiplier = intval($m[3]);
            $result = $num * $multiplier;
            // Format the result - remove trailing zeros and unnecessary decimal
            $resultStr = rtrim(rtrim(number_format($result, 6, '.', ''), '0'), '.');
            return $resultStr . $unit;
        }
        // Match: calc(A / B * 100%) - fold fraction utilities like `w-1/2`
        // to a percentage the way lightningcss does (6 significant digits):
        // calc(1 / 2 * 100%) -> 50%, calc(1 / 3 * 100%) -> 33.3333%.
        // Custom properties are unparsed by lightningcss and keep the calc().
        if (!$isCustomProperty && preg_match('/^calc\((\d+)\s*\/\s*(\d+)\s*\*\s*100%\)$/', $value, $m)) {
            $numerator = (int) $m[1];
            $denominator = (int) $m[2];
            if ($denominator !== 0) {
                return sprintf('%.6g', $numerator / $denominator * 100) . '%';
            }
        }
        return $value;
    }
    /**
     * Serialize a number-form lightness in oklch()/oklab() as a percentage,
     * matching lightningcss: `oklch(0.971 0.013 17.38)` -> `oklch(97.1% 0.013 17.38)`.
     * The decimal point is shifted textually to avoid float precision drift.
     *
     * @param string $value The CSS value
     * @return string Normalized value
     */
    public static function normalizeOklabLightness(string $value): string
    {
        if (!preg_match('/okl(ch|ab)\(/i', $value)) {
            return $value;
        }
        return preg_replace_callback('/\b(oklch|oklab)\(\s*([0-9]*\.?[0-9]+)(?=\s)/i', function ($match) {
            $number = $match[2];
            // Shift the decimal point two places right (multiply by 100)
            if (str_contains($number, '.')) {
                [$int, $frac] = explode('.', $number, 2);
                $frac = str_pad($frac, 2, '0');
                $shifted = $int . substr($frac, 0, 2) . (strlen($frac) > 2 ? '.' . substr($frac, 2) : '');
            } else {
                $shifted = $number . '00';
            }
            // Trim leading zeros (but keep a lone zero) and trailing
            // fraction zeros
            $shifted = ltrim($shifted, '0');
            if ($shifted === '' || $shifted[0] === '.') {
                $shifted = '0' . $shifted;
            }
            if (str_contains($shifted, '.')) {
                $shifted = rtrim(rtrim($shifted, '0'), '.');
            }
            return $match[1] . '(' . $shifted . '%';
        }, $value);
    }
    /**
     * Normalize leading zeros in decimal numbers.
     *
     * lightningcss removes leading zeros: 0.5 -> .5, 0.25 -> .25
     *
     * @param string $value The CSS value
     * @return string Normalized value
     */
    public static function normalizeLeadingZeros(string $value): string
    {
        // Match 0.X at word boundaries and replace with .X
        return preg_replace('/\b0+(\.\d+)/', '$1', $value);
    }
    /**
     * Normalize grid-related values.
     *
     * lightningcss normalizations for grid:
     * - Add spaces around / in span values: "span 1/span 2" -> "span 1 / span 2"
     * - Convert bare integers to px for grid-template-*: 123 -> 123px
     *
     * @param string $value The CSS value
     * @param string $property The CSS property name
     * @return string Normalized value
     */
    public static function normalizeGridValues(string $value, string $property = ''): string
    {
        // Add spaces around / in grid span values
        // Match patterns like "span 123/span 123" -> "span 123 / span 123"
        if (str_contains($value, 'span') && str_contains($value, '/')) {
            $value = preg_replace('/(\S)\/(\S)/', '$1 / $2', $value);
        }
        // Convert bare integers to px for grid-template-columns/rows
        // lightningcss does this normalization: 123 -> 123px
        // Only for grid-template-* properties, NOT for grid-column/grid-row (which use line numbers)
        if (preg_match('/^\d+$/', $value) && ($property === 'grid-template-columns' || $property === 'grid-template-rows')) {
            $value = $value . 'px';
        }
        return $value;
    }
    /**
     * Normalize transform function spacing.
     *
     * @param string $value The CSS value
     * @param string $property The CSS property name
     * @return string Normalized value
     */
    public static function normalizeTransformFunctions(string $value, string $property = ''): string
    {
        return $value;
    }
    /**
     * Downlevel native CSS nesting to flat CSS, the way lightningcss does
     * for the reference pipeline's browser targets.
     *
     * The heavy lifting — `&` substitution with `:is(…)` semantics,
     * conditional at-rule hoisting, adjacent merging — happens first in
     * `TailwindPHP\Ast\handleNesting()` (the 1:1 port of the reference's
     * pass), which intentionally leaves nesting below the first
     * declaration-carrying level in native syntax. This pass flattens that
     * remainder and applies the selector serialization normalizations
     * lightningcss performs while reprinting (`::before` → `:before`,
     * `*::x` → ` ::x`, parentless `&` → `:scope`).
     *
     * @param array $ast The CSS AST
     * @return array Transformed AST with flat selectors
     */
    public static function transformNesting(array $ast): array
    {
        $atRules = [];
        // Unused; conditional at-rules now emit in place
        $result = self::flattenNodes($ast, $atRules);
        // Note: adjacent-sibling merging (mergeAdjacentNodes) is applied by
        // callers late in their pipelines so the full and incremental
        // optimizeAst paths stay byte-identical.
        return $result;
    }
    /**
     * The property-handler chain of lightningcss's DeclarationHandler, in
     * flush order (declaration.rs). Each entry maps a handler name to the
     * longhand properties it absorbs, listed in the handler's own flush
     * order. Properties not listed here (including logical properties, which
     * Tailwind's reference pipeline excludes via
     * `exclude: Features.LogicalProperties`, custom properties, and
     * vendor-prefixed properties) pass through in source order.
     *
     * @var array<string, array<int, string>>
     */
    private const DECLARATION_HANDLERS = ['direction' => ['direction', 'unicode-bidi'], 'background' => ['background', 'background-color', 'background-image', 'background-position', 'background-position-x', 'background-position-y', 'background-repeat', 'background-size', 'background-attachment', 'background-origin', 'background-clip'], 'border' => ['border', 'border-top', 'border-right', 'border-bottom', 'border-left', 'border-width', 'border-style', 'border-color', 'border-top-width', 'border-top-style', 'border-top-color', 'border-right-width', 'border-right-style', 'border-right-color', 'border-bottom-width', 'border-bottom-style', 'border-bottom-color', 'border-left-width', 'border-left-style', 'border-left-color', 'border-radius', 'border-top-left-radius', 'border-top-right-radius', 'border-bottom-left-radius', 'border-bottom-right-radius', 'border-image', 'border-image-source', 'border-image-slice', 'border-image-width', 'border-image-outset', 'border-image-repeat'], 'outline' => ['outline', 'outline-color', 'outline-style', 'outline-width'], 'flex' => ['flex', 'flex-grow', 'flex-shrink', 'flex-basis', 'flex-direction', 'flex-wrap', 'flex-flow', 'order'], 'grid' => ['grid', 'grid-template', 'grid-template-areas', 'grid-template-rows', 'grid-template-columns', 'grid-area', 'grid-row', 'grid-row-start', 'grid-row-end', 'grid-column', 'grid-column-start', 'grid-column-end', 'grid-auto-rows', 'grid-auto-columns', 'grid-auto-flow'], 'align' => ['align-content', 'justify-content', 'place-content', 'align-self', 'justify-self', 'place-self', 'align-items', 'justify-items', 'place-items', 'row-gap', 'column-gap', 'gap'], 'size' => ['width', 'min-width', 'max-width', 'height', 'min-height', 'max-height'], 'margin' => ['margin-top', 'margin-bottom', 'margin-left', 'margin-right', 'margin'], 'padding' => ['padding-top', 'padding-bottom', 'padding-left', 'padding-right', 'padding'], 'scroll-margin' => ['scroll-margin-top', 'scroll-margin-bottom', 'scroll-margin-left', 'scroll-margin-right', 'scroll-margin'], 'scroll-padding' => ['scroll-padding-top', 'scroll-padding-bottom', 'scroll-padding-left', 'scroll-padding-right', 'scroll-padding'], 'font' => ['font', 'font-family', 'font-size', 'font-style', 'font-weight', 'font-stretch', 'line-height', 'font-variant-caps'], 'text' => ['text-decoration', 'text-decoration-line', 'text-decoration-style', 'text-decoration-color', 'text-decoration-thickness', 'text-emphasis', 'text-emphasis-style', 'text-emphasis-color', 'text-emphasis-position'], 'list' => ['list-style', 'list-style-type', 'list-style-image', 'list-style-position'], 'transition' => ['transition-property', 'transition-duration', 'transition-delay', 'transition-timing-function', 'transition'], 'animation' => ['animation-name', 'animation-duration', 'animation-timing-function', 'animation-iteration-count', 'animation-direction', 'animation-play-state', 'animation-delay', 'animation-fill-mode', 'animation'], 'display' => ['display'], 'position' => ['position'], 'inset' => ['top', 'bottom', 'left', 'right', 'inset'], 'overflow' => ['overflow-x', 'overflow-y', 'overflow'], 'transform' => ['perspective', 'perspective-origin', 'backface-visibility', 'transform-style', 'transform-box', 'transform-origin', 'transform', 'translate', 'rotate', 'scale'], 'box-shadow' => ['box-shadow'], 'mask' => ['clip-path', 'mask', 'mask-image', 'mask-position', 'mask-size', 'mask-repeat', 'mask-clip', 'mask-origin', 'mask-composite', 'mask-mode', 'mask-border', 'mask-border-source', 'mask-border-slice', 'mask-border-width', 'mask-border-outset', 'mask-border-repeat', 'mask-border-mode'], 'container' => ['container', 'container-type', 'container-name']];
    /**
     * Property name → [handler name, canonical index] lookup built from
     * DECLARATION_HANDLERS on first use.
     *
     * @var array<string, array{string, int}>|null
     */
    private static ?array $handlerForProperty = null;
    /**
     * Reorder declarations within every style rule (and keyframe block) the
     * way lightningcss's DeclarationHandler does: declarations whose property
     * belongs to a handler and whose value is fully parseable are absorbed
     * into that handler's pending state and flushed at the end of the block
     * in the fixed handler-chain order; everything else (custom properties,
     * unhandled properties, vendor-prefixed properties) passes through in
     * source order. A handler property whose value contains `var(`/`env(`
     * cannot be represented in typed form (lightningcss's
     * `Property::Unparsed`), so it flushes that handler's pending state and
     * then passes through, preserving relative order. Important declarations
     * form their own block appended after the normal ones, and repeated
     * absorbed properties keep one slot with the last value while repeated
     * custom properties keep their first position with the last value.
     *
     * Shorthand combination and target-driven compatibility fallbacks of the
     * reference handlers are intentionally not emulated: the reference
     * output never exercises them (Tailwind emits one longhand per concern
     * and modern values stay `Unparsed` via their `var()` references).
     *
     * @param array $nodes
     * @return array
     */
    public static function reorderDeclarations(array $nodes): array
    {
        if (self::$handlerForProperty === null) {
            self::$handlerForProperty = [];
            foreach (self::DECLARATION_HANDLERS as $handler => $properties) {
                foreach ($properties as $index => $property) {
                    self::$handlerForProperty[$property] = [$handler, $index];
                }
            }
        }
        foreach ($nodes as &$node) {
            if ($node['kind'] === 'rule') {
                $node['nodes'] = self::reorderDeclarationBlock($node['nodes'] ?? []);
                // Style rules never nest other rules at this point in the
                // pipeline, but keyframe blocks reach here through the
                // at-rule recursion below.
            } elseif ($node['kind'] === 'at-rule' && !empty($node['nodes'])) {
                // Descriptor blocks keep their authored order.
                if (in_array($node['name'], ['@property', '@font-face', '@counter-style', '@page'], \true)) {
                    continue;
                }
                $node['nodes'] = self::reorderDeclarations($node['nodes']);
            }
        }
        unset($node);
        return $nodes;
    }
    /**
     * Reorder one declaration block per the handler model. Non-declaration
     * children (comments) keep their source position within the pass-through
     * stream.
     *
     * @param array $children
     * @return array
     */
    private static function reorderDeclarationBlock(array $children): array
    {
        // Fast path: nothing to reorder for zero or one declaration.
        if (count($children) < 2) {
            return $children;
        }
        $normal = [];
        $important = [];
        $hasMixins = \false;
        foreach ($children as $child) {
            if ($child['kind'] === 'at-rule' && ($child['name'] ?? '') === '@apply') {
                $hasMixins = \true;
            }
            if ($child['kind'] === 'declaration' && ($child['important'] ?? \false)) {
                $important[] = $child;
            } else {
                $normal[] = $child;
            }
        }
        if ($hasMixins) {
            $result = [];
            $group = [];
            foreach ($children as $child) {
                if ($child['kind'] === 'at-rule' && ($child['name'] ?? '') === '@apply') {
                    $result = array_merge($result, self::reorderDeclarationBlock($group), [$child]);
                    $group = [];
                } else {
                    $group[] = $child;
                }
            }
            return array_merge($result, self::reorderDeclarationBlock($group));
        }
        $result = self::reorderDeclarationGroup($normal);
        if ($important !== []) {
            $result = array_merge($result, self::reorderDeclarationGroup($important));
        }
        return $result;
    }
    /**
     * @param array $children
     * @return array
     */
    private static function reorderDeclarationGroup(array $children): array
    {
        $out = [];
        $customIndex = [];
        // custom property name => index in $out
        /** @var array<string, array<string, array>> $buckets handler => [property => declaration] */
        $buckets = [];
        $flushHandler = function (string $handler) use (&$out, &$buckets): void {
            if (empty($buckets[$handler])) {
                return;
            }
            foreach (self::DECLARATION_HANDLERS[$handler] as $property) {
                if (isset($buckets[$handler][$property])) {
                    $out[] = $buckets[$handler][$property];
                }
            }
            $buckets[$handler] = [];
        };
        foreach ($children as $child) {
            if ($child['kind'] !== 'declaration') {
                $out[] = $child;
                continue;
            }
            $property = $child['property'] ?? '';
            // Custom properties: IndexMap semantics — first position wins,
            // a repeat with a different value replaces in place, an
            // identical repeat is dropped.
            if (str_starts_with($property, '--')) {
                if (isset($customIndex[$property])) {
                    if ($out[$customIndex[$property]] !== $child) {
                        $out[$customIndex[$property]] = $child;
                    }
                } else {
                    $customIndex[$property] = count($out);
                    $out[] = $child;
                }
                continue;
            }
            // Vendor-prefixed declarations pass through untouched (the
            // reference pipeline receives them rarely and its PrefixHandler
            // interplay is not emulated).
            if ($property !== '' && $property[0] === '-') {
                $out[] = $child;
                continue;
            }
            $handlerInfo = self::$handlerForProperty[$property] ?? null;
            $value = $child['value'] ?? '';
            // Values with variable references and CSS-wide keywords stay
            // unparsed in lightningcss (`Property::Unparsed`). Such a
            // declaration travels the whole handler chain: the size handler
            // flushes its pending state for ANY unparsed property that
            // reaches it (its match arm is unguarded in size.rs), the owning
            // handler flushes its own pending state, and the declaration
            // then passes through in place. Handlers before size in the
            // chain claim their own unparsed properties first, so the size
            // flush only triggers when no earlier handler owns the property.
            $isUnparsed = str_contains($value, 'var(') || str_contains($value, 'env(') || in_array(strtolower(trim($value)), ['inherit', 'initial', 'unset', 'revert', 'revert-layer'], \true);
            if ($isUnparsed) {
                $handler = $handlerInfo[0] ?? null;
                static $handlerOrder = null;
                $handlerOrder ??= array_flip(array_keys(self::DECLARATION_HANDLERS));
                if ($handler === null || $handlerOrder[$handler] >= $handlerOrder['size']) {
                    $flushHandler('size');
                }
                if ($handler !== null) {
                    $flushHandler($handler);
                }
                $out[] = $child;
                continue;
            }
            if ($handlerInfo === null) {
                $out[] = $child;
                continue;
            }
            // Absorb into the handler's pending state; a repeated property
            // keeps one slot with the last value.
            $buckets[$handlerInfo[0]][$property] = $child;
        }
        // Finalize: flush handlers in chain order.
        foreach (array_keys(self::DECLARATION_HANDLERS) as $handler) {
            $flushHandler($handler);
        }
        return $out;
    }
    /**
     * Merge directly adjacent sibling nodes the way lightningcss does:
     * conditional at-rules with an identical name and params concatenate
     * their children, and rules with an identical selector concatenate their
     * declarations. Non-adjacent nodes are never merged so cascade order is
     * preserved. Recurses into at-rule children after merging.
     *
     * @param array $nodes
     * @return array
     */
    public static function mergeAdjacentNodes(array $nodes): array
    {
        $mergeableAtRules = ['@media', '@supports', '@container', '@starting-style'];
        $result = [];
        foreach ($nodes as $node) {
            $lastIndex = count($result) - 1;
            $last = $lastIndex >= 0 ? $result[$lastIndex] : null;
            if ($last !== null && $node['kind'] === 'at-rule' && $last['kind'] === 'at-rule' && $node['name'] === $last['name'] && ($node['params'] ?? '') === ($last['params'] ?? '') && in_array($node['name'], $mergeableAtRules, \true)) {
                $result[$lastIndex]['nodes'] = array_merge($last['nodes'] ?? [], $node['nodes'] ?? []);
                continue;
            }
            if ($last !== null && $node['kind'] === 'rule' && $last['kind'] === 'rule' && $node['selector'] === $last['selector']) {
                $result[$lastIndex]['nodes'] = array_merge($last['nodes'] ?? [], $node['nodes'] ?? []);
                continue;
            }
            $result[] = $node;
        }
        foreach ($result as &$node) {
            if ($node['kind'] === 'at-rule' && !empty($node['nodes'])) {
                $node['nodes'] = self::mergeAdjacentNodes($node['nodes']);
            } elseif ($node['kind'] === 'rule' && !empty($node['nodes'])) {
                $node['nodes'] = self::dedupeDeclarations($node['nodes']);
            }
        }
        unset($node);
        return $result;
    }
    /**
     * Remove shadowed declarations within a rule the way lightningcss does:
     * exact duplicates (same property, value, and importance) keep only the
     * last occurrence, and custom properties shadowed by a later declaration
     * of the same name are dropped entirely. Standard properties with
     * different values are preserved, since repeating a property is a valid
     * browser-fallback pattern.
     *
     * @param array $nodes
     * @return array
     */
    public static function dedupeDeclarations(array $nodes): array
    {
        $removed = [];
        // Walk backwards so "last occurrence wins" falls out naturally
        $seenExact = [];
        $seenCustom = [];
        for ($i = count($nodes) - 1; $i >= 0; $i--) {
            $node = $nodes[$i];
            if ($node['kind'] !== 'declaration') {
                continue;
            }
            $property = $node['property'] ?? '';
            // Vendor-prefixed properties keep their duplicates (lightningcss
            // routes them through its prefix handling, which appends)
            if ($property !== '' && $property[0] === '-' && !str_starts_with($property, '--')) {
                continue;
            }
            $exactKey = $property . ':' . ($node['value'] ?? '') . ':' . ($node['important'] ?? \false ? '1' : '0');
            if (isset($seenExact[$exactKey])) {
                $removed[$i] = \true;
                continue;
            }
            $seenExact[$exactKey] = \true;
            if (str_starts_with($property, '--')) {
                if (isset($seenCustom[$property])) {
                    $removed[$i] = \true;
                    continue;
                }
                $seenCustom[$property] = \true;
            }
        }
        if (!empty($removed)) {
            $result = [];
            foreach ($nodes as $i => $node) {
                if (!isset($removed[$i])) {
                    $result[] = $node;
                }
            }
            $nodes = $result;
        }
        return self::mergeLogicalPairs($nodes);
    }
    /**
     * Merge adjacent logical property pairs with identical values into their
     * shorthand, the way lightningcss does: `margin-inline-start: 0;
     * margin-inline-end: 0` becomes `margin-inline: 0`.
     *
     * @param array $nodes
     * @return array
     */
    private static function mergeLogicalPairs(array $nodes): array
    {
        static $pairs = ['margin-inline-start' => ['margin-inline-end', 'margin-inline'], 'margin-inline-end' => ['margin-inline-start', 'margin-inline'], 'margin-block-start' => ['margin-block-end', 'margin-block'], 'margin-block-end' => ['margin-block-start', 'margin-block']];
        $result = [];
        $count = count($nodes);
        for ($i = 0; $i < $count; $i++) {
            $node = $nodes[$i];
            $next = $nodes[$i + 1] ?? null;
            if ($next !== null && $node['kind'] === 'declaration' && $next['kind'] === 'declaration' && isset($pairs[$node['property'] ?? '']) && $pairs[$node['property']][0] === ($next['property'] ?? '') && ($node['value'] ?? null) === ($next['value'] ?? null) && ($node['important'] ?? \false) === ($next['important'] ?? \false)) {
                $merged = $node;
                $merged['property'] = $pairs[$node['property']][1];
                $result[] = $merged;
                $i++;
                continue;
            }
            $result[] = $node;
        }
        return $result;
    }
    /**
     * Flatten a list of nodes, collecting hoistable at-rules into the shared
     * collector instead of appending them. Callers that process the AST in
     * segments use this with one collector so hoisting order matches a single
     * transformNesting() pass over the concatenated list.
     *
     * @param array $nodes The nodes to flatten
     * @param array &$atRules Shared collector for hoisted at-rules
     * @return array Flattened nodes without the hoisted at-rules
     */
    public static function flattenNodes(array $nodes, array &$atRules): array
    {
        $result = [];
        foreach ($nodes as $node) {
            self::flattenNode($node, $result, $atRules, null);
        }
        return $result;
    }
    /**
     * Merge at-rules collected by flattenNodes() with same name and params.
     *
     * @param array $atRules
     * @return array
     */
    public static function mergeCollectedAtRules(array $atRules): array
    {
        return self::mergeAtRules($atRules);
    }
    /**
     * Split a selector list on top-level commas only.
     * Does not split commas inside :where(), :not(), :is(), etc.
     *
     * @param string $selector The selector list to split
     * @return array Array of individual selectors
     */
    private static function splitSelectorList(string $selector): array
    {
        $selectors = [];
        $current = '';
        $depth = 0;
        for ($i = 0; $i < strlen($selector); $i++) {
            $char = $selector[$i];
            if ($char === '(' || $char === '[') {
                $depth++;
                $current .= $char;
            } elseif ($char === ')' || $char === ']') {
                $depth--;
                $current .= $char;
            } elseif ($char === ',' && $depth === 0) {
                $selectors[] = $current;
                $current = '';
            } else {
                $current .= $char;
            }
        }
        if ($current !== '') {
            $selectors[] = $current;
        }
        return $selectors;
    }
    /**
     * Flatten a single AST node, resolving nesting.
     *
     * @param array $node The node to flatten
     * @param array &$parent The parent array to add flattened nodes to
     * @param array &$atRules Collected at-rules to hoist
     * @param string|null $parentSelector The parent selector for resolving &
     */
    private static function flattenNode(array $node, array &$parent, array &$atRules, ?string $parentSelector): void
    {
        if ($node['kind'] === 'declaration') {
            $parent[] = $node;
            return;
        }
        if ($node['kind'] === 'comment') {
            $parent[] = $node;
            return;
        }
        if ($node['kind'] === 'context') {
            // Process context children
            foreach ($node['nodes'] ?? [] as $child) {
                self::flattenNode($child, $parent, $atRules, $parentSelector);
            }
            return;
        }
        if ($node['kind'] === 'rule') {
            $selector = $node['selector'];
            // Resolve & in selector, or prepend parent selector if no &.
            //
            // A parent that is a selector LIST has to be wrapped in `:is()` before
            // it is substituted, which is what native CSS nesting and Lightning CSS
            // both do. Substituting it raw turns `.a, .b` + `&:hover` into
            // `.a, .b:hover`, where the variant binds to the last selector only and
            // `.a` gets the styles unconditionally. Every `@apply` of a variant
            // utility inside a grouped selector hit this.
            if ($parentSelector !== null) {
                $parentRef = count(self::splitSelectorList($parentSelector)) > 1 ? ':is(' . $parentSelector . ')' : $parentSelector;
                if (str_contains($selector, '&')) {
                    // `&` alone is just the parent, so it needs no grouping.
                    $selector = trim($selector) === '&' ? $parentSelector : str_replace('&', $parentRef, $selector);
                } else {
                    // Nested selector without & - prepend parent to EACH selector in list
                    // e.g., ".parent" + "h1, h2, h3" -> ".parent h1, .parent h2, .parent h3"
                    // Must split on top-level commas only (not inside :where(), :not(), etc.)
                    $selectors = self::splitSelectorList($selector);
                    $selectors = array_map(fn($s) => $parentRef . ' ' . trim($s), $selectors);
                    $selector = implode(', ', $selectors);
                }
            } elseif (str_contains($selector, '&')) {
                // A parentless `&` refers to the current scoping root;
                // lightningcss serializes it as `:scope`. Escaped `\&` inside
                // class names (e.g. `.\[\&\>img\]`) must stay untouched.
                $selector = preg_replace('/(?<!\\\\)&/', ':scope', $selector);
            }
            // LightningCSS normalizes `*::pseudo` to ` ::pseudo` since `*` is implicit
            // e.g., `.foo *::selection` becomes `.foo ::selection`
            $selector = preg_replace('/\s\*::/', ' ::', $selector);
            // LightningCSS downlevels the CSS2 pseudo-elements to their
            // single-colon legacy form for compatibility
            $selector = preg_replace('/::(before|after|first-letter|first-line)\b/', ':$1', $selector);
            // If this is a nested rule inside a parent rule
            $declarations = [];
            $nestedRules = [];
            foreach ($node['nodes'] ?? [] as $child) {
                if ($child['kind'] === 'declaration' || $child['kind'] === 'at-rule' && ($child['name'] ?? '') === '@apply') {
                    $declarations[] = $child;
                } else {
                    $nestedRules[] = $child;
                }
            }
            // Output declarations at this level
            if (!empty($declarations)) {
                $parent[] = ['kind' => 'rule', 'selector' => $selector, 'nodes' => $declarations];
            }
            // Process nested rules with this selector as parent
            foreach ($nestedRules as $nested) {
                self::flattenNode($nested, $parent, $atRules, $selector);
            }
            return;
        }
        if ($node['kind'] === 'at-rule') {
            // Handle @layer specially - contents flatten in place inside the layer
            if ($node['name'] === '@layer') {
                $layerNodes = [];
                $layerAtRules = [];
                // Unused; conditional at-rules now emit in place
                foreach ($node['nodes'] ?? [] as $child) {
                    self::flattenNode($child, $layerNodes, $layerAtRules, $parentSelector);
                }
                $allLayerNodes = $layerNodes;
                // Keep @layer if:
                // 1. It has content (non-empty children), OR
                // 2. It's a layer order declaration (has params with comma-separated names and no children)
                //    e.g., @layer theme, base, components, utilities;
                $isLayerOrderDeclaration = empty($node['nodes']) && str_contains($node['params'] ?? '', ',');
                if (!empty($allLayerNodes) || $isLayerOrderDeclaration) {
                    $parent[] = ['kind' => 'at-rule', 'name' => '@layer', 'params' => $node['params'], 'nodes' => $allLayerNodes];
                }
                return;
            }
            // For at-rules like @media, @supports, @starting-style: flatten
            // their contents and emit the at-rule IN PLACE, preserving source
            // order. (lightningcss keeps conditional at-rules where they occur
            // and only merges directly adjacent ones; the previous behavior of
            // hoisting them to the end of the stylesheet and merging by
            // condition destroyed cascade order.)
            if (in_array($node['name'], ['@media', '@supports', '@container', '@starting-style'])) {
                // Collect declarations and nested rules from at-rule body
                $declarations = [];
                $nestedRules = [];
                foreach ($node['nodes'] ?? [] as $child) {
                    if ($child['kind'] === 'declaration') {
                        $declarations[] = $child;
                    } else {
                        $nestedRules[] = $child;
                    }
                }
                // If we have declarations and a parent selector, wrap them in a rule
                $flattenedNodes = [];
                if (!empty($declarations) && $parentSelector !== null) {
                    $flattenedNodes[] = ['kind' => 'rule', 'selector' => $parentSelector, 'nodes' => $declarations];
                } elseif (!empty($declarations)) {
                    // No parent selector - declarations at root level (shouldn't happen often)
                    $flattenedNodes = array_merge($flattenedNodes, $declarations);
                }
                // Process nested rules in place; nested at-rules stay as
                // children of this at-rule in their source position
                $unusedCollector = [];
                foreach ($nestedRules as $child) {
                    self::flattenNode($child, $flattenedNodes, $unusedCollector, $parentSelector);
                }
                if (!empty($flattenedNodes)) {
                    $parent[] = ['kind' => 'at-rule', 'name' => $node['name'], 'params' => $node['params'], 'nodes' => $flattenedNodes];
                }
                return;
            }
            // Other at-rules pass through
            $parent[] = $node;
        }
    }
    /**
     * Merge at-rules with the same name and params.
     *
     * @param array $atRules
     * @return array
     */
    private static function mergeAtRules(array $atRules): array
    {
        $merged = [];
        $seen = [];
        foreach ($atRules as $rule) {
            $key = $rule['name'] . '|' . $rule['params'];
            if (isset($seen[$key])) {
                // Merge nodes into existing rule
                $seen[$key]['nodes'] = array_merge($seen[$key]['nodes'], $rule['nodes']);
            } else {
                $seen[$key] = $rule;
                $merged[] =& $seen[$key];
            }
        }
        // Deduplicate rules within each at-rule
        foreach ($merged as &$rule) {
            $rule['nodes'] = self::deduplicateRules($rule['nodes']);
        }
        return $merged;
    }
    /**
     * Deduplicate rules with the same selector by merging their declarations.
     *
     * @param array $nodes
     * @return array
     */
    private static function deduplicateRules(array $nodes): array
    {
        $bySelector = [];
        $result = [];
        foreach ($nodes as $node) {
            if ($node['kind'] === 'rule') {
                if (!isset($bySelector[$node['selector']])) {
                    $bySelector[$node['selector']] = ['kind' => 'rule', 'selector' => $node['selector'], 'nodes' => []];
                    $result[] =& $bySelector[$node['selector']];
                }
                $bySelector[$node['selector']]['nodes'] = array_merge($bySelector[$node['selector']]['nodes'], $node['nodes']);
            } else {
                $result[] = $node;
            }
        }
        return $result;
    }
    /**
     * Merge ADJACENT rules with identical declarations by combining their selectors.
     * This optimizes output by grouping selectors that share the same styles.
     * Only merges rules that are directly adjacent to preserve ordering semantics.
     *
     * @param array $nodes
     * @return array
     */
    public static function mergeRulesWithSameDeclarations(array $nodes): array
    {
        $result = [];
        $lastDeclKey = null;
        $lastIndex = -1;
        $lastSelectors = [];
        // Track selectors we've already added to the merged rule
        foreach ($nodes as $node) {
            if ($node['kind'] === 'rule') {
                // Serialize declarations for comparison
                $declKey = self::serializeDeclarations($node['nodes'] ?? []);
                $currentSelector = $node['selector'];
                // Only merge if this rule immediately follows another rule with same declarations
                if ($lastIndex >= 0 && $lastDeclKey === $declKey && $lastIndex === count($result) - 1) {
                    // Check if this selector is already included (avoid duplicates)
                    if (!isset($lastSelectors[$currentSelector])) {
                        // Merge selectors with previous rule
                        $result[$lastIndex]['selector'] .= ', ' . $currentSelector;
                        $lastSelectors[$currentSelector] = \true;
                    }
                    // If selector already included, skip it entirely
                } else {
                    $result[] = $node;
                    $lastDeclKey = $declKey;
                    $lastIndex = count($result) - 1;
                    $lastSelectors = [$currentSelector => \true];
                    // Reset tracked selectors
                }
            } else {
                // For at-rules, recursively merge their child rules
                if ($node['kind'] === 'at-rule' && isset($node['nodes'])) {
                    $node['nodes'] = self::mergeRulesWithSameDeclarations($node['nodes']);
                }
                $result[] = $node;
                $lastDeclKey = null;
                // Reset when encountering non-rule
                $lastIndex = -1;
                $lastSelectors = [];
            }
        }
        return $result;
    }
    /**
     * Serialize declarations for comparison.
     *
     * @param array $nodes
     * @return string
     */
    public static function serializeDeclarations(array $nodes): string
    {
        $parts = [];
        foreach ($nodes as $node) {
            if ($node['kind'] === 'declaration') {
                $important = !empty($node['important']) ? '!important' : '';
                $parts[] = ($node['property'] ?? '') . ':' . ($node['value'] ?? '') . $important;
            } elseif ($node['kind'] === 'rule') {
                // Include nested rules in serialization
                $parts[] = 'rule:' . ($node['selector'] ?? '') . '{' . self::serializeDeclarations($node['nodes'] ?? []) . '}';
            } elseif ($node['kind'] === 'at-rule') {
                return serialize($nodes);
            }
        }
        sort($parts);
        // Sort for consistent comparison
        return implode(';', $parts);
    }
    /**
     * Minify a CSS string (optional, for production builds).
     *
     * @param string $css The CSS to minify
     * @return string Minified CSS
     */
    public static function minify(string $css): string
    {
        // Remove comments
        $css = preg_replace('/\/\*[\s\S]*?\*\//', '', $css);
        // Remove unnecessary whitespace
        $css = preg_replace('/\s+/', ' ', $css);
        $css = preg_replace('/\s*([{};:,])\s*/', '$1', $css);
        // Remove trailing semicolons before closing braces
        $css = str_replace(';}', '}', $css);
        return trim($css);
    }
    /**
     * Properties that require vendor prefixes.
     * Maps property name to array of prefixed versions (in order).
     */
    private const VENDOR_PREFIXES = ['text-size-adjust' => ['-webkit-text-size-adjust', '-moz-text-size-adjust', 'text-size-adjust'], 'appearance' => ['-webkit-appearance', 'appearance'], 'user-select' => ['-webkit-user-select', '-moz-user-select', 'user-select'], 'backdrop-filter' => ['-webkit-backdrop-filter', 'backdrop-filter'], 'text-decoration-skip-ink' => ['-webkit-text-decoration-skip-ink', 'text-decoration-skip-ink'], 'hyphens' => ['-webkit-hyphens', 'hyphens'], 'print-color-adjust' => ['-webkit-print-color-adjust', 'print-color-adjust'], 'mask' => ['-webkit-mask', 'mask'], 'mask-image' => ['-webkit-mask-image', 'mask-image'], 'mask-size' => ['-webkit-mask-size', 'mask-size'], 'mask-position' => ['-webkit-mask-position', 'mask-position'], 'mask-repeat' => ['-webkit-mask-repeat', 'mask-repeat'], 'mask-clip' => ['-webkit-mask-clip', 'mask-clip'], 'mask-composite' => ['-webkit-mask-composite', 'mask-composite'], 'text-decoration-color' => ['-webkit-text-decoration-color', '-webkit-text-decoration-color', 'text-decoration-color']];
    /**
     * Add vendor prefixes to declarations in the AST.
     *
     * @param array $ast The AST to process
     * @return array AST with vendor prefixes added
     */
    public static function addVendorPrefixes(array $ast): array
    {
        $result = [];
        foreach ($ast as $node) {
            if ($node['kind'] === 'rule' || $node['kind'] === 'at-rule') {
                if (isset($node['nodes'])) {
                    $node['nodes'] = self::addVendorPrefixesToNodes($node['nodes']);
                }
                $result[] = $node;
            } elseif ($node['kind'] === 'declaration') {
                // Handle top-level declarations
                $expanded = self::expandDeclarationWithPrefixes($node);
                foreach ($expanded as $decl) {
                    $result[] = $decl;
                }
            } else {
                $result[] = $node;
            }
        }
        return $result;
    }
    /**
     * Add vendor prefixes to a list of nodes.
     *
     * @param array $nodes
     * @return array
     */
    private static function addVendorPrefixesToNodes(array $nodes): array
    {
        $result = [];
        foreach ($nodes as $node) {
            if ($node['kind'] === 'declaration') {
                $expanded = self::expandDeclarationWithPrefixes($node);
                foreach ($expanded as $decl) {
                    $result[] = $decl;
                }
            } elseif ($node['kind'] === 'rule' || $node['kind'] === 'at-rule') {
                if (isset($node['nodes'])) {
                    $node['nodes'] = self::addVendorPrefixesToNodes($node['nodes']);
                }
                $result[] = $node;
            } else {
                $result[] = $node;
            }
        }
        return $result;
    }
    /**
     * Expand a declaration to include vendor-prefixed versions.
     *
     * @param array $decl
     * @return array Array of declarations (may be multiple if prefixes are needed)
     */
    private static function expandDeclarationWithPrefixes(array $decl): array
    {
        $property = $decl['property'] ?? '';
        if (!isset(self::VENDOR_PREFIXES[$property])) {
            return [$decl];
        }
        $prefixes = self::VENDOR_PREFIXES[$property];
        $result = [];
        foreach ($prefixes as $prefixedProp) {
            $value = $decl['value'] ?? '';
            // The legacy -webkit-mask-composite syntax uses different keywords
            // than the standard property; lightningcss translates them.
            if ($prefixedProp === '-webkit-mask-composite') {
                $value = strtr($value, ['add' => 'source-over', 'subtract' => 'source-out', 'intersect' => 'source-in', 'exclude' => 'xor']);
            }
            $result[] = ['kind' => 'declaration', 'property' => $prefixedProp, 'value' => $value, 'important' => $decl['important'] ?? \false];
        }
        return $result;
    }
    /**
     * CSS named colors to RGB values.
     */
    private const NAMED_COLORS = ['red' => [255, 0, 0], 'blue' => [0, 0, 255], 'green' => [0, 128, 0], 'lime' => [0, 255, 0], 'yellow' => [255, 255, 0], 'cyan' => [0, 255, 255], 'aqua' => [0, 255, 255], 'magenta' => [255, 0, 255], 'fuchsia' => [255, 0, 255], 'white' => [255, 255, 255], 'black' => [0, 0, 0], 'gray' => [128, 128, 128], 'grey' => [128, 128, 128], 'orange' => [255, 165, 0], 'purple' => [128, 0, 128], 'pink' => [255, 192, 203], 'brown' => [165, 42, 42], 'transparent' => [0, 0, 0, 0]];
    /**
     * Evaluate color-mix() expressions to oklch/oklab format with alpha.
     *
     * LightningCSS evaluates color-mix() when all values are static.
     * e.g., color-mix(in oklab, red 50%, transparent) -> oklab(62.7955% .224 .125 / .5)
     * e.g., color-mix(in oklab, oklch(63.7% .237 25.331) 50%, transparent) -> oklch(63.7% .237 25.331 / .5)
     *
     * @param string $value The CSS value
     * @return string Evaluated value
     */
    public static function evaluateColorMix(string $value): string
    {
        // Evaluate the innermost statically analyzable color-mix(…) first so
        // nested calls collapse outside-in, mirroring lightningcss.
        foreach (range(0, 15) as $_) {
            $pos = strrpos($value, 'color-mix(');
            if ($pos === \false) {
                return $value;
            }
            // Find the matching closing parenthesis
            $depth = 0;
            $end = null;
            $len = strlen($value);
            for ($i = $pos + 9; $i < $len; $i++) {
                if ($value[$i] === '(') {
                    $depth++;
                } elseif ($value[$i] === ')') {
                    $depth--;
                    if ($depth === 0) {
                        $end = $i;
                        break;
                    }
                }
            }
            if ($end === null) {
                return $value;
            }
            $args = substr($value, $pos + 10, $end - $pos - 10);
            $replacement = self::evaluateColorMixArguments($args);
            if ($replacement === null) {
                return $value;
            }
            $value = substr($value, 0, $pos) . $replacement . substr($value, $end + 1);
        }
        return $value;
    }
    /**
     * Evaluate the arguments of a single statically analyzable color-mix().
     *
     * @param string $args The raw argument list (between the parentheses)
     * @return string|null The flattened color, or null when not statically analyzable
     */
    private static function evaluateColorMixArguments(string $args): ?string
    {
        $parts = [];
        $depth = 0;
        $current = '';
        $len = strlen($args);
        for ($i = 0; $i < $len; $i++) {
            $char = $args[$i];
            if ($char === '(') {
                $depth++;
            } elseif ($char === ')') {
                $depth--;
            } elseif ($char === ',' && $depth === 0) {
                $parts[] = $current;
                $current = '';
                continue;
            }
            $current .= $char;
        }
        $parts[] = $current;
        if (count($parts) !== 3) {
            return null;
        }
        if (!preg_match('/^in\s+([a-z0-9-]+)$/i', trim($parts[0]), $m)) {
            return null;
        }
        $space = strtolower($m[1]);
        [$color1, $pct1] = self::splitColorMixComponent($parts[1]);
        [$color2, $pct2] = self::splitColorMixComponent($parts[2]);
        if ($color1 === null || $color2 === null) {
            return null;
        }
        // Normalize weights
        if ($pct1 === null && $pct2 === null) {
            $pct1 = 50.0;
            $pct2 = 50.0;
        } elseif ($pct1 === null) {
            $pct1 = 100.0 - $pct2;
        } elseif ($pct2 === null) {
            $pct2 = 100.0 - $pct1;
        }
        // Mixing with `transparent` keeps the color and multiplies the alpha
        if (strcasecmp($color2, 'transparent') === 0) {
            $parsed = self::parseStaticColor($color1);
            if ($parsed === null) {
                return null;
            }
            $alpha = $parsed['alpha'] * ($pct1 / 100.0);
            return self::serializeMixedColor($space, $parsed, $alpha);
        }
        // Static two-color mix: only the srgb space is required in practice
        if ($space === 'srgb') {
            $c1 = self::parseStaticColor($color1);
            $c2 = self::parseStaticColor($color2);
            if ($c1 === null || $c2 === null) {
                return null;
            }
            $w1 = $pct1 / 100.0;
            $w2 = $pct2 / 100.0;
            $total = $w1 + $w2;
            if ($total <= 0) {
                return null;
            }
            $w1 /= $total;
            $w2 /= $total;
            // Premultiplied interpolation in gamma-encoded sRGB
            $a1 = $c1['alpha'];
            $a2 = $c2['alpha'];
            $alpha = $a1 * $w1 + $a2 * $w2;
            if ($alpha <= 0) {
                return null;
            }
            $rgb = [];
            for ($i = 0; $i < 3; $i++) {
                $rgb[$i] = ($c1['rgbf'][$i] * $a1 * $w1 + $c2['rgbf'][$i] * $a2 * $w2) / $alpha;
            }
            return self::serializeSrgbHex($rgb, $alpha);
        }
        return null;
    }
    /**
     * Split a color-mix component into [color, percentage].
     *
     * @param string $part
     * @return array{0: ?string, 1: ?float}
     */
    private static function splitColorMixComponent(string $part): array
    {
        $part = trim($part);
        if ($part === '') {
            return [null, null];
        }
        // The percentage may be glued to the color: `oklch(…)50%`
        if (preg_match('/^(.*?)\s*([\d.]+)%$/s', $part, $m) && trim($m[1]) !== '') {
            return [trim($m[1]), (float) $m[2]];
        }
        return [$part, null];
    }
    /**
     * Parse a statically analyzable color into float RGB + alpha, keeping the
     * source oklch/oklab components when available for lossless re-serialization.
     *
     * @param string $color
     * @return array{rgbf: array{float, float, float}, alpha: float, oklch: ?array{float, float, float}, oklab: ?array{float, float, float}}|null
     */
    private static function parseStaticColor(string $color): ?array
    {
        $color = trim($color);
        // Hex colors
        if ($color !== '' && $color[0] === '#') {
            $hex = substr($color, 1);
            $alpha = 1.0;
            if (strlen($hex) === 3) {
                $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
            }
            if (strlen($hex) === 8) {
                $alpha = hexdec(substr($hex, 6, 2)) / 255;
                $hex = substr($hex, 0, 6);
            }
            if (strlen($hex) !== 6 || !ctype_xdigit($hex)) {
                return null;
            }
            return ['rgbf' => [hexdec(substr($hex, 0, 2)) / 255, hexdec(substr($hex, 2, 2)) / 255, hexdec(substr($hex, 4, 2)) / 255], 'alpha' => $alpha, 'oklch' => null, 'oklab' => null];
        }
        // oklch(L C H [/ A])
        if (preg_match('/^oklch\(\s*([\d.]+)(%?)\s+([\d.]+)\s+([\d.]+)\s*(?:\/\s*([\d.]+%?))?\s*\)$/i', $color, $m)) {
            $l = (float) $m[1];
            if ($m[2] === '%') {
                $l /= 100;
            }
            $c = (float) $m[3];
            $h = (float) $m[4];
            $alpha = self::parseAlphaComponent($m[5] ?? null);
            return ['rgbf' => self::oklchToSrgbGamutMapped($l, $c, $h), 'alpha' => $alpha, 'oklch' => [$l, $c, $h], 'oklab' => [$l, $c * cos(deg2rad($h)), $c * sin(deg2rad($h))]];
        }
        // oklab(L a b [/ A])
        if (preg_match('/^oklab\(\s*([\d.]+)(%?)\s+(-?[\d.]+)\s+(-?[\d.]+)\s*(?:\/\s*([\d.]+%?))?\s*\)$/i', $color, $m)) {
            $l = (float) $m[1];
            if ($m[2] === '%') {
                $l /= 100;
            }
            $a = (float) $m[3];
            $b = (float) $m[4];
            $alpha = self::parseAlphaComponent($m[5] ?? null);
            $c = sqrt($a * $a + $b * $b);
            $h = fmod(rad2deg(atan2($b, $a)) + 360, 360);
            return ['rgbf' => self::oklchToSrgbGamutMapped($l, $c, $h), 'alpha' => $alpha, 'oklch' => [$l, $c, $h], 'oklab' => [$l, $a, $b]];
        }
        // rgb() / rgba()
        if (preg_match('/^rgba?\(\s*(\d+)\s*,?\s*(\d+)\s*,?\s*(\d+)\s*(?:[\/,]\s*([\d.]+%?))?\s*\)$/i', $color, $m)) {
            return ['rgbf' => [(int) $m[1] / 255, (int) $m[2] / 255, (int) $m[3] / 255], 'alpha' => self::parseAlphaComponent($m[4] ?? null), 'oklch' => null, 'oklab' => null];
        }
        // Named colors
        $lower = strtolower($color);
        if (isset(self::NAMED_COLORS[$lower])) {
            $rgb = self::NAMED_COLORS[$lower];
            return ['rgbf' => [$rgb[0] / 255, $rgb[1] / 255, $rgb[2] / 255], 'alpha' => 1.0, 'oklch' => null, 'oklab' => null];
        }
        return null;
    }
    /**
     * Parse an alpha component (`0.5` or `50%`), defaulting to 1.
     */
    private static function parseAlphaComponent(?string $alpha): float
    {
        if ($alpha === null || $alpha === '') {
            return 1.0;
        }
        if (str_ends_with($alpha, '%')) {
            return (float) substr($alpha, 0, -1) / 100;
        }
        return (float) $alpha;
    }
    /**
     * Serialize a parsed color mixed down to $alpha in the given interpolation
     * space, matching lightningcss serialization.
     */
    private static function serializeMixedColor(string $space, array $parsed, float $alpha): ?string
    {
        switch ($space) {
            case 'srgb':
                return self::serializeSrgbHex($parsed['rgbf'], $alpha);
            case 'oklab':
                $oklab = $parsed['oklab'] ?? self::rgbfToOklab($parsed['rgbf']);
                return self::serializeOklab($oklab, $alpha);
            case 'oklch':
                if ($parsed['oklch'] !== null) {
                    [$l, $c, $h] = $parsed['oklch'];
                    $lStr = rtrim(rtrim(number_format($l * 100, 4, '.', ''), '0'), '.') . '%';
                    $cStr = self::formatMixComponent($c);
                    $hStr = self::formatMixComponent($h);
                    return "oklch({$lStr} {$cStr} {$hStr} / " . self::formatMixAlpha($alpha) . ')';
                }
                return null;
            case 'lch':
                [$l, $c, $h] = self::rgbfToLchD50($parsed['rgbf']);
                $lStr = self::formatMixComponent($l) . '%';
                $cStr = self::formatMixComponent($c);
                $hStr = self::formatMixComponent($h);
                return "lch({$lStr} {$cStr} {$hStr} / " . self::formatMixAlpha($alpha) . ')';
            default:
                return null;
        }
    }
    /**
     * Serialize float RGB + alpha as a hex color the way lightningcss does.
     */
    private static function serializeSrgbHex(array $rgbf, float $alpha): string
    {
        $r = (int) round(min(1.0, max(0.0, $rgbf[0])) * 255);
        $g = (int) round(min(1.0, max(0.0, $rgbf[1])) * 255);
        $b = (int) round(min(1.0, max(0.0, $rgbf[2])) * 255);
        $hex = sprintf('#%02x%02x%02x', $r, $g, $b);
        if ($alpha < 1.0) {
            $hex .= sprintf('%02x', (int) round($alpha * 255));
        }
        return $hex;
    }
    /**
     * Serialize an OKLab triple with alpha (lightningcss truncates the a/b
     * components to 3 decimals).
     */
    private static function serializeOklab(array $oklab, float $alpha): string
    {
        $l = round($oklab[0] * 100, 4);
        $a = floor($oklab[1] * 1000) / 1000;
        $b = floor($oklab[2] * 1000) / 1000;
        $lStr = rtrim(rtrim(number_format($l, 4, '.', ''), '0'), '.') . '%';
        $aStr = self::formatOklabComponent($a);
        $bStr = self::formatOklabComponent($b);
        return "oklab({$lStr} {$aStr} {$bStr} / " . self::formatMixAlpha($alpha) . ')';
    }
    /**
     * Format a mixed-color component to 4 decimals, trimming trailing zeros.
     * A +2e-6 bias approximates lightningcss's f32 serialization at rounding
     * boundaries (e.g. 89.790249… prints as 89.7903).
     */
    private static function formatMixComponent(float $value): string
    {
        $rounded = round($value + 2.0E-6, 4);
        $str = rtrim(rtrim(number_format($rounded, 4, '.', ''), '0'), '.');
        if ($str === '' || $str === '-0') {
            $str = '0';
        }
        return $str;
    }
    /**
     * Format a mixed-color alpha to 5 decimals, trimming zeros and the
     * leading zero (`0.25098` -> `.25098`).
     */
    private static function formatMixAlpha(float $alpha): string
    {
        $str = rtrim(rtrim(number_format(round($alpha, 5), 5, '.', ''), '0'), '.');
        if ($str === '') {
            $str = '0';
        }
        if (str_starts_with($str, '0.')) {
            $str = substr($str, 1);
        }
        return $str;
    }
    /**
     * OKLab -> linear sRGB (Björn Ottosson's reference matrices).
     */
    private static function oklabToLinearSrgb(float $l, float $a, float $b): array
    {
        $l_ = $l + 0.3963377774 * $a + 0.2158037573 * $b;
        $m_ = $l - 0.1055613458 * $a - 0.0638541728 * $b;
        $s_ = $l - 0.0894841775 * $a - 1.291485548 * $b;
        $lc = $l_ ** 3;
        $mc = $m_ ** 3;
        $sc = $s_ ** 3;
        return [4.0767416621 * $lc - 3.3077115913 * $mc + 0.2309699292 * $sc, -1.2684380046 * $lc + 2.6097574011 * $mc - 0.3413193965 * $sc, -0.0041960863 * $lc - 0.7034186147 * $mc + 1.707614701 * $sc];
    }
    /**
     * Linear sRGB -> OKLab.
     */
    private static function linearSrgbToOklab(float $r, float $g, float $b): array
    {
        $l = 0.4122214708 * $r + 0.5363325363 * $g + 0.0514459929 * $b;
        $m = 0.2119034982 * $r + 0.6806995451 * $g + 0.1073969566 * $b;
        $s = 0.0883024619 * $r + 0.2817188376 * $g + 0.6299787005 * $b;
        $cbrt = fn(float $x): float => $x < 0 ? -(-$x) ** (1 / 3) : $x ** (1 / 3);
        $l_ = $cbrt($l);
        $m_ = $cbrt($m);
        $s_ = $cbrt($s);
        return [0.2104542553 * $l_ + 0.793617785 * $m_ - 0.0040720468 * $s_, 1.9779984951 * $l_ - 2.428592205 * $m_ + 0.4505937099 * $s_, 0.0259040371 * $l_ + 0.7827717662 * $m_ - 0.808675766 * $s_];
    }
    private static function srgbGammaEncode(float $c): float
    {
        return $c <= 0.0031308 ? 12.92 * $c : 1.055 * $c ** (1 / 2.4) - 0.055;
    }
    private static function srgbGammaDecode(float $c): float
    {
        return $c <= 0.04045 ? $c / 12.92 : (($c + 0.055) / 1.055) ** 2.4;
    }
    /**
     * Gamma-encoded sRGB (floats) -> OKLab.
     */
    private static function rgbfToOklab(array $rgbf): array
    {
        return self::linearSrgbToOklab(self::srgbGammaDecode($rgbf[0]), self::srgbGammaDecode($rgbf[1]), self::srgbGammaDecode($rgbf[2]));
    }
    /**
     * OKLCH -> gamma-encoded sRGB, gamut-mapped the way lightningcss does:
     * bisect on chroma and return the first clipped candidate whose
     * deltaEOK against the reduced color is below the 0.02 JND.
     */
    private static function oklchToSrgbGamutMapped(float $l, float $c, float $h): array
    {
        $toGamma = function (float $chroma) use ($l, $h): array {
            $lin = self::oklabToLinearSrgb($l, $chroma * cos(deg2rad($h)), $chroma * sin(deg2rad($h)));
            return [self::srgbGammaEncode($lin[0]), self::srgbGammaEncode($lin[1]), self::srgbGammaEncode($lin[2])];
        };
        $inGamut = fn(array $rgb): bool => $rgb[0] >= 0 && $rgb[0] <= 1 && $rgb[1] >= 0 && $rgb[1] <= 1 && $rgb[2] >= 0 && $rgb[2] <= 1;
        $rgb = $toGamma($c);
        if ($inGamut($rgb)) {
            return $rgb;
        }
        if ($l >= 1) {
            return [1.0, 1.0, 1.0];
        }
        if ($l <= 0) {
            return [0.0, 0.0, 0.0];
        }
        $jnd = 0.02;
        $epsilon = 0.0001;
        $lo = 0.0;
        $hi = $c;
        $current = $rgb;
        while ($hi - $lo > $epsilon) {
            $chroma = ($lo + $hi) / 2;
            $current = $toGamma($chroma);
            if ($inGamut($current)) {
                $lo = $chroma;
                continue;
            }
            $clipped = [min(1.0, max(0.0, $current[0])), min(1.0, max(0.0, $current[1])), min(1.0, max(0.0, $current[2]))];
            $clippedOklab = self::rgbfToOklab($clipped);
            $currentOklab = [$l, $chroma * cos(deg2rad($h)), $chroma * sin(deg2rad($h))];
            $deltaE = sqrt(($clippedOklab[0] - $currentOklab[0]) ** 2 + ($clippedOklab[1] - $currentOklab[1]) ** 2 + ($clippedOklab[2] - $currentOklab[2]) ** 2);
            if ($deltaE < $jnd) {
                return $clipped;
            }
            $hi = $chroma;
        }
        return [min(1.0, max(0.0, $current[0])), min(1.0, max(0.0, $current[1])), min(1.0, max(0.0, $current[2]))];
    }
    /**
     * Gamma-encoded sRGB (floats) -> CIE LCH (D50), the space lightningcss
     * serializes `in lch` mixes in.
     */
    private static function rgbfToLchD50(array $rgbf): array
    {
        $rl = self::srgbGammaDecode($rgbf[0]);
        $gl = self::srgbGammaDecode($rgbf[1]);
        $bl = self::srgbGammaDecode($rgbf[2]);
        // linear sRGB -> XYZ (D65)
        $x = 0.41239079926595934 * $rl + 0.357584339383878 * $gl + 0.1804807884018343 * $bl;
        $y = 0.21263900587151027 * $rl + 0.715168678767756 * $gl + 0.07219231536073371 * $bl;
        $z = 0.01933081871559182 * $rl + 0.11919477979462598 * $gl + 0.9505321522496607 * $bl;
        // Bradford chromatic adaptation D65 -> D50
        $x2 = 1.0479298208405488 * $x + 0.022946793341019088 * $y - 0.05019222954313557 * $z;
        $y2 = 0.029627815688159344 * $x + 0.990434484573249 * $y - 0.01707382502938514 * $z;
        $z2 = -0.009243058152591178 * $x + 0.015055144896577895 * $y + 0.7518742899580008 * $z;
        // XYZ (D50) -> Lab
        $wx = 0.3457 / 0.3585;
        $wz = (1.0 - 0.3457 - 0.3585) / 0.3585;
        $f = function (float $t): float {
            $e = 216 / 24389;
            return $t > $e ? $t ** (1 / 3) : (24389 / 27 * $t + 16) / 116;
        };
        $fx = $f($x2 / $wx);
        $fy = $f($y2);
        $fz = $f($z2 / $wz);
        $labL = 116 * $fy - 16;
        $labA = 500 * ($fx - $fy);
        $labB = 200 * ($fy - $fz);
        $chroma = sqrt($labA * $labA + $labB * $labB);
        $hue = fmod(rad2deg(atan2($labB, $labA)) + 360, 360);
        return [$labL, $chroma, $hue];
    }
    /**
     * Format an OKLab a or b component.
     *
     * @param float $value The component value
     * @return string Formatted string (removes leading zero for decimals)
     */
    private static function formatOklabComponent(float $value): string
    {
        // Format to 3 decimals, remove trailing zeros
        $str = rtrim(rtrim(number_format($value, 3, '.', ''), '0'), '.');
        // Remove leading zero for positive decimals (0.224 -> .224)
        if (strpos($str, '0.') === 0) {
            $str = substr($str, 1);
        }
        return $str ?: '0';
    }
    /**
     * Convert RGB (0-255) to OKLab color space.
     *
     * @param int $r Red (0-255)
     * @param int $g Green (0-255)
     * @param int $b Blue (0-255)
     * @return array [L, a, b] where L is 0-1, a and b are roughly -0.4 to 0.4
     */
    private static function rgbToOklab(int $r, int $g, int $b): array
    {
        // Normalize to 0-1
        $r = $r / 255;
        $g = $g / 255;
        $b = $b / 255;
        // sRGB to linear RGB
        $r = $r <= 0.04045 ? $r / 12.92 : pow(($r + 0.055) / 1.055, 2.4);
        $g = $g <= 0.04045 ? $g / 12.92 : pow(($g + 0.055) / 1.055, 2.4);
        $b = $b <= 0.04045 ? $b / 12.92 : pow(($b + 0.055) / 1.055, 2.4);
        // Linear RGB to LMS
        $l = 0.4122214708 * $r + 0.5363325363 * $g + 0.0514459929 * $b;
        $m = 0.2119034982 * $r + 0.6806995451 * $g + 0.1073969566 * $b;
        $s = 0.0883024619 * $r + 0.2817188376 * $g + 0.6299787005 * $b;
        // LMS to OKLab
        $l_ = pow($l, 1 / 3);
        $m_ = pow($m, 1 / 3);
        $s_ = pow($s, 1 / 3);
        $L = 0.2104542553 * $l_ + 0.793617785 * $m_ - 0.0040720468 * $s_;
        $a = 1.9779984951 * $l_ - 2.428592205 * $m_ + 0.4505937099 * $s_;
        $b = 0.0259040371 * $l_ + 0.7827717662 * $m_ - 0.808675766 * $s_;
        return [$L, $a, $b];
    }
    /**
     * Convert a color with opacity to a solid oklab color (no alpha channel).
     *
     * This is used for --theme(--color/opacity inline) where we need to return
     * the actual computed color value, not a color-mix expression.
     *
     * @param string $color Color value (hex like #f00 or named like red)
     * @param float $alpha Alpha value (0-1)
     * @return string OKLab color string (e.g., oklab(62.7955% .224863 .125846))
     */
    public static function colorToOklabWithOpacity(string $color, float $alpha, bool $includeAlpha = \false): string
    {
        // Normalize color to RGB
        $color = strtolower(trim($color));
        $r = $g = $b = 0;
        // Handle named colors
        if (isset(self::NAMED_COLORS[$color])) {
            [$r, $g, $b] = self::NAMED_COLORS[$color];
        } elseif (preg_match('/^#([0-9a-f])([0-9a-f])([0-9a-f])$/i', $color, $match)) {
            $r = hexdec($match[1] . $match[1]);
            $g = hexdec($match[2] . $match[2]);
            $b = hexdec($match[3] . $match[3]);
        } elseif (preg_match('/^#([0-9a-f]{2})([0-9a-f]{2})([0-9a-f]{2})$/i', $color, $match)) {
            $r = hexdec($match[1]);
            $g = hexdec($match[2]);
            $b = hexdec($match[3]);
        } else {
            // Unknown format, return as-is
            return $color;
        }
        // Convert RGB to OKLab
        $oklab = self::rgbToOklab($r, $g, $b);
        // Format L - remove trailing zeros, add %
        $l = round($oklab[0] * 100, 4);
        $lStr = rtrim(rtrim(number_format($l, 4, '.', ''), '0'), '.') . '%';
        if ($includeAlpha) {
            // For stacking opacity - include alpha channel with standard precision
            // Truncate a and b to 3 decimal places (like floor but toward zero)
            $a = floor($oklab[1] * 1000) / 1000;
            $bComp = floor($oklab[2] * 1000) / 1000;
            // Format a and b - remove leading zero for decimals (0.224 -> .224)
            $aStr = self::formatOklabComponent($a);
            $bStr = self::formatOklabComponent($bComp);
            // Format alpha
            $alphaStr = rtrim(rtrim(number_format($alpha, 2, '.', ''), '0'), '.') ?: '0';
            // Remove leading zero from alpha too if it's a decimal
            if (strpos($alphaStr, '0.') === 0) {
                $alphaStr = substr($alphaStr, 1);
            }
            return "oklab({$lStr} {$aStr} {$bStr} / {$alphaStr})";
        } else {
            // For inline mode - no alpha channel, higher precision
            // LightningCSS uses higher precision for inline values
            $aStr = self::formatOklabComponentHighPrecision($oklab[1]);
            $bStr = self::formatOklabComponentHighPrecision($oklab[2]);
            return "oklab({$lStr} {$aStr} {$bStr})";
        }
    }
    /**
     * Format an OKLab a or b component with higher precision (for inline mode).
     *
     * @param float $value The component value
     * @return string Formatted string
     */
    private static function formatOklabComponentHighPrecision(float $value): string
    {
        // Format to 6 decimals, remove trailing zeros
        $str = rtrim(rtrim(number_format($value, 6, '.', ''), '0'), '.');
        // Remove leading zero for positive decimals (0.224 -> .224)
        if (strpos($str, '0.') === 0) {
            $str = substr($str, 1);
        }
        return $str ?: '0';
    }
    /**
     * Apply alpha to a color value and return hex with alpha.
     *
     * @param string $color Color value (hex like #f00 or named like red)
     * @param float $alpha Alpha value (0-1)
     * @return string Hex color with alpha (e.g., #ff000080)
     */
    public static function colorWithAlpha(string $color, float $alpha): string
    {
        // Normalize color to RGB
        $color = strtolower(trim($color));
        $r = $g = $b = 0;
        // Handle named colors
        if (isset(self::NAMED_COLORS[$color])) {
            [$r, $g, $b] = self::NAMED_COLORS[$color];
        } elseif (preg_match('/^#([0-9a-f])([0-9a-f])([0-9a-f])$/i', $color, $match)) {
            $r = hexdec($match[1] . $match[1]);
            $g = hexdec($match[2] . $match[2]);
            $b = hexdec($match[3] . $match[3]);
        } elseif (preg_match('/^#([0-9a-f]{2})([0-9a-f]{2})([0-9a-f]{2})$/i', $color, $match)) {
            $r = hexdec($match[1]);
            $g = hexdec($match[2]);
            $b = hexdec($match[3]);
        } elseif (preg_match('/^#([0-9a-f]{2})([0-9a-f]{2})([0-9a-f]{2})([0-9a-f]{2})$/i', $color, $match)) {
            $r = hexdec($match[1]);
            $g = hexdec($match[2]);
            $b = hexdec($match[3]);
            // Multiply existing alpha with new alpha
            $existingAlpha = hexdec($match[4]) / 255;
            $alpha = $alpha * $existingAlpha;
        } else {
            // Unknown format, return as-is
            return $color;
        }
        // Convert alpha to 2-digit hex
        $alphaHex = str_pad(dechex((int) round($alpha * 255)), 2, '0', \STR_PAD_LEFT);
        return '#' . str_pad(dechex($r), 2, '0', \STR_PAD_LEFT) . str_pad(dechex($g), 2, '0', \STR_PAD_LEFT) . str_pad(dechex($b), 2, '0', \STR_PAD_LEFT) . $alphaHex;
    }
    /**
     * Process @custom-media rules and substitute them in @media queries.
     *
     * LightningCSS with `customMedia: true` handles:
     * 1. Collects @custom-media definitions
     * 2. Substitutes custom media names in @media rules
     * 3. Removes @custom-media rules from output
     *
     * @param array $ast The CSS AST
     * @return array Transformed AST with custom media substituted
     */
    public static function processCustomMedia(array $ast): array
    {
        // First pass: collect @custom-media definitions
        $customMedia = [];
        foreach ($ast as $node) {
            if ($node['kind'] === 'at-rule' && $node['name'] === '@custom-media') {
                // Parse @custom-media --name (query)
                $params = $node['params'] ?? '';
                if (preg_match('/^(--[\w-]+)\s+(.+)$/', trim($params), $match)) {
                    $name = $match[1];
                    $query = $match[2];
                    $customMedia[$name] = $query;
                }
            }
        }
        if (empty($customMedia)) {
            return $ast;
        }
        // Second pass: substitute and remove @custom-media
        $result = [];
        foreach ($ast as $node) {
            // Remove @custom-media rules
            if ($node['kind'] === 'at-rule' && $node['name'] === '@custom-media') {
                continue;
            }
            // Substitute in @media rules
            if ($node['kind'] === 'at-rule' && $node['name'] === '@media') {
                $params = $node['params'] ?? '';
                // Check if params references a custom media query (--name)
                foreach ($customMedia as $name => $query) {
                    // Replace (--name) with the query
                    $params = preg_replace('/\(\s*' . preg_quote($name, '/') . '\s*\)/', $query, $params);
                }
                $node['params'] = $params;
            }
            // Recursively process nested nodes
            if (isset($node['nodes'])) {
                $node['nodes'] = self::processCustomMedia($node['nodes']);
            }
            $result[] = $node;
        }
        return $result;
    }
    /**
     * Transform media query range syntax to standard syntax.
     *
     * LightningCSS transforms Media Queries Level 4 range syntax:
     * - (width >= 48rem) → (min-width: 48rem)
     * - (width <= 48rem) → (max-width: 48rem)
     * - (width > 48rem) → (min-width: 48rem)
     * - (width < 48rem) → (not (min-width: 48rem))
     *
     * @param string $query The media query params
     * @return string Transformed query with standard syntax
     */
    public static function transformMediaQueryRange(string $query): string
    {
        // `not (feature)` without a media type is normalized by lightningcss
        // to `not all and (feature)`
        if (preg_match('/^\s*not\s+\(/', $query)) {
            $query = preg_replace('/^\s*not\s+/', 'not all and ', $query, 1);
        }
        // Pattern for (width >= value) or (width <= value)
        // Also handles height, device-width, device-height, etc.
        // (width >= value) → (min-width: value)
        $query = preg_replace_callback('/\(\s*(width|height|device-width|device-height)\s*>=\s*([^)]+)\s*\)/', function ($match) {
            $prop = $match[1];
            $value = trim($match[2]);
            return "(min-{$prop}: {$value})";
        }, $query);
        // (width <= value) → (max-width: value)
        $query = preg_replace_callback('/\(\s*(width|height|device-width|device-height)\s*<=\s*([^)]+)\s*\)/', function ($match) {
            $prop = $match[1];
            $value = trim($match[2]);
            return "(max-{$prop}: {$value})";
        }, $query);
        // (width > value) → (min-width: value)
        $query = preg_replace_callback('/\(\s*(width|height|device-width|device-height)\s*>\s*([^)]+)\s*\)/', function ($match) {
            $prop = $match[1];
            $value = trim($match[2]);
            return "(min-{$prop}: {$value})";
        }, $query);
        // (width < value) → (not (min-width: value))
        // LightningCSS uses negation for strict less-than
        $query = preg_replace_callback('/\(\s*(width|height|device-width|device-height)\s*<\s*([^)]+)\s*\)/', function ($match) {
            $prop = $match[1];
            $value = trim($match[2]);
            return "(not (min-{$prop}: {$value}))";
        }, $query);
        return $query;
    }
    /**
     * Transform container query range syntax to standard syntax.
     *
     * Container queries use slightly different transformations:
     * - (width >= 48rem) → (min-width: 48rem)
     * - (width <= 48rem) → (max-width: 48rem)
     * - (width > 48rem) → not (max-width: 48rem)
     * - (width < 48rem) → (not (min-width: 48rem))
     *
     * @param string $query The container query params
     * @return string Transformed query with standard syntax
     */
    public static function transformContainerQueryRange(string $query): string
    {
        // (width >= value) → (min-width: value)
        $query = preg_replace_callback('/\(\s*(width|height)\s*>=\s*([^)]+)\s*\)/', function ($match) {
            $prop = $match[1];
            $value = trim($match[2]);
            return "(min-{$prop}: {$value})";
        }, $query);
        // (width <= value) → (max-width: value)
        $query = preg_replace_callback('/\(\s*(width|height)\s*<=\s*([^)]+)\s*\)/', function ($match) {
            $prop = $match[1];
            $value = trim($match[2]);
            return "(max-{$prop}: {$value})";
        }, $query);
        // (width > value) → not (max-width: value)
        // Container queries use "not (max-width)" for strict greater-than
        $query = preg_replace_callback('/\(\s*(width|height)\s*>\s*([^)]+)\s*\)/', function ($match) {
            $prop = $match[1];
            $value = trim($match[2]);
            return "not (max-{$prop}: {$value})";
        }, $query);
        // (width < value) → (not (min-width: value))
        $query = preg_replace_callback('/\(\s*(width|height)\s*<\s*([^)]+)\s*\)/', function ($match) {
            $prop = $match[1];
            $value = trim($match[2]);
            return "(not (min-{$prop}: {$value}))";
        }, $query);
        return $query;
    }
    /**
     * Process media and container query range syntax in the AST.
     *
     * @param array $ast The CSS AST
     * @return array Transformed AST
     */
    public static function processQueryRangeSyntax(array $ast): array
    {
        $result = [];
        foreach ($ast as $node) {
            if ($node['kind'] === 'at-rule') {
                if ($node['name'] === '@media' && isset($node['params'])) {
                    $node['params'] = self::transformMediaQueryRange($node['params']);
                } elseif ($node['name'] === '@container' && isset($node['params'])) {
                    $node['params'] = self::transformContainerQueryRange($node['params']);
                }
            }
            // Recursively process nested nodes
            if (isset($node['nodes'])) {
                $node['nodes'] = self::processQueryRangeSyntax($node['nodes']);
            }
            $result[] = $node;
        }
        return $result;
    }
}
