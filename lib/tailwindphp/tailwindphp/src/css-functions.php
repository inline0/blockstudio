<?php

declare (strict_types=1);
namespace BlockstudioVendor\TailwindPHP;

use function BlockstudioVendor\TailwindPHP\Utilities\withAlpha;
use function BlockstudioVendor\TailwindPHP\Utils\segment;
use function BlockstudioVendor\TailwindPHP\Utils\toKeyPath;
use function BlockstudioVendor\TailwindPHP\ValueParser\parse as parseValue;
use function BlockstudioVendor\TailwindPHP\ValueParser\toCss;
use function BlockstudioVendor\TailwindPHP\Walk\walk;
use BlockstudioVendor\TailwindPHP\Walk\WalkAction;
/**
 * Exception thrown when a theme value cannot be resolved.
 * This is caught during candidate compilation to skip invalid candidates.
 */
class ThemeResolutionException extends \Exception
{
}
/**
 * CSS Functions
 *
 * Port of: packages/tailwindcss/src/css-functions.ts
 *
 * @port-deviation:dispatch TypeScript uses CSS_FUNCTIONS object with dynamic dispatch.
 * PHP uses individual function handlers (handleAlpha, handleSpacing, etc.) for clarity.
 *
 * @port-deviation:errors TypeScript throws errors for invalid theme() usage.
 * PHP throws ThemeResolutionException which is caught during candidate compilation
 * to filter out invalid candidates (matching TypeScript behavior).
 *
 * @port-deviation:fallback-injection Implemented injectFallbackForInitialFallback() and
 * resolveNestedThemeCallsForInitial() to handle --theme(--var, initial) patterns.
 * When a theme value resolves to 'initial', caller's fallback is injected into var().
 *
 * @port-deviation:namespace-fallback Added keyPathToCssPropertyDirect() as fallback
 * for theme() lookups. When OLD_TO_NEW_NAMESPACE mapping fails (e.g., --font-sans),
 * tries direct kebab-case conversion (e.g., --font-family-sans).
 *
 * Handles CSS function substitution for:
 * - --alpha(color / opacity)
 * - --spacing(multiplier)
 * - --theme(path) with initial fallback handling
 * - theme(path) (legacy) with namespace mapping and fallback
 */
/**
 * Pattern to detect theme function invocations.
 */
const THEME_FUNCTION_INVOCATION = '/(?:--alpha|--spacing|--theme|theme)\(/';
/**
 * Substitute CSS functions in the AST.
 *
 * If a theme() call cannot be resolved (no fallback and value doesn't exist),
 * the containing rule is removed from the AST.
 *
 * @param array &$ast CSS AST
 * @param object $designSystem Design system instance
 * @return int Features flags
 */
function substituteFunctions(array &$ast, object $designSystem): int
{
    $features = FEATURE_NONE;
    $nodesToRemove = [];
    walk($ast, function (&$node) use ($designSystem, &$features, &$nodesToRemove) {
        // Find all declaration values
        if ($node['kind'] === 'declaration' && isset($node['value']) && preg_match(THEME_FUNCTION_INVOCATION, $node['value'])) {
            $features |= FEATURE_AT_THEME;
            try {
                $node['value'] = substituteFunctionsInValue($node['value'], $node, $designSystem);
            } catch (ThemeResolutionException $e) {
                // Mark this node's parent rule for removal
                $node['__invalid'] = \true;
            }
            return WalkAction::Skip;
        }
        // Find at-rules
        if ($node['kind'] === 'at-rule') {
            $name = $node['name'] ?? '';
            if (($name === '@media' || $name === '@custom-media' || $name === '@container' || $name === '@supports') && isset($node['params']) && preg_match(THEME_FUNCTION_INVOCATION, $node['params'])) {
                $features |= FEATURE_AT_THEME;
                try {
                    $node['params'] = substituteFunctionsInValue($node['params'], $node, $designSystem);
                } catch (ThemeResolutionException $e) {
                    $node['__invalid'] = \true;
                }
            }
        }
        return WalkAction::Continue;
    });
    // Remove rules containing invalid declarations
    $ast = filterInvalidNodes($ast);
    return $features;
}
/**
 * Recursively filter out nodes marked as invalid and rules containing invalid declarations.
 *
 * @param array $ast AST nodes
 * @return array Filtered AST
 */
