<?php

declare (strict_types=1);
namespace BlockstudioVendor\TailwindPHP;

use function BlockstudioVendor\TailwindPHP\Utils\escape;
use function BlockstudioVendor\TailwindPHP\Utils\unescape;
/**
 * Theme - Theme value management and resolution.
 *
 * Port of: packages/tailwindcss/src/theme.ts
 *
 * @port-deviation:storage TypeScript uses Map<string, ...> for values and Set<AtRule> for keyframes.
 * PHP uses associative arrays for both since PHP doesn't have native Map/Set types.
 *
 * @port-deviation:sourcemaps TypeScript stores Declaration['src'] for source map tracking.
 * PHP omits source tracking as source maps aren't implemented.
 *
 * @port-deviation:enum TypeScript uses enum ThemeOptions.
 * PHP uses constants (THEME_OPTION_*) for PHP 8.1 compatibility.
 */
// Theme option flags (using constants instead of enum for PHP 8.1 compatibility)
const THEME_OPTION_NONE = 0;
const THEME_OPTION_INLINE = 1 << 0;
// 1
const THEME_OPTION_REFERENCE = 1 << 1;
// 2
const THEME_OPTION_DEFAULT = 1 << 2;
// 4
const THEME_OPTION_STATIC = 1 << 3;
// 8
const THEME_OPTION_USED = 1 << 4;
// 16
/**
 * Map of theme keys to keys that should be ignored when looking up values.
 * For example, --font should not match --font-weight or --font-size.
 */
const IGNORED_THEME_KEY_MAP = ['--font' => ['--font-weight', '--font-size'], '--inset' => ['--inset-shadow', '--inset-ring'], '--text' => ['--text-color', '--text-decoration-color', '--text-decoration-thickness', '--text-indent', '--text-shadow', '--text-underline-offset'], '--grid-column' => ['--grid-column-start', '--grid-column-end'], '--grid-row' => ['--grid-row-start', '--grid-row-end']];
/**
 * Check if a theme key should be ignored for a given namespace.
 *
 * @param string $themeKey
 * @param string $namespace
 * @return bool
 */
function isIgnoredThemeKey(string $themeKey, string $namespace): bool
{
    $ignored = IGNORED_THEME_KEY_MAP[$namespace] ?? [];
    foreach ($ignored as $ignoredKey) {
        if ($themeKey === $ignoredKey || str_starts_with($themeKey, "{$ignoredKey}-")) {
            return \true;
        }
    }
    return \false;
}
/**
 * Theme class - manages theme values and provides resolution methods.
 *
 * @port-deviation:performance LRU cache added for resolveKey() lookups.
 * The original TypeScript doesn't cache lookups, but PHP benefits from
 * avoiding repeated array iterations during compilation.
 */
