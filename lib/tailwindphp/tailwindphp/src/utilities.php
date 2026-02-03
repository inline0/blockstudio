<?php

declare (strict_types=1);
namespace BlockstudioVendor\TailwindPHP\Utilities;

use function BlockstudioVendor\TailwindPHP\Ast\atRule;
use function BlockstudioVendor\TailwindPHP\Ast\decl;
use BlockstudioVendor\TailwindPHP\Candidate\UtilitiesInterface;
use BlockstudioVendor\TailwindPHP\Theme;
use BlockstudioVendor\TailwindPHP\Utils\DefaultMap;
use function BlockstudioVendor\TailwindPHP\Utils\isPositiveInteger;
use function BlockstudioVendor\TailwindPHP\Utils\isStrictPositiveInteger;
use function BlockstudioVendor\TailwindPHP\Utils\isValidOpacityValue;
use function BlockstudioVendor\TailwindPHP\Utils\isValidSpacingMultiplier;
use function BlockstudioVendor\TailwindPHP\Utils\segment;
/**
 * Utilities - Utility registry and core utility functions.
 *
 * Port of: packages/tailwindcss/src/utilities.ts
 *
 * @port-deviation:structure TypeScript's utilities.ts is 6000+ lines with all utilities inline.
 * PHP splits utilities into separate files under src/utilities/ for maintainability.
 *
 * @port-deviation:suggestions TypeScript includes IDE suggestion infrastructure (SuggestionGroup,
 * SuggestionDefinition, completions). PHP omits these since IDE tooling isn't the primary use case.
 *
 * @port-deviation:featureFlags TypeScript uses enableContainerSizeUtility feature flag.
 * PHP implements utilities directly without feature flag gating.
 *
 * @port-deviation:types PHP uses UtilitiesInterface for candidate.php integration.
 */
const IS_VALID_STATIC_UTILITY_NAME = '/^-?[a-z][a-zA-Z0-9\/%._-]*$/';
const IS_VALID_FUNCTIONAL_UTILITY_NAME = '/^-?[a-z][a-zA-Z0-9\/%._-]*-\*$/';
const DEFAULT_SPACING_SUGGESTIONS = ['0', '0.5', '1', '1.5', '2', '2.5', '3', '3.5', '4', '5', '6', '7', '8', '9', '10', '11', '12', '14', '16', '20', '24', '28', '32', '36', '40', '44', '48', '52', '56', '60', '64', '72', '80', '96'];
/**
 * Utility class to manage utility registrations.
 */