function filterInvalidNodes(array $ast): array
{
    $result = [];
    foreach ($ast as $node) {
        // Skip nodes marked as invalid
        if (!empty($node['__invalid'])) {
            continue;
        }
        // For rules, check if any declaration is invalid
        if ($node['kind'] === 'rule' && isset($node['nodes'])) {
            $hasInvalid = \false;
            foreach ($node['nodes'] as $child) {
                if (!empty($child['__invalid'])) {
                    $hasInvalid = \true;
                    break;
                }
            }
            if ($hasInvalid) {
                continue;
            }
        }
        // Recursively filter children
        if (isset($node['nodes']) && is_array($node['nodes'])) {
            $node['nodes'] = filterInvalidNodes($node['nodes']);
        }
        $result[] = $node;
    }
    return $result;
}
/**
 * Substitute CSS functions in a value string.
 *
 * @param string $value Value string
 * @param array $source Source AST node
 * @param object $designSystem Design system instance
 * @return string Substituted value
 */
function substituteFunctionsInValue(string $value, array $source, object $designSystem): string
{
    $ast = parseValue($value);
    walk($ast, function (&$node, $ctx) use ($designSystem, $source) {
        if ($node['kind'] !== 'function') {
            return WalkAction::Continue;
        }
        $funcName = $node['value'];
        // Handle theme() function (legacy)
        if ($funcName === 'theme') {
            $result = handleLegacyTheme($node, $designSystem);
            if ($result !== null) {
                return WalkAction::Replace(parseValue($result));
            }
        }
        // Handle --theme() function
        if ($funcName === '--theme') {
            $result = handleTheme($node, $source, $designSystem);
            if ($result !== null) {
                return WalkAction::Replace(parseValue($result));
            }
        }
        // Handle --spacing() function
        if ($funcName === '--spacing') {
            $result = handleSpacing($node, $designSystem);
            if ($result !== null) {
                return WalkAction::Replace(parseValue($result));
            }
        }
        // Handle --alpha() function
        if ($funcName === '--alpha') {
            $result = handleAlpha($node, $designSystem);
            if ($result !== null) {
                return WalkAction::Replace(parseValue($result));
            }
        }
        return WalkAction::Continue;
    });
    return toCss($ast);
}
/**
 * Map v3 namespace names to v4 CSS variable namespaces.
 */
const OLD_TO_NEW_NAMESPACE = ['animation' => 'animate', 'aspectRatio' => 'aspect', 'borderRadius' => 'radius', 'boxShadow' => 'shadow', 'colors' => 'color', 'containers' => 'container', 'fontFamily' => 'font', 'fontSize' => 'text', 'letterSpacing' => 'tracking', 'lineHeight' => 'leading', 'maxWidth' => 'container', 'screens' => 'breakpoint', 'transitionTimingFunction' => 'ease'];
/**
 * Convert a key path to a CSS property name.
 *
 * @param array $path Key path segments
 * @return string|null CSS property name (without --)
 */
