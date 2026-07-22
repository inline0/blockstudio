<?php

declare (strict_types=1);
/**
 * Port of: packages/tailwindcss/src/plugin-api.ts
 *
 * Plugin system for TailwindPHP - allows plugins to register utilities,
 * variants, and components matching the TailwindCSS v4 plugin API.
 *
 * @port-deviation:async PHP uses synchronous code instead of async/await
 * @port-deviation:types PHPDoc annotations instead of TypeScript types
 */
namespace BlockstudioVendor\TailwindPHP\Plugin;

use BlockstudioVendor\TailwindPHP\Theme;
use BlockstudioVendor\TailwindPHP\Utilities\Utilities;
use BlockstudioVendor\TailwindPHP\Variants\Variants;
// ==================================================
// Plugin Interface
// ==================================================
/**
 * Contract for TailwindPHP plugins.
 *
 * Plugins implement this interface to register utilities, variants,
 * and components with TailwindPHP.
 */
interface PluginInterface
{
    /**
     * Get the plugin name/identifier.
     *
     * This is used to reference the plugin in @plugin directives.
     * e.g., '@tailwindcss/typography' for @plugin "@tailwindcss/typography"
     *
     * @return string
     */
    public function getName(): string;
    /**
     * Execute the plugin, registering utilities/variants/components.
     *
     * @param PluginAPI $api The plugin API instance
     * @param array $options Options passed from @plugin directive
     */
    public function __invoke(PluginAPI $api, array $options = []): void;
    /**
     * Get the plugin's theme extensions.
     *
     * Returns an array that will be merged into the theme config.
     * This is equivalent to the second argument of plugin.withOptions().
     *
     * @param array $options Options passed from @plugin directive
     * @return array Theme configuration to merge
     */
    public function getThemeExtensions(array $options = []): array;
}
// ==================================================
// Plugin API
// ==================================================
/**
 * Interface for TailwindCSS plugins.
 *
 * This mirrors the TailwindCSS v4 plugin API exactly, allowing plugins
 * to register utilities, variants, and components.
 *
 * @see https://tailwindcss.com/docs/plugins
 */