class Utilities implements UtilitiesInterface
{
    /**
     * @var DefaultMap<string, array<array{kind: string, compileFn: callable, options?: array}>>
     */
    private DefaultMap $utilities;
    /**
     * @var array<string, callable>
     */
    private array $completions = [];
    public function __construct()
    {
        $this->utilities = new DefaultMap(fn() => []);
    }
    /**
     * Register a static utility.
     *
     * @param string $name
     * @param callable $compileFn
     * @return void
     */
    public function static(string $name, callable $compileFn): void
    {
        $utilities = $this->utilities->get($name);
        $utilities[] = ['kind' => 'static', 'compileFn' => $compileFn];
        $this->utilities->set($name, $utilities);
    }
    /**
     * Register a functional utility.
     *
     * @param string $name
     * @param callable $compileFn
     * @param array|null $options
     * @return void
     */
    public function functional(string $name, callable $compileFn, ?array $options = null): void
    {
        $utilities = $this->utilities->get($name);
        $utility = ['kind' => 'functional', 'compileFn' => $compileFn];
        if ($options !== null) {
            $utility['options'] = $options;
        }
        $utilities[] = $utility;
        $this->utilities->set($name, $utilities);
    }
    /**
     * Check if a utility exists with a given kind.
     *
     * @param string $name
     * @param string $kind 'static' or 'functional'
     * @return bool
     */
    public function has(string $name, string $kind): bool
    {
        if (!$this->utilities->has($name)) {
            return \false;
        }
        $utils = $this->utilities->get($name);
        foreach ($utils as $util) {
            if ($util['kind'] === $kind) {
                return \true;
            }
        }
        return \false;
    }
    /**
     * Get all utilities for a name.
     *
     * @param string $name
     * @return array
     */
    public function get(string $name): array
    {
        if (!$this->utilities->has($name)) {
            return [];
        }
        return $this->utilities->get($name);
    }
    /**
     * Get completions for a utility.
     *
     * @param string $name
     * @return array
     */
    public function getCompletions(string $name): array
    {
        if ($this->has($name, 'static')) {
            if (isset($this->completions[$name])) {
                return $this->completions[$name]();
            }
            return [['supportsNegative' => \false, 'values' => [], 'modifiers' => []]];
        }
        if (isset($this->completions[$name])) {
            return $this->completions[$name]();
        }
        return [];
    }
    /**
     * Register suggestion groups for a utility.
     *
     * @param string $name
     * @param callable $groups
     * @return void
     */
    public function suggest(string $name, callable $groups): void
    {
        if (isset($this->completions[$name])) {
            $existingGroups = $this->completions[$name];
            $this->completions[$name] = fn() => array_merge($existingGroups(), $groups());
        } else {
            $this->completions[$name] = $groups;
        }
    }
    /**
     * Get all utility keys of a specific kind.
     *
     * @param string $kind 'static' or 'functional'
     * @return array<string>
     */
    public function keys(string $kind): array
    {
        $keys = [];
        foreach ($this->utilities->entries() as [$key, $fns]) {
            foreach ($fns as $fn) {
                if ($fn['kind'] === $kind) {
                    $keys[] = $key;
                    break;
                }
            }
        }
        return $keys;
    }
    /**
     * @var array<string, array{callback: callable, options: array}>
     */
    private array $functionalPluginUtilities = [];
    /**
     * @var array<string, array{declarations: array, options: array}>
     */
    private array $pluginUtilities = [];
    /**
     * Add a utility from a plugin (static utility).
     *
     * @param string $name Utility class name (without dot)
     * @param array $declarations CSS declarations
     * @param array $options Plugin options
     */
    public function addPluginUtility(string $name, array $declarations, array $options = []): void
    {
        $this->pluginUtilities[$name] = ['declarations' => $declarations, 'options' => $options];
        // Also register as static utility for compilation
        $this->static($name, function () use ($declarations) {
            return $this->declarationsToAst($declarations);
        });
    }
    /**
     * Add a functional utility from a plugin (matchUtilities).
     *
     * @param string $name Utility base name
     * @param callable $callback Function that generates CSS
     * @param array $options Plugin options including values, type, etc.
     */
    public function addFunctional(string $name, callable $callback, array $options = []): void
    {
        $this->functionalPluginUtilities[$name] = ['callback' => $callback, 'options' => $options];
    }
    /**
     * Check if a plugin utility exists.
     *
     * @param string $name
     * @return bool
     */
    public function hasPluginUtility(string $name): bool
    {
        return isset($this->pluginUtilities[$name]);
    }
    /**
     * Get a plugin utility.
     *
     * @param string $name
     * @return array|null
     */
    public function getPluginUtility(string $name): ?array
    {
        return $this->pluginUtilities[$name] ?? null;
    }
    /**
     * Get all plugin utilities.
     *
     * @return array<string, array{declarations: array, options: array}>
     */
    public function getPluginUtilities(): array
    {
        return $this->pluginUtilities;
    }
    /**
     * Get all functional plugin utilities.
     *
     * @return array<string, array{callback: callable, options: array}>
     */
    public function getFunctionalPluginUtilities(): array
    {
        return $this->functionalPluginUtilities;
    }
    /**
     * Convert declarations array to AST nodes.
     *
     * @param array $declarations
     * @return array
     */
    private function declarationsToAst(array $declarations): array
    {
        $nodes = [];
        foreach ($declarations as $property => $value) {
            if (is_int($property)) {
                // Tuple format [$property, $value] - this must come first
                if (is_array($value) && isset($value[0], $value[1])) {
                    $nodes[] = decl($value[0], (string) $value[1]);
                }
            } elseif (is_array($value)) {
                // Nested selector
                $nodes[] = \BlockstudioVendor\TailwindPHP\Ast\rule($property, $this->declarationsToAst($value));
            } else {
                $nodes[] = decl($property, (string) $value);
            }
        }
        return $nodes;
    }
}
/**
 * Create a @property at-rule for CSS custom properties.
 *
 * @param string $ident
 * @param string|null $initialValue
 * @param string|null $syntax
 * @return array
 */