function keyPathToCssProperty(array $path): ?string
{
    if (empty($path)) {
        return null;
    }
    // The legacy container component config should not be included in the Theme
    if ($path[0] === 'container') {
        return null;
    }
    // Map old v3 namespaces to new theme namespaces
    $ns = OLD_TO_NEW_NAMESPACE[$path[0]] ?? null;
    if ($ns !== null) {
        $path[0] = $ns;
    }
    // Convert path segments
    $result = array_map(function ($part, $idx) use ($path) {
        // Replace dots with underscores (for values like 2.5 -> 2_5)
        $part = str_replace('.', '_', $part);
        // Convert camelCase to kebab-case for the first segment (namespace)
        // and for certain special keys like lineHeight
        $shouldConvert = $idx === 0 || str_starts_with($part, '-') || $part === 'lineHeight';
        if ($shouldConvert) {
            $part = preg_replace('/([a-z])([A-Z])/', '$1-$2', $part);
            $part = strtolower($part);
        }
        return $part;
    }, $path, array_keys($path));
    // Remove the `DEFAULT` key at the end of a path
    if (end($result) === 'DEFAULT') {
        array_pop($result);
    }
    // Handle '1' as a special separator for nested tuple values
    // e.g., fontSize.xs.1.lineHeight -> text-xs--line-height
    $result = array_map(function ($part, $idx) use ($result) {
        return $part === '1' && $idx !== count($result) - 1 ? '' : $part;
    }, $result, array_keys($result));
    return implode('-', $result);
}
/**
 * Convert a key path to a CSS property name using direct kebab-case conversion.
 *
 * Unlike keyPathToCssProperty, this does NOT apply namespace remapping.
 * e.g., "fontFamily.sans" -> "font-family-sans" (not "font-sans")
 *
 * @param array $path Key path segments
 * @return string|null CSS property name (without --)
 */
function keyPathToCssPropertyDirect(array $path): ?string
{
    if (empty($path)) {
        return null;
    }
    // Convert path segments with direct kebab-case (no namespace remapping)
    $result = array_map(function ($part, $idx) {
        // Replace dots with underscores (for values like 2.5 -> 2_5)
        $part = str_replace('.', '_', $part);
        // Convert camelCase to kebab-case for all segments
        $part = preg_replace('/([a-z])([A-Z])/', '$1-$2', $part);
        $part = strtolower($part);
        return $part;
    }, $path, array_keys($path));
    // Remove the `DEFAULT` key at the end of a path
    if (end($result) === 'DEFAULT') {
        array_pop($result);
    }
    return implode('-', $result);
}
/**
 * Handle legacy theme() function.
 *
 * @param array $node Function node
 * @param object $designSystem Design system instance
 * @param int $depth Current recursion depth for nested resolution
 * @return string|null Resolved value or null
 */