class Theme
{
    // Theme option constants as class constants for easier access
    public const OPTIONS_NONE = THEME_OPTION_NONE;
    public const OPTIONS_INLINE = THEME_OPTION_INLINE;
    public const OPTIONS_REFERENCE = THEME_OPTION_REFERENCE;
    public const OPTIONS_DEFAULT = THEME_OPTION_DEFAULT;
    public const OPTIONS_STATIC = THEME_OPTION_STATIC;
    public const OPTIONS_USED = THEME_OPTION_USED;
    private const CACHE_MAX_SIZE = 256;
    public ?string $prefix = null;
    /**
     * @var array<string, array{value: string, options: int, src: mixed}>
     */
    private array $values = [];
    /**
     * @var array<string, array{node: array, options: int}>
     */
    private array $keyframes = [];
    /**
     * Cache for resolveKey() lookups.
     * Key format: candidateValue|namespace1|namespace2|...
     *
     * @var array<string, string|null>
     */
    private array $resolveKeyCache = [];
    /**
     * @param array<string, array{value: string, options: int, src: mixed}> $values
     * @param array<string, array{node: array, options: int}> $keyframes
     */
    public function __construct(array $values = [], array $keyframes = [])
    {
        $this->values = $values;
        $this->keyframes = $keyframes;
    }
    /**
     * Get the number of theme values.
     *
     * @return int
     */
    public function size(): int
    {
        return count($this->values);
    }
    /**
     * Get the prefix.
     *
     * @return string|null
     */
    public function getPrefix(): ?string
    {
        return $this->prefix;
    }
    /**
     * Add a theme value.
     *
     * @param string $key
     * @param string $value
     * @param int $options
     * @param mixed $src
     * @return void
     */
    public function add(string $key, string $value, int $options = THEME_OPTION_NONE, mixed $src = null): void
    {
        // Clear resolve cache when values change
        $this->resolveKeyCache = [];
        // Handle namespace wildcards (e.g., --color-* to clear color namespace)
        if (str_ends_with($key, '-*')) {
            if ($value !== 'initial') {
                throw new \InvalidArgumentException("Invalid theme value `{$value}` for namespace `{$key}`");
            }
            if ($key === '--*') {
                $this->values = [];
            } else {
                $this->clearNamespace(
                    substr($key, 0, -2),
                    // `--${key}-*: initial;` should clear _all_ theme values
                    THEME_OPTION_NONE
                );
            }
        }
        // Default values should not override non-default values
        if ($options & THEME_OPTION_DEFAULT) {
            $existing = $this->values[$key] ?? null;
            if ($existing && !($existing['options'] & THEME_OPTION_DEFAULT)) {
                return;
            }
        }
        if ($value === 'initial') {
            unset($this->values[$key]);
        } else {
            $this->values[$key] = ['value' => $value, 'options' => $options, 'src' => $src];
        }
    }
    /**
     * Get all keys in the given namespaces.
     *
     * @param iterable<string> $themeKeys
     * @return array<string>
     */
    public function keysInNamespaces(iterable $themeKeys): array
    {
        $keys = [];
        foreach ($themeKeys as $namespace) {
            $prefix = "{$namespace}-";
            foreach (array_keys($this->values) as $key) {
                if (!str_starts_with($key, $prefix)) {
                    continue;
                }
                // Skip keys that have additional namespaces (e.g., --color-red-500--alpha)
                if (strpos($key, '--', 2) !== \false) {
                    continue;
                }
                if (isIgnoredThemeKey($key, $namespace)) {
                    continue;
                }
                $keys[] = substr($key, strlen($prefix));
            }
        }
        return $keys;
    }
    /**
     * Get the first matching theme value.
     *
     * @param array<string> $themeKeys
     * @return string|null
     */
    public function get(array $themeKeys): ?string
    {
        foreach ($themeKeys as $key) {
            if (isset($this->values[$key])) {
                return $this->values[$key]['value'];
            }
        }
        return null;
    }
    /**
     * Check if a key has default options.
     *
     * @param string $key
     * @return bool
     */
    public function hasDefault(string $key): bool
    {
        return ($this->getOptions($key) & THEME_OPTION_DEFAULT) === THEME_OPTION_DEFAULT;
    }
    /**
     * Get options for a key.
     *
     * @param string $key
     * @return int
     */
    public function getOptions(string $key): int
    {
        $key = unescape($this->unprefixKey($key));
        return $this->values[$key]['options'] ?? THEME_OPTION_NONE;
    }
    /**
     * Get all entries, prefixed if a prefix is set.
     *
     * @return iterable<array{0: string, 1: array{value: string, options: int, src: mixed}}>
     */
    public function entries(): iterable
    {
        if (!$this->prefix) {
            foreach ($this->values as $key => $value) {
                yield [$key, $value];
            }
            return;
        }
        foreach ($this->values as $key => $value) {
            yield [$this->prefixKey($key), $value];
        }
    }
    /**
     * Prefix a key with the theme prefix.
     *
     * @param string $key
     * @return string
     */
    public function prefixKey(string $key): string
    {
        if (!$this->prefix) {
            return $key;
        }
        return "--{$this->prefix}-" . substr($key, 2);
    }
    /**
     * Remove the prefix from a key.
     *
     * @param string $key
     * @return string
     */
    private function unprefixKey(string $key): string
    {
        if (!$this->prefix) {
            return $key;
        }
        return '--' . substr($key, 3 + strlen($this->prefix));
    }
    /**
     * Clear all values in a namespace.
     *
     * @param string $namespace
     * @param int $clearOptions
     * @return void
     */
    public function clearNamespace(string $namespace, int $clearOptions): void
    {
        $ignored = IGNORED_THEME_KEY_MAP[$namespace] ?? [];
        foreach (array_keys($this->values) as $key) {
            if (!str_starts_with($key, $namespace)) {
                continue;
            }
            if ($clearOptions !== THEME_OPTION_NONE) {
                $options = $this->getOptions($key);
                if (($options & $clearOptions) !== $clearOptions) {
                    continue;
                }
            }
            $shouldSkip = \false;
            foreach ($ignored as $ignoredNamespace) {
                if (str_starts_with($key, $ignoredNamespace)) {
                    $shouldSkip = \true;
                    break;
                }
            }
            if ($shouldSkip) {
                continue;
            }
            unset($this->values[$key]);
        }
    }
    /**
     * Resolve a theme key from candidate value and namespaces.
     *
     * @param string|null $candidateValue
     * @param array<string> $themeKeys
     * @return string|null
     */
    private function resolveKey(?string $candidateValue, array $themeKeys): ?string
    {
        // Build cache key
        $cacheKey = ($candidateValue ?? '') . '|' . implode('|', $themeKeys);
        // Check cache first
        if (array_key_exists($cacheKey, $this->resolveKeyCache)) {
            return $this->resolveKeyCache[$cacheKey];
        }
        // Compute result
        $result = null;
        foreach ($themeKeys as $namespace) {
            $themeKey = $candidateValue !== null ? "{$namespace}-{$candidateValue}" : $namespace;
            if (!isset($this->values[$themeKey])) {
                // If the exact theme key is not found, we might be trying to resolve a key containing a dot
                // that was registered with an underscore instead:
                if ($candidateValue !== null && str_contains($candidateValue, '.')) {
                    $themeKey = "{$namespace}-" . str_replace('.', '_', $candidateValue);
                    if (!isset($this->values[$themeKey])) {
                        continue;
                    }
                } else {
                    continue;
                }
            }
            if (isIgnoredThemeKey($themeKey, $namespace)) {
                continue;
            }
            $result = $themeKey;
            break;
        }
        // Cache with size limit (FIFO eviction)
        if (count($this->resolveKeyCache) >= self::CACHE_MAX_SIZE) {
            array_shift($this->resolveKeyCache);
        }
        $this->resolveKeyCache[$cacheKey] = $result;
        return $result;
    }
    /**
     * Get a CSS var() reference for a theme key.
     *
     * @param string $themeKey
     * @return string|null
     */
    private function var(string $themeKey): ?string
    {
        $value = $this->values[$themeKey] ?? null;
        if (!$value) {
            return null;
        }
        // Since @theme blocks in reference mode do not emit the CSS variables, we can not assume that
        // the values will eventually be set up in the browser (e.g. when using `@apply` inside roots
        // that use `@reference`). Ensure we set up a fallback in these cases.
        $fallback = null;
        if ($value['options'] & THEME_OPTION_REFERENCE) {
            $fallback = $value['value'];
        }
        $prefixedKey = escape($this->prefixKey($themeKey));
        return "var({$prefixedKey}" . ($fallback ? ", {$fallback}" : '') . ')';
    }
    /**
     * Mark a theme variable as used.
     *
     * @param string $themeKey
     * @return bool True if this is the first time the variable was marked as used
     */
    public function markUsedVariable(string $themeKey): bool
    {
        $key = unescape($this->unprefixKey($themeKey));
        if (!isset($this->values[$key])) {
            return \false;
        }
        $isUsed = $this->values[$key]['options'] & THEME_OPTION_USED;
        $this->values[$key]['options'] |= THEME_OPTION_USED;
        return !$isUsed;
    }
    /**
     * Resolve a theme value.
     *
     * @param string|null $candidateValue
     * @param array<string> $themeKeys
     * @param int $options
     * @return string|null
     */
    public function resolve(?string $candidateValue, array $themeKeys, int $options = THEME_OPTION_NONE): ?string
    {
        $themeKey = $this->resolveKey($candidateValue, $themeKeys);
        if (!$themeKey) {
            return null;
        }
        $value = $this->values[$themeKey];
        if (($options | $value['options']) & THEME_OPTION_INLINE) {
            return $value['value'];
        }
        return $this->var($themeKey);
    }
    /**
     * Resolve the raw value (not a var() reference).
     *
     * @param string|null $candidateValue
     * @param array<string> $themeKeys
     * @return string|null
     */
    public function resolveValue(?string $candidateValue, array $themeKeys): ?string
    {
        $themeKey = $this->resolveKey($candidateValue, $themeKeys);
        if (!$themeKey) {
            return null;
        }
        return $this->values[$themeKey]['value'];
    }
    /**
     * Resolve a theme value with nested keys.
     *
     * @param string $candidateValue
     * @param array<string> $themeKeys
     * @param array<string> $nestedKeys
     * @return array{0: string, 1: array<string, string>}|null
     */
    public function resolveWith(string $candidateValue, array $themeKeys, array $nestedKeys = []): ?array
    {
        $themeKey = $this->resolveKey($candidateValue, $themeKeys);
        if (!$themeKey) {
            return null;
        }
        $extra = [];
        foreach ($nestedKeys as $name) {
            $nestedKey = "{$themeKey}{$name}";
            $nestedValue = $this->values[$nestedKey] ?? null;
            if (!$nestedValue) {
                continue;
            }
            if ($nestedValue['options'] & THEME_OPTION_INLINE) {
                $extra[$name] = $nestedValue['value'];
            } else {
                $extra[$name] = $this->var($nestedKey);
            }
        }
        $value = $this->values[$themeKey];
        if ($value['options'] & THEME_OPTION_INLINE) {
            return [$value['value'], $extra];
        }
        return [$this->var($themeKey), $extra];
    }
    /**
     * Get all values in a namespace.
     *
     * @param string $namespace
     * @return array<string|null, string>
     */
    public function namespace(string $namespace): array
    {
        $values = [];
        $prefix = "{$namespace}-";
        foreach ($this->values as $key => $value) {
            if ($key === $namespace) {
                $values[null] = $value['value'];
            } elseif (str_starts_with($key, "{$prefix}-")) {
                // Preserve `--` prefix for sub-variables
                // e.g. `--font-size-sm--line-height`
                $values[substr($key, strlen($namespace))] = $value['value'];
            } elseif (str_starts_with($key, $prefix)) {
                $values[substr($key, strlen($prefix))] = $value['value'];
            }
        }
        return $values;
    }
    /**
     * Add keyframes to the theme.
     *
     * @param array $node
     * @param int $options
     * @return void
     */
    public function addKeyframes(array $node, int $options = THEME_OPTION_NONE): void
    {
        $name = trim($node['params'] ?? '');
        $this->keyframes[$name] = ['node' => $node, 'options' => $options];
    }
    /**
     * Get all keyframes.
     *
     * @return array<array>
     */
    public function getKeyframes(): array
    {
        return array_map(fn($kf) => $kf['node'], $this->keyframes);
    }
    /**
     * Get options for a keyframe.
     *
     * @param string $name
     * @return int
     */
    public function getKeyframeOptions(string $name): int
    {
        return $this->keyframes[$name]['options'] ?? THEME_OPTION_NONE;
    }
    /**
     * Check if a keyframe is registered in the theme.
     *
     * @param string $name
     * @return bool
     */
    public function hasKeyframe(string $name): bool
    {
        return isset($this->keyframes[$name]);
    }
    /**
     * Check if a value exists for a key.
     *
     * @param string $key
     * @return bool
     */
    public function has(string $key): bool
    {
        return isset($this->values[$key]);
    }
}