function property(string $ident, ?string $initialValue = null, ?string $syntax = null): array
{
    $nodes = [decl('syntax', $syntax ? "\"{$syntax}\"" : '"*"'), decl('inherits', 'false')];
    // initial-value must come after inherits
    if ($initialValue !== null) {
        // For <length> syntax, LightningCSS strips units from zero values
        // e.g., "0px" -> "0"
        $optimizedValue = $initialValue;
        if ($syntax === '<length>' && preg_match('/^0(px|rem|em|%)$/', $initialValue)) {
            $optimizedValue = '0';
        }
        $nodes[] = decl('initial-value', $optimizedValue);
    }
    return atRule('@property', $ident, $nodes);
}
/**
 * Apply opacity to a color using `color-mix`.
 *
 * When the color already has an alpha channel (e.g., oklab with / .5),
 * this function computes the stacked opacity directly.
 *
 * Returns null for invalid alpha values (silently fails like Tailwind).
 *
 * @param string $value
 * @param string|null $alpha
 * @param bool $inline If true, compute the oklab value instead of using color-mix
 * @return string|null
 */
function withAlpha(string $value, ?string $alpha, bool $inline = \false): ?string
{
    if ($alpha === null || $alpha === '') {
        return $value;
    }
    // Check if alpha contains a CSS variable - handle separately
    if (str_contains($alpha, 'var(')) {
        // Normalize the color for consistency
        $normalizedValue = \BlockstudioVendor\TailwindPHP\LightningCss\LightningCss::normalizeColors($value);
        // Return color-mix with the variable opacity
        return "color-mix(in oklab, {$normalizedValue} {$alpha}, transparent)";
    }
    // Convert alpha to a decimal (0-1 range)
    $alphaDecimal = parseAlphaToDecimal($alpha);
    // Invalid alpha value - silently fail (return null)
    if ($alphaDecimal === null) {
        return null;
    }
    // No need for color-mix if the alpha is 100%
    if ($alphaDecimal === 1.0) {
        return $value;
    }
    // Check if the value is an oklab with an existing alpha
    // e.g., oklab(62.7955% .224 .125 / .5)
    if (preg_match('/^oklab\(([^\/]+)\/\s*([\d.]+%?)\s*\)$/i', $value, $match)) {
        $oklabComponents = trim($match[1]);
        $existingAlpha = parseAlphaToDecimal($match[2]);
        // Invalid existing alpha - return original value
        if ($existingAlpha === null) {
            return $value;
        }
        // Compute stacked opacity
        $stackedAlpha = $existingAlpha * $alphaDecimal;
        // Format the stacked alpha (keep precision, remove trailing zeros)
        $stackedAlphaStr = formatAlpha($stackedAlpha);
        return "oklab({$oklabComponents} / {$stackedAlphaStr})";
    }
    // For inline mode, compute the actual oklab value with alpha
    if ($inline) {
        return \BlockstudioVendor\TailwindPHP\LightningCss\LightningCss::colorToOklabWithOpacity($value, $alphaDecimal, \true);
    }
    // Normalize the color (e.g., #f00 -> red) for consistency with TailwindCSS output
    $normalizedValue = \BlockstudioVendor\TailwindPHP\LightningCss\LightningCss::normalizeColors($value);
    // Convert alpha back to percentage for color-mix
    $alphaPercent = $alphaDecimal * 100 . '%';
    return "color-mix(in oklab, {$normalizedValue} {$alphaPercent}, transparent)";
}
/**
 * Parse an alpha value to a decimal (0-1 range).
 *
 * Returns null for invalid values (out of range, non-numeric).
 *
 * @param string $alpha
 * @return float|null
 */
function parseAlphaToDecimal(string $alpha): ?float
{
    $alpha = trim($alpha);
    // Skip non-numeric values (e.g., CSS variables, calc expressions)
    // These are handled elsewhere
    if (!is_numeric(rtrim($alpha, '%'))) {
        return null;
    }
    if (str_ends_with($alpha, '%')) {
        $val = floatval(substr($alpha, 0, -1)) / 100;
    } else {
        $val = floatval($alpha);
        // If value > 1, assume it's a percentage
        if ($val > 1) {
            $val = $val / 100;
        }
    }
    // Validate range: must be 0-1
    if ($val < 0 || $val > 1) {
        return null;
    }
    return $val;
}
/**
 * Format an alpha value for oklab output.
 *
 * @param float $alpha
 * @return string
 */