function handleLegacyTheme(array $node, object $designSystem, int $depth = 0): ?string
{
    // Prevent infinite recursion from circular references
    if ($depth >= MAX_THEME_RECURSION_DEPTH) {
        return null;
    }
    $argsStr = toCss($node['nodes'] ?? []);
    $args = array_map('trim', segment(trim($argsStr), ','));
    if (empty($args) || $args[0] === '') {
        return null;
    }
    $path = eventuallyUnquote($args[0]);
    $fallback = array_slice($args, 1);
    $theme = $designSystem->getTheme();
    // Check for modifier (opacity) e.g., "colors.red.500 / 50%"
    $modifier = null;
    $lastSlash = strrpos($path, '/');
    if ($lastSlash !== \false) {
        $modifier = trim(substr($path, $lastSlash + 1));
        $path = trim(substr($path, 0, $lastSlash));
    }
    // If path already starts with --, use it directly
    if (str_starts_with($path, '--')) {
        $cssVar = $path;
        $cssVarAlt = null;
    } else {
        // Convert legacy dot notation to CSS variable name
        // e.g., "colors.red.500" -> "--color-red-500"
        $keyPath = toKeyPath($path);
        $cssProperty = keyPathToCssProperty($keyPath);
        if ($cssProperty === null) {
            return null;
        }
        $cssVar = '--' . $cssProperty;
        // Also try direct kebab-case conversion as fallback
        // This handles cases like fontFamily.sans -> --font-family-sans
        // when the theme defines the variable directly without namespace mapping
        $cssVarAlt = '--' . keyPathToCssPropertyDirect($keyPath);
    }
    // Resolve the theme value using resolveValue to get the raw value
    // Try primary mapping first, then fall back to direct mapping
    $resolvedValue = $theme->resolveValue(null, [$cssVar]);
    if ($resolvedValue === null && $cssVarAlt !== null && $cssVarAlt !== $cssVar) {
        $resolvedValue = $theme->resolveValue(null, [$cssVarAlt]);
        if ($resolvedValue !== null) {
            $cssVar = $cssVarAlt;
        }
    }
    // Recursively resolve any nested theme() calls in the resolved value
    if ($resolvedValue !== null && str_contains($resolvedValue, 'theme(')) {
        $resolvedValue = resolveNestedThemeCalls($resolvedValue, $designSystem, $depth);
    }
    if ($resolvedValue === null) {
        if (!empty($fallback)) {
            // Recursively resolve any nested theme() calls in fallback
            $fallbackStr = implode(', ', $fallback);
            if (str_contains($fallbackStr, 'theme(')) {
                $fallbackAst = parseValue($fallbackStr);
                walk($fallbackAst, function (&$fNode) use ($designSystem, $depth) {
                    if ($fNode['kind'] === 'function' && $fNode['value'] === 'theme') {
                        $result = handleLegacyTheme($fNode, $designSystem, $depth + 1);
                        if ($result !== null) {
                            return WalkAction::Replace(parseValue($result));
                        }
                    }
                    return WalkAction::Continue;
                });
                return toCss($fallbackAst);
            }
            return $fallbackStr;
        }
        // Throw an exception to indicate unresolvable theme value
        // This will be caught during candidate compilation to skip invalid candidates
        throw new ThemeResolutionException("Could not resolve value for theme function: `theme({$path})`. Consider checking if the path is correct or provide a fallback value.");
    }
    // Apply opacity modifier if present
    if ($modifier !== null && $modifier !== '') {
        // Check if the modifier is a static value (can be inlined) or dynamic (contains var())
        // For static values, use inline mode to compute the actual oklab value
        // This enables proper stacking of opacity (50% on 50% = 25%)
        $isStaticOpacity = !str_contains($modifier, 'var(');
        return withAlpha($resolvedValue, $modifier, $isStaticOpacity);
    }
    return $resolvedValue;
}
/**
 * Handle --theme() function.
 *
 * The --theme() function resolves CSS variables from @theme blocks:
 * - --theme(--color-red-500) → var(--color-red-500)
 * - --theme(--color-red-500 inline) → red (the actual value)
 * - --theme(--color-red-500, fallback) → var(--color-red-500, fallback) or fallback if not found
 *
 * @param array $node Function node
 * @param array $source Source AST node
 * @param object $designSystem Design system instance
 * @return string|null Resolved value or null
 */