class PluginAPI
{
    private Theme $theme;
    private Utilities $utilities;
    private Variants $variants;
    private array $config;
    private array $baseStyles = [];
    private array $componentStyles = [];
    public function __construct(Theme $theme, Utilities $utilities, Variants $variants, array $config = [])
    {
        $this->theme = $theme;
        $this->utilities = $utilities;
        $this->variants = $variants;
        $this->config = $config;
    }
    /**
     * Add base styles (applied to @layer base).
     */
    public function addBase(array $css): void
    {
        $this->baseStyles[] = $css;
    }
    /**
     * Get all registered base styles.
     */
    public function getBaseStyles(): array
    {
        return $this->baseStyles;
    }
    /**
     * Add static utility classes.
     */
    public function addUtilities(array $utilities, array $options = []): void
    {
        foreach ($utilities as $className => $css) {
            $this->registerUtility($className, $css, $options);
        }
    }
    /**
     * Add functional utilities that accept values.
     */
    public function matchUtilities(array $utilities, array $options = []): void
    {
        $values = $options['values'] ?? [];
        $supportsNegativeValues = $options['supportsNegativeValues'] ?? \false;
        foreach ($utilities as $name => $callback) {
            foreach ($values as $key => $value) {
                $className = $key === 'DEFAULT' ? $name : "{$name}-{$key}";
                $css = $callback($value, ['modifier' => null]);
                if ($css !== null) {
                    $this->registerUtility(".{$className}", $css, $options);
                }
                if ($supportsNegativeValues && is_numeric($value)) {
                    $negativeValue = $this->negate($value);
                    $negativeClassName = "-{$className}";
                    $negativeCss = $callback($negativeValue, ['modifier' => null]);
                    if ($negativeCss !== null) {
                        $this->registerUtility(".{$negativeClassName}", $negativeCss, $options);
                    }
                }
            }
            $this->utilities->addFunctional($name, $callback, $options);
        }
    }
    /**
     * Add static component classes.
     */
    public function addComponents(array $components, array $options = []): void
    {
        foreach ($components as $className => $css) {
            $this->componentStyles[$className] = $css;
            $this->registerUtility($className, $css, array_merge($options, ['layer' => 'components']));
        }
    }
    /**
     * Get all registered component styles.
     */
    public function getComponentStyles(): array
    {
        return $this->componentStyles;
    }
    /**
     * Add functional components that accept values.
     */
    public function matchComponents(array $components, array $options = []): void
    {
        $this->matchUtilities($components, array_merge($options, ['layer' => 'components']));
    }
    /**
     * Add a static variant.
     */
    public function addVariant(string $name, string|array $variant): void
    {
        $this->variants->addPluginVariant($name, $variant);
    }
    /**
     * Add a functional variant that accepts values.
     */
    public function matchVariant(string $name, callable $callback, array $options = []): void
    {
        $values = $options['values'] ?? [];
        foreach ($values as $key => $value) {
            $variantName = $key === 'DEFAULT' ? $name : "{$name}-{$key}";
            $selector = $callback($value, ['modifier' => null]);
            $this->variants->addPluginVariant($variantName, $selector);
        }
        $this->variants->addFunctionalVariant($name, $callback, $options);
    }
    /**
     * Get a value from the theme.
     */
    public function theme(string $path, mixed $defaultValue = null): mixed
    {
        $modifier = null;
        if (str_contains($path, '/')) {
            $parts = explode('/', $path, 2);
            $path = trim($parts[0]);
            $modifier = trim($parts[1]);
        }
        // First check config for theme overrides (e.g., theme.typography from compile options)
        $themeConfig = $this->config['theme'] ?? [];
        if (!empty($themeConfig)) {
            $configValue = $this->resolvePath($themeConfig, $path, null);
            if ($configValue !== null) {
                return $configValue;
            }
        }
        $value = $this->resolveThemePath($path, $defaultValue);
        if ($modifier !== null && is_string($value)) {
            return $this->applyOpacityModifier($value, $modifier);
        }
        return $value;
    }
    /**
     * Get a value from the config.
     */
    public function config(?string $path = null, mixed $defaultValue = null): mixed
    {
        if ($path === null) {
            return $this->config;
        }
        return $this->resolvePath($this->config, $path, $defaultValue);
    }
    /**
     * Get the configured prefix.
     */
    public function prefix(string $className): string
    {
        $prefix = $this->theme->getPrefix();
        return $prefix === null ? $className : "{$prefix}:{$className}";
    }
    private function registerUtility(string $className, array $css, array $options): void
    {
        $name = ltrim($className, '.');
        $declarations = $this->cssToDeclarations($css);
        $this->utilities->addPluginUtility($name, $declarations, $options);
    }
    private function cssToDeclarations(array $css): array
    {
        $declarations = [];
        foreach ($css as $property => $value) {
            // Skip integer keys - they're not valid CSS property names
            if (is_int($property)) {
                continue;
            }
            if (is_array($value)) {
                if ($this->isNestedSelector($property)) {
                    $declarations[$property] = $this->cssToDeclarations($value);
                } else {
                    foreach ($value as $v) {
                        $declarations[] = [$this->toKebabCase($property), (string) $v];
                    }
                }
            } else {
                // Ensure value is a string
                $declarations[$this->toKebabCase($property)] = is_int($value) || is_float($value) ? (string) $value : $value;
            }
        }
        return $declarations;
    }
    private function isNestedSelector(string|int $property): bool
    {
        if (is_int($property)) {
            return \false;
        }
        return str_starts_with($property, '&') || str_starts_with($property, '@') || str_starts_with($property, '.') || str_contains($property, ' ') || str_contains($property, ':') || str_contains($property, '>');
    }
    private function toKebabCase(string|int $str): string
    {
        if (is_int($str)) {
            return (string) $str;
        }
        return strtolower(preg_replace('/([A-Z])/', '-$1', $str));
    }
    private function resolveThemePath(string $path, mixed $default): mixed
    {
        $parts = explode('.', $path);
        $namespace = array_shift($parts);
        $namespaceMap = ['colors' => '--color', 'spacing' => '--spacing', 'fontSize' => '--font-size', 'fontFamily' => '--font-family', 'fontWeight' => '--font-weight', 'lineHeight' => '--line-height', 'letterSpacing' => '--letter-spacing', 'borderRadius' => '--radius', 'borderWidth' => '--border-width', 'boxShadow' => '--shadow', 'opacity' => '--opacity', 'zIndex' => '--z-index', 'width' => '--width', 'height' => '--height', 'maxWidth' => '--max-width', 'screens' => '--breakpoint'];
        $themeNamespace = $namespaceMap[$namespace] ?? "--{$this->toKebabCase($namespace)}";
        if (empty($parts)) {
            return $this->theme->namespace($themeNamespace);
        }
        $themeKey = $themeNamespace . '-' . implode('-', $parts);
        $value = $this->theme->get([$themeKey]);
        return $value ?? $default;
    }
    private function resolvePath(array $data, string $path, mixed $default): mixed
    {
        $parts = explode('.', $path);
        $current = $data;
        foreach ($parts as $part) {
            if (!is_array($current) || !array_key_exists($part, $current)) {
                return $default;
            }
            $current = $current[$part];
        }
        return $current;
    }
    private function negate(string $value): string
    {
        return str_starts_with($value, '-') ? substr($value, 1) : "-{$value}";
    }
    private function applyOpacityModifier(string $value, string $opacity): string
    {
        if (str_ends_with($opacity, '%')) {
            $opacity = rtrim($opacity, '%');
        }
        return "color-mix(in oklab, {$value} {$opacity}%, transparent)";
    }
}
// ==================================================
// Plugin Manager
// ==================================================
/**
 * Handles plugin registration and execution.
 */
