<?php

declare (strict_types=1);
namespace BlockstudioVendor\TailwindPHP\LightningCss;

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
        $value = self::simplifyCalcExpressions($value);
        $value = self::normalizeTimeValues($value);
        $value = self::normalizeOpacityPercentages($value, $property);
        $value = self::normalizeColors($value, $isCustomProperty);
        $value = self::evaluateColorMix($value);
        // Evaluate color-mix AFTER normalizeColors converts hex to named
        $value = self::normalizeLeadingZeros($value);
        $value = self::normalizeGridValues($value, $property);
        $value = self::normalizeTransformFunctions($value, $property);
        $value = self::normalizeAnimationValue($value, $property);
        $value = self::normalizeUrlQuoting($value);
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
        static $nameToHex = ['blue' => '#00f', 'lime' => '#0f0', 'aqua' => '#0ff', 'cyan' => '#0ff', 'fuchsia' => '#f0f', 'magenta' => '#f0f', 'yellow' => '#ff0'];
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
    public static function simplifyCalcExpressions(string $value): string
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
        return $value;
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
     * lightningcss removes spaces between consecutive transform functions:
     * "scaleZ(2) rotateY(45deg)" -> "scaleZ(2)rotateY(45deg)"
     *
     * Only applies to values without var() calls, as those need spaces preserved.
     *
     * @param string $value The CSS value
     * @param string $property The CSS property name
     * @return string Normalized value
     */
    public static function normalizeTransformFunctions(string $value, string $property = ''): string
    {
        // Only apply to transform property
        if ($property !== 'transform') {
            return $value;
        }
        // Don't apply to values with CSS variables - they need spaces preserved
        // e.g., "var(--tw-rotate-x, ) var(--tw-rotate-y, )"
        if (str_contains($value, 'var(')) {
            return $value;
        }
        // Remove spaces between consecutive transform functions
        // Match ") " followed by a function name and "("
        return preg_replace('/\)\s+([a-zA-Z]+\()/', ')$1', $value);
    }
    /**
     * Transform CSS nesting to flat CSS.
     *
     * Handles:
     * - `&:hover` style selectors → resolved with parent selector
     * - `@media` hoisting → moved to top level
     *
     * @param array $ast The CSS AST
     * @return array Transformed AST with flat selectors
     */
    public static function transformNesting(array $ast): array
    {
        $result = [];
        $atRules = [];
        // Collected @media and other at-rules
        foreach ($ast as $node) {
            self::flattenNode($node, $result, $atRules, null);
        }
        // Merge at-rules with same params
        $mergedAtRules = self::mergeAtRules($atRules);
        // Append at-rules at the end (they should come after regular rules)
        return array_merge($result, $mergedAtRules);
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
            // Resolve & in selector, or prepend parent selector if no &
            if ($parentSelector !== null) {
                if (str_contains($selector, '&')) {
                    $selector = str_replace('&', $parentSelector, $selector);
                } else {
                    // Nested selector without & - prepend parent to EACH selector in list
                    // e.g., ".parent" + "h1, h2, h3" -> ".parent h1, .parent h2, .parent h3"
                    // Must split on top-level commas only (not inside :where(), :not(), etc.)
                    $selectors = self::splitSelectorList($selector);
                    $selectors = array_map(fn($s) => $parentSelector . ' ' . trim($s), $selectors);
                    $selector = implode(', ', $selectors);
                }
            }
            // LightningCSS normalizes `*::pseudo` to ` ::pseudo` since `*` is implicit
            // e.g., `.foo *::selection` becomes `.foo ::selection`
            $selector = preg_replace('/\s\*::/', ' ::', $selector);
            // If this is a nested rule inside a parent rule
            $declarations = [];
            $nestedRules = [];
            foreach ($node['nodes'] ?? [] as $child) {
                if ($child['kind'] === 'declaration') {
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
            // Handle @layer specially - it should NOT have its contents hoisted
            if ($node['name'] === '@layer') {
                // Process layer contents but keep them inside the layer
                $layerNodes = [];
                $layerAtRules = [];
                // Separate at-rules collector for layer contents
                foreach ($node['nodes'] ?? [] as $child) {
                    self::flattenNode($child, $layerNodes, $layerAtRules, $parentSelector);
                }
                // Merge at-rules collected within the layer
                $mergedLayerAtRules = self::mergeAtRules($layerAtRules);
                // Combine regular nodes with merged at-rules (at-rules go at end)
                $allLayerNodes = array_merge($layerNodes, $mergedLayerAtRules);
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
            // For at-rules like @media, @supports, @starting-style
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
                // Process nested rules - use LOCAL atRules collector for nested at-rules
                // so they stay as children of this at-rule, not siblings
                $nestedAtRules = [];
                foreach ($nestedRules as $child) {
                    self::flattenNode($child, $flattenedNodes, $nestedAtRules, $parentSelector);
                }
                // Merge any nested at-rules and append to flattened nodes
                $mergedNestedAtRules = self::mergeAtRules($nestedAtRules);
                $flattenedNodes = array_merge($flattenedNodes, $mergedNestedAtRules);
                if (!empty($flattenedNodes)) {
                    $atRules[] = ['kind' => 'at-rule', 'name' => $node['name'], 'params' => $node['params'], 'nodes' => $flattenedNodes];
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
                if ($lastDeclKey === $declKey && $lastIndex === count($result) - 1 && $lastIndex >= 0) {
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
    private static function serializeDeclarations(array $nodes): string
    {
        $parts = [];
        foreach ($nodes as $node) {
            if ($node['kind'] === 'declaration') {
                $important = !empty($node['important']) ? '!important' : '';
                $parts[] = ($node['property'] ?? '') . ':' . ($node['value'] ?? '') . $important;
            } elseif ($node['kind'] === 'rule') {
                // Include nested rules in serialization
                $parts[] = 'rule:' . ($node['selector'] ?? '') . '{' . self::serializeDeclarations($node['nodes'] ?? []) . '}';
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
            $result[] = ['kind' => 'declaration', 'property' => $prefixedProp, 'value' => $decl['value'] ?? '', 'important' => $decl['important'] ?? \false];
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
        // First try oklch() colors: color-mix(in oklab, oklch(...) PERCENTAGE%, transparent)
        if (preg_match('/color-mix\(in oklab,\s*(oklch\([^)]+\))\s+(\d+(?:\.\d+)?)%?,\s*transparent\)/i', $value, $match)) {
            $oklchColor = $match[1];
            $percentage = floatval($match[2]);
            $alpha = $percentage / 100;
            // Format alpha
            $alphaStr = rtrim(rtrim(number_format($alpha, 2, '.', ''), '0'), '.') ?: '0';
            if (strpos($alphaStr, '0.') === 0) {
                $alphaStr = substr($alphaStr, 1);
            }
            // Insert alpha into oklch: oklch(L C H) -> oklch(L C H / alpha)
            $oklchWithAlpha = preg_replace('/\)$/', " / {$alphaStr})", $oklchColor);
            return str_replace($match[0], $oklchWithAlpha, $value);
        }
        // Then try named colors: color-mix(in oklab, COLOR PERCENTAGE%, transparent)
        if (!preg_match('/color-mix\(in oklab,\s*([a-z]+)\s+(\d+(?:\.\d+)?)%?,\s*transparent\)/i', $value, $match)) {
            return $value;
        }
        $colorName = strtolower($match[1]);
        $percentage = floatval($match[2]);
        // Only convert if we know the color
        if (!isset(self::NAMED_COLORS[$colorName])) {
            return $value;
        }
        $rgb = self::NAMED_COLORS[$colorName];
        $alpha = $percentage / 100;
        // Convert RGB to OKLab
        $oklab = self::rgbToOklab($rgb[0], $rgb[1], $rgb[2]);
        // Format: oklab(L% a b / alpha)
        // LightningCSS truncates to 3 decimal places (floor toward zero)
        $l = round($oklab[0] * 100, 4);
        // Truncate a and b to 3 decimal places (like floor but toward zero)
        $a = floor($oklab[1] * 1000) / 1000;
        $b = floor($oklab[2] * 1000) / 1000;
        // Format L - remove trailing zeros, add %
        $lStr = rtrim(rtrim(number_format($l, 4, '.', ''), '0'), '.') . '%';
        // Format a and b - remove leading zero for decimals (0.224 -> .224)
        $aStr = self::formatOklabComponent($a);
        $bStr = self::formatOklabComponent($b);
        // Format alpha
        $alphaStr = rtrim(rtrim(number_format($alpha, 2, '.', ''), '0'), '.') ?: '0';
        // Remove leading zero from alpha too if it's a decimal
        if (strpos($alphaStr, '0.') === 0) {
            $alphaStr = substr($alphaStr, 1);
        }
        $oklabValue = "oklab({$lStr} {$aStr} {$bStr} / {$alphaStr})";
        return str_replace($match[0], $oklabValue, $value);
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