function handleTheme(array $node, array $source, object $designSystem): ?string
{
    $argsStr = toCss($node['nodes'] ?? []);
    $args = array_map('trim', segment(trim($argsStr), ','));
    if (empty($args) || $args[0] === '') {
        return null;
    }
    $path = $args[0];
    $fallback = array_slice($args, 1);
    if (!str_starts_with($path, '--')) {
        return null;
    }
    $inline = \false;
    // Handle `--theme(… inline)` to force inline resolution
    if (str_ends_with($path, ' inline')) {
        $inline = \true;
        $path = substr($path, 0, -7);
    }
    // If the --theme() function is used within an at-rule, always inline
    if (($source['kind'] ?? '') === 'at-rule') {
        $inline = \true;
    }
    // Check for opacity modifier: --theme(--color-red-500/0.5) or --theme(--color-red-500/50)
    $opacity = null;
    $slashPos = strrpos($path, '/');
    if ($slashPos !== \false) {
        $opacity = trim(substr($path, $slashPos + 1));
        $path = trim(substr($path, 0, $slashPos));
    }
    $theme = $designSystem->getTheme();
    $prefix = $theme->getPrefix();
    // Apply prefix to the variable name for output (var() references)
    $prefixedPath = $path;
    if ($prefix !== null && str_starts_with($path, '--')) {
        // Convert --color-red-500 to --tw-color-red-500 (with prefix)
        $prefixedPath = '--' . $prefix . '-' . substr($path, 2);
    }
    // Theme stores values WITHOUT prefix, so we look up using the unprefixed path
    // The prefix is only applied during output (in entries() and when generating var())
    $value = $theme->get([$path]);
    if ($value === null) {
        // Value not found - use fallback if provided
        if (!empty($fallback)) {
            return implode(', ', $fallback);
        }
        return null;
    }
    // If the stored value contains a --theme() call, we need to resolve it
    // This handles cases like: --default-font-family: --theme(--font-family, initial);
    if (str_contains($value, '--theme(')) {
        $resolvedValue = resolveNestedThemeCallsForInitial($value, $designSystem, $inline);
        $value = $resolvedValue;
    }
    $joinedFallback = !empty($fallback) ? implode(', ', $fallback) : null;
    // If the caller's fallback is 'initial', just return the resolved value as-is
    if ($joinedFallback === 'initial') {
        if ($inline) {
            return $value;
        }
        return "var({$prefixedPath})";
    }
    // Handle 'initial' value - this means the variable's own --theme() call resolved to 'initial'
    // (typically because the referenced variable doesn't exist and the fallback was 'initial')
    if ($value === 'initial') {
        if ($inline) {
            // For inline mode, return the fallback directly
            if ($joinedFallback !== null) {
                return $joinedFallback;
            }
            return 'initial';
        } else {
            // For non-inline mode, return var() with fallback injected
            if ($joinedFallback !== null) {
                return "var({$prefixedPath}, {$joinedFallback})";
            }
            return "var({$prefixedPath})";
        }
    }
    // Inject the fallback into nested var()/--theme() references that have 'initial' fallback
    // This handles: --default-font-family: --theme(--font-sans, initial) where --font-sans exists
    // -> should become var(--prefix-default-font-family, caller-fallback) when --font-sans has 'initial' fallback
    if ($joinedFallback !== null && (str_starts_with($value, 'var(') || str_starts_with($value, 'theme(') || str_starts_with($value, '--theme('))) {
        return injectFallbackForInitialFallback($value, $joinedFallback, $prefixedPath, $inline);
    }
    // Apply opacity modifier if present
    if ($opacity !== null && $opacity !== '') {
        // Normalize opacity to a float (0-1 range)
        $opacityFloat = floatval($opacity);
        if ($opacityFloat > 1) {
            $opacityFloat = $opacityFloat / 100;
        }
        if ($inline) {
            // For inline, convert to oklab color without alpha channel
            return \BlockstudioVendor\TailwindPHP\LightningCss\LightningCss::colorToOklabWithOpacity($value, $opacityFloat);
        } else {
            // For non-inline, return color-mix with var() reference
            // Normalize opacity: 0.5 -> 50%, 50 -> 50%
            $opacityValue = $opacity;
            if (is_numeric($opacity) && floatval($opacity) <= 1) {
                $opacityValue = floatval($opacity) * 100 . '%';
            } elseif (!str_ends_with($opacity, '%')) {
                $opacityValue = $opacity . '%';
            }
            return "color-mix(in oklab, var({$prefixedPath}) {$opacityValue}, transparent)";
        }
    }
    if ($inline) {
        // Return the actual value
        return $value;
    }
    // Return var() reference with optional fallback
    if (!empty($fallback)) {
        return "var({$prefixedPath}, " . implode(', ', $fallback) . ')';
    }
    return "var({$prefixedPath})";
}
/**
 * Handle --spacing() function.
 *
 * @param array $node Function node
 * @param object $designSystem Design system instance
 * @return string|null Resolved value or null
 */