function formatAlpha(float $alpha): string
{
    // Format with enough precision, removing trailing zeros
    $str = rtrim(rtrim(number_format($alpha, 6, '.', ''), '0'), '.');
    // Ensure we have a leading zero or the decimal point itself
    if ($str === '' || $str === '.') {
        return '0';
    }
    return '.' . ltrim($str, '0.');
}
/**
 * Replace the alpha channel of a color.
 *
 * @param string $value
 * @param string $alpha
 * @return string
 */
function replaceAlpha(string $value, string $alpha): string
{
    // Convert numeric values to percentages
    if (is_numeric($alpha)) {
        $alpha = floatval($alpha) * 100 . '%';
    }
    return "oklab(from {$value} l a b / {$alpha})";
}
/**
 * Resolve a color value + optional opacity modifier to a final color.
 *
 * @param string $value
 * @param array|null $modifier
 * @param Theme $theme
 * @return string|null
 */
function asColor(string $value, ?array $modifier, Theme $theme): ?string
{
    if ($modifier === null) {
        return $value;
    }
    if ($modifier['kind'] === 'arbitrary') {
        return withAlpha($value, $modifier['value']);
    }
    // Check if the modifier exists in the `opacity` theme configuration
    $alpha = $theme->resolve($modifier['value'], ['--opacity']);
    if ($alpha) {
        return withAlpha($value, $alpha);
    }
    if (!isValidOpacityValue($modifier['value'])) {
        return null;
    }
    // The modifier is a bare value like `50`, so convert that to `50%`.
    return withAlpha($value, $modifier['value'] . '%');
}
/**
 * Resolve a theme color for a candidate.
 *
 * @param array $candidate Functional candidate
 * @param Theme $theme
 * @param array $themeKeys
 * @return string|null
 */
function resolveThemeColor(array $candidate, Theme $theme, array $themeKeys): ?string
{
    if (!isset($candidate['value']) || $candidate['value']['kind'] !== 'named') {
        return null;
    }
    $value = null;
    switch ($candidate['value']['value']) {
        case 'inherit':
            $value = 'inherit';
            break;
        case 'transparent':
            $value = 'transparent';
            break;
        case 'current':
            $value = 'currentcolor';
            break;
        default:
            $value = $theme->resolve($candidate['value']['value'], $themeKeys);
            break;
    }
    return $value ? asColor($value, $candidate['modifier'] ?? null, $theme) : null;
}
/**
 * Helper class for registering utilities with a theme.
 */