class PluginManager
{
    /** @var array<string, PluginInterface> */
    private array $plugins = [];
    /** @var array<string, class-string<PluginInterface>> */
    private static array $builtInPlugins = ['@tailwindcss/typography' => \BlockstudioVendor\TailwindPHP\Plugin\Plugins\TypographyPlugin::class, '@tailwindcss/forms' => \BlockstudioVendor\TailwindPHP\Plugin\Plugins\FormsPlugin::class];
    public function register(PluginInterface $plugin): void
    {
        $this->plugins[$plugin->getName()] = $plugin;
    }
    public static function registerBuiltIn(string $name, string $class): void
    {
        self::$builtInPlugins[$name] = $class;
    }
    public function has(string $name): bool
    {
        return isset($this->plugins[$name]) || isset(self::$builtInPlugins[$name]);
    }
    public function get(string $name): ?PluginInterface
    {
        if (isset($this->plugins[$name])) {
            return $this->plugins[$name];
        }
        if (isset(self::$builtInPlugins[$name])) {
            $class = self::$builtInPlugins[$name];
            $this->plugins[$name] = new $class();
            return $this->plugins[$name];
        }
        return null;
    }
    public function execute(string $name, PluginAPI $api, array $options = []): bool
    {
        $plugin = $this->get($name);
        if ($plugin === null) {
            return \false;
        }
        $plugin($api, $options);
        return \true;
    }
    public function getThemeExtensions(string $name, array $options = []): array
    {
        $plugin = $this->get($name);
        return $plugin === null ? [] : $plugin->getThemeExtensions($options);
    }
    public function getRegisteredPlugins(): array
    {
        return array_unique(array_merge(array_keys($this->plugins), array_keys(self::$builtInPlugins)));
    }
    public function createAPI(Theme $theme, Utilities $utilities, Variants $variants, array $config = []): PluginAPI
    {
        return new PluginAPI($theme, $utilities, $variants, $config);
    }
    public function applyPlugins(array $pluginRefs, Theme $theme, Utilities $utilities, Variants $variants, array $config = []): PluginAPI
    {
        $api = $this->createAPI($theme, $utilities, $variants, $config);
        foreach ($pluginRefs as $ref) {
            if (is_string($ref)) {
                $name = $ref;
                $options = [];
            } else {
                $name = $ref['name'];
                $options = $ref['options'] ?? [];
            }
            $themeExtensions = $this->getThemeExtensions($name, $options);
            $this->applyThemeExtensions($theme, $themeExtensions);
            $this->execute($name, $api, $options);
        }
        return $api;
    }
    private function applyThemeExtensions(Theme $theme, array $extensions): void
    {
        foreach ($extensions as $namespace => $values) {
            if (!is_array($values)) {
                continue;
            }
            $themeNamespace = '--' . strtolower(preg_replace('/([A-Z])/', '-$1', $namespace));
            foreach ($values as $key => $value) {
                if ($key === 'DEFAULT') {
                    $theme->add($themeNamespace, $value);
                } else {
                    $theme->add("{$themeNamespace}-{$key}", $value);
                }
            }
        }
    }
}