function handleSpacing(array $node, object $designSystem): ?string
{
    $argsStr = trim(toCss($node['nodes'] ?? []));
    if ($argsStr === '') {
        return null;
    }
    $theme = $designSystem->getTheme();
    $multiplier = $theme->resolve(null, ['--spacing']);
    if ($multiplier === null) {
        return null;
    }
    return "calc({$multiplier} * {$argsStr})";
}
/**
 * Handle --alpha() function.
 *
 * @param array $node Function node
 * @param object $designSystem Design system instance
 * @return string|null Resolved value or null
 */
function handleAlpha(array $node, object $designSystem): ?string
{
    $argsStr = trim(toCss($node['nodes'] ?? []));
    $parts = segment($argsStr, '/');
    if (count($parts) !== 2) {
        return null;
    }
    $color = trim($parts[0]);
    $alpha = trim($parts[1]);
    if ($color === '' || $alpha === '') {
        return null;
    }
    // Use withAlpha utility which uses color-mix in oklab
    return withAlpha($color, $alpha);
}
/**
 * Eventually unquote a string value.
 *
 * @param string $value Value to unquote
 * @return string Unquoted value
 */
function eventuallyUnquote(string $value): string
{
    if (strlen($value) < 2) {
        return $value;
    }
    $first = $value[0];
    if ($first !== '"' && $first !== "'") {
        return $value;
    }
    $quoteChar = $first;
    $chars = [];
    $len = strlen($value) - 1;
    for ($i = 1; $i < $len; $i++) {
        $currentChar = $value[$i];
        $nextChar = $value[$i + 1] ?? '';
        if ($currentChar === '\\' && ($nextChar === $quoteChar || $nextChar === '\\')) {
            $chars[] = $nextChar;
            $i++;
        } else {
            $chars[] = $currentChar;
        }
    }
    return implode('', $chars);
}
/**
 * Maximum recursion depth for nested theme() resolution.
 * Prevents infinite loops from circular theme references.
 * Set high enough (50) to never limit legitimate use cases while still
 * preventing stack overflow from circular references.
 */
const MAX_THEME_RECURSION_DEPTH = 50;
/**
 * Recursively resolve nested theme() calls in a value.
 *
 * @param string $value Value that may contain theme() calls
 * @param object $designSystem Design system instance
 * @param int $depth Current recursion depth
 * @return string Value with theme() calls resolved
 */
function resolveNestedThemeCalls(string $value, object $designSystem, int $depth = 0): string
{
    // Prevent infinite recursion from circular references
    if ($depth >= MAX_THEME_RECURSION_DEPTH) {
        return $value;
    }
    if (!str_contains($value, 'theme(')) {
        return $value;
    }
    $ast = parseValue($value);
    walk($ast, function (&$node) use ($designSystem, $depth) {
        if ($node['kind'] !== 'function') {
            return WalkAction::Continue;
        }
        if ($node['value'] === 'theme') {
            $result = handleLegacyTheme($node, $designSystem, $depth + 1);
            if ($result !== null) {
                return WalkAction::Replace(parseValue($result));
            }
        }
        return WalkAction::Continue;
    });
    return toCss($ast);
}
/**
 * Resolve --theme() calls in a stored theme value for initial fallback handling.
 *
 * This is called when a theme value contains --theme() calls that need to be resolved
 * before we can determine if the result is 'initial' or a var() reference.
 *
 * @param string $value Value containing --theme() calls
 * @param object $designSystem Design system instance
 * @param bool $inline Whether to force inline resolution
 * @return string Resolved value
 */