class UtilityBuilder
{
    private Utilities $utilities;
    private Theme $theme;
    public function __construct(Utilities $utilities, Theme $theme)
    {
        $this->utilities = $utilities;
        $this->theme = $theme;
    }
    /**
     * Get the utilities instance.
     */
    public function getUtilities(): Utilities
    {
        return $this->utilities;
    }
    /**
     * Get the theme instance.
     */
    public function getTheme(): Theme
    {
        return $this->theme;
    }
    /**
     * Register a static utility class like `justify-center`.
     *
     * @param string $className
     * @param array $declarations Array of [property, value] tuples or callables
     */
    public function staticUtility(string $className, array $declarations): void
    {
        $this->utilities->static($className, function () use ($declarations) {
            return array_map(function ($node) {
                return is_callable($node) ? $node() : \BlockstudioVendor\TailwindPHP\Ast\decl($node[0], $node[1]);
            }, $declarations);
        });
    }
    /**
     * Register a functional utility class like `max-w-*`.
     *
     * @param string $classRoot
     * @param array $desc Utility description
     */
    public function functionalUtility(string $classRoot, array $desc): void
    {
        $theme = $this->theme;
        $utilities = $this->utilities;
        $handleFunctionalUtility = function (bool $negative) use ($theme, $desc) {
            return function (array $candidate) use ($theme, $desc, $negative) {
                $value = null;
                $dataType = null;
                if (!isset($candidate['value']) || $candidate['value'] === null) {
                    if (isset($candidate['modifier']) && $candidate['modifier'] !== null) {
                        return null;
                    }
                    // Use defaultValue or resolve from theme
                    if (array_key_exists('defaultValue', $desc)) {
                        $value = $desc['defaultValue'];
                    } else {
                        $value = $theme->resolve(null, $desc['themeKeys'] ?? []);
                    }
                } elseif ($candidate['value']['kind'] === 'arbitrary') {
                    if (isset($candidate['modifier']) && $candidate['modifier'] !== null) {
                        return null;
                    }
                    $value = $candidate['value']['value'];
                    $dataType = $candidate['value']['dataType'] ?? null;
                } else {
                    // Check for numeric fractions (like flex-1/2)
                    $hasNumericFraction = isset($candidate['value']['fraction']) && isset($candidate['modifier']['value']) && ctype_digit($candidate['modifier']['value']);
                    $lookupValue = $candidate['value']['fraction'] ?? $candidate['value']['value'];
                    // Theme resolution first (matches original TailwindCSS order)
                    $value = $theme->resolve($lookupValue, $desc['themeKeys'] ?? []);
                    // If we have a modifier and no theme value resolved:
                    // - For named fractions (foo/bar): if theme value exists, modifier is consumed by fraction
                    // - For numeric fractions (1/2): modifier is consumed by fraction
                    // - Otherwise: reject - modifier not supported for this utility
                    $hasFraction = isset($candidate['value']['fraction']);
                    $fractionResolvedFromTheme = $value !== null && $hasFraction;
                    if (isset($candidate['modifier']) && $candidate['modifier'] !== null) {
                        if (!$hasNumericFraction && !$fractionResolvedFromTheme) {
                            return null;
                        }
                    }
                    // Handle fractions like w-1/2
                    if ($value === null && ($desc['supportsFractions'] ?? \false) && isset($candidate['value']['fraction'])) {
                        $parts = segment($candidate['value']['fraction'], '/');
                        // Numerator can be 0+, but denominator must be > 0 to avoid division by zero
                        if (count($parts) === 2 && isPositiveInteger($parts[0]) && isStrictPositiveInteger($parts[1])) {
                            // Format as "calc(1 / 2 * 100%)" with spaces around /
                            $value = "calc({$parts[0]} / {$parts[1]} * 100%)";
                        }
                    }
                    // Handle bare values with negative handler
                    if ($value === null && $negative && isset($desc['handleNegativeBareValue'])) {
                        $value = $desc['handleNegativeBareValue']($candidate['value']);
                        if ($value !== null && strpos($value, '/') === \false && isset($candidate['modifier'])) {
                            return null;
                        }
                        if ($value !== null) {
                            return $desc['handle']($value, null);
                        }
                    }
                    // Handle bare values (fallback after theme resolution)
                    if ($value === null && isset($desc['handleBareValue'])) {
                        $value = $desc['handleBareValue']($candidate['value']);
                        // If we got a value but there's an unconsumed modifier, reject it
                        // Exception: if it was a numeric fraction, the modifier was consumed
                        if ($value !== null && strpos($value, '/') === \false && isset($candidate['modifier']) && !$hasNumericFraction) {
                            return null;
                        }
                    }
                    // Handle static values as fallback
                    if ($value === null && !$negative && isset($desc['staticValues']) && !isset($candidate['modifier'])) {
                        $fallback = $desc['staticValues'][$candidate['value']['value']] ?? null;
                        if ($fallback !== null) {
                            return array_map('BlockstudioVendor\TailwindPHP\Ast\cloneAstNode', $fallback);
                        }
                    }
                }
                if ($value === null) {
                    return null;
                }
                // Negate the value if needed
                if ($negative) {
                    $value = "calc({$value} * -1)";
                }
                return $desc['handle']($value, $dataType);
            };
        };
        if ($desc['supportsNegative'] ?? \false) {
            $utilities->functional("-{$classRoot}", $handleFunctionalUtility(\true));
        }
        $utilities->functional($classRoot, $handleFunctionalUtility(\false));
        // Add suggestions
        $this->suggest($classRoot, fn() => [['supportsNegative' => $desc['supportsNegative'] ?? \false, 'valueThemeKeys' => $desc['themeKeys'] ?? [], 'hasDefaultValue' => array_key_exists('defaultValue', $desc) && $desc['defaultValue'] !== null, 'supportsFractions' => $desc['supportsFractions'] ?? \false]]);
        // Add static value suggestions
        if (isset($desc['staticValues']) && count($desc['staticValues']) > 0) {
            $values = array_keys($desc['staticValues']);
            $this->suggest($classRoot, fn() => [['values' => $values]]);
        }
    }
    /**
     * Register a color utility class.
     *
     * @param string $classRoot
     * @param array $desc Color utility description
     */
    public function colorUtility(string $classRoot, array $desc): void
    {
        $theme = $this->theme;
        $this->utilities->functional($classRoot, function (array $candidate) use ($theme, $desc) {
            if (!isset($candidate['value'])) {
                return null;
            }
            $value = null;
            if ($candidate['value']['kind'] === 'arbitrary') {
                $value = $candidate['value']['value'];
                $value = asColor($value, $candidate['modifier'] ?? null, $theme);
            } else {
                $value = resolveThemeColor($candidate, $theme, $desc['themeKeys']);
            }
            if ($value === null) {
                return null;
            }
            return $desc['handle']($value);
        });
        $this->suggest($classRoot, fn() => [['values' => ['current', 'inherit', 'transparent'], 'valueThemeKeys' => $desc['themeKeys'], 'modifiers' => array_map(fn($i) => (string) ($i * 5), range(0, 20))]]);
    }
    /**
     * Register a spacing utility.
     *
     * @param string $name
     * @param array $themeKeys
     * @param callable $handle
     * @param array $options
     */
    public function spacingUtility(string $name, array $themeKeys, callable $handle, array $options = []): void
    {
        $supportsNegative = $options['supportsNegative'] ?? \false;
        $supportsFractions = $options['supportsFractions'] ?? \false;
        $staticValues = $options['staticValues'] ?? null;
        $theme = $this->theme;
        if ($supportsNegative) {
            $this->utilities->static("-{$name}-px", fn() => $handle('-1px'));
        }
        $this->utilities->static("{$name}-px", fn() => $handle('1px'));
        $this->functionalUtility($name, ['themeKeys' => $themeKeys, 'supportsFractions' => $supportsFractions, 'supportsNegative' => $supportsNegative, 'defaultValue' => null, 'handleBareValue' => function ($value) use ($theme) {
            // Fallback: if no theme value found, use calc(var(--spacing) * N)
            $multiplier = $theme->resolve(null, ['--spacing']);
            if ($multiplier === null) {
                return null;
            }
            if (!isValidSpacingMultiplier($value['value'])) {
                return null;
            }
            return "calc({$multiplier} * {$value['value']})";
        }, 'handleNegativeBareValue' => function ($value) use ($theme) {
            $multiplier = $theme->resolve(null, ['--spacing']);
            if ($multiplier === null) {
                return null;
            }
            if (!isValidSpacingMultiplier($value['value'])) {
                return null;
            }
            return "calc({$multiplier} * -{$value['value']})";
        }, 'staticValues' => $staticValues, 'handle' => $handle]);
    }
    /**
     * Register suggestions for a utility.
     *
     * @param string $name
     * @param callable $groups
     */
    public function suggest(string $name, callable $groups): void
    {
        $this->utilities->suggest($name, $groups);
    }
}
/**
 * Create utilities with the given theme.
 *
 * @param Theme $theme
 * @return Utilities
 */