function resolveNestedThemeCallsForInitial(string $value, object $designSystem, bool $inline): string
{
    // Parse the value and walk to find --theme() function calls
    $ast = parseValue($value);
    $theme = $designSystem->getTheme();
    $prefix = $theme->getPrefix();
    walk($ast, function (&$node) use ($designSystem, $theme, $prefix, $inline) {
        if ($node['kind'] !== 'function') {
            return WalkAction::Continue;
        }
        if ($node['value'] !== '--theme') {
            return WalkAction::Continue;
        }
        // Extract args from the --theme() call
        $argsStr = toCss($node['nodes'] ?? []);
        $args = array_map('trim', segment(trim($argsStr), ','));
        if (empty($args) || $args[0] === '') {
            return WalkAction::Continue;
        }
        $path = $args[0];
        $fallback = array_slice($args, 1);
        $innerInline = $inline;
        // Handle `--theme(… inline)` modifier
        if (str_ends_with($path, ' inline')) {
            $innerInline = \true;
            $path = substr($path, 0, -7);
        }
        if (!str_starts_with($path, '--')) {
            return WalkAction::Continue;
        }
        // Look up the value in the theme (without prefix)
        $innerValue = $theme->get([$path]);
        if ($innerValue === null) {
            // Not found - use fallback or 'initial'
            if (!empty($fallback)) {
                return WalkAction::Replace(parseValue(implode(', ', $fallback)));
            }
            return WalkAction::Replace(parseValue('initial'));
        }
        // Apply prefix for output
        $prefixedPath = $path;
        if ($prefix !== null && str_starts_with($path, '--')) {
            $prefixedPath = '--' . $prefix . '-' . substr($path, 2);
        }
        if ($innerInline) {
            return WalkAction::Replace(parseValue($innerValue));
        }
        // Return var() reference
        return WalkAction::Replace(parseValue("var({$prefixedPath})"));
    });
    return toCss($ast);
}
/**
 * Inject a fallback value into a var()/theme()/--theme() reference that has 'initial' fallback.
 *
 * This implements the TypeScript injectFallbackForInitialFallback function.
 * When a theme value resolves to var(--foo) or var(--foo, initial), and the caller
 * provides a fallback, we inject the caller's fallback.
 *
 * @param string $value The resolved value (starts with var/theme/--theme)
 * @param string $fallback The caller's fallback to inject
 * @param string $prefixedPath The prefixed path for the variable
 * @param bool $inline Whether in inline mode
 * @return string Result with fallback injected
 */
function injectFallbackForInitialFallback(string $value, string $fallback, string $prefixedPath, bool $inline): string
{
    $ast = parseValue($value);
    walk($ast, function (&$node) use ($fallback) {
        if ($node['kind'] !== 'function') {
            return WalkAction::Continue;
        }
        if ($node['value'] !== 'var' && $node['value'] !== 'theme' && $node['value'] !== '--theme') {
            return WalkAction::Continue;
        }
        $nodes = $node['nodes'] ?? [];
        if (count($nodes) === 1) {
            // No fallback - add one
            $node['nodes'][] = ['kind' => 'word', 'value' => ", {$fallback}"];
        } else {
            // Has fallback - check if it's 'initial'
            $lastNode =& $node['nodes'][count($node['nodes']) - 1];
            if ($lastNode['kind'] === 'word' && trim($lastNode['value']) === 'initial') {
                // Replace 'initial' with our fallback
                $lastNode['value'] = str_replace('initial', $fallback, $lastNode['value']);
            } elseif ($lastNode['kind'] === 'word' && str_contains($lastNode['value'], 'initial')) {
                // Handle case like ", initial"
                $lastNode['value'] = str_replace('initial', $fallback, $lastNode['value']);
            }
        }
        return WalkAction::Continue;
    });
    $result = toCss($ast);
    // If in non-inline mode and the result is still just the inner value,
    // wrap it in our outer var()
    if (!$inline && !str_starts_with($result, "var({$prefixedPath}")) {
        return "var({$prefixedPath}, {$fallback})";
    }
    return $result;
}