function createUtilities(Theme $theme): Utilities
{
    $utilities = new Utilities();
    $builder = new UtilityBuilder($utilities, $theme);
    // Register all utilities by loading individual utility files
    registerAccessibilityUtilities($builder);
    registerLayoutUtilities($builder);
    registerFlexboxUtilities($builder);
    registerSpacingUtilities($builder);
    registerSizingUtilities($builder);
    registerTypographyUtilities($builder);
    registerBackgroundUtilities($builder);
    registerBorderUtilities($builder);
    registerEffectsUtilities($builder);
    registerFiltersUtilities($builder);
    registerTablesUtilities($builder);
    registerTransitionsUtilities($builder);
    registerTransformsUtilities($builder);
    registerInteractivityUtilities($builder);
    registerSvgUtilities($builder);
    registerMaskUtilities($builder);
    return $utilities;
}
// Include individual utility registration files
require_once __DIR__ . '/utilities/accessibility.php';
require_once __DIR__ . '/utilities/layout.php';
require_once __DIR__ . '/utilities/flexbox.php';
require_once __DIR__ . '/utilities/spacing.php';
require_once __DIR__ . '/utilities/sizing.php';
require_once __DIR__ . '/utilities/typography.php';
require_once __DIR__ . '/utilities/borders.php';
require_once __DIR__ . '/utilities/effects.php';
require_once __DIR__ . '/utilities/tables.php';
require_once __DIR__ . '/utilities/transitions.php';
require_once __DIR__ . '/utilities/transforms.php';
require_once __DIR__ . '/utilities/filters.php';
require_once __DIR__ . '/utilities/interactivity.php';
require_once __DIR__ . '/utilities/svg.php';
require_once __DIR__ . '/utilities/backgrounds.php';
require_once __DIR__ . '/utilities/masks.php';
